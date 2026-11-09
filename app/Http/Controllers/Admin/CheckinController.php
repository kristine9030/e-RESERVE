<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Services\Audit;
use App\Services\Stats;
use App\Services\TicketService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Gate check-in and live attendance monitor.
 *
 * A scan writes a real row into `checkins`. The UNIQUE index on ticket_id is
 * what makes a ticket single-use; the signature in the QR payload is what makes
 * a fabricated ticket number fail.
 */
class CheckinController extends Controller
{
    private const GATES = ['Gate A', 'Gate B', 'Gate C'];

    public function __construct(private TicketService $tickets, private Stats $stats)
    {
    }

    public function index()
    {
        $scanLog  = $this->stats->scanLog();
        $expected = (int) DB::table('tickets')->where('status', '<>', 'void')->count();
        $admitted = (int) DB::table('checkins')->count();

        $byGate = [];
        foreach (DB::table('checkins')->select('gate', DB::raw('COUNT(*) AS n'))->groupBy('gate')->get() as $g) {
            $byGate[$g->gate] = (int) $g->n;
        }

        return view('admin.checkin', [
            'result'   => session('scan_result'),
            'scanLog'  => $scanLog,
            'expected' => $expected,
            'admitted' => $admitted,
            'fillPct'  => $expected > 0 ? (int) round($admitted / $expected * 100) : 0,
            'byGate'   => $byGate,
            'lastScan' => $scanLog[0]['time_in'] ?? null,
            'prefix'   => config('ereserve.ticket.prefix'),
        ]);
    }

    /**
     * Pull a ticket number out of whatever the scanner handed us: the bare code,
     * "code|signature", a verification URL or a JSON payload.
     */
    private function extractCode(string $raw): string
    {
        $raw = trim($raw);

        if ($raw !== '' && ($json = json_decode($raw, true)) && is_array($json) && isset($json['ticket'])) {
            $raw = (string) $json['ticket'];
        } elseif (stripos($raw, 'http') === 0) {
            parse_str((string) parse_url($raw, PHP_URL_QUERY), $query);
            $raw = (string) ($query['t'] ?? $raw);
        }

        if (preg_match('/' . preg_quote((string) config('ereserve.ticket.prefix'), '/') . '\d+/i', $raw, $m)) {
            return strtoupper($m[0]);
        }

        return strtoupper(trim(explode('|', $raw)[0]));
    }

    /** Form fallback (typed ticket number). Shows the result as a pop-up after redirect. */
    public function store(Request $request)
    {
        $result = $this->admit($request, (string) $request->input('ticket_no', ''), (string) $request->input('gate', ''));

        return redirect()->route('admin.checkin')->with('scan_result', Arr::except($result, 'ok'));
    }

    /** Camera scanner: same decision, returned as JSON so the page never reloads. */
    public function scan(Request $request)
    {
        $result = $this->admit($request, (string) $request->input('ticket_no', ''), (string) $request->input('gate', ''));

        return response()->json($result);
    }

    /**
     * Decide one scan.
     *
     * @return array{ok:bool,tone:string,title:string,text:string}
     */
    private function admit(Request $request, string $payload, string $gate): array
    {
        $code = $this->extractCode($payload);
        $gate = in_array($gate, self::GATES, true) ? $gate : 'Gate A';

        // fromPayload() also checks the signature carried in the QR, so an
        // edited code is rejected before it ever reaches the database.
        $ticket = $code === '' ? null : $this->tickets->fromPayload($payload);

        if ($code === '') {
            return ['ok' => false, 'tone' => 'warning', 'title' => 'No code entered', 'text' => 'Scan or type a ticket number first.'];
        }
        if (!$ticket) {
            return ['ok' => false, 'tone' => 'error', 'title' => 'Ticket not recognised',
                    'text' => 'No valid ticket matches <strong>' . e($code) . '</strong>. A fabricated or edited code fails this check.'];
        }
        if ($ticket['status'] === 'void') {
            return ['ok' => false, 'tone' => 'error', 'title' => 'Ticket voided',
                    'text' => '<strong>' . e($code) . '</strong> is no longer valid for entry.'];
        }

        try {
            $admitted = DB::transaction(function () use ($ticket, $gate, $request) {
                // Flip valid → used first: if the ticket was voided a moment ago this
                // matches nothing and nobody is admitted on a void ticket.
                $flipped = DB::table('tickets')->where('id', $ticket['id'])->where('status', 'valid')
                    ->update(['status' => 'used']);

                if (!$flipped) {
                    return false;
                }

                Checkin::create([
                    'ticket_id' => $ticket['id'], 'gate' => $gate,
                    'scanner' => mb_substr((string) $request->user()->name, 0, 64),
                    'scanned_by' => $request->user()->id, 'scanned_at' => now(),
                ]);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $admitted = false;   // UNIQUE(ticket_id): two gates scanning at once cannot both win.
        }

        if (!$admitted) {
            $seen = DB::table('checkins')->where('ticket_id', $ticket['id'])->first();

            return $seen
                ? ['ok' => false, 'tone' => 'warning', 'title' => 'Already checked in',
                   'text' => '<strong>' . e($ticket['ticket_no']) . '</strong> was admitted at '
                           . e($seen->gate ?? 'the gate') . ' on ' . pretty_date($seen->scanned_at ?? null) . '.']
                : ['ok' => false, 'tone' => 'error', 'title' => 'Ticket voided',
                   'text' => '<strong>' . e($code) . '</strong> is no longer valid for entry.'];
        }

        Audit::log('gate.checkin', 'ticket', $ticket['ticket_no'], ['gate' => $gate]);

        return ['ok' => true, 'tone' => 'success', 'title' => 'Welcome, ' . explode(' ', $ticket['name'])[0] . '!',
                'text' => '<strong>' . e($ticket['ticket_no']) . '</strong> · ' . e($ticket['student_no'])
                        . '<br>' . e($ticket['program']) . '<br>Admitted at ' . e($gate) . '.'];
    }

    /** A standalone QR as SVG, so a table of tickets ships one <img> per row. */
    public function qr(Request $request)
    {
        $payload = (string) $request->query('t', '');
        abort_if($payload === '', 400);

        $size = max(80, min(400, (int) $request->query('s', 170)));

        return response('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . qr_svg($payload, $size, ['label' => $payload]))
            ->header('Content-Type', 'image/svg+xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}

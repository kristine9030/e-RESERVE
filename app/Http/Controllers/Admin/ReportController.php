<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Services\ReportPdf;
use App\Services\ReportXlsx;
use App\Services\Stats;
use Illuminate\Http\Request;

/** Reports hub, the three official report forms, and their spreadsheet/CSV export. */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports, private Stats $stats)
    {
    }

    public function index()
    {
        $catalog = $this->reports->catalog();
        $counts  = [];
        foreach (array_keys($catalog) as $key) {
            $counts[$key] = count($this->reports->dataset($key)['rows']);
        }

        return view('reports.index', ['stats' => $this->stats->dashboard(), 'catalog' => $catalog, 'counts' => $counts]);
    }

    public function show(Request $request, string $report)
    {
        abort_unless($this->reports->has($report), 404);
        $f = $this->reports->cleanFilters($request->query());

        return view('reports.' . $report, ['ds' => $this->reports->dataset($report, $f), 'rpt' => $this->reports, 'filters' => $f] + $this->analysis($report, $f));
    }

    /** Screen-only analysis panels — they do not belong on the signed form. */
    private function analysis(string $report, array $f = []): array
    {
        if ($report === 'registrations') {
            $regs  = $this->reports->filteredRegistrations($f);
            $byDoc = [];
            foreach (config('ereserve.documents') as $key => $meta) {
                $byDoc[$key] = ['label' => $meta['label'], 'approved' => 0, 'submitted' => 0, 'rejected' => 0, 'missing' => 0];
            }
            $byYear = [];
            foreach ($regs as $r) {
                foreach ($byDoc as $key => $_) {
                    if (!($r['docs'][$key]['applicable'] ?? false)) {
                        continue;
                    }
                    $byDoc[$key][$r['docs'][$key]['status'] ?: 'missing']++;
                }
                $y = $r['year'] ?: 'Unspecified';
                $byYear[$y] = ($byYear[$y] ?? 0) + 1;
            }
            ksort($byYear);

            return ['byDoc' => $byDoc, 'byYear' => $byYear];
        }

        if ($report === 'tickets') {
            $tickets = $this->reports->filteredTickets($f);
            $byDay = $byStatus = [];
            foreach ($tickets as $t) {
                $d = date('Y-m-d', strtotime($t['issued_at']));
                $byDay[$d] = ($byDay[$d] ?? 0) + 1;
                $byStatus[$t['status']] = ($byStatus[$t['status']] ?? 0) + 1;
            }
            krsort($byDay);

            return ['byDay' => $byDay, 'byStatus' => $byStatus, 'remaining' => app(\App\Services\TicketService::class)->slotsRemaining()];
        }

        $log = $this->reports->filteredAttendance($f);
        $byGate = $byProgram = $byHour = [];
        foreach ($log as $r) {
            $byGate[$r['gate']]       ??= ['expected' => 0, 'present' => 0];
            $byProgram[$r['program']] ??= ['expected' => 0, 'present' => 0];
            $byGate[$r['gate']]['expected']++;
            $byProgram[$r['program']]['expected']++;
            if ($r['checked_in']) {
                $byGate[$r['gate']]['present']++;
                $byProgram[$r['program']]['present']++;
            }
            if ($r['checked_in'] && $r['time_in']) {
                $h = date('H', strtotime($r['time_in']));
                $byHour[$h] = ($byHour[$h] ?? 0) + 1;
            }
        }
        ksort($byGate);
        uasort($byProgram, static fn($a, $b) => $b['expected'] <=> $a['expected']);
        ksort($byHour);

        return ['byGate' => $byGate, 'byProgram' => $byProgram, 'byHour' => $byHour];
    }

    public function export(Request $request, string $report)
    {
        abort_unless($this->reports->has($report), 404);

        $ds       = $this->reports->dataset($report, $this->reports->cleanFilters($request->query()));
        $format   = strtolower((string) $request->query('format', 'sheet'));
        $basename = 'eRESERVE-' . $ds['code'] . '-' . date('Ymd-His');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($ds) {
                $out = fopen('php://output', 'wb');
                fwrite($out, "\xEF\xBB\xBF");          // BOM so Excel reads UTF-8
                $this->writeCsv($out, $ds, letterhead: true);
                fclose($out);
            }, $basename . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        if ($format === 'pdf') {
            return response(app(ReportPdf::class)->render($ds), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $basename . '.pdf"',
                'Cache-Control'       => 'max-age=0',
            ]);
        }

        // Spreadsheet: a real .xlsx laid out like the on-screen form and the printed paper.
        return response(app(ReportXlsx::class)->build($ds), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $basename . '.xlsx"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * The live feed for Google Sheets: =IMPORTDATA("<signed link>"). A clean table only
     * (header row, then records) that follows the filters baked into the link. Anyone with
     * the link can read it, so the signature is the password; it carries no login.
     */
    public function live(Request $request, string $report)
    {
        abort_unless($this->reports->has($report), 404);

        $ds = $this->reports->dataset($report, $this->reports->cleanFilters($request->query()));

        return response()->streamDownload(function () use ($ds) {
            $out = fopen('php://output', 'wb');
            $this->writeCsv($out, $ds, letterhead: false);
            fclose($out);
        }, 'eRESERVE-' . $ds['code'] . '.csv', [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Robots-Tag'  => 'noindex, nofollow',
        ], 'inline');
    }

    /** CSV rows. With the letterhead it mirrors the printed form; without, it is one clean table. */
    private function writeCsv($out, array $ds, bool $letterhead): void
    {
        $cols = $ds['columns'];
        if ($letterhead) {
            fputcsv($out, [mb_strtoupper((string) ev('host'))]);
            fputcsv($out, [mb_strtoupper((string) ev('org'))]);
            fputcsv($out, [$ds['title']]);
            fputcsv($out, [$ds['subtitle']]);
            fputcsv($out, [ev('name') . ' · ' . ev('date_text') . ' · ' . ev('venue')]);
            fputcsv($out, ['Report Code ' . $ds['code'], 'Generated ' . date('F d, Y · g:i A'), count($ds['rows']) . ' record' . (count($ds['rows']) === 1 ? '' : 's')]);
            fputcsv($out, []);
        }
        fputcsv($out, array_column($cols, 'label'));
        foreach ($ds['rows'] as $row) {
            fputcsv($out, array_map(fn($v, $i) => $this->reports->raw($cols[$i]['type'], $v), $row, array_keys($row)));
        }
        if ($letterhead) {
            fputcsv($out, []);
            fputcsv($out, ['SUMMARY']);
            foreach ($ds['summary'] as [$label, $value, $type]) {
                fputcsv($out, [$label, $this->reports->raw($type, $value)]);
            }
            fputcsv($out, []);
            fputcsv($out, array_map(static fn($s) => $s[0] . ':', $ds['signatories']));
            fputcsv($out, array_map(static fn($s) => $s[1], $ds['signatories']));
        }
    }
}

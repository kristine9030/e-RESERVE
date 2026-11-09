<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
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

    public function show(string $report)
    {
        abort_unless($this->reports->has($report), 404);

        return view('reports.' . $report, ['ds' => $this->reports->dataset($report), 'rpt' => $this->reports] + $this->analysis($report));
    }

    /** Screen-only analysis panels — they do not belong on the signed form. */
    private function analysis(string $report): array
    {
        if ($report === 'registrations') {
            $regs  = $this->stats->registrations();
            $byDoc = [];
            foreach (config('ereserve.documents') as $key => $meta) {
                $byDoc[$key] = ['label' => $meta['label'], 'approved' => 0, 'submitted' => 0, 'rejected' => 0, 'missing' => 0];
            }
            $byYear = [];
            foreach ($regs as $r) {
                foreach ($byDoc as $key => $_) {
                    $byDoc[$key][$r['docs'][$key]['status'] ?: 'missing']++;
                }
                $y = $r['year'] ?: 'Unspecified';
                $byYear[$y] = ($byYear[$y] ?? 0) + 1;
            }
            ksort($byYear);

            return ['byDoc' => $byDoc, 'byYear' => $byYear];
        }

        if ($report === 'tickets') {
            $tickets = $this->stats->issuedTickets();
            $byDay = $byStatus = [];
            foreach ($tickets as $t) {
                $d = date('Y-m-d', strtotime($t['issued_at']));
                $byDay[$d] = ($byDay[$d] ?? 0) + 1;
                $byStatus[$t['status']] = ($byStatus[$t['status']] ?? 0) + 1;
            }
            krsort($byDay);

            return ['byDay' => $byDay, 'byStatus' => $byStatus, 'remaining' => app(\App\Services\TicketService::class)->slotsRemaining()];
        }

        $log = $this->stats->attendanceLog();
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

        $ds       = $this->reports->dataset($report);
        $format   = strtolower((string) $request->query('format', 'sheet'));
        $basename = 'eRESERVE-' . $ds['code'] . '-' . date('Ymd-His');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($ds) {
                $out = fopen('php://output', 'wb');
                fwrite($out, "\xEF\xBB\xBF");          // BOM so Excel reads UTF-8
                fputcsv($out, [$ds['title'] . ' · ' . app_name()]);
                fputcsv($out, [ev('name') . ' · ' . ev('host')]);
                fputcsv($out, ['Report Code', $ds['code'], 'Generated', date('Y-m-d H:i:s')]);
                fputcsv($out, []);
                fputcsv($out, array_column($ds['columns'], 'label'));
                foreach ($ds['rows'] as $row) {
                    fputcsv($out, array_map(fn($v, $i) => $this->reports->raw($ds['columns'][$i]['type'], $v), $row, array_keys($row)));
                }
                fputcsv($out, []);
                foreach ($ds['summary'] as [$label, $value, $type]) {
                    fputcsv($out, [$label, $this->reports->raw($type, $value)]);
                }
                fclose($out);
            }, $basename . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        // Spreadsheet: an HTML workbook Excel and Google Sheets open with the design intact.
        return response()
            ->view('reports.sheet', ['ds' => $ds, 'rpt' => $this->reports])
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $basename . '.xls"')
            ->header('Cache-Control', 'max-age=0');
    }
}

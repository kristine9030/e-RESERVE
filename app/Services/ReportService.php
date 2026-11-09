<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * The three official reports, described once as a dataset (header, typed
 * columns, rows, summary, signatories). The on-screen table, the printed paper
 * form and the spreadsheet export all read that same description.
 */
class ReportService
{
    public const TEXT = 'text';
    public const NUM  = 'number';
    public const DATE = 'date';

    public function __construct(private Stats $stats)
    {
    }

    public function catalog(): array
    {
        $out = [];
        foreach (config('ereserve.reports') as $key => $meta) {
            $out[$key] = $meta + ['page' => route('reports.show', $key)];
        }

        return $out;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, config('ereserve.reports'));
    }

    public function dataset(string $key): array
    {
        $meta = config("ereserve.reports.$key") ?? throw new InvalidArgumentException("Unknown report: $key");
        $data = match ($key) {
            'registrations' => $this->registrations(),
            'tickets'       => $this->tickets(),
            'attendance'    => $this->attendance(),
        };

        return ['key' => $key, 'code' => $meta['code'], 'title' => $meta['title']] + $data;
    }

    private function col(string $label, string $type, int $width, bool $nowrap = false): array
    {
        return ['label' => $label, 'type' => $type, 'width' => $width, 'nowrap' => $nowrap];
    }

    private function registrations(): array
    {
        $regs  = $this->stats->registrations();
        $total = count(config('ereserve.documents'));
        $label = static fn(?string $s) => match ($s) {
            'approved' => 'Approved', 'submitted' => 'For review', 'rejected' => 'Returned', default => 'Missing',
        };

        $rows = [];
        $complete = 0;
        foreach ($regs as $i => $r) {
            $complete += $r['doc_count'] === $total ? 1 : 0;
            $rows[] = [
                $i + 1, $r['student_no'], $r['name'], $r['program'], $r['year'],
                $label($r['docs']['waiver']['status']), $label($r['docs']['cor']['status']), $label($r['docs']['id']['status']),
                $r['ticket_no'] ?: '—', $r['registered_at'],
            ];
        }

        return [
            'subtitle' => 'Student accounts and the status of their three required documents',
            'columns'  => [
                $this->col('No.', self::TEXT, 45, true), $this->col('SR-Code', self::TEXT, 110, true),
                $this->col('Name', self::TEXT, 200), $this->col('Program', self::TEXT, 210),
                $this->col('Year', self::TEXT, 80, true), $this->col('Waiver', self::TEXT, 90),
                $this->col('COR', self::TEXT, 90), $this->col('School ID', self::TEXT, 90),
                $this->col('Ticket No.', self::TEXT, 150, true), $this->col('Registered', self::DATE, 150, true),
            ],
            'rows'    => $rows,
            'summary' => [
                ['Registered Students', count($regs), self::NUM],
                ['Complete (3 of 3)', $complete, self::NUM],
                ['Incomplete', count($regs) - $complete, self::NUM],
                ['Completion Rate', (count($regs) ? round($complete / count($regs) * 100, 1) : 0) . '%', self::TEXT],
            ],
            'signatories' => [['Prepared by', 'SSC Secretary'], ['Verified by', 'SSC Auditor'], ['Approved by', 'SSC President']],
        ];
    }

    private function tickets(): array
    {
        $tickets = $this->stats->issuedTickets();
        $count   = static fn(string $s) => count(array_filter($tickets, static fn($t) => $t['status'] === $s));

        $rows = [];
        foreach ($tickets as $i => $t) {
            $rows[] = [$i + 1, $t['ticket_no'], $t['student_no'], $t['name'], $t['program'], $t['year'], $t['issued_at'], ucfirst($t['status'])];
        }

        return [
            'subtitle' => 'Ticket numbers issued against completed document submissions',
            'columns'  => [
                $this->col('No.', self::TEXT, 45, true), $this->col('Ticket No.', self::TEXT, 170, true),
                $this->col('SR-Code', self::TEXT, 110, true), $this->col('Name', self::TEXT, 210),
                $this->col('Program', self::TEXT, 230), $this->col('Year', self::TEXT, 80, true),
                $this->col('Issued', self::DATE, 150, true), $this->col('Status', self::TEXT, 90),
            ],
            'rows'    => $rows,
            'summary' => [
                ['Tickets Issued', count($tickets), self::NUM], ['Valid', $count('valid'), self::NUM],
                ['Used at Gate', $count('used'), self::NUM], ['Voided', $count('void'), self::NUM],
            ],
            'signatories' => [['Prepared by', 'SSC Secretary'], ['Audited by', 'SSC Auditor'], ['Approved by', 'SSC President']],
        ];
    }

    private function attendance(): array
    {
        $log     = $this->stats->attendanceLog();
        $present = count(array_filter($log, static fn($r) => $r['checked_in']));
        $rate    = count($log) > 0 ? round($present / count($log) * 100, 1) : 0.0;

        $rows = [];
        foreach ($log as $i => $r) {
            $rows[] = [
                $i + 1, $r['ticket_no'], $r['student_no'], $r['name'], $r['program'], $r['gate'],
                $r['time_in'] ?: '', $r['scanner'], $r['checked_in'] ? 'Checked In' : 'No Show',
            ];
        }

        return [
            'subtitle' => 'Gate check-in record for ' . config('ereserve.event.name'),
            'columns'  => [
                $this->col('No.', self::TEXT, 45, true), $this->col('Ticket No.', self::TEXT, 170, true),
                $this->col('SR-Code', self::TEXT, 110, true), $this->col('Name', self::TEXT, 200),
                $this->col('Program', self::TEXT, 230), $this->col('Gate', self::TEXT, 80),
                $this->col('Time In', self::DATE, 150, true), $this->col('Scanner', self::TEXT, 130),
                $this->col('Status', self::TEXT, 110),
            ],
            'rows'    => $rows,
            'summary' => [
                ['Tickets Issued', count($log), self::NUM], ['Checked In', $present, self::NUM],
                ['No Show', count($log) - $present, self::NUM], ['Attendance Rate', $rate . '%', self::TEXT],
            ],
            'signatories' => [['Prepared by', 'Gate Marshal'], ['Certified correct', 'SSC President'], ['Noted by', 'Office of Student Affairs']],
        ];
    }

    /** Human-readable value for the screen and the printed form. */
    public function format(string $type, $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            self::NUM  => number_format((float) $value),
            self::DATE => pretty_date((string) $value),
            default    => (string) $value,
        };
    }

    /** Spreadsheet value. */
    public function raw(string $type, $value): string
    {
        if ($type === self::DATE) {
            return ($value === null || $value === '') ? '' : date('Y-m-d H:i:s', strtotime((string) $value));
        }

        return (string) $value;
    }

    public function align(string $type): string
    {
        return $type === self::NUM ? 'right' : 'left';
    }
}

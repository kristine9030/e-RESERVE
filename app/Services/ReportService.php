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

    public function dataset(string $key, array $filters = []): array
    {
        $meta = config("ereserve.reports.$key") ?? throw new InvalidArgumentException("Unknown report: $key");
        $data = match ($key) {
            'registrations' => $this->registrations($filters),
            'tickets'       => $this->tickets($filters),
            'attendance'    => $this->attendance($filters),
        };

        return ['key' => $key, 'code' => $meta['code'], 'title' => $meta['title']] + $data;
    }

    private function col(string $label, string $type, int $width, bool $nowrap = false): array
    {
        return ['label' => $label, 'type' => $type, 'width' => $width, 'nowrap' => $nowrap];
    }

    /** Keep only the filters the registration report understands. */
    public function cleanFilters(array $f): array
    {
        $type = (string) ($f['type'] ?? '');
        $ok   = array_merge(['student', 'employee', 'sponsor'], array_keys(config('ereserve.employee_types')));
        $out  = [];
        if (in_array($type, $ok, true)) {
            $out['type'] = $type;
        }
        if (($out['type'] ?? '') === 'student') {          // college / program / year only make sense for students
            if (array_key_exists((string) ($f['college'] ?? ''), config('ereserve.colleges'))) {
                $out['college'] = $f['college'];
            }
            $pg = trim((string) ($f['program'] ?? ''));
            if ($pg !== '' && in_array($pg, array_merge(...array_values(config('ereserve.programs'))), true)) {
                $out['program'] = $pg;
            }
            if (in_array((string) ($f['year'] ?? ''), config('ereserve.year_levels'), true)) {
                $out['year'] = $f['year'];
            }
        }

        return $out;
    }

    /** Keep the rows (each with member_type, college, program, year) that match the filters. */
    private function narrow(array $rows, array $f): array
    {
        $f   = $this->cleanFilters($f);
        $emp = array_keys(config('ereserve.employee_types'));

        return array_values(array_filter($rows, static function (array $r) use ($f, $emp): bool {
            $mt = $r['member_type'];
            $okType = match ($f['type'] ?? '') {
                'student'  => $mt === 'student',
                'sponsor'  => $mt === 'sponsor',
                'employee' => in_array($mt, $emp, true),
                ''         => true,
                default    => $mt === $f['type'],
            };

            return $okType
                && (!isset($f['college']) || $r['college'] === $f['college'])
                && (!isset($f['program']) || $r['program'] === $f['program'])
                && (!isset($f['year']) || $r['year'] === $f['year']);
        }));
    }

    public function filteredTickets(array $f): array
    {
        return $this->narrow($this->stats->issuedTickets(), $f);
    }

    public function filteredAttendance(array $f): array
    {
        return $this->narrow($this->stats->attendanceLog(), $f);
    }

    public static function groupLabel(string $mt): string
    {
        return $mt === 'student' ? 'Student' : ($mt === 'sponsor' ? 'Sponsor' : (config('ereserve.employee_types')[$mt] ?? 'Employee'));
    }

    /** Subtitle with the active filter in front, or the plain one when nothing is filtered. */
    private function scoped(array $filters, string $plain): string
    {
        return $this->cleanFilters($filters) ? $this->scopeText($filters) . ' — ' . lcfirst($plain) : $plain;
    }

    /** Registrations narrowed by the filters and sorted group, college, program, year, name (print order). */
    public function filteredRegistrations(array $f): array
    {
        $regs = $this->narrow($this->stats->registrations(), $f);

        $order = array_flip(['student', 'faculty', 'instructor', 'staff', 'sponsor']);
        $years = array_flip(config('ereserve.year_levels'));
        usort($regs, static fn(array $a, array $b) => [$order[$a['member_type']] ?? 9, (string) $a['college'], (string) $a['program'], $years[$a['year']] ?? 9, $a['name']]
            <=> [$order[$b['member_type']] ?? 9, (string) $b['college'], (string) $b['program'], $years[$b['year']] ?? 9, $b['name']]);

        return $regs;
    }

    /** Human description of the active filters, printed under the report title. */
    public function scopeText(array $f): string
    {
        $f = $this->cleanFilters($f);
        $t = $f['type'] ?? '';
        $parts = [match (true) {
            $t === ''         => 'All registrants',
            $t === 'student'  => 'Students',
            $t === 'employee' => 'All faculty, instructors & staff',
            $t === 'sponsor'  => 'Sponsors',
            default           => config('ereserve.employee_types')[$t] ?? ucfirst($t),
        }];
        foreach (['college', 'program', 'year'] as $k) {
            if (isset($f[$k])) {
                $parts[] = $f[$k];
            }
        }

        return implode(' · ', $parts);
    }

    private function registrations(array $filters = []): array
    {
        $regs  = $this->filteredRegistrations($filters);
        $label = static fn(array $d) => !$d['applicable'] ? '—' : match ($d['status']) {
            'approved' => 'Approved', 'submitted' => 'For review', 'rejected' => 'Returned', default => 'Missing',
        };
        $group = static fn(string $mt) => $mt === 'student' ? 'Student' : ($mt === 'sponsor' ? 'Sponsor' : (config('ereserve.employee_types')[$mt] ?? 'Employee'));

        $rows = [];
        $complete = 0;
        foreach ($regs as $i => $r) {
            $complete += $r['doc_count'] === $r['doc_total'] ? 1 : 0;
            $rows[] = [
                $i + 1, $r['student_no'], $r['name'], $group($r['member_type']), $r['college'] ?: '—', $r['program'] ?: '—', $r['year'] ?: '—',
                $label($r['docs']['waiver']), $label($r['docs']['cor']), $label($r['docs']['id']),
                $label($r['docs']['employee_id']['applicable'] ? $r['docs']['employee_id'] : $r['docs']['sponsorship']),
                $r['ticket_no'] ?: '—', $r['registered_at'],
            ];
        }

        return [
            'subtitle' => $this->scopeText($filters) . ' — accounts and the status of their required documents',
            'columns'  => [
                $this->col('No.', self::TEXT, 45, true), $this->col('SR-Code', self::TEXT, 110, true),
                $this->col('Name', self::TEXT, 200), $this->col('Group', self::TEXT, 80, true),
                $this->col('College', self::TEXT, 80, true), $this->col('Program', self::TEXT, 210),
                $this->col('Year', self::TEXT, 80, true), $this->col('Waiver', self::TEXT, 90),
                $this->col('COR', self::TEXT, 90), $this->col('School ID', self::TEXT, 90), $this->col('Employee ID / Proof', self::TEXT, 120),
                $this->col('Ticket No.', self::TEXT, 150, true), $this->col('Registered', self::DATE, 150, true),
            ],
            'rows'    => $rows,
            'summary' => [
                ['Registrants', count($regs), self::NUM],
                ['Complete (all required uploaded)', $complete, self::NUM],
                ['Incomplete', count($regs) - $complete, self::NUM],
                ['Completion Rate', (count($regs) ? round($complete / count($regs) * 100, 1) : 0) . '%', self::TEXT],
            ],
            'signatories' => [['Prepared by', 'SSC Secretary'], ['Verified by', 'SSC Auditor'], ['Approved by', 'SSC President']],
        ];
    }

    private function tickets(array $filters = []): array
    {
        $tickets = $this->filteredTickets($filters);
        $count   = static fn(string $s) => count(array_filter($tickets, static fn($t) => $t['status'] === $s));

        $rows = [];
        foreach ($tickets as $i => $t) {
            $rows[] = [$i + 1, $t['ticket_no'], $t['student_no'], $t['name'], self::groupLabel($t['member_type']), $t['college'] ?: '—', $t['program'] ?: '—', $t['year'] ?: '—', $t['issued_at'], ucfirst($t['status'])];
        }

        return [
            'subtitle' => $this->scoped($filters, 'Ticket numbers issued against completed document submissions'),
            'columns'  => [
                $this->col('No.', self::TEXT, 45, true), $this->col('Ticket No.', self::TEXT, 170, true),
                $this->col('SR-Code', self::TEXT, 110, true), $this->col('Name', self::TEXT, 210),
                $this->col('Group', self::TEXT, 80, true), $this->col('College', self::TEXT, 80, true),
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

    private function attendance(array $filters = []): array
    {
        $log     = $this->filteredAttendance($filters);
        $present = count(array_filter($log, static fn($r) => $r['checked_in']));
        $rate    = count($log) > 0 ? round($present / count($log) * 100, 1) : 0.0;

        $rows = [];
        foreach ($log as $i => $r) {
            $rows[] = [
                $i + 1, $r['ticket_no'], $r['student_no'], $r['name'], self::groupLabel($r['member_type']), $r['college'] ?: '—', $r['program'] ?: '—', $r['year'] ?: '—', $r['gate'],
                $r['time_in'] ?: '', $r['scanner'], $r['checked_in'] ? 'Checked In' : 'No Show',
            ];
        }

        return [
            'subtitle' => $this->scoped($filters, 'Gate check-in record for ' . config('ereserve.event.name')),
            'columns'  => [
                $this->col('No.', self::TEXT, 45, true), $this->col('Ticket No.', self::TEXT, 170, true),
                $this->col('SR-Code', self::TEXT, 110, true), $this->col('Name', self::TEXT, 200),
                $this->col('Group', self::TEXT, 80, true), $this->col('College', self::TEXT, 80, true),
                $this->col('Program', self::TEXT, 230), $this->col('Year', self::TEXT, 80, true), $this->col('Gate', self::TEXT, 80),
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

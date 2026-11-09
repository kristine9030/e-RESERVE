<?php
/**
 * includes/reports.php — the single source of truth for the three official
 * e-RESERVE reports (Registration, Ticket, Attendance).
 *
 * Every report is described once as a dataset: header block, typed columns,
 * rows, summary figures and signatories. The on-screen table, the printed
 * paper form and the spreadsheet export all read that same description, so
 * the three can never drift apart.
 *
 * Only the row builders below touch the data layer.
 */

declare(strict_types=1);

/** Column types understood by the renderers. */
const RPT_TEXT  = 'text';
const RPT_NUM   = 'number';
const RPT_MONEY = 'money';
const RPT_DATE  = 'date';

/** The reports offered by the module, in menu order. */
function report_catalog(): array
{
    return [
        'registrations' => [
            'code'  => 'RPT-REG-01',
            'title' => 'Registration Report',
            'blurb' => 'Every student account with the status of their waiver, COR and school ID.',
            'icon'  => 'fa-clipboard-list',
            'nav'   => 'report_registrations',
            'page'  => 'reports/registrations.php',
        ],
        'tickets' => [
            'code'  => 'RPT-TIX-02',
            'title' => 'Ticket Issuance Report',
            'blurb' => 'Ticket numbers issued, when, to whom — and which have been voided or used.',
            'icon'  => 'fa-ticket',
            'nav'   => 'report_tickets',
            'page'  => 'reports/tickets.php',
        ],
        'attendance' => [
            'code'  => 'RPT-ATT-03',
            'title' => 'Attendance Report',
            'blurb' => 'Gate check-in record with per-gate and per-program totals.',
            'icon'  => 'fa-user-check',
            'nav'   => 'report_attendance',
            'page'  => 'reports/attendance.php',
        ],
    ];
}

function is_report_key(string $key): bool
{
    return array_key_exists($key, report_catalog());
}

/**
 * Build one report dataset.
 *
 * @return array{key:string,code:string,title:string,subtitle:string,columns:array,rows:array,summary:array,signatories:array}
 */
function report_dataset(string $key): array
{
    $meta = report_catalog()[$key] ?? null;
    if ($meta === null) {
        throw new InvalidArgumentException('Unknown report: ' . $key);
    }

    $build = 'report_rows_' . $key;
    $data  = $build();

    return [
        'key'         => $key,
        'code'        => $meta['code'],
        'title'       => $meta['title'],
        'subtitle'    => $data['subtitle'],
        'columns'     => $data['columns'],
        'rows'        => $data['rows'],
        'summary'     => $data['summary'],
        'signatories' => $data['signatories'],
    ];
}

/* --------------------------------------------------------------------------
 | Report 01 — Registration Report (accounts and documents)
 * ------------------------------------------------------------------------ */
function report_rows_registrations(): array
{
    $regs = registrations();

    $label = static fn(?string $s): string => match ($s) {
        'approved'  => 'Approved',
        'submitted' => 'For review',
        'rejected'  => 'Returned',
        default     => 'Missing',
    };

    $rows = [];
    $complete = 0;
    foreach ($regs as $i => $r) {
        if ($r['doc_count'] === count(DOCUMENT_TYPES)) {
            $complete++;
        }
        $rows[] = [
            $i + 1,
            $r['student_no'],
            $r['name'],
            $r['program'],
            $r['year'],
            $label($r['docs']['waiver']['status']),
            $label($r['docs']['cor']['status']),
            $label($r['docs']['id']['status']),
            $r['ticket_no'] ?: '—',
            $r['registered_at'],
        ];
    }

    return [
        'subtitle' => 'Student accounts and the status of their three required documents',
        'columns'  => [
            ['label' => 'No.',          'type' => RPT_TEXT, 'width' => 45,  'nowrap' => true],
            ['label' => 'SR-Code',  'type' => RPT_TEXT, 'width' => 110, 'nowrap' => true],
            ['label' => 'Name',         'type' => RPT_TEXT, 'width' => 200],
            ['label' => 'Program',      'type' => RPT_TEXT, 'width' => 210],
            ['label' => 'Year',         'type' => RPT_TEXT, 'width' => 80,  'nowrap' => true],
            ['label' => 'Waiver',       'type' => RPT_TEXT, 'width' => 90],
            ['label' => 'COR',          'type' => RPT_TEXT, 'width' => 90],
            ['label' => 'School ID',    'type' => RPT_TEXT, 'width' => 90],
            ['label' => 'Ticket No.',   'type' => RPT_TEXT, 'width' => 150, 'nowrap' => true],
            ['label' => 'Registered',   'type' => RPT_DATE, 'width' => 150, 'nowrap' => true],
        ],
        'rows'    => $rows,
        'summary' => [
            ['Registered Students', count($regs),             RPT_NUM],
            ['Complete (3 of 3)',   $complete,                RPT_NUM],
            ['Incomplete',          count($regs) - $complete, RPT_NUM],
            ['Completion Rate',     (count($regs) ? round($complete / count($regs) * 100, 1) : 0) . '%', RPT_TEXT],
        ],
        'signatories' => [
            ['Prepared by', 'SSC Secretary'],
            ['Verified by', 'SSC Auditor'],
            ['Approved by', 'SSC President'],
        ],
    ];
}

/* --------------------------------------------------------------------------
 | Report 02 — Ticket Issuance Report
 * ------------------------------------------------------------------------ */
function report_rows_tickets(): array
{
    $tickets = issued_tickets();
    $valid   = count(array_filter($tickets, static fn($t) => $t['status'] === 'valid'));
    $used    = count(array_filter($tickets, static fn($t) => $t['status'] === 'used'));
    $void    = count(array_filter($tickets, static fn($t) => $t['status'] === 'void'));

    $rows = [];
    foreach ($tickets as $i => $t) {
        $rows[] = [
            $i + 1,
            $t['ticket_no'],
            $t['student_no'],
            $t['name'],
            $t['program'],
            $t['year'],
            $t['issued_at'],
            ucfirst($t['status']),
        ];
    }

    return [
        'subtitle' => 'Ticket numbers issued against completed document submissions',
        'columns'  => [
            ['label' => 'No.',         'type' => RPT_TEXT, 'width' => 45,  'nowrap' => true],
            ['label' => 'Ticket No.',  'type' => RPT_TEXT, 'width' => 170, 'nowrap' => true],
            ['label' => 'SR-Code', 'type' => RPT_TEXT, 'width' => 110, 'nowrap' => true],
            ['label' => 'Name',        'type' => RPT_TEXT, 'width' => 210],
            ['label' => 'Program',     'type' => RPT_TEXT, 'width' => 230],
            ['label' => 'Year',        'type' => RPT_TEXT, 'width' => 80,  'nowrap' => true],
            ['label' => 'Issued',      'type' => RPT_DATE, 'width' => 150, 'nowrap' => true],
            ['label' => 'Status',      'type' => RPT_TEXT, 'width' => 90],
        ],
        'rows'    => $rows,
        'summary' => [
            ['Tickets Issued', count($tickets), RPT_NUM],
            ['Valid',          $valid,          RPT_NUM],
            ['Used at Gate',   $used,           RPT_NUM],
            ['Voided',         $void,           RPT_NUM],
        ],
        'signatories' => [
            ['Prepared by', 'SSC Secretary'],
            ['Audited by',  'SSC Auditor'],
            ['Approved by', 'SSC President'],
        ],
    ];
}

/* --------------------------------------------------------------------------
 | Report 03 — Attendance Report (gate check-in)
 * ------------------------------------------------------------------------ */
function report_rows_attendance(): array
{
    $log     = attendance_log();
    $present = array_filter($log, static fn($r) => $r['checked_in']);
    $absent  = count($log) - count($present);
    $rate    = count($log) > 0 ? round(count($present) / count($log) * 100, 1) : 0.0;

    $rows = [];
    foreach ($log as $i => $r) {
        $rows[] = [
            $i + 1,
            $r['ticket_no'],
            $r['student_no'],
            $r['name'],
            $r['program'],
            $r['gate'],
            $r['time_in'] ?: '',
            $r['scanner'],
            $r['checked_in'] ? 'Checked In' : 'No Show',
        ];
    }

    return [
        'subtitle' => 'Gate check-in record for ' . EVENT_NAME,
        'columns'  => [
            ['label' => 'No.',         'type' => RPT_TEXT, 'width' => 45,  'nowrap' => true],
            ['label' => 'Ticket No.',  'type' => RPT_TEXT, 'width' => 170, 'nowrap' => true],
            ['label' => 'SR-Code', 'type' => RPT_TEXT, 'width' => 110, 'nowrap' => true],
            ['label' => 'Name',        'type' => RPT_TEXT, 'width' => 200],
            ['label' => 'Program',     'type' => RPT_TEXT, 'width' => 230],
            ['label' => 'Gate',        'type' => RPT_TEXT, 'width' => 80],
            ['label' => 'Time In',     'type' => RPT_DATE, 'width' => 150, 'nowrap' => true],
            ['label' => 'Scanner',     'type' => RPT_TEXT, 'width' => 130],
            ['label' => 'Status',      'type' => RPT_TEXT, 'width' => 110],
        ],
        'rows'    => $rows,
        'summary' => [
            ['Tickets Issued',  count($log),     RPT_NUM],
            ['Checked In',      count($present), RPT_NUM],
            ['No Show',         $absent,         RPT_NUM],
            ['Attendance Rate', $rate . '%',     RPT_TEXT],
        ],
        'signatories' => [
            ['Prepared by',       'Gate Marshal'],
            ['Certified correct', 'SSC President'],
            ['Noted by',          'Office of Student Affairs'],
        ],
    ];
}

/* --------------------------------------------------------------------------
 | Value formatting shared by every renderer
 * ------------------------------------------------------------------------ */

/** Human-readable value for the screen and the printed form. */
function report_format(string $type, $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    switch ($type) {
        case RPT_MONEY:
            return peso((float) $value);
        case RPT_NUM:
            return number_format((float) $value);
        case RPT_DATE:
            return pretty_date((string) $value);
        default:
            return (string) $value;
    }
}

/** Spreadsheet value — numbers stay numbers so SUM() still works in Sheets. */
function report_raw(string $type, $value)
{
    if ($type === RPT_MONEY) {
        return number_format((float) $value, 2, '.', '');
    }
    if ($type === RPT_DATE) {
        return ($value === null || $value === '') ? '' : date('Y-m-d H:i:s', strtotime((string) $value));
    }
    return (string) $value;
}

/** Column alignment used by both the web table and the spreadsheet. */
function report_align(string $type): string
{
    return in_array($type, [RPT_MONEY, RPT_NUM], true) ? 'right' : 'left';
}

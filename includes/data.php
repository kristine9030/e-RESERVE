<?php
/**
 * e-RESERVE — Sample data provider
 *
 * Every function here returns the same shape a MySQL query would.
 * REPLACE LATER: swap each body for a prepared statement, e.g.
 *   return $pdo->query('SELECT * FROM tbl_reservations')->fetchAll();
 */

declare(strict_types=1);

/* --------------------------------------------------------------------------
 | Master roster used to fabricate consistent reservations / payments / tickets
 * ------------------------------------------------------------------------ */
function sample_students(): array
{
    return [
        ['Althea Mae Ramirez',   '22-10234', 'BS Information Technology',      '3rd Year'],
        ['Joshua Miguel Torres', '22-10877', 'BS Computer Science',            '2nd Year'],
        ['Cristine Joy Aguilar', '21-20455', 'BS Business Administration',     '4th Year'],
        ['Marc Lawrence Bayani', '23-30981', 'BS Hospitality Management',      '1st Year'],
        ['Danica Rose Malvar',   '22-11902', 'BS Criminology',                 '3rd Year'],
        ['Kevin Paul Sarmiento', '21-20118', 'Bachelor of Elementary Education','4th Year'],
        ['Angelica Dimaano',     '23-31240', 'BS Information Technology',      '1st Year'],
        ['Rafael Antonio Lim',   '22-10556', 'BS Computer Science',            '3rd Year'],
        ['Shaira Mae Villamor',  '21-21033', 'BS Business Administration',     '4th Year'],
        ['John Carlo Endaya',    '23-30442', 'BS Hospitality Management',      '2nd Year'],
        ['Patricia Anne Gutierrez','22-11487','BS Criminology',                '2nd Year'],
        ['Nathaniel Reyes',      '21-20890', 'BS Information Technology',      '4th Year'],
        ['Kyla Marie Fontanilla','23-31788', 'Bachelor of Elementary Education','1st Year'],
        ['Aaron Dela Peña',      '22-10099', 'BS Computer Science',            '3rd Year'],
        ['Bianca Louise Manalo', '21-21567', 'BS Business Administration',     '3rd Year'],
        ['Renz Christian Ilagan','23-30655', 'BS Hospitality Management',      '1st Year'],
        ['Marielle Joyce Castro','22-11330', 'BS Criminology',                 '4th Year'],
        ['Gabriel Andrei Perez', '21-20744', 'BS Information Technology',      '2nd Year'],
        ['Zyra Nicole Bautista', '23-31099', 'BS Computer Science',            '1st Year'],
        ['Lester Kim Rivera',    '22-10711', 'BS Business Administration',     '3rd Year'],
        ['Camille Ann Mercado',  '21-21288', 'BS Hospitality Management',      '4th Year'],
        ['Dominic Jose Alvarez', '23-30320', 'BS Criminology',                 '1st Year'],
        ['Jasmine Kaye Salazar', '22-11655', 'Bachelor of Elementary Education','2nd Year'],
        ['Emmanuel Rey Panganiban','21-20977','BS Information Technology',     '4th Year'],
    ];
}

/**
 * Reservations table.
 * The signed-in student's own live (session) reservation is prepended so the
 * admin panel visibly reacts to what happens on the student side.
 */
function sample_reservations(): array
{
    $rows     = [];
    $students = sample_students();
    $statuses = ['paid', 'paid', 'paid', 'pending', 'paid', 'paid', 'cancelled', 'paid'];

    foreach ($students as $i => [$name, $sn, $program, $year]) {
        $status = $statuses[$i % count($statuses)];
        $day    = 1 + ($i % 26);
        $date   = sprintf('2026-10-%02d %02d:%02d:00', $day, 8 + ($i % 11), ($i * 7) % 60);

        $rows[] = [
            'code'       => 'RSV-2026-' . str_pad((string) (1000 + $i), 6, '0', STR_PAD_LEFT),
            'ticket_no'  => $status === 'paid' ? TICKET_PREFIX . str_pad((string) (100 + $i), 6, '0', STR_PAD_LEFT) : '—',
            'name'       => $name,
            'student_no' => $sn,
            'program'    => $program,
            'year'       => $year,
            'quantity'   => 1,
            'amount'     => TICKET_PRICE,
            'status'     => $status,
            'created_at' => $date,
            'paid_at'    => $status === 'paid' ? date('Y-m-d H:i:s', strtotime($date) + 3600) : null,
            'checked_in' => $status === 'paid' && $i % 3 === 0,
        ];
    }

    // --- live session record on top ----------------------------------------
    if (function_exists('get_reservation') && ($mine = get_reservation())) {
        $ticket = get_ticket();
        array_unshift($rows, [
            'code'       => $mine['code'],
            'ticket_no'  => $ticket['ticket_no'] ?? '—',
            'name'       => $mine['name'] . ' (you)',
            'student_no' => $mine['student_no'],
            'program'    => $mine['program'],
            'year'       => $mine['year'],
            'quantity'   => $mine['quantity'],
            'amount'     => $mine['amount'],
            'status'     => $mine['status'],
            'created_at' => $mine['created_at'],
            'paid_at'    => $mine['paid_at'],
            'checked_in' => (bool) ($ticket['checked_in'] ?? false),
        ]);
    }

    return $rows;
}

/** Payments / transactions table. */
function sample_payments(): array
{
    $rows = [];
    foreach (sample_reservations() as $i => $r) {
        if ($r['status'] !== 'paid') {
            continue;
        }
        $rows[] = [
            'receipt_no' => 'OR-2026-' . str_pad((string) (500 + $i), 6, '0', STR_PAD_LEFT),
            'reference'  => 'PM-' . strtoupper(substr(md5($r['code']), 0, 10)),
            'code'       => $r['code'],
            'ticket_no'  => $r['ticket_no'],
            'name'       => $r['name'],
            'student_no' => $r['student_no'],
            'method'     => PAYMENT_METHOD,
            'amount'     => $r['amount'],
            'fee'        => round($r['amount'] * 0.025, 2),   // mock 2.5% gateway fee
            'status'     => 'succeeded',
            'paid_at'    => $r['paid_at'] ?? $r['created_at'],
        ];
    }
    return $rows;
}

/** Issued tickets table. */
function sample_tickets(): array
{
    $rows = [];
    foreach (sample_reservations() as $i => $r) {
        if ($r['status'] !== 'paid') {
            continue;
        }
        $rows[] = [
            'ticket_no'  => $r['ticket_no'],
            'name'       => $r['name'],
            'student_no' => $r['student_no'],
            'program'    => $r['program'],
            'seat'       => 'GA-' . str_pad((string) (100 + $i), 4, '0', STR_PAD_LEFT),
            'status'     => $r['checked_in'] ? 'used' : 'valid',
            'checked_in' => $r['checked_in'],
            'issued_at'  => $r['paid_at'] ?? $r['created_at'],
        ];
    }
    return $rows;
}

/* --------------------------------------------------------------------------
 | Aggregates for dashboard cards
 * ------------------------------------------------------------------------ */
function dashboard_stats(): array
{
    $reservations = sample_reservations();
    $paid         = array_filter($reservations, static fn($r) => $r['status'] === 'paid');
    $pending      = array_filter($reservations, static fn($r) => $r['status'] === 'pending');
    $cancelled    = array_filter($reservations, static fn($r) => $r['status'] === 'cancelled');
    $checked      = array_filter($reservations, static fn($r) => $r['checked_in']);
    $revenue      = array_sum(array_column($paid, 'amount'));

    return [
        'total_reservations' => count($reservations),
        'paid_tickets'       => count($paid),
        'pending_payments'   => count($pending),
        'cancelled'          => count($cancelled),
        'checked_in'         => count($checked),
        'revenue'            => (float) $revenue,
        'gateway_fees'       => round($revenue * 0.025, 2),
        'net_revenue'        => round($revenue * 0.975, 2),
        'capacity'           => EVENT_CAPACITY,
        'fill_rate'          => round(count($paid) / max(1, EVENT_CAPACITY) * 100, 2),
    ];
}

/** Daily sales trend used by the mini bar chart (pure CSS, no chart library). */
function sales_trend(): array
{
    $out = [];
    for ($d = 1; $d <= 14; $d++) {
        $out[] = [
            'label' => date('M j', strtotime("2026-10-" . str_pad((string) $d, 2, '0', STR_PAD_LEFT))),
            'count' => seeded($d * 31, 4, 42),
        ];
    }
    return $out;
}

/** Reservations grouped by program — used in the reports module. */
function program_breakdown(): array
{
    $out = [];
    foreach (sample_reservations() as $r) {
        $key = $r['program'];
        if (!isset($out[$key])) {
            $out[$key] = ['program' => $key, 'total' => 0, 'paid' => 0, 'revenue' => 0.0];
        }
        $out[$key]['total']++;
        if ($r['status'] === 'paid') {
            $out[$key]['paid']++;
            $out[$key]['revenue'] += $r['amount'];
        }
    }
    usort($out, static fn($a, $b) => $b['total'] <=> $a['total']);
    return array_values($out);
}

/** Attendance / check-in log. */
function attendance_log(): array
{
    $rows = [];
    foreach (sample_tickets() as $i => $t) {
        $rows[] = [
            'ticket_no'  => $t['ticket_no'],
            'name'       => $t['name'],
            'student_no' => $t['student_no'],
            'program'    => $t['program'],
            'gate'       => 'Gate ' . chr(65 + ($i % 3)),
            'checked_in' => $t['checked_in'],
            'time_in'    => $t['checked_in'] ? date('Y-m-d H:i:s', strtotime(EVENT_DATE_ISO) + ($i * 137)) : null,
            'scanner'    => $t['checked_in'] ? 'SSC-Scanner-' . (1 + ($i % 4)) : '—',
        ];
    }
    return $rows;
}

/** Finance ledger — payouts and adjustments on top of ticket sales. */
function finance_ledger(): array
{
    $rows = [];
    foreach (sample_payments() as $p) {
        $rows[] = [
            'date'      => $p['paid_at'],
            'reference' => $p['reference'],
            'particular'=> 'Ticket sale · ' . $p['ticket_no'],
            'payer'     => $p['name'],
            'method'    => $p['method'],
            'debit'     => 0.0,
            'credit'    => $p['amount'],
            'fee'       => $p['fee'],
            'status'    => 'cleared',
        ];
    }

    $rows[] = [
        'date' => '2026-10-31 17:00:00', 'reference' => 'PAYOUT-2026-001',
        'particular' => 'PayMongo settlement to SSC account', 'payer' => 'PayMongo Philippines',
        'method' => 'Bank Transfer', 'debit' => 4500.00, 'credit' => 0.0, 'fee' => 0.0, 'status' => 'cleared',
    ];
    $rows[] = [
        'date' => '2026-11-02 09:15:00', 'reference' => 'ADJ-2026-004',
        'particular' => 'Refund · duplicate GCash charge', 'payer' => 'Danica Rose Malvar',
        'method' => 'GCash', 'debit' => TICKET_PRICE, 'credit' => 0.0, 'fee' => 0.0, 'status' => 'refunded',
    ];

    usort($rows, static fn($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
    return $rows;
}

/* --------------------------------------------------------------------------
 | Landing page content
 * ------------------------------------------------------------------------ */
function event_schedule(): array
{
    return [
        ['4:00 PM', 'Registration & QR Check-in',   'Gates open. Present your e-RESERVE ticket at the assigned gate.',        'fa-qrcode'],
        ['6:00 PM', 'Opening Program & Invocation', 'Welcome remarks from the Supreme Student Council and campus officials.', 'fa-microphone-lines'],
        ['6:45 PM', 'Sigla ng Talino',              'Academic org showcase and 70th Anniversary tribute presentation.',        'fa-graduation-cap'],
        ['7:30 PM', 'Battle of the Bands',          'Campus bands compete for the SIGLA 2026 championship trophy.',            'fa-guitar'],
        ['9:00 PM', 'Sigla ng Puso',                'Dance crew face-off and the search for Mr. & Ms. Student\'s Night.',      'fa-star'],
        ['10:30 PM','Guest Performance',            'Special production number by the featured guest artist.',                 'fa-music'],
        ['11:30 PM','Awarding & Afterparty',        'Announcement of winners followed by the SIGLA afterparty.',               'fa-trophy'],
    ];
}

function faqs(): array
{
    return [
        ['Who can reserve a ticket?',
         'All bona fide students of Batangas State University TNEU – ARASOF Nasugbu with an active @g.batstate-u.edu.ph GSuite account may reserve.'],
        ['How many tickets can I reserve?',
         'One student is entitled to exactly one ticket. The system automatically blocks a second reservation on the same account.'],
        ['What payment methods are accepted?',
         'Payments are processed through PayMongo using GCash. Additional channels (Maya, GrabPay, cards) will be enabled in a later release.'],
        ['When do I receive my ticket?',
         'Immediately. Once PayMongo confirms your payment, e-RESERVE generates your e-ticket and official receipt automatically — no waiting for manual approval.'],
        ['What if I lose my ticket?',
         'Nothing is lost. Sign in to e-RESERVE anytime and re-open View Ticket or Download Receipt from your dashboard.'],
        ['Can I cancel my reservation?',
         'Yes, while it is still unpaid. Cancel it from the Track Reservation page. Paid tickets follow the SSC refund policy.'],
        ['What do I bring on event day?',
         'Your e-ticket QR code (printed or on your phone) plus your BatStateU student ID for verification at the gate.'],
    ];
}

function about_highlights(): array
{
    return [
        ['fa-people-group', '2,500 Students',   'The largest single-night student gathering of the 70th Founding Anniversary.'],
        ['fa-bolt',         'Instant Ticketing','Ticket and receipt are issued the second your GCash payment clears.'],
        ['fa-shield-halved','One Slot Each',    'A strict one-student-one-ticket rule keeps the reservation fair for everyone.'],
        ['fa-qrcode',       'Contactless Entry','Scan-and-go gates powered by QR codes — no printed manifest, no queues.'],
    ];
}

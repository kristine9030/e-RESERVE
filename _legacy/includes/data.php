<?php
/**
 * e-RESERVE — Data provider for the staff dashboards, reports and landing page.
 *
 * Every function here is a prepared query against MySQL (or static copy for the
 * landing page). Pages never build SQL themselves.
 */

declare(strict_types=1);

/* --------------------------------------------------------------------------
 | Registries
 * ------------------------------------------------------------------------ */

/**
 * Every student account with the state of their three documents and ticket.
 * One row per student, newest first.
 */
function registrations(): array
{
    $rows = db_all(
        'SELECT u.id, u.name, u.email, u.student_no, u.program, u.year_level, u.created_at, u.email_verified_at,
                MAX(CASE WHEN d.doc_type = "waiver" THEN d.status END) AS waiver_status,
                MAX(CASE WHEN d.doc_type = "cor"    THEN d.status END) AS cor_status,
                MAX(CASE WHEN d.doc_type = "id"     THEN d.status END) AS id_status,
                MAX(CASE WHEN d.doc_type = "waiver" THEN d.id END)     AS waiver_id,
                MAX(CASE WHEN d.doc_type = "cor"    THEN d.id END)     AS cor_id,
                MAX(CASE WHEN d.doc_type = "id"     THEN d.id END)     AS id_id,
                COUNT(d.id) AS doc_count,
                t.ticket_no, t.status AS ticket_status, t.issued_at,
                c.scanned_at
         FROM users u
         LEFT JOIN documents d ON d.user_id = u.id
         LEFT JOIN tickets t   ON t.user_id = u.id
         LEFT JOIN checkins c  ON c.ticket_id = t.id
         WHERE u.role = "student"
         GROUP BY u.id, t.id, c.id
         ORDER BY u.created_at DESC, u.id DESC'
    );

    return array_map(static fn(array $r): array => [
        'id'            => (int) $r['id'],
        'name'          => $r['name'],
        'email'         => $r['email'],
        'student_no'    => $r['student_no'],
        'program'       => $r['program'],
        'year'          => $r['year_level'],
        'registered_at' => $r['created_at'],
        'verified'      => $r['email_verified_at'] !== null,
        'docs'          => [
            'waiver' => ['status' => $r['waiver_status'], 'id' => $r['waiver_id'] ? (int) $r['waiver_id'] : null],
            'cor'    => ['status' => $r['cor_status'],    'id' => $r['cor_id']    ? (int) $r['cor_id']    : null],
            'id'     => ['status' => $r['id_status'],     'id' => $r['id_id']     ? (int) $r['id_id']     : null],
        ],
        'doc_count'     => (int) $r['doc_count'],
        'ticket_no'     => $r['ticket_no'],
        'ticket_status' => $r['ticket_status'],
        'issued_at'     => $r['issued_at'],
        'checked_in'    => !empty($r['scanned_at']),
        'checked_at'    => $r['scanned_at'],
    ], $rows);
}

/** Issued tickets, newest first. */
function issued_tickets(): array
{
    $rows = db_all(
        'SELECT t.id, t.ticket_no, t.status, t.issued_at,
                u.name, u.student_no, u.program, u.year_level,
                c.scanned_at, c.gate
         FROM tickets t
         JOIN users u         ON u.id = t.user_id
         LEFT JOIN checkins c ON c.ticket_id = t.id
         ORDER BY t.issued_at DESC, t.id DESC'
    );

    return array_map(static fn(array $t): array => [
        'id'         => (int) $t['id'],
        'ticket_no'  => $t['ticket_no'],
        'name'       => $t['name'],
        'student_no' => $t['student_no'],
        'program'    => $t['program'],
        'year'       => $t['year_level'],
        'status'     => $t['status'],
        'checked_in' => !empty($t['scanned_at']),
        'gate'       => $t['gate'],
        'issued_at'  => $t['issued_at'],
    ], $rows);
}

/* --------------------------------------------------------------------------
 | Aggregates for the dashboard cards
 * ------------------------------------------------------------------------ */
function dashboard_stats(): array
{
    $row = db_one(
        'SELECT
            (SELECT COUNT(*) FROM users WHERE role = "student")                         AS students,
            (SELECT COUNT(*) FROM documents WHERE status = "submitted")                  AS docs_pending,
            (SELECT COUNT(*) FROM documents WHERE status = "approved")                   AS docs_approved,
            (SELECT COUNT(*) FROM documents WHERE status = "rejected")                   AS docs_rejected,
            (SELECT COUNT(*) FROM tickets WHERE status <> "void")                        AS tickets,
            (SELECT COUNT(*) FROM tickets WHERE status = "void")                         AS tickets_void,
            (SELECT COUNT(*) FROM checkins)                                              AS checked_in,
            (SELECT COUNT(*) FROM users u WHERE u.role = "student"
                AND (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) < ' . count(DOCUMENT_TYPES) . ') AS incomplete'
    ) ?? [];

    $tickets = (int) ($row['tickets'] ?? 0);

    return [
        'students'      => (int) ($row['students'] ?? 0),
        'docs_pending'  => (int) ($row['docs_pending'] ?? 0),
        'docs_approved' => (int) ($row['docs_approved'] ?? 0),
        'docs_rejected' => (int) ($row['docs_rejected'] ?? 0),
        'tickets'       => $tickets,
        'tickets_void'  => (int) ($row['tickets_void'] ?? 0),
        'checked_in'    => (int) ($row['checked_in'] ?? 0),
        'incomplete'    => (int) ($row['incomplete'] ?? 0),
        'capacity'      => EVENT_CAPACITY,
        'fill_rate'     => round($tickets / max(1, EVENT_CAPACITY) * 100, 2),
    ];
}

/** Tickets issued per day for the mini bar chart — last 14 days with data. */
function issuance_trend(): array
{
    $rows = db_all(
        'SELECT DATE(issued_at) AS day, COUNT(*) AS count
         FROM tickets
         GROUP BY DATE(issued_at)
         ORDER BY day DESC
         LIMIT 14'
    );

    return array_map(static fn(array $r): array => [
        'label' => date('M j', strtotime($r['day'])),
        'count' => (int) $r['count'],
    ], array_reverse($rows));
}

/** Registered students and ticket holders grouped by program. */
function program_breakdown(): array
{
    $rows = db_all(
        'SELECT u.program,
                COUNT(DISTINCT u.id) AS total,
                COUNT(DISTINCT CASE WHEN t.status <> "void" THEN t.id END) AS ticketed
         FROM users u
         LEFT JOIN tickets t ON t.user_id = u.id
         WHERE u.role = "student" AND u.program IS NOT NULL
         GROUP BY u.program
         ORDER BY total DESC'
    );

    return array_map(static fn(array $r): array => [
        'program'  => $r['program'],
        'total'    => (int) $r['total'],
        'ticketed' => (int) $r['ticketed'],
    ], $rows);
}

/** Attendance / check-in log — every ticket holder, scanned or not. */
function attendance_log(): array
{
    $rows = db_all(
        'SELECT t.id, t.ticket_no,
                u.name, u.student_no, u.program,
                c.gate, c.scanner, c.scanned_at
         FROM tickets t
         JOIN users u         ON u.id = t.user_id
         LEFT JOIN checkins c ON c.ticket_id = t.id
         WHERE t.status <> "void"
         ORDER BY c.scanned_at IS NULL, c.scanned_at ASC, t.ticket_no ASC'
    );

    return array_map(static fn(array $r): array => [
        'ticket_no'  => $r['ticket_no'],
        'name'       => $r['name'],
        'student_no' => $r['student_no'],
        'program'    => $r['program'],
        'gate'       => $r['gate'] ?? 'Unassigned',
        'checked_in' => !empty($r['scanned_at']),
        'time_in'    => $r['scanned_at'],
        'scanner'    => $r['scanner'] ?? '—',
    ], $rows);
}

/** Recent entries in the audit trail, for the admin dashboard. */
function recent_audit(int $limit = 12): array
{
    $limit = max(1, min(100, $limit));
    return db_all('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . $limit);
}

/* --------------------------------------------------------------------------
 | Landing page content
 * ------------------------------------------------------------------------ */

/** The four-step pipeline shown on the landing page and the dashboard. */
function pipeline_steps(): array
{
    return [
        [
            'num'   => '01',
            'icon'  => 'fa-user-plus',
            'title' => 'Create your account',
            'text'  => 'Sign up with your BatStateU GSuite address, then open the confirmation link we email you.',
            'tag'   => 'Takes 2 minutes',
        ],
        [
            'num'   => '02',
            'icon'  => 'fa-file-arrow-up',
            'title' => 'Submit 3 required documents',
            'text'  => 'Your signed waiver, your Certificate of Registration (COR) and your school ID — one PDF each.',
            'tag'   => 'Max 5 MB each',
        ],
        [
            'num'   => '03',
            'icon'  => 'fa-ticket',
            'title' => 'Get your ticket number',
            'text'  => 'The moment the third file is in, your personal ticket number and QR code are issued.',
            'tag'   => 'Instant',
        ],
        [
            'num'   => '04',
            'icon'  => 'fa-qrcode',
            'title' => 'Scan at the gate',
            'text'  => 'Show the QR on your phone or on paper. The gate scans it and you walk straight in.',
            'tag'   => 'Event night',
        ],
    ];
}

/** Detailed guidelines, grouped for the landing-page tabs. */
function guidelines(): array
{
    return [
        'eligibility' => [
            'label' => 'Who can join',
            'icon'  => 'fa-user-graduate',
            'items' => [
                ['fa-graduation-cap', 'Currently enrolled students only', 'You must be enrolled at BatStateU TNEU – ARASOF Nasugbu this semester. Your COR is how we check.'],
                ['fa-envelope',       'A GSuite account is required',   'Register with your @' . ALLOWED_EMAIL_DOMAINS[0] . ' address. Personal email addresses are not accepted.'],
                ['fa-user-lock',      'One student, one ticket',        'Each student gets exactly one ticket. A second account or a shared ticket will be voided.'],
                ['fa-ban',            'Non-transferable',               'Your ticket is tied to your name and SR-code. It cannot be sold, lent or gifted.'],
            ],
        ],
        'documents' => [
            'label' => 'The 3 documents',
            'icon'  => 'fa-file-pdf',
            'items' => [
                ['fa-file-signature', 'Signed waiver',       'Download the waiver from your dashboard, print it, sign it in ink, then scan it. Every page must be included.'],
                ['fa-file-lines',     'COR',                 'Your Certificate of Registration for the current semester. Your name and SR-code must be readable.'],
                ['fa-id-card',        'School ID',           'The front of your valid BatStateU ID, face visible, no glare, no filters.'],
                ['fa-circle-check',   'Clear and complete',  'Blurry, cropped, edited or expired documents are returned and your ticket is paused until you replace them.'],
            ],
        ],
        'files' => [
            'label' => 'File rules',
            'icon'  => 'fa-file-circle-check',
            'items' => [
                ['fa-file-pdf',      'PDF only',              'Only .pdf files are accepted. Phone photos and Word files must be saved or exported as PDF first.'],
                ['fa-weight-hanging','Up to 5 MB each',       'Scan at 150–200 dpi or use your phone’s “scan to PDF”. Larger files are rejected before they upload.'],
                ['fa-rotate',        'You can replace a file','Uploaded the wrong one? Upload again any time — until staff approve it or your ticket has been used.'],
                ['fa-shield-halved', 'Private by design',     'Your documents are stored securely and opened only by you and authorised SSC staff, for verification only.'],
            ],
        ],
        'ticket' => [
            'label' => 'Your ticket',
            'icon'  => 'fa-ticket',
            'items' => [
                ['fa-bolt',          'Issued automatically',  'No payment and no waiting list: your ticket number appears the moment all three documents are uploaded.'],
                ['fa-qrcode',        'The QR is your pass',   'Your QR code carries a secret signature, so a copied or made-up ticket number fails at the gate.'],
                ['fa-triangle-exclamation', 'If a file is returned', 'Staff may return a document with a note. Your ticket is paused, then restored — same number — once you replace it.'],
                ['fa-hourglass-half', 'Tickets are limited',   'Tickets go on a first-come, first-served basis. Finish your uploads early so yours is issued.'],
            ],
        ],
        'gate' => [
            'label' => 'Event night',
            'icon'  => 'fa-door-open',
            'items' => [
                ['fa-clock',         'Arrive early',          'The event runs 5:00 PM – 10:00 PM. Be at the gate before 5:00 PM so you are scanned in before the program starts.'],
                ['fa-mobile-screen', 'Bring your QR',         'Show it on your phone with the brightness turned up, or bring a printed copy as backup.'],
                ['fa-id-badge',      'Bring your school ID',  'Gate staff may match your QR against your physical ID.'],
                ['fa-user-check',    'One scan per ticket',   'A ticket can be scanned once. After that it shows as already used — screenshots will not get a second person in.'],
            ],
        ],
    ];
}

/** Performers, from EVENT_PERFORMERS in config. */
function event_lineup(): array
{
    return EVENT_PERFORMERS;
}

function faqs(): array
{
    return [
        ['Who can get a ticket?',
         'All currently enrolled students of Batangas State University TNEU – ARASOF Nasugbu with an active university GSuite account.'],
        ['Do I have to pay?',
         'No. Your ticket is issued after you upload your signed waiver, COR and school ID. There is nothing to send through GCash.'],
        ['What do I need to upload?',
         'Three required documents, each saved as a PDF: your signed waiver, your Certificate of Registration (COR) for this semester, and the front of your school ID. Each can be up to 5 MB.'],
        ['Where do I get the waiver?',
         'Sign in, open Documents and choose “Download waiver”. Print it, sign it in ink, scan it and upload the PDF.'],
        ['How do I make a PDF from my phone?',
         'Use any “scan to PDF” feature — Google Drive, Adobe Scan, Microsoft Lens, or the Notes app on iPhone. Photos can also be exported as PDF from your gallery’s print option.'],
        ['When do I get my ticket number?',
         'Instantly. As soon as the third document is uploaded, your ticket number and QR code appear on your dashboard.'],
        ['Why was my document returned?',
         'SSC staff check every file. If something is unreadable, cropped, expired or unsigned, the file is returned with a note. Your ticket is paused until you upload a corrected copy — then it is restored with the same number.'],
        ['How do I get in on event night?',
         'Show the QR code on your ticket at the gate. It is scanned once and you are admitted. Bring your school ID in case staff want to match it.'],
        ['What if I lose my ticket?',
         'Nothing is lost. Sign in to e-RESERVE any time and open My Ticket to see or print it again.'],
        ['Can I give my ticket to a friend?',
         'No. Tickets are tied to your name and SR-code, and a ticket can only be scanned once.'],
    ];
}

function about_highlights(): array
{
    return [
        ['fa-people-group', 'Made for Every Student', 'A night for the whole campus, part of the 70th Founding Anniversary celebration.'],
        ['fa-file-pdf',     'Paperless Sign-up',   'Submit your 3 required documents from your phone. No queues at the SSC office, no paper forms to hand in.'],
        ['fa-ticket',       'Free, Instant Ticket','Your ticket number is issued the moment your documents are in. No payment needed.'],
        ['fa-qrcode',       'Contactless Entry',   'Scan-and-go gates powered by signed QR codes — no printed manifest, no long lines.'],
    ];
}

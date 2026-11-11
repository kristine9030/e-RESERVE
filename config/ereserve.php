<?php

/**
 * e-RESERVE — event, ticketing and eligibility settings.
 *
 * Everything that used to be a define() in includes/config.php lives here, so
 * it can be read with config('ereserve.…') and overridden per environment.
 */
return [

    'name'    => 'e-RESERVE',
    'tagline' => 'Register. Upload. Get your ticket. Celebrate.',
    'version' => '2.0.0',

    'event' => [
        'name'      => "SIGLA 2026: A Student's Night",
        'subtitle'  => 'A flagship event of the 70th Founding Anniversary',
        'host'      => 'BatStateU The NEU ARASOF-Nasugbu',
        'org'       => 'Supreme Student Council',
        'motto'     => 'PUSO. DEDIKASYON. TALINO.',
        'venue'     => 'HEB Grounds',
        'date_iso'  => '2026-11-26T17:00:00',
        'date_text' => 'November 26, 2026',
        'time_text' => '5:00 PM – 10:00 PM',
        'capacity'  => 3000,

        /** Soft launch: when student sign-in & account creation open to the public (SSC overrides it in Site Settings). */
        'registration_opens_at' => env('STUDENT_ACCESS_OPENS_AT', '2026-11-15T02:00:00'),
    ],

    /** name, kind, icon, photo (null = placeholder) */
    'performers' => [
        ['SIKLAB',      'Dance performance', 'fa-person-walking', 'assets/images/artists/siklab.jpg'],
        ['UNO Band',    'Live band',         'fa-guitar',         'assets/images/artists/uno-band.jpg'],
        ['EXCESS Band', 'Live band',         'fa-guitar',         'assets/images/artists/excess-band.jpg'],
        ['FitterKarma', 'Live performance',  'fa-music',          'assets/images/[MAIN] fitterkarma Photo B (1).jpg'],
        ['Hiraya',      'Live performance',  'fa-music',          'assets/images/HIRAYA.jpg'],
        ['DJ Chris',    'DJ set',            'fa-record-vinyl',   'assets/images/DJ Chris.jpg'],
    ],

    'ssc' => [
        'facebook'  => 'https://www.facebook.com/SSCARASOF',
        'email'     => 'sscnasugbu@g.batstate-u.edu.ph',
        'messenger' => 'Supreme Student Council - BatStateU The NEU ARASOF-Nasugbu',
        'logo'      => 'assets/images/ssc-logo.png',
        'logo_small' => 'assets/images/ssc-logo-sm.png',   // 240px copy for page headers (80 KB instead of 888 KB)
    ],

    /** Starting split of the tickets (SSC changes it in Site Settings). Should add up to event.capacity. */
    'quotas' => ['student' => 2500, 'employee' => 200, 'sponsor' => 300],

    // Sponsors are managed by the SSC in the panel (Sponsors + Site Settings), not here.

    'ticket' => [
        'prefix' => 'SIGLA-2026-',
        'limit'  => 1,                  // one student = one ticket
    ],

    /** The three requirements. The key is stored in documents.doc_type. */
    'documents' => [
        'waiver' => [
            'key'   => 'waiver',
            'label' => 'Signed Waiver',
            'short' => 'Waiver',
            'icon'  => 'fa-file-signature',
            'hint'  => 'Download the official waiver, print it, have your parent or guardian and you sign it, then scan it and save it as a PDF.',
            'rules' => ['Signed in ink by you', 'Every page included', 'Name matches your account'],
        ],
        'cor' => [
            'key'   => 'cor',
            'label' => 'Certificate of Registration (COR)',
            'short' => 'COR',
            'icon'  => 'fa-file-lines',
            'hint'  => 'Your current-semester COR from the registrar or the student portal.',
            'rules' => ['Current semester only', 'Your name and SR-code visible', 'Readable, not cropped'],
        ],
        'id' => [
            'key'   => 'id',
            'label' => 'School ID',
            'short' => 'School ID',
            'icon'  => 'fa-id-card',
            'hint'  => 'A clear scan or photo of the front of your BatStateU ID, saved as a PDF.',
            'rules' => ['Front side, face visible', 'Valid for this school year', 'No glare or filters'],
        ],
        'employee_id' => [
            'key'   => 'employee_id',
            'label' => 'Employee ID',
            'short' => 'Employee ID',
            'icon'  => 'fa-id-badge',
            'hint'  => 'A clear scan or photo of the front of your BatStateU employee ID, saved as one PDF.',
            'rules' => ['Front side, face visible', 'Name matches your account', 'No glare or filters'],
        ],
        'sponsorship' => [
            'key'   => 'sponsorship',
            'label' => 'Proof of Sponsorship',
            'short' => 'Proof of sponsorship',
            'icon'  => 'fa-handshake',
            'hint'  => 'The SSC letter, agreement or receipt that shows your sponsorship, saved as one PDF.',
            'rules' => ['Shows the company or your name', 'Shows the SSC or the event', 'Readable, not cropped'],
        ],
    ],

    /** Which of the documents above each kind of holder uploads. The ticket comes once all of them are approved. */
    'document_sets' => [
        'student'  => ['waiver', 'cor', 'id'],
        'employee' => ['employee_id'],          // faculty, instructor, staff
        'sponsor'  => ['sponsorship'],
    ],

    'doc_max_bytes' => 5 * 1024 * 1024,

    /** Who may hold an account. */
    'allowed_email_domains' => [
        'g.batstate-u.edu.ph',   // students, faculty and offices
    ],

    /**
     * Exact addresses outside those domains that may hold a STAFF account (e.g. the SSC's own Gmail).
     * Comma-separated in .env. Students can never sign up with these: registration still needs a university address.
     */
    'staff_extra_emails' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) env('STAFF_EXTRA_EMAILS', '')))))),

    /** Hours a confirmation link stays usable. */
    'verify_token_hours' => 24,

    /** Employees who may register without documents: a confirmed email is enough for a ticket. */
    'employee_types' => [
        'faculty'        => 'Faculty',
        'instructor'     => 'Instructor',
        'staff'          => 'Staff',
    ],

    /** Employee ID shape: letters, digits, dash or slash; 3 to 20 characters. */
    'employee_id_pattern' => '/^[A-Z0-9][A-Z0-9\-\/]{2,19}$/',

    'year_levels' => ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'],

    /** Colleges whose students may attend. The Laboratory School is not eligible. */
    'colleges' => [
        'CABEIHM' => 'College of Accountancy, Business, Economics and International Hospitality Management',
        'CICS'    => 'College of Informatics and Computing Sciences',
        'CTE'     => 'College of Teacher Education',
        'CHS'     => 'College of Health Sciences',
        'CCJE'    => 'College of Criminal Justice Education',
        'CAS'     => 'College of Arts and Sciences',
    ],

    /**
     * Programmes per college, from the registrar's enrollment summary
     * (A.Y. 2026-2027, 1st semester). Majors are separate choices because the
     * masterlist enrols students under them separately.
     */
    'programs' => [
        'CABEIHM' => [
            'Bachelor of Science in Accountancy',
            'Bachelor of Science in Management Accounting',
            'Bachelor of Science in Business Administration — Major in Financial Management',
            'Bachelor of Science in Business Administration — Major in Human Resource Management',
            'Bachelor of Science in Business Administration — Major in Marketing Management',
            'Bachelor of Science in Hospitality Management',
            'Bachelor of Science in Tourism Management',
        ],
        'CICS' => [
            'Bachelor of Science in Information Technology',
            'Bachelor of Science in Information Technology — Major in Business Analytics',
            'Bachelor of Science in Information Technology — Major in Network Technology',
        ],
        'CTE' => [
            'Bachelor of Elementary Education',
            'Bachelor of Physical Education',
            'Bachelor of Secondary Education — Major in English',
            'Bachelor of Secondary Education — Major in Filipino',
            'Bachelor of Secondary Education — Major in Mathematics',
            'Bachelor of Secondary Education — Major in Science',
            'Bachelor of Secondary Education — Major in Social Studies',
        ],
        'CHS' => [
            'Bachelor of Science in Nursing',
            'Bachelor of Science in Nutrition and Dietetics',
        ],
        'CCJE' => [
            'Bachelor of Science in Criminology',
        ],
        'CAS' => [
            'Bachelor of Arts in Communication',
            'Bachelor of Science in Fisheries and Aquatic Sciences',
            'Bachelor of Science in Food Technology',
            'Bachelor of Science in Psychology',
        ],
    ],

    'reports' => [
        'registrations' => [
            'code'  => 'RPT-REG-01',
            'title' => 'Registration Report',
            'blurb' => 'Every student account with the status of their waiver, COR and school ID.',
            'icon'  => 'fa-clipboard-list',
            'nav'   => 'report_registrations',
        ],
        'tickets' => [
            'code'  => 'RPT-TIX-02',
            'title' => 'Ticket Issuance Report',
            'blurb' => 'Ticket numbers issued, when, to whom — and which have been voided or used.',
            'icon'  => 'fa-ticket',
            'nav'   => 'report_tickets',
        ],
        'attendance' => [
            'code'  => 'RPT-ATT-03',
            'title' => 'Attendance Report',
            'blurb' => 'Gate check-in record with per-gate and per-program totals.',
            'icon'  => 'fa-user-check',
            'nav'   => 'report_attendance',
        ],
    ],
];

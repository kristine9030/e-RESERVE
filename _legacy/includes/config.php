<?php
/**
 * e-RESERVE — Global Configuration
 * Student Registration and Ticketing System
 * SIGLA: A Student's Night 2026 — BatStateU TNEU ARASOF-Nasugbu
 *
 * ---------------------------------------------------------------------------
 * HOW ENTRY WORKS
 * ---------------------------------------------------------------------------
 *   1. Create an account with a BatStateU GSuite address (confirmed by email).
 *   2. Upload three PDFs: signed waiver, COR and school ID.
 *   3. The system issues a TICKET NUMBER with a signed QR code.
 *   4. The QR code is scanned at the gate.
 *
 * Storage      MySQL. Run install.php once; includes/db.php holds the helpers.
 * Documents    Saved outside the web path (uploads/documents) and opened only
 *              through admin/document.php by their owner or SSC staff.
 * Ticket       Issued automatically the moment all three documents are in. SSC
 *              staff review the files afterwards; rejecting one voids the
 *              ticket until the student uploads a corrected copy.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

/* --------------------------------------------------------------------------
 | Session bootstrap (this is our temporary "database" for the prototype)
 * ------------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    session_name('ERESERVE_SESS');
    session_start();
}

/* --------------------------------------------------------------------------
 | Application identity
 * ------------------------------------------------------------------------ */
define('APP_NAME',      'e-RESERVE');
define('APP_TAGLINE',   'Register. Upload. Get your ticket. Celebrate.');
define('APP_VERSION',   '1.0.0-prototype');

/* --------------------------------------------------------------------------
 | Base URL resolver — lets every page (root, /admin, /reports ...) build
 | correct links without worrying about how deep it sits.
 * ------------------------------------------------------------------------ */
$__appRoot = str_replace('\\', '/', dirname(__DIR__));
$__docRoot = str_replace('\\', '/', rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
$__base    = ($__docRoot !== '' && strpos($__appRoot, $__docRoot) === 0)
    ? substr($__appRoot, strlen($__docRoot))
    : '';
$__base = rtrim($__base, '/');
define('BASE_URL', $__base . '/');
define('APP_PATH', $__appRoot);

/* --------------------------------------------------------------------------
 | Event configuration — swap for a DB row (tbl_events) later
 * ------------------------------------------------------------------------ */
define('EVENT_NAME',      "SIGLA: A Student's Night 2026");
define('EVENT_SUBTITLE',  'A flagship event of the 70th Founding Anniversary');
define('EVENT_HOST',      'Batangas State University TNEU – ARASOF Nasugbu');
define('EVENT_ORG',       'Supreme Student Council');
define('EVENT_MOTTO',     'PUSO. DEDIKASYON. TALINO.');
define('EVENT_VENUE',     'HEB Grounds');
define('EVENT_DATE_ISO',  '2026-11-24T17:00:00');   // countdown target
define('EVENT_DATE_TEXT', 'November 24, 2026');
define('EVENT_TIME_TEXT', '5:00 PM – 10:00 PM');
define('EVENT_CAPACITY',  2500);

/** Who is performing, in the order they are shown on the landing page. */
define('EVENT_PERFORMERS', [
    // name, kind, icon, photo (null = placeholder until a photo is supplied)
    ['SIKLAB',      'Dance performance', 'fa-person-walking', 'assets/images/artists/siklab.jpg'],
    ['UNO Band',    'Live band',         'fa-guitar',         'assets/images/artists/uno-band.jpg'],
    ['EXCESS Band', 'Live band',         'fa-guitar',         null],
    ['FitterKarma', 'Live performance',  'fa-music',          'assets/images/artists/fitterkarma.jpg'],
]);

/** Where students reach the SSC. */
define('SSC_FACEBOOK_URL', 'https://www.facebook.com/SSCARASOF');
define('SSC_EMAIL',        'sscnasugbu@g.batstate-u.edu.ph');
define('SSC_MESSENGER',    'Supreme Student Council - Batangas State University ARASOF-Nasugbu Campus');
define('SSC_LOGO_FILE',    'assets/images/ssc-logo.png');

/**
 * Sponsors shown in the sliding strip on the landing page.
 *
 * Each entry: [name, logo image path or null, fallback icon or null].
 *   · with a logo path  → the logo image is shown
 *   · icon, no logo     → icon + name
 *   · neither           → the name alone, set as a plain wordmark
 *
 * !! EXAMPLE SPONSORS !!  The companies below are placeholders showing how big
 * names look in the strip. They have NOT agreed to sponsor SIGLA 2026. Their
 * logos (from Wikimedia Commons) are trademarks of their owners. Replace this
 * list with the real sponsors, and their own logo files, before the site goes
 * public — showing a company as a sponsor without its consent is misleading.
 * Put real logos (transparent PNG/SVG) in assets/images/sponsors/.
 */
define('SPONSORS', [
    ['SM Supermalls', 'assets/images/sponsors/sm.png',          null],
    ['Ayala Malls',   'assets/images/sponsors/ayala.png',       null],
    ['Jollibee',      'assets/images/sponsors/jollibee.png',    null],
    ['Globe',         'assets/images/sponsors/globe.png',       null],
    ['Smart',         'assets/images/sponsors/smart.png',       null],
    ['BDO',           'assets/images/sponsors/bdo.png',         null],
    ['San Miguel',    'assets/images/sponsors/san-miguel.png',  null],
    ['Metrobank',     'assets/images/sponsors/metrobank.png',   null],
]);

/* --------------------------------------------------------------------------
 | Ticketing — one free ticket per student, no tiers, no payment.
 * ------------------------------------------------------------------------ */
define('TICKET_PREFIX',       'SIGLA-2026-');
define('TICKET_LIMIT',        1);        // Business rule: One Student = One Ticket

/**
 * The three requirements. The key is stored in documents.doc_type, so changing
 * a key means changing the ENUM in database/schema.sql as well.
 */
define('DOCUMENT_TYPES', [
    'waiver' => [
        'key'   => 'waiver',
        'label' => 'Signed Waiver',
        'short' => 'Waiver',
        'icon'  => 'fa-file-signature',
        'hint'  => 'Print the waiver, sign it, then scan or photograph it and save it as a PDF.',
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
]);

/** PDF uploads. */
define('DOC_MAX_BYTES', 5 * 1024 * 1024);                // 5 MB each
define('DOC_PATH',      APP_PATH . '/uploads/documents');

/**
 * Passwords the installer gives the seeded demo accounts, so the one-click
 * chips on the sign-in page still work during a presentation.
 * REMOVE both constants and reset those accounts before going live.
 */
define('DEMO_STAFF_PASSWORD',   'SSC@sigla2026');
define('DEMO_STUDENT_PASSWORD', 'Student@2026');

/* --------------------------------------------------------------------------
 | Who may hold an account
 |
 | BatStateU issues more than one mail domain, so this is a list rather than a
 | single string. ADD YOUR OWN here if your campus uses another one — it is the
 | only place the rule is written.
 * ------------------------------------------------------------------------ */
define('ALLOWED_EMAIL_DOMAINS', [
    'g.batstate-u.edu.ph',      // students, faculty and offices
]);

/* --------------------------------------------------------------------------
 | Outgoing mail — used to confirm that an address really belongs to the
 | person registering.
 |
 | MAIL_TRANSPORT:
 |   'smtp' — send for real through the mailbox configured below
 |   'log'  — write the message to /sessions/mail as an .eml file and show the
 |            confirmation link on screen. Nothing leaves the machine, which is
 |            what you want while developing and demoing.
 |
 | For Gmail / Google Workspace use an App Password, never the account
 | password: myaccount.google.com → Security → 2-Step Verification → App
 | passwords. Host smtp.gmail.com, port 465, encryption 'ssl'.
 * ------------------------------------------------------------------------ */
define('MAIL_TRANSPORT',  'log');
define('MAIL_FROM',       'sscnasugbu@g.batstate-u.edu.ph');
define('MAIL_FROM_NAME',  'e-RESERVE · Supreme Student Council');

define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       465);
define('SMTP_ENCRYPTION', 'ssl');            // 'ssl' (465) or 'tls' (587)
define('SMTP_USERNAME',   '');
define('SMTP_PASSWORD',   '');
define('SMTP_TIMEOUT',    15);

/* --------------------------------------------------------------------------
 | Sign in with Google (OAuth 2.0)
 |
 | Leave both empty and the "Continue with Google" button explains that it is
 | not set up yet. To switch it on:
 |   1. console.cloud.google.com → APIs & Services → Credentials →
 |      Create credentials → OAuth client ID → Web application.
 |   2. Authorised redirect URI:  http://localhost/e-RESERVE/google.php
 |      (use your real https URL when it goes live).
 |   3. Paste the client ID and secret below.
 | Only @g.batstate-u.edu.ph accounts are accepted, whatever Google
 | itself allows.
 * ------------------------------------------------------------------------ */
define('GOOGLE_CLIENT_ID',     '');
define('GOOGLE_CLIENT_SECRET', '');

/** How long a confirmation link stays usable. */
define('VERIFY_TOKEN_HOURS', 24);

/* --------------------------------------------------------------------------
 | Database
 * ------------------------------------------------------------------------ */
define('DB_HOST',    '127.0.0.1');
define('DB_PORT',    3306);
define('DB_NAME',    getenv('ERESERVE_DB') ?: 'e_reserve');   // env override is for automated tests
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

/** Storage folder kept for flash files and legacy counters. */
define('STORAGE_PATH', APP_PATH . '/sessions');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/google.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/reports.php';

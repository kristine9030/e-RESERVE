<?php
/**
 * e-RESERVE — Global Configuration
 * Online Ticket Reservation and Payment System
 * SIGLA: A Student's Night 2026 — BatStateU TNEU ARASOF-Nasugbu
 *
 * ---------------------------------------------------------------------------
 * PROTOTYPE NOTE
 * ---------------------------------------------------------------------------
 * This build intentionally uses NO database. All "records" live in PHP
 * sessions + JSON files inside /sessions.
 *
 * TO CONNECT MYSQL LATER:
 *   1. Create includes/db.php that returns a PDO instance using the
 *      DB_* constants defined below.
 *   2. Replace the function bodies in includes/functions.php
 *      (get_reservation / save_reservation / save_payment / ...) with SQL.
 *      The function SIGNATURES are designed to stay identical so no page
 *      markup has to change.
 *
 * TO CONNECT PAYMONGO LATER:
 *   3. Put your live keys in PAYMONGO_PUBLIC_KEY / PAYMONGO_SECRET_KEY.
 *   4. In payment.php, swap mock_paymongo_intent() for a real cURL call to
 *      https://api.paymongo.com/v1/sources (GCash) and handle the webhook in
 *      a new file: /webhooks/paymongo.php
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
define('APP_TAGLINE',   'Reserve. Pay. Track. Celebrate.');
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
define('EVENT_VENUE',     'BatStateU ARASOF-Nasugbu Gymnasium');
define('EVENT_DATE_ISO',  '2026-11-27T18:00:00');   // countdown target
define('EVENT_DATE_TEXT', 'November 27, 2026');
define('EVENT_TIME_TEXT', '6:00 PM – 12:00 MN');
define('EVENT_CAPACITY',  2500);

/* --------------------------------------------------------------------------
 | Ticketing — SIGLA 2026 sells exactly two tiers: VIP and SVIP.
 |
 | REPLACE LATER: this array becomes tbl_ticket_types. Everything that shows a
 | price reads it from here (or from the stored reservation amount), so a price
 | change only ever happens in this one block.
 * ------------------------------------------------------------------------ */
define('TICKET_TIERS', [
    'VIP' => [
        'code'  => 'VIP',
        'name'  => 'VIP',
        'price' => 500.00,
        'seat'  => 'VIP',
        'tone'  => 'green',
        'icon'  => 'fa-ticket',
        'blurb' => 'Reserved VIP seating with full access to every segment of the night.',
        'perks' => [
            'Reserved VIP seating section',
            'Access to all SIGLA 2026 segments',
            'Entry to the post-program afterparty',
            'Official e-ticket with QR gate entry',
        ],
    ],
    'SVIP' => [
        'code'  => 'SVIP',
        'name'  => 'SVIP',
        'price' => 800.00,
        'seat'  => 'SVIP',
        'tone'  => 'gold',
        'icon'  => 'fa-crown',
        'blurb' => 'Front-row seating, priority lane at the gate and the SVIP loot bag.',
        'perks' => [
            'Front-row SVIP seating section',
            'Priority scanning lane — no queue',
            'Access to all SIGLA 2026 segments',
            'Entry to the post-program afterparty',
            'Exclusive SVIP loot bag and lanyard',
        ],
    ],
]);

define('TICKET_DEFAULT_TIER', 'VIP');
define('TICKET_PRICE_FROM',   500.00);   // entry tier — used for "starts at" copy
define('TICKET_PREFIX',       'SIGLA-2026-');
define('TICKET_LIMIT',        1);        // Business rule: One Student = One Ticket

/* --------------------------------------------------------------------------
 | Payment gateway (mock) — replace with live credentials on integration
 * ------------------------------------------------------------------------ */
define('PAYMONGO_PUBLIC_KEY', 'pk_test_MOCK_e_reserve_prototype');
define('PAYMONGO_SECRET_KEY', 'sk_test_MOCK_e_reserve_prototype');
define('PAYMENT_METHOD',      'GCash');
define('PAYMENT_CURRENCY',    'PHP');

/* --------------------------------------------------------------------------
 | Database placeholders (unused in the prototype — kept for a clean migration)
 * ------------------------------------------------------------------------ */
define('DB_HOST', 'localhost');
define('DB_NAME', 'e_reserve');
define('DB_USER', 'root');
define('DB_PASS', '');

/* --------------------------------------------------------------------------
 | Temporary storage folder for JSON "tables"
 * ------------------------------------------------------------------------ */
define('STORAGE_PATH', APP_PATH . '/sessions');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/data.php';

<?php
/**
 * e-RESERVE — Core helper functions
 *
 * MIGRATION NOTE:
 * Every function below is the *only* place that touches storage. When MySQL is
 * introduced, rewrite the bodies (keep the names + return shapes) and the whole
 * UI keeps working untouched.
 */

declare(strict_types=1);

/* ==========================================================================
 | 1. Output / URL helpers
 * ========================================================================*/

/** Escape for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Build an absolute app URL. url('admin/index.php') => /e-RESERVE/admin/index.php */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/** Redirect helper. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Format money the Philippine way. */
function peso(float $amount, bool $symbol = true): string
{
    return ($symbol ? '₱' : '') . number_format($amount, 2);
}

/** Pretty date. */
function pretty_date(?string $datetime, string $format = 'M d, Y · g:i A'): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '—';
}

/** Is the given file the page currently being viewed? Used for nav highlighting. */
function is_active(string $file): bool
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === $file;
}

/* ==========================================================================
 | 2. Flash messages (one-shot notices rendered through SweetAlert2)
 * ========================================================================*/

function set_flash(string $type, string $title, string $text = ''): void
{
    $_SESSION['flash'] = ['type' => $type, 'title' => $title, 'text' => $text];
}

function take_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/* ==========================================================================
 | 3. Authentication (mocked BatStateU GSuite sign-in)
 * ========================================================================*/

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return is_logged_in() && in_array(current_user()['role'] ?? 'student', ['admin', 'finance'], true);
}

/** Guard a student page. */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Session Required', 'Please sign in with your BatStateU GSuite account first.');
        redirect('login.php');
    }
}

/** Guard an admin / finance / reports page. */
function require_admin(): void
{
    if (!is_admin()) {
        set_flash('warning', 'Restricted Area', 'Sign in with a staff account to open the administrative panel.');
        redirect('login.php?panel=admin');
    }
}

/**
 * Build a mock student profile out of a GSuite address.
 * REPLACE LATER: SELECT * FROM tbl_students WHERE email = ?
 */
function mock_student_from_email(string $email): array
{
    $handle = strtolower(trim(explode('@', $email)[0]));
    $handle = preg_replace('/[^a-z0-9._-]/', '', $handle) ?: 'student';

    // Turn "juan.dela.cruz" / "jdelacruz" into a presentable name.
    $parts = preg_split('/[._-]+/', $handle) ?: [$handle];
    $name  = implode(' ', array_map(static fn($p) => ucfirst($p), array_filter($parts)));
    if (mb_strlen($name) < 3) {
        $name = 'BatStateU Student';
    }

    $programs = [
        'BS Information Technology',
        'BS Computer Science',
        'BS Business Administration',
        'BS Hospitality Management',
        'BS Criminology',
        'Bachelor of Elementary Education',
    ];
    $years = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
    $seed  = crc32($handle);

    return [
        'role'       => 'student',
        'name'       => $name,
        'email'      => $email,
        'student_no' => '22-' . str_pad((string) ($seed % 90000 + 10000), 5, '0', STR_PAD_LEFT),
        'program'    => $programs[$seed % count($programs)],
        'year'       => $years[($seed >> 3) % count($years)],
        'college'    => 'ARASOF-Nasugbu Campus',
        'contact'    => '0917-' . str_pad((string) ($seed % 9000000), 7, '0', STR_PAD_LEFT),
        'initials'   => strtoupper(mb_substr($name, 0, 1) . mb_substr(strrchr($name, ' ') ?: $name, 1, 1)),
    ];
}

/** Staff accounts for the admin / finance side of the demo. */
function mock_staff_from_email(string $email, string $role): array
{
    return [
        'role'       => $role,
        'name'       => $role === 'finance' ? 'Finance Officer' : 'SSC Administrator',
        'email'      => $email,
        'student_no' => 'STAFF-' . strtoupper(substr(md5($email), 0, 5)),
        'program'    => $role === 'finance' ? 'Accounting Office' : 'Supreme Student Council',
        'year'       => 'Staff',
        'college'    => 'ARASOF-Nasugbu Campus',
        'contact'    => '(043) 416-0350',
        'initials'   => $role === 'finance' ? 'FO' : 'SA',
    ];
}

/* ==========================================================================
 | 4. Sequence counter — persisted in /sessions/counters.json
 |    (stands in for MySQL AUTO_INCREMENT)
 * ========================================================================*/

function next_sequence(string $key): int
{
    $file = STORAGE_PATH . '/counters.json';

    if (!is_dir(STORAGE_PATH)) {
        @mkdir(STORAGE_PATH, 0777, true);
    }

    $data = [];
    if (is_readable($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $data[$key] = (int) ($data[$key] ?? 0) + 1;

    // If the folder is not writable we still work — the counter just lives in session.
    if (@file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) === false) {
        $_SESSION['counters'][$key] = (int) ($_SESSION['counters'][$key] ?? 0) + 1;
        return $_SESSION['counters'][$key];
    }

    return $data[$key];
}

/* ==========================================================================
 | 5. Reservation lifecycle  (session-backed "tables")
 |    Status flow: pending → paid → issued
 * ========================================================================*/

function get_reservation(): ?array
{
    return $_SESSION['reservation'] ?? null;
}

function get_payment(): ?array
{
    return $_SESSION['payment'] ?? null;
}

function get_ticket(): ?array
{
    return $_SESSION['ticket'] ?? null;
}

function has_reservation(): bool
{
    return !empty($_SESSION['reservation']);
}

function is_paid(): bool
{
    return (get_reservation()['status'] ?? '') === 'paid' || !empty($_SESSION['ticket']);
}

/**
 * Create the one-and-only reservation for the signed-in student.
 * REPLACE LATER: INSERT INTO tbl_reservations (...)
 *
 * @return array{ok:bool, message:string, reservation:?array}
 */
function create_reservation(): array
{
    if (!is_logged_in()) {
        return ['ok' => false, 'message' => 'You need to sign in first.', 'reservation' => null];
    }

    // Business rule: One Student = One Ticket
    if (has_reservation()) {
        return [
            'ok'          => false,
            'message'     => 'You already have an active ticket reservation.',
            'reservation' => get_reservation(),
        ];
    }

    $user = current_user();
    $seq  = next_sequence('reservation');

    $reservation = [
        'code'        => 'RSV-2026-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
        'student_no'  => $user['student_no'],
        'name'        => $user['name'],
        'email'       => $user['email'],
        'program'     => $user['program'],
        'year'        => $user['year'],
        'quantity'    => TICKET_LIMIT,
        'amount'      => TICKET_PRICE,
        'status'      => 'pending',              // pending | paid | cancelled
        'created_at'  => date('Y-m-d H:i:s'),
        'paid_at'     => null,
        'seq'         => $seq,
    ];

    $_SESSION['reservation'] = $reservation;

    return ['ok' => true, 'message' => 'Reservation created.', 'reservation' => $reservation];
}

/** Cancel — used by the "Cancel Reservation" action while still unpaid. */
function cancel_reservation(): bool
{
    if (!has_reservation() || is_paid()) {
        return false;
    }
    unset($_SESSION['reservation'], $_SESSION['payment'], $_SESSION['ticket']);
    return true;
}

/* ==========================================================================
 | 6. Mock PayMongo / GCash
 * ========================================================================*/

/**
 * Stand-in for POST https://api.paymongo.com/v1/sources
 * Returns the reference the checkout screen displays.
 */
function mock_paymongo_intent(float $amount): array
{
    if (empty($_SESSION['pay_intent'])) {
        $_SESSION['pay_intent'] = [
            'reference'  => 'PM-' . strtoupper(bin2hex(random_bytes(5))),
            'source_id'  => 'src_' . strtolower(bin2hex(random_bytes(9))),
            'amount'     => $amount,
            'currency'   => PAYMENT_CURRENCY,
            'type'       => 'gcash',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
    return $_SESSION['pay_intent'];
}

/**
 * Mark the reservation as paid, then auto-generate ticket + receipt.
 * REPLACE LATER: this is what the PayMongo webhook handler should call.
 *
 * @return array{ok:bool, message:string}
 */
function complete_payment(): array
{
    if (!has_reservation()) {
        return ['ok' => false, 'message' => 'No reservation found to pay for.'];
    }
    if (is_paid()) {
        return ['ok' => false, 'message' => 'This reservation is already settled.'];
    }

    $user   = current_user();
    $intent = mock_paymongo_intent(TICKET_PRICE);
    $now    = date('Y-m-d H:i:s');

    // --- 1. Settle the reservation -----------------------------------------
    $_SESSION['reservation']['status']  = 'paid';
    $_SESSION['reservation']['paid_at'] = $now;

    // --- 2. Payment record --------------------------------------------------
    $paySeq = next_sequence('payment');
    $_SESSION['payment'] = [
        'receipt_no'  => 'OR-2026-' . str_pad((string) $paySeq, 6, '0', STR_PAD_LEFT),
        'reference'   => $intent['reference'],
        'source_id'   => $intent['source_id'],
        'method'      => PAYMENT_METHOD,
        'amount'      => TICKET_PRICE,
        'currency'    => PAYMENT_CURRENCY,
        'status'      => 'succeeded',
        'paid_at'     => $now,
        'payer'       => $user['name'],
        'payer_email' => $user['email'],
    ];

    // --- 3. Auto-generated ticket ------------------------------------------
    $ticketSeq = next_sequence('ticket');
    $_SESSION['ticket'] = [
        'ticket_no'   => TICKET_PREFIX . str_pad((string) $ticketSeq, 6, '0', STR_PAD_LEFT),
        'name'        => $user['name'],
        'student_no'  => $user['student_no'],
        'program'     => $user['program'],
        'year'        => $user['year'],
        'email'       => $user['email'],
        'event'       => EVENT_NAME,
        'venue'       => EVENT_VENUE,
        'date_text'   => EVENT_DATE_TEXT,
        'time_text'   => EVENT_TIME_TEXT,
        'seat'        => 'GA-' . str_pad((string) $ticketSeq, 4, '0', STR_PAD_LEFT),
        'status'      => 'valid',           // valid | used | void
        'issued_at'   => $now,
        'checked_in'  => false,
    ];

    unset($_SESSION['pay_intent']);

    return ['ok' => true, 'message' => 'Payment successful.'];
}

/* ==========================================================================
 | 7. Reservation tracking timeline
 * ========================================================================*/

/**
 * Returns the five tracking stages with a state of done | current | upcoming.
 */
function tracking_timeline(): array
{
    $res    = get_reservation();
    $pay    = get_payment();
    $ticket = get_ticket();

    $stages = [
        [
            'key'   => 'submitted',
            'label' => 'Reservation Submitted',
            'icon'  => 'fa-file-circle-check',
            'desc'  => 'Your slot request was recorded in the system.',
            'stamp' => $res['created_at'] ?? null,
            'done'  => (bool) $res,
        ],
        [
            'key'   => 'pending',
            'label' => 'Pending Payment',
            'icon'  => 'fa-hourglass-half',
            'desc'  => 'Settle ' . peso(TICKET_PRICE) . ' via GCash to secure your ticket.',
            'stamp' => $res['created_at'] ?? null,
            'done'  => (bool) $res,
        ],
        [
            'key'   => 'paid',
            'label' => 'Payment Successful',
            'icon'  => 'fa-circle-check',
            'desc'  => 'PayMongo confirmed your GCash transaction.',
            'stamp' => $pay['paid_at'] ?? null,
            'done'  => (bool) $pay,
        ],
        [
            'key'   => 'issued',
            'label' => 'Ticket Generated',
            'icon'  => 'fa-ticket',
            'desc'  => 'Your e-ticket and official receipt are ready to download.',
            'stamp' => $ticket['issued_at'] ?? null,
            'done'  => (bool) $ticket,
        ],
        [
            'key'   => 'ready',
            'label' => 'Ready for Event',
            'icon'  => 'fa-star',
            'desc'  => 'Present your QR code at the ' . EVENT_VENUE . ' entrance.',
            'stamp' => $ticket['issued_at'] ?? null,
            'done'  => (bool) $ticket,
        ],
    ];

    // Mark the first not-done stage as "current".
    $currentAssigned = false;
    foreach ($stages as $i => $stage) {
        if (!$stage['done'] && !$currentAssigned) {
            $stages[$i]['state'] = 'current';
            $currentAssigned = true;
        } elseif ($stage['done']) {
            $stages[$i]['state'] = 'done';
        } else {
            $stages[$i]['state'] = 'upcoming';
        }
    }

    // Special case: reserved but unpaid → "Pending Payment" is the live stage.
    if ($res && !$pay) {
        $stages[1]['state'] = 'current';
    }

    return $stages;
}

/** Human label + colour for the current overall stage. */
function reservation_stage_label(): array
{
    if (!has_reservation()) {
        return ['No Reservation', 'muted', 'fa-circle-minus'];
    }
    if (!is_paid()) {
        return ['Pending Payment', 'warning', 'fa-hourglass-half'];
    }
    return ['Ready for Event', 'success', 'fa-circle-check'];
}

/* ==========================================================================
 | 8. Presentation helpers
 * ========================================================================*/

/** Bootstrap-ish badge for a status string. */
function status_badge(string $status): string
{
    $map = [
        'paid'      => ['success', 'Paid'],
        'succeeded' => ['success', 'Succeeded'],
        'valid'     => ['success', 'Valid'],
        'issued'    => ['success', 'Issued'],
        'checked'   => ['info',    'Checked In'],
        'used'      => ['info',    'Used'],
        'pending'   => ['warning', 'Pending'],
        'unpaid'    => ['warning', 'Unpaid'],
        'failed'    => ['danger',  'Failed'],
        'cancelled' => ['danger',  'Cancelled'],
        'void'      => ['danger',  'Void'],
        'refunded'  => ['muted',   'Refunded'],
    ];
    [$tone, $label] = $map[strtolower($status)] ?? ['muted', ucfirst($status)];
    return '<span class="badge-soft badge-' . $tone . '">' . e($label) . '</span>';
}

/**
 * Placeholder QR code. Draws a deterministic, QR-looking SVG from the payload
 * so every ticket looks unique — no library, no network call.
 * REPLACE LATER: endroid/qr-code or a QR API using the ticket number.
 */
function qr_placeholder(string $payload, int $size = 190): string
{
    $grid  = 21;                       // classic QR v1 module count
    $cell  = $size / $grid;
    $hash  = hash('sha256', $payload);
    $bits  = '';
    foreach (str_split($hash) as $ch) {
        $bits .= str_pad(base_convert($ch, 16, 2), 4, '0', STR_PAD_LEFT);
    }

    $svg  = '<svg class="qr-svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" role="img" aria-label="QR code placeholder">';
    $svg .= '<rect width="' . $size . '" height="' . $size . '" fill="#ffffff"/>';

    // Finder eyes at three corners.
    $eye = static function (float $x, float $y) use ($cell): string {
        $s = '';
        $s .= '<rect x="' . ($x * $cell) . '" y="' . ($y * $cell) . '" width="' . (7 * $cell) . '" height="' . (7 * $cell) . '" fill="#0B3B2C"/>';
        $s .= '<rect x="' . (($x + 1) * $cell) . '" y="' . (($y + 1) * $cell) . '" width="' . (5 * $cell) . '" height="' . (5 * $cell) . '" fill="#ffffff"/>';
        $s .= '<rect x="' . (($x + 2) * $cell) . '" y="' . (($y + 2) * $cell) . '" width="' . (3 * $cell) . '" height="' . (3 * $cell) . '" fill="#0B3B2C"/>';
        return $s;
    };
    $svg .= $eye(0, 0) . $eye($grid - 7, 0) . $eye(0, $grid - 7);

    // Data modules.
    $i = 0;
    for ($r = 0; $r < $grid; $r++) {
        for ($c = 0; $c < $grid; $c++) {
            $inEye = ($r < 8 && $c < 8) || ($r < 8 && $c > $grid - 9) || ($r > $grid - 9 && $c < 8);
            if ($inEye) {
                continue;
            }
            $bit = $bits[$i % strlen($bits)];
            $i++;
            if ($bit === '1') {
                $svg .= '<rect x="' . ($c * $cell) . '" y="' . ($r * $cell) . '" width="' . $cell . '" height="' . $cell . '" fill="#0B3B2C"/>';
            }
        }
    }
    $svg .= '</svg>';
    return $svg;
}

/** Small deterministic pseudo-random helper for the dummy analytics. */
function seeded(int $seed, int $min, int $max): int
{
    mt_srand($seed);
    $v = mt_rand($min, $max);
    mt_srand();
    return $v;
}

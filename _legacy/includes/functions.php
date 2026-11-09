<?php
/**
 * e-RESERVE — Core helper functions
 *
 * Every function below is the only place that touches storage. Storage is
 * MySQL (see includes/db.php); nothing here builds SQL by concatenation.
 *
 * Entry model: a student uploads a signed waiver, COR and school ID (PDF).
 * Once all three are in, a ticket number is issued automatically; its signed
 * QR code is what the gate scans. Staff can reject a document, which voids
 * the ticket until the student replaces it.
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
 | 3. Accounts and sessions
 * ========================================================================*/

function is_logged_in(): bool
{
    return !empty($_SESSION['user']['id']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return is_logged_in() && in_array(current_user()['role'] ?? 'student', ['admin'], true);
}

/** Guard a student page. */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Session Required', 'Please sign in with your BatStateU GSuite account first.');
        redirect('login.php');
    }
}

/**
 * Guard a page that commits a student to something — uploading documents.
 * Browsing is fine unverified; acting is not.
 */
function require_verified(): void
{
    require_login();

    if (!is_email_verified()) {
        set_flash(
            'warning',
            'Confirm your email first',
            'We sent a confirmation link to <strong>' . e(current_user()['email']) . '</strong>. '
            . 'Open it so we know the account is really yours.'
        );
        redirect('verify.php');
    }
}

/** Guard an admin / reports page. */
function require_admin(): void
{
    if (!is_admin()) {
        set_flash('warning', 'Restricted Area', 'Sign in with a staff account to open the administrative panel.');
        redirect('login.php?panel=admin');
    }
}

/** Shape a users row into the array every page expects in $_SESSION['user']. */
function user_session_row(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'role'       => $row['role'],
        'name'       => $row['name'],
        'email'      => $row['email'],
        'student_no' => $row['student_no'] ?? ('STAFF-' . strtoupper(substr(md5($row['email']), 0, 5))),
        'program'    => $row['program'] ?? 'Supreme Student Council',
        'year'       => $row['year_level'] ?? 'Staff',
        'college'    => 'ARASOF-Nasugbu Campus',
        'contact'    => $row['mobile'] ?? '',
        'initials'   => $row['initials'] ?: strtoupper(mb_substr($row['name'], 0, 2)),
    ];
}

/**
 * Sign in.
 *
 * Staff must present a password. Students authenticate by GSuite address only,
 * which is the campus's own identity check for the prototype; their account row
 * is created on first sign-in.
 *
 * @return array{ok:bool, message:string, user:?array}
 */
function attempt_login(string $email, string $password = ''): array
{
    $email = strtolower(trim($email));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'That does not look like a valid email address.', 'user' => null];
    }

    if (!email_domain_allowed($email)) {
        return ['ok' => false, 'message' => domain_rejection_message($email), 'user' => null];
    }

    /* Slow down guessing without locking a whole classroom out. */
    if ($wait = rate_limit('login', 8, 300)) {
        return ['ok' => false, 'message' => 'Too many sign-in attempts. Try again in ' . $wait . ' seconds.', 'user' => null];
    }

    if ($password === '') {
        return ['ok' => false, 'message' => 'Enter your password.', 'user' => null];
    }

    $row = db_one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

    /* One wording for every failure — a wrong password and an email that was
       never registered must be indistinguishable, or this form becomes a way
       to discover which accounts exist. password_verify() still runs against a
       dummy hash so the reply takes the same time either way. */
    $generic = ['ok' => false, 'message' => 'Incorrect email or password.', 'user' => null];

    if ($row === null) {
        password_verify($password, '$2y$12$usesomesillystringforeadingtimeconstantcomparisonxxxxxxxxxxxx');
        return $generic;
    }

    if (empty($row['password_hash']) || !password_verify($password, $row['password_hash'])) {
        return $generic;
    }

    if ($row['status'] !== 'active') {
        return ['ok' => false, 'message' => 'That account has been disabled. Please contact the SSC.', 'user' => null];
    }

    /* Move an old hash up to the current cost when the algorithm moves on. */
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }

    db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$row['id']]);

    /* A fresh session id on every sign-in defeats session fixation. */
    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    forget_registration_cache();

    $_SESSION['user']         = user_session_row($row);
    $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');

    audit('login', 'user', (string) $row['id']);

    return ['ok' => true, 'message' => 'Signed in.', 'user' => $_SESSION['user']];
}

/* --------------------------------------------------------------------------
 | Which email addresses may hold an account
 * ------------------------------------------------------------------------ */

/** Is this address on one of the university's mail domains? */
function email_domain_allowed(string $email): bool
{
    $at = strrpos($email, '@');
    if ($at === false) {
        return false;
    }

    $domain = strtolower(substr($email, $at + 1));

    return in_array($domain, array_map('strtolower', ALLOWED_EMAIL_DOMAINS), true);
}

/** "@g.batstate-u.edu.ph or @batstate-u.edu.ph" — for error messages. */
function allowed_domains_text(): string
{
    $list = array_map(static fn(string $d): string => '@' . $d, ALLOWED_EMAIL_DOMAINS);

    if (count($list) === 1) {
        return $list[0];
    }

    $last = array_pop($list);

    return implode(', ', $list) . ' or ' . $last;
}

/**
 * A near-miss domain is almost always a typo — "g.bastate-u.edu.ph" for
 * "g.batstate-u.edu.ph". Point at the intended one instead of just refusing,
 * because a student staring at their own address rarely spots the missing
 * letter. Returns null when the domain is nowhere near ours.
 */
function suggest_email_domain(string $email): ?string
{
    $at = strrpos($email, '@');
    if ($at === false) {
        return null;
    }

    $typed = strtolower(substr($email, $at + 1));
    $best  = null;
    $score = PHP_INT_MAX;

    foreach (ALLOWED_EMAIL_DOMAINS as $domain) {
        $distance = levenshtein($typed, strtolower($domain));
        if ($distance < $score) {
            $score = $distance;
            $best  = $domain;
        }
    }

    /* Up to three edits away — enough for a dropped or doubled letter, not so
       loose that "gmail.com" gets "corrected" into a university address. */
    return ($best !== null && $score > 0 && $score <= 3) ? $best : null;
}

/** The message shown when an address is not on a university domain. */
function domain_rejection_message(string $email): string
{
    $suggestion = suggest_email_domain($email);

    if ($suggestion !== null) {
        $local = substr($email, 0, (int) strrpos($email, '@'));
        return 'Check the spelling — did you mean ' . $local . '@' . $suggestion . '?';
    }

    return 'Use your university address (' . allowed_domains_text() . ').';
}

/* --------------------------------------------------------------------------
 | Registration
 * ------------------------------------------------------------------------ */

/**
 * Programmes offered per college, taken from the registrar's enrollment
 * summary (A.Y. 2026-2027, 1st semester). Majors are listed as their own
 * choice because the masterlist enrols students under them separately.
 *
 * @return array<string, list<string>>
 */
function college_programs(): array
{
    return [
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
    ];
}

/** Is this programme offered by that college? */
function program_in_college(string $program, string $college): bool
{
    return in_array($program, college_programs()[$college] ?? [], true);
}

function year_levels(): array
{
    return ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
}

/**
 * Colleges of ARASOF-Nasugbu whose students may attend (code => full name).
 * The Laboratory School is deliberately absent: it is not part of the event.
 */
function colleges(): array
{
    return [
        'CABEIHM' => 'College of Accountancy, Business, Economics and International Hospitality Management',
        'CICS'    => 'College of Informatics and Computing Sciences',
        'CTE'     => 'College of Teacher Education',
        'CHS'     => 'College of Health Sciences',
        'CCJE'    => 'College of Criminal Justice Education',
        'CAS'     => 'College of Arts and Sciences',
    ];
}

/**
 * Check an SR-code against the registrar's masterlist (enrolled_students).
 *
 * @return string Empty when the student is on the list under that college,
 *                otherwise the message to show.
 */
function enrollment_problem(string $studentNo, string $college): string
{
    $row = db_one('SELECT college FROM enrolled_students WHERE sr_code = ?', [$studentNo]);

    if ($row === null) {
        return 'That SR-code is not on the ARASOF-Nasugbu masterlist for A.Y. 2026-2027. Check for typos, or contact the SSC.';
    }
    if ($row['college'] !== $college) {
        return 'That SR-code is enrolled under a different college. Please choose your correct college.';
    }
    return '';
}

/**
 * Is this password good enough to guard a ticket worth real money?
 * Length does more work than symbol soup, so the bar is 8+ with a letter and
 * a digit — enough to stop the obvious, short enough that students comply.
 *
 * @return string Empty when acceptable, otherwise the reason.
 */
function password_problem(string $password): string
{
    if (mb_strlen($password) < 8) {
        return 'Use at least 8 characters.';
    }
    if (mb_strlen($password) > 200) {
        return 'That password is too long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Include at least one letter and one number.';
    }
    if (in_array(strtolower($password), ['password1', '12345678', 'qwerty123', 'sigla2026', 'password123'], true)) {
        return 'That password is too easy to guess. Please pick another.';
    }
    return '';
}

/**
 * Create a student account.
 *
 * Students register themselves; staff accounts are seeded by the SSC and can
 * never be created here — role is hard-coded, not read from the form.
 *
 * @return array{ok:bool, message:string, field:?string, user:?array}
 */
function register_student(array $input): array
{
    $fail = static fn(string $msg, ?string $field = null) =>
        ['ok' => false, 'message' => $msg, 'field' => $field, 'user' => null,
         'mail_ok' => false, 'mail_link' => null, 'logged_to' => null];

    $name      = trim((string) ($input['name'] ?? ''));
    $email     = strtolower(trim((string) ($input['email'] ?? '')));
    $studentNo = strtoupper(trim((string) ($input['student_no'] ?? '')));
    $college   = trim((string) ($input['college'] ?? ''));
    $program   = trim((string) ($input['program'] ?? ''));
    $year      = trim((string) ($input['year_level'] ?? ''));
    $mobile    = trim((string) ($input['mobile'] ?? ''));
    $password  = (string) ($input['password'] ?? '');
    $confirm   = (string) ($input['password_confirm'] ?? '');

    /* --- Identity ------------------------------------------------------ */
    if (mb_strlen($name) < 3) {
        return $fail('Enter your full name as it appears on your student record.', 'name');
    }
    if (mb_strlen($name) > 160) {
        return $fail('That name is too long.', 'name');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $fail('That does not look like a valid email address.', 'email');
    }
    if (!email_domain_allowed($email)) {
        return $fail(domain_rejection_message($email), 'email');
    }

    /* Older codes carry an N prefix (e.g. N14-02607); they are on the masterlist too. */
    if (!preg_match('/^N?\d{2}-\d{5}$/', $studentNo)) {
        return $fail('Your SR-code must look like 22-10234.', 'student_no');
    }

    if (!isset(colleges()[$college])) {
        return $fail('Choose your college from the list.', 'college');
    }
    if (!program_in_college($program, $college)) {
        return $fail('Choose a programme that belongs to your college.', 'program');
    }
    if (!in_array($year, year_levels(), true)) {
        return $fail('Choose your year level.', 'year_level');
    }

    if ($mobile !== '' && !preg_match('/^09\d{9}$/', preg_replace('/\D/', '', $mobile) ?? '')) {
        return $fail('Enter your mobile number as 09XXXXXXXXX.', 'mobile');
    }
    $mobile = $mobile === '' ? null : preg_replace('/\D/', '', $mobile);

    /* --- Is this really an ARASOF student? ------------------------------ */
    if ($problem = enrollment_problem($studentNo, $college)) {
        return $fail($problem, 'student_no');
    }

    /* --- Password ------------------------------------------------------ */
    if ($problem = password_problem($password)) {
        return $fail($problem, 'password');
    }
    if (!hash_equals($password, $confirm)) {
        return $fail('The two passwords do not match.', 'password_confirm');
    }

    /* --- Abuse control -------------------------------------------------- */
    if ($wait = rate_limit('register', 5, 900)) {
        return $fail('Too many sign-up attempts. Please wait ' . $wait . ' seconds.');
    }

    /* --- Duplicates ----------------------------------------------------- */
    if (db_value('SELECT COUNT(*) FROM users WHERE email = ?', [$email], 0)) {
        return $fail('An account already exists for that email. Sign in instead.', 'email');
    }
    if (db_value('SELECT COUNT(*) FROM users WHERE student_no = ?', [$studentNo], 0)) {
        return $fail('That SR-code is already registered.', 'student_no');
    }

    $parts    = preg_split('/\s+/', $name) ?: [$name];
    $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr((string) end($parts), 0, 1));

    try {
        $id = db_insert(
            'INSERT INTO users (email, password_hash, role, name, initials, student_no, college_code, program, year_level, mobile, status, created_at)
             VALUES (?, ?, "student", ?, ?, ?, ?, ?, ?, ?, "active", NOW())',
            [
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $name, $initials, $studentNo, $college, $program, $year, $mobile,
            ]
        );
    } catch (PDOException $ex) {
        /* A duplicate that slipped past the checks above — the unique indexes
           are the real guard, the checks only make the message friendlier. */
        if ($ex->getCode() === '23000') {
            return $fail('An account with that email or SR-code already exists.', 'email');
        }
        throw $ex;
    }

    $row = db_one('SELECT * FROM users WHERE id = ?', [$id]);

    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    forget_registration_cache();

    $_SESSION['user']         = user_session_row($row);
    $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');

    audit('user.register', 'user', (string) $id, ['student_no' => $studentNo]);

    $mail = send_verification_email($id);

    return [
        'ok'        => true,
        'message'   => 'Account created.',
        'field'     => null,
        'user'      => $_SESSION['user'],
        'mail_ok'   => $mail['ok'],
        'mail_link' => $mail['link'],
        'logged_to' => $mail['logged_to'],
    ];
}

/**
 * Finish a sign-up that began with "Continue with Google".
 *
 * The address comes from the session (Google proved it), never from the form.
 * No password is stored: this account signs in through Google.
 *
 * @return array{ok:bool, message:string, field:?string, user:?array}
 */
function register_google_student(array $input): array
{
    $fail = static fn(string $msg, ?string $field = null) => ['ok' => false, 'message' => $msg, 'field' => $field, 'user' => null];

    $pending = $_SESSION['google_pending'] ?? null;
    if (!$pending || empty($pending['email'])) {
        return $fail('Your Google sign-in expired. Please start again.');
    }

    $email     = strtolower((string) $pending['email']);
    $name      = trim((string) ($input['name'] ?? ''));
    $studentNo = strtoupper(trim((string) ($input['student_no'] ?? '')));
    $college   = trim((string) ($input['college'] ?? ''));
    $program   = trim((string) ($input['program'] ?? ''));
    $year      = trim((string) ($input['year_level'] ?? ''));
    $mobile    = trim((string) ($input['mobile'] ?? ''));

    if (!email_domain_allowed($email)) {
        return $fail(domain_rejection_message($email));
    }
    if (mb_strlen($name) < 3 || mb_strlen($name) > 160) {
        return $fail('Enter your full name as it appears on your student record.', 'name');
    }
    if (!preg_match('/^N?\d{2}-\d{5}$/', $studentNo)) {
        return $fail('Your SR-code must look like 22-10234.', 'student_no');
    }
    if (!isset(colleges()[$college])) {
        return $fail('Choose your college from the list.', 'college');
    }
    if (!program_in_college($program, $college)) {
        return $fail('Choose a programme that belongs to your college.', 'program');
    }
    if (!in_array($year, year_levels(), true)) {
        return $fail('Choose your year level.', 'year_level');
    }
    if ($mobile !== '' && !preg_match('/^09\d{9}$/', preg_replace('/\D/', '', $mobile) ?? '')) {
        return $fail('Enter your mobile number as 09XXXXXXXXX.', 'mobile');
    }
    $mobile = $mobile === '' ? null : preg_replace('/\D/', '', $mobile);

    if ($problem = enrollment_problem($studentNo, $college)) {
        return $fail($problem, 'student_no');
    }

    if (db_value('SELECT COUNT(*) FROM users WHERE email = ?', [$email], 0)) {
        return $fail('An account already exists for that email. Sign in instead.', 'email');
    }
    if (db_value('SELECT COUNT(*) FROM users WHERE student_no = ?', [$studentNo], 0)) {
        return $fail('That SR-code is already registered.', 'student_no');
    }

    $parts    = preg_split('/\s+/', $name) ?: [$name];
    $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr((string) end($parts), 0, 1));

    try {
        $id = db_insert(
            'INSERT INTO users (email, password_hash, role, name, initials, student_no, college_code, program, year_level, mobile, status, email_verified_at, created_at)
             VALUES (?, NULL, "student", ?, ?, ?, ?, ?, ?, ?, "active", NOW(), NOW())',
            [$email, $name, $initials, $studentNo, $college, $program, $year, $mobile]
        );
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            return $fail('An account with that email or SR-code already exists.', 'student_no');
        }
        throw $ex;
    }

    $row = db_one('SELECT * FROM users WHERE id = ?', [$id]);

    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    forget_registration_cache();
    unset($_SESSION['google_pending']);
    $_SESSION['user']         = user_session_row($row);
    $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');

    audit('user.register.google', 'user', (string) $id, ['student_no' => $studentNo]);

    return ['ok' => true, 'message' => 'Account created.', 'field' => null, 'user' => $_SESSION['user']];
}

/* --------------------------------------------------------------------------
 | Email confirmation
 |
 | A registration only proves someone typed an address. Sending a one-time link
 | to it and requiring a click proves they can read that mailbox — which, on a
 | university domain nobody else can issue, is what makes the account legit.
 * ------------------------------------------------------------------------ */

/** Has the signed-in user proved they own their address? */
function is_email_verified(): bool
{
    if (!is_logged_in()) {
        return false;
    }

    /* Staff accounts are created by the SSC, not self-registered. */
    if (is_admin()) {
        return true;
    }

    return db_value('SELECT email_verified_at FROM users WHERE id = ?', [current_user()['id']]) !== null;
}

/**
 * Issue a fresh confirmation link and email it.
 *
 * Only the SHA-256 of the token is stored, so a leaked database still cannot
 * be used to confirm anybody's account.
 *
 * @return array{ok:bool, message:string, link:?string, logged_to:?string}
 */
function send_verification_email(int $userId): array
{
    $user = db_one('SELECT id, name, email, email_verified_at, verify_sent_at FROM users WHERE id = ?', [$userId]);

    if (!$user) {
        return ['ok' => false, 'code' => 'missing', 'message' => 'That account no longer exists.', 'link' => null, 'logged_to' => null];
    }
    if ($user['email_verified_at'] !== null) {
        return ['ok' => false, 'code' => 'already', 'message' => 'That address is already confirmed.', 'link' => null, 'logged_to' => null];
    }

    /* Don't let anyone use us to flood a mailbox. */
    if ($user['verify_sent_at'] !== null && strtotime($user['verify_sent_at']) > time() - 60) {
        $wait = 60 - (time() - strtotime($user['verify_sent_at']));
        return ['ok' => false, 'code' => 'throttled', 'message' => 'We already sent one a moment ago. Check your inbox, or ask again in ' . $wait . ' seconds.', 'link' => null, 'logged_to' => null];
    }

    $token = bin2hex(random_bytes(32));

    db_run(
        'UPDATE users
         SET verify_token_hash = ?, verify_expires_at = DATE_ADD(NOW(), INTERVAL ? HOUR), verify_sent_at = NOW()
         WHERE id = ?',
        [hash('sha256', $token), VERIFY_TOKEN_HOURS, $userId]
    );

    $link = rtrim(app_base_url(), '/') . '/verify.php?token=' . $token;

    $sent = send_mail(
        $user['email'],
        $user['name'],
        'Confirm your e-RESERVE account · ' . EVENT_NAME,
        verification_email_html($user['name'], $link),
        verification_email_text($user['name'], $link)
    );

    audit('email.verification_sent', 'user', (string) $userId, ['transport' => MAIL_TRANSPORT]);

    return [
        'ok'        => $sent['ok'],
        'code'      => $sent['ok'] ? 'sent' : 'mail_failed',
        'message'   => $sent['message'],
        'link'      => $link,
        'logged_to' => $sent['logged_to'],
    ];
}

/**
 * Confirm an address from the emailed token.
 *
 * @return array{ok:bool, message:string, user:?array}
 */
function verify_email_token(string $token): array
{
    $token = trim($token);

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return ['ok' => false, 'message' => 'That confirmation link is not valid.', 'user' => null];
    }

    if ($wait = rate_limit('verify', 12, 600)) {
        return ['ok' => false, 'message' => 'Too many attempts. Try again in ' . $wait . ' seconds.', 'user' => null];
    }

    /* The hash column is indexed, so this is a single lookup rather than a
       scan — and it never exposes the token itself. */
    $row = db_one('SELECT * FROM users WHERE verify_token_hash = ? LIMIT 1', [hash('sha256', $token)]);

    if (!$row) {
        return ['ok' => false, 'message' => 'That link has already been used, or it is not valid.', 'user' => null];
    }

    if ($row['verify_expires_at'] !== null && strtotime($row['verify_expires_at']) < time()) {
        return ['ok' => false, 'message' => 'That link has expired. Sign in and ask for a new one.', 'user' => null];
    }

    /* Clearing the hash makes the link single-use. */
    db_run(
        'UPDATE users
         SET email_verified_at = NOW(), verify_token_hash = NULL, verify_expires_at = NULL
         WHERE id = ?',
        [$row['id']]
    );

    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    forget_registration_cache();

    $row['email_verified_at'] = date('Y-m-d H:i:s');
    $_SESSION['user']         = user_session_row($row);
    $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');

    audit('email.verified', 'user', (string) $row['id']);

    return ['ok' => true, 'message' => 'Email confirmed.', 'user' => $_SESSION['user']];
}

/** Absolute base URL, needed because an email cannot follow a relative link. */
function app_base_url(): string
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . rtrim(BASE_URL, '/');
}

/**
 * Derive a presentable student profile from a GSuite address. Used to
 * pre-fill the sign-up form when someone arrives with their address typed.
 */
function student_profile_from_email(string $email): array
{
    $handle = strtolower(trim(explode('@', $email)[0]));
    $handle = preg_replace('/[^a-z0-9._-]/', '', $handle) ?: 'student';

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
        'contact'    => '0917' . str_pad((string) ($seed % 9000000), 7, '0', STR_PAD_LEFT),
        'initials'   => strtoupper(mb_substr($name, 0, 1) . mb_substr(strrchr($name, ' ') ?: $name, 1, 1)),
    ];
}

/* ==========================================================================
 | 4. Document sequences
 * ========================================================================*/

function next_sequence(string $key): int
{
    return db_next_sequence($key);
}

/* ==========================================================================
 | 5. Documents and the ticket
 |
 |    account → 3 PDFs uploaded → ticket number issued → QR scanned at the gate
 |
 |    A document is `submitted` when uploaded, then `approved` or `rejected` by
 |    SSC staff. A ticket exists as soon as all three are in. Rejecting any one
 |    voids the ticket; a corrected upload brings the same ticket back.
 * ========================================================================*/

/** The three requirements, in the order the student sees them. */
function document_types(): array
{
    return DOCUMENT_TYPES;
}

function is_document_type(?string $key): bool
{
    return $key !== null && array_key_exists($key, DOCUMENT_TYPES);
}

/**
 * Backing store for the per-request cache. Returned by reference so
 * forget_registration_cache() can actually empty it.
 */
function &registration_cache_store(): array
{
    static $store = [];
    return $store;
}

/** Drop the per-request cache. Must be called after any write. */
function forget_registration_cache(): void
{
    $store = &registration_cache_store();
    $store = [];
}

/**
 * A student's uploaded documents, keyed by type. Missing types are absent.
 *
 * @return array<string,array>
 */
function user_documents(?int $userId = null): array
{
    $userId ??= (int) (current_user()['id'] ?? 0);
    if ($userId === 0) {
        return [];
    }

    $store = &registration_cache_store();
    if (isset($store['docs'][$userId])) {
        return $store['docs'][$userId];
    }

    $out = [];
    foreach (db_all('SELECT * FROM documents WHERE user_id = ?', [$userId]) as $row) {
        $out[$row['doc_type']] = [
            'id'          => (int) $row['id'],
            'type'        => $row['doc_type'],
            'name'        => $row['original_name'],
            'size'        => (int) $row['size_bytes'],
            'status'      => $row['status'],
            'note'        => $row['review_note'],
            'uploaded_at' => $row['uploaded_at'],
            'reviewed_at' => $row['reviewed_at'],
        ];
    }

    return $store['docs'][$userId] = $out;
}

/**
 * Where the student stands on the three requirements.
 *
 * @return array{total:int,uploaded:int,approved:int,rejected:int,missing:array,complete:bool,blocked:bool}
 */
function document_progress(?int $userId = null): array
{
    $docs     = user_documents($userId);
    $missing  = array_values(array_diff(array_keys(DOCUMENT_TYPES), array_keys($docs)));
    $statuses = array_column($docs, 'status');

    $rejected = count(array_keys($statuses, 'rejected', true));

    return [
        'total'    => count(DOCUMENT_TYPES),
        'uploaded' => count($docs),
        'approved' => count(array_keys($statuses, 'approved', true)),
        'rejected' => $rejected,
        'missing'  => $missing,
        'complete' => $missing === [] && $rejected === 0,
        /* A rejected file stops the ticket until it is replaced. */
        'blocked'  => $rejected > 0,
    ];
}

/** The signed-in student's ticket, or null. */
function get_ticket(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    $uid   = (int) current_user()['id'];
    $store = &registration_cache_store();

    if (array_key_exists($uid, $store['ticket'] ?? [])) {
        return $store['ticket'][$uid];
    }

    $row = db_one(
        'SELECT t.*, u.name, u.email, u.student_no, u.program, u.year_level,
                c.scanned_at, c.gate
         FROM tickets t
         JOIN users u ON u.id = t.user_id
         LEFT JOIN checkins c ON c.ticket_id = t.id
         WHERE t.user_id = ? LIMIT 1',
        [$uid]
    );

    return $store['ticket'][$uid] = ($row === null ? null : ticket_shape($row));
}

function ticket_shape(array $row): array
{
    return [
        'id'          => (int) $row['id'],
        'ticket_no'   => $row['ticket_no'],
        'name'        => $row['name'] ?? '',
        'student_no'  => $row['student_no'] ?? '',
        'program'     => $row['program'] ?? '',
        'year'        => $row['year_level'] ?? '',
        'email'       => $row['email'] ?? '',
        'event'       => EVENT_NAME,
        'venue'       => EVENT_VENUE,
        'date_text'   => EVENT_DATE_TEXT,
        'time_text'   => EVENT_TIME_TEXT,
        'status'      => $row['status'],
        'verify_hash' => $row['verify_hash'],
        'issued_at'   => $row['issued_at'],
        'checked_in'  => !empty($row['scanned_at']),
        'checked_at'  => $row['scanned_at'] ?? null,
        'gate'        => $row['gate'] ?? null,
    ];
}

function has_ticket(): bool
{
    $t = get_ticket();
    return $t !== null && $t['status'] !== 'void';
}

/** How many seats are still unclaimed. */
function slots_remaining(): int
{
    $taken = (int) db_value('SELECT COUNT(*) FROM tickets WHERE status <> "void"', [], 0);
    return max(0, EVENT_CAPACITY - $taken);
}

/**
 * A per-installation secret, generated once and kept out of source control.
 * Used to sign ticket QR payloads.
 */
function app_secret(): string
{
    static $secret = null;

    if ($secret !== null) {
        return $secret;
    }

    $file = STORAGE_PATH . '/app_secret.key';

    if (is_readable($file)) {
        $secret = trim((string) file_get_contents($file));
        if ($secret !== '') {
            return $secret;
        }
    }

    if (!is_dir(STORAGE_PATH)) {
        @mkdir(STORAGE_PATH, 0755, true);
    }

    $secret = bin2hex(random_bytes(32));
    @file_put_contents($file, $secret);
    @chmod($file, 0600);

    return $secret;
}

/**
 * Signature baked into a ticket's QR payload.
 *
 * The gate scanner recomputes it, so a made-up ticket number fails
 * verification even offline.
 */
function ticket_verify_hash(string $ticketNo, int $userId): string
{
    return hash_hmac('sha256', $ticketNo . '|' . $userId, app_secret());
}

/**
 * Bring a student's ticket in line with their documents.
 *
 *   all three in, none rejected → issue a ticket, or re-validate a void one
 *   any rejected                → void the ticket (unless it was already used)
 *
 * Safe to call after every upload or review. The UNIQUE index on
 * tickets.user_id is the real guard against a double issue.
 *
 * @return array{state:string, ticket_no:?string}
 *         state: none | issued | restored | voided | full | unchanged
 */
function sync_ticket(int $userId): array
{
    $progress = document_progress($userId);
    $existing = db_one('SELECT * FROM tickets WHERE user_id = ?', [$userId]);

    if ($progress['blocked']) {
        if ($existing && $existing['status'] === 'valid') {
            db_run('UPDATE tickets SET status = "void", voided_at = NOW() WHERE id = ?', [$existing['id']]);
            audit('ticket.void', 'ticket', $existing['ticket_no'], ['reason' => 'document rejected']);
            forget_registration_cache();
            return ['state' => 'voided', 'ticket_no' => $existing['ticket_no']];
        }
        return ['state' => 'unchanged', 'ticket_no' => $existing['ticket_no'] ?? null];
    }

    if ($progress['missing'] !== []) {
        return ['state' => 'none', 'ticket_no' => $existing['ticket_no'] ?? null];
    }

    if ($existing) {
        if ($existing['status'] === 'void') {
            db_run('UPDATE tickets SET status = "valid", voided_at = NULL WHERE id = ?', [$existing['id']]);
            audit('ticket.restore', 'ticket', $existing['ticket_no']);
            forget_registration_cache();
            return ['state' => 'restored', 'ticket_no' => $existing['ticket_no']];
        }
        return ['state' => 'unchanged', 'ticket_no' => $existing['ticket_no']];
    }

    if (slots_remaining() <= 0) {
        return ['state' => 'full', 'ticket_no' => null];
    }

    try {
        $ticketNo = db_transaction(static function () use ($userId) {
            $no = TICKET_PREFIX . str_pad((string) db_next_sequence('ticket'), 6, '0', STR_PAD_LEFT);

            db_insert(
                'INSERT INTO tickets (ticket_no, user_id, verify_hash, status, issued_at) VALUES (?, ?, ?, "valid", NOW())',
                [$no, $userId, ticket_verify_hash($no, $userId)]
            );

            return $no;
        });
    } catch (PDOException $ex) {
        /* Two requests raced; the unique index let exactly one through. */
        if ($ex->getCode() === '23000') {
            forget_registration_cache();
            return ['state' => 'unchanged', 'ticket_no' => db_value('SELECT ticket_no FROM tickets WHERE user_id = ?', [$userId])];
        }
        throw $ex;
    }

    forget_registration_cache();
    audit('ticket.issue', 'ticket', $ticketNo, ['user_id' => $userId]);

    return ['state' => 'issued', 'ticket_no' => $ticketNo];
}

/**
 * Save one of the signed-in student's three documents.
 *
 * A student can replace a file until staff approve it, or until the ticket has
 * been used at the gate. Uploading the last missing file issues the ticket.
 *
 * @return array{ok:bool, message:string, ticket:string}  ticket = sync_ticket() state
 */
function submit_document(string $type, array $file): array
{
    $fail = static fn(string $msg) => ['ok' => false, 'message' => $msg, 'ticket' => 'unchanged'];

    if (!is_logged_in() || is_admin()) {
        return $fail('Sign in with a student account first.');
    }
    if (!is_document_type($type)) {
        return $fail('That is not one of the required documents.');
    }

    $userId = (int) current_user()['id'];
    $label  = DOCUMENT_TYPES[$type]['label'];

    if ($wait = rate_limit('upload', 12, 600)) {
        return $fail('Too many uploads in a short time. Try again in ' . $wait . ' seconds.');
    }

    $current = user_documents($userId)[$type] ?? null;

    if ($current && $current['status'] === 'approved') {
        return $fail('Your ' . $label . ' has already been approved, so it is locked.');
    }
    if ((get_ticket()['status'] ?? '') === 'used') {
        return $fail('Your ticket has already been used at the gate.');
    }

    $stored = store_document_upload($file, $label);
    if (!$stored['ok']) {
        return $fail($stored['message']);
    }

    $previous = db_value('SELECT stored_name FROM documents WHERE user_id = ? AND doc_type = ?', [$userId, $type]);

    db_run(
        'INSERT INTO documents (user_id, doc_type, original_name, stored_name, size_bytes, status, uploaded_ip, uploaded_at)
         VALUES (?, ?, ?, ?, ?, "submitted", ?, NOW())
         ON DUPLICATE KEY UPDATE
            original_name = VALUES(original_name), stored_name = VALUES(stored_name),
            size_bytes = VALUES(size_bytes), status = "submitted",
            reviewed_by = NULL, reviewed_at = NULL, review_note = NULL,
            uploaded_ip = VALUES(uploaded_ip), uploaded_at = NOW()',
        [$userId, $type, $stored['original'], $stored['stored'], $stored['size'], substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]
    );

    /* The replaced file is of no use to anyone now. */
    if ($previous && ($old = document_path((string) $previous))) {
        @unlink($old);
    }

    forget_registration_cache();
    audit('document.upload', 'document', $type, ['user_id' => $userId, 'file' => $stored['original']]);

    $sync = sync_ticket($userId);

    $message = match ($sync['state']) {
        'issued'   => 'All three documents are in. Your ticket number is ' . $sync['ticket_no'] . '.',
        'restored' => 'Thanks — your ticket ' . $sync['ticket_no'] . ' is valid again.',
        'full'     => 'Your documents are saved, but every seat has been taken.',
        default    => $label . ' uploaded.',
    };

    return ['ok' => true, 'message' => $message, 'ticket' => $sync['state']];
}

/**
 * Staff decision on one document.
 *
 * @return array{ok:bool, message:string}
 */
function review_document(int $documentId, string $decision, string $note = ''): array
{
    if (!is_admin()) {
        return ['ok' => false, 'message' => 'Only SSC staff can review documents.'];
    }
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return ['ok' => false, 'message' => 'Unknown decision.'];
    }

    $note = trim($note);
    if ($decision === 'rejected' && mb_strlen($note) < 5) {
        return ['ok' => false, 'message' => 'Tell the student what to fix (at least a few words).'];
    }

    $doc = db_one(
        'SELECT d.*, u.name FROM documents d JOIN users u ON u.id = d.user_id WHERE d.id = ?',
        [$documentId]
    );
    if (!$doc) {
        return ['ok' => false, 'message' => 'That document no longer exists.'];
    }

    db_run(
        'UPDATE documents SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?',
        [$decision, current_user()['id'], $note === '' ? null : mb_substr($note, 0, 250), $documentId]
    );

    forget_registration_cache();
    audit('document.' . ($decision === 'approved' ? 'approve' : 'reject'), 'document', (string) $documentId, [
        'student' => $doc['name'], 'type' => $doc['doc_type'], 'note' => $note,
    ]);

    $sync = sync_ticket((int) $doc['user_id']);

    $extra = $sync['state'] === 'voided' ? ' Their ticket was voided until they upload a corrected file.' : '';

    return [
        'ok'      => true,
        'message' => DOCUMENT_TYPES[$doc['doc_type']]['short'] . ' of ' . $doc['name']
                   . ($decision === 'approved' ? ' approved.' : ' rejected.') . $extra,
    ];
}

/** Documents waiting for a staff decision, oldest first. */
function pending_documents(): array
{
    return db_all(
        'SELECT d.*, u.name AS student_name, u.student_no, u.program, u.year_level
         FROM documents d
         JOIN users u ON u.id = d.user_id
         WHERE d.status = "submitted"
         ORDER BY d.uploaded_at ASC, d.id ASC'
    );
}

/* ==========================================================================
 | 6. Registration tracking timeline
 * ========================================================================*/

function tracking_timeline(): array
{
    $user     = current_user() ?? [];
    $progress = document_progress();
    $docs     = user_documents();
    $ticket   = get_ticket();
    $live     = $ticket && $ticket['status'] !== 'void';

    $docDesc = $progress['missing']
        ? 'Still needed: ' . implode(', ', array_map(static fn($k) => DOCUMENT_TYPES[$k]['short'], $progress['missing'])) . '.'
        : ($progress['blocked']
            ? 'A document was returned — replace it to restore your ticket.'
            : 'Waiver, COR and school ID are all uploaded.');

    $stages = [
        [
            'key'   => 'account',
            'label' => 'Account Created',
            'icon'  => 'fa-user-check',
            'desc'  => 'Registered with your BatStateU GSuite address.',
            'stamp' => db_value('SELECT created_at FROM users WHERE id = ?', [$user['id'] ?? 0]),
            'done'  => is_logged_in(),
        ],
        [
            'key'   => 'email',
            'label' => 'Email Confirmed',
            'icon'  => 'fa-envelope-circle-check',
            'desc'  => 'You opened the confirmation link we sent to your GSuite inbox.',
            'stamp' => db_value('SELECT email_verified_at FROM users WHERE id = ?', [$user['id'] ?? 0]),
            'done'  => is_email_verified(),
        ],
        [
            'key'   => 'documents',
            'label' => 'Documents Uploaded',
            'icon'  => 'fa-file-pdf',
            'desc'  => $docDesc,
            'stamp' => $docs ? max(array_column($docs, 'uploaded_at')) : null,
            'done'  => $progress['complete'],
        ],
        [
            'key'   => 'ticket',
            'label' => 'Ticket Number Issued',
            'icon'  => 'fa-ticket',
            'desc'  => $ticket ? 'Your ticket number is ' . $ticket['ticket_no'] . '.' : 'Issued automatically once all three documents are in.',
            'stamp' => $ticket['issued_at'] ?? null,
            'done'  => $live,
        ],
        [
            'key'   => 'gate',
            'label' => 'Scanned at the Gate',
            'icon'  => 'fa-qrcode',
            'desc'  => 'Show your QR code at the ' . EVENT_VENUE . ' entrance.',
            'stamp' => $ticket['checked_at'] ?? null,
            'done'  => (bool) ($ticket['checked_in'] ?? false),
        ],
    ];

    $currentAssigned = false;
    foreach ($stages as $i => $stage) {
        if ($stage['done']) {
            $stages[$i]['state'] = 'done';
        } elseif (!$currentAssigned) {
            $stages[$i]['state'] = 'current';
            $currentAssigned = true;
        } else {
            $stages[$i]['state'] = 'upcoming';
        }
    }

    return $stages;
}

/** Human label + colour for the student's overall standing. */
function registration_stage_label(): array
{
    $ticket   = get_ticket();
    $progress = document_progress();

    if ($ticket && $ticket['checked_in']) {
        return ['Attended', 'info', 'fa-user-check'];
    }
    if ($ticket && $ticket['status'] === 'valid') {
        return ['Ticket Ready', 'success', 'fa-circle-check'];
    }
    if ($progress['blocked']) {
        return ['Action Needed', 'danger', 'fa-triangle-exclamation'];
    }
    if ($progress['uploaded'] > 0) {
        return [$progress['uploaded'] . ' of ' . $progress['total'] . ' Uploaded', 'warning', 'fa-file-arrow-up'];
    }

    return ['Documents Needed', 'warning', 'fa-hourglass-half'];
}


/* ==========================================================================
 | 8. Presentation helpers
 * ========================================================================*/

/** Bootstrap-ish badge for a status string. */
function status_badge(string $status): string
{
    $map = [
        'approved'  => ['success', 'Approved'],
        'valid'     => ['success', 'Valid'],
        'issued'    => ['success', 'Issued'],
        'checked'   => ['info',    'Checked In'],
        'used'      => ['info',    'Used'],
        'submitted' => ['info',    'For Review'],
        'pending'   => ['warning', 'Pending'],
        'missing'   => ['warning', 'Not Uploaded'],
        'rejected'  => ['danger',  'Returned'],
        'void'      => ['danger',  'Void'],
    ];

    [$tone, $label] = $map[strtolower($status)] ?? ['muted', ucfirst(str_replace('_', ' ', $status))];
    return '<span class="badge-soft badge-' . $tone . '">' . e($label) . '</span>';
}

/**
 * QR payload a gate scanner reads off a ticket.
 *
 * Carries the ticket number plus a short signature, so the scanner can reject
 * a fabricated code without a round trip to the server.
 */
function ticket_qr_payload(array $ticket): string
{
    $no   = (string) ($ticket['ticket_no'] ?? '');
    $sig  = substr((string) ($ticket['verify_hash'] ?? ''), 0, 12);

    return $sig === '' ? $no : $no . '|' . $sig;
}

/** Verify a payload scanned at the gate. */
function ticket_from_payload(string $payload): ?array
{
    [$ticketNo] = array_pad(explode('|', trim($payload), 2), 2, '');

    $row = db_one(
        'SELECT t.*, u.name, u.email, u.student_no, u.program, u.year_level,
                c.scanned_at, c.gate
         FROM tickets t
         JOIN users u ON u.id = t.user_id
         LEFT JOIN checkins c ON c.ticket_id = t.id
         WHERE t.ticket_no = ? LIMIT 1',
        [$ticketNo]
    );

    if ($row === null) {
        return null;
    }

    $ticket = ticket_shape($row);

    /* A payload that carries a signature must carry the right one. */
    $parts = explode('|', trim($payload), 2);
    if (count($parts) === 2 && !hash_equals(substr($ticket['verify_hash'], 0, 12), $parts[1])) {
        return null;
    }

    return $ticket;
}

/* NOTE: qr_svg() / qr_data_uri() live in includes/qr.php — they emit real,
   scannable QR codes (ISO/IEC 18004), not placeholders. */

/** Small deterministic pseudo-random helper for the dummy analytics. */
function seeded(int $seed, int $min, int $max): int
{
    mt_srand($seed);
    $v = mt_rand($min, $max);
    mt_srand();
    return $v;
}

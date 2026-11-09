<?php

namespace App\Services;

use App\Mail\ConfirmEmail;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/** Sign-up, Google sign-up and the emailed confirmation link. */
class AccountService
{
    public function __construct(private Registrar $registrar, private TicketService $tickets)
    {
    }

    /* --------------------------------------------------------- email domain */

    public function domainAllowed(string $email): bool
    {
        $at = strrpos($email, '@');

        return $at !== false
            && in_array(strtolower(substr($email, $at + 1)), array_map('strtolower', config('ereserve.allowed_email_domains')), true);
    }

    /** A university address, or one of the exact extra addresses the SSC listed for staff. */
    public function staffEmailAllowed(string $email): bool
    {
        return $this->domainAllowed($email)
            || in_array(strtolower(trim($email)), (array) config('ereserve.staff_extra_emails'), true);
    }

    public function domainsText(): string
    {
        $list = array_map(static fn($d) => '@' . $d, config('ereserve.allowed_email_domains'));
        if (count($list) === 1) {
            return $list[0];
        }
        $last = array_pop($list);

        return implode(', ', $list) . ' or ' . $last;
    }

    /** A near-miss domain is almost always a typo — point at the intended one. */
    public function suggestDomain(string $email): ?string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }

        $typed = strtolower(substr($email, $at + 1));
        $best  = null;
        $score = PHP_INT_MAX;

        foreach (config('ereserve.allowed_email_domains') as $d) {
            $dist = levenshtein($typed, strtolower($d));
            if ($dist < $score) {
                $score = $dist;
                $best  = $d;
            }
        }

        return ($best !== null && $score > 0 && $score <= 3) ? $best : null;
    }

    public function domainRejection(string $email): string
    {
        if ($s = $this->suggestDomain($email)) {
            return 'Check the spelling — did you mean ' . substr($email, 0, (int) strrpos($email, '@')) . '@' . $s . '?';
        }

        return 'Use your university address (' . $this->domainsText() . ').';
    }

    /* ------------------------------------------------------------- password */

    public function passwordProblem(string $password): string
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

    /* --------------------------------------------------------- registration */

    /**
     * Validate the profile fields shared by both sign-up paths.
     *
     * @return array{0:?array,1:?array} [error result, cleaned values]
     */
    private function profile(array $in, string $name, string $studentNo): array
    {
        $fail = static fn(string $m, ?string $f = null) => [['ok' => false, 'message' => $m, 'field' => $f, 'user' => null], null];

        $college = trim((string) ($in['college'] ?? ''));
        $program = trim((string) ($in['program'] ?? ''));
        $year    = trim((string) ($in['year_level'] ?? ''));
        $mobile  = trim((string) ($in['mobile'] ?? ''));

        if (mb_strlen($name) < 3) {
            return $fail('Enter your full name as it appears on your student record.', 'name');
        }
        if (mb_strlen($name) > 160) {
            return $fail('That name is too long.', 'name');
        }
        if (!$this->registrar->validCode($studentNo)) {
            return $fail('Your SR-code must look like 22-10234.', 'student_no');
        }
        if (!isset($this->registrar->colleges()[$college])) {
            return $fail('Choose your college from the list.', 'college');
        }
        if (!$this->registrar->programInCollege($program, $college)) {
            return $fail('Choose a programme that belongs to your college.', 'program');
        }
        if (!in_array($year, config('ereserve.year_levels'), true)) {
            return $fail('Choose your year level.', 'year_level');
        }
        if ($mobile !== '' && !preg_match('/^09\d{9}$/', preg_replace('/\D/', '', $mobile) ?? '')) {
            return $fail('Enter your mobile number as 09XXXXXXXXX.', 'mobile');
        }
        if ($problem = $this->registrar->enrollmentProblem($studentNo, $college)) {
            return $fail($problem, 'student_no');
        }

        return [null, [
            'college' => $college, 'program' => $program, 'year' => $year,
            'mobile'  => $mobile === '' ? null : preg_replace('/\D/', '', $mobile),
        ]];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', $name) ?: [$name];

        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr((string) end($parts), 0, 1));
    }

    /**
     * Faculty, instructor, staff or administration: name, employee ID, email and a password.
     * No documents. The ticket is issued the moment the email is confirmed.
     *
     * @return array{ok:bool,message:string,field:?string,user:?User,mail_ok?:bool}
     */
    public function registerEmployee(array $in): array
    {
        $fail = static fn(string $m, ?string $f = null) => ['ok' => false, 'message' => $m, 'field' => $f, 'user' => null];

        $type      = trim((string) ($in['member_type'] ?? ''));
        $name      = trim(preg_replace('/\s+/', ' ', (string) ($in['name'] ?? '')) ?? '');
        $email     = strtolower(trim((string) ($in['email'] ?? '')));
        $employeeNo = strtoupper(trim((string) ($in['employee_no'] ?? '')));
        $mobile    = trim((string) ($in['mobile'] ?? ''));
        $password  = (string) ($in['password'] ?? '');
        $confirm   = (string) ($in['password_confirm'] ?? '');

        if (!isset(config('ereserve.employee_types')[$type])) {
            return $fail('Choose whether you are faculty, an instructor, staff or administration.', 'member_type');
        }
        if (mb_strlen($name) < 3 || mb_strlen($name) > 160) {
            return $fail('Enter your full name.', 'name');
        }
        if (!$this->employeeIdValid($employeeNo)) {
            return $fail('Enter your employee ID (letters, numbers, dash or slash, 3 to 20 characters).', 'employee_no');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $fail('That does not look like a valid email address.', 'email');
        }
        if (!$this->domainAllowed($email)) {
            return $fail($this->domainRejection($email), 'email');
        }
        if ($mobile !== '' && !preg_match('/^09\d{9}$/', preg_replace('/\D/', '', $mobile) ?? '')) {
            return $fail('Enter your mobile number as 09XXXXXXXXX.', 'mobile');
        }
        if ($problem = $this->passwordProblem($password)) {
            return $fail($problem, 'password');
        }
        if (!hash_equals($password, $confirm)) {
            return $fail('The two passwords do not match.', 'password_confirm');
        }

        $key = 'register:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $fail('Too many sign-up attempts. Please wait ' . RateLimiter::availableIn($key) . ' seconds.');
        }
        RateLimiter::hit($key, 900);

        if (User::where('email', $email)->exists()) {
            return $fail('An account already exists for that email. Sign in instead.', 'email');
        }
        if (User::where('student_no', $employeeNo)->exists()) {
            return $fail('That employee ID is already registered.', 'employee_no');
        }

        try {
            $user = new User();
            $user->forceFill([
                'email' => $email, 'password' => $password, 'role' => 'student', 'member_type' => $type, 'name' => $name,
                'initials' => $this->initials($name), 'student_no' => $employeeNo,
                'program' => config('ereserve.employee_types')[$type], 'year_level' => null, 'college_code' => null,
                'mobile' => $mobile === '' ? null : preg_replace('/\D/', '', $mobile), 'status' => 'active',
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return $fail('An account with that email or employee ID already exists.', 'email');
        }

        $this->tickets->forget();
        Auth::login($user);
        request()->session()->regenerate();
        Audit::log('user.register', 'user', (string) $user->id, ['type' => $type]);

        $mail = $this->sendVerification($user);

        return ['ok' => true, 'message' => 'Account created.', 'field' => null, 'user' => $user, 'mail_ok' => $mail['ok']];
    }

    public function employeeIdValid(string $id): bool
    {
        return (bool) preg_match((string) config('ereserve.employee_id_pattern'), strtoupper(trim($id)));
    }

    /** @return array{ok:bool,message:string,field:?string,user:?User} */
    public function registerStudent(array $in): array
    {
        $fail = static fn(string $m, ?string $f = null) => ['ok' => false, 'message' => $m, 'field' => $f, 'user' => null];

        $name      = trim((string) ($in['name'] ?? ''));
        $email     = strtolower(trim((string) ($in['email'] ?? '')));
        $studentNo = strtoupper(trim((string) ($in['student_no'] ?? '')));
        $password  = (string) ($in['password'] ?? '');
        $confirm   = (string) ($in['password_confirm'] ?? '');

        if (mb_strlen($name) < 3) {
            return $fail('Enter your full name as it appears on your student record.', 'name');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $fail('That does not look like a valid email address.', 'email');
        }
        if (!$this->domainAllowed($email)) {
            return $fail($this->domainRejection($email), 'email');
        }

        [$err, $p] = $this->profile($in, $name, $studentNo);
        if ($err) {
            return $err;
        }

        if ($problem = $this->passwordProblem($password)) {
            return $fail($problem, 'password');
        }
        if (!hash_equals($password, $confirm)) {
            return $fail('The two passwords do not match.', 'password_confirm');
        }

        $key = 'register:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $fail('Too many sign-up attempts. Please wait ' . RateLimiter::availableIn($key) . ' seconds.');
        }
        RateLimiter::hit($key, 900);

        if (User::where('email', $email)->exists()) {
            return $fail('An account already exists for that email. Sign in instead.', 'email');
        }
        if (User::where('student_no', $studentNo)->exists()) {
            return $fail('That SR-code is already registered.', 'student_no');
        }

        try {
            $user = new User();
            $user->forceFill([
                'email' => $email, 'password' => $password, 'role' => 'student', 'name' => $name,
                'initials' => $this->initials($name), 'student_no' => $studentNo,
                'college_code' => $p['college'], 'program' => $p['program'], 'year_level' => $p['year'],
                'mobile' => $p['mobile'], 'status' => 'active',
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return $fail('An account with that email or SR-code already exists.', 'email');
        }

        $this->tickets->forget();
        Auth::login($user);
        request()->session()->regenerate();

        Audit::log('user.register', 'user', (string) $user->id, ['student_no' => $studentNo]);
        $sent = $this->sendVerification($user);

        return ['ok' => true, 'message' => 'Account created.', 'field' => null, 'user' => $user, 'mail_ok' => $sent['ok']];
    }

    /**
     * Finish a sign-up that began with "Continue with Google". The address comes
     * from the session (Google proved it), never from the form.
     */
    public function registerGoogle(array $in, ?array $pending): array
    {
        $fail = static fn(string $m, ?string $f = null) => ['ok' => false, 'message' => $m, 'field' => $f, 'user' => null];

        if (!$pending || empty($pending['email'])) {
            return $fail('Your Google sign-in expired. Please start again.');
        }

        $email     = strtolower((string) $pending['email']);
        $name      = trim((string) ($in['name'] ?? ''));
        $studentNo = strtoupper(trim((string) ($in['student_no'] ?? '')));

        if (!$this->domainAllowed($email)) {
            return $fail($this->domainRejection($email));
        }

        [$err, $p] = $this->profile($in, $name, $studentNo);
        if ($err) {
            return $err;
        }

        if (User::where('email', $email)->exists()) {
            return $fail('An account already exists for that email. Sign in instead.', 'email');
        }
        if (User::where('student_no', $studentNo)->exists()) {
            return $fail('That SR-code is already registered.', 'student_no');
        }

        try {
            $user = new User();
            $user->forceFill([
                'email' => $email, 'password' => null, 'role' => 'student', 'name' => $name,
                'initials' => $this->initials($name), 'student_no' => $studentNo,
                'college_code' => $p['college'], 'program' => $p['program'], 'year_level' => $p['year'],
                'mobile' => $p['mobile'], 'status' => 'active', 'email_verified_at' => now(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return $fail('An account with that email or SR-code already exists.', 'student_no');
        }

        $this->tickets->forget();
        request()->session()->forget('google_pending');
        Auth::login($user);
        request()->session()->regenerate();

        Audit::log('user.register.google', 'user', (string) $user->id, ['student_no' => $studentNo]);

        return ['ok' => true, 'message' => 'Account created.', 'field' => null, 'user' => $user];
    }

    /* ---------------------------------------------------- email confirmation */

    /**
     * Issue a fresh confirmation link and email it. Only the SHA-256 of the
     * token is stored, so a leaked database cannot confirm anybody's account.
     *
     * @return array{ok:bool,code:string,message:string,link:?string}
     */
    public function sendVerification(User $user): array
    {
        if ($user->email_verified_at !== null) {
            return ['ok' => false, 'code' => 'already', 'message' => 'That address is already confirmed.', 'link' => null];
        }

        if ($user->verify_sent_at !== null && $user->verify_sent_at->gt(now()->subSeconds(60))) {
            $wait = 60 - (int) $user->verify_sent_at->diffInSeconds(now());
            return ['ok' => false, 'code' => 'throttled', 'link' => null,
                    'message' => 'We already sent one a moment ago. Check your inbox, or ask again in ' . max(1, $wait) . ' seconds.'];
        }

        $token = bin2hex(random_bytes(32));
        $hours = (int) config('ereserve.verify_token_hours');

        $user->forceFill([
            'verify_token_hash' => hash('sha256', $token),
            'verify_expires_at' => now()->addHours($hours),
            'verify_sent_at'    => now(),
        ])->save();

        $link = route('verify', ['token' => $token]);

        try {
            // Queued so a slow SMTP server never holds up a sign-up (runs inline when QUEUE_CONNECTION=sync).
            Mail::to($user->email, $user->name)->queue(new ConfirmEmail($user->name, $link, $hours));
            $ok = true;
            $message = 'Email sent.';
        } catch (Throwable $e) {
            report($e);
            $ok = false;
            $message = 'The confirmation email could not be sent right now. Please try again in a minute.';
            // Nothing went out, so do not make the student wait out the 60-second resend lock.
            $user->forceFill(['verify_sent_at' => null])->save();
        }

        Audit::log('email.verification_sent', 'user', (string) $user->id, ['transport' => config('mail.default')]);

        return ['ok' => $ok, 'code' => $ok ? 'sent' : 'mail_failed', 'message' => $message, 'link' => $link];
    }

    /** @return array{ok:bool,message:string,user:?User} */
    public function verifyToken(string $token): array
    {
        $token = trim($token);

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['ok' => false, 'message' => 'That confirmation link is not valid.', 'user' => null];
        }

        $key = 'verify:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 12)) {
            return ['ok' => false, 'message' => 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.', 'user' => null];
        }
        RateLimiter::hit($key, 600);

        $user = User::where('verify_token_hash', hash('sha256', $token))->first();

        if (!$user) {
            return ['ok' => false, 'message' => 'That link has already been used, or it is not valid.', 'user' => null];
        }
        if ($user->verify_expires_at !== null && $user->verify_expires_at->isPast()) {
            return ['ok' => false, 'message' => 'That link has expired. Sign in and ask for a new one.', 'user' => null];
        }

        // Clearing the hash makes the link single-use.
        $user->forceFill(['email_verified_at' => now(), 'verify_token_hash' => null, 'verify_expires_at' => null])->save();

        $this->tickets->forget();
        Auth::login($user);
        request()->session()->regenerate();

        Audit::log('email.verified', 'user', (string) $user->id);

        // Employees have no documents: a confirmed email is their approval, so the ticket is issued now.
        if ($user->isEmployee()) {
            $this->tickets->sync((int) $user->id);
        }

        return ['ok' => true, 'message' => 'Email confirmed.', 'user' => $user];
    }
}

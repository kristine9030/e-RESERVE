<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function __construct(private AccountService $accounts)
    {
    }

    public function show(Request $request)
    {
        return view('auth.login', ['next' => (string) $request->query('next', '')]);
    }

    /** The SSC staff sign-in page (/ssc): staff accounts only, no sign-up, no Google. */
    public function showStaff()
    {
        return view('auth.staff-login');
    }

    public function storeStaff(Request $request)
    {
        return $this->store($request, staffOnly: true);
    }

    public function store(Request $request, bool $staffOnly = false)
    {
        $email = strtolower(trim((string) $request->input('email')));
        $pass  = (string) $request->input('password', '');
        $next  = (string) $request->input('next', '');

        // A one-letter slip in the domain (g.bastate-u… for g.batstate-u…) is fixed for them;
        // the password is still required, so this opens nothing up.
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !$this->accounts->loginEmailAllowed($email)
            && ($fixed = $this->accounts->suggestDomain($email))) {
            $email = substr($email, 0, (int) strrpos($email, '@')) . '@' . strtolower($fixed);
            $request->merge(['email' => $email]);
        }

        $error = $this->attempt($request, $email, $pass, $staffOnly);

        if ($error !== null) {
            return back()->withInput($request->only('email', 'next', 'remember'))->with('login_error', $error);
        }

        $user = Auth::user();

        return redirect()
            ->to($user->isStaff() || $user->isSuperAdmin() ? $user->homeRoute() : ($next === 'documents' ? route('documents.index') : route('dashboard')))
            ->with('flash', flash(
                'success',
                'Welcome, ' . e(explode(' ', $user->name)[0]) . '!',
                'You are signed in to ' . app_name() . ' as <strong>' . e($user->email) . '</strong>.'
            ));
    }

    /** @return string|null an error message, or null when signed in */
    private function attempt(Request $request, string $email, string $password, bool $staffOnly = false): ?string
    {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'That does not look like a valid email address.';
        }
        // The staff door also takes the super admin, whose email can be any address. For everyone else the
        // wording stays generic there, so the form never reveals whether an outside address is a super admin.
        $superAdmin = $staffOnly && User::where('email', $email)->where('role', 'superadmin')->exists();
        if (!$superAdmin && !$this->accounts->loginEmailAllowed($email)) {
            return $staffOnly ? 'Incorrect email or password.' : $this->accounts->domainRejection($email);
        }

        $key = 'login:' . $email . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return 'Too many sign-in attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.';
        }
        if ($password === '') {
            return 'Enter your password.';
        }

        $user = User::where('email', $email)->first();

        // One wording for every failure, and a hash check even when the account
        // does not exist, so this form cannot be used to discover accounts.
        $generic = 'Incorrect email or password.';

        if ($user === null) {
            Hash::check($password, '$2y$12$Dp.zz6rpUONA4CJ4up5PguhTU5f0j2BMzx54SUWCQla.XHz2iu10C');
            RateLimiter::hit($key, 300);
            return $generic;
        }
        if (empty($user->password) || !Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 300);
            return $generic;
        }
        if ($user->isSuperAdmin() && !$staffOnly) {
            RateLimiter::hit($key, 300);   // never through the student sign-in
            return $generic;
        }
        if (!$user->isActive()) {
            return 'That account has been disabled. Please contact the SSC.';
        }
        // Password was right, but this door is for staff. Do not sign a student in here.
        if ($staffOnly && !$user->canUseSsc() && !$user->isScanner() && !$user->isSuperAdmin()) {
            return 'This page is for SSC staff. Students sign in from the main sign-in page.';
        }

        RateLimiter::clear($key);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();         // defeats session fixation
        // A student account with SSC access enters the SSC panel from /ssc, the student side from /login.
        session(['ssc_mode' => $staffOnly && $user->isDualAccount()]);
        $user->forceFill(['last_login_at' => now()])->save();
        session(['logged_in_at' => now()->toDateTimeString()]);

        Audit::log('login', 'user', (string) $user->id);

        return null;
    }

    public function destroy(Request $request)
    {
        if ($request->user()) {
            Audit::log('logout', 'user', (string) $request->user()->id);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('flash', flash(
            'success', 'Signed Out', 'You have been signed out of ' . app_name() . '. See you at SIGLA 2026!'
        ));
    }
}

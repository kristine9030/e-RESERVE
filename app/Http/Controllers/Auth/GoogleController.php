<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/** "Continue with Google". Only university addresses are accepted. */
class GoogleController extends Controller
{
    public function __construct(private AccountService $accounts)
    {
    }

    private function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(Request $request)
    {
        if (!$this->enabled()) {
            return redirect()->route('login')->with('flash', flash(
                'info', 'Google sign-in is not switched on yet',
                'The SSC still needs to add its Google client ID in <code>.env</code>. For now, sign in with your email and password.'
            ));
        }

        $key = 'google:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return redirect()->route('login')->with('flash', flash('warning', 'Slow down a little', 'Try again in ' . RateLimiter::availableIn($key) . ' seconds.'));
        }
        RateLimiter::hit($key, 300);

        // `hd` is only a hint; the domain is enforced again in callback().
        return Socialite::driver('google')
            ->with(['hd' => config('ereserve.allowed_email_domains')[0], 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('login')->with('flash', flash('info', 'Google sign-in cancelled', 'No problem — you can try again or use your email and password.'));
        }

        try {
            $g = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->with('flash', flash('error', 'Could not sign in with Google', 'That Google sign-in could not be verified. Please try again.'));
        }

        $email = strtolower(trim((string) $g->getEmail()));
        $raw   = $g->user ?? [];

        if (($raw['email_verified'] ?? false) !== true && ($raw['email_verified'] ?? '') !== 'true') {
            return redirect()->route('login')->with('flash', flash('error', 'Could not sign in with Google', 'That Google address is not verified.'));
        }
        if ($email === '' || !$this->accounts->domainAllowed($email)) {
            return redirect()->route('login')->with('flash', flash('error', 'Could not sign in with Google', e($this->accounts->domainRejection($email))));
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            // First visit: Google proved the address; we still need the SR-code and college.
            session(['google_pending' => ['email' => $email, 'name' => trim((string) $g->getName())]]);

            return redirect()->route('register')->with('flash', flash('success', 'Google account confirmed', 'Just a few details and your account is ready.'));
        }
        if ($user->role !== 'student') {
            return redirect()->route('login')->with('flash', flash('error', 'Could not sign in', 'Staff accounts sign in with their password.'));
        }
        if (!$user->isActive()) {
            return redirect()->route('login')->with('flash', flash('error', 'Could not sign in', 'That account has been disabled. Please contact the SSC.'));
        }

        // Google already proved the mailbox, so the confirmation email is moot.
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now(), 'verify_token_hash' => null, 'verify_expires_at' => null]);
        }
        $user->forceFill(['last_login_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('google_pending');
        session(['logged_in_at' => now()->toDateTimeString()]);

        Audit::log('login.google', 'user', (string) $user->id);

        return redirect()->route('dashboard')->with('flash', flash(
            'success', 'Welcome, ' . e(explode(' ', $user->name)[0]) . '!',
            'You are signed in with Google as <strong>' . e($user->email) . '</strong>.'
        ));
    }
}

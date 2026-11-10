<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** The super admin's own sign-in door. Only a super admin account gets through it. */
class AuthController extends Controller
{
    public function show()
    {
        return view('superadmin.login');
    }

    public function store(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email')));
        $key   = 'superadmin-login:' . $email . '|' . $request->ip();
        $bad   = fn (string $m) => back()->withInput($request->only('email'))->with('login_error', $m);

        if (RateLimiter::tooManyAttempts($key, 6)) {
            return $bad('Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.');
        }

        $user = User::where('email', $email)->first();
        // One wording for every failure, and a hash check even when there is no such account.
        $ok = Hash::check((string) $request->input('password'), $user?->password ?: '$2y$12$Dp.zz6rpUONA4CJ4up5PguhTU5f0j2BMzx54SUWCQla.XHz2iu10C')
            && $user && $user->isSuperAdmin() && $user->isActive();

        if (!$ok) {
            RateLimiter::hit($key, 300);
            Audit::log('login.failed', 'user', null, ['email' => $email, 'door' => 'superadmin']);

            return $bad('Incorrect email or password.');
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('login', 'user', (string) $user->id);

        return redirect()->route('superadmin.dashboard');
    }
}

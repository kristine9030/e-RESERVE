<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** A staff member can disable an account; it stops working on the very next request. */
class EnsureActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && !$user->isActive()) {
            $staff = $user->isStaff();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($staff ? 'ssc.login' : 'login')->with('login_error', 'That account has been disabled. Please contact the SSC.');
        }

        return $next($request);
    }
}

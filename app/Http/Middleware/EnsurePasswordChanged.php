<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * An account the SSC created (or reset) starts with a password someone else chose.
 * Until its owner picks their own, the only pages they can reach are the
 * change-password form and sign-out.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->must_change_password && !$request->routeIs('account.password', 'account.password.update', 'logout')) {
            if ($request->expectsJson()) {
                abort(403, 'Choose your own password first.');
            }

            return redirect()->route('account.password')->with('flash', [
                'type'  => 'info',
                'title' => 'Choose your own password',
                'text'  => 'The SSC gave you a temporary password. Set one only you know, then you can continue.',
            ]);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Gate check-in: SSC admins and scanner-only accounts. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()?->isStaff()) {
            if ($request->expectsJson()) {
                abort(403, 'Staff only.');
            }

            return redirect()->route('ssc.login')
                ->with('flash', ['type' => 'warning', 'title' => 'Restricted Area',
                                 'text' => 'Sign in with a staff account to open the gate check-in.']);
        }

        return $next($request);
    }
}

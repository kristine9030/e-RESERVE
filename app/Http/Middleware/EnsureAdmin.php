<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Staff-only pages. */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()?->isAdmin()) {
            return redirect()->route('ssc.login')
                ->with('flash', ['type' => 'warning', 'title' => 'Restricted Area',
                                 'text' => 'Sign in with a staff account to open the administrative panel.']);
        }

        return $next($request);
    }
}

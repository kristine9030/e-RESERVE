<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Student pages: staff are sent to their own shell. */
class EnsureStudent
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->isStaff()) {
            return redirect($request->user()->homeRoute());
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\StudentAccess;
use Closure;
use Illuminate\Http\Request;

/**
 * The soft-launch gate. While student access is closed, the public sign-in and
 * registration doors are shut and visitors are sent back to the landing page.
 * The SSC staff door (/ssc) is never gated, so testing can continue.
 */
class EnsureStudentAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (StudentAccess::open()) {
            return $next($request);
        }

        return redirect()->route('home')->with('flash', flash(
            'info',
            'Registration opens ' . StudentAccess::opensAtText('M j, Y'),
            'Sign-in and account creation open on ' . StudentAccess::opensAtText() . '. See you then!'
        ));
    }
}

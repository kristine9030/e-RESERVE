<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Browsing is fine unverified; committing to something (uploading) is not. */
class EnsureVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && !$user->hasVerifiedEmail()) {
            return redirect()->route('verify')->with('flash', [
                'type'  => 'warning',
                'title' => 'Confirm your email first',
                'text'  => 'We sent a confirmation link to <strong>' . e($user->email) . '</strong>. Open it so we know the account is really yours.',
            ]);
        }

        return $next($request);
    }
}

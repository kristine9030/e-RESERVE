<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** The system-owner console. Nobody else, not even SSC admins, gets in. */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()?->isSuperAdmin()) {
            // Do not reveal that this area exists to anyone who is not the super admin.
            abort(404);
        }

        return $next($request);
    }
}

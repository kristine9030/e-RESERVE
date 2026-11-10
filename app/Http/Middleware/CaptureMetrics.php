<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records how long each web request took and how it ended, after the response has gone out,
 * so the super admin's Performance tab shows real traffic. Never allowed to break a request.
 */
class CaptureMetrics
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (app()->runningUnitTests() || $request->is('up')) {
            return;
        }

        try {
            $start = defined('LARAVEL_START') ? LARAVEL_START : (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

            DB::table('request_metrics')->insert([
                'route'       => substr((string) ($request->route()?->getName() ?? $request->path()), 0, 120),
                'method'      => substr($request->method(), 0, 8),
                'status'      => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'created_at'  => now(),
            ]);

            if (random_int(1, 300) === 1) {   // keep the table to the last 30 days
                DB::table('request_metrics')->where('created_at', '<', now()->subDays(30))->delete();
            }
        } catch (\Throwable) {
            // metrics are a nice-to-have
        }
    }
}

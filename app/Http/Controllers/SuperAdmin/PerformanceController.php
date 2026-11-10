<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SystemMetrics;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    public function __invoke(SystemMetrics $m)
    {
        $e = fn (string $q) => (int) DB::selectOne($q)->c;

        return view('superadmin.performance', [
            'resp'    => $m->response(),
            'traffic' => $m->traffic(),
            'slowest' => $m->slowest(),
            'daily'   => $m->daily(14),
            'errors'  => $m->errors(),
            'health'  => $m->health(),
            'event'   => [
                'docs_waiting' => $e("select count(*) c from documents where status = 'submitted'"),
                'tickets'      => $e("select count(*) c from tickets where status <> 'void'"),
                'checkins'     => $e('select count(*) c from checkins'),
                'logins_today' => $e("select count(*) c from audit_log where action in ('login','login.google') and created_at >= '" . now()->startOfDay()->format('Y-m-d H:i:s') . "'"),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\SystemMetrics;
use App\Services\TicketQuota;
use App\Support\ActivityText;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(SystemMetrics $metrics)
    {
        $roles = UserController::roleCounts();

        return view('superadmin.dashboard', [
            'total'    => array_sum(array_column($roles, 'count')),
            'newWeek'  => DB::table('users')->where('created_at', '>=', now()->subDays(7))->count(),
            'disabled' => DB::table('users')->where('status', 'disabled')->count(),
            'roles'    => $roles,
            'tickets'  => DB::table('tickets')->where('status', '<>', 'void')->count(),
            'checked'  => DB::table('checkins')->count(),
            'capacity' => TicketQuota::capacity(),
            'quota'    => ['limits' => TicketQuota::limits(), 'taken' => TicketQuota::taken(), 'labels' => TicketQuota::GROUPS],
            'resp'     => $metrics->response(),
            'traffic'  => $metrics->traffic(),
            'recent'   => AuditLog::orderByDesc('id')->limit(10)->get()->map(fn ($r) => [
                'actor' => $r->actor_name, 'when' => $r->created_at,
                'text'  => ActivityText::describe($r->action, $r->entity_type, $r->entity_id, $r->context),
            ]),
        ]);
    }
}

<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit;
use App\Support\ActivityText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Every recorded action in the system, newest first, with filters and a CSV export. */
class ActivityController extends Controller
{
    private function query(Request $request)
    {
        $q = AuditLog::query();

        if (($s = trim((string) $request->query('q'))) !== '') {
            $like = '%' . addcslashes($s, '%_\\') . '%';
            $q->where(fn ($w) => $w->where('actor_name', 'like', $like)->orWhere('action', 'like', $like)->orWhere('entity_id', 'like', $like)
                ->orWhere('ip_address', 'like', $like)->orWhereRaw('CAST(context AS CHAR) like ?', [$like]));
        }
        if (($a = (string) $request->query('action')) !== '') {
            $q->where('action', $a);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('from'))) {
            $q->where('created_at', '>=', $request->query('from') . ' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('to'))) {
            $q->where('created_at', '<=', $request->query('to') . ' 23:59:59');
        }

        return $q;
    }

    public function index(Request $request)
    {
        $rows = $this->query($request)->orderByDesc('id')->paginate(50)->withQueryString();
        $rows->getCollection()->transform(function ($r) {
            $r->text = ActivityText::describe($r->action, $r->entity_type, $r->entity_id, $r->context);

            return $r;
        });

        return view('superadmin.activity', [
            'rows'    => $rows,
            'total'   => AuditLog::count(),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->query($request)->orderByDesc('id')->limit(50000)->get();
        Audit::log('superadmin.export', 'activity', null, ['rows' => $rows->count()]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // so Excel reads UTF-8
            fputcsv($out, ['When', 'Actor', 'Role', 'Action', 'Description', 'IP']);
            foreach ($rows as $r) {
                // a leading = + - @ would run as a formula in Excel
                $safe = fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v;
                fputcsv($out, [$r->created_at?->format('Y-m-d H:i:s'), $safe($r->actor_name), $r->actor_role, $r->action,
                    $safe(ActivityText::describe($r->action, $r->entity_type, $r->entity_id, $r->context)), $r->ip_address]);
            }
            fclose($out);
        }, 'activity-log-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

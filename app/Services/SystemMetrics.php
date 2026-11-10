<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Numbers behind the super admin's Dashboard and Performance tabs (from the request_metrics table and the server). */
class SystemMetrics
{
    private const SAMPLE = 200;

    private function has(): bool
    {
        try {
            return Schema::hasTable('request_metrics');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{avg:?int,p95:?int,p99:?int,samples:int} over the most recent requests */
    public function response(): array
    {
        $d = $this->has() ? DB::table('request_metrics')->orderByDesc('id')->limit(self::SAMPLE)->pluck('duration_ms')->map(fn ($v) => (int) $v)->sort()->values()->all() : [];
        $n = count($d);
        if ($n === 0) {
            return ['avg' => null, 'p95' => null, 'p99' => null, 'samples' => 0];
        }
        $pct = fn (float $p) => $d[max(0, (int) ceil($p * $n) - 1)];

        return ['avg' => (int) round(array_sum($d) / $n), 'p95' => $pct(0.95), 'p99' => $pct(0.99), 'samples' => $n];
    }

    /** @return array{today:int,days14:int,reliability:?float,errors_today:int} */
    public function traffic(): array
    {
        if (!$this->has()) {
            return ['today' => 0, 'days14' => 0, 'reliability' => null, 'errors_today' => 0];
        }
        $since = now()->subDays(14);
        $q     = DB::table('request_metrics');
        $days  = (clone $q)->where('created_at', '>=', $since)->count();
        $bad   = (clone $q)->where('created_at', '>=', $since)->where('status', '>=', 500)->count();

        return [
            'today'        => (clone $q)->where('created_at', '>=', now()->startOfDay())->count(),
            'days14'       => $days,
            'reliability'  => $days === 0 ? null : round(($days - $bad) / $days * 100, 1),
            'errors_today' => (clone $q)->where('created_at', '>=', now()->startOfDay())->where('status', '>=', 500)->count(),
        ];
    }

    /** Slowest routes among the recent samples. @return list<array{route:string,samples:int,avg:int,max:int}> */
    public function slowest(int $limit = 8): array
    {
        if (!$this->has()) {
            return [];
        }
        $rows = DB::table('request_metrics')->orderByDesc('id')->limit(self::SAMPLE)->get(['route', 'duration_ms'])->groupBy('route');

        return $rows->map(fn ($g, $route) => [
            'route' => (string) $route, 'samples' => $g->count(),
            'avg' => (int) round($g->avg('duration_ms')), 'max' => (int) $g->max('duration_ms'),
        ])->sortByDesc('avg')->take($limit)->values()->all();
    }

    /** Daily average response time and request count, oldest first, empty days included. @return list<array{day:string,avg:?int,count:int}> */
    public function daily(int $days = 14): array
    {
        $by = $this->has()
            ? DB::table('request_metrics')->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                ->selectRaw('DATE(created_at) as d, AVG(duration_ms) as a, COUNT(*) as c')->groupBy('d')->get()->keyBy('d')
            : collect();

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day   = now()->subDays($i)->format('Y-m-d');
            $row   = $by->get($day);
            $out[] = ['day' => $day, 'avg' => $row ? (int) round($row->a) : null, 'count' => $row ? (int) $row->c : 0];
        }

        return $out;
    }

    /** Recent server errors. @return list<object> */
    public function errors(int $limit = 8): array
    {
        return $this->has() ? DB::table('request_metrics')->where('status', '>=', 500)->orderByDesc('id')->limit($limit)->get()->all() : [];
    }

    /** What the server itself looks like. @return list<array{0:string,1:string,2:string}> label, value, state (ok|warn|bad|'') */
    public function health(): array
    {
        $rows = [];

        $t = microtime(true);
        try {
            DB::select('select 1');
            $ms     = (int) round((microtime(true) - $t) * 1000);
            $rows[] = ['Database', DB::getDriverName() . ' · ' . $ms . ' ms round trip', $ms > 200 ? 'warn' : 'ok'];
        } catch (\Throwable) {
            $rows[] = ['Database', 'not reachable', 'bad'];
        }

        $free  = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if ($free !== false && $total) {
            $pct    = round($free / $total * 100);
            $rows[] = ['Disk space', self::bytes((float) $free) . ' free of ' . self::bytes((float) $total) . ' (' . $pct . '% free)', $pct < 10 ? 'bad' : ($pct < 20 ? 'warn' : 'ok')];
        }

        $debug  = (bool) config('app.debug');
        $prod   = app()->environment('production');
        $rows[] = ['Environment', config('app.env') . ($debug ? ' · debug ON' : ''), $debug && $prod ? 'bad' : 'ok'];
        $rows[] = ['PHP / Laravel', PHP_VERSION . ' / ' . app()->version(), ''];
        $rows[] = ['Cache / Queue', config('cache.default') . ' / ' . config('queue.default'), ''];
        $rows[] = ['Mail', (string) config('mail.default'), config('mail.default') === 'log' ? 'warn' : 'ok'];

        try {
            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $failed  = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $rows[]  = ['Queued / failed jobs', $pending . ' waiting · ' . $failed . ' failed', $failed > 0 ? 'bad' : ($pending > 100 ? 'warn' : 'ok')];
        } catch (\Throwable) {
        }

        return $rows;
    }

    public static function bytes(float $b): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $u) {
            if ($b < 1024 || $u === 'TB') {
                return round($b, $u === 'B' ? 0 : 1) . ' ' . $u;
            }
            $b /= 1024;
        }

        return '';
    }
}

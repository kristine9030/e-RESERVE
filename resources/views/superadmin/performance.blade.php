@extends('superadmin.layout')
@section('title', 'Performance')
@section('nav', 'performance')
@section('heading', 'Performance')
@section('sub', 'Real traffic captured by the app itself: response time, throughput and error trend over the last 14 days.')

@section('content')
<div class="sa-grid sa-g4">
    <div class="sa-stat"><div><b>{{ $resp['avg'] === null ? '—' : $resp['avg'] . 'ms' }}</b><span>Avg Response Time</span><small>{{ $resp['samples'] }} recent samples</small></div><span class="sa-ico bad"><i class="fa-solid fa-gauge"></i></span></div>
    <div class="sa-stat"><div><b>{{ $resp['p95'] === null ? '—' : $resp['p95'] . 'ms' }}</b><span>p95 Response Time</span><small>p99 {{ $resp['p99'] === null ? '—' : $resp['p99'] . 'ms' }}</small></div><span class="sa-ico"><i class="fa-solid fa-stopwatch"></i></span></div>
    <div class="sa-stat"><div><b>{{ number_format($traffic['today']) }}</b><span>Requests Today</span><small>{{ number_format($traffic['days14']) }} in the last 14 days</small></div><span class="sa-ico ok"><i class="fa-solid fa-arrow-trend-up"></i></span></div>
    <div class="sa-stat"><div><b>{{ $traffic['reliability'] === null ? '—' : $traffic['reliability'] . '%' }}</b><span>Reliability (14d)</span><small class="{{ $traffic['errors_today'] ? 'bad' : 'ok' }}">{{ $traffic['errors_today'] }} server error(s) today</small></div><span class="sa-ico ok"><i class="fa-solid fa-shield-halved"></i></span></div>
</div>

<div class="sa-grid sa-g2e">
    <div class="sa-card">
        <h2><i class="fa-solid fa-chart-line"></i> Response Time Trend</h2>
        <div class="sub">Daily average in milliseconds. Empty days had no traffic.</div>
        @php $maxAvg = max(1, collect($daily)->max('avg') ?? 1); @endphp
        <div class="sa-bars">
            @foreach ($daily as $d)
                <div title="{{ $d['day'] }}: {{ $d['avg'] === null ? 'no traffic' : $d['avg'] . ' ms' }}">
                    <i class="{{ $d['avg'] === null ? 'zero' : '' }}" style="height:{{ $d['avg'] === null ? 2 : max(3, round($d['avg'] / $maxAvg * 100)) }}%"></i>
                    <span>{{ \Illuminate\Support\Carbon::parse($d['day'])->format('j') }}</span>
                </div>
            @endforeach
        </div>
    </div>
    <div class="sa-card">
        <h2><i class="fa-solid fa-server"></i> Requests per Day</h2>
        <div class="sub">Total requests handled by the app each day.</div>
        @php $maxCnt = max(1, collect($daily)->max('count') ?? 1); @endphp
        <div class="sa-bars">
            @foreach ($daily as $d)
                <div title="{{ $d['day'] }}: {{ number_format($d['count']) }} requests">
                    <i class="{{ $d['count'] === 0 ? 'zero' : '' }}" style="height:{{ $d['count'] === 0 ? 2 : max(3, round($d['count'] / $maxCnt * 100)) }}%"></i>
                    <span>{{ \Illuminate\Support\Carbon::parse($d['day'])->format('j') }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="sa-grid sa-g2e">
    <div class="sa-card">
        <h2>Slowest Routes</h2>
        <div class="sub">Endpoints driving the tail, from the same {{ $resp['samples'] }} recent samples.</div>
        <div class="sa-scroll"><table class="sa-table">
            <thead><tr><th>Route</th><th class="num">Samples</th><th class="num">Avg</th><th class="num">Max</th></tr></thead>
            <tbody>
                @forelse ($slowest as $s)
                    <tr><td>{{ $s['route'] }}</td><td class="num">{{ $s['samples'] }}</td><td class="num">{{ $s['avg'] }}ms</td><td class="num">{{ $s['max'] }}ms</td></tr>
                @empty
                    <tr><td colspan="4" class="sa-empty">No samples yet. They appear as people use the site.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="sa-card">
        <h2>Server Health</h2>
        <div class="sub">The machine and services behind the site.</div>
        <table class="sa-table"><tbody>
            @foreach ($health as [$label, $value, $state])
                <tr><td style="width:38%;"><span class="sa-dot {{ $state }}"></span>{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
        </tbody></table>
    </div>
</div>

<div class="sa-grid sa-g2e">
    <div class="sa-card">
        <h2>Event Load</h2>
        <div class="sub">What the system is being asked to carry right now.</div>
        <div class="sa-kv" style="grid-template-columns:1fr;">
            <div><span>Documents waiting for SSC review</span><b>{{ number_format($event['docs_waiting']) }}</b></div>
            <div><span>Tickets issued</span><b>{{ number_format($event['tickets']) }}</b></div>
            <div><span>Gate check-ins</span><b>{{ number_format($event['checkins']) }}</b></div>
            <div><span>Sign-ins today</span><b>{{ number_format($event['logins_today']) }}</b></div>
        </div>
    </div>

    <div class="sa-card">
        <h2>Recent Server Errors</h2>
        <div class="sub">Requests that ended in a 5xx.</div>
        <div class="sa-scroll"><table class="sa-table">
            <thead><tr><th>When</th><th>Route</th><th class="num">Status</th></tr></thead>
            <tbody>
                @forelse ($errors as $e)
                    <tr><td>{{ $e->created_at }}</td><td>{{ $e->method }} {{ $e->route }}</td><td class="num"><span class="sa-chip danger">{{ $e->status }}</span></td></tr>
                @empty
                    <tr><td colspan="3" class="sa-empty"><i class="fa-solid fa-circle-check" style="color:var(--sa-ok);"></i> No server errors recorded.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection

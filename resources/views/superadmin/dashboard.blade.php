@extends('superadmin.layout')
@section('title', 'System console')
@section('nav', 'dashboard')
@section('heading', 'System Console')
@section('sub', 'Welcome back, ' . auth()->user()->name . '. Live snapshot of platform health and activity.')
@section('actions')
    <a href="{{ route('superadmin.users') }}#add" class="sa-btn primary"><i class="fa-solid fa-user-plus"></i> Add Account</a>
@endsection

@section('content')
<div class="sa-grid sa-g4">
    <div class="sa-stat"><div><b>{{ number_format($total) }}</b><span>Total Accounts</span><small class="ok">+{{ $newWeek }} this week</small></div><span class="sa-ico"><i class="fa-solid fa-users"></i></span></div>
    <div class="sa-stat"><div><b>{{ number_format($total - $disabled) }}</b><span>Active Accounts</span><small>{{ $disabled }} deactivated</small></div><span class="sa-ico ok"><i class="fa-solid fa-signal"></i></span></div>
    <div class="sa-stat"><div><b>{{ number_format($tickets) }}</b><span>Tickets Issued</span><small>of {{ number_format($capacity) }} · {{ number_format($checked) }} checked in</small></div><span class="sa-ico warn"><i class="fa-solid fa-ticket"></i></span></div>
    <div class="sa-stat"><div><b>{{ $resp['avg'] === null ? '—' : $resp['avg'] . 'ms' }}</b><span>Avg Response Time</span><small>{{ $resp['p95'] === null ? 'no samples yet' : 'p95 ' . $resp['p95'] . 'ms · ' . $resp['samples'] . ' samples' }}</small></div><span class="sa-ico bad"><i class="fa-solid fa-gauge"></i></span></div>
</div>

<div class="sa-grid sa-g2">
    <div>
        <div class="sa-card">
            <div class="d-flex justify-content-between"><h2>Accounts by Role</h2><a href="{{ route('superadmin.users') }}" style="font-size:.8rem;">Manage users →</a></div>
            <div class="sa-kv mt-2">
                @foreach ($roles as $key => $r)
                    <div><span>{{ $r['label'] }}s</span><b>{{ number_format($r['count']) }}</b></div>
                @endforeach
                <div><span>Requests Today</span><b>{{ number_format($traffic['today']) }}</b></div>
            </div>
        </div>

        <div class="sa-card">
            <h2>Tickets by Group</h2>
            <div class="sub">Issued against each group’s allotment. Change the split in the SSC panel’s Site Settings.</div>
            @foreach ($quota['labels'] as $g => $label)
                @php $lim = $quota['limits'][$g]; $tk = $quota['taken'][$g] ?? 0; $pct = $lim > 0 ? min(100, round($tk / $lim * 100)) : 0; @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between" style="font-size:.86rem;"><span>{{ $label }}</span><b>{{ number_format($tk) }} / {{ number_format($lim) }}</b></div>
                    <div class="sa-meter"><i style="width:{{ $pct }}%"></i></div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="sa-card">
        <div class="d-flex justify-content-between"><h2>Recent Activity</h2><a href="{{ route('superadmin.activity') }}" style="font-size:.8rem;">Full log →</a></div>
        <ul class="sa-feed mt-2">
            @forelse ($recent as $r)
                <li><b>{{ $r['actor'] }}</b> — {{ $r['text'] }}<small>{{ $r['when']?->format('Y-m-d H:i:s') }}</small></li>
            @empty
                <li class="sa-empty">Nothing recorded yet.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection

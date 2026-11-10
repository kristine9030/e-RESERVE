@extends('superadmin.layout')
@section('title', 'Activity Log')
@section('nav', 'activity')
@section('heading', 'Activity Log')
@section('sub', number_format($total) . ' recorded actions across every portal.')
@section('actions')
    <a class="sa-btn" href="{{ route('superadmin.activity.export', request()->query()) }}"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
@endsection

@section('content')
<div class="sa-card">
    <form method="get" class="sa-filter">
        <input type="search" class="form-control grow" name="q" value="{{ request('q') }}" placeholder="Search actor, action, ticket no., IP…">
        <select class="form-select" name="action" style="width:auto;max-width:200px;">
            <option value="">All Actions</option>
            @foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach
        </select>
        <input type="date" class="form-control" name="from" value="{{ request('from') }}" style="width:auto;" aria-label="From date">
        <input type="date" class="form-control" name="to" value="{{ request('to') }}" style="width:auto;" aria-label="To date">
        <button class="sa-btn" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'action', 'from', 'to']))<a class="sa-btn" href="{{ route('superadmin.activity') }}">Clear</a>@endif
    </form>

    @php
        $tone = ['superadmin' => 'purple', 'admin' => 'danger', 'scanner' => 'info', 'student' => 'success', 'system' => 'muted'];
        $roleName = ['superadmin' => 'Super admin', 'admin' => 'Admin', 'scanner' => 'Scanner', 'student' => 'Student', 'system' => 'System'];
    @endphp
    <div class="sa-scroll"><table class="sa-table">
        <thead><tr><th>When</th><th>Actor</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="text-nowrap">{{ $r->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $r->actor_name }}</td>
                    <td><span class="sa-chip {{ $tone[$r->actor_role] ?? 'muted' }}">{{ $roleName[$r->actor_role] ?? ucfirst($r->actor_role) }}</span></td>
                    <td><span class="sa-act">{{ $r->action }}</span></td>
                    <td>{{ $r->text }}</td>
                    <td class="text-nowrap" style="font-size:.78rem;color:var(--sa-soft);">{{ $r->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="sa-empty">Nothing matches those filters.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
@endsection

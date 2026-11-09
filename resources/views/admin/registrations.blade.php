@extends('layouts.app')
@section('title', 'Students')
@section('crumb', 'Administration')
@section('nav', 'registrations')

@section('content')
@php
$chip = static function (string $short, array $d): string {
    $status = $d['status'] ?: 'missing';
    $tone   = ['missing' => 'muted', 'submitted' => 'info', 'approved' => 'success', 'rejected' => 'danger'][$status];
    $label  = ['missing' => 'Not uploaded', 'submitted' => 'For review', 'approved' => 'Approved', 'rejected' => 'Returned'][$status];
    $inner  = '<span class="doc-chip chip-' . $tone . '" title="' . e($short . ' — ' . $label) . '">' . e($short) . '</span>';

    return $d['id'] ? '<a href="' . document_url($d['id']) . '" target="_blank" rel="noopener">' . $inner . '</a>' : $inner;
};
@endphp
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-users"></i> Records</span>
            <h2>Student Registrations</h2>
            <p>Every account for {{ ev('name') }}, with the status of the waiver, COR and school ID and the ticket issued.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <a class="btn btn-gold" href="{{ route('reports.export', ['report' => 'registrations', 'format' => 'sheet']) }}"><i class="fa-solid fa-file-excel"></i> Export</a>
            <button class="btn btn-outline-light2 ms-2" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php $mini = [
        ['fa-users',          'deep',   'Students',          $stats['students']],
        ['fa-ticket',         'green',  'Tickets Issued',    $stats['tickets']],
        ['fa-hourglass-half', 'orange', 'Still Uploading',   $stats['incomplete']],
        ['fa-rotate-left',    'danger', 'Docs Returned',     $stats['docs_rejected']],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d{{ $i + 1 }}">
                <div class="stat-icon {{ $tone }}"><i class="fa-solid {{ $icon }}"></i></div>
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value" data-count-to="{{ $val }}">0</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-soft fade-up d3">
    <div class="card-head">
        <div>
            <h5 class="mb-0">All Students</h5>
            <span class="cell-sub">{{ number_format($rows->total()) }} account{{ $rows->total() === 1 ? '' : 's' }}{{ array_filter($filters) ? ' match your filters' : '' }}</span>
        </div>
        <div class="d-flex gap-2 align-items-center no-print flex-wrap">
            <span class="doc-chip chip-success">Approved</span>
            <span class="doc-chip chip-info">For review</span>
            <span class="doc-chip chip-danger">Returned</span>
            <span class="doc-chip chip-muted">Missing</span>
        </div>
    </div>

    <form method="get" class="filter-bar d-flex flex-wrap gap-2 px-3 pb-3 no-print">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" style="max-width:260px;"
               placeholder="Search name, SR-code or email">
        <select name="college" class="form-select" style="max-width:150px;">
            <option value="">All colleges</option>
            @foreach (array_keys(config('ereserve.colleges')) as $code)
                <option value="{{ $code }}" @selected(($filters['college'] ?? '') === $code)>{{ $code }}</option>
            @endforeach
        </select>
        <select name="docs" class="form-select" style="max-width:190px;">
            <option value="">All document states</option>
            @foreach (['pending' => 'Has file for review', 'returned' => 'Has returned file', 'approved' => 'All 3 approved', 'incomplete' => 'Still uploading'] as $k => $label)
                <option value="{{ $k }}" @selected(($filters['docs'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="ticket" class="form-select" style="max-width:170px;">
            <option value="">Any ticket</option>
            @foreach (['issued' => 'Ticket issued', 'used' => 'Checked in', 'none' => 'No ticket'] as $k => $label)
                <option value="{{ $k }}" @selected(($filters['ticket'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-green btn-sm2" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
        @if (array_filter($filters))
            <a class="btn btn-ghost btn-sm2" href="{{ route('admin.registrations') }}">Clear</a>
        @endif
    </form>

    <div class="table-responsive">
        <table class="table table-stack" style="width:100%;">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Documents</th>
                    <th>Ticket No.</th>
                    <th>Ticket</th>
                    <th>Gate</th>
                    <th>Registered</th>
                    <th class="no-sort no-print">Account</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td>
                            <div class="cell-strong">{{ $r['name'] }}@if (($r['member_type'] ?? 'student') !== 'student') <span class="badge-soft badge-gold ms-1">{{ config('ereserve.employee_types')[$r['member_type']] ?? 'Employee' }}</span>@endif</div>
                            <div class="cell-sub">{{ $r['student_no'] }} · {{ $r['email'] }}</div>
                        </td>
                        <td data-label="Program" {{ $r['program'] ? '' : 'data-empty' }}>{{ $r['program'] }}<div class="cell-sub">{{ $r['year'] }}</div></td>
                        <td data-label="Documents" data-order="{{ $r['doc_count'] }}">
                            @if (($r['member_type'] ?? 'student') !== 'student')
                                <span class="cell-sub">Not required</span>
                            @else
                            <div class="d-flex gap-1 flex-wrap">
                                {!! $chip('Waiver', $r['docs']['waiver']) !!}
                                {!! $chip('COR', $r['docs']['cor']) !!}
                                {!! $chip('ID', $r['docs']['id']) !!}
                            </div>
                            @endif
                        </td>
                        <td data-label="Ticket No." {{ $r['ticket_no'] ? '' : 'data-empty' }}><span class="mono">{{ $r['ticket_no'] ?: '—' }}</span></td>
                        <td data-label="Ticket" {{ $r['ticket_status'] ? '' : 'data-empty' }}>{!! $r['ticket_status'] ? status_badge($r['ticket_status']) : '<span class="cell-sub">—</span>' !!}</td>
                        <td data-label="Gate" {{ $r['checked_in'] ? '' : 'data-empty' }}>{!! $r['checked_in']
                            ? '<span class="badge-soft badge-info"><i class="fa-solid fa-user-check"></i> In</span>'
                            : '<span class="cell-sub">—</span>' !!}</td>
                        <td class="cell-sub" data-label="Registered" data-order="{{ $r['registered_at'] }}">{{ pretty_date($r['registered_at']) }}</td>
                        <td class="no-print" data-label="Account">
                            @php $off = $r['account_status'] === 'disabled'; @endphp
                            <form method="post" action="{{ route('admin.students.status', $r['id']) }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $off ? 'enable' : 'disable' }}">
                                <button type="submit" class="btn btn-sm2 {{ $off ? 'btn-green' : 'btn-ghost' }}"
                                        data-confirm-title="{{ $off ? 'Enable this account?' : 'Disable this account?' }}"
                                        data-confirm-text="{{ $off ? $r['name'] . ' will be able to sign in again.' : $r['name'] . ' will be signed out and their ticket put on hold.' }}"
                                        data-confirm-icon="warning"
                                        data-confirm-yes="{{ $off ? 'Enable' : 'Disable' }}">
                                    <i class="fa-solid {{ $off ? 'fa-user-check' : 'fa-user-slash' }}"></i> {{ $off ? 'Enable' : 'Disable' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($rows->hasPages())
        <div class="px-3 py-3 no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="cell-sub">Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ number_format($rows->total()) }}</span>
            {{ $rows->links() }}
        </div>
    @endif
</div>
@endsection

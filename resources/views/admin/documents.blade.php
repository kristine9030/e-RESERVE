@extends('layouts.app')
@section('title', 'Review Documents')
@section('crumb', 'Administration')
@section('nav', 'documents')

@section('content')
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-user-shield"></i> Document Control</span>
            <h2>Review Documents</h2>
            <p>Open a student to approve or return their documents.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="badge-soft badge-gold" style="font-size:.82rem;">
                <i class="fa-solid fa-inbox"></i> {{ number_format($queue->total()) }} students waiting
            </span>
        </div>
    </div>
</div>

<!-- ================= Needs review: one table ================= -->
<div class="card-soft stu-card rq-card fade-up d2 mb-4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Needs review</h5>
            <span class="cell-sub">Longest wait first · {{ number_format($queue->total()) }} student{{ $queue->total() === 1 ? '' : 's' }} waiting</span>
        </div>
    </div>

    <?php
        $tab  = $filters['type'] ?? 'all';
        $tabs = ['all' => ['All', 'fa-layer-group'], 'student' => ['Students', 'fa-user-graduate'], 'employee' => ['Faculty & Staff', 'fa-chalkboard-user'], 'sponsor' => ['Sponsors', 'fa-handshake']];
    ?>
    <nav class="stu-tabs no-print" aria-label="Filter by group">
        @foreach ($tabs as $key => [$label, $icon])
            <a class="stu-tab {{ $tab === $key ? 'is-active' : '' }}" href="{{ route('admin.documents', array_filter(['q' => $filters['q'] ?? null, 'college' => $filters['college'] ?? null, 'type' => $key === 'all' ? null : $key])) }}">
                <i class="fa-solid {{ $icon }}"></i>{{ $label }} <b>{{ number_format($counts[$key] ?? 0) }}</b>
            </a>
        @endforeach
    </nav>

    <form method="get" class="rq-filter no-print">
        @if ($tab !== 'all')<input type="hidden" name="type" value="{{ $tab }}">@endif
        <div class="stu-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Search name, SR-code, employee ID or email">
        </div>
        <select name="college" class="form-select">
            <option value="">All colleges</option>
            @foreach (config('ereserve.colleges') as $code => $name)
                <option value="{{ $code }}" @selected(($filters['college'] ?? '') === $code)>{{ $code }}</option>
            @endforeach
        </select>
        <div class="stu-filter-actions">
            <button class="btn btn-green btn-sm2" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
            @if (array_filter(\Illuminate\Support\Arr::except($filters, 'type')))<a class="btn btn-ghost btn-sm2" href="{{ route('admin.documents', array_filter(['type' => $filters['type'] ?? null])) }}">Clear</a>@endif
        </div>
    </form>

    @if ($queue->isEmpty())
        <div class="text-center py-5">
            <span class="stat-icon green d-inline-grid mb-3" style="width:56px;height:56px;font-size:1.3rem;"><i class="fa-solid fa-check"></i></span>
            <h5 class="mb-1">Nothing to review</h5>
            <p class="text-soft mb-0" style="font-size:.88rem;">No one is waiting for a review{{ array_filter($filters) ? ' with these filters' : '' }}.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-stack stu-table rq-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>College / group</th>
                        <th>Documents</th>
                        <th>Waiting</th>
                        <th class="no-print text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($queue as $s)
                        <?php
                            $look = false;
                            foreach ($s['docs'] as $d) {
                                $ck = $d['auto_checks'] ?? [];
                                $ck = is_string($ck) ? (json_decode($ck, true) ?: []) : $ck;
                                if ($d && \App\Services\DocumentChecker::needsLook($ck)) { $look = true; }
                            }
                            $mt    = $s['member_type'] ?? 'student';
                            $group = $mt === 'student' ? null : ($mt === 'sponsor' ? 'Sponsor' : (config('ereserve.employee_types')[$mt] ?? 'Employee'));
                        ?>
                        <tr class="{{ $look ? 'needs-look' : '' }}">
                            <td>
                                <div class="cell-strong">{{ $s['name'] }}</div>
                                <div class="cell-sub mono">{{ $s['student_no'] }}</div>
                                <div class="cell-sub stu-email" title="{{ $s['email'] }}">{{ $s['email'] }}</div>
                            </td>
                            <td data-label="College / group">
                                <div class="d-flex flex-wrap align-items-center gap-1">
                                    @if ($group)<span class="badge-soft badge-gold">{{ $group }}</span>
                                    @elseif ($s['college_code'])<span class="badge-soft badge-info">{{ $s['college_code'] }}</span>@endif
                                </div>
                                @if ($s['program'])<div class="cell-sub mt-1">{{ $s['program'] }}@if ($s['year_level']) · {{ $s['year_level'] }}@endif</div>@endif
                            </td>
                            <td data-label="Documents">
                                <div class="student-docs">
                                    @foreach (\App\Support\DocumentSets::metaFor($mt) as $key => $m)
                                        <?php $d = $s['docs'][$key] ?? null; $st = $d['status'] ?? 'none'; ?>
                                        <span class="sd sd-{{ $st }}" title="{{ $m['label'] }}: {{ $st === 'none' ? 'not uploaded' : $st }}">
                                            <i class="fa-solid {{ ['approved' => 'fa-circle-check', 'submitted' => 'fa-hourglass-half', 'rejected' => 'fa-rotate-left', 'none' => 'fa-minus'][$st] ?? 'fa-minus' }}"></i>{{ $m['short'] }}
                                        </span>
                                    @endforeach
                                </div>
                                @if ($look)<div class="mt-1"><span class="chk chk-warn"><i class="fa-solid fa-triangle-exclamation"></i>Needs a closer look</span></div>@endif
                            </td>
                            <td data-label="Waiting" data-order="{{ $s['waiting_since'] }}">
                                <div class="cell-strong rq-n">{{ $s['waiting'] }} <small>of {{ count($s['docs']) }}</small></div>
                                <div class="cell-sub">since {{ pretty_date($s['waiting_since']) }}</div>
                            </td>
                            <td class="no-print text-end" data-label="Action">
                                <a href="{{ route('admin.documents.student', array_filter(['user' => $s['id'], 'college' => $filters['college'] ?? null])) }}" class="btn btn-green btn-sm2">
                                    <i class="fa-solid fa-folder-open"></i> Review
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($queue->hasPages())
            <div class="px-4 py-3 no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="cell-sub">Showing {{ $queue->firstItem() }}–{{ $queue->lastItem() }} of {{ number_format($queue->total()) }}</span>
                {{ $queue->links() }}
            </div>
        @endif
    @endif
</div>
@endsection

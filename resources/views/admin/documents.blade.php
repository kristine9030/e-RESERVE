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
            <p>Review one student at a time: open a student to see their waiver, COR and school ID together, then approve or return each one.
               Returning a document pauses that student’s ticket until they upload a corrected file.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="badge-soft badge-gold" style="font-size:.82rem;">
                <i class="fa-solid fa-inbox"></i> {{ number_format($queue->total()) }} students waiting
            </span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php $mini = [
        ['fa-user-clock',           'orange', 'Students Waiting',   $queue->total()],
        ['fa-file-circle-question', 'gold',   'Documents Waiting',  $stats['docs_pending']],
        ['fa-circle-check',         'green',  'Documents Approved', $stats['docs_approved']],
        ['fa-rotate-left',          'danger', 'Documents Returned', $stats['docs_rejected']],
        ['fa-ticket',               'gold',   'Tickets Issued',     $stats['tickets']],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg">
            <div class="stat-card fade-up d{{ min($i + 1, 4) }}">
                <div class="stat-icon {{ $tone }}"><i class="fa-solid {{ $icon }}"></i></div>
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value" style="font-size:1.5rem;">{{ number_format((int) $val) }}</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ================= Per college ================= -->
<div class="card-soft fade-up d2 mb-4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">By college</h5>
            <span class="cell-sub">Click a college to see only its students in the queue</span>
        </div>
        @if (!empty($filters['college']))
            <a class="btn btn-ghost btn-sm2" href="{{ route('admin.documents', array_filter(['q' => $filters['q'] ?? null])) }}">Show all colleges</a>
        @endif
    </div>
    <div class="card-body-p">
        <div class="row g-3">
            @foreach ($colleges as $c)
                <?php $on = ($filters['college'] ?? '') === $c['code']; ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ $c['code'] !== '' ? route('admin.documents', array_filter(['college' => $on ? null : $c['code'], 'q' => $filters['q'] ?? null])) : '#' }}"
                       class="college-card {{ $on ? 'is-on' : '' }}" title="{{ $c['name'] }}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <div class="college-code">{{ $c['code'] ?: '—' }}</div>
                                <div class="cell-sub text-truncate">{{ $c['name'] }}</div>
                            </div>
                            <span class="college-waiting {{ $c['waiting'] ? 'has' : '' }}">
                                <b>{{ number_format($c['waiting']) }}</b> waiting
                            </span>
                        </div>
                        <div class="college-nums">
                            <div><b>{{ number_format($c['students']) }}</b><span>Students</span></div>
                            <div><b>{{ number_format($c['approved']) }}</b><span>Cleared</span></div>
                            <div><b>{{ number_format($c['returned']) }}</b><span>Returned</span></div>
                            <div><b>{{ number_format($c['tickets']) }}</b><span>Tickets</span></div>
                        </div>
                        <div class="college-bar" title="{{ (int) $c['done'] }}% of this college’s students are fully approved"><i style="width:{{ (int) $c['done'] }}%"></i></div>
                        <div class="d-flex justify-content-between cell-sub mt-1">
                            <span>{{ (int) $c['done'] }}% cleared</span>
                            <span>{{ $c['share'] }}% of all students</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- ================= Student queue ================= -->
<div class="card-soft fade-up d3 mb-4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Students waiting for review</h5>
            <span class="cell-sub">Longest wait first · {{ number_format($queue->total()) }} in the queue</span>
        </div>
        <form method="get" class="filter-bar d-flex flex-wrap gap-2 no-print">
            <select name="college" class="form-select form-select-sm" style="max-width:150px;">
                <option value="">All colleges</option>
                @foreach (config('ereserve.colleges') as $code => $name)
                    <option value="{{ $code }}" @selected(($filters['college'] ?? '') === $code)>{{ $code }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" style="max-width:210px;" placeholder="Name, SR-code or email">
            <button class="btn btn-green btn-sm2" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            @if (array_filter($filters))<a class="btn btn-ghost btn-sm2" href="{{ route('admin.documents') }}">Clear</a>@endif
        </form>
    </div>

    <div class="card-body-p">
        @if ($queue->isEmpty())

            <div class="text-center py-5">
                <span class="stat-icon green d-inline-grid mb-3" style="width:56px;height:56px;font-size:1.3rem;">
                    <i class="fa-solid fa-check"></i>
                </span>
                <h5 class="mb-1">Queue is clear</h5>
                <p class="text-soft mb-0" style="font-size:.88rem;">No student is waiting for a review{{ array_filter($filters) ? ' with these filters' : '' }}.</p>
            </div>

        @else

            @foreach ($queue as $s)
                <?php $look = false;
                      foreach ($s['docs'] as $d) {
                          $ck = $d['auto_checks'] ?? [];
                          $ck = is_string($ck) ? (json_decode($ck, true) ?: []) : $ck;
                          if ($d && \App\Services\DocumentChecker::needsLook($ck)) { $look = true; }
                      } ?>
                <div class="review-card {{ $look ? 'needs-look' : '' }}">
                    <span class="review-icon"><i class="fa-solid fa-user-graduate"></i></span>

                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="cell-strong">{{ $s['name'] }}</span>
                            @if ($s['college_code'])<span class="badge-soft badge-gold">{{ $s['college_code'] }}</span>@endif
                            <span class="cell-sub">{{ $s['student_no'] }} · {{ $s['program'] }}@if ($s['year_level']) · {{ $s['year_level'] }}@endif</span>
                        </div>
                        <div class="student-docs mt-2">
                            @foreach (config('ereserve.documents') as $key => $m)
                                <?php $d = $s['docs'][$key]; $st = $d['status'] ?? 'none'; ?>
                                <span class="sd sd-{{ $st }}" title="{{ $m['label'] }}: {{ $st === 'none' ? 'not uploaded' : $st }}">
                                    <i class="fa-solid {{ ['approved' => 'fa-circle-check', 'submitted' => 'fa-hourglass-half', 'rejected' => 'fa-rotate-left', 'none' => 'fa-minus'][$st] ?? 'fa-minus' }}"></i>{{ $m['short'] }}
                                </span>
                            @endforeach
                            @if ($look)<span class="chk chk-warn"><i class="fa-solid fa-triangle-exclamation"></i>Needs a closer look</span>@endif
                        </div>
                        <div class="cell-sub mt-1">
                            {{ $s['waiting'] }} of {{ count(config('ereserve.documents')) }} waiting · since {{ pretty_date($s['waiting_since']) }}
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.documents.student', array_filter(['user' => $s['id'], 'college' => $filters['college'] ?? null])) }}" class="btn btn-green btn-sm2">
                            <i class="fa-solid fa-folder-open"></i> Review documents
                        </a>
                    </div>
                </div>
            @endforeach

            @if ($queue->hasPages())
                <div class="pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="cell-sub">Showing {{ $queue->firstItem() }}–{{ $queue->lastItem() }} of {{ number_format($queue->total()) }}</span>
                    {{ $queue->links() }}
                </div>
            @endif

        @endif
    </div>
</div>

<!-- ================= Reviewed history ================= -->
<div class="card-soft fade-up d4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Review history</h5>
            <span class="cell-sub">Every decision is written to the audit trail</span>
        </div>
        <a href="{{ route('reports.show', 'registrations') }}" class="btn btn-ghost btn-sm2">Registration Report <i class="fa-solid fa-arrow-right"></i></a>
    </div>

    <div class="table-responsive">
        <table class="table table-stack js-datatable" data-page-length="10" style="width:100%;">
            <thead>
                <tr>
                    <th>Reviewed</th><th>Student</th><th>Document</th>
                    <th>Decision</th><th>Reviewed by</th><th>Note</th><th class="no-sort">File</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reviewed as $r)
                    <tr>
                        <td data-label="Reviewed" class="cell-sub">{{ pretty_date($r['reviewed_at']) }}</td>
                        <td data-label="Student">
                            <a href="{{ route('admin.documents.student', $r['user_id']) }}" class="cell-strong text-decoration-none">{{ $r['student_name'] }}</a>
                            <div class="cell-sub">{{ $r['student_no'] }}</div>
                        </td>
                        <td data-label="Document">{{ config('ereserve.documents')[$r['doc_type']]['short'] }}</td>
                        <td data-label="Decision">{!! status_badge($r['status']) !!}</td>
                        <td data-label="Reviewed by" class="cell-sub">{{ $r['reviewer'] ?: '—' }}</td>
                        <td data-label="Note" class="cell-sub">{{ $r['review_note'] ?: '—' }}</td>
                        <td data-label="File"><a href="{{ document_url((int) $r['id']) }}" target="_blank" rel="noopener" class="icon-btn" style="width:32px;height:32px;border-radius:9px;" title="Open PDF"><i class="fa-solid fa-file-pdf" style="font-size:.75rem;"></i></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

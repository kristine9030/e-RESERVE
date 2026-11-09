@extends('layouts.app')
@section('title', 'Students')
@section('crumb', 'Administration')
@section('nav', 'registrations')

@section('content')
@php
$chip = static function (string $short, array $d, string $key = ''): string {
    $status = $d['status'] ?: 'missing';
    $tone   = ['missing' => 'muted', 'submitted' => 'info', 'approved' => 'success', 'rejected' => 'danger'][$status];
    $label  = ['missing' => 'Not uploaded', 'submitted' => 'For review', 'approved' => 'Approved', 'rejected' => 'Returned'][$status];
    $inner  = '<span class="doc-chip chip-' . $tone . '" title="' . e($short . ' — ' . $label) . '">' . e($short) . '</span>';

    return $d['id'] ? '<button type="button" class="chip-btn js-viewdocs" data-start="' . e($key) . '">' . $inner . '</button>' : $inner;
};

/** The three documents of one student, shaped for the viewer modal. */
$viewerData = static function (array $r): string {
    $out = [];
    foreach (['waiver' => 'Waiver', 'cor' => 'COR', 'id' => 'School ID'] as $k => $label) {
        $d = $r['docs'][$k];
        $out[] = ['key' => $k, 'label' => $label, 'status' => $d['status'] ?: 'missing', 'url' => $d['id'] ? document_url($d['id']) : null];
    }

    return json_encode(['name' => $r['name'], 'sr' => $r['student_no'], 'review' => route('admin.documents.student', $r['id']), 'docs' => $out]);
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
        <div class="d-flex gap-2 align-items-center no-print flex-wrap stu-legend">
            <span class="cell-sub me-1">Documents:</span>
            <span class="doc-chip chip-success">Approved</span>
            <span class="doc-chip chip-info">For review</span>
            <span class="doc-chip chip-danger">Returned</span>
            <span class="doc-chip chip-muted">Missing</span>
        </div>
    </div>

    <form method="get" class="stu-filter no-print">
        <div class="stu-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Search name, SR-code or email">
        </div>
        <select name="college" class="form-select">
            <option value="">All colleges</option>
            @foreach (array_keys(config('ereserve.colleges')) as $code)
                <option value="{{ $code }}" @selected(($filters['college'] ?? '') === $code)>{{ $code }}</option>
            @endforeach
        </select>
        <select name="docs" class="form-select">
            <option value="">All document states</option>
            @foreach (['pending' => 'Has file for review', 'returned' => 'Has returned file', 'approved' => 'All 3 approved', 'incomplete' => 'Still uploading'] as $k => $label)
                <option value="{{ $k }}" @selected(($filters['docs'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="ticket" class="form-select">
            <option value="">Any ticket</option>
            @foreach (['issued' => 'Ticket issued', 'used' => 'Checked in', 'none' => 'No ticket'] as $k => $label)
                <option value="{{ $k }}" @selected(($filters['ticket'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="stu-filter-actions">
            <button class="btn btn-green btn-sm2" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
            @if (array_filter($filters))
                <a class="btn btn-ghost btn-sm2" href="{{ route('admin.registrations') }}">Clear</a>
            @endif
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-stack stu-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Documents</th>
                    <th>Ticket</th>
                    <th>Registered</th>
                    <th class="no-sort no-print text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                    @php $isStudent = ($r['member_type'] ?? 'student') === 'student'; $off = $r['account_status'] === 'disabled'; @endphp
                    <tr class="{{ $off ? 'is-off' : '' }}">
                        <td>
                            <div class="cell-strong">{{ $r['name'] }}@if (!$isStudent) <span class="badge-soft badge-gold ms-1">{{ config('ereserve.employee_types')[$r['member_type']] ?? 'Employee' }}</span>@endif @if ($off)<span class="badge-soft badge-muted ms-1">Disabled</span>@endif</div>
                            <div class="cell-sub mono">{{ $r['student_no'] }}</div>
                            <div class="cell-sub stu-email" title="{{ $r['email'] }}">{{ $r['email'] }}</div>
                        </td>
                        <td data-label="Program" {{ $r['program'] ? '' : 'data-empty' }}>
                            <div>
                                <div class="stu-prog">{{ $r['program'] }}</div>
                                <div class="cell-sub">{{ $r['year'] }}</div>
                            </div>
                        </td>
                        <td data-label="Documents" data-order="{{ $r['doc_count'] }}">
                            @if (!$isStudent)
                                <span class="cell-sub">Not required</span>
                            @else
                                <div class="d-flex gap-1 flex-wrap">
                                    {!! $chip('Waiver', $r['docs']['waiver'], 'waiver') !!}
                                    {!! $chip('COR', $r['docs']['cor'], 'cor') !!}
                                    {!! $chip('ID', $r['docs']['id'], 'id') !!}
                                </div>
                            @endif
                        </td>
                        <td data-label="Ticket">
                            @if ($r['ticket_status'])
                                <div class="stu-ticket">
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        {!! status_badge($r['ticket_status']) !!}
                                        @if ($r['checked_in'])<span class="badge-soft badge-info"><i class="fa-solid fa-user-check"></i> In</span>@endif
                                    </div>
                                    <div class="cell-sub mono mt-1">{{ $r['ticket_no'] }}</div>
                                </div>
                            @else
                                <span class="cell-sub">No ticket</span>
                            @endif
                        </td>
                        <td class="cell-sub" data-label="Registered" data-order="{{ $r['registered_at'] }}">{{ pretty_date($r['registered_at']) }}</td>
                        <td class="no-print text-end" data-label="Actions">
                            <div class="stu-actions">
                                @if ($isStudent)
                                    <button type="button" class="btn btn-green btn-sm2 js-viewdocs" data-start="" data-student="{{ $viewerData($r) }}">
                                        <i class="fa-solid fa-folder-open"></i> View documents
                                    </button>
                                @endif
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
                            </div>
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

<!-- ================= In-system document viewer ================= -->
<div class="modal fade" id="docViewer" tabindex="-1" aria-labelledby="docViewerTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content dv">
            <div class="modal-header dv-head">
                <div class="min-w-0">
                    <h5 class="modal-title mb-0 text-truncate" id="docViewerTitle">Documents</h5>
                    <span class="cell-sub mono" id="docViewerSub"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="dv-tabs" id="docViewerTabs" role="tablist"></div>
            <div class="modal-body p-0 dv-body">
                <div class="dv-empty" id="docViewerEmpty"><i class="fa-regular fa-file"></i><span>This document has not been uploaded yet.</span></div>
                <iframe id="docViewerFrame" title="Document preview"></iframe>
            </div>
            <div class="modal-footer dv-foot">
                <a href="#" id="docViewerReview" class="btn btn-gold btn-sm2"><i class="fa-solid fa-file-circle-check"></i> Review &amp; approve</a>
                <a href="#" id="docViewerFull" class="btn btn-ghost btn-sm2" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i> Full screen</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var modalEl = document.getElementById('docViewer');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);
    var tabs = document.getElementById('docViewerTabs'), frame = document.getElementById('docViewerFrame'), empty = document.getElementById('docViewerEmpty');
    var full = document.getElementById('docViewerFull');
    var LABEL = {missing: 'Not uploaded', submitted: 'For review', approved: 'Approved', rejected: 'Returned'};
    var TONE  = {missing: 'muted', submitted: 'info', approved: 'success', rejected: 'danger'};

    function show(doc) {
        [].forEach.call(tabs.children, function (b) { b.classList.toggle('active', b.dataset.key === doc.key); });
        if (doc.url) { frame.style.display = 'block'; empty.style.display = 'none'; frame.src = doc.url; full.style.display = ''; full.href = doc.url; }
        else { frame.removeAttribute('src'); frame.style.display = 'none'; empty.style.display = 'flex'; full.style.display = 'none'; }
    }

    document.querySelectorAll('.js-viewdocs').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var holder = btn.closest('tr').querySelector('[data-student]');
            var data = JSON.parse(holder.dataset.student);
            document.getElementById('docViewerTitle').textContent = data.name;
            document.getElementById('docViewerSub').textContent = data.sr;
            document.getElementById('docViewerReview').href = data.review;
            tabs.innerHTML = '';
            data.docs.forEach(function (d) {
                var b = document.createElement('button');
                b.type = 'button'; b.dataset.key = d.key; b.className = 'dv-tab';
                b.innerHTML = '<span>' + d.label + '</span><span class="doc-chip chip-' + TONE[d.status] + '">' + LABEL[d.status] + '</span>';
                b.addEventListener('click', function () { show(d); });
                tabs.appendChild(b);
            });
            var start = btn.dataset.start;
            var first = data.docs.filter(function (d) { return start ? d.key === start : d.url; })[0] || data.docs[0];
            show(first);
            modal.show();
        });
    });
    modalEl.addEventListener('hidden.bs.modal', function () { frame.removeAttribute('src'); });
})();
</script>
@endpush

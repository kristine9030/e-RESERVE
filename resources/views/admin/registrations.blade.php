@extends('layouts.app')
@section('title', ['student' => 'Students', 'employee' => 'Instructors & staff', 'sponsor' => 'Sponsors', 'all' => 'All records'][request('type', 'all')] ?? (config('ereserve.employee_types')[request('type')] ?? 'All records'))
@section('crumb', 'Records')
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

/** The documents one person must upload, shaped for the viewer modal. */
$viewerData = static function (array $r): string {
    $out = [];
    foreach (['waiver' => 'Waiver', 'cor' => 'COR', 'id' => 'School ID', 'employee_id' => 'Employee ID', 'sponsorship' => 'Proof of sponsorship'] as $k => $label) {
        $d = $r['docs'][$k];
        if (!$d['applicable']) {
            continue;
        }
        $out[] = ['key' => $k, 'label' => $label, 'status' => $d['status'] ?: 'missing', 'url' => $d['id'] ? document_url($d['id']) : null];
    }

    return json_encode(['name' => $r['name'], 'sr' => $r['student_no'], 'review' => route('admin.documents.student', $r['id']), 'docs' => $out]);
};
@endphp
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-users"></i> Records</span>
            <h2>{{ ['student' => 'Students', 'employee' => 'Instructors & staff', 'sponsor' => 'Sponsors'][$filters['type'] ?? ''] ?? (config('ereserve.employee_types')[$filters['type'] ?? ''] ?? 'All records') }}</h2>
            <p>Every account, with the status of its documents and ticket.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <?php
                $exQ     = array_filter(array_intersect_key($filters, array_flip(\App\Services\RecordsExport::FILTERS)), fn ($v) => (string) $v !== '');
                $feedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('records.feed', now()->addHours(2), $exQ);
            ?>
            <div class="dropdown d-inline-block">
                <button class="btn btn-gold" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down ms-1" style="font-size:.65rem;"></i></button>
                <ul class="dropdown-menu dropdown-menu-end app-drop export-menu shadow border-0 mt-2">
                    <li class="export-note">Exports what you see now: {{ $filters['type'] === 'all' ? 'all records' : strtolower(['student' => 'Students', 'employee' => 'Instructors & staff', 'sponsor' => 'Sponsors'][$filters['type']] ?? 'this list') }}{{ array_diff_key($exQ, ['type' => 1]) ? ' (filtered)' : '' }}</li>
                    <li><a class="dropdown-item app-drop-item" href="{{ route('admin.records.export', ['format' => 'xlsx'] + $exQ) }}"><i class="fa-solid fa-file-excel" style="color:#1E9A5A;"></i><span>Excel <small>.xlsx</small></span></a></li>
                    <li><a class="dropdown-item app-drop-item" href="{{ route('admin.records.export', ['format' => 'pdf'] + $exQ) }}"><i class="fa-solid fa-file-pdf" style="color:#D13B3B;"></i><span>PDF <small>A4 landscape</small></span></a></li>
                    <li><a class="dropdown-item app-drop-item" href="{{ route('admin.records.export', ['format' => 'csv'] + $exQ) }}"><i class="fa-solid fa-file-csv" style="color:#2A8DB5;"></i><span>CSV <small>any spreadsheet</small></span></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" class="dropdown-item app-drop-item" data-bs-toggle="modal" data-bs-target="#gsheetModal"><i class="fa-solid fa-table" style="color:#1E9A5A;"></i><span>Google Sheets <small>live link</small></span></button></li>
                </ul>
            </div>
            <button class="btn btn-outline-light2 ms-2" onclick="window.print()" title="Print"><i class="fa-solid fa-print"></i></button>
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

<div class="card-soft stu-card fade-up d3">
    <div class="card-head">
        <div>
            <h5 class="mb-0">{{ ['student' => 'Students', 'employee' => 'Instructors & staff', 'sponsor' => 'Sponsors'][$filters['type'] ?? ''] ?? (config('ereserve.employee_types')[$filters['type'] ?? ''] ?? 'All records') }}{{ !empty($filters['program']) ? ' · ' . $filters['program'] : '' }}</h5>
            <span class="cell-sub">{{ number_format($rows->total()) }} account{{ $rows->total() === 1 ? '' : 's' }}{{ array_filter(array_diff_key($filters, ['type' => 1])) ? ' match your filters' : '' }}</span>
        </div>
        <div class="d-flex gap-2 align-items-center no-print flex-wrap stu-legend">
            <span class="cell-sub me-1">Documents:</span>
            <span class="doc-chip chip-success">Approved</span>
            <span class="doc-chip chip-info">For review</span>
            <span class="doc-chip chip-danger">Returned</span>
            <span class="doc-chip chip-muted">Missing</span>
        </div>
    </div>

    <?php
        $curType = $filters['type'] ?? 'all';
        $isStu   = $curType === 'student';                                              // college + program filters
        $isEmp   = $curType === 'employee' || array_key_exists($curType, config('ereserve.employee_types'));
        $colHead = $isStu ? 'Program' : ($isEmp ? 'Category' : 'Program / category');
    ?>
    <form method="get" class="stu-filter no-print" id="stuFilter">
        @unless ($isEmp)<input type="hidden" name="type" value="{{ $curType }}">@endunless
        <div class="stu-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Search name, {{ $isStu ? 'SR-code' : ($isEmp ? 'employee ID' : 'ID') }} or email">
        </div>
        @if ($isStu)
        <select name="college" class="form-select">
            <option value="">All colleges</option>
            @foreach (array_keys(config('ereserve.colleges')) as $code)
                <option value="{{ $code }}" @selected(($filters['college'] ?? '') === $code)>{{ $code }}</option>
            @endforeach
        </select>
        <select name="program" class="form-select" id="sfProgram">
            <option value="">All programs</option>
            @foreach (config('ereserve.programs') as $pc => $plist)
                <optgroup label="{{ $pc }}" data-college="{{ $pc }}">
                    @foreach ($plist as $prog)
                        <option value="{{ $prog }}" @selected(($filters['program'] ?? '') === $prog)>{{ $prog }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @endif
        @if ($isEmp)
        <select name="type" class="form-select">
            <option value="employee" @selected($curType === 'employee')>All categories</option>
            @foreach (config('ereserve.employee_types') as $k => $label)
                <option value="{{ $k }}" @selected($curType === $k)>{{ $label }}</option>
            @endforeach
        </select>
        @endif
        <select name="docs" class="form-select">
            <option value="">All document states</option>
            @foreach (['pending' => 'Has file for review', 'returned' => 'Has returned file', 'approved' => 'All approved', 'incomplete' => 'Still uploading'] as $k => $label)
                <option value="{{ $k }}" @selected(($filters['docs'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="ticket" class="form-select">
            <option value="">Any ticket</option>
            @foreach (['held' => 'Has a ticket', 'issued' => 'Ticket issued', 'used' => 'Checked in', 'none' => 'No ticket'] as $k => $label)
                <option value="{{ $k }}" @selected(($filters['ticket'] ?? '') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="stu-filter-actions">
            <button class="btn btn-green btn-sm2" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
            @if (array_filter(array_diff_key($filters, ['type' => 1])))
                <a class="btn btn-ghost btn-sm2" href="{{ route('admin.registrations', ['type' => $curType]) }}">Clear</a>
            @endif
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-stack stu-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Student</th>
                    @if ($curType !== 'sponsor')<th>{{ $colHead }}</th>@endif
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
                            <div class="cell-strong">{{ $r['name'] }}@if ($isStudent && $curType === 'all') <span class="badge-soft badge-info ms-1">Student</span>@endif @if (!$isStudent) <span class="badge-soft badge-gold ms-1">{{ $r['member_type'] === 'sponsor' ? 'Sponsor' : (config('ereserve.employee_types')[$r['member_type']] ?? 'Employee') }}</span>@endif @if ($off)<span class="badge-soft badge-muted ms-1">Disabled</span>@endif</div>
                            <div class="cell-sub mono">{{ $r['student_no'] }}</div>
                            <div class="cell-sub stu-email" title="{{ $r['email'] }}">{{ $r['email'] }}</div>
                        </td>
                        @if ($curType !== 'sponsor')
                        <td data-label="{{ $colHead }}" {{ $r['program'] ? '' : 'data-empty' }}>
                            <div>
                                <div class="stu-prog">{{ $r['program'] }}</div>
                                <div class="cell-sub">{{ $r['year'] }}</div>
                            </div>
                        </td>
                        @endif
                        <td data-label="Documents" data-order="{{ $r['doc_count'] }}">
                            <div class="d-flex gap-1 flex-wrap">
                                @foreach (['waiver' => 'Waiver', 'cor' => 'COR', 'id' => 'ID', 'employee_id' => 'Employee ID', 'sponsorship' => 'Proof'] as $k => $short)
                                    @if ($r['docs'][$k]['applicable']){!! $chip($short, $r['docs'][$k], $k) !!}@endif
                                @endforeach
                            </div>
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
                                @if (true)
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

<!-- ================= Google Sheets ================= -->
<div class="modal fade" id="gsheetModal" tabindex="-1" aria-labelledby="gsheetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content col-modal">
            <div class="modal-header">
                <div class="min-w-0"><h5 class="modal-title" id="gsheetTitle"><i class="fa-solid fa-table me-2" style="color:#1E9A5A;"></i>Open in Google Sheets</h5><div class="cell-sub">These records, as a live sheet</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body gs-steps">
                <?php $local = in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1'], true); ?>
                @if ($local)
                    <div class="gs-warn"><i class="fa-solid fa-triangle-exclamation"></i><span>This site is running on <b>localhost</b>, so Google cannot reach the link below. Use the <b>Excel</b> or <b>CSV</b> download, then in Google Sheets choose <b>File → Import → Upload</b>. The live link works once e-RESERVE is online.</span></div>
                @endif
                <div class="gs-step"><span>1</span><div>Open a blank sheet. <a class="btn btn-ghost btn-sm2 ms-2" href="https://sheets.new" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> New Google Sheet</a></div></div>
                <div class="gs-step"><span>2</span><div>Paste this into cell <b>A1</b>, then press Enter:
                    <div class="gs-formula">
                        <input type="text" id="gsFormula" readonly value='=IMPORTDATA("{{ $feedUrl }}")'>
                        <button type="button" class="btn btn-green btn-sm2" id="gsCopy"><i class="fa-regular fa-copy"></i> Copy</button>
                    </div>
                </div></div>
                <div class="gs-note"><i class="fa-solid fa-lock"></i><span>This link expires in <b>2 hours</b> and anyone who has it can read these records, so do not share it. Generate a new one by reloading this page.</span></div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-ghost btn-sm2" href="{{ route('admin.records.export', ['format' => 'csv'] + $exQ) }}"><i class="fa-solid fa-file-csv"></i> Download CSV instead</a>
                <button type="button" class="btn btn-gold btn-sm2" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
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
        var btn = document.getElementById('gsCopy'), box = document.getElementById('gsFormula');
        if (!btn || !box) return;
        btn.addEventListener('click', function () {
            box.select();
            var done = function () { btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied'; setTimeout(function () { btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy'; }, 1800); };
            if (navigator.clipboard) { navigator.clipboard.writeText(box.value).then(done, function () { document.execCommand('copy'); done(); }); }
            else { document.execCommand('copy'); done(); }
        });
    })();
</script>
<script>
    (function () {
        var form = document.getElementById('stuFilter');
        if (!form) return;
        var college = form.querySelector('[name="college"]'), program = document.getElementById('sfProgram');
        if (!college || !program) return;

        function limitPrograms() {
            var c = college.value;
            program.querySelectorAll('optgroup').forEach(function (g) {
                var show = !c || g.dataset.college === c;
                g.hidden = !show; g.disabled = !show;
            });
            var cur = program.selectedOptions[0];
            if (cur && cur.parentElement.tagName === 'OPTGROUP' && cur.parentElement.disabled) program.value = '';
        }
        college.addEventListener('change', function () { limitPrograms(); program.value = program.value; });
        limitPrograms();

    })();
</script>
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

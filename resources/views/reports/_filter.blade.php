<?php $curType = $filters['type'] ?? ''; ?>
<div class="card-soft mb-4 no-print fade-up d1">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Choose who to include</h5>
            <span class="cell-sub">Showing: <strong>{{ $rpt->scopeText($filters) }}</strong> · {{ number_format(count($ds['rows'])) }} record{{ count($ds['rows']) === 1 ? '' : 's' }}. Print and export follow this selection.</span>
        </div>
    </div>
    <form method="get" class="rpt-filter" id="rptFilter">
        <select name="type" class="form-select" id="rfType" aria-label="Group">
            <option value="">All registrants</option>
            <option value="student" @selected($curType === 'student')>Students</option>
            <option value="employee" @selected($curType === 'employee')>All faculty, instructors &amp; staff</option>
            @foreach (config('ereserve.employee_types') as $k => $label)
                <option value="{{ $k }}" @selected($curType === $k)>{{ $label }} only</option>
            @endforeach
            <option value="sponsor" @selected($curType === 'sponsor')>Sponsors</option>
        </select>
        <select name="college" class="form-select" id="rfCollege" data-stu aria-label="College">
            <option value="">All colleges</option>
            @foreach (array_keys(config('ereserve.colleges')) as $code)
                <option value="{{ $code }}" @selected(($filters['college'] ?? '') === $code)>{{ $code }}</option>
            @endforeach
        </select>
        <select name="program" class="form-select" id="rfProgram" data-stu aria-label="Program">
            <option value="">All programs</option>
            @foreach (config('ereserve.programs') as $pc => $plist)
                <optgroup label="{{ $pc }}" data-college="{{ $pc }}">
                    @foreach ($plist as $prog)
                        <option value="{{ $prog }}" @selected(($filters['program'] ?? '') === $prog)>{{ $prog }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <select name="year" class="form-select" id="rfYear" data-stu aria-label="Year level">
            <option value="">All year levels</option>
            @foreach (config('ereserve.year_levels') as $y)
                <option value="{{ $y }}" @selected(($filters['year'] ?? '') === $y)>{{ $y }}</option>
            @endforeach
        </select>
        <div class="d-flex gap-2">
            <button class="btn btn-green btn-sm2" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
            @if ($filters)<a class="btn btn-ghost btn-sm2" href="{{ route('reports.show', $ds['key']) }}">Clear</a>@endif
        </div>
    </form>
</div>
<style>
    .rpt-filter { display: grid; grid-template-columns: minmax(180px, 1.2fr) repeat(3, minmax(120px, 1fr)) auto; gap: .65rem; padding: 0 1.5rem 1.15rem; align-items: center; }
    .rpt-filter [data-stu][hidden] { display: none; }
    @media (max-width: 991.98px) { .rpt-filter { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 575.98px) { .rpt-filter { grid-template-columns: 1fr; padding: 0 1rem 1rem; } }
</style>
<script>
(function () {
    var f = document.getElementById('rptFilter'); if (!f) return;
    var type = document.getElementById('rfType'), col = document.getElementById('rfCollege'),
        prog = document.getElementById('rfProgram'), year = document.getElementById('rfYear');
    function sync() {
        var stu = type.value === 'student';
        [col, prog, year].forEach(function (el) { el.hidden = !stu; el.disabled = !stu; });
        prog.querySelectorAll('optgroup').forEach(function (g) {
            var show = !col.value || g.dataset.college === col.value; g.hidden = !show; g.disabled = !show;
        });
        var cur = prog.selectedOptions[0];
        if (cur && cur.parentElement.tagName === 'OPTGROUP' && cur.parentElement.disabled) prog.value = '';
    }
    type.addEventListener('change', function () { sync(); f.submit(); });
    col.addEventListener('change', sync);
    sync();
})();
</script>

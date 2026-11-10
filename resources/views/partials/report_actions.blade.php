<div class="d-flex flex-wrap gap-2 justify-content-lg-end no-print">
    <a href="{{ route('reports.export', ['report' => $ds['key'], 'format' => 'sheet'] + ($filters ?? [])) }}" class="btn btn-gold btn-sm2">
        <i class="fa-solid fa-file-excel"></i> Export Sheet
    </a>
    <a href="{{ route('reports.export', ['report' => $ds['key'], 'format' => 'pdf'] + ($filters ?? [])) }}" class="btn btn-outline-light2 btn-sm2">
        <i class="fa-solid fa-file-pdf"></i> PDF
    </a>
    <a href="{{ route('reports.export', ['report' => $ds['key'], 'format' => 'csv'] + ($filters ?? [])) }}" class="btn btn-outline-light2 btn-sm2">
        <i class="fa-solid fa-file-csv"></i> CSV
    </a>
    @php $liveUrl = \Illuminate\Support\Facades\URL::signedRoute('reports.live', ['report' => $ds['key']] + ($filters ?? [])); @endphp
    <button type="button" class="btn btn-outline-light2 btn-sm2" data-live-sheet="=IMPORTDATA(&quot;{{ $liveUrl }}&quot;)"
            title="Copy a formula that keeps a Google Sheet up to date with this report">
        <i class="fa-brands fa-google-drive"></i> Google Sheets link
    </button>
    <button class="btn btn-outline-light2 btn-sm2" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print
    </button>
</div>

<div class="print-tip no-print mt-3" style="text-align:left;">
    <i class="fa-solid fa-circle-info"></i>
    <div>
        <strong>Printing this form.</strong> It is laid out for <strong>A4 landscape</strong> with a
        0.5 in margin on every side, and the browser picks that up automatically. Prefer a file?
        <strong>PDF</strong> and <strong>Export Sheet</strong> give the same form. Tick <strong>Background graphics</strong> so the
        column band prints, and untick <strong>Headers and footers</strong> to keep the page URL
        and date off the paper.
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-live-sheet]'); if (!b) return;
    var text = b.getAttribute('data-live-sheet');
    var done = function () {
        var old = b.innerHTML; b.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
        setTimeout(function () { b.innerHTML = old; }, 2200);
        if (window.Swal) Swal.fire({ icon: 'success', title: 'Formula copied', html: 'Open a Google Sheet, click cell <b>A1</b> and paste. It refreshes about every hour and follows the choices you made here.<br><small>Anyone with this link can read the report, so share it carefully. It only works once e-RESERVE is online.</small>', confirmButtonText: 'Got it' });
    };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, function () { prompt('Copy this formula:', text); }); }
    else { prompt('Copy this formula:', text); }
});
</script>

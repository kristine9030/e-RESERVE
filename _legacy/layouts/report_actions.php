<?php
/**
 * layouts/report_actions.php — export toolbar shared by every report page.
 * Expects: $ds (from report_dataset())
 */
?>
<div class="d-flex flex-wrap gap-2 justify-content-lg-end no-print">
    <a href="<?= url('reports/export.php?report=' . $ds['key'] . '&format=sheet') ?>" class="btn btn-gold btn-sm2">
        <i class="fa-solid fa-file-excel"></i> Export Sheet
    </a>
    <a href="<?= url('reports/export.php?report=' . $ds['key'] . '&format=csv') ?>" class="btn btn-outline-light2 btn-sm2">
        <i class="fa-solid fa-file-csv"></i> CSV
    </a>
    <button class="btn btn-outline-light2 btn-sm2" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print
    </button>
</div>

<div class="print-tip no-print mt-3" style="text-align:left;">
    <i class="fa-solid fa-circle-info"></i>
    <div>
        <strong>Printing this form.</strong> It is laid out for <strong>A4 landscape</strong> —
        the browser picks that up automatically. Tick <strong>Background graphics</strong> so the
        column band prints, and untick <strong>Headers and footers</strong> to keep the page URL
        and date off the paper.
    </div>
</div>

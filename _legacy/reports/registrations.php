<?php
/**
 * reports/registrations.php — Registration Report (Report 01).
 *
 * Rows, totals and layout come from report_dataset('registrations') so the
 * screen, the printed form and the spreadsheet export are one document.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$ds   = report_dataset('registrations');
$regs = registrations();

/* Analysis panels — screen only, they do not belong on the signed form. */
$byDoc = [];
foreach (DOCUMENT_TYPES as $key => $meta) {
    $byDoc[$key] = ['label' => $meta['label'], 'approved' => 0, 'submitted' => 0, 'rejected' => 0, 'missing' => 0];
}
foreach ($regs as $r) {
    foreach ($byDoc as $key => $_) {
        $status = $r['docs'][$key]['status'] ?: 'missing';
        $byDoc[$key][$status]++;
    }
}

$byYear = [];
foreach ($regs as $r) {
    $y = $r['year'] ?: 'Unspecified';
    $byYear[$y] = ($byYear[$y] ?? 0) + 1;
}
ksort($byYear);

$pageTitle = 'Registration Report';
$crumb     = 'Reports';
$activeNav = 'report_registrations';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up no-print">
    <div class="row align-items-center g-3">
        <div class="col-lg-7">
            <span class="eyebrow on-dark"><i class="fa-solid fa-clipboard-list"></i> Report 01 · <?= e($ds['code']) ?></span>
            <h2>Registration Report</h2>
            <p><?= e($ds['subtitle']) ?>.</p>
        </div>
        <div class="col-lg-5">
            <?php require __DIR__ . '/../layouts/report_actions.php'; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4 no-print">
    <?php foreach ($ds['summary'] as $i => [$label, $value, $type]):
        $tone = [0 => 'deep', 1 => 'green', 2 => 'orange', 3 => 'gold'][$i] ?? 'deep';
        $icon = [0 => 'fa-users', 1 => 'fa-circle-check', 2 => 'fa-hourglass-half', 3 => 'fa-chart-simple'][$i] ?? 'fa-users';
    ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= e($label) ?></div>
                <div class="stat-value" style="font-size:1.35rem;"><?= e(report_format($type, $value)) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4 no-print">
    <div class="col-lg-7">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head"><h5 class="mb-0">Documents by Status</h5></div>
            <div class="card-body-p">
                <?php foreach ($byDoc as $d):
                    $total = max(1, array_sum([$d['approved'], $d['submitted'], $d['rejected'], $d['missing']])); ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n"><?= e($d['label']) ?></span>
                            <span class="v"><?= $d['approved'] + $d['submitted'] ?> / <?= $total ?> uploaded</span>
                        </div>
                        <div class="prog-track">
                            <div class="prog-fill" data-width="<?= round(($d['approved'] + $d['submitted']) / $total * 100) ?>" style="width:0;"></div>
                        </div>
                        <div class="cell-sub mt-1">
                            <?= $d['approved'] ?> approved · <?= $d['submitted'] ?> for review ·
                            <?= $d['rejected'] ?> returned · <?= $d['missing'] ?> missing
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">Registrations by Year Level</h5></div>
            <div class="card-body-p">
                <?php foreach ($byYear as $year => $n): ?>
                    <div class="receipt-row">
                        <span class="k"><?= e($year) ?></span>
                        <span class="v"><?= number_format($n) ?> student<?= $n === 1 ? '' : 's' ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byYear): ?>
                    <p class="cell-sub mb-0">No students have registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/report_paper.php'; ?>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

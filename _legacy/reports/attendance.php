<?php
/**
 * reports/attendance.php — Attendance Report (Report 03).
 *
 * Rows, totals and layout come from report_dataset('attendance') so the
 * screen, the printed form and the spreadsheet export are one document.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$ds      = report_dataset('attendance');
$log     = attendance_log();
$present = array_filter($log, static fn($r) => $r['checked_in']);

/* Analysis panels — screen only, they do not belong on the signed form. */
$byGate = [];
foreach ($log as $r) {
    $g = $r['gate'];
    if (!isset($byGate[$g])) {
        $byGate[$g] = ['expected' => 0, 'present' => 0];
    }
    $byGate[$g]['expected']++;
    if ($r['checked_in']) {
        $byGate[$g]['present']++;
    }
}
ksort($byGate);

$byProgram = [];
foreach ($log as $r) {
    $p = $r['program'];
    if (!isset($byProgram[$p])) {
        $byProgram[$p] = ['expected' => 0, 'present' => 0];
    }
    $byProgram[$p]['expected']++;
    if ($r['checked_in']) {
        $byProgram[$p]['present']++;
    }
}
uasort($byProgram, static fn($a, $b) => $b['expected'] <=> $a['expected']);

/* Hourly arrival curve — how the gate load spread across the night. */
$byHour = [];
foreach ($present as $r) {
    if (!$r['time_in']) {
        continue;
    }
    $h = date('H', strtotime($r['time_in']));
    $byHour[$h] = ($byHour[$h] ?? 0) + 1;
}
ksort($byHour);

$pageTitle = 'Attendance Report';
$crumb     = 'Reports';
$activeNav = 'report_attendance';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up no-print">
    <div class="row align-items-center g-3">
        <div class="col-lg-7">
            <span class="eyebrow on-dark"><i class="fa-solid fa-user-check"></i> Report 03 · <?= e($ds['code']) ?></span>
            <h2>Attendance Report</h2>
            <p><?= e($ds['subtitle']) ?>.</p>
        </div>
        <div class="col-lg-5">
            <?php require __DIR__ . '/../layouts/report_actions.php'; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4 no-print">
    <?php foreach ($ds['summary'] as $i => [$label, $value, $type]):
        $tone = [0 => 'gold', 1 => 'green', 2 => 'danger', 3 => 'info'][$i] ?? 'deep';
        $icon = [0 => 'fa-ticket', 1 => 'fa-user-check', 2 => 'fa-user-xmark', 3 => 'fa-chart-simple'][$i] ?? 'fa-ticket';
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
    <div class="col-lg-4">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head"><h5 class="mb-0">Check-in by Gate</h5></div>
            <div class="card-body-p">
                <?php foreach ($byGate as $gate => $g):
                    $pct = $g['expected'] ? round($g['present'] / $g['expected'] * 100) : 0; ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n"><?= e($gate) ?></span>
                            <span class="v"><?= $g['present'] ?> / <?= $g['expected'] ?></span>
                        </div>
                        <div class="prog-track"><div class="prog-fill" data-width="<?= $pct ?>" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byGate): ?>
                    <p class="cell-sub mb-0">No gates assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">Check-in by Program</h5></div>
            <div class="card-body-p">
                <?php foreach ($byProgram as $prog => $p):
                    $pct = $p['expected'] ? round($p['present'] / $p['expected'] * 100) : 0; ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n text-truncate" style="max-width:68%;"><?= e($prog) ?></span>
                            <span class="v"><?= $p['present'] ?> / <?= $p['expected'] ?></span>
                        </div>
                        <div class="prog-track"><div class="prog-fill gold" data-width="<?= $pct ?>" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byProgram): ?>
                    <p class="cell-sub mb-0">No check-ins recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft h-100 fade-up d4">
            <div class="card-head"><h5 class="mb-0">Arrival Curve</h5></div>
            <div class="card-body-p">
                <?php $maxHour = ($byHour ? max($byHour) : 0) ?: 1; ?>
                <?php foreach ($byHour as $hour => $n): ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n"><?= date('g:00 A', strtotime($hour . ':00')) ?></span>
                            <span class="v"><?= $n ?> scanned</span>
                        </div>
                        <div class="prog-track"><div class="prog-fill" data-width="<?= round($n / $maxHour * 100) ?>" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byHour): ?>
                    <p class="cell-sub mb-0">The gate has not opened yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/report_paper.php'; ?>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

<?php
/**
 * reports/attendance.php — Attendance / gate check-in report.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$log       = attendance_log();
$present   = array_filter($log, static fn($r) => $r['checked_in']);
$absent    = count($log) - count($present);
$rate      = count($log) > 0 ? round(count($present) / count($log) * 100, 1) : 0;

/* Per-gate totals. */
$byGate = [];
foreach ($present as $r) {
    $byGate[$r['gate']] = ($byGate[$r['gate']] ?? 0) + 1;
}
ksort($byGate);

/* Per-program totals. */
$byProgram = [];
foreach ($present as $r) {
    $byProgram[$r['program']] = ($byProgram[$r['program']] ?? 0) + 1;
}
arsort($byProgram);

$pageTitle = 'Attendance Report';
$crumb     = 'Reports';
$activeNav = 'report_attendance';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up no-print">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-user-check"></i> Report 03</span>
            <h2>Attendance Report</h2>
            <p>Gate check-in record for <?= EVENT_NAME ?> · <?= EVENT_DATE_TEXT ?>.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-gold" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            <button class="btn btn-outline-light2 ms-2" data-soon="Export Attendance Report"><i class="fa-solid fa-file-excel"></i></button>
        </div>
    </div>
</div>

<div class="print-only mb-4">
    <h3 style="margin-bottom:.2rem;">Attendance Report</h3>
    <p style="margin:0;font-size:.85rem;"><?= EVENT_NAME ?> · <?= EVENT_VENUE ?></p>
    <p style="margin:0;font-size:.8rem;color:#666;">Generated <?= date('F d, Y · g:i A') ?></p>
    <hr>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-ticket',      'gold',  'Tickets Issued', count($log)],
        ['fa-user-check',  'green', 'Checked In',     count($present)],
        ['fa-user-xmark',  'danger','No Show',        $absent],
        ['fa-chart-simple','info',  'Attendance Rate', $rate],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value"><?= $label === 'Attendance Rate' ? $val . '%' : $val ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head"><h5 class="mb-0">Attendance Rate</h5></div>
            <div class="card-body-p">
                <div class="donut" style="background:conic-gradient(
                        var(--green-700) 0% <?= $rate ?>%,
                        var(--green-050) <?= $rate ?>% 100%);">
                    <div class="donut-center">
                        <div class="n"><?= $rate ?>%</div>
                        <div class="l">Present</div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--green-700);"></span>
                        <span class="flex-grow-1">Checked in</span><strong><?= count($present) ?></strong>
                    </div>
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--green-050);"></span>
                        <span class="flex-grow-1">No show</span><strong><?= $absent ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">By Gate</h5></div>
            <div class="card-body-p">
                <?php $maxGate = $byGate ? max($byGate) : 1; foreach ($byGate as $gate => $n): ?>
                    <div class="prog-row">
                        <div class="prog-top"><span class="n"><?= e($gate) ?></span><span class="v"><?= $n ?></span></div>
                        <div class="prog-track"><div class="prog-fill" data-width="<?= round($n / $maxGate * 100) ?>" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byGate): ?>
                    <p class="cell-sub mb-0">No check-ins recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft h-100 fade-up d4">
            <div class="card-head"><h5 class="mb-0">By Program</h5></div>
            <div class="card-body-p">
                <?php $maxProg = $byProgram ? max($byProgram) : 1; foreach ($byProgram as $prog => $n): ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n text-truncate" style="max-width:70%;"><?= e($prog) ?></span>
                            <span class="v"><?= $n ?></span>
                        </div>
                        <div class="prog-track"><div class="prog-fill gold" data-width="<?= round($n / $maxProg * 100) ?>" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byProgram): ?>
                    <p class="cell-sub mb-0">No check-ins recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card-soft fade-up d5">
    <div class="card-head">
        <h5 class="mb-0">Check-in Log</h5>
        <span class="cell-sub"><?= count($log) ?> ticket holders</span>
    </div>
    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="25" style="width:100%;">
            <thead>
                <tr>
                    <th>#</th><th>Ticket No.</th><th>Student</th><th>Program</th>
                    <th>Gate</th><th>Time In</th><th>Scanner</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log as $i => $r): ?>
                    <tr>
                        <td class="cell-sub"><?= $i + 1 ?></td>
                        <td><span class="mono cell-strong"><?= e($r['ticket_no']) ?></span></td>
                        <td><div class="cell-strong"><?= e($r['name']) ?></div><div class="cell-sub"><?= e($r['student_no']) ?></div></td>
                        <td><?= e($r['program']) ?></td>
                        <td><span class="badge-soft badge-<?= $r['checked_in'] ? 'info' : 'muted' ?>"><?= e($r['gate']) ?></span></td>
                        <td class="cell-sub"><?= $r['time_in'] ? pretty_date($r['time_in'], 'M d · g:i A') : '—' ?></td>
                        <td class="cell-sub"><?= e($r['scanner']) ?></td>
                        <td><?= $r['checked_in'] ? status_badge('checked') : '<span class="badge-soft badge-muted">Not Yet</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row mt-5 print-only">
    <div class="col-6">
        <p style="margin-bottom:3rem;font-size:.8rem;">Prepared by:</p>
        <div style="border-top:1px solid #333;width:220px;padding-top:.3rem;font-size:.78rem;">Gate Marshal</div>
    </div>
    <div class="col-6">
        <p style="margin-bottom:3rem;font-size:.8rem;">Certified correct:</p>
        <div style="border-top:1px solid #333;width:220px;padding-top:.3rem;font-size:.78rem;">SSC President</div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

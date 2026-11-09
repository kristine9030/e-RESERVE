<?php
/**
 * reports/tickets.php — Ticket Issuance Report (Report 02).
 *
 * Rows, totals and layout come from report_dataset('tickets') so the screen,
 * the printed form and the spreadsheet export are one document.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$ds      = report_dataset('tickets');
$tickets = issued_tickets();

/* Analysis panels — screen only, they do not belong on the signed form. */
$byDay = [];
foreach ($tickets as $t) {
    $d = date('Y-m-d', strtotime($t['issued_at']));
    $byDay[$d] = ($byDay[$d] ?? 0) + 1;
}
krsort($byDay);

$byStatus = [];
foreach ($tickets as $t) {
    $byStatus[$t['status']] = ($byStatus[$t['status']] ?? 0) + 1;
}

$pageTitle = 'Ticket Issuance Report';
$crumb     = 'Reports';
$activeNav = 'report_tickets';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up no-print">
    <div class="row align-items-center g-3">
        <div class="col-lg-7">
            <span class="eyebrow on-dark"><i class="fa-solid fa-ticket"></i> Report 02 · <?= e($ds['code']) ?></span>
            <h2>Ticket Issuance Report</h2>
            <p><?= e($ds['subtitle']) ?>.</p>
        </div>
        <div class="col-lg-5">
            <?php require __DIR__ . '/../layouts/report_actions.php'; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4 no-print">
    <?php foreach ($ds['summary'] as $i => [$label, $value, $type]):
        $tone = [0 => 'gold', 1 => 'green', 2 => 'info', 3 => 'danger'][$i] ?? 'deep';
        $icon = [0 => 'fa-ticket', 1 => 'fa-circle-check', 2 => 'fa-user-check', 3 => 'fa-ban'][$i] ?? 'fa-ticket';
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
            <div class="card-head">
                <h5 class="mb-0">Tickets Issued by Date</h5>
                <span class="cell-sub"><?= count($byDay) ?> days</span>
            </div>
            <div class="card-body-p" style="max-height:430px;overflow-y:auto;">
                <?php $maxDay = ($byDay ? max($byDay) : 0) ?: 1; ?>
                <?php foreach ($byDay as $day => $n): ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n"><?= pretty_date($day, 'M d, Y') ?></span>
                            <span class="v"><?= $n ?> ticket<?= $n === 1 ? '' : 's' ?></span>
                        </div>
                        <div class="prog-track">
                            <div class="prog-fill gold" data-width="<?= round($n / $maxDay * 100) ?>" style="width:0;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$byDay): ?>
                    <p class="cell-sub mb-0">No tickets issued yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">By Status</h5></div>
            <div class="card-body-p">
                <?php foreach ($byStatus as $status => $n): ?>
                    <div class="receipt-row">
                        <span class="k"><?= status_badge($status) ?></span>
                        <span class="v"><?= number_format($n) ?> ticket<?= $n === 1 ? '' : 's' ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="receipt-row">
                    <span class="k">Seats remaining</span>
                    <span class="v"><?= number_format(slots_remaining()) ?> of <?= number_format(EVENT_CAPACITY) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/report_paper.php'; ?>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

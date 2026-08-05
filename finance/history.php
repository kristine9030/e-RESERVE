<?php
/**
 * finance/history.php — Payment history grouped by day.
 * REPLACE LATER: SELECT DATE(paid_at) d, COUNT(*), SUM(amount) ... GROUP BY d
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$payments = sample_payments();

/* Group by calendar day. */
$byDay = [];
foreach ($payments as $p) {
    $day = date('Y-m-d', strtotime($p['paid_at']));
    if (!isset($byDay[$day])) {
        $byDay[$day] = ['day' => $day, 'count' => 0, 'amount' => 0.0, 'rows' => []];
    }
    $byDay[$day]['count']++;
    $byDay[$day]['amount'] += $p['amount'];
    $byDay[$day]['rows'][]  = $p;
}
krsort($byDay);

$pageTitle = 'Payment History';
$crumb     = 'Finance';
$activeNav = 'history';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-clock-rotate-left"></i> Archive</span>
            <h2>Payment History</h2>
            <p>Daily collection summary with a drill-down per transaction.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-gold" data-soon="Export Payment History"><i class="fa-solid fa-file-export"></i> Export</button>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- Daily summary -->
    <div class="col-xl-4">
        <div class="card-soft fade-up d1">
            <div class="card-head"><h5 class="mb-0">Daily Collections</h5></div>
            <div class="card-body-p">
                <?php
                $maxDay = max(array_column($byDay, 'amount')) ?: 1;
                foreach ($byDay as $d): ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n"><?= pretty_date($d['day'], 'M d, Y') ?></span>
                            <span class="v"><?= peso($d['amount']) ?></span>
                        </div>
                        <div class="prog-track">
                            <div class="prog-fill gold" data-width="<?= round($d['amount'] / $maxDay * 100) ?>" style="width:0;"></div>
                        </div>
                        <div class="cell-sub mt-1"><?= $d['count'] ?> transaction<?= $d['count'] > 1 ? 's' : '' ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Full history -->
    <div class="col-xl-8">
        <div class="card-soft fade-up d2">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">All Payments</h5>
                    <span class="cell-sub"><?= count($payments) ?> settled transactions</span>
                </div>
                <button class="btn btn-ghost btn-sm2 no-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            </div>

            <div class="table-responsive">
                <table class="table js-datatable" data-page-length="15" data-order-col="4" data-order-dir="desc" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Receipt No.</th><th>Payer</th><th>Reference</th>
                            <th>Amount</th><th>Paid At</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><span class="mono cell-strong"><?= e($p['receipt_no']) ?></span></td>
                                <td>
                                    <div class="cell-strong"><?= e($p['name']) ?></div>
                                    <div class="cell-sub"><?= e($p['student_no']) ?></div>
                                </td>
                                <td><span class="mono"><?= e($p['reference']) ?></span></td>
                                <td class="fw-800"><?= peso($p['amount']) ?></td>
                                <td class="cell-sub"><?= pretty_date($p['paid_at']) ?></td>
                                <td><?= status_badge($p['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

<?php
/**
 * finance/index.php — Finance overview.
 * REPLACE LATER: SUM()/GROUP BY queries over tbl_payments.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$stats    = dashboard_stats();
$payments = sample_payments();
$ledger   = finance_ledger();
$trend    = sales_trend();
$maxTrend = max(array_column($trend, 'count')) ?: 1;

$gross    = array_sum(array_column($payments, 'amount'));
$fees     = array_sum(array_column($payments, 'fee'));
$net      = $gross - $fees;
$refunds  = array_sum(array_column(array_filter($ledger, static fn($l) => $l['status'] === 'refunded'), 'debit'));

$pageTitle = 'Finance Overview';
$crumb     = 'Finance';
$activeNav = 'finance';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-chart-pie"></i> Treasury</span>
            <h2>Finance Overview</h2>
            <p>Ticket sales, gateway fees and settlements for <?= EVENT_NAME ?>.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-gold" data-soon="Export Financial Summary"><i class="fa-solid fa-file-export"></i> Export</button>
            <button class="btn btn-outline-light2 ms-2" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
        </div>
    </div>
</div>

<div class="row g-3 g-lg-4 mb-4">
    <?php
    $cards = [
        ['fa-sack-dollar', 'gold',   'Gross Revenue',   $gross,  '₱', 'All settled ticket sales'],
        ['fa-percent',     'orange', 'Gateway Fees',    $fees,   '₱', 'PayMongo 2.5% per transaction'],
        ['fa-vault',       'green',  'Net Proceeds',    $net,    '₱', 'Payable to the SSC account'],
        ['fa-rotate-left', 'danger', 'Refunds Issued',  $refunds,'₱', 'Adjustments and reversals'],
    ];
    foreach ($cards as $i => [$icon, $tone, $label, $val, $prefix, $foot]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value" data-count-to="<?= $val ?>" data-count-prefix="<?= $prefix ?>" data-count-decimals="2">0</div>
                <div class="stat-foot"><?= $foot ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Daily Collections</h5>
                    <span class="cell-sub">Transactions per day · last 14 days</span>
                </div>
                <span class="badge-soft badge-success"><i class="fa-solid fa-arrow-trend-up"></i> Healthy</span>
            </div>
            <div class="card-body-p">
                <div class="mini-chart">
                    <?php foreach ($trend as $i => $t): ?>
                        <div class="mc-col" data-bs-toggle="tooltip"
                             title="<?= e($t['label']) ?>: <?= peso($t['count'] * average_ticket_price()) ?> (est.)">
                            <div class="mc-bar" style="height:<?= round($t['count'] / $maxTrend * 100) ?>%;animation-delay:<?= $i * 45 ?>ms;"></div>
                            <div class="mc-label"><?= e($t['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">Revenue Split</h5></div>
            <div class="card-body-p">
                <?php $netPct = $gross > 0 ? round($net / $gross * 100, 1) : 0; ?>
                <div class="donut" style="background:conic-gradient(
                        var(--green-700) 0% <?= $netPct ?>%,
                        var(--gold-500) <?= $netPct ?>% 100%);">
                    <div class="donut-center">
                        <div class="n"><?= $netPct ?>%</div>
                        <div class="l">Net</div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--green-700);"></span>
                        <span class="flex-grow-1">Net proceeds</span><strong><?= peso($net) ?></strong>
                    </div>
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--gold-500);"></span>
                        <span class="flex-grow-1">Gateway fees</span><strong><?= peso($fees) ?></strong>
                    </div>
                </div>

                <hr style="border-color:var(--line);">
                <div class="receipt-row"><span class="k">Payment channel</span><span class="v">GCash</span></div>
                <div class="receipt-row"><span class="k">Settlement cycle</span><span class="v">T+2 banking days</span></div>
                <div class="receipt-row" style="border-bottom:0;"><span class="k">Currency</span><span class="v"><?= PAYMENT_CURRENCY ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- ================= Ledger preview ================= -->
<div class="card-soft fade-up d4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">General Ledger</h5>
            <span class="cell-sub">Collections, settlements and adjustments</span>
        </div>
        <a href="<?= url('finance/transactions.php') ?>" class="btn btn-ghost btn-sm2">All transactions <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="10" style="width:100%;">
            <thead>
                <tr>
                    <th>Date</th><th>Reference</th><th>Particulars</th>
                    <th>Method</th><th>Debit</th><th>Credit</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($ledger, 0, 15) as $l): ?>
                    <tr>
                        <td class="cell-sub"><?= pretty_date($l['date'], 'M d, Y') ?></td>
                        <td><span class="mono cell-strong"><?= e($l['reference']) ?></span></td>
                        <td>
                            <div><?= e($l['particular']) ?></div>
                            <div class="cell-sub"><?= e($l['payer']) ?></div>
                        </td>
                        <td><?= e($l['method']) ?></td>
                        <td class="fw-800 <?= $l['debit'] > 0 ? 'text-danger' : 'text-muted2' ?>">
                            <?= $l['debit'] > 0 ? peso($l['debit']) : '—' ?>
                        </td>
                        <td class="fw-800 <?= $l['credit'] > 0 ? 'text-green' : 'text-muted2' ?>">
                            <?= $l['credit'] > 0 ? peso($l['credit']) : '—' ?>
                        </td>
                        <td><?= status_badge($l['status'] === 'cleared' ? 'paid' : $l['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

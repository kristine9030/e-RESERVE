<?php
/**
 * reports/revenue.php — Revenue report.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$payments = sample_payments();
$programs = program_breakdown();
$trend    = sales_trend();
$maxTrend = max(array_column($trend, 'count')) ?: 1;

$gross    = array_sum(array_column($payments, 'amount'));
$fees     = array_sum(array_column($payments, 'fee'));
$net      = $gross - $fees;
$target   = 350000.00;                       // mock revenue target for the event
$attain   = $target > 0 ? round($gross / $target * 100, 1) : 0;

/* Income-statement lines that sit on top of ticket sales. */
$refunds     = tier_price('SVIP');           // the single refund in the ledger
$sponsorship = 75000.00;
$totalIncome = $net - $refunds + $sponsorship;
$avgPrice    = average_ticket_price();

/* Simple expense sheet so the report reads like a real financial summary. */
$expenses = [
    ['Production and stage design',   68000.00],
    ['Guest artist talent fee',       55000.00],
    ['Sound and lighting rental',     42000.00],
    ['Security and marshals',         18500.00],
    ['Printing and marketing',        12400.00],
    ['Miscellaneous / contingency',    9600.00],
];
$totalExpense = array_sum(array_column($expenses, 1));

$pageTitle = 'Revenue Report';
$crumb     = 'Reports';
$activeNav = 'report_revenue';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up no-print">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-sack-dollar"></i> Report 04</span>
            <h2>Revenue Report</h2>
            <p>Ticket income, gateway costs and projected surplus for <?= EVENT_NAME ?>.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-gold" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            <button class="btn btn-outline-light2 ms-2" data-soon="Export Revenue Report"><i class="fa-solid fa-file-excel"></i></button>
        </div>
    </div>
</div>

<div class="print-only mb-4">
    <h3 style="margin-bottom:.2rem;">Revenue Report</h3>
    <p style="margin:0;font-size:.85rem;"><?= EVENT_NAME ?> · <?= EVENT_HOST ?></p>
    <p style="margin:0;font-size:.8rem;color:#666;">Generated <?= date('F d, Y · g:i A') ?></p>
    <hr>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-sack-dollar',   'gold',   'Gross Revenue', $gross],
        ['fa-percent',       'orange', 'Gateway Fees',  $fees],
        ['fa-vault',         'green',  'Net Revenue',   $net],
        ['fa-scale-balanced','deep',   'Net of Expenses', $net - $totalExpense],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value" style="<?= $val < 0 ? 'color:var(--danger);' : '' ?>"><?= peso($val) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Revenue Trend</h5>
                    <span class="cell-sub">Estimated daily collections</span>
                </div>
            </div>
            <div class="card-body-p">
                <div class="mini-chart">
                    <?php foreach ($trend as $i => $t): ?>
                        <div class="mc-col" data-bs-toggle="tooltip" title="<?= e($t['label']) ?>: <?= peso($t['count'] * $avgPrice) ?> (est.)">
                            <div class="mc-bar" style="height:<?= round($t['count'] / $maxTrend * 100) ?>%;animation-delay:<?= $i * 45 ?>ms;"></div>
                            <div class="mc-label"><?= e($t['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr style="border-color:var(--line);">

                <div class="prog-row">
                    <div class="prog-top">
                        <span class="n">Target attainment · <?= peso($target) ?></span>
                        <span class="v"><?= $attain ?>%</span>
                    </div>
                    <div class="prog-track"><div class="prog-fill gold" data-width="<?= min(100, $attain) ?>" style="width:0;"></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">Revenue by Program</h5></div>
            <div class="card-body-p">
                <?php
                $maxRev = max(array_column($programs, 'revenue')) ?: 1;
                foreach ($programs as $p): ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n text-truncate" style="max-width:62%;"><?= e($p['program']) ?></span>
                            <span class="v"><?= peso($p['revenue']) ?></span>
                        </div>
                        <div class="prog-track"><div class="prog-fill" data-width="<?= round($p['revenue'] / $maxRev * 100) ?>" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Income statement -->
    <div class="col-lg-6">
        <div class="card-soft h-100 fade-up d4">
            <div class="card-head"><h5 class="mb-0">Statement of Income</h5></div>
            <div class="card-body-p">
                <?php foreach (tier_breakdown() as $t): ?>
                    <div class="receipt-row">
                        <span class="k"><?= e($t['name']) ?> ticket sales (<?= number_format($t['sold']) ?> × <?= peso((float) $t['price']) ?>)</span>
                        <span class="v"><?= peso($t['revenue']) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="receipt-row"><span class="k">Less: PayMongo gateway fees (2.5%)</span><span class="v text-danger">(<?= peso($fees) ?>)</span></div>
                <div class="receipt-row"><span class="k">Less: Refunds and adjustments</span><span class="v text-danger">(<?= peso($refunds) ?>)</span></div>
                <div class="receipt-row"><span class="k">Add: Sponsorship contributions</span><span class="v text-green"><?= peso($sponsorship) ?></span></div>

                <div class="receipt-total">
                    <div>
                        <div class="info-label">Total Income</div>
                        <div class="cell-sub">Before event expenses</div>
                    </div>
                    <div class="amt"><?= peso($totalIncome) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expenses -->
    <div class="col-lg-6">
        <div class="card-soft h-100 fade-up d5">
            <div class="card-head"><h5 class="mb-0">Statement of Expenses</h5></div>
            <div class="card-body-p">
                <?php foreach ($expenses as [$label, $amount]): ?>
                    <div class="receipt-row"><span class="k"><?= e($label) ?></span><span class="v"><?= peso($amount) ?></span></div>
                <?php endforeach; ?>

                <div class="receipt-total" style="background:rgba(217,72,59,.06);border-color:rgba(217,72,59,.2);">
                    <div>
                        <div class="info-label">Total Expenses</div>
                        <div class="cell-sub">Projected, as approved by the SSC</div>
                    </div>
                    <div class="amt" style="color:var(--danger);"><?= peso($totalExpense) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-soft mt-4 fade-up d5">
    <div class="card-head"><h5 class="mb-0">Summary</h5></div>
    <div class="card-body-p">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="info-label">Total Income</div>
                <div class="fw-900 text-deep" style="font-size:1.5rem;letter-spacing:-1px;"><?= peso($totalIncome) ?></div>
            </div>
            <div class="col-md-4">
                <div class="info-label">Total Expenses</div>
                <div class="fw-900" style="font-size:1.5rem;letter-spacing:-1px;color:var(--danger);"><?= peso($totalExpense) ?></div>
            </div>
            <div class="col-md-4">
                <?php $surplus = $totalIncome - $totalExpense; ?>
                <div class="info-label"><?= $surplus >= 0 ? 'Projected Surplus' : 'Projected Deficit' ?></div>
                <div class="fw-900" style="font-size:1.5rem;letter-spacing:-1px;color:<?= $surplus >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                    <?= peso(abs($surplus)) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5 print-only">
    <div class="col-6">
        <p style="margin-bottom:3rem;font-size:.8rem;">Prepared by:</p>
        <div style="border-top:1px solid #333;width:220px;padding-top:.3rem;font-size:.78rem;">SSC Treasurer</div>
    </div>
    <div class="col-6">
        <p style="margin-bottom:3rem;font-size:.8rem;">Approved by:</p>
        <div style="border-top:1px solid #333;width:220px;padding-top:.3rem;font-size:.78rem;">Director for Student Affairs</div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

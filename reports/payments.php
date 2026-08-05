<?php
/**
 * reports/payments.php — Payment report.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$payments = sample_payments();
$gross    = array_sum(array_column($payments, 'amount'));
$fees     = array_sum(array_column($payments, 'fee'));

/* Group by day for the summary table. */
$byDay = [];
foreach ($payments as $p) {
    $d = date('Y-m-d', strtotime($p['paid_at']));
    $byDay[$d] = ($byDay[$d] ?? 0) + $p['amount'];
}
krsort($byDay);

$pageTitle = 'Payment Report';
$crumb     = 'Reports';
$activeNav = 'report_payments';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up no-print">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-file-invoice-dollar"></i> Report 02</span>
            <h2>Payment Report</h2>
            <p>All settled GCash transactions processed through PayMongo.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-gold" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            <button class="btn btn-outline-light2 ms-2" data-soon="Export Payment Report"><i class="fa-solid fa-file-excel"></i></button>
        </div>
    </div>
</div>

<div class="print-only mb-4">
    <h3 style="margin-bottom:.2rem;">Payment Report</h3>
    <p style="margin:0;font-size:.85rem;"><?= EVENT_NAME ?> · <?= EVENT_HOST ?></p>
    <p style="margin:0;font-size:.8rem;color:#666;">Generated <?= date('F d, Y · g:i A') ?></p>
    <hr>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-receipt',      'green',  'Transactions',  count($payments), ''],
        ['fa-sack-dollar',  'gold',   'Gross Collected', $gross, '₱'],
        ['fa-percent',      'orange', 'Gateway Fees',  $fees, '₱'],
        ['fa-vault',        'deep',   'Net Proceeds',  $gross - $fees, '₱'],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val, $prefix]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value" data-count-to="<?= $val ?>" data-count-prefix="<?= $prefix ?>"
                     data-count-decimals="<?= $prefix === '₱' ? 2 : 0 ?>"><?= $prefix ? peso($val) : $val ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head"><h5 class="mb-0">Collections by Date</h5></div>
            <div class="card-body-p">
                <?php $maxDay = max($byDay) ?: 1; foreach ($byDay as $day => $amt): ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n"><?= pretty_date($day, 'M d, Y') ?></span>
                            <span class="v"><?= peso($amt) ?></span>
                        </div>
                        <div class="prog-track"><div class="prog-fill gold" data-width="<?= round($amt / $maxDay * 100) ?>" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">Payment Channel Summary</h5></div>
            <div class="card-body-p">
                <div class="table-responsive">
                    <table class="table mb-0" style="width:100%;font-size:.86rem;">
                        <thead>
                            <tr style="background:var(--green-050);">
                                <th style="border:0;padding:.85rem 1rem;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);">Channel</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);" class="text-center">Count</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);" class="text-end">Amount</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);" class="text-end">Fees</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding:.75rem 1rem;border-bottom:1px solid var(--line);">
                                    <i class="fa-solid fa-mobile-screen text-gold me-2"></i>GCash <span class="cell-sub">(PayMongo)</span>
                                </td>
                                <td class="text-center fw-800" style="border-bottom:1px solid var(--line);"><?= count($payments) ?></td>
                                <td class="text-end fw-800" style="border-bottom:1px solid var(--line);"><?= peso($gross) ?></td>
                                <td class="text-end" style="border-bottom:1px solid var(--line);"><?= peso($fees) ?></td>
                            </tr>
                            <?php foreach ([['Maya', 'fa-wallet'], ['GrabPay', 'fa-car'], ['Card / Bank', 'fa-credit-card']] as [$ch, $ic]): ?>
                                <tr>
                                    <td style="padding:.75rem 1rem;border-bottom:1px solid var(--line);" class="text-muted2">
                                        <i class="fa-solid <?= $ic ?> me-2"></i><?= $ch ?> <span class="cell-sub">(not yet enabled)</span>
                                    </td>
                                    <td class="text-center text-muted2" style="border-bottom:1px solid var(--line);">0</td>
                                    <td class="text-end text-muted2" style="border-bottom:1px solid var(--line);">—</td>
                                    <td class="text-end text-muted2" style="border-bottom:1px solid var(--line);">—</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--green-050);">
                                <th style="padding:.85rem 1rem;">TOTAL</th>
                                <th class="text-center"><?= count($payments) ?></th>
                                <th class="text-end"><?= peso($gross) ?></th>
                                <th class="text-end"><?= peso($fees) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-soft fade-up d4">
    <div class="card-head">
        <h5 class="mb-0">Detailed Transactions</h5>
        <span class="cell-sub"><?= count($payments) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="25" style="width:100%;">
            <thead>
                <tr>
                    <th>#</th><th>Receipt No.</th><th>PayMongo Ref.</th><th>Payer</th>
                    <th>Method</th><th>Amount</th><th>Fee</th><th>Paid At</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $i => $p): ?>
                    <tr>
                        <td class="cell-sub"><?= $i + 1 ?></td>
                        <td><span class="mono cell-strong"><?= e($p['receipt_no']) ?></span></td>
                        <td><span class="mono"><?= e($p['reference']) ?></span></td>
                        <td><div class="cell-strong"><?= e($p['name']) ?></div><div class="cell-sub"><?= e($p['student_no']) ?></div></td>
                        <td><?= e($p['method']) ?></td>
                        <td class="fw-800"><?= peso($p['amount']) ?></td>
                        <td class="cell-sub"><?= peso($p['fee']) ?></td>
                        <td class="cell-sub"><?= pretty_date($p['paid_at']) ?></td>
                        <td><?= status_badge($p['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row mt-5 print-only">
    <div class="col-6">
        <p style="margin-bottom:3rem;font-size:.8rem;">Prepared by:</p>
        <div style="border-top:1px solid #333;width:220px;padding-top:.3rem;font-size:.78rem;">SSC Treasurer</div>
    </div>
    <div class="col-6">
        <p style="margin-bottom:3rem;font-size:.8rem;">Audited by:</p>
        <div style="border-top:1px solid #333;width:220px;padding-top:.3rem;font-size:.78rem;">SSC Auditor</div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

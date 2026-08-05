<?php
/**
 * admin/payments.php — Payment verification table.
 * REPLACE LATER: SELECT * FROM tbl_payments ORDER BY paid_at DESC
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$rows    = sample_payments();
$stats   = dashboard_stats();
$gross   = array_sum(array_column($rows, 'amount'));
$fees    = array_sum(array_column($rows, 'fee'));

$pageTitle = 'Payments';
$crumb     = 'Administration';
$activeNav = 'payments';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-credit-card"></i> PayMongo · GCash</span>
            <h2>Payment Verification</h2>
            <p>Cross-check every settled transaction against its PayMongo reference.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-gold" data-soon="Sync with PayMongo"><i class="fa-solid fa-rotate"></i> Sync Gateway</button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-receipt',     'green',  'Transactions',  count($rows),        ''],
        ['fa-sack-dollar', 'gold',   'Gross Revenue', $gross,              '₱'],
        ['fa-percent',     'orange', 'Gateway Fees',  $fees,               '₱'],
        ['fa-vault',       'deep',   'Net Proceeds',  $gross - $fees,      '₱'],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val, $prefix]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value" data-count-to="<?= $val ?>" data-count-prefix="<?= $prefix ?>"
                     data-count-decimals="<?= $prefix === '₱' ? 2 : 0 ?>">0</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-soft fade-up d3">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Transaction Records</h5>
            <span class="cell-sub">All amounts in <?= PAYMENT_CURRENCY ?></span>
        </div>
        <div class="d-flex gap-2 no-print">
            <button class="btn btn-ghost btn-sm2" data-soon="Export CSV"><i class="fa-solid fa-file-csv"></i> CSV</button>
            <button class="btn btn-ghost btn-sm2" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="15" data-order-col="7" data-order-dir="desc" style="width:100%;">
            <thead>
                <tr>
                    <th>Receipt No.</th>
                    <th>PayMongo Ref.</th>
                    <th>Payer</th>
                    <th>Ticket No.</th>
                    <th>Type</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Paid At</th>
                    <th>Status</th>
                    <th class="no-sort">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $p): ?>
                    <tr>
                        <td><span class="mono cell-strong"><?= e($p['receipt_no']) ?></span></td>
                        <td><span class="mono"><?= e($p['reference']) ?></span>
                            <div class="cell-sub">fee <?= peso($p['fee']) ?></div></td>
                        <td>
                            <div class="cell-strong"><?= e($p['name']) ?></div>
                            <div class="cell-sub"><?= e($p['student_no']) ?></div>
                        </td>
                        <td><span class="mono"><?= e($p['ticket_no']) ?></span></td>
                        <td><?= tier_badge($p['tier'] ?? null) ?></td>
                        <td><i class="fa-solid fa-mobile-screen text-gold me-1"></i><?= e($p['method']) ?></td>
                        <td class="fw-800"><?= peso($p['amount']) ?></td>
                        <td class="cell-sub"><?= pretty_date($p['paid_at']) ?></td>
                        <td><?= status_badge($p['status']) ?></td>
                        <td class="no-print">
                            <button class="icon-btn" style="width:32px;height:32px;border-radius:9px;"
                                    data-soon="Refund Transaction" title="Refund">
                                <i class="fa-solid fa-rotate-left" style="font-size:.72rem;"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

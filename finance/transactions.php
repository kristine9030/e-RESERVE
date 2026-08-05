<?php
/**
 * finance/transactions.php — Full transaction ledger with export buttons (UI only).
 * REPLACE LATER: SELECT * FROM tbl_payments p LEFT JOIN tbl_adjustments a ...
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$ledger  = finance_ledger();
$credits = array_sum(array_column($ledger, 'credit'));
$debits  = array_sum(array_column($ledger, 'debit'));
$fees    = array_sum(array_column($ledger, 'fee'));

$pageTitle = 'Transactions';
$crumb     = 'Finance';
$activeNav = 'transactions';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-7">
            <span class="eyebrow on-dark"><i class="fa-solid fa-arrow-right-arrow-left"></i> Ledger</span>
            <h2>Transactions</h2>
            <p>Every movement of funds recorded by e-RESERVE.</p>
        </div>
        <div class="col-lg-5 text-lg-end">
            <button class="btn btn-gold btn-sm2" data-soon="Export to Excel"><i class="fa-solid fa-file-excel"></i> Excel</button>
            <button class="btn btn-gold btn-sm2 ms-1" data-soon="Export to CSV"><i class="fa-solid fa-file-csv"></i> CSV</button>
            <button class="btn btn-outline-light2 btn-sm2 ms-1" data-soon="Export to PDF"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            <button class="btn btn-outline-light2 btn-sm2 ms-1" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-list',        'deep',   'Entries',       count($ledger), ''],
        ['fa-arrow-down',  'green',  'Total Credits', $credits, '₱'],
        ['fa-arrow-up',    'danger', 'Total Debits',  $debits,  '₱'],
        ['fa-scale-balanced','gold', 'Balance',       $credits - $debits, '₱'],
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
            <h5 class="mb-0">Transaction Ledger</h5>
            <span class="cell-sub">Gateway fees total <?= peso($fees) ?></span>
        </div>
        <span class="badge-soft badge-gold"><?= PAYMENT_CURRENCY ?></span>
    </div>

    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="20" data-order-col="0" data-order-dir="desc" style="width:100%;">
            <thead>
                <tr>
                    <th>Date</th><th>Reference</th><th>Particulars</th><th>Payer / Payee</th>
                    <th>Method</th><th>Debit</th><th>Credit</th><th>Fee</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ledger as $l): ?>
                    <tr>
                        <td class="cell-sub text-nowrap"><?= pretty_date($l['date'], 'M d, Y') ?></td>
                        <td><span class="mono cell-strong"><?= e($l['reference']) ?></span></td>
                        <td><?= e($l['particular']) ?></td>
                        <td><?= e($l['payer']) ?></td>
                        <td class="text-nowrap"><?= e($l['method']) ?></td>
                        <td class="fw-800 <?= $l['debit'] > 0 ? 'text-danger' : 'text-muted2' ?>"><?= $l['debit'] > 0 ? peso($l['debit']) : '—' ?></td>
                        <td class="fw-800 <?= $l['credit'] > 0 ? 'text-green' : 'text-muted2' ?>"><?= $l['credit'] > 0 ? peso($l['credit']) : '—' ?></td>
                        <td class="cell-sub"><?= $l['fee'] > 0 ? peso($l['fee']) : '—' ?></td>
                        <td><?= status_badge($l['status'] === 'cleared' ? 'paid' : $l['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--green-050);">
                    <th colspan="5" style="padding:.9rem 1rem;text-align:right;">Totals</th>
                    <th style="padding:.9rem 1rem;"><?= peso($debits) ?></th>
                    <th style="padding:.9rem 1rem;"><?= peso($credits) ?></th>
                    <th style="padding:.9rem 1rem;"><?= peso($fees) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

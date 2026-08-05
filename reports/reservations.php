<?php
/**
 * reports/reservations.php — Reservations report.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$rows     = sample_reservations();
$stats    = dashboard_stats();
$programs = program_breakdown();

/* Group by year level. */
$byYear = [];
foreach ($rows as $r) {
    $byYear[$r['year']] = ($byYear[$r['year']] ?? 0) + 1;
}
ksort($byYear);

$pageTitle = 'Reservations Report';
$crumb     = 'Reports';
$activeNav = 'report_reservations';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up no-print">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-file-lines"></i> Report 01</span>
            <h2>Reservations Report</h2>
            <p>Consolidated slot requests for <?= EVENT_NAME ?> as of <?= date('F d, Y') ?>.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-gold" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            <button class="btn btn-outline-light2 ms-2" data-soon="Export Reservations Report"><i class="fa-solid fa-file-excel"></i></button>
        </div>
    </div>
</div>

<!-- Print letterhead -->
<div class="print-only mb-4">
    <h3 style="margin-bottom:.2rem;">Reservations Report</h3>
    <p style="margin:0;font-size:.85rem;"><?= EVENT_NAME ?> · <?= EVENT_HOST ?></p>
    <p style="margin:0;font-size:.8rem;color:#666;">Generated <?= date('F d, Y · g:i A') ?></p>
    <hr>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-list-check',   'deep',   'Total Reservations', $stats['total_reservations']],
        ['fa-circle-check', 'green',  'Confirmed / Paid',   $stats['paid_tickets']],
        ['fa-hourglass',    'orange', 'Pending Payment',    $stats['pending_payments']],
        ['fa-circle-xmark', 'danger', 'Cancelled',          $stats['cancelled']],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value" data-count-to="<?= $val ?>"><?= $val ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head"><h5 class="mb-0">Breakdown by Program</h5></div>
            <div class="card-body-p">
                <div class="table-responsive">
                    <table class="table mb-0" style="width:100%;font-size:.86rem;">
                        <thead>
                            <tr style="background:var(--green-050);">
                                <th style="border:0;padding:.85rem 1rem;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);">Program</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);" class="text-center">Total</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);" class="text-center">Paid</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);" class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programs as $p): ?>
                                <tr>
                                    <td style="padding:.75rem 1rem;border-bottom:1px solid var(--line);"><?= e($p['program']) ?></td>
                                    <td class="text-center fw-800" style="border-bottom:1px solid var(--line);"><?= $p['total'] ?></td>
                                    <td class="text-center" style="border-bottom:1px solid var(--line);"><?= $p['paid'] ?></td>
                                    <td class="text-end fw-800" style="border-bottom:1px solid var(--line);"><?= peso($p['revenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--green-050);">
                                <th style="padding:.85rem 1rem;">TOTAL</th>
                                <th class="text-center"><?= array_sum(array_column($programs, 'total')) ?></th>
                                <th class="text-center"><?= array_sum(array_column($programs, 'paid')) ?></th>
                                <th class="text-end"><?= peso(array_sum(array_column($programs, 'revenue'))) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">By Year Level</h5></div>
            <div class="card-body-p">
                <?php $maxYear = max($byYear) ?: 1; foreach ($byYear as $year => $count): ?>
                    <div class="prog-row">
                        <div class="prog-top"><span class="n"><?= e($year) ?></span><span class="v"><?= $count ?></span></div>
                        <div class="prog-track"><div class="prog-fill" data-width="<?= round($count / $maxYear * 100) ?>" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>

                <hr style="border-color:var(--line);">
                <div class="receipt-row"><span class="k">Venue capacity</span><span class="v"><?= number_format(EVENT_CAPACITY) ?></span></div>
                <div class="receipt-row"><span class="k">Fill rate</span><span class="v"><?= $stats['fill_rate'] ?>%</span></div>
                <div class="receipt-row" style="border-bottom:0;"><span class="k">Slots remaining</span><span class="v"><?= number_format(EVENT_CAPACITY - $stats['paid_tickets']) ?></span></div>
            </div>
        </div>
    </div>
</div>

<div class="card-soft fade-up d4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Detailed Reservation List</h5>
            <span class="cell-sub"><?= count($rows) ?> records</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="25" style="width:100%;">
            <thead>
                <tr>
                    <th>#</th><th>Reference</th><th>Student</th><th>Program</th>
                    <th>Year</th><th>Amount</th><th>Reserved</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td class="cell-sub"><?= $i + 1 ?></td>
                        <td><span class="mono cell-strong"><?= e($r['code']) ?></span></td>
                        <td><div class="cell-strong"><?= e($r['name']) ?></div><div class="cell-sub"><?= e($r['student_no']) ?></div></td>
                        <td><?= e($r['program']) ?></td>
                        <td><?= e($r['year']) ?></td>
                        <td class="fw-800"><?= peso($r['amount']) ?></td>
                        <td class="cell-sub"><?= pretty_date($r['created_at'], 'M d, Y') ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row mt-5 print-only">
    <div class="col-6">
        <p style="margin-bottom:3rem;font-size:.8rem;">Prepared by:</p>
        <div style="border-top:1px solid #333;width:220px;padding-top:.3rem;font-size:.78rem;">SSC Secretary</div>
    </div>
    <div class="col-6">
        <p style="margin-bottom:3rem;font-size:.8rem;">Noted by:</p>
        <div style="border-top:1px solid #333;width:220px;padding-top:.3rem;font-size:.78rem;">SSC President</div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

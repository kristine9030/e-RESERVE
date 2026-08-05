<?php
/**
 * admin/reservations.php — Full reservations table.
 * REPLACE LATER: SELECT r.*, s.* FROM tbl_reservations r JOIN tbl_students s ...
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$rows  = sample_reservations();
$stats = dashboard_stats();

$pageTitle = 'Reservations';
$crumb     = 'Administration';
$activeNav = 'reservations';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-calendar-check"></i> Records</span>
            <h2>Reservation Management</h2>
            <p>Every slot request for <?= EVENT_NAME ?>, live from the reservation store.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-gold" data-soon="Export to Excel"><i class="fa-solid fa-file-excel"></i> Export</button>
            <button class="btn btn-outline-light2 ms-2" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-list-check',     'deep',   'Total',     $stats['total_reservations']],
        ['fa-circle-check',   'green',  'Paid',      $stats['paid_tickets']],
        ['fa-hourglass-half', 'orange', 'Pending',   $stats['pending_payments']],
        ['fa-circle-xmark',   'danger', 'Cancelled', $stats['cancelled']],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value" data-count-to="<?= $val ?>">0</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-soft fade-up d3">
    <div class="card-head">
        <div>
            <h5 class="mb-0">All Reservations</h5>
            <span class="cell-sub"><?= count($rows) ?> records</span>
        </div>
        <div class="d-flex gap-2 no-print">
            <button class="btn btn-ghost btn-sm2" data-soon="Bulk Actions"><i class="fa-solid fa-layer-group"></i> Bulk</button>
            <button class="btn btn-ghost btn-sm2" data-soon="Filter Panel"><i class="fa-solid fa-filter"></i> Filter</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="15" data-order-col="6" data-order-dir="desc" style="width:100%;">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Ticket No.</th>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Qty</th>
                    <th>Amount</th>
                    <th>Reserved</th>
                    <th>Status</th>
                    <th class="no-sort">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><span class="mono cell-strong"><?= e($r['code']) ?></span></td>
                        <td><span class="mono"><?= e($r['ticket_no']) ?></span></td>
                        <td>
                            <div class="cell-strong"><?= e($r['name']) ?></div>
                            <div class="cell-sub"><?= e($r['student_no']) ?></div>
                        </td>
                        <td><?= e($r['program']) ?><div class="cell-sub"><?= e($r['year']) ?></div></td>
                        <td class="text-center"><?= (int) $r['quantity'] ?></td>
                        <td class="fw-800"><?= peso($r['amount']) ?></td>
                        <td class="cell-sub"><?= pretty_date($r['created_at']) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="no-print">
                            <button class="icon-btn js-view" style="width:32px;height:32px;border-radius:9px;"
                                    data-json='<?= e(json_encode($r)) ?>' title="View">
                                <i class="fa-solid fa-eye" style="font-size:.75rem;"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

<script>
    /* Row detail viewer — reads the JSON payload rendered per row. */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-view');
        if (!btn) return;
        var r = JSON.parse(btn.dataset.json);

        var row = function (k, v) {
            return '<div style="display:flex;justify-content:space-between;gap:1rem;padding:.5rem 0;border-bottom:1px dashed #E3EDE8;">' +
                   '<span style="color:#4A5F58;">' + k + '</span>' +
                   '<strong style="color:#06251B;text-align:right;">' + (v || '—') + '</strong></div>';
        };

        Swal.fire({
            title: r.code,
            html: '<div style="text-align:left;font-size:.85rem;">' +
                  row('Student', r.name) +
                  row('Student No.', r.student_no) +
                  row('Program', r.program) +
                  row('Year Level', r.year) +
                  row('Ticket No.', r.ticket_no) +
                  row('Quantity', r.quantity) +
                  row('Amount', '₱' + Number(r.amount).toFixed(2)) +
                  row('Status', r.status.toUpperCase()) +
                  row('Reserved At', r.created_at) +
                  row('Paid At', r.paid_at) +
                  row('Checked In', r.checked_in ? 'Yes' : 'No') +
                  '</div>',
            confirmButtonText: 'Close',
            width: 520
        });
    });
</script>

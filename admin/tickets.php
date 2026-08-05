<?php
/**
 * admin/tickets.php — Issued tickets registry.
 * REPLACE LATER: SELECT * FROM tbl_tickets WHERE event_id = ?
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$rows   = sample_tickets();
$valid  = count(array_filter($rows, static fn($t) => !$t['checked_in']));
$used   = count($rows) - $valid;

$pageTitle = 'Tickets';
$crumb     = 'Administration';
$activeNav = 'tickets';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-ticket"></i> Registry</span>
            <h2>Issued Tickets</h2>
            <p>Auto-generated the moment a payment clears. Each ticket admits one student.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-gold" data-soon="Bulk QR Export"><i class="fa-solid fa-qrcode"></i> Export QR Set</button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-ticket',       'gold',  'Tickets Issued', count($rows)],
        ['fa-circle-check', 'green', 'Valid / Unused', $valid],
        ['fa-user-check',   'info',  'Already Used',   $used],
        ['fa-ban',          'danger','Voided',         0],
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
            <h5 class="mb-0">Ticket Records</h5>
            <span class="cell-sub"><?= count($rows) ?> issued for <?= EVENT_NAME ?></span>
        </div>
        <button class="btn btn-ghost btn-sm2 no-print" data-soon="Export to Excel"><i class="fa-solid fa-file-excel"></i> Export</button>
    </div>

    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="15" style="width:100%;">
            <thead>
                <tr>
                    <th>Ticket No.</th>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Seat</th>
                    <th>Issued</th>
                    <th>Status</th>
                    <th class="no-sort">QR</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $t): ?>
                    <tr>
                        <td><span class="mono cell-strong"><?= e($t['ticket_no']) ?></span></td>
                        <td>
                            <div class="cell-strong"><?= e($t['name']) ?></div>
                            <div class="cell-sub"><?= e($t['student_no']) ?></div>
                        </td>
                        <td><?= e($t['program']) ?></td>
                        <td><span class="badge-soft badge-muted"><?= e($t['seat']) ?></span></td>
                        <td class="cell-sub"><?= pretty_date($t['issued_at']) ?></td>
                        <td><?= status_badge($t['status']) ?></td>
                        <td class="no-print">
                            <button class="icon-btn js-qr" style="width:32px;height:32px;border-radius:9px;"
                                    data-ticket="<?= e($t['ticket_no']) ?>"
                                    data-name="<?= e($t['name']) ?>"
                                    data-seat="<?= e($t['seat']) ?>"
                                    data-qr="<?= e(url('admin/qr.php?s=170&t=' . urlencode($t['ticket_no'] . '|' . $t['student_no']))) ?>"
                                    title="Show QR">
                                <i class="fa-solid fa-qrcode" style="font-size:.75rem;"></i>
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
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-qr');
        if (!btn) return;
        Swal.fire({
            title: btn.dataset.ticket,
            html: '<div style="display:flex;justify-content:center;margin:.5rem 0 1rem;">' +
                  '<div style="border:1px solid #E3EDE8;border-radius:14px;padding:.7rem;background:#fff;">' +
                  '<img src="' + btn.dataset.qr + '" width="170" height="170" alt="QR code">' +
                  '</div></div>' +
                  '<div style="font-weight:800;color:#06251B;">' + btn.dataset.name + '</div>' +
                  '<div style="font-size:.8rem;color:#7C8F88;">Seat ' + btn.dataset.seat + ' · Admit one</div>' +
                  '<div style="font-size:.72rem;color:#7C8F88;margin-top:.8rem;">' +
                  'Placeholder QR — swap for a generated code on integration.</div>',
            confirmButtonText: 'Close',
            width: 380
        });
    });
</script>

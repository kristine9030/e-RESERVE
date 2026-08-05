<?php
/**
 * reports/index.php — Reports hub.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$stats = dashboard_stats();

$pageTitle = 'Reports Hub';
$crumb     = 'Reports';
$activeNav = 'reports';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <span class="eyebrow on-dark"><i class="fa-solid fa-folder-open"></i> Documentation</span>
    <h2>Reports</h2>
    <p>Printable summaries for the SSC, the Office of Student Affairs and the campus auditor.</p>
</div>

<div class="row g-4 mb-4">
    <?php
    $reports = [
        ['fa-file-lines',           'Reservations Report', 'Slot requests by status, program and date, with fill-rate analysis.',
         'reports/reservations.php', $stats['total_reservations'] . ' records', 'green'],
        ['fa-file-invoice-dollar',  'Payment Report',      'Settled GCash transactions with PayMongo references and fees.',
         'reports/payments.php', $stats['paid_tickets'] . ' transactions', 'gold'],
        ['fa-user-check',           'Attendance Report',   'Gate check-in log with per-gate and per-program breakdowns.',
         'reports/attendance.php', $stats['checked_in'] . ' checked in', 'info'],
        ['fa-sack-dollar',          'Revenue Report',      'Gross, fees and net proceeds against the event revenue target.',
         'reports/revenue.php', peso($stats['revenue']), 'orange'],
    ];
    foreach ($reports as $i => [$icon, $title, $desc, $href, $meta, $tone]): ?>
        <div class="col-md-6">
            <a href="<?= url($href) ?>" class="card-soft feature-card d-block h-100 fade-up d<?= $i + 1 ?>" style="color:inherit;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="feature-icon"><i class="fa-solid <?= $icon ?>"></i></div>
                    <span class="badge-soft badge-<?= $tone === 'green' ? 'success' : ($tone === 'gold' ? 'gold' : ($tone === 'info' ? 'info' : 'warning')) ?>">
                        <?= e($meta) ?>
                    </span>
                </div>
                <h5><?= $title ?></h5>
                <p><?= $desc ?></p>
                <span class="text-gold fw-800" style="font-size:.82rem;">
                    Open report <i class="fa-solid fa-arrow-right ms-1"></i>
                </span>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-soft fade-up d4">
    <div class="card-head">
        <h5 class="mb-0">Event Snapshot</h5>
        <span class="cell-sub">Generated <?= date('F d, Y · g:i A') ?></span>
    </div>
    <div class="card-body-p">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="receipt-row"><span class="k">Event</span><span class="v"><?= EVENT_NAME ?></span></div>
                <div class="receipt-row"><span class="k">Date &amp; Venue</span><span class="v"><?= EVENT_DATE_TEXT ?> · <?= EVENT_VENUE ?></span></div>
                <div class="receipt-row"><span class="k">Organiser</span><span class="v"><?= EVENT_ORG ?></span></div>
                <div class="receipt-row"><span class="k">Ticket Price</span><span class="v"><?= peso(TICKET_PRICE) ?></span></div>
                <div class="receipt-row"><span class="k">Capacity</span><span class="v"><?= number_format(EVENT_CAPACITY) ?> students</span></div>
            </div>
            <div class="col-md-6">
                <div class="receipt-row"><span class="k">Total Reservations</span><span class="v"><?= number_format($stats['total_reservations']) ?></span></div>
                <div class="receipt-row"><span class="k">Paid Tickets</span><span class="v"><?= number_format($stats['paid_tickets']) ?></span></div>
                <div class="receipt-row"><span class="k">Pending Payments</span><span class="v"><?= number_format($stats['pending_payments']) ?></span></div>
                <div class="receipt-row"><span class="k">Checked-in Students</span><span class="v"><?= number_format($stats['checked_in']) ?></span></div>
                <div class="receipt-row"><span class="k">Gross Revenue</span><span class="v"><?= peso($stats['revenue']) ?></span></div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4 no-print">
            <button class="btn btn-gold" data-soon="Consolidated Report Export"><i class="fa-solid fa-file-export"></i> Export All Reports</button>
            <button class="btn btn-outline-green" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Snapshot</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

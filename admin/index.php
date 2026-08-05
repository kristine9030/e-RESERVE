<?php
/**
 * admin/index.php — Administrator dashboard with sample analytics.
 * REPLACE LATER: each sample_*() call becomes a SQL aggregate query.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$stats        = dashboard_stats();
$reservations = sample_reservations();
$payments     = sample_payments();
$trend        = sales_trend();
$programs     = program_breakdown();
$maxTrend     = max(array_column($trend, 'count')) ?: 1;

$pageTitle = 'Admin Dashboard';
$crumb     = 'Administration';
$activeNav = 'admin';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-chart-line"></i> Live Overview</span>
            <h2><?= EVENT_NAME ?></h2>
            <p><?= EVENT_DATE_TEXT ?> · <?= EVENT_VENUE ?> · Capacity <?= number_format(EVENT_CAPACITY) ?></p>
        </div>
        <div class="col-lg-4">
            <div class="countdown-wrap" data-countdown="<?= EVENT_DATE_ISO ?>">
                <div class="cd-box"><div class="cd-num" data-cd="days">00</div><div class="cd-label">Days</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="hours">00</div><div class="cd-label">Hrs</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="minutes">00</div><div class="cd-label">Min</div></div>
            </div>
        </div>
    </div>
</div>

<!-- ================= Stat cards ================= -->
<div class="row g-3 g-lg-4 mb-4">
    <?php
    $cards = [
        ['fa-calendar-check', 'deep',   'Total Reservations', $stats['total_reservations'], '', '', 'All statuses combined'],
        ['fa-ticket',         'green',  'Paid Tickets',       $stats['paid_tickets'], '', '', 'Issued and valid for entry'],
        ['fa-hourglass-half', 'orange', 'Pending Payments',   $stats['pending_payments'], '', '', 'Slots on hold, unpaid'],
        ['fa-sack-dollar',    'gold',   'Revenue',            $stats['revenue'], '₱', '', 'Gross ticket sales'],
        ['fa-user-check',     'info',   'Checked-in Students',$stats['checked_in'], '', '', 'Scanned at the gate'],
    ];
    foreach ($cards as $i => [$icon, $tone, $label, $value, $prefix, $suffix, $foot]): ?>
        <div class="col-6 col-lg-4 col-xl">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value"
                     data-count-to="<?= $value ?>"
                     data-count-prefix="<?= $prefix ?>"
                     data-count-decimals="<?= $prefix === '₱' ? 2 : 0 ?>">0</div>
                <div class="stat-foot"><?= $foot ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">

    <!-- ================= Sales trend ================= -->
    <div class="col-xl-8">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Reservation Trend</h5>
                    <span class="cell-sub">Daily reservations · last 14 days</span>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge-soft badge-success"><i class="fa-solid fa-arrow-trend-up"></i> +18.4%</span>
                    <button class="btn btn-ghost btn-sm2" data-soon="Export Chart"><i class="fa-solid fa-download"></i></button>
                </div>
            </div>
            <div class="card-body-p">
                <div class="mini-chart">
                    <?php foreach ($trend as $i => $t): ?>
                        <div class="mc-col" data-bs-toggle="tooltip" title="<?= e($t['label']) ?>: <?= $t['count'] ?> reservations">
                            <div class="mc-bar" style="height:<?= round($t['count'] / $maxTrend * 100) ?>%;animation-delay:<?= $i * 45 ?>ms;"></div>
                            <div class="mc-label"><?= e($t['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= Capacity donut ================= -->
    <div class="col-xl-4">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">Venue Capacity</h5></div>
            <div class="card-body-p">
                <?php
                $paidPct    = round($stats['paid_tickets'] / EVENT_CAPACITY * 100, 1);
                $pendingPct = round($stats['pending_payments'] / EVENT_CAPACITY * 100, 1);
                $used       = $paidPct + $pendingPct;
                ?>
                <div class="donut" style="background:conic-gradient(
                        var(--green-700) 0% <?= $paidPct ?>%,
                        var(--gold-500) <?= $paidPct ?>% <?= $used ?>%,
                        var(--green-050) <?= $used ?>% 100%);">
                    <div class="donut-center">
                        <div class="n"><?= $paidPct ?>%</div>
                        <div class="l">Filled</div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--green-700);"></span>
                        <span class="flex-grow-1">Paid tickets</span>
                        <strong><?= number_format($stats['paid_tickets']) ?></strong>
                    </div>
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--gold-500);"></span>
                        <span class="flex-grow-1">Pending payment</span>
                        <strong><?= number_format($stats['pending_payments']) ?></strong>
                    </div>
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--green-050);"></span>
                        <span class="flex-grow-1">Available slots</span>
                        <strong><?= number_format(EVENT_CAPACITY - $stats['paid_tickets'] - $stats['pending_payments']) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- ================= Program breakdown ================= -->
    <div class="col-xl-5">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head">
                <h5 class="mb-0">Reservations by Program</h5>
                <a href="<?= url('reports/reservations.php') ?>" class="text-gold fw-800" style="font-size:.76rem;">Report <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <div class="card-body-p">
                <?php
                $maxProg = max(array_column($programs, 'total')) ?: 1;
                foreach ($programs as $p): ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n text-truncate" style="max-width:70%;"><?= e($p['program']) ?></span>
                            <span class="v"><?= $p['total'] ?></span>
                        </div>
                        <div class="prog-track">
                            <div class="prog-fill" data-width="<?= round($p['total'] / $maxProg * 100) ?>" style="width:0;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ================= Quick admin actions ================= -->
    <div class="col-xl-7">
        <div class="card-soft h-100 fade-up d4">
            <div class="card-head"><h5 class="mb-0">Operations</h5>
                <span class="badge-soft badge-gold"><i class="fa-solid fa-bolt"></i> Shortcuts</span>
            </div>
            <div class="card-body-p">
                <div class="row g-2">
                    <?php
                    $ops = [
                        ['fa-calendar-check', 'Manage Reservations', 'Search, filter and export every slot', 'admin/reservations.php'],
                        ['fa-credit-card',    'Verify Payments',     'Cross-check PayMongo references',      'admin/payments.php'],
                        ['fa-ticket',         'Issued Tickets',      'Validity and seat assignments',        'admin/tickets.php'],
                        ['fa-qrcode',         'Gate Check-in',       'Simulate scanning at the entrance',    'admin/checkin.php'],
                        ['fa-chart-pie',      'Finance Panel',       'Transactions, revenue, payouts',       'finance/index.php'],
                        ['fa-folder-open',    'Reports Hub',         'Four printable report modules',        'reports/index.php'],
                    ];
                    foreach ($ops as [$icon, $title, $sub, $href]): ?>
                        <div class="col-md-6">
                            <a href="<?= url($href) ?>" class="action-tile h-100">
                                <span class="t-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                                <span><span class="t-title d-block"><?= $title ?></span><span class="t-sub"><?= $sub ?></span></span>
                                <i class="fa-solid fa-arrow-right t-arrow"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= Latest reservations ================= -->
<div class="card-soft mb-4 fade-up d4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Latest Reservations</h5>
            <span class="cell-sub">Most recent slot requests</span>
        </div>
        <a href="<?= url('admin/reservations.php') ?>" class="btn btn-ghost btn-sm2">View all <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="8" style="width:100%;">
            <thead>
                <tr>
                    <th>Reference</th><th>Student</th><th>Program</th>
                    <th>Amount</th><th>Status</th><th>Reserved</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($reservations, 0, 12) as $r): ?>
                    <tr>
                        <td><span class="mono cell-strong"><?= e($r['code']) ?></span></td>
                        <td>
                            <div class="cell-strong"><?= e($r['name']) ?></div>
                            <div class="cell-sub"><?= e($r['student_no']) ?></div>
                        </td>
                        <td><?= e($r['program']) ?><div class="cell-sub"><?= e($r['year']) ?></div></td>
                        <td class="fw-800"><?= peso($r['amount']) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="cell-sub"><?= pretty_date($r['created_at'], 'M d, Y') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= Recent payments ================= -->
<div class="card-soft fade-up d5">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Recent Payments</h5>
            <span class="cell-sub">Settled through PayMongo · GCash</span>
        </div>
        <a href="<?= url('admin/payments.php') ?>" class="btn btn-ghost btn-sm2">View all <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="8" style="width:100%;">
            <thead>
                <tr>
                    <th>Receipt No.</th><th>PayMongo Ref.</th><th>Payer</th>
                    <th>Method</th><th>Amount</th><th>Status</th><th>Paid At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($payments, 0, 12) as $p): ?>
                    <tr>
                        <td><span class="mono cell-strong"><?= e($p['receipt_no']) ?></span></td>
                        <td><span class="mono"><?= e($p['reference']) ?></span></td>
                        <td>
                            <div class="cell-strong"><?= e($p['name']) ?></div>
                            <div class="cell-sub"><?= e($p['student_no']) ?></div>
                        </td>
                        <td><i class="fa-solid fa-mobile-screen text-gold me-1"></i><?= e($p['method']) ?></td>
                        <td class="fw-800"><?= peso($p['amount']) ?></td>
                        <td><?= status_badge($p['status']) ?></td>
                        <td class="cell-sub"><?= pretty_date($p['paid_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

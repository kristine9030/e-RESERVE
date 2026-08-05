<?php
/**
 * dashboard.php — Student dashboard.
 * Shows reservation / payment / ticket state and the primary action tiles.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

/* Staff accounts belong in the admin shell. */
if (is_admin()) {
    redirect('admin/index.php');
}

$user   = current_user();
$res    = get_reservation();
$pay    = get_payment();
$ticket = get_ticket();
$paid   = is_paid();

[$stageLabel, $stageTone, $stageIcon] = reservation_stage_label();

$firstName = explode(' ', $user['name'])[0];
$hour      = (int) date('G');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$pageTitle = 'Dashboard';
$crumb     = 'Student Portal';
$activeNav = 'dashboard';
require __DIR__ . '/layouts/app_header.php';
?>

<!-- ================= Welcome hero ================= -->
<div class="page-hero fade-up">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="eyebrow on-dark"><i class="fa-solid fa-hand-sparkles"></i> <?= $greeting ?></span>
            <h2>Welcome back, <?= e($firstName) ?>!</h2>
            <p>
                <?= e($user['program']) ?> · <?= e($user['year']) ?> ·
                Student No. <strong class="text-white"><?= e($user['student_no']) ?></strong>
            </p>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <span class="badge-soft badge-<?= $stageTone === 'muted' ? 'muted' : $stageTone ?>">
                    <i class="fa-solid <?= $stageIcon ?>"></i> <?= e($stageLabel) ?>
                </span>
                <?php if ($res): ?>
                    <span class="badge-soft badge-gold"><i class="fa-solid fa-hashtag"></i> <?= e($res['code']) ?></span>
                <?php endif; ?>
                <?php if ($ticket): ?>
                    <span class="badge-soft badge-success"><i class="fa-solid fa-ticket"></i> <?= e($ticket['ticket_no']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="countdown-wrap" data-countdown="<?= EVENT_DATE_ISO ?>">
                <div class="cd-box"><div class="cd-num" data-cd="days">00</div><div class="cd-label">Days</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="hours">00</div><div class="cd-label">Hours</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="minutes">00</div><div class="cd-label">Mins</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="seconds">00</div><div class="cd-label">Secs</div></div>
            </div>
            <p class="text-center mb-0 mt-2" style="font-size:.74rem;color:rgba(255,255,255,.55);" data-cd-live>
                until <?= EVENT_NAME ?>
            </p>
        </div>
    </div>
</div>

<!-- ================= Status cards ================= -->
<div class="row g-3 g-lg-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card fade-up d1">
            <div class="stat-icon <?= $res ? 'green' : 'deep' ?>"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="stat-label">Reservation Status</div>
            <div class="stat-value" style="font-size:1.35rem;">
                <?= $res ? ($paid ? 'Confirmed' : 'Reserved') : 'Not Reserved' ?>
            </div>
            <div class="stat-foot">
                <?= $res ? 'Ref ' . e($res['code']) . ' · ' . pretty_date($res['created_at'], 'M d, Y') : 'You have not claimed a slot yet.' ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card fade-up d2">
            <div class="stat-icon <?= $paid ? 'green' : ($res ? 'orange' : 'deep') ?>"><i class="fa-solid fa-wallet"></i></div>
            <div class="stat-label">Payment Status</div>
            <div class="stat-value" style="font-size:1.35rem;">
                <?= $paid ? 'Paid' : ($res ? 'Pending' : '—') ?>
            </div>
            <div class="stat-foot">
                <?php if ($paid): ?>
                    <span class="up"><?= peso($pay['amount']) ?></span> via <?= e($pay['method']) ?> · <?= e($pay['reference']) ?>
                <?php elseif ($res): ?>
                    <span class="down"><?= peso(TICKET_PRICE) ?></span> due — settle via GCash
                <?php else: ?>
                    No outstanding balance.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card fade-up d3">
            <div class="stat-icon <?= $ticket ? 'gold' : 'deep' ?>"><i class="fa-solid fa-ticket"></i></div>
            <div class="stat-label">Ticket Status</div>
            <div class="stat-value" style="font-size:1.35rem;">
                <?= $ticket ? 'Issued' : 'Not Issued' ?>
            </div>
            <div class="stat-foot">
                <?= $ticket
                    ? 'No. ' . e($ticket['ticket_no']) . ' · Seat ' . e($ticket['seat'])
                    : 'Generated automatically once payment clears.' ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 g-lg-4">

    <!-- ================= Quick actions ================= -->
    <div class="col-lg-7">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Quick Actions</h5>
                    <span class="cell-sub">Everything you need for SIGLA 2026</span>
                </div>
                <span class="badge-soft badge-gold"><?= APP_TAGLINE ?></span>
            </div>

            <div class="card-body-p d-grid gap-2">

                <!-- Reserve -->
                <?php if (!$res): ?>
                    <a href="<?= url('reserve.php') ?>" class="action-tile primary">
                        <span class="t-icon"><i class="fa-solid fa-calendar-check"></i></span>
                        <span>
                            <span class="t-title d-block">Reserve Ticket</span>
                            <span class="t-sub">Claim your slot — one ticket per student</span>
                        </span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </a>
                <?php else: ?>
                    <button type="button" class="action-tile" id="btnAlreadyReserved">
                        <span class="t-icon"><i class="fa-solid fa-circle-check"></i></span>
                        <span>
                            <span class="t-title d-block">Reserve Ticket</span>
                            <span class="t-sub">Already reserved — <?= e($res['code']) ?></span>
                        </span>
                        <i class="fa-solid fa-lock t-arrow"></i>
                    </button>
                <?php endif; ?>

                <!-- Pay -->
                <?php if ($res && !$paid): ?>
                    <a href="<?= url('payment.php') ?>" class="action-tile primary">
                        <span class="t-icon"><i class="fa-solid fa-mobile-screen"></i></span>
                        <span>
                            <span class="t-title d-block">Pay Now · <?= peso(TICKET_PRICE) ?></span>
                            <span class="t-sub">GCash checkout powered by PayMongo</span>
                        </span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </a>
                <?php else: ?>
                    <button type="button" class="action-tile" disabled>
                        <span class="t-icon"><i class="fa-solid fa-mobile-screen"></i></span>
                        <span>
                            <span class="t-title d-block">Pay Now</span>
                            <span class="t-sub"><?= $paid ? 'Payment already settled' : 'Reserve a ticket first' ?></span>
                        </span>
                        <i class="fa-solid fa-<?= $paid ? 'circle-check' : 'lock' ?> t-arrow"></i>
                    </button>
                <?php endif; ?>

                <!-- View ticket -->
                <?php if ($ticket): ?>
                    <a href="<?= url('ticket.php') ?>" class="action-tile">
                        <span class="t-icon"><i class="fa-solid fa-qrcode"></i></span>
                        <span>
                            <span class="t-title d-block">View Ticket</span>
                            <span class="t-sub"><?= e($ticket['ticket_no']) ?> · QR gate pass</span>
                        </span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </a>
                <?php else: ?>
                    <button type="button" class="action-tile" disabled>
                        <span class="t-icon"><i class="fa-solid fa-qrcode"></i></span>
                        <span>
                            <span class="t-title d-block">View Ticket</span>
                            <span class="t-sub">Issued automatically after payment</span>
                        </span>
                        <i class="fa-solid fa-lock t-arrow"></i>
                    </button>
                <?php endif; ?>

                <!-- Receipt -->
                <?php if ($pay): ?>
                    <a href="<?= url('receipt.php') ?>" class="action-tile">
                        <span class="t-icon"><i class="fa-solid fa-receipt"></i></span>
                        <span>
                            <span class="t-title d-block">Download Receipt</span>
                            <span class="t-sub"><?= e($pay['receipt_no']) ?> · <?= peso($pay['amount']) ?></span>
                        </span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </a>
                <?php else: ?>
                    <button type="button" class="action-tile" disabled>
                        <span class="t-icon"><i class="fa-solid fa-receipt"></i></span>
                        <span>
                            <span class="t-title d-block">Download Receipt</span>
                            <span class="t-sub">Available once payment is confirmed</span>
                        </span>
                        <i class="fa-solid fa-lock t-arrow"></i>
                    </button>
                <?php endif; ?>

                <!-- Tracking -->
                <a href="<?= url('tracking.php') ?>" class="action-tile">
                    <span class="t-icon"><i class="fa-solid fa-route"></i></span>
                    <span>
                        <span class="t-title d-block">Track Reservation</span>
                        <span class="t-sub">Follow your slot from request to gate</span>
                    </span>
                    <i class="fa-solid fa-arrow-right t-arrow"></i>
                </a>

                <!-- Profile -->
                <a href="<?= url('profile.php') ?>" class="action-tile">
                    <span class="t-icon"><i class="fa-solid fa-id-card"></i></span>
                    <span>
                        <span class="t-title d-block">Profile</span>
                        <span class="t-sub">Your student record and contact details</span>
                    </span>
                    <i class="fa-solid fa-arrow-right t-arrow"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- ================= Right rail ================= -->
    <div class="col-lg-5">

        <!-- Mini progress -->
        <div class="card-soft mb-4 fade-up d4">
            <div class="card-head">
                <h6 class="mb-0">Reservation Progress</h6>
                <a href="<?= url('tracking.php') ?>" class="text-gold fw-800" style="font-size:.76rem;">Full timeline <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <div class="card-body-p">
                <?php
                $timeline = tracking_timeline();
                $doneCt   = count(array_filter($timeline, static fn($s) => $s['state'] === 'done'));
                $pct      = (int) round($doneCt / count($timeline) * 100);
                ?>
                <div class="prog-row">
                    <div class="prog-top">
                        <span class="n"><?= $doneCt ?> of <?= count($timeline) ?> stages complete</span>
                        <span class="v"><?= $pct ?>%</span>
                    </div>
                    <div class="prog-track"><div class="prog-fill gold" data-width="<?= $pct ?>" style="width:0;"></div></div>
                </div>

                <ul class="track-line mt-4">
                    <?php foreach (array_slice($timeline, 0, 5) as $s): ?>
                        <li class="track-step <?= $s['state'] ?>" style="padding-bottom:<?= $s['key'] === 'ready' ? '0' : '1.3rem' ?>;">
                            <span class="track-dot" style="width:38px;height:38px;border-radius:12px;font-size:.82rem;">
                                <i class="fa-solid <?= $s['icon'] ?>"></i>
                            </span>
                            <div style="padding-top:.35rem;">
                                <h6 class="mb-0" style="font-size:.86rem;"><?= e($s['label']) ?></h6>
                                <?php if ($s['state'] === 'done' && $s['stamp']): ?>
                                    <div class="track-stamp"><?= pretty_date($s['stamp']) ?></div>
                                <?php elseif ($s['state'] === 'current'): ?>
                                    <div class="track-stamp text-gold">In progress</div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Event card -->
        <div class="card-soft fade-up d5">
            <div class="card-head"><h6 class="mb-0">Event Details</h6>
                <span class="badge-soft badge-success"><i class="fa-solid fa-circle" style="font-size:.4rem;"></i> Confirmed</span>
            </div>
            <div class="card-body-p">
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-star"></i></span>
                    <div><div class="info-label">Event</div><div class="info-value" style="font-size:.85rem;"><?= EVENT_NAME ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-calendar-day"></i></span>
                    <div><div class="info-label">Date &amp; Time</div><div class="info-value" style="font-size:.85rem;"><?= EVENT_DATE_TEXT ?> · <?= EVENT_TIME_TEXT ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-location-dot"></i></span>
                    <div><div class="info-label">Venue</div><div class="info-value" style="font-size:.85rem;"><?= EVENT_VENUE ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-tag"></i></span>
                    <div><div class="info-label">Ticket Price</div><div class="info-value" style="font-size:.85rem;"><?= peso(TICKET_PRICE) ?> · General Admission</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/app_footer.php'; ?>

<script>
    /* Business rule feedback: one student = one ticket. */
    var already = document.getElementById('btnAlreadyReserved');
    if (already) {
        already.addEventListener('click', function () {
            Swal.fire({
                icon: 'info',
                title: 'You already have an active ticket reservation.',
                html: 'Reference <strong><?= e($res['code'] ?? '') ?></strong>.<br>' +
                      'Each student may hold only one ticket for SIGLA 2026.',
                showCancelButton: true,
                confirmButtonText: '<?= $paid ? 'View my ticket' : 'Pay now' ?>',
                cancelButtonText: 'Close'
            }).then(function (r) {
                if (r.isConfirmed) window.location.href = '<?= url($paid ? 'ticket.php' : 'payment.php') ?>';
            });
        });
    }
</script>

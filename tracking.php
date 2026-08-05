<?php
/**
 * tracking.php — Reservation timeline.
 *
 * Reservation Submitted → Pending Payment → Payment Successful
 * → Ticket Generated → Ready for Event
 *
 * The states come from tracking_timeline() in includes/functions.php, which
 * reads the current session. REPLACE LATER with a status column / audit table.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

$res      = get_reservation();
$pay      = get_payment();
$ticket   = get_ticket();
$timeline = tracking_timeline();
$doneCt   = count(array_filter($timeline, static fn($s) => $s['state'] === 'done'));
$pct      = (int) round($doneCt / count($timeline) * 100);

[$stageLabel, $stageTone, $stageIcon] = reservation_stage_label();

$pageTitle = 'Track Reservation';
$crumb     = 'Student Portal';
$activeNav = 'tracking';
require __DIR__ . '/layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid <?= $stageIcon ?>"></i> <?= e($stageLabel) ?></span>
            <h2>Reservation Tracking</h2>
            <p>
                <?= $res
                    ? 'Reference <strong class="text-white">' . e($res['code']) . '</strong> · last updated ' . pretty_date($res['paid_at'] ?? $res['created_at'])
                    : 'You have not reserved a slot for ' . EVENT_NAME . ' yet.' ?>
            </p>
        </div>
        <div class="col-lg-4">
            <div class="text-lg-end">
                <div class="tg-label mb-1">Overall Progress</div>
                <div class="fw-900" style="font-size:2.6rem;line-height:1;color:var(--gold-400);letter-spacing:-2px;"><?= $pct ?>%</div>
                <div style="font-size:.78rem;color:rgba(255,255,255,.6);"><?= $doneCt ?> of <?= count($timeline) ?> stages complete</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ================= Timeline ================= -->
    <div class="col-lg-7">
        <div class="card-soft fade-up d1">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Status Timeline</h5>
                    <span class="cell-sub">From request to gate entry</span>
                </div>
                <?php if ($res): ?><?= status_badge($res['status']) ?><?php endif; ?>
            </div>

            <div class="card-body-p">
                <?php if (!$res): ?>
                    <div class="alert d-flex align-items-start gap-3 mb-4"
                         style="background:var(--gold-050);border:1px solid rgba(233,169,58,.3);border-radius:16px;color:#96690A;font-size:.86rem;">
                        <i class="fa-solid fa-circle-info mt-1"></i>
                        <div>
                            <strong class="d-block">Nothing to track yet.</strong>
                            Reserve a ticket first — the timeline below will then update in real time.
                        </div>
                    </div>
                <?php endif; ?>

                <ul class="track-line">
                    <?php foreach ($timeline as $i => $s): ?>
                        <li class="track-step <?= $s['state'] ?>">
                            <span class="track-dot"><i class="fa-solid <?= $s['icon'] ?>"></i></span>

                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <h6><?= e($s['label']) ?></h6>
                                    <p><?= $s['desc'] ?></p>
                                    <?php if ($s['state'] === 'done' && $s['stamp']): ?>
                                        <div class="track-stamp"><i class="fa-regular fa-clock me-1"></i><?= pretty_date($s['stamp']) ?></div>
                                    <?php elseif ($s['state'] === 'current'): ?>
                                        <div class="track-stamp text-gold"><i class="fa-solid fa-spinner me-1"></i>Waiting on you</div>
                                    <?php else: ?>
                                        <div class="track-stamp">Not yet reached</div>
                                    <?php endif; ?>
                                </div>

                                <span class="badge-soft badge-<?= $s['state'] === 'done' ? 'success' : ($s['state'] === 'current' ? 'warning' : 'muted') ?>">
                                    <?= $s['state'] === 'done' ? 'Completed' : ($s['state'] === 'current' ? 'In Progress' : 'Pending') ?>
                                </span>
                            </div>

                            <?php if ($s['state'] === 'current' && $s['key'] === 'pending'): ?>
                                <a href="<?= url('payment.php') ?>" class="btn btn-gold btn-sm2 mt-3">
                                    <i class="fa-solid fa-mobile-screen"></i> Pay <?= peso(TICKET_PRICE) ?> now
                                </a>
                            <?php elseif ($s['state'] === 'current' && $s['key'] === 'submitted'): ?>
                                <a href="<?= url('reserve.php') ?>" class="btn btn-gold btn-sm2 mt-3">
                                    <i class="fa-solid fa-calendar-check"></i> Reserve a slot
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- ================= Right rail ================= -->
    <div class="col-lg-5">

        <div class="card-soft mb-4 fade-up d2">
            <div class="card-head"><h6 class="mb-0">Reservation Summary</h6></div>
            <div class="card-body-p">
                <?php if ($res): ?>
                    <div class="receipt-row"><span class="k">Reference</span><span class="v mono"><?= e($res['code']) ?></span></div>
                    <div class="receipt-row"><span class="k">Status</span><span class="v"><?= status_badge($res['status']) ?></span></div>
                    <div class="receipt-row"><span class="k">Reserved On</span><span class="v"><?= pretty_date($res['created_at']) ?></span></div>
                    <div class="receipt-row"><span class="k">Amount</span><span class="v"><?= peso($res['amount']) ?></span></div>
                    <?php if ($pay): ?>
                        <div class="receipt-row"><span class="k">Receipt No.</span><span class="v mono"><?= e($pay['receipt_no']) ?></span></div>
                        <div class="receipt-row"><span class="k">PayMongo Ref.</span><span class="v mono"><?= e($pay['reference']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($ticket): ?>
                        <div class="receipt-row"><span class="k">Ticket No.</span><span class="v mono"><?= e($ticket['ticket_no']) ?></span></div>
                        <div class="receipt-row"><span class="k">Seat</span><span class="v"><?= e($ticket['seat']) ?></span></div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <?php if (!$pay): ?>
                            <a href="<?= url('payment.php') ?>" class="btn btn-gold btn-sm2"><i class="fa-solid fa-mobile-screen"></i> Pay Now</a>
                        <?php else: ?>
                            <a href="<?= url('ticket.php') ?>" class="btn btn-green btn-sm2"><i class="fa-solid fa-qrcode"></i> Ticket</a>
                            <a href="<?= url('receipt.php') ?>" class="btn btn-ghost btn-sm2"><i class="fa-solid fa-receipt"></i> Receipt</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="empty-art" style="width:70px;height:70px;font-size:1.7rem;border-radius:22px;">
                            <i class="fa-solid fa-calendar-xmark"></i>
                        </div>
                        <h6>No active reservation</h6>
                        <p style="font-size:.85rem;">Claim your slot to start tracking.</p>
                        <a href="<?= url('reserve.php') ?>" class="btn btn-gold btn-sm2"><i class="fa-solid fa-ticket"></i> Reserve Ticket</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-soft fade-up d3">
            <div class="card-head"><h6 class="mb-0">Need Help?</h6></div>
            <div class="card-body-p">
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-envelope"></i></span>
                    <div><div class="info-label">Email the SSC</div>
                         <div class="info-value" style="font-size:.82rem;">ssc.nasugbu@g.batstate-u.edu.ph</div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-phone"></i></span>
                    <div><div class="info-label">Hotline</div>
                         <div class="info-value" style="font-size:.82rem;">(043) 416-0350 local 118</div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-location-dot"></i></span>
                    <div><div class="info-label">SSC Office</div>
                         <div class="info-value" style="font-size:.82rem;">Student Center, ARASOF-Nasugbu</div></div>
                </div>
                <button class="btn btn-outline-green btn-block2 btn-sm2 mt-3" data-soon="Support Ticket">
                    <i class="fa-solid fa-headset"></i> Report an Issue
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/app_footer.php'; ?>

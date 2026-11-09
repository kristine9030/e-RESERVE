<?php
/**
 * dashboard.php — Student dashboard.
 * Shows where the student is in the four-step pipeline and the next thing to do.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

/* Staff accounts belong in the admin shell. */
if (is_admin()) {
    redirect('admin/index.php');
}

$user     = current_user();
$verified = is_email_verified();
$docs     = user_documents();
$progress = document_progress();
$ticket   = get_ticket();
$timeline = tracking_timeline();

[$stageLabel, $stageTone, $stageIcon] = registration_stage_label();

$firstName = explode(' ', $user['name'])[0];
$hour      = (int) date('G');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

/* The one thing to do next — drives the big call-to-action card. */
if (!$verified) {
    $next = ['fa-envelope-open-text', 'Confirm your email', 'Open the link we sent to ' . $user['email'] . ' so we know the account is yours.', 'Resend link', 'verify.php'];
} elseif ($progress['blocked']) {
    $next = ['fa-triangle-exclamation', 'Replace the returned document', 'Staff sent a document back with a note. Upload a corrected PDF to restore your ticket.', 'Fix it now', 'documents.php'];
} elseif ($progress['missing']) {
    $names = implode(', ', array_map(static fn($k) => DOCUMENT_TYPES[$k]['short'], $progress['missing']));
    $next = ['fa-file-arrow-up', 'Upload your documents', 'Still needed: ' . $names . '. Your ticket number is issued the moment all three are in.', 'Upload now', 'documents.php'];
} elseif ($ticket && !$ticket['checked_in']) {
    $next = ['fa-qrcode', 'Your ticket is ready', 'Show the QR code at the gate on event night. Screenshot it or print a copy as backup.', 'Open my ticket', 'ticket.php'];
} else {
    $next = ['fa-circle-check', 'You’re all set', 'You have been scanned in. Enjoy SIGLA!', 'View ticket', 'ticket.php'];
}

$pageTitle = 'Dashboard';
$crumb     = 'Student Portal';
$activeNav = 'dashboard';
require __DIR__ . '/layouts/app_header.php';
?>

<!-- ================= Welcome ================= -->
<section class="dash-welcome">
    <div class="dw-text">
        <span class="dw-greet"><i class="fa-solid fa-hand-sparkles"></i> <?= $greeting ?></span>
        <h2>Welcome, <?= e($firstName) ?>!</h2>
        <p class="dw-meta">
            <span><i class="fa-solid fa-graduation-cap"></i> <?= e($user['program']) ?> · <?= e($user['year']) ?></span>
            <span><i class="fa-solid fa-id-badge"></i> SR-Code <strong><?= e($user['student_no']) ?></strong></span>
        </p>
        <div class="dw-badges">
            <span class="badge-soft badge-<?= $stageTone ?>"><i class="fa-solid <?= $stageIcon ?>"></i> <?= e($stageLabel) ?></span>
            <?php if ($ticket && $ticket['status'] !== 'void'): ?>
                <span class="badge-soft badge-info"><i class="fa-solid fa-ticket"></i> <?= e($ticket['ticket_no']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="dw-count" data-countdown="<?= EVENT_DATE_ISO ?>">
        <div class="dw-count-title"><?= EVENT_NAME ?> · <?= EVENT_DATE_TEXT ?></div>
        <div class="dw-count-row">
            <div><b data-cd="days">00</b><span>days</span></div>
            <div><b data-cd="hours">00</b><span>hrs</span></div>
            <div><b data-cd="minutes">00</b><span>min</span></div>
            <div><b data-cd="seconds">00</b><span>sec</span></div>
        </div>
    </div>
</section>

<div class="dash-grid">

    <!-- ================= Main column ================= -->
    <div class="dash-main">

        <!-- Next step -->
        <div class="next-step <?= $progress['blocked'] ? 'is-alert' : '' ?>">
            <span class="next-icon"><i class="fa-solid <?= $next[0] ?>"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="next-kicker">Your next step</div>
                <h4><?= e($next[1]) ?></h4>
                <p><?= e($next[2]) ?></p>
            </div>
            <a href="<?= url($next[4]) ?>" class="btn btn-gold"><?= e($next[3]) ?> <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <!-- Progress -->
        <div class="dash-card">
            <div class="dash-card-head">
                <h5><i class="fa-solid fa-route"></i> Your progress</h5>
                <?php
                $doneCt = count(array_filter($timeline, static fn($s) => $s['state'] === 'done'));
                ?>
                <span class="cell-sub"><?= $doneCt ?> of <?= count($timeline) ?> done</span>
            </div>
            <div class="pipeline pipeline-app pipeline-flat">
                <?php foreach ($timeline as $s): ?>
                    <div class="pipe-step <?= $s['state'] ?>">
                        <div class="pipe-node">
                            <?php if ($s['state'] === 'done'): ?><i class="fa-solid fa-check"></i>
                            <?php else: ?><i class="fa-solid <?= $s['icon'] ?>"></i><?php endif; ?>
                        </div>
                        <div class="pipe-body">
                            <div class="pipe-title"><?= e($s['label']) ?></div>
                            <div class="pipe-sub">
                                <?php if ($s['state'] === 'done' && $s['stamp']): ?><?= pretty_date($s['stamp'], 'M d · g:i A') ?>
                                <?php elseif ($s['state'] === 'current'): ?><span class="text-gold">In progress</span>
                                <?php else: ?>Up next<?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Documents -->
        <div class="dash-card">
            <div class="dash-card-head">
                <h5><i class="fa-solid fa-file-circle-check"></i> Required documents</h5>
                <a href="<?= url('documents.php') ?>" class="btn btn-ghost btn-sm2">Manage <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="dash-docs">
                <?php foreach (DOCUMENT_TYPES as $key => $meta):
                    $doc    = $docs[$key] ?? null;
                    $status = $doc['status'] ?? 'missing';
                ?>
                    <a href="<?= url('documents.php#doc-' . $key) ?>" class="doc-row state-<?= $status ?>">
                        <span class="doc-row-icon"><i class="fa-solid <?= $meta['icon'] ?>"></i></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-block fw-800 text-deep"><?= e($meta['label']) ?></span>
                            <span class="cell-sub d-block text-truncate">
                                <?php if ($doc): ?><?= e($doc['name']) ?><?php else: ?>Not uploaded yet<?php endif; ?>
                            </span>
                        </span>
                        <?= status_badge($status) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ================= Side column ================= -->
    <aside class="dash-side">

        <?php if ($ticket && $ticket['status'] !== 'void'): ?>
            <a href="<?= url('ticket.php') ?>" class="mini-ticket tilt d-block" data-tilt="8">
                <div class="mini-ticket-top">
                    <span class="eyebrow on-dark"><i class="fa-solid fa-ticket"></i> Your ticket</span>
                    <span class="badge-soft badge-<?= $ticket['checked_in'] ? 'info' : 'success' ?>"><?= $ticket['checked_in'] ? 'Used' : 'Valid' ?></span>
                </div>
                <div class="mini-ticket-no mono"><?= e($ticket['ticket_no']) ?></div>
                <div class="mini-ticket-foot">
                    <span><?= e($user['name']) ?></span>
                    <span>Show QR <i class="fa-solid fa-arrow-right ms-1"></i></span>
                </div>
            </a>
        <?php else: ?>
            <div class="dash-card dash-empty">
                <span class="dash-empty-icon"><i class="fa-solid fa-ticket"></i></span>
                <h6>No ticket yet</h6>
                <p>It is issued automatically the moment all 3 required documents are uploaded.</p>
            </div>
        <?php endif; ?>

        <div class="dash-card">
            <div class="dash-card-head"><h5><i class="fa-solid fa-calendar-day"></i> Event details</h5></div>
            <ul class="dash-facts">
                <li><i class="fa-solid fa-star"></i><div><span>Event</span><strong><?= EVENT_NAME ?></strong></div></li>
                <li><i class="fa-solid fa-calendar-day"></i><div><span>Date &amp; time</span><strong><?= EVENT_DATE_TEXT ?> · <?= EVENT_TIME_TEXT ?></strong></div></li>
                <li><i class="fa-solid fa-location-dot"></i><div><span>Venue</span><strong><?= EVENT_VENUE ?></strong></div></li>
            </ul>
        </div>

        <div class="dash-card">
            <div class="dash-card-head"><h5><i class="fa-solid fa-headset"></i> Need help?</h5></div>
            <ul class="dash-facts">
                <li><i class="fa-solid fa-envelope"></i><div><span>Email</span><strong><a href="mailto:<?= SSC_EMAIL ?>"><?= SSC_EMAIL ?></a></strong></div></li>
                <li><i class="fa-brands fa-facebook"></i><div><span>Facebook</span><strong><a href="<?= SSC_FACEBOOK_URL ?>" target="_blank" rel="noopener">facebook.com/SSCARASOF</a></strong></div></li>
            </ul>
            <div class="dash-help-links">
                <a href="<?= url('index.php#guidelines') ?>" class="btn btn-ghost btn-sm2"><i class="fa-solid fa-book-open"></i> Guidelines</a>
                <a href="<?= url('tracking.php') ?>" class="btn btn-ghost btn-sm2"><i class="fa-solid fa-route"></i> Full timeline</a>
            </div>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/layouts/app_footer.php'; ?>

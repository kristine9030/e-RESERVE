<?php
/**
 * tracking.php — Registration timeline.
 *
 * Account → Email confirmed → Documents uploaded → Ticket issued → Scanned at the gate.
 * The states come from tracking_timeline() in includes/functions.php.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

if (is_admin()) {
    redirect('admin/index.php');
}

$progress = document_progress();
$docs     = user_documents();
$ticket   = get_ticket();
$timeline = tracking_timeline();
$doneCt   = count(array_filter($timeline, static fn($s) => $s['state'] === 'done'));
$pct      = (int) round($doneCt / count($timeline) * 100);

[$stageLabel, $stageTone, $stageIcon] = registration_stage_label();

$pageTitle = 'Track My Progress';
$crumb     = 'Student Portal';
$activeNav = 'tracking';
require __DIR__ . '/layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid <?= $stageIcon ?>"></i> <?= e($stageLabel) ?></span>
            <h2>Registration Tracking</h2>
            <p>
                <?= $ticket && $ticket['status'] !== 'void'
                    ? 'Ticket <strong class="text-white">' . e($ticket['ticket_no']) . '</strong> issued ' . pretty_date($ticket['issued_at'])
                    : 'Follow your registration from account to gate for ' . EVENT_NAME . '.' ?>
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
                    <span class="cell-sub">From sign-up to gate entry</span>
                </div>
                <span class="badge-soft badge-<?= $stageTone ?>"><?= e($stageLabel) ?></span>
            </div>

            <div class="card-body-p">
                <ul class="track-line">
                    <?php foreach ($timeline as $s): ?>
                        <li class="track-step <?= $s['state'] ?>">
                            <span class="track-dot"><i class="fa-solid <?= $s['icon'] ?>"></i></span>

                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <h6><?= e($s['label']) ?></h6>
                                    <p><?= e($s['desc']) ?></p>
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

                            <?php if ($s['state'] === 'current' && $s['key'] === 'email'): ?>
                                <a href="<?= url('verify.php') ?>" class="btn btn-gold btn-sm2 mt-3"><i class="fa-solid fa-paper-plane"></i> Resend confirmation</a>
                            <?php elseif ($s['state'] === 'current' && in_array($s['key'], ['documents', 'ticket'], true)): ?>
                                <a href="<?= url('documents.php') ?>" class="btn btn-gold btn-sm2 mt-3"><i class="fa-solid fa-file-arrow-up"></i> Go to my documents</a>
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
            <div class="card-head"><h6 class="mb-0">Document Checklist</h6></div>
            <div class="card-body-p">
                <?php foreach (DOCUMENT_TYPES as $key => $meta):
                    $doc = $docs[$key] ?? null; ?>
                    <div class="receipt-row">
                        <span class="k"><i class="fa-solid <?= $meta['icon'] ?> text-gold me-2"></i><?= e($meta['short']) ?></span>
                        <span class="v"><?= status_badge($doc['status'] ?? 'missing') ?></span>
                    </div>
                    <?php if (($doc['status'] ?? '') === 'rejected' && !empty($doc['note'])): ?>
                        <div class="doc-note mb-2"><i class="fa-solid fa-comment-dots"></i><div><?= e($doc['note']) ?></div></div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($ticket): ?>
                    <div class="receipt-row"><span class="k">Ticket No.</span><span class="v mono"><?= e($ticket['ticket_no']) ?></span></div>
                    <div class="receipt-row"><span class="k">Ticket status</span><span class="v"><?= status_badge($ticket['checked_in'] ? 'used' : $ticket['status']) ?></span></div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="<?= url('documents.php') ?>" class="btn btn-gold btn-sm2"><i class="fa-solid fa-file-pdf"></i> Documents</a>
                    <?php if ($ticket && $ticket['status'] !== 'void'): ?>
                        <a href="<?= url('ticket.php') ?>" class="btn btn-green btn-sm2"><i class="fa-solid fa-qrcode"></i> Ticket</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card-soft fade-up d3">
            <div class="card-head"><h6 class="mb-0">Need Help?</h6></div>
            <div class="card-body-p">
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-envelope"></i></span>
                    <div><div class="info-label">Email the SSC</div>
                         <div class="info-value" style="font-size:.82rem;"><a href="mailto:<?= SSC_EMAIL ?>"><?= SSC_EMAIL ?></a></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><i class="fa-brands fa-facebook"></i></span>
                    <div><div class="info-label">Facebook</div>
                         <div class="info-value" style="font-size:.82rem;"><a href="<?= SSC_FACEBOOK_URL ?>" target="_blank" rel="noopener">facebook.com/SSCARASOF</a></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><i class="fa-brands fa-facebook-messenger"></i></span>
                    <div><div class="info-label">Messenger</div>
                         <div class="info-value" style="font-size:.78rem;"><?= e(SSC_MESSENGER) ?></div></div>
                </div>
                <a href="<?= url('index.php#guidelines') ?>" class="btn btn-outline-green btn-block2 btn-sm2 mt-3">
                    <i class="fa-solid fa-book-open"></i> Read the guidelines
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/app_footer.php'; ?>

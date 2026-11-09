<?php
/**
 * ticket.php — The e-ticket: ticket number plus the QR the gate scans.
 *
 * Issued automatically once the student's three documents are uploaded.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

if (is_admin()) {
    redirect('admin/index.php');
}

$ticket = get_ticket();
$progress = document_progress();
$user   = current_user();

$pageTitle = 'My Ticket';
$crumb     = 'Student Portal';
$activeNav = 'ticket';
require __DIR__ . '/layouts/app_header.php';
?>

<?php if (!$ticket): ?>

    <div class="card-soft fade-up">
        <div class="empty-state">
            <div class="empty-art"><i class="fa-solid fa-ticket"></i></div>
            <h5>No ticket has been issued yet</h5>
            <p>
                Your ticket number appears the moment your waiver, COR and school ID are all uploaded.
                You have uploaded <strong><?= $progress['uploaded'] ?> of <?= $progress['total'] ?></strong> so far.
            </p>
            <a href="<?= url('documents.php') ?>" class="btn btn-gold"><i class="fa-solid fa-file-arrow-up"></i> Upload documents</a>
            <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost ms-2"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>

<?php else: ?>

    <?php
    // Exactly what the gate scanner reads off this ticket.
    $qrPayload = ticket_qr_payload($ticket);
    $isVoid    = $ticket['status'] === 'void';
    ?>

    <div class="page-hero fade-up no-print">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <span class="eyebrow on-dark"><i class="fa-solid fa-circle-check"></i> Ticket Issued</span>
                <h2>You're going to SIGLA 2026!</h2>
                <p>Show the QR code below at the gate. Keep this ticket private — it admits one person only.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <button class="btn btn-gold" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Ticket</button>
            </div>
        </div>
    </div>

    <?php if ($isVoid): ?>
        <div class="callout callout-danger no-print">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div class="flex-grow-1">
                <strong>This ticket is on hold.</strong> A document was returned by SSC staff.
                Upload a corrected PDF and this same ticket becomes valid again.
            </div>
            <a href="<?= url('documents.php') ?>" class="btn btn-gold btn-sm2">Fix my documents</a>
        </div>
    <?php endif; ?>

    <p class="cell-sub no-print mb-3 text-center">
        <i class="fa-solid fa-print me-1"></i> Printing? Choose <strong>A4</strong>, tick <strong>Background graphics</strong> and untick <strong>Headers and footers</strong>.
    </p>

    <!-- ================= The ticket ================= -->
    <?php
    /* Decorative barcode derived from the ticket number — the QR is what actually scans. */
    $bars = '';
    $x    = 0;
    foreach (str_split(hash('crc32b', $ticket['ticket_no']) . hash('crc32b', strrev($ticket['ticket_no'])) . hash('crc32b', $ticket['verify_hash'])) as $ch) {
        $v = hexdec($ch);
        $w = 1 + ($v % 3);
        if ($v % 2 === 0) {
            $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="40"/>';
        }
        $x += $w + 1 + ($v % 2);
    }
    $performers = implode('  ·  ', array_column(EVENT_PERFORMERS, 0));
    ?>

    <div class="tix-wrap tilt mb-4" data-tilt="7">
        <article class="tix <?= $isVoid ? 'is-void' : '' ?> <?= $ticket['checked_in'] ? 'is-used' : '' ?>">

            <!-- ============ Main ============ -->
            <div class="tix-main">
                <header class="tix-top">
                    <img class="tix-logo" src="<?= url(SSC_LOGO_FILE) ?>" alt="Supreme Student Council seal">
                    <div class="tix-org">
                        <span><?= e(EVENT_ORG) ?></span>
                        <span>BatStateU TNEU · ARASOF-Nasugbu</span>
                    </div>
                    <span class="tix-admit">ADMIT ONE</span>
                </header>

                <div class="tix-title">
                    <span class="tix-kicker">Official Ticket · 70th Founding Anniversary</span>
                    <h3>SIGLA <em>A Student’s Night</em> 2026</h3>
                </div>

                <div class="tix-when">
                    <div><span>Date</span><strong><?= e($ticket['date_text']) ?></strong></div>
                    <div><span>Time</span><strong><?= e($ticket['time_text']) ?></strong></div>
                    <div><span>Venue</span><strong><?= e($ticket['venue']) ?></strong></div>
                </div>

                <div class="tix-holder">
                    <div class="tix-name">
                        <span>Ticket holder</span>
                        <strong><?= e($ticket['name']) ?></strong>
                    </div>
                    <div>
                        <span>SR-Code</span>
                        <strong class="mono"><?= e($ticket['student_no']) ?></strong>
                    </div>
                    <div>
                        <span>Program</span>
                        <strong><?= e($ticket['program']) ?> · <?= e($ticket['year']) ?></strong>
                    </div>
                </div>

                <footer class="tix-foot">
                    <svg class="tix-barcode" viewBox="0 0 <?= $x ?> 40" preserveAspectRatio="none" aria-hidden="true"><?= $bars ?></svg>
                    <span class="tix-perf-line"><?= e($performers) ?></span>
                </footer>
            </div>

            <!-- ============ Stub ============ -->
            <aside class="tix-stub">
                <div class="tix-stub-label">Scan at the gate</div>
                <div class="tix-qr">
                    <?= $isVoid
                        ? '<div class="qr-void"><i class="fa-solid fa-ban"></i><span>On hold</span></div>'
                        : qr_svg($qrPayload, 168, ['label' => 'Ticket ' . $ticket['ticket_no']]) ?>
                </div>
                <div class="tix-no-label">Ticket no.</div>
                <div class="tix-no mono"><?= e($ticket['ticket_no']) ?></div>
                <span class="tix-state tix-state-<?= $isVoid ? 'void' : ($ticket['checked_in'] ? 'used' : 'valid') ?>">
                    <?= $isVoid ? 'On hold' : ($ticket['checked_in'] ? 'Checked in' : 'Valid for entry') ?>
                </span>
            </aside>

            <?php if ($isVoid): ?><div class="tix-stamp">ON HOLD</div><?php endif; ?>
            <?php if ($ticket['checked_in']): ?><div class="tix-stamp tix-stamp-used">USED</div><?php endif; ?>
        </article>
    </div>

    <div class="print-doc-foot">
        Issued <?= pretty_date($ticket['issued_at'], 'F d, Y · g:i A') ?> · non-transferable · one scan per ticket ·
        questions: <?= e(SSC_EMAIL) ?>
    </div>

    <!-- ================= Actions + reminders ================= -->
    <div class="row g-4 no-print">
        <div class="col-lg-7">
            <div class="card-soft fade-up d2">
                <div class="card-head"><h6 class="mb-0">Ticket Actions</h6></div>
                <div class="card-body-p d-grid gap-2">
                    <button class="action-tile" onclick="window.print()">
                        <span class="t-icon"><i class="fa-solid fa-print"></i></span>
                        <span><span class="t-title d-block">Print / Save as PDF</span>
                              <span class="t-sub">Bring a printed copy as backup</span></span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </button>

                    <a href="<?= url('documents.php') ?>" class="action-tile">
                        <span class="t-icon"><i class="fa-solid fa-file-pdf"></i></span>
                        <span><span class="t-title d-block">My Documents</span>
                              <span class="t-sub">The 3 required documents behind this ticket</span></span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </a>

                    <button class="action-tile" data-copy="<?= e($ticket['ticket_no']) ?>">
                        <span class="t-icon"><i class="fa-solid fa-copy"></i></span>
                        <span><span class="t-title d-block">Copy Ticket Number</span>
                              <span class="t-sub"><?= e($ticket['ticket_no']) ?></span></span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-soft fade-up d3">
                <div class="card-head"><h6 class="mb-0">Before You Go</h6></div>
                <div class="card-body-p">
                    <?php
                    $reminders = [
                        ['fa-clock',      'Arrive early',        'The event starts at 5:00 PM. Be at the gate before then so you are scanned in on time.'],
                        ['fa-id-badge',   'Bring your school ID','Gate staff match your QR code against your ID.'],
                        ['fa-mobile-screen','Screen brightness',  'Turn brightness up so the scanner reads the QR quickly.'],
                        ['fa-ban',        'Non-transferable',    'This ticket admits one student and cannot be resold.'],
                    ];
                    foreach ($reminders as [$icon, $t, $d]): ?>
                        <div class="info-row">
                            <span class="info-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                            <div>
                                <div class="info-value" style="font-size:.86rem;"><?= e($t) ?></div>
                                <div class="cell-sub"><?= e($d) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/layouts/app_footer.php'; ?>

<?php
/**
 * ticket.php — The automatically generated e-ticket.
 *
 * REPLACE LATER: SELECT * FROM tbl_tickets WHERE student_no = ? AND event_id = ?
 * The QR payload below is what the gate scanner should verify.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

$ticket = get_ticket();
$user   = current_user();

$pageTitle = 'My E-Ticket';
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
                Your e-ticket is generated automatically the moment your GCash payment is confirmed.
                <?= has_reservation()
                    ? 'Your reservation is waiting for payment.'
                    : 'Start by reserving your slot for ' . EVENT_NAME . '.' ?>
            </p>
            <?php if (has_reservation()): ?>
                <a href="<?= url('payment.php') ?>" class="btn btn-gold"><i class="fa-solid fa-mobile-screen"></i> Pay Now</a>
            <?php else: ?>
                <a href="<?= url('reserve.php') ?>" class="btn btn-gold"><i class="fa-solid fa-calendar-check"></i> Reserve Ticket</a>
            <?php endif; ?>
            <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost ms-2"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>

<?php else: ?>

    <?php
    // Payload the gate scanner would read.
    $qrPayload = json_encode([
        'ticket'  => $ticket['ticket_no'],
        'student' => $ticket['student_no'],
        'event'   => 'SIGLA-2026',
        'issued'  => $ticket['issued_at'],
    ]);
    ?>

    <div class="page-hero fade-up no-print">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <span class="eyebrow on-dark"><i class="fa-solid fa-circle-check"></i> Ticket Issued</span>
                <h2>You're going to SIGLA 2026!</h2>
                <p>Present the QR code below at the gate. Keep this ticket private — it admits one person only.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <button class="btn btn-gold" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Ticket</button>
            </div>
        </div>
    </div>

    <!-- ================= The ticket ================= -->
    <div class="eticket fade-up d1 mb-4">
        <div class="eticket-main">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <span class="eyebrow on-dark mb-2"><i class="fa-solid fa-ticket"></i> Official E-Ticket</span>
                    <h3 class="text-white mt-3 mb-1" style="font-size:1.65rem;letter-spacing:-.8px;"><?= e($ticket['event']) ?></h3>
                    <p class="mb-0" style="color:rgba(255,255,255,.6);font-size:.84rem;"><?= EVENT_SUBTITLE ?></p>
                </div>
                <?= status_badge($ticket['checked_in'] ? 'used' : $ticket['status']) ?>
            </div>

            <div class="ticket-grid">
                <div>
                    <div class="tg-label">Ticket Number</div>
                    <div class="tg-value mono" style="color:var(--gold-400);"><?= e($ticket['ticket_no']) ?></div>
                </div>
                <div>
                    <div class="tg-label">Admission</div>
                    <div class="tg-value">General · Seat <?= e($ticket['seat']) ?></div>
                </div>
                <div>
                    <div class="tg-label">Student Name</div>
                    <div class="tg-value"><?= e($ticket['name']) ?></div>
                </div>
                <div>
                    <div class="tg-label">Student Number</div>
                    <div class="tg-value mono"><?= e($ticket['student_no']) ?></div>
                </div>
                <div>
                    <div class="tg-label">Program</div>
                    <div class="tg-value" style="font-size:.86rem;"><?= e($ticket['program']) ?></div>
                </div>
                <div>
                    <div class="tg-label">Year Level</div>
                    <div class="tg-value"><?= e($ticket['year']) ?></div>
                </div>
                <div>
                    <div class="tg-label">Date &amp; Time</div>
                    <div class="tg-value" style="font-size:.86rem;"><?= e($ticket['date_text']) ?><br><?= e($ticket['time_text']) ?></div>
                </div>
                <div>
                    <div class="tg-label">Venue</div>
                    <div class="tg-value" style="font-size:.86rem;"><?= e($ticket['venue']) ?></div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,.12);">
                <div>
                    <div class="tg-label">Issued</div>
                    <div class="tg-value" style="font-size:.82rem;"><?= pretty_date($ticket['issued_at']) ?></div>
                </div>
                <div class="ms-auto text-end">
                    <div class="tg-label">Issued By</div>
                    <div class="tg-value" style="font-size:.82rem;">Supreme Student Council</div>
                </div>
            </div>

            <div class="watermark">SIGLA</div>
        </div>

        <!-- QR stub -->
        <div class="eticket-stub">
            <div class="info-label mb-2">Scan at Gate</div>
            <div class="qr-frame mb-3">
                <?= qr_placeholder($qrPayload, 190) ?>
            </div>
            <div class="mono fw-800 text-deep" style="font-size:.82rem;letter-spacing:.6px;"><?= e($ticket['ticket_no']) ?></div>
            <div class="cell-sub mb-3">Admit one (1) student</div>
            <span class="badge-soft badge-<?= $ticket['checked_in'] ? 'info' : 'success' ?>">
                <i class="fa-solid fa-<?= $ticket['checked_in'] ? 'user-check' : 'circle-check' ?>"></i>
                <?= $ticket['checked_in'] ? 'Checked In' : 'Valid for Entry' ?>
            </span>

            <p class="cell-sub mt-3 mb-0 print-only">
                Placeholder QR — replace with a generated code on API integration.
            </p>
        </div>
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

                    <a href="<?= url('receipt.php') ?>" class="action-tile">
                        <span class="t-icon"><i class="fa-solid fa-receipt"></i></span>
                        <span><span class="t-title d-block">View Official Receipt</span>
                              <span class="t-sub">Proof of your GCash payment</span></span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </a>

                    <button class="action-tile" data-soon="Email My Ticket">
                        <span class="t-icon"><i class="fa-solid fa-envelope"></i></span>
                        <span><span class="t-title d-block">Email Ticket to Me</span>
                              <span class="t-sub">Send a copy to <?= e($user['email']) ?></span></span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </button>

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
                        ['fa-clock',      'Arrive early',        'Gates open 4:00 PM. Scanning is fastest before 5:30 PM.'],
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

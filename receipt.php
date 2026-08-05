<?php
/**
 * receipt.php — Auto-generated official receipt for a settled reservation.
 *
 * REPLACE LATER: SELECT * FROM tbl_payments WHERE receipt_no = ?
 * Consider generating a PDF (Dompdf/TCPDF) for the Download button.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

$pay    = get_payment();
$ticket = get_ticket();
$res    = get_reservation();
$user   = current_user();

$pageTitle = 'Official Receipt';
$crumb     = 'Student Portal';
$activeNav = 'receipt';
require __DIR__ . '/layouts/app_header.php';
?>

<?php if (!$pay): ?>

    <div class="card-soft fade-up">
        <div class="empty-state">
            <div class="empty-art"><i class="fa-solid fa-receipt"></i></div>
            <h5>No receipt available yet</h5>
            <p>
                Your official receipt is issued automatically once your GCash payment is confirmed by PayMongo.
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

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 no-print">
        <div>
            <span class="eyebrow"><i class="fa-solid fa-circle-check"></i> Payment Settled</span>
            <h3 class="mt-3 mb-0">Official Receipt</h3>
            <p class="text-soft mb-0" style="font-size:.87rem;">
                Receipt <strong class="text-deep"><?= e($pay['receipt_no']) ?></strong> ·
                issued <?= pretty_date($pay['paid_at']) ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-gold" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Receipt</button>
            <a href="<?= url('ticket.php') ?>" class="btn btn-outline-green"><i class="fa-solid fa-qrcode"></i> View Ticket</a>
        </div>
    </div>

    <!-- ================= Receipt sheet ================= -->
    <div class="receipt-sheet fade-up d1">

        <div class="receipt-head">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-3">
                    <span class="brand-mark" style="background:rgba(255,255,255,.12);width:52px;height:52px;font-size:1.3rem;">
                        <i class="fa-solid fa-ticket"></i>
                    </span>
                    <div>
                        <div class="fw-900 text-white" style="font-size:1.28rem;letter-spacing:-.6px;">
                            e-<span style="color:var(--gold-400);">RESERVE</span>
                        </div>
                        <div style="font-size:.68rem;letter-spacing:1.7px;text-transform:uppercase;color:rgba(255,255,255,.55);">
                            Official Receipt
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="tg-label">Receipt No.</div>
                    <div class="mono fw-800" style="color:var(--gold-400);font-size:1.05rem;"><?= e($pay['receipt_no']) ?></div>
                </div>
            </div>

            <div class="mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,.12);">
                <div class="row g-3">
                    <div class="col-sm-7">
                        <div class="tg-label">Issued To</div>
                        <div class="tg-value"><?= e($user['name']) ?></div>
                        <div style="font-size:.8rem;color:rgba(255,255,255,.6);">
                            <?= e($user['student_no']) ?> · <?= e($user['program']) ?>
                        </div>
                    </div>
                    <div class="col-sm-5 text-sm-end">
                        <div class="tg-label">Issued By</div>
                        <div class="tg-value">Supreme Student Council</div>
                        <div style="font-size:.8rem;color:rgba(255,255,255,.6);">ARASOF-Nasugbu Campus</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="receipt-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Transaction Details</h5>
                <span class="paid-stamp"><i class="fa-solid fa-circle-check"></i> Paid</span>
            </div>

            <div class="receipt-row"><span class="k">Receipt Number</span><span class="v mono"><?= e($pay['receipt_no']) ?></span></div>
            <div class="receipt-row"><span class="k">Ticket Number</span><span class="v mono"><?= e($ticket['ticket_no'] ?? '—') ?></span></div>
            <div class="receipt-row"><span class="k">Reservation Code</span><span class="v mono"><?= e($res['code'] ?? '—') ?></span></div>
            <div class="receipt-row"><span class="k">Student Name</span><span class="v"><?= e($pay['payer']) ?></span></div>
            <div class="receipt-row"><span class="k">Student Number</span><span class="v mono"><?= e($user['student_no']) ?></span></div>
            <div class="receipt-row"><span class="k">Email</span><span class="v"><?= e($pay['payer_email']) ?></span></div>
            <div class="receipt-row"><span class="k">Ticket Type</span><span class="v"><?= tier_badge($pay['tier'] ?? $res['tier'] ?? null) ?></span></div>
            <div class="receipt-row"><span class="k">Payment Method</span><span class="v"><i class="fa-solid fa-mobile-screen me-1 text-gold"></i><?= e($pay['method']) ?></span></div>
            <div class="receipt-row"><span class="k">PayMongo Reference</span><span class="v mono"><?= e($pay['reference']) ?></span></div>
            <div class="receipt-row"><span class="k">Source ID</span><span class="v mono" style="font-size:.76rem;"><?= e($pay['source_id']) ?></span></div>
            <div class="receipt-row"><span class="k">Payment Date</span><span class="v"><?= pretty_date($pay['paid_at'], 'F d, Y · g:i A') ?></span></div>
            <div class="receipt-row"><span class="k">Status</span><span class="v"><?= status_badge($pay['status']) ?></span></div>

            <h6 class="mt-4 mb-3">Particulars</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0" style="font-size:.86rem;">
                    <thead>
                        <tr style="background:var(--green-050);">
                            <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);">Description</th>
                            <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);" class="text-center">Qty</th>
                            <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);" class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $tier = tier($pay['tier'] ?? $res['tier'] ?? null); ?>
                        <tr>
                            <td>
                                <div class="cell-strong"><?= EVENT_NAME ?></div>
                                <div class="cell-sub"><?= e($tier['name']) ?> Ticket · <?= EVENT_DATE_TEXT ?> · <?= EVENT_VENUE ?></div>
                            </td>
                            <td class="text-center">1</td>
                            <td class="text-end fw-800"><?= peso((float) $pay['amount']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-soft">Convenience / gateway fee</td>
                            <td class="text-center">—</td>
                            <td class="text-end text-soft">Waived</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="receipt-total">
                <div>
                    <div class="info-label">Total Amount Paid</div>
                    <div class="cell-sub">Settled via <?= e($pay['method']) ?> · <?= e($pay['currency']) ?></div>
                </div>
                <div class="amt"><?= peso($pay['amount']) ?></div>
            </div>

            <div class="row g-3 mt-4 pt-3" style="border-top:1px dashed var(--line);">
                <div class="col-sm-8">
                    <p class="cell-sub mb-0" style="line-height:1.7;">
                        This is a system-generated receipt from <?= APP_NAME ?> and is valid without a signature.
                        Keep it for your records — you may re-download it anytime from your dashboard.
                        For concerns, contact the SSC Office at ssc.nasugbu@g.batstate-u.edu.ph.
                    </p>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <div class="qr-frame">
                        <?= qr_svg($pay['receipt_no'] . '|' . $pay['reference'], 108, ['label' => 'Receipt ' . $pay['receipt_no']]) ?>
                    </div>
                    <div class="cell-sub mt-2">Verification code</div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-4 no-print">
        <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        <button class="btn btn-outline-green ms-2" data-soon="Download PDF Receipt">
            <i class="fa-solid fa-file-pdf"></i> Download as PDF
        </button>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/layouts/app_footer.php'; ?>

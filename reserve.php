<?php
/**
 * reserve.php — Ticket reservation.
 *
 * Business rule: ONE STUDENT = ONE TICKET.
 * REPLACE LATER:
 *   SELECT COUNT(*) FROM tbl_reservations WHERE student_no = ? AND status <> 'cancelled'
 *   → if > 0, block. Otherwise INSERT and return the new reservation code.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

$user = current_user();

/* ---------------------------------------------------------------------
 | POST actions
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reserve') {
        $result = create_reservation();

        if ($result['ok']) {
            set_flash(
                'success',
                'Slot Reserved!',
                'Reference <strong>' . e($result['reservation']['code']) . '</strong> is on hold.<br>'
                . 'Settle ' . peso(TICKET_PRICE) . ' via GCash to have your e-ticket issued.'
            );
            redirect('payment.php');
        }

        // One-ticket rule violated.
        set_flash('info', 'You already have an active ticket reservation.', e($result['message']));
        redirect('reserve.php');
    }

    if ($action === 'cancel') {
        if (cancel_reservation()) {
            set_flash('success', 'Reservation Cancelled', 'Your slot has been released. You may reserve again anytime.');
        } else {
            set_flash('error', 'Cannot Cancel', 'Paid reservations follow the SSC refund policy and cannot be cancelled here.');
        }
        redirect('reserve.php');
    }
}

$res       = get_reservation();
$paid      = is_paid();
$stats     = dashboard_stats();
$remaining = max(0, EVENT_CAPACITY - $stats['paid_tickets']);

$pageTitle = 'Reserve Ticket';
$crumb     = 'Student Portal';
$activeNav = 'reserve';
require __DIR__ . '/layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <span class="eyebrow on-dark"><i class="fa-solid fa-ticket"></i> Step 1 of 3</span>
    <h2>Reserve your SIGLA 2026 ticket</h2>
    <p>Claim a slot now — it is held for you until payment. One ticket per student, no exceptions.</p>
</div>

<div class="row g-4">

    <!-- =============== Left: the reservation form / state =============== -->
    <div class="col-lg-7">

        <?php if (!$res): ?>
            <!-- ---------- No reservation yet ---------- -->
            <div class="card-soft fade-up d1">
                <div class="card-head">
                    <div>
                        <h5 class="mb-0">Reservation Summary</h5>
                        <span class="cell-sub">Review before confirming</span>
                    </div>
                    <span class="badge-soft badge-warning"><i class="fa-solid fa-hourglass-half"></i> Unclaimed</span>
                </div>

                <div class="card-body-p">
                    <div class="receipt-row"><span class="k">Student Name</span><span class="v"><?= e($user['name']) ?></span></div>
                    <div class="receipt-row"><span class="k">Student Number</span><span class="v mono"><?= e($user['student_no']) ?></span></div>
                    <div class="receipt-row"><span class="k">Program &amp; Year</span><span class="v"><?= e($user['program']) ?> · <?= e($user['year']) ?></span></div>
                    <div class="receipt-row"><span class="k">GSuite Email</span><span class="v"><?= e($user['email']) ?></span></div>
                    <div class="receipt-row"><span class="k">Event</span><span class="v"><?= EVENT_NAME ?></span></div>
                    <div class="receipt-row"><span class="k">Date &amp; Venue</span><span class="v"><?= EVENT_DATE_TEXT ?><br><?= EVENT_VENUE ?></span></div>
                    <div class="receipt-row"><span class="k">Ticket Type</span><span class="v">General Admission × 1</span></div>

                    <div class="receipt-total">
                        <div>
                            <div class="info-label">Amount Due</div>
                            <div class="cell-sub">Payable via GCash on the next step</div>
                        </div>
                        <div class="amt"><?= peso(TICKET_PRICE) ?></div>
                    </div>

                    <form method="post" id="reserveForm" class="mt-4">
                        <input type="hidden" name="action" value="reserve">

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="agree" required>
                            <label class="form-check-label text-soft" for="agree" style="font-size:.84rem;">
                                I confirm the details above are correct and I accept the
                                <a href="#" class="text-gold fw-800" data-soon="Terms &amp; Conditions">terms of reservation</a>.
                            </label>
                        </div>

                        <button type="submit" class="btn btn-gold btn-lg2 btn-block2">
                            <i class="fa-solid fa-calendar-check"></i> Confirm Reservation
                        </button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- ---------- Reservation exists ---------- -->
            <div class="card-soft fade-up d1">
                <div class="card-head">
                    <div>
                        <h5 class="mb-0">Your Reservation</h5>
                        <span class="cell-sub">Reference <?= e($res['code']) ?></span>
                    </div>
                    <?= status_badge($res['status']) ?>
                </div>

                <div class="card-body-p">
                    <div class="alert d-flex align-items-start gap-3 mb-4" style="border-radius:16px;font-size:.87rem;
                        background:<?= $paid ? 'rgba(31,157,99,.08)' : 'rgba(226,161,28,.1)' ?>;
                        border:1px solid <?= $paid ? 'rgba(31,157,99,.24)' : 'rgba(226,161,28,.28)' ?>;
                        color:<?= $paid ? '#157A4B' : '#96690A' ?>;">
                        <i class="fa-solid <?= $paid ? 'fa-circle-check' : 'fa-circle-info' ?> mt-1"></i>
                        <div>
                            <strong class="d-block mb-1">You already have an active ticket reservation.</strong>
                            <?= $paid
                                ? 'Payment is settled and your e-ticket has been issued.'
                                : 'Each student may hold only one ticket. Complete the payment to have your e-ticket generated.' ?>
                        </div>
                    </div>

                    <div class="receipt-row"><span class="k">Reference Code</span><span class="v mono"><?= e($res['code']) ?></span></div>
                    <div class="receipt-row"><span class="k">Student</span><span class="v"><?= e($res['name']) ?> · <?= e($res['student_no']) ?></span></div>
                    <div class="receipt-row"><span class="k">Program &amp; Year</span><span class="v"><?= e($res['program']) ?> · <?= e($res['year']) ?></span></div>
                    <div class="receipt-row"><span class="k">Quantity</span><span class="v"><?= (int) $res['quantity'] ?> ticket</span></div>
                    <div class="receipt-row"><span class="k">Reserved On</span><span class="v"><?= pretty_date($res['created_at']) ?></span></div>
                    <?php if ($res['paid_at']): ?>
                        <div class="receipt-row"><span class="k">Paid On</span><span class="v"><?= pretty_date($res['paid_at']) ?></span></div>
                    <?php endif; ?>

                    <div class="receipt-total">
                        <div>
                            <div class="info-label"><?= $paid ? 'Amount Paid' : 'Amount Due' ?></div>
                            <div class="cell-sub"><?= $paid ? 'Settled via GCash' : 'Payable via GCash' ?></div>
                        </div>
                        <div class="amt"><?= peso($res['amount']) ?></div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <?php if (!$paid): ?>
                            <a href="<?= url('payment.php') ?>" class="btn btn-gold">
                                <i class="fa-solid fa-mobile-screen"></i> Pay Now
                            </a>
                            <form method="post" id="cancelForm" class="d-inline">
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="btn btn-soft-danger">
                                    <i class="fa-solid fa-xmark"></i> Cancel Reservation
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= url('ticket.php') ?>" class="btn btn-green"><i class="fa-solid fa-qrcode"></i> View E-Ticket</a>
                            <a href="<?= url('receipt.php') ?>" class="btn btn-ghost"><i class="fa-solid fa-receipt"></i> Receipt</a>
                        <?php endif; ?>
                        <a href="<?= url('tracking.php') ?>" class="btn btn-outline-green"><i class="fa-solid fa-route"></i> Track</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- =============== Right rail =============== -->
    <div class="col-lg-5">
        <div class="card-soft mb-4 fade-up d2">
            <div class="card-head"><h6 class="mb-0">Availability</h6></div>
            <div class="card-body-p">
                <?php $sold = EVENT_CAPACITY - $remaining; $pct = (int) round($sold / EVENT_CAPACITY * 100); ?>
                <div class="prog-row">
                    <div class="prog-top">
                        <span class="n"><?= number_format($sold) ?> claimed</span>
                        <span class="v"><?= $pct ?>%</span>
                    </div>
                    <div class="prog-track"><div class="prog-fill" data-width="<?= max($pct, 2) ?>" style="width:0;"></div></div>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <div>
                        <div class="info-label">Slots Remaining</div>
                        <div class="fw-900 text-deep" style="font-size:1.5rem;letter-spacing:-1px;"><?= number_format($remaining) ?></div>
                    </div>
                    <div class="text-end">
                        <div class="info-label">Capacity</div>
                        <div class="fw-900 text-deep" style="font-size:1.5rem;letter-spacing:-1px;"><?= number_format(EVENT_CAPACITY) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-soft fade-up d3">
            <div class="card-head"><h6 class="mb-0">Reservation Rules</h6></div>
            <div class="card-body-p">
                <?php
                $rules = [
                    ['fa-user-lock',   'One student, one ticket', 'The system blocks a second reservation on the same GSuite account.'],
                    ['fa-clock',       'Slots are held, not sold','An unpaid reservation may be released if the event sells out.'],
                    ['fa-mobile-screen','GCash only for now',     'Maya, GrabPay and cards arrive in a later release.'],
                    ['fa-id-badge',    'Bring your student ID',   'Gate staff verify the QR code against your ID.'],
                ];
                foreach ($rules as [$icon, $title, $desc]): ?>
                    <div class="info-row">
                        <span class="info-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                        <div>
                            <div class="info-value" style="font-size:.86rem;"><?= e($title) ?></div>
                            <div class="cell-sub"><?= e($desc) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/app_footer.php'; ?>

<script>
    /* ---- Confirmation dialog before creating the reservation ---- */
    var reserveForm = document.getElementById('reserveForm');
    if (reserveForm) {
        reserveForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (!document.getElementById('agree').checked) {
                Swal.fire({
                    icon: 'warning',
                    title: 'One more thing',
                    text: 'Please confirm your details and accept the terms of reservation first.',
                    confirmButtonText: 'Okay'
                });
                return;
            }

            Swal.fire({
                icon: 'question',
                title: 'Confirm your reservation?',
                html:
                    '<div style="text-align:left;font-size:.9rem;line-height:1.9;">' +
                    '<div><strong>Student:</strong> <?= e($user['name']) ?></div>' +
                    '<div><strong>Student No.:</strong> <?= e($user['student_no']) ?></div>' +
                    '<div><strong>Event:</strong> <?= EVENT_NAME ?></div>' +
                    '<div><strong>Ticket:</strong> General Admission × 1</div>' +
                    '<div><strong>Amount:</strong> <?= peso(TICKET_PRICE) ?></div>' +
                    '</div>' +
                    '<p style="margin-top:1rem;font-size:.82rem;color:#7C8F88;">' +
                    'Remember: one student is entitled to one ticket only.</p>',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-check"></i> Yes, reserve my slot',
                cancelButtonText: 'Not yet',
                reverseButtons: true
            }).then(function (r) {
                if (!r.isConfirmed) return;
                window.eReserve.processing('Reserving your slot…', 1200).then(function () {
                    reserveForm.submit();
                });
            });
        });
    }

    /* ---- Cancel confirmation ---- */
    var cancelForm = document.getElementById('cancelForm');
    if (cancelForm) {
        cancelForm.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Release your slot?',
                html: 'Your reservation <strong><?= e($res['code'] ?? '') ?></strong> will be cancelled and the slot returned to the pool.',
                showCancelButton: true,
                confirmButtonText: 'Yes, cancel it',
                cancelButtonText: 'Keep my slot',
                reverseButtons: true,
                confirmButtonColor: '#D9483B'
            }).then(function (r) { if (r.isConfirmed) cancelForm.submit(); });
        });
    }
</script>

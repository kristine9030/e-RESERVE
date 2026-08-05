<?php
/**
 * payment.php — Mock PayMongo / GCash checkout.
 *
 * REPLACE LATER (real integration outline):
 *   1. POST https://api.paymongo.com/v1/sources
 *        { type: "gcash", amount: 35000, currency: "PHP",
 *          redirect: { success: ".../payment.php?status=success",
 *                      failed:  ".../payment.php?status=failed" } }
 *   2. Redirect the student to data.attributes.redirect.checkout_url
 *   3. Handle source.chargeable in /webhooks/paymongo.php, then create a
 *      payment via POST /v1/payments and call complete_payment().
 *
 * For the prototype the "Complete Payment" button calls complete_payment()
 * directly, which settles the reservation and auto-generates ticket + receipt.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

/* --- Guards ---------------------------------------------------------- */
if (!has_reservation()) {
    set_flash('warning', 'No Reservation Found', 'Reserve a ticket first before proceeding to checkout.');
    redirect('reserve.php');
}

if (is_paid()) {
    set_flash('info', 'Already Paid', 'This reservation is already settled. Here is your official receipt.');
    redirect('receipt.php');
}

/* --- Actions --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'pay') {
        $result = complete_payment();

        if ($result['ok']) {
            $ticket = get_ticket();
            $pay    = get_payment();
            set_flash(
                'success',
                'Payment Successful!',
                'Reference <strong>' . e($pay['reference']) . '</strong><br>'
                . 'Your e-ticket <strong>' . e($ticket['ticket_no']) . '</strong> and receipt '
                . '<strong>' . e($pay['receipt_no']) . '</strong> have been generated automatically.'
            );
            redirect('dashboard.php');
        }

        set_flash('error', 'Payment Failed', e($result['message']));
        redirect('dashboard.php');
    }

    if ($action === 'fail') {
        set_flash('error', 'Payment Cancelled', 'No amount was charged. Your slot is still on hold — you may try again anytime.');
        redirect('dashboard.php');
    }
}

$user   = current_user();
$res    = get_reservation();
$intent = mock_paymongo_intent(TICKET_PRICE);

$pageTitle = 'Secure Checkout';
$bodyClass = 'checkout-page';
require __DIR__ . '/layouts/head.php';
?>

<div class="checkout-shell">
    <div class="w-100" style="max-width:460px;">

        <!-- Gateway breadcrumb -->
        <div class="text-center mb-4 fade-up">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-2"
                 style="background:#fff;border:1px solid var(--line);border-radius:99px;box-shadow:var(--shadow-sm);">
                <i class="fa-solid fa-lock text-green" style="font-size:.7rem;"></i>
                <span class="fw-800 text-deep" style="font-size:.72rem;letter-spacing:1.2px;">
                    SECURE CHECKOUT · POWERED BY PAYMONGO
                </span>
            </div>
        </div>

        <div class="checkout-card fade-up d1">

            <!-- GCash header -->
            <div class="gcash-head">
                <div class="gcash-logo">G</div>
                <h4 class="mb-1 fw-900" style="letter-spacing:-.5px;">GCash</h4>
                <p class="mb-0" style="font-size:.8rem;opacity:.9;">Pay with your GCash wallet</p>
            </div>

            <!-- Amount -->
            <div class="pay-amount">
                <div class="lbl mb-2">Amount to Pay</div>
                <div class="amt"><?= peso(TICKET_PRICE) ?></div>
                <div class="mt-2">
                    <span class="badge-soft badge-gold"><i class="fa-solid fa-ticket"></i> SIGLA 2026 · General Admission</span>
                </div>
            </div>

            <!-- Transaction details -->
            <div class="pay-rows">
                <div class="pay-row">
                    <span class="k">Reference Number</span>
                    <span class="v mono">
                        <?= e($intent['reference']) ?>
                        <a href="#" class="text-gold ms-1" data-copy="<?= e($intent['reference']) ?>" title="Copy">
                            <i class="fa-regular fa-copy"></i>
                        </a>
                    </span>
                </div>
                <div class="pay-row"><span class="k">Source ID</span><span class="v mono" style="font-size:.74rem;"><?= e($intent['source_id']) ?></span></div>
                <div class="pay-row"><span class="k">Merchant</span><span class="v">SSC ARASOF-Nasugbu</span></div>
                <div class="pay-row"><span class="k">Reservation</span><span class="v mono"><?= e($res['code']) ?></span></div>
                <div class="pay-row"><span class="k">Payer</span><span class="v"><?= e($user['name']) ?></span></div>
                <div class="pay-row"><span class="k">Student No.</span><span class="v mono"><?= e($user['student_no']) ?></span></div>
                <div class="pay-row"><span class="k">Payment Method</span><span class="v"><i class="fa-solid fa-mobile-screen me-1 text-gold"></i>GCash e-Wallet</span></div>

                <hr style="border-color:var(--line);opacity:1;">

                <div class="pay-row">
                    <span class="k fw-800 text-deep">Total Due</span>
                    <span class="v" style="font-size:1.1rem;"><?= peso(TICKET_PRICE) ?></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="pay-foot">
                <form method="post" id="payForm">
                    <input type="hidden" name="action" value="pay">
                    <button type="submit" class="btn btn-green btn-block2 btn-lg2">
                        <i class="fa-solid fa-shield-halved"></i> Complete Payment
                    </button>
                </form>

                <form method="post" id="failForm" class="mt-2">
                    <input type="hidden" name="action" value="fail">
                    <button type="submit" class="btn btn-ghost btn-block2">
                        <i class="fa-solid fa-arrow-left"></i> Cancel and go back
                    </button>
                </form>

                <div class="secure-note">
                    <i class="fa-solid fa-lock"></i>
                    <span>Mock gateway — no real charge is made in this prototype</span>
                </div>
            </div>
        </div>

        <p class="text-center text-muted2 mt-4 mb-0" style="font-size:.74rem;">
            <i class="fa-solid fa-circle-info me-1"></i>
            After payment, your e-ticket and official receipt are generated automatically.
        </p>
    </div>
</div>

<?php require __DIR__ . '/layouts/scripts.php'; ?>

<script>
    /* Simulated gateway round-trip so the demo feels like a real redirect. */
    document.getElementById('payForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            icon: 'question',
            title: 'Authorize GCash payment?',
            html: 'You are about to pay <strong><?= peso(TICKET_PRICE) ?></strong> to ' +
                  '<strong>SSC ARASOF-Nasugbu</strong>.<br>' +
                  '<span style="font-size:.8rem;color:#7C8F88;">Ref. <?= e($intent['reference']) ?></span>',
            showCancelButton: true,
            confirmButtonText: 'Authorize payment',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then(function (r) {
            if (!r.isConfirmed) return;
            window.eReserve.processing('Contacting GCash…', 1500)
                .then(function () { return window.eReserve.processing('Confirming with PayMongo…', 1400); })
                .then(function () { form.submit(); });
        });
    });

    document.getElementById('failForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            icon: 'warning',
            title: 'Cancel this payment?',
            text: 'Your reservation stays on hold, but no ticket is issued until payment clears.',
            showCancelButton: true,
            confirmButtonText: 'Yes, cancel payment',
            cancelButtonText: 'Continue paying',
            reverseButtons: true,
            confirmButtonColor: '#D9483B'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });
</script>

<?php
/**
 * verify.php — confirm an email address from the emailed link, and resend it.
 *
 * The token in the URL is compared against a SHA-256 stored in the users table
 * and cleared on success, so a link works exactly once and a leaked database
 * cannot be used to confirm anyone's account.
 */
require_once __DIR__ . '/includes/config.php';

$state   = '';   // confirmed | failed | sent | prompt
$message = '';
$devLink = null;
$devFile = null;

/* --- Resend ---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    require_login();

    $result  = send_verification_email((int) current_user()['id']);
    $message = $result['message'];
    $devLink = $result['link'];
    $devFile = $result['logged_to'];

    /* "We sent one a minute ago" is not a failure — saying so in red only
       makes the student think something broke. */
    $state = match ($result['code'] ?? '') {
        'sent'      => 'sent',
        'throttled' => 'waiting',
        'already'   => 'prompt',
        default     => 'failed',
    };

    /* In log mode the link cannot arrive by email, so it is shown on screen. */
    if ($state === 'sent' && MAIL_TRANSPORT !== 'smtp' && $result['link']) {
        $message = 'A confirmation link was generated.';
    }

/* --- Confirm --------------------------------------------------------- */
} elseif (isset($_GET['token'])) {
    $result  = verify_email_token((string) $_GET['token']);
    $state   = $result['ok'] ? 'confirmed' : 'failed';
    $message = $result['message'];

    if ($result['ok']) {
        set_flash(
            'success',
            'Email confirmed!',
            'Your account is fully verified. Next, upload your waiver, COR and school ID to get your ticket for ' . EVENT_NAME . '.'
        );
    }

} else {
    $state   = 'prompt';
    $message = 'Open the link in the email we sent you.';
}

$user     = current_user();
$verified = is_logged_in() && is_email_verified();

$pageTitle = 'Confirm Email';
$bodyClass = 'auth-page';
require __DIR__ . '/layouts/head.php';
?>

<div class="container" style="max-width:620px;padding:4rem 1rem;">

    <div class="text-center mb-4">
        <a class="brand-lockup d-inline-flex" href="<?= url('index.php') ?>">
            <img class="brand-logo" src="<?= url(SSC_LOGO_FILE) ?>" alt="Supreme Student Council seal">
            <span class="brand-text text-start">
                <span class="brand-name d-block">e-<span>RESERVE</span></span>
                <span class="brand-sub">SIGLA 2026</span>
            </span>
        </a>
    </div>

    <div class="card-soft p-4 p-md-5 fade-up text-center">

        <?php if ($state === 'confirmed'): ?>

            <span class="stat-icon green d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-circle-check"></i>
            </span>
            <h3 style="font-size:1.4rem;">Email confirmed</h3>
            <p class="text-soft" style="font-size:.9rem;">
                Thanks<?= $user ? ', ' . e(explode(' ', $user['name'])[0]) : '' ?> — your address is verified
                and your account is ready.
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                <a href="<?= url('documents.php') ?>" class="btn btn-gold"><i class="fa-solid fa-file-arrow-up"></i> Upload my documents</a>
                <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost">Go to dashboard</a>
            </div>

        <?php elseif ($state === 'sent'): ?>

            <span class="stat-icon gold d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-paper-plane"></i>
            </span>
            <h3 style="font-size:1.4rem;">Confirmation link sent</h3>
            <p class="text-soft" style="font-size:.9rem;">
                Check <strong class="text-deep"><?= e($user['email'] ?? 'your inbox') ?></strong>.
                The link works for <?= (int) VERIFY_TOKEN_HOURS ?> hours. Look in Spam if it is not there.
            </p>

            <?php if (MAIL_TRANSPORT !== 'smtp' && $devLink): ?>
                <div class="print-tip mt-4" style="text-align:left;">
                    <i class="fa-solid fa-flask"></i>
                    <div>
                        <strong>Mail is in log mode.</strong> Nothing was actually sent — the message was written to
                        <code>sessions/mail/<?= e((string) $devFile) ?></code>. Use this link to confirm:
                        <a href="<?= e($devLink) ?>" class="d-block mt-2 text-gold fw-800" style="word-break:break-all;font-size:.78rem;">
                            <?= e($devLink) ?>
                        </a>
                        <span class="d-block mt-2 cell-sub">
                            Set <code>MAIL_TRANSPORT</code> to <code>'smtp'</code> in
                            <code>includes/config.php</code> to send for real.
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost mt-4">Back to dashboard</a>

        <?php elseif ($state === 'waiting'): ?>

            <span class="stat-icon info d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-hourglass-half"></i>
            </span>
            <h3 style="font-size:1.4rem;">Already on its way</h3>
            <p class="text-soft" style="font-size:.9rem;"><?= e($message) ?></p>
            <p class="cell-sub">
                Sent to <strong class="text-deep"><?= e($user['email'] ?? '') ?></strong> — check Spam too.
            </p>
            <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost mt-3">Back to dashboard</a>

        <?php elseif ($state === 'failed'): ?>

            <span class="stat-icon danger d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-circle-exclamation"></i>
            </span>
            <h3 style="font-size:1.4rem;">That did not work</h3>
            <p class="text-soft" style="font-size:.9rem;"><?= e($message) ?></p>

            <?php if (is_logged_in() && !$verified): ?>
                <form method="post" class="mt-4">
                    <?= csrf_field() ?>
                    <button class="btn btn-gold"><i class="fa-solid fa-rotate"></i> Send me a new link</button>
                </form>
            <?php else: ?>
                <a href="<?= url('login.php') ?>" class="btn btn-gold mt-4">Sign in</a>
            <?php endif; ?>

        <?php else: ?>

            <span class="stat-icon info d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-envelope-open-text"></i>
            </span>
            <h3 style="font-size:1.4rem;">Confirm your email</h3>

            <?php if ($verified): ?>
                <p class="text-soft" style="font-size:.9rem;">
                    <?= e($user['email']) ?> is already confirmed. Nothing more to do.
                </p>
                <a href="<?= url('dashboard.php') ?>" class="btn btn-gold mt-3">Go to dashboard</a>

            <?php elseif (is_logged_in()): ?>
                <p class="text-soft" style="font-size:.9rem;">
                    We sent a confirmation link to <strong class="text-deep"><?= e($user['email']) ?></strong>.
                    Open it to finish setting up your account. Did not get it?
                </p>
                <form method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <button class="btn btn-gold"><i class="fa-solid fa-rotate"></i> Resend the link</button>
                </form>

            <?php else: ?>
                <p class="text-soft" style="font-size:.9rem;">
                    Sign in first, then we can resend your confirmation link.
                </p>
                <a href="<?= url('login.php') ?>" class="btn btn-gold mt-3">Sign in</a>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <p class="text-center text-muted2 mt-4 mb-0" style="font-size:.76rem;">
        <?= EVENT_ORG ?> · <?= EVENT_HOST ?>
    </p>
</div>

<?php require __DIR__ . '/layouts/scripts.php'; ?>

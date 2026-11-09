<?php
/**
 * google.php — "Continue with Google".
 *
 *   google.php            send the student to Google
 *   google.php?code=…     Google sends them back here
 */
require_once __DIR__ . '/includes/config.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'dashboard.php');
}

if (!google_enabled()) {
    set_flash(
        'info',
        'Google sign-in is not switched on yet',
        'The SSC still needs to add its Google client ID in <code>includes/config.php</code>. '
        . 'For now, sign in with your email and password.'
    );
    redirect('login.php');
}

/* --- Step 1: off to Google ------------------------------------------- */
if (!isset($_GET['code']) && !isset($_GET['error'])) {
    if ($wait = rate_limit('google', 10, 300)) {
        set_flash('warning', 'Slow down a little', 'Try again in ' . $wait . ' seconds.');
        redirect('login.php');
    }
    header('Location: ' . google_auth_url());
    exit;
}

/* --- Step 2: back from Google ----------------------------------------- */
$state = (string) ($_GET['state'] ?? '');
$saved = (string) ($_SESSION['google_state'] ?? '');
unset($_SESSION['google_state']);

if (isset($_GET['error'])) {
    set_flash('info', 'Google sign-in cancelled', 'No problem — you can try again or use your email and password.');
    redirect('login.php');
}

if ($saved === '' || !hash_equals($saved, $state)) {
    set_flash('error', 'Security check failed', 'That Google sign-in could not be matched to your session. Please try again.');
    redirect('login.php');
}

$who = google_identity_from_code((string) $_GET['code']);
if (!$who['ok']) {
    set_flash('error', 'Could not sign in with Google', e($who['message']));
    redirect('login.php');
}

$result = google_sign_in($who['email'], $who['name']);

if ($result['state'] === 'signed_in') {
    $user = current_user();
    set_flash('success', 'Welcome, ' . explode(' ', $user['name'])[0] . '!', 'You are signed in with Google as <strong>' . e($user['email']) . '</strong>.');
    redirect('dashboard.php');
}

if ($result['state'] === 'needs_profile') {
    set_flash('success', 'Google account confirmed', 'Just a few details and your account is ready.');
    redirect('register.php');
}

set_flash('error', 'Could not sign in', e($result['message']));
redirect('login.php');

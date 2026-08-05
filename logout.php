<?php
/**
 * logout.php — clears the prototype session.
 *
 * REPLACE LATER: revoke the Google OAuth token / delete the remember-me row
 * before destroying the PHP session.
 */
require_once __DIR__ . '/includes/config.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

/* Start a fresh session purely to carry the goodbye message. */
session_start();
set_flash('success', 'Signed Out', 'You have been signed out of ' . APP_NAME . '. See you at SIGLA 2026!');

header('Location: ' . BASE_URL . 'index.php');
exit;

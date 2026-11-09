<?php
/**
 * includes/google.php — Sign in with Google (OAuth 2.0 authorization-code flow).
 *
 * Nothing here trusts the browser: the code is exchanged server-to-server, the
 * returned ID token is validated by Google's own tokeninfo endpoint, and the
 * address must be a verified one on a university domain.
 *
 * Credentials live in includes/config.php (GOOGLE_CLIENT_ID / _SECRET).
 */

declare(strict_types=1);

function google_enabled(): bool
{
    return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
}

/** Must match an "Authorised redirect URI" in the Google Cloud console. */
function google_redirect_uri(): string
{
    return rtrim(app_base_url(), '/') . '/google.php';
}

/** Where Google should send the student. A random state defeats CSRF. */
function google_auth_url(): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_state'] = $state;

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
        /* Only a hint — the domain is enforced again on our side. */
        'hd'            => ALLOWED_EMAIL_DOMAINS[0],
    ]);
}

/** POST/GET a URL and decode the JSON reply. Returns null on any failure. */
function google_http(string $url, ?array $post = null): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $raw = @file_get_contents($url, false, stream_context_create(['http' => [
            'method'  => $post === null ? 'GET' : 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $post === null ? '' : http_build_query($post),
            'timeout' => 15,
            'ignore_errors' => true,
        ]]));
    }

    $data = is_string($raw) ? json_decode($raw, true) : null;

    return is_array($data) ? $data : null;
}

/**
 * Turn the ?code=… Google sent back into a verified identity.
 *
 * @return array{ok:bool, message:string, email:string, name:string}
 */
function google_identity_from_code(string $code): array
{
    $fail = static fn(string $m) => ['ok' => false, 'message' => $m, 'email' => '', 'name' => ''];

    $token = google_http('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);

    if (!$token || empty($token['id_token'])) {
        return $fail('Google did not accept the sign-in. Please try again.');
    }

    /* Google validates the signature and expiry of its own token. */
    $claims = google_http('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode((string) $token['id_token']));

    if (!$claims || ($claims['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
        return $fail('Google sign-in could not be verified.');
    }
    if (!in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)) {
        return $fail('Google sign-in could not be verified.');
    }
    if (($claims['email_verified'] ?? 'false') !== 'true' && ($claims['email_verified'] ?? false) !== true) {
        return $fail('That Google address is not verified.');
    }

    $email = strtolower(trim((string) ($claims['email'] ?? '')));
    if ($email === '' || !email_domain_allowed($email)) {
        return $fail(domain_rejection_message($email));
    }

    return ['ok' => true, 'message' => 'ok', 'email' => $email, 'name' => trim((string) ($claims['name'] ?? ''))];
}

/**
 * Sign a Google-verified student in, or park them for the profile step.
 *
 * @return array{state:string, message:string}  state: signed_in | needs_profile | denied
 */
function google_sign_in(string $email, string $name): array
{
    $row = db_one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

    if ($row === null) {
        /* First visit: Google proved the address, we still need the SR-code etc. */
        $_SESSION['google_pending'] = ['email' => $email, 'name' => $name];
        return ['state' => 'needs_profile', 'message' => 'Finish your profile to get started.'];
    }

    if ($row['role'] !== 'student') {
        return ['state' => 'denied', 'message' => 'Staff accounts sign in with their password.'];
    }
    if ($row['status'] !== 'active') {
        return ['state' => 'denied', 'message' => 'That account has been disabled. Please contact the SSC.'];
    }

    /* Google already proved the mailbox, so the confirmation email is moot. */
    if ($row['email_verified_at'] === null) {
        db_run('UPDATE users SET email_verified_at = NOW(), verify_token_hash = NULL, verify_expires_at = NULL WHERE id = ?', [$row['id']]);
        $row['email_verified_at'] = date('Y-m-d H:i:s');
    }
    db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$row['id']]);

    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    forget_registration_cache();
    unset($_SESSION['google_pending']);
    $_SESSION['user']         = user_session_row($row);
    $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');

    audit('login.google', 'user', (string) $row['id']);

    return ['state' => 'signed_in', 'message' => 'Signed in with Google.'];
}

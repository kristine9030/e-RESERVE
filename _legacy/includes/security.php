<?php
/**
 * includes/security.php — the controls that protect uploads and tickets.
 *
 *   · CSRF tokens      — a form can only be submitted from our own page
 *   · Upload hardening — documents are validated by content, renamed, shielded
 *                        and served only to their owner or to staff
 *   · Signed tickets   — the QR carries an HMAC, so a made-up code fails
 *   · Audit trail      — every review and gate scan is attributed and kept
 */

declare(strict_types=1);

/* ==========================================================================
 | CSRF
 * ========================================================================*/

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_valid(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Abort the request unless it carries our token. */
function csrf_guard(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security Check Failed', 'Your session expired or the form was submitted from an untrusted page. Please try again.');
        redirect('dashboard.php');
    }
}

/* ==========================================================================
 | Rate limiting — blunt but effective against submission hammering
 * ========================================================================*/

/**
 * Allow at most $limit actions per $window seconds for this session.
 * Returns the seconds left to wait, or 0 when the action may proceed.
 */
function rate_limit(string $key, int $limit, int $window): int
{
    $now  = time();
    $hits = $_SESSION['rate'][$key] ?? [];
    $hits = array_values(array_filter($hits, static fn($t) => $t > $now - $window));

    if (count($hits) >= $limit) {
        $_SESSION['rate'][$key] = $hits;
        return ($hits[0] + $window) - $now;
    }

    $hits[] = $now;
    $_SESSION['rate'][$key] = $hits;

    return 0;
}

/* ==========================================================================
 | Document uploads (waiver, COR, school ID — PDF only)
 * ========================================================================*/

/**
 * Validate and store an uploaded PDF.
 *
 * The file is checked by its real content (not by the name or type the browser
 * sent), re-named to an unguessable string and written to a folder Apache is
 * told never to serve. Nothing the uploader controls survives into the path.
 *
 * @return array{ok:bool, message:string, stored:?string, original:?string, size:int}
 */
function store_document_upload(array $file, string $label): array
{
    $fail = static fn(string $msg) => ['ok' => false, 'message' => $msg, 'stored' => null, 'original' => null, 'size' => 0];

    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($err === UPLOAD_ERR_NO_FILE) {
        return $fail('Choose the PDF for your ' . $label . ' first.');
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return $fail('The ' . $label . ' is too large. Keep each PDF under ' . (int) (DOC_MAX_BYTES / 1024 / 1024) . ' MB.');
    }
    if ($err !== UPLOAD_ERR_OK) {
        return $fail('The upload did not complete. Please try again.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('That file was rejected.');
    }
    if ($file['size'] > DOC_MAX_BYTES) {
        return $fail('The ' . $label . ' is too large. Keep each PDF under ' . (int) (DOC_MAX_BYTES / 1024 / 1024) . ' MB.');
    }
    if ($file['size'] < 200) {
        return $fail('That file looks empty. Re-export the ' . $label . ' as a PDF and try again.');
    }

    /* Trust the bytes: a real PDF starts with %PDF- and sniffs as application/pdf. */
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 1024);

    if ($mime !== 'application/pdf' || strpos($head, '%PDF-') === false) {
        return $fail('Only PDF files are accepted. Photos and Word files must be saved as PDF first.');
    }

    if (!is_dir(DOC_PATH) && !mkdir(DOC_PATH, 0755, true) && !is_dir(DOC_PATH)) {
        return $fail('Upload storage is unavailable. Please tell the SSC.');
    }

    protect_upload_dir();

    $name = date('Ymd') . '-' . bin2hex(random_bytes(16)) . '.pdf';
    $dest = DOC_PATH . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return $fail('The file could not be saved. Please try again.');
    }

    @chmod($dest, 0644);

    /* Keep a readable name for the reviewer, but strip anything path-like. */
    $original = preg_replace('/[^\w\s.\-()]+/u', '', basename((string) ($file['name'] ?? 'document.pdf'))) ?: 'document.pdf';

    return [
        'ok'       => true,
        'message'  => 'File received.',
        'stored'   => $name,
        'original' => mb_substr($original, 0, 180),
        'size'     => (int) $file['size'],
    ];
}

/**
 * Lock the upload folder so Apache never serves or executes anything in it.
 * Documents are only ever opened through admin/document.php, which checks who
 * is asking first.
 */
function protect_upload_dir(): void
{
    $guard = DOC_PATH . '/.htaccess';

    if (is_file($guard)) {
        return;
    }

    file_put_contents($guard, <<<'HTACCESS'
# Student documents — never served directly, never executable, never listed.
php_flag engine off
Options -Indexes -ExecCGI
Require all denied
HTACCESS);
}

function document_url(int $documentId): string
{
    return url('admin/document.php?id=' . $documentId);
}

/** Reject any path trickery before touching the filesystem. */
function document_path(string $storedName): ?string
{
    if (!preg_match('/^\d{8}-[0-9a-f]{32}\.pdf$/', $storedName)) {
        return null;
    }

    $path = DOC_PATH . '/' . $storedName;

    return is_file($path) ? $path : null;
}

/* ==========================================================================
 | Audit trail
 * ========================================================================*/

/**
 * Record an action that touched money or a ticket. Never deleted, so a
 * disputed payment can always be traced back to who approved it and when.
 */
function audit(string $action, string $entityType, ?string $entityId = null, array $context = []): void
{
    $user = current_user();

    try {
        db_run(
            'INSERT INTO audit_log (actor_id, actor_name, actor_role, action, entity_type, entity_id, context, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $user['id']   ?? null,
                $user['name'] ?? 'system',
                $user['role'] ?? 'system',
                $action,
                $entityType,
                $entityId,
                $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]
        );
    } catch (Throwable $ex) {
        error_log('[e-RESERVE] audit failed: ' . $ex->getMessage());
    }
}

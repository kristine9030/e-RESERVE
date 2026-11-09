<?php
/**
 * admin/document.php — serves one student document (PDF).
 *
 * The files live in /uploads/documents, which Apache is told never to serve.
 * Every view goes through this gate instead, so a file is readable only by SSC
 * staff or by the student who uploaded it — never by anyone who guesses a URL.
 *
 *   document.php?id=12            open in the browser
 *   document.php?id=12&download=1 save to disk
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$id  = (int) ($_GET['id'] ?? 0);
$doc = $id > 0 ? db_one('SELECT * FROM documents WHERE id = ?', [$id]) : null;

if ($doc === null) {
    http_response_code(404);
    exit('Not found.');
}

/* Staff see any document; a student sees only their own. */
if (!is_admin() && (int) $doc['user_id'] !== (int) current_user()['id']) {
    http_response_code(403);
    exit('Forbidden.');
}

$path = document_path((string) $doc['stored_name']);
if ($path === null) {
    http_response_code(404);
    exit('The file is no longer on the server.');
}

if (is_admin()) {
    /* Opening a student's document is itself worth a line in the audit trail. */
    audit('document.view', 'document', (string) $id, ['type' => $doc['doc_type']]);
}

$type     = DOCUMENT_TYPES[$doc['doc_type']]['short'] ?? 'document';
$filename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $type . '-' . $doc['user_id']) . '.pdf';
$inline   = empty($_GET['download']);

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
/* No CSP here on purpose: browsers' built-in PDF viewers refuse to render
   under a sandboxing policy. The file is validated as a real PDF at upload and
   nosniff stops it being treated as anything else. */

readfile($path);

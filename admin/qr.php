<?php
/**
 * admin/qr.php — Serves a placeholder QR code as a standalone SVG image.
 *
 * Usage: <img src="qr.php?t=SIGLA-2026-000100&s=170">
 *
 * Keeping the QR out of the tickets table drops that page from ~440 KB to ~40 KB.
 *
 * REPLACE LATER: render a real QR (endroid/qr-code) from the same payload,
 * or point the <img> at your QR service.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$payload = (string) ($_GET['t'] ?? '');
$size    = max(80, min(400, (int) ($_GET['s'] ?? 170)));

if ($payload === '') {
    http_response_code(400);
    exit;
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo qr_placeholder($payload, $size);

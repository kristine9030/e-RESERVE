<?php
/**
 * admin/qr.php — Serves a real, scannable QR code as a standalone SVG image.
 *
 * Usage: <img src="qr.php?t=SIGLA-2026-000100&s=170">
 *
 * Keeping the QR out of the tickets table means the page ships one small <img>
 * per row instead of a few hundred inline SVGs.
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
echo qr_svg($payload, $size, ['label' => $payload]);

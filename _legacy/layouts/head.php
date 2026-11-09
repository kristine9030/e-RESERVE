<?php
/**
 * layouts/head.php — shared <head> block.
 *
 * Usage:  $pageTitle = 'Dashboard';  require 'layouts/head.php';
 * Optional: $pageDesc, $bodyClass
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../includes/config.php';
}
$pageTitle = $pageTitle ?? 'Online Ticket Reservation';
$pageDesc  = $pageDesc ?? 'Register, upload your documents and get your ticket for ' . EVENT_NAME . ' — ' . EVENT_HOST . '.';
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDesc) ?>">
    <meta name="theme-color" content="#2A8DB5">
    <title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>

    <!-- Favicon (inline SVG ticket mark — no external asset needed) -->
    <link rel="icon" type="image/png" href="<?= url(SSC_LOGO_FILE) ?>">

    <!-- Fonts: Plus Jakarta Sans (UI) + Montserrat (legacy fallback) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300..800&family=Montserrat:ital,wght@0,300..900;1,400..800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- e-RESERVE design system -->
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/modern.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/sky.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/glass.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/pages.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/sponsors.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/dash.css') ?>" rel="stylesheet">
    <link href="<?= url('assets/css/student.css') ?>" rel="stylesheet">
</head>
<body class="<?= e($bodyClass) ?>">

<?php
/**
 * layouts/app_header.php — opens the dashboard shell (top navbar + page head).
 * Pair with layouts/app_footer.php.
 *
 * Expects: $pageTitle, $crumb (optional), $activeNav (optional)
 */
$bodyClass = trim(($bodyClass ?? '') . ' role-' . (current_user()['role'] ?? 'guest'));
require __DIR__ . '/head.php';

$user  = current_user() ?? [];
$crumb = $crumb ?? 'e-RESERVE';

require __DIR__ . '/app_nav.php';
?>
<div class="app-shell">

    <header class="page-head no-print">
        <div class="app-container d-flex align-items-center gap-3">
            <div class="flex-grow-1 min-w-0">
                <div class="crumb"><?= e($crumb) ?></div>
                <h1 class="text-truncate"><?= e($pageTitle) ?></h1>
            </div>
            <span class="badge-soft badge-gold d-none d-md-inline-flex">
                <i class="fa-solid fa-calendar-day"></i> <?= EVENT_DATE_TEXT ?>
            </span>
        </div>
    </header>

    <main class="app-content">
        <div class="app-container">

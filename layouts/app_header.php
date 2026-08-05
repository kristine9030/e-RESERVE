<?php
/**
 * layouts/app_header.php — opens the dashboard shell (sidebar + topbar).
 * Pair with layouts/app_footer.php.
 *
 * Expects: $pageTitle, $crumb (optional), $activeNav (optional)
 */
require __DIR__ . '/head.php';
require __DIR__ . '/sidebar.php';

$user  = current_user() ?? [];
$crumb = $crumb ?? 'e-RESERVE';
?>
<div class="app-shell">
    <?php // sidebar is rendered above ?>
    <div class="app-main">

        <header class="topbar">
            <button class="icon-btn sidebar-toggle" data-sidebar-toggle aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="flex-grow-1 min-w-0">
                <div class="crumb"><?= e($crumb) ?></div>
                <h1 class="text-truncate"><?= e($pageTitle) ?></h1>
            </div>

            <div class="d-none d-md-flex align-items-center gap-2">
                <span class="badge-soft badge-gold">
                    <i class="fa-solid fa-calendar-day"></i> <?= EVENT_DATE_TEXT ?>
                </span>
            </div>

            <button class="icon-btn" data-soon="Notification Center" aria-label="Notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="dot"></span>
            </button>

            <div class="dropdown">
                <button class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                    <i class="fa-solid fa-user"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius:16px;min-width:238px;">
                    <li class="px-3 py-2">
                        <div class="fw-800 text-deep" style="font-size:.9rem;"><?= e($user['name'] ?? 'Guest') ?></div>
                        <div class="text-muted2" style="font-size:.76rem;"><?= e($user['email'] ?? '—') ?></div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (($user['role'] ?? 'student') === 'student'): ?>
                        <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="fa-solid fa-id-card me-2 text-gold"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="<?= url('tracking.php') ?>"><i class="fa-solid fa-route me-2 text-gold"></i>Track Reservation</a></li>
                        <li><a class="dropdown-item" href="<?= url('ticket.php') ?>"><i class="fa-solid fa-qrcode me-2 text-gold"></i>My E-Ticket</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="fa-solid fa-gauge-high me-2 text-gold"></i>Admin Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?= url('finance/index.php') ?>"><i class="fa-solid fa-chart-pie me-2 text-gold"></i>Finance Panel</a></li>
                        <li><a class="dropdown-item" href="<?= url('reports/index.php') ?>"><i class="fa-solid fa-folder-open me-2 text-gold"></i>Reports</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"
                           data-confirm-title="Sign out of e-RESERVE?"
                           data-confirm-text="Your prototype session data will be cleared."
                           data-confirm-icon="warning"
                           data-confirm-yes="Sign out">
                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Sign Out
                        </a>
                    </li>
                </ul>
            </div>
        </header>

        <main class="app-content">

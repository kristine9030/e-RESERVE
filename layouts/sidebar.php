<?php
/**
 * layouts/sidebar.php — role-aware application sidebar.
 * Expects: $activeNav (string key), $user (array)
 */
$user      = current_user() ?? [];
$role      = $user['role'] ?? 'student';
$activeNav = $activeNav ?? '';

/** Small helper to render one nav row. */
$link = function (string $key, string $href, string $icon, string $label, string $badge = '') use ($activeNav) {
    $cls = 'side-link' . ($activeNav === $key ? ' active' : '');
    echo '<a class="' . $cls . '" href="' . url($href) . '">';
    echo '<i class="fa-solid ' . $icon . '"></i><span>' . e($label) . '</span>';
    if ($badge !== '') {
        echo '<span class="side-badge">' . e($badge) . '</span>';
    }
    echo '</a>';
};

$reserved = has_reservation();
$paid     = is_paid();
?>
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-head">
        <a class="brand-lockup" href="<?= url('index.php') ?>">
            <span class="brand-mark" style="background:rgba(255,255,255,.1);"><i class="fa-solid fa-ticket"></i></span>
            <span class="brand-text">
                <span class="brand-name d-block">e-<span>RESERVE</span></span>
                <span class="brand-sub">SIGLA 2026</span>
            </span>
        </a>
    </div>

    <div class="sidebar-scroll">
        <?php if ($role === 'student'): ?>

            <div class="nav-section">Main</div>
            <?php
            $link('dashboard', 'dashboard.php', 'fa-gauge-high', 'Dashboard');
            $link('reserve',   'reserve.php',   'fa-calendar-check', 'Reserve Ticket', $reserved ? '' : 'New');
            $link('tracking',  'tracking.php',  'fa-route', 'Track Reservation');
            ?>

            <div class="nav-section">My Ticket</div>
            <?php
            $link('payment', 'payment.php', 'fa-wallet', 'Payment', (!$paid && $reserved) ? 'Due' : '');
            $link('ticket',  'ticket.php',  'fa-qrcode', 'My E-Ticket');
            $link('receipt', 'receipt.php', 'fa-receipt', 'Official Receipt');
            ?>

            <div class="nav-section">Account</div>
            <?php
            $link('profile', 'profile.php', 'fa-id-card', 'My Profile');
            ?>
            <a class="side-link" href="<?= url('index.php') ?>"><i class="fa-solid fa-house"></i><span>Event Page</span></a>

        <?php else: ?>

            <div class="nav-section">Administration</div>
            <?php
            $link('admin',         'admin/index.php',        'fa-gauge-high',      'Dashboard');
            $link('reservations',  'admin/reservations.php', 'fa-calendar-check',  'Reservations');
            $link('payments',      'admin/payments.php',     'fa-credit-card',     'Payments');
            $link('tickets',       'admin/tickets.php',      'fa-ticket',          'Tickets');
            $link('checkin',       'admin/checkin.php',      'fa-qrcode',          'Gate Check-in');
            ?>

            <div class="nav-section">Finance</div>
            <?php
            $link('finance',      'finance/index.php',        'fa-chart-pie',   'Finance Overview');
            $link('transactions', 'finance/transactions.php', 'fa-arrow-right-arrow-left', 'Transactions');
            $link('history',      'finance/history.php',      'fa-clock-rotate-left', 'Payment History');
            ?>

            <div class="nav-section">Reports</div>
            <?php
            $link('reports',            'reports/index.php',        'fa-folder-open', 'Reports Hub');
            $link('report_reservations','reports/reservations.php', 'fa-file-lines',  'Reservations Report');
            $link('report_payments',    'reports/payments.php',     'fa-file-invoice-dollar', 'Payment Report');
            $link('report_attendance',  'reports/attendance.php',   'fa-user-check',  'Attendance Report');
            $link('report_revenue',     'reports/revenue.php',      'fa-sack-dollar', 'Revenue Report');
            ?>

            <div class="nav-section">Other</div>
            <a class="side-link" href="<?= url('dashboard.php') ?>"><i class="fa-solid fa-user-graduate"></i><span>Student View</span></a>
            <a class="side-link" href="<?= url('index.php') ?>"><i class="fa-solid fa-house"></i><span>Event Page</span></a>

        <?php endif; ?>
    </div>

    <div class="sidebar-foot">
        <div class="side-user">
            <span class="avatar"><?= e($user['initials'] ?? 'ST') ?></span>
            <div class="flex-grow-1 min-w-0">
                <div class="u-name text-truncate"><?= e($user['name'] ?? 'Guest') ?></div>
                <div class="u-role"><?= e(ucfirst($role)) ?></div>
            </div>
            <a href="<?= url('logout.php') ?>" class="text-white-50"
               data-bs-toggle="tooltip" title="Sign out"
               data-confirm-title="Sign out of e-RESERVE?"
               data-confirm-text="Your prototype session data will be cleared."
               data-confirm-icon="warning"
               data-confirm-yes="Sign out">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop"></div>

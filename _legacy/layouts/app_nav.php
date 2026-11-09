<?php
/**
 * layouts/app_nav.php — role-aware application top navigation bar.
 *
 * The bar itself is plain text; icons only appear inside the dropdown panels
 * and the account menu, where they help scanning a list.
 *
 * Expects: $activeNav (string key), $user (array)
 */
$user      = current_user() ?? [];
$role      = $user['role'] ?? 'student';
$activeNav = $activeNav ?? '';

/** Renders a top-level nav item. */
$link = function (string $key, string $href, string $label, string $badge = '') use ($activeNav) {
    $cls = 'top-link' . ($activeNav === $key ? ' active' : '');
    echo '<li class="nav-item"><a class="' . $cls . '" href="' . url($href) . '">';
    echo '<span>' . e($label) . '</span>';
    if ($badge !== '') {
        echo '<span class="top-badge">' . e($badge) . '</span>';
    }
    echo '</a></li>';
};

/** Renders one row inside a nav dropdown. */
$item = function (string $key, string $href, string $icon, string $label, string $badge = '') use ($activeNav) {
    $cls = 'dropdown-item app-drop-item' . ($activeNav === $key ? ' active' : '');
    echo '<li><a class="' . $cls . '" href="' . url($href) . '">';
    echo '<i class="fa-solid ' . $icon . '"></i><span>' . e($label) . '</span>';
    if ($badge !== '') {
        echo '<span class="top-badge ms-auto">' . e($badge) . '</span>';
    }
    echo '</a></li>';
};

/** Renders a dropdown parent; $keys marks it active when a child page is open. */
$group = function (string $id, string $label, array $keys, callable $body) use ($activeNav) {
    $on  = in_array($activeNav, $keys, true);
    $cls = 'top-link dropdown-toggle' . ($on ? ' active' : '');
    echo '<li class="nav-item dropdown">';
    echo '<a class="' . $cls . '" href="#" id="' . $id . '" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
    echo '<span>' . e($label) . '</span></a>';
    echo '<ul class="dropdown-menu app-drop" aria-labelledby="' . $id . '">';
    $body();
    echo '</ul></li>';
};

$progress = $role === 'student' ? document_progress() : null;
$hasTicket = $role === 'student' && has_ticket();
?>
<nav class="navbar navbar-expand-xl app-nav" id="appNav">
    <div class="app-container">

        <a class="brand-lockup app-nav-brand" href="<?= url($role === 'student' ? 'dashboard.php' : 'admin/index.php') ?>">
            <img class="brand-logo" src="<?= url(SSC_LOGO_FILE) ?>" alt="Supreme Student Council seal">
            <span class="brand-text">
                <span class="brand-name d-block">e-<span>RESERVE</span></span>
                <span class="brand-sub">SIGLA 2026</span>
            </span>
        </a>

        <button class="navbar-toggler border-0 shadow-none ms-auto" type="button"
                data-bs-toggle="collapse" data-bs-target="#appNavMenu"
                aria-controls="appNavMenu" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="collapse navbar-collapse" id="appNavMenu">
            <ul class="navbar-nav app-nav-list mx-xl-auto">
                <?php if ($role === 'student'): ?>

                    <?php
                    $link('dashboard', 'dashboard.php', 'Dashboard');
                    $link('documents', 'documents.php', 'My Documents',
                        $progress['complete'] ? '' : ($progress['blocked'] ? '!' : $progress['uploaded'] . '/' . $progress['total']));
                    $link('ticket',    'ticket.php',    'My Ticket', $hasTicket ? '' : '');
                    $link('tracking',  'tracking.php',  'Track');
                    $link('profile',   'profile.php',   'My Profile');
                    ?>
                    <li class="nav-item"><a class="top-link" href="<?= url('index.php#guidelines') ?>"><span>Guidelines</span></a></li>

                <?php else: ?>

                    <?php
                    $waiting = (int) db_value('SELECT COUNT(*) FROM documents WHERE status = "submitted"', [], 0);

                    $link('admin',         'admin/index.php',         'Dashboard');
                    $link('registrations', 'admin/registrations.php', 'Students');
                    $link('documents',     'admin/documents.php',     'Review Documents', $waiting > 0 ? (string) $waiting : '');
                    $link('checkin',       'admin/checkin.php',       'Gate Scan');

                    $group('navReports', 'Reports',
                        array_merge(['reports'], array_column(report_catalog(), 'nav')),
                        function () use ($item) {
                            $item('reports', 'reports/index.php', 'fa-folder-open', 'Reports Hub');
                            foreach (report_catalog() as $meta) {
                                $item($meta['nav'], $meta['page'], $meta['icon'], $meta['title']);
                            }
                        });

                    $group('navMore', 'More', [], function () use ($item) {
                        $item('', 'dashboard.php', 'fa-user-graduate', 'Student View');
                        $item('', 'index.php',     'fa-house',         'Event Page');
                    });
                    ?>

                <?php endif; ?>
            </ul>

            <div class="app-nav-tools">
                <button class="icon-btn" data-soon="Notification Center" aria-label="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <span class="dot"></span>
                </button>

                <div class="dropdown">
                    <button class="nav-user" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                        <span class="avatar"><?= e($user['initials'] ?? 'ST') ?></span>
                        <span class="nav-user-meta d-none d-xxl-block">
                            <span class="u-name"><?= e($user['name'] ?? 'Guest') ?></span>
                            <span class="u-role"><?= e($role === 'admin' ? 'SSC Staff' : 'Student') ?></span>
                        </span>
                        <i class="fa-solid fa-chevron-down nav-user-caret d-none d-xxl-block"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end app-drop shadow border-0 mt-2" style="min-width:238px;">
                        <li class="px-3 py-2">
                            <div class="fw-800 text-deep" style="font-size:.9rem;"><?= e($user['name'] ?? 'Guest') ?></div>
                            <div class="text-muted2" style="font-size:.76rem;"><?= e($user['email'] ?? '—') ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($role === 'student'): ?>
                            <li><a class="dropdown-item app-drop-item" href="<?= url('profile.php') ?>"><i class="fa-solid fa-id-card"></i><span>My Profile</span></a></li>
                            <li><a class="dropdown-item app-drop-item" href="<?= url('documents.php') ?>"><i class="fa-solid fa-file-pdf"></i><span>My Documents</span></a></li>
                            <li><a class="dropdown-item app-drop-item" href="<?= url('ticket.php') ?>"><i class="fa-solid fa-qrcode"></i><span>My Ticket</span></a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item app-drop-item" href="<?= url('admin/index.php') ?>"><i class="fa-solid fa-gauge-high"></i><span>Admin Dashboard</span></a></li>
                            <li><a class="dropdown-item app-drop-item" href="<?= url('reports/index.php') ?>"><i class="fa-solid fa-folder-open"></i><span>Reports</span></a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item app-drop-item text-danger" href="<?= url('logout.php') ?>"
                               data-confirm-title="Sign out of e-RESERVE?"
                               data-confirm-text="You can sign back in any time."
                               data-confirm-icon="warning"
                               data-confirm-yes="Sign out">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign Out</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

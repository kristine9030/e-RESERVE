<?php
/** layouts/public_nav.php — navigation for the landing page. */
$onLanding = is_active('index.php');
?>
<nav class="navbar navbar-expand-lg nav-public">
    <div class="container">
        <a class="brand-lockup" href="<?= url('index.php') ?>">
            <img class="brand-logo" src="<?= url(SSC_LOGO_FILE) ?>" alt="Supreme Student Council seal">
            <span class="brand-text">
                <span class="brand-name d-block">e-<span>RESERVE</span></span>
                <span class="brand-sub">SIGLA 2026</span>
            </span>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button"
                data-bs-toggle="collapse" data-bs-target="#publicNav" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars text-deep fs-5"></i>
        </button>

        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="<?= $onLanding ? '#home' : url('index.php#home') ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $onLanding ? '#about' : url('index.php#about') ?>">About SIGLA</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $onLanding ? '#pipeline' : url('index.php#pipeline') ?>">How it works</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $onLanding ? '#guidelines' : url('index.php#guidelines') ?>">Guidelines</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $onLanding ? '#lineup' : url('index.php#lineup') ?>">Lineup</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $onLanding ? '#faq' : url('index.php#faq') ?>">FAQ</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $onLanding ? '#contact' : url('index.php#contact') ?>">Contact</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                <?php if (is_logged_in()): ?>
                    <a href="<?= url(is_admin() ? 'admin/index.php' : 'dashboard.php') ?>" class="btn btn-outline-green btn-sm2">
                        <i class="fa-solid fa-gauge-high"></i> My Dashboard
                    </a>
                    <a href="<?= url('logout.php') ?>" class="btn btn-ghost btn-sm2"
                       data-confirm-title="Sign out of e-RESERVE?"
                       data-confirm-text="You can sign back in any time."
                       data-confirm-icon="warning"
                       data-confirm-yes="Sign out">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= url('login.php') ?>" class="btn btn-ghost btn-sm2">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign In
                    </a>
                    <a href="<?= url('register.php') ?>" class="btn btn-gold btn-sm2">
                        Get my ticket <i class="fa-solid fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php /** layouts/public_footer.php */ ?>
<footer class="site-footer" id="contact">
    <span class="orb orb-gold orb-soft" style="width:360px;height:360px;top:-140px;right:-120px;"></span>

    <div class="container position-relative">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="brand-lockup mb-3">
                    <img class="brand-logo" src="<?= url(SSC_LOGO_FILE) ?>" alt="Supreme Student Council seal">
                    <span class="brand-text">
                        <span class="brand-name d-block text-white">e-<span>RESERVE</span></span>
                        <span class="brand-sub">Register. Upload. Scan. Celebrate.</span>
                    </span>
                </div>
                <p style="font-size:.875rem;max-width:330px;">
                    The official student registration and ticketing system for
                    <strong class="text-white"><?= EVENT_NAME ?></strong>, a flagship event of the
                    70th Founding Anniversary of <?= EVENT_HOST ?>.
                </p>
                <div class="d-flex gap-2 mt-4">
                    <a href="#" class="social-dot" data-soon="Facebook Page"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="social-dot" data-soon="Instagram Profile"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="social-dot" data-soon="X / Twitter"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#" class="social-dot" data-soon="TikTok"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6>Navigate</h6>
                <ul class="footer-links">
                    <li><a href="<?= url('index.php#home') ?>">Home</a></li>
                    <li><a href="<?= url('index.php#about') ?>">About SIGLA</a></li>
                    <li><a href="<?= url('index.php#lineup') ?>">Lineup</a></li>
                    <li><a href="<?= url('index.php#pipeline') ?>">How it works</a></li>
                    <li><a href="<?= url('index.php#guidelines') ?>">Guidelines</a></li>
                    <li><a href="<?= url('index.php#faq') ?>">FAQ</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6>My Account</h6>
                <ul class="footer-links">
                    <li><a href="<?= url('login.php') ?>">Sign In</a></li>
                    <li><a href="<?= url('dashboard.php') ?>">Dashboard</a></li>
                    <li><a href="<?= url('register.php') ?>">Create Account</a></li>
                    <li><a href="<?= url('documents.php') ?>">Upload Documents</a></li>
                    <li><a href="<?= url('ticket.php') ?>">View My Ticket</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h6>Get in Touch</h6>
                <ul class="footer-links">
                    <li>
                        <i class="fa-solid fa-location-dot text-gold me-2"></i>
                        BatStateU TNEU – ARASOF Nasugbu,<br>
                        <span class="ms-4">R. Martinez St., Bucana, Nasugbu, Batangas</span>
                    </li>
                    <li><i class="fa-solid fa-envelope text-gold me-2"></i>
                        <a href="mailto:<?= SSC_EMAIL ?>"><?= SSC_EMAIL ?></a></li>
                    <li><i class="fa-brands fa-facebook text-gold me-2"></i>
                        <a href="<?= SSC_FACEBOOK_URL ?>" target="_blank" rel="noopener">facebook.com/SSCARASOF</a></li>
                    <li><i class="fa-brands fa-facebook-messenger text-gold me-2"></i>
                        <span><?= e(SSC_MESSENGER) ?></span></li>
                </ul>
                <div class="mt-4 p-3" style="background:rgba(255,255,255,.05);border-radius:16px;">
                    <div class="footer-motto"><?= EVENT_MOTTO ?></div>
                    <div style="font-size:.76rem;">Supreme Student Council · ARASOF-Nasugbu</div>
                </div>
            </div>
        </div>

        <div class="footer-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>&copy; <?= date('Y') ?> Supreme Student Council · Batangas State University TNEU. All rights reserved.</span>
            <span>
                <?= APP_NAME ?> <?= APP_VERSION ?> ·
                <a href="#" data-soon="Privacy Policy">Privacy</a> ·
                <a href="#" data-soon="Terms of Use">Terms</a>
            </span>
        </div>
    </div>
</footer>

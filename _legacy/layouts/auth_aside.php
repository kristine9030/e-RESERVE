<?php
/**
 * layouts/auth_aside.php — the artwork panel beside the sign-in / sign-up form.
 * Expects: $asideMode = 'login' | 'register'
 */
$asideMode = $asideMode ?? 'login';
$register  = $asideMode === 'register';
?>
<aside class="auth-aside">
    <span class="aside-orb orb-a"></span>
    <span class="aside-orb orb-b"></span>
    <span class="aside-orb orb-c"></span>
    <span class="aside-crowd"></span>

    <div class="aside-top">
        <a class="brand-lockup" href="<?= url('index.php') ?>">
            <img class="brand-logo" src="<?= url(SSC_LOGO_FILE) ?>" alt="Supreme Student Council seal">
            <span class="brand-text">
                <span class="brand-name d-block text-white">e-<span style="color:var(--gold-400);">RESERVE</span></span>
                <span class="brand-sub" style="color:rgba(255,255,255,.65);">SIGLA 2026</span>
            </span>
        </a>
    </div>

    <div class="aside-mid">
        <span class="pill-glossy aside-pill">
            <i class="fa-solid <?= $register ? 'fa-user-plus' : 'fa-right-to-bracket' ?>"></i>
            <?= $register ? 'One student · one ticket' : 'Welcome back' ?>
        </span>

        <h2 class="aside-title">
            <?php if ($register): ?>
                Join <span>SIGLA</span><br>in three steps.
            <?php else: ?>
                Your ticket to<br><span>A Student’s Night</span>
            <?php endif; ?>
        </h2>
        <p class="aside-lead">
            <?= $register
                ? 'Create your account, upload your waiver, COR and school ID, and your ticket number is issued instantly.'
                : 'Sign in to upload your documents, get your ticket number and keep your QR code in your pocket.' ?>
        </p>

        <!-- floating 3D ticket -->
        <div class="mini-scene" data-parallax-scene>
            <span class="float-el mini-pill" data-depth="40"><i class="fa-solid fa-gift"></i> FREE ENTRY</span>
            <span class="float-el mini-note" data-depth="55"><i class="fa-solid fa-music"></i></span>

            <div class="ticket3d ghost mg"></div>
            <div class="ticket3d main tilt" data-tilt="12" data-tilt-rest="8,-16,-3" data-glare>
                <div class="t3-top">
                    <img class="t3-logo" src="<?= url(SSC_LOGO_FILE) ?>" alt="">
                    <div class="t3-org"><strong>Supreme Student Council</strong><span>ARASOF-Nasugbu</span></div>
                    <span class="t3-admit">ADMIT ONE</span>
                </div>
                <div class="t3-body">
                    <span class="t3-kicker">Official ticket</span>
                    <div class="t3-title">SIGLA <em>A Student’s Night</em> 2026</div>
                    <div class="t3-when">
                        <div><span>Date</span><strong>Nov 24, 2026</strong></div>
                        <div><span>Time</span><strong>5 – 10 PM</strong></div>
                        <div><span>Where</span><strong>HEB Grounds</strong></div>
                    </div>
                </div>
                <div class="t3-stub">
                    <div class="t3-qr"><?= qr_svg('SIGLA-2026-SAMPLE', 120, ['label' => 'Sample ticket']) ?></div>
                    <span class="t3-sample">Sample</span>
                </div>
            </div>
        </div>

        <?php if ($register): ?>
            <ol class="aside-steps">
                <li><span>1</span> Create your account</li>
                <li><span>2</span> Submit 3 required documents</li>
                <li><span>3</span> Get your ticket &amp; scan at the gate</li>
            </ol>
        <?php endif; ?>

        <div class="aside-count" data-countdown="<?= EVENT_DATE_ISO ?>">
            <div><b data-cd="days">00</b><span>Days</span></div>
            <div><b data-cd="hours">00</b><span>Hrs</span></div>
            <div><b data-cd="minutes">00</b><span>Min</span></div>
            <div><b data-cd="seconds">00</b><span>Sec</span></div>
        </div>
    </div>

    <div class="aside-foot">
        <span class="footer-motto d-block mb-1"><?= EVENT_MOTTO ?></span>
        Supreme Student Council · <?= EVENT_HOST ?>
    </div>
</aside>

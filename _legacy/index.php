<?php
/**
 * index.php — Public landing page for e-RESERVE.
 * Hero · Pipeline · Guidelines · About · Lineup · FAQ · Contact
 */
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Official Event Portal';
$stats     = dashboard_stats();
$cta       = url(is_logged_in() ? (is_admin() ? 'admin/index.php' : 'documents.php') : 'register.php');

require __DIR__ . '/layouts/head.php';
require __DIR__ . '/layouts/public_nav.php';
?>

<!-- ======================================================================
     HERO
====================================================================== -->
<section class="hero" id="home" data-parallax-scene>

    <!-- soft colour wells the glass refracts -->
    <span class="glow glow-sky"></span>
    <span class="glow glow-sun"></span>

    <div class="container hero-content">
        <div class="row align-items-center g-5">

            <!-- ============ Copy ============ -->
            <div class="col-lg-6">
                <span class="pill-blue fade-up">
                    <i class="fa-solid fa-fire"></i> 70th Founding Anniversary
                </span>

                <h1 class="hero-title fade-up d1">
                    SIGLA
                    <span class="accent">A Student's Night</span>
                    <span class="hero-year">2026</span>
                </h1>

                <p class="hero-lead fade-up d2">
                    Sign up with your GSuite account, submit your 3 required documents and get your ticket number —
                    then <strong class="text-deep">just scan the QR at the gate.</strong> Free, paperless, no queues.
                </p>

                <div class="d-flex flex-wrap gap-3 fade-up d3">
                    <a href="<?= $cta ?>" class="btn btn-gold btn-lg2 btn-shine">
                        <i class="fa-solid fa-ticket"></i> <?= is_logged_in() ? 'Continue my registration' : 'Get my ticket' ?>
                    </a>
                    <a href="#pipeline" class="btn btn-glass btn-lg2">
                        <i class="fa-solid fa-route"></i> How it works
                    </a>
                </div>

                <div class="hero-chips fade-up d4">
                    <div class="glass-chip"><i class="fa-solid fa-calendar-day"></i><div><span>Date</span><strong><?= EVENT_DATE_TEXT ?></strong></div></div>
                    <div class="glass-chip"><i class="fa-solid fa-clock"></i><div><span>Time</span><strong><?= EVENT_TIME_TEXT ?></strong></div></div>
                    <div class="glass-chip"><i class="fa-solid fa-location-dot"></i><div><span>Where</span><strong><?= EVENT_VENUE ?></strong></div></div>
                </div>
            </div>

            <!-- ============ 3D ticket scene ============ -->
            <div class="col-lg-6">
                <div class="scene" aria-hidden="false">

                    <!-- floating decorations (each drifts at its own depth) -->
                    <span class="float-el el-orb"   data-depth="30"></span>
                    <span class="float-el el-ring"  data-depth="-20"></span>
                    <span class="float-el el-pill pill-1" data-depth="55"><i class="fa-solid fa-gift"></i> FREE ADMISSION</span>
                    <span class="float-el el-pill pill-2" data-depth="40"><i class="fa-solid fa-qrcode"></i> SCAN &amp; GO</span>
                    <span class="float-el el-note n1" data-depth="70"><i class="fa-solid fa-music"></i></span>
                    <span class="float-el el-note n2" data-depth="45"><i class="fa-solid fa-star"></i></span>
                    <span class="float-el el-note n3" data-depth="60"><i class="fa-solid fa-guitar"></i></span>

                    <!-- stacked ghost tickets for depth -->
                    <div class="ticket3d ghost g2"></div>
                    <div class="ticket3d ghost g1"></div>

                    <!-- the main ticket -->
                    <div class="ticket3d main tilt" data-tilt="14" data-tilt-rest="9,-17,-3" data-glare>
                        <div class="t3-top">
                            <img class="t3-logo" src="<?= url(SSC_LOGO_FILE) ?>" alt="Supreme Student Council seal">
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

                            <div class="t3-acts">
                                <?php foreach (EVENT_PERFORMERS as [$act]): ?><span><?= e($act) ?></span><?php endforeach; ?>
                            </div>
                        </div>

                        <div class="t3-stub">
                            <div class="t3-qr"><?= qr_svg('SIGLA-2026-SAMPLE', 120, ['label' => 'Sample ticket']) ?></div>
                            <span class="t3-sample">Sample</span>
                        </div>
                    </div>

                    <!-- countdown, glass, floating in front -->
                    <div class="count-glass" data-countdown="<?= EVENT_DATE_ISO ?>">
                        <div class="count-title"><i class="fa-solid fa-bolt"></i> Doors open in</div>
                        <div class="count-row">
                            <div><b data-cd="days">00</b><span>Days</span></div>
                            <div><b data-cd="hours">00</b><span>Hours</span></div>
                            <div><b data-cd="minutes">00</b><span>Mins</span></div>
                            <div><b data-cd="seconds">00</b><span>Secs</span></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- ======================================================================
     SPONSORS (sliding)
====================================================================== -->
<section class="sponsors" id="sponsors" aria-label="Sponsors">
    <div class="container">
        <div class="sponsors-head reveal">
            <span class="eyebrow"><i class="fa-solid fa-handshake"></i> With thanks to</span>
            <h3>Our sponsors &amp; partners</h3>
        </div>
    </div>

    <div class="marquee" role="list">
        <?php for ($copy = 0; $copy < 2; $copy++): ?>
            <div class="marquee-track" <?= $copy ? 'aria-hidden="true"' : '' ?>>
                <?php foreach (SPONSORS as [$sName, $sLogo, $sIcon]): ?>
                    <div class="sponsor-item" <?= $copy ? '' : 'role="listitem"' ?> title="<?= e($sName) ?>">
                        <?php if ($sLogo): ?>
                            <img src="<?= e(url($sLogo)) ?>" alt="<?= e($sName) ?>" loading="lazy">
                        <?php elseif ($sIcon): ?>
                            <span class="sponsor-mark"><i class="fa-solid <?= $sIcon ?>"></i></span>
                            <span class="sponsor-name"><?= e($sName) ?></span>
                        <?php else: ?>
                            <span class="sponsor-word"><?= e($sName) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>

    <p class="sponsors-cta text-center">
        Want your brand here? <a href="mailto:<?= SSC_EMAIL ?>?subject=SIGLA%202026%20sponsorship">Talk to the SSC</a>
    </p>
</section>

<!-- ======================================================================
     ABOUT SIGLA
====================================================================== -->
<section class="section" id="about" style="position:relative;overflow:hidden;">
    <span class="orb orb-teal orb-soft" style="width:420px;height:420px;top:60px;left:-200px;"></span>

    <div class="container position-relative">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5 reveal">
                <span class="eyebrow"><i class="fa-solid fa-star"></i> About the Event</span>
                <h2 class="section-title">What is <span class="grad-text">SIGLA</span>?</h2>
                <div class="divider-dot"></div>
                <p class="section-lead">
                    <strong class="text-deep">SIGLA: A Student's Night</strong> is the flagship student gathering of the
                    70th Founding Anniversary of Batangas State University — The National Engineering University,
                    ARASOF-Nasugbu Campus.
                </p>
                <p class="text-soft" style="font-size:.9rem;">
                    Organized by the <strong>Supreme Student Council</strong>, the night brings every college, org and
                    year level under one roof for a celebration of talent, culture and campus pride — a living tribute to
                    the council's motto, <em class="text-gold fw-800"><?= EVENT_MOTTO ?></em>
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <?php foreach (event_lineup() as [$act]): ?>
                        <span class="badge-soft badge-info"><i class="fa-solid fa-music"></i> <?= e($act) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="row g-3">
                    <?php foreach (about_highlights() as $i => [$icon, $title, $desc]): ?>
                        <div class="col-md-6 reveal" style="transition-delay:<?= $i * 90 ?>ms;">
                            <div class="card-soft feature-card">
                                <div class="feature-icon"><i class="fa-solid <?= $icon ?>"></i></div>
                                <h5><?= e($title) ?></h5>
                                <p><?= e($desc) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======================================================================
     PIPELINE
====================================================================== -->
<section class="section-tight pipeline-section" id="pipeline">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <span class="eyebrow"><i class="fa-solid fa-route"></i> The pipeline</span>
            <h2 class="section-title mx-auto">From sign-up to the gate in <span class="grad-text">four steps</span></h2>
            <p class="section-lead mx-auto text-center"><?= APP_TAGLINE ?></p>
        </div>

        <div class="pipeline">
            <?php foreach (pipeline_steps() as $i => $step): ?>
                <div class="pipe-step reveal" style="transition-delay:<?= $i * 110 ?>ms;">
                    <div class="pipe-node"><i class="fa-solid <?= $step['icon'] ?>"></i></div>
                    <div class="pipe-card tilt" data-tilt="7">
                        <span class="pipe-num"><?= $step['num'] ?></span>
                        <h5><?= e($step['title']) ?></h5>
                        <p><?= e($step['text']) ?></p>
                        <span class="pipe-tag"><?= e($step['tag']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- The 3 required documents, spelled out -->
        <div class="row g-4 mt-4">
            <?php $n = 0; foreach (DOCUMENT_TYPES as $d): $n++; ?>
                <div class="col-md-4 reveal" style="transition-delay:<?= $n * 90 ?>ms;">
                    <div class="card-soft doc-preview h-100 tilt" data-tilt="6">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="feature-icon mb-0"><i class="fa-solid <?= $d['icon'] ?>"></i></span>
                            <div>
                                <div class="doc-step">Required document <?= $n ?> of 3</div>
                                <h5 class="mb-0" style="font-size:1.02rem;"><?= e($d['label']) ?></h5>
                            </div>
                        </div>
                        <p class="text-soft" style="font-size:.86rem;"><?= e($d['hint']) ?></p>
                        <ul class="doc-rules mb-0">
                            <?php foreach ($d['rules'] as $rule): ?>
                                <li><i class="fa-solid fa-check"></i> <?= e($rule) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5 reveal">
            <a href="<?= $cta ?>" class="btn btn-gold btn-lg2"><i class="fa-solid fa-ticket"></i> Start now — it takes about 10 minutes</a>
        </div>
    </div>
</section>

<!-- ======================================================================
     GUIDELINES
====================================================================== -->
<section class="section" id="guidelines">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4 reveal">
                <span class="eyebrow"><i class="fa-solid fa-book-open"></i> Guidelines</span>
                <h2 class="section-title">Read this <span class="grad-text">before you upload.</span></h2>
                <div class="divider-dot"></div>
                <p class="section-lead">
                    Everything you need to get through verification the first time — who can join, what each
                    document must show, file rules, how your ticket works and what to expect at the gate.
                </p>

                <div class="guide-note mt-4">
                    <i class="fa-solid fa-lightbulb"></i>
                    <div>
                        <strong>Tip:</strong> most returned documents are blurry, cropped or unsigned.
                        A flat, well-lit scan avoids almost every problem.
                    </div>
                </div>

                <a href="<?= $cta ?>" class="btn btn-green mt-4"><i class="fa-solid fa-arrow-right"></i> I’m ready</a>
            </div>

            <div class="col-lg-8 reveal">
                <div class="guide-card">
                    <div class="guide-tabs" role="tablist">
                        <?php $first = true; foreach (guidelines() as $key => $g): ?>
                            <button class="guide-tab <?= $first ? 'active' : '' ?>" type="button" role="tab"
                                    data-bs-toggle="pill" data-bs-target="#guide-<?= $key ?>"
                                    aria-selected="<?= $first ? 'true' : 'false' ?>">
                                <i class="fa-solid <?= $g['icon'] ?>"></i> <span><?= e($g['label']) ?></span>
                            </button>
                        <?php $first = false; endforeach; ?>
                    </div>

                    <div class="tab-content guide-body">
                        <?php $first = true; foreach (guidelines() as $key => $g): ?>
                            <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="guide-<?= $key ?>" role="tabpanel">
                                <div class="row g-3">
                                    <?php foreach ($g['items'] as [$icon, $title, $text]): ?>
                                        <div class="col-md-6">
                                            <div class="guide-item">
                                                <span class="guide-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                                                <div>
                                                    <h6><?= e($title) ?></h6>
                                                    <p><?= e($text) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>

                <!-- Hard rules, always visible -->
                <div class="row g-3 mt-1">
                    <?php foreach ([
                        ['fa-circle-xmark',  'danger', 'Not accepted',  'Photos (JPG/PNG), Word files, screenshots of documents, edited or expired IDs.'],
                        ['fa-circle-check',  'success','Accepted',      'A single clear PDF per requirement, up to 5 MB, showing your own name.'],
                        ['fa-user-slash',    'warning','Zero tolerance','Using someone else’s documents voids the ticket and is referred to Student Affairs.'],
                    ] as [$icon, $tone, $title, $text]): ?>
                        <div class="col-md-4">
                            <div class="rule-pill rule-<?= $tone ?>">
                                <i class="fa-solid <?= $icon ?>"></i>
                                <div><strong><?= e($title) ?></strong><span><?= e($text) ?></span></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======================================================================
     LINEUP
====================================================================== -->
<section class="section-tight" id="lineup">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5 reveal">
                <span class="eyebrow"><i class="fa-solid fa-music"></i> The lineup</span>
                <h2 class="section-title">Who’s <span class="grad-text">performing</span></h2>
                <div class="divider-dot"></div>
                <p class="section-lead">
                    One evening of dance and live music, right on campus.
                </p>

                <div class="event-facts mt-4">
                    <div class="fact"><i class="fa-solid fa-calendar-day"></i><div><span>Date</span><strong><?= EVENT_DATE_TEXT ?></strong></div></div>
                    <div class="fact"><i class="fa-solid fa-clock"></i><div><span>Time</span><strong><?= EVENT_TIME_TEXT ?></strong></div></div>
                    <div class="fact"><i class="fa-solid fa-location-dot"></i><div><span>Where</span><strong><?= EVENT_VENUE ?></strong></div></div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="row g-3">
                    <?php foreach (event_lineup() as $i => [$name, $kind, $icon, $photo]): ?>
                        <div class="col-sm-6 reveal" style="transition-delay:<?= $i * 90 ?>ms;">
                            <article class="artist-card tilt" data-tilt="7">
                                <?php if ($photo): ?>
                                    <div class="artist-photo" style="background-image:url('<?= e(url($photo)) ?>');" role="img" aria-label="<?= e($name) ?>"></div>
                                <?php else: ?>
                                    <div class="artist-photo artist-placeholder" role="img" aria-label="<?= e($name) ?> — photo coming soon">
                                        <i class="fa-solid <?= $icon ?>"></i><span>Photo coming soon</span>
                                    </div>
                                <?php endif; ?>
                                <div class="artist-info">
                                    <span class="artist-tag"><i class="fa-solid <?= $icon ?>"></i> <?= e($kind) ?></span>
                                    <h5><?= e($name) ?></h5>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======================================================================
     FAQ
====================================================================== -->
<section class="section" id="faq">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4 reveal">
                <span class="eyebrow"><i class="fa-solid fa-circle-question"></i> Support</span>
                <h2 class="section-title">Frequently Asked Questions</h2>
                <div class="divider-dot"></div>
                <p class="section-lead">
                    Can't find what you're looking for? The SSC office is open on weekdays and replies to email within
                    one working day.
                </p>
                <a href="#contact" class="btn btn-outline-green mt-2">
                    <i class="fa-solid fa-headset"></i> Contact the SSC
                </a>
            </div>

            <div class="col-lg-8 reveal">
                <div class="accordion faq" id="faqAccordion">
                    <?php foreach (faqs() as $i => [$q, $a]): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>">
                                    <?= e($q) ?>
                                </button>
                            </h2>
                            <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                                 data-bs-parent="#faqAccordion">
                                <div class="accordion-body"><?= e($a) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======================================================================
     CTA
====================================================================== -->
<section class="pb-5">
    <div class="container">
        <div class="cta-band reveal">
            <div class="row align-items-center g-4 position-relative">
                <div class="col-lg-8">
                    <span class="eyebrow on-dark mb-3"><i class="fa-solid fa-bolt"></i> Free for every student</span>
                    <h2 class="mt-3 mb-2" style="font-size:clamp(1.6rem,3vw,2.4rem);">
                        Your ticket to SIGLA 2026 is 3 required documents away.
                    </h2>
                    <p class="mb-0" style="color:rgba(255,255,255,.72);max-width:560px;">
                        Create your account, submit your 3 required documents and get your QR-coded ticket the moment the last one is in.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?= $cta ?>" class="btn btn-gold btn-lg2 mb-2">
                        <i class="fa-solid fa-ticket"></i> Get my ticket
                    </a><br>
                    <a href="<?= url('login.php?panel=admin') ?>" class="btn btn-outline-light2 btn-sm2">
                        <i class="fa-solid fa-user-shield"></i> Staff / Admin Panel
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require __DIR__ . '/layouts/public_footer.php';
require __DIR__ . '/layouts/scripts.php';

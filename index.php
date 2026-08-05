<?php
/**
 * index.php — Public landing page for e-RESERVE.
 * Hero · Countdown · About · Schedule · Tickets · FAQ · Contact
 */
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Official Event Portal';
$stats     = dashboard_stats();
$remaining = max(0, EVENT_CAPACITY - $stats['paid_tickets']);

require __DIR__ . '/layouts/head.php';
require __DIR__ . '/layouts/public_nav.php';
?>

<!-- ======================================================================
     HERO
====================================================================== -->
<section class="hero" id="home">
    <span class="orb hero-orb-1 float-slow"></span>
    <span class="orb hero-orb-2"></span>
    <span class="orb hero-orb-3"></span>
    <span class="orb hero-orb-4 float-slow"></span>

    <div class="container hero-content">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="eyebrow fade-up">
                    <i class="fa-solid fa-fire"></i> 70th Founding Anniversary
                </span>

                <h1 class="hero-title fade-up d1">
                    SIGLA
                    <span class="accent">A Student's Night</span>
                    <span class="d-block" style="font-size:.44em;letter-spacing:-.5px;color:var(--ink-soft);font-weight:800;">2026</span>
                </h1>

                <div class="hero-plate fade-up d2 mt-4">
                    <p class="plate-title">Supreme Student Council</p>
                </div>
                <p class="fw-800 text-green mb-0 fade-up d2" style="font-size:.86rem;">
                    The highest governing student body of<br>
                    <?= EVENT_HOST ?>
                </p>

                <p class="hero-lead fade-up d3">
                    One night. One campus. One <em>sigla</em>. Reserve your slot online, pay through GCash,
                    and walk straight into the gym with a QR code on your phone —
                    <strong class="text-deep">no queues, no paper stubs, no manual lists.</strong>
                </p>

                <div class="d-flex flex-wrap gap-3 fade-up d4">
                    <a href="<?= url(is_logged_in() ? 'reserve.php' : 'login.php?next=reserve') ?>" class="btn btn-gold btn-lg2">
                        <i class="fa-solid fa-ticket"></i> Reserve Now
                    </a>
                    <a href="#tickets" class="btn btn-outline-green btn-lg2">
                        <i class="fa-solid fa-circle-info"></i> Ticket Details
                    </a>
                </div>

                <div class="hero-meta fade-up d5">
                    <div class="hero-meta-item">
                        <span class="hero-meta-icon"><i class="fa-solid fa-calendar-day"></i></span>
                        <div>
                            <div class="hero-meta-label">Event Date</div>
                            <div class="hero-meta-value"><?= EVENT_DATE_TEXT ?></div>
                        </div>
                    </div>
                    <div class="hero-meta-item">
                        <span class="hero-meta-icon"><i class="fa-solid fa-clock"></i></span>
                        <div>
                            <div class="hero-meta-label">Call Time</div>
                            <div class="hero-meta-value"><?= EVENT_TIME_TEXT ?></div>
                        </div>
                    </div>
                    <div class="hero-meta-item">
                        <span class="hero-meta-icon"><i class="fa-solid fa-location-dot"></i></span>
                        <div>
                            <div class="hero-meta-label">Venue</div>
                            <div class="hero-meta-value">ARASOF-Nasugbu Gym</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ticket-shaped hero card with the live countdown -->
            <div class="col-lg-5">
                <div class="hero-ticket fade-up d3" data-countdown="<?= EVENT_DATE_ISO ?>">
                    <span class="ticket-notch left"></span>
                    <span class="ticket-notch right"></span>

                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <span class="eyebrow on-dark mb-2"><i class="fa-solid fa-bolt"></i> Live Countdown</span>
                            <h4 class="text-white mb-0 mt-2" style="font-size:1.08rem;">Doors open in</h4>
                        </div>
                        <i class="fa-solid fa-ticket fs-3" style="color:var(--gold-400);opacity:.65;"></i>
                    </div>

                    <div class="countdown-wrap mb-4">
                        <div class="cd-box"><div class="cd-num" data-cd="days">00</div><div class="cd-label">Days</div></div>
                        <div class="cd-box"><div class="cd-num" data-cd="hours">00</div><div class="cd-label">Hours</div></div>
                        <div class="cd-box"><div class="cd-num" data-cd="minutes">00</div><div class="cd-label">Minutes</div></div>
                        <div class="cd-box"><div class="cd-num" data-cd="seconds">00</div><div class="cd-label">Seconds</div></div>
                    </div>

                    <div class="d-flex align-items-end justify-content-between mb-3">
                        <div>
                            <div class="cd-label mb-1">General Admission</div>
                            <div class="price"><?= peso(TICKET_PRICE, false) ?><small> PHP</small></div>
                        </div>
                        <span class="badge-soft badge-gold"><i class="fa-solid fa-circle-check"></i> Selling Fast</span>
                    </div>

                    <div class="ticket-rows pt-3">
                        <div class="ticket-row"><span class="k">Slots remaining</span><span class="v"><?= number_format($remaining) ?> of <?= number_format(EVENT_CAPACITY) ?></span></div>
                        <div class="ticket-row"><span class="k">Payment channel</span><span class="v">GCash via PayMongo</span></div>
                        <div class="ticket-row"><span class="k">Limit</span><span class="v">1 ticket / student</span></div>
                    </div>

                    <a href="<?= url(is_logged_in() ? 'reserve.php' : 'login.php?next=reserve') ?>" class="btn btn-gold btn-block2 mt-4">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Reserve My Slot
                    </a>
                    <p class="text-center mb-0 mt-3" style="font-size:.72rem;color:rgba(255,255,255,.5);">
                        <i class="fa-solid fa-lock me-1"></i> Sign in with your BatStateU GSuite account
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======================================================================
     STAT STRIP
====================================================================== -->
<section class="py-4" style="background:var(--white);border-top:1px solid var(--line);border-bottom:1px solid var(--line);">
    <div class="container">
        <div class="row g-4 text-center">
            <?php
            $strip = [
                ['fa-users',        number_format(EVENT_CAPACITY), 'Student Capacity'],
                ['fa-ticket',       number_format($stats['paid_tickets']), 'Tickets Issued'],
                ['fa-graduation-cap','12', 'Participating Programs'],
                ['fa-clock',        '6 Hours', 'Of Non-stop Program'],
            ];
            foreach ($strip as $i => [$icon, $value, $label]): ?>
                <div class="col-6 col-lg-3 reveal">
                    <i class="fa-solid <?= $icon ?> text-gold fs-4 mb-2"></i>
                    <div class="fw-900 text-deep" style="font-size:1.5rem;letter-spacing:-1px;"><?= $value ?></div>
                    <div class="text-muted2 fw-800" style="font-size:.68rem;letter-spacing:1.4px;text-transform:uppercase;"><?= $label ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
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
                    Organised by the <strong>Supreme Student Council</strong>, the night brings every college, org and
                    year level under one roof for a celebration of talent, culture and campus pride — a living tribute to
                    the council's motto, <em class="text-gold fw-800"><?= EVENT_MOTTO ?></em>
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <span class="badge-soft badge-success"><i class="fa-solid fa-check"></i> Battle of the Bands</span>
                    <span class="badge-soft badge-gold"><i class="fa-solid fa-check"></i> Dance Face-off</span>
                    <span class="badge-soft badge-info"><i class="fa-solid fa-check"></i> Guest Artist</span>
                    <span class="badge-soft badge-muted"><i class="fa-solid fa-check"></i> Afterparty</span>
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
     HOW IT WORKS
====================================================================== -->
<section class="section-tight" style="background:var(--green-050);">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <span class="eyebrow"><i class="fa-solid fa-route"></i> How it works</span>
            <h2 class="section-title mx-auto">Four steps to your ticket</h2>
            <p class="section-lead mx-auto text-center"><?= APP_TAGLINE ?></p>
        </div>

        <div class="row g-4">
            <?php
            $steps = [
                ['01', 'fa-right-to-bracket', 'Sign In',  'Use your @g.batstate-u.edu.ph GSuite account. No extra registration needed.'],
                ['02', 'fa-calendar-check',   'Reserve',  'Claim your slot in one click. The system enforces one ticket per student.'],
                ['03', 'fa-mobile-screen',    'Pay',      'Settle ' . peso(TICKET_PRICE) . ' through GCash, processed by PayMongo.'],
                ['04', 'fa-qrcode',           'Celebrate','Your e-ticket and receipt generate instantly. Scan in at the gate.'],
            ];
            foreach ($steps as $i => [$num, $icon, $title, $desc]): ?>
                <div class="col-md-6 col-lg-3 reveal" style="transition-delay:<?= $i * 100 ?>ms;">
                    <div class="card-soft feature-card h-100 position-relative">
                        <span class="fw-900" style="position:absolute;top:1.1rem;right:1.4rem;font-size:2.4rem;color:var(--green-050);letter-spacing:-2px;"><?= $num ?></span>
                        <div class="feature-icon"><i class="fa-solid <?= $icon ?>"></i></div>
                        <h5><?= $title ?></h5>
                        <p><?= $desc ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ======================================================================
     TICKETS / PRICE
====================================================================== -->
<section class="section" id="tickets">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6 reveal">
                <span class="eyebrow"><i class="fa-solid fa-tag"></i> Ticket Price</span>
                <h2 class="section-title">One ticket. <span class="grad-text">Everything included.</span></h2>
                <div class="divider-dot"></div>
                <p class="section-lead">
                    A single General Admission ticket covers the whole six-hour program, from the opening rites
                    to the afterparty. No tiers, no hidden add-ons.
                </p>

                <ul class="list-unstyled mt-4">
                    <?php
                    $incl = [
                        'Full access to all SIGLA 2026 segments',
                        'Free entry to the post-program afterparty',
                        'Official e-ticket with QR-coded gate entry',
                        'Downloadable, printable official receipt',
                        'Real-time reservation tracking on your dashboard',
                    ];
                    foreach ($incl as $item): ?>
                        <li class="d-flex align-items-start gap-3 mb-3">
                            <span class="badge-soft badge-success" style="width:26px;height:26px;justify-content:center;padding:0;border-radius:50%;flex:0 0 auto;">
                                <i class="fa-solid fa-check" style="font-size:.62rem;"></i>
                            </span>
                            <span class="fw-600 text-soft" style="font-size:.9rem;"><?= e($item) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-lg-6 reveal">
                <div class="card-soft p-0 overflow-hidden" style="border-radius:var(--radius-lg);">
                    <div class="p-4 p-lg-5 text-center" style="background:var(--grad-green);color:#fff;position:relative;overflow:hidden;">
                        <span class="orb orb-gold" style="width:280px;height:280px;top:-140px;right:-90px;opacity:.28;"></span>
                        <div class="position-relative">
                            <span class="eyebrow on-dark mb-3"><i class="fa-solid fa-ticket"></i> General Admission</span>
                            <div class="mt-3" style="font-size:4rem;font-weight:900;line-height:1;letter-spacing:-3px;color:var(--gold-400);">
                                <?= peso(TICKET_PRICE) ?>
                            </div>
                            <p class="mb-0 mt-2" style="color:rgba(255,255,255,.65);font-size:.84rem;">
                                Per student · Inclusive of all segments
                            </p>
                        </div>
                    </div>

                    <div class="p-4 p-lg-5">
                        <div class="receipt-row"><span class="k">Event</span><span class="v"><?= EVENT_NAME ?></span></div>
                        <div class="receipt-row"><span class="k">Date &amp; Time</span><span class="v"><?= EVENT_DATE_TEXT ?><br><?= EVENT_TIME_TEXT ?></span></div>
                        <div class="receipt-row"><span class="k">Venue</span><span class="v"><?= EVENT_VENUE ?></span></div>
                        <div class="receipt-row"><span class="k">Payment</span><span class="v">GCash · PayMongo</span></div>
                        <div class="receipt-row"><span class="k">Reservation limit</span><span class="v">1 ticket per student</span></div>

                        <a href="<?= url(is_logged_in() ? 'reserve.php' : 'login.php?next=reserve') ?>" class="btn btn-green btn-block2 btn-lg2 mt-4">
                            <i class="fa-solid fa-ticket"></i> Reserve Now
                        </a>
                        <p class="text-center text-muted2 mb-0 mt-3" style="font-size:.74rem;">
                            <i class="fa-solid fa-shield-halved me-1 text-green"></i>
                            Secured checkout · Your slot is held until payment
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======================================================================
     SCHEDULE
====================================================================== -->
<section class="section-tight" id="schedule" style="background:var(--white);border-top:1px solid var(--line);">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4 reveal">
                <span class="eyebrow"><i class="fa-solid fa-calendar-days"></i> Programme</span>
                <h2 class="section-title">Event Schedule</h2>
                <div class="divider-dot"></div>
                <p class="section-lead">
                    A full night of student-led performances. Gates open two hours before the opening rites — come early,
                    scanning is fastest before 5:30 PM.
                </p>
                <div class="card-soft p-4 mt-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="feature-icon mb-0" style="width:44px;height:44px;font-size:1rem;"><i class="fa-solid fa-location-dot"></i></span>
                        <div>
                            <div class="info-label">Venue</div>
                            <div class="info-value" style="font-size:.85rem;"><?= EVENT_VENUE ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="feature-icon mb-0" style="width:44px;height:44px;font-size:1rem;"><i class="fa-solid fa-shirt"></i></span>
                        <div>
                            <div class="info-label">Dress Code</div>
                            <div class="info-value" style="font-size:.85rem;">Smart casual · Green &amp; Gold</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 reveal">
                <div class="card-soft p-4 p-lg-5">
                    <ul class="sched-list">
                        <?php foreach (event_schedule() as [$time, $title, $desc, $icon]): ?>
                            <li class="sched-item">
                                <div class="sched-time"><?= e($time) ?></div>
                                <div class="sched-icon"><i class="fa-solid <?= $icon ?>"></i></div>
                                <div class="sched-body">
                                    <h6><?= e($title) ?></h6>
                                    <p><?= e($desc) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
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
                    <span class="eyebrow on-dark mb-3"><i class="fa-solid fa-bolt"></i> Limited Slots</span>
                    <h2 class="mt-3 mb-2" style="font-size:clamp(1.6rem,3vw,2.4rem);">
                        Only <?= number_format($remaining) ?> slots left for SIGLA 2026.
                    </h2>
                    <p class="mb-0" style="color:rgba(255,255,255,.72);max-width:560px;">
                        Reserve in seconds, pay through GCash, and get your QR-coded e-ticket the moment payment clears.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?= url(is_logged_in() ? 'reserve.php' : 'login.php?next=reserve') ?>" class="btn btn-gold btn-lg2 mb-2">
                        <i class="fa-solid fa-ticket"></i> Reserve Now
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

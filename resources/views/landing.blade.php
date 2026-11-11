@extends('layouts.public')
@section('title', 'Official Event Portal')
@section('bodyClass', 'landing-page')

@section('content')
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
                    SIGLA 2026:
                    <span class="accent">A Student's Night</span>
                </h1>

                <p class="hero-lead fade-up d2">
                    Sign up, upload your required documents and get your ticket number —
                    then <strong class="text-deep">just scan the QR at the gate.</strong> Free, paperless, no queues.
                </p>

                <div class="d-flex flex-wrap gap-3 fade-up d3">
                    @if (is_logged_in() || student_access_open())
                        <a href="{{ $cta }}" class="btn btn-gold btn-lg2 btn-shine">
                            <i class="fa-solid fa-ticket"></i> {{ is_logged_in() ? 'Continue my registration' : 'Get my ticket' }}
                        </a>
                    @else
                        <span class="btn btn-gold btn-lg2 btn-shine" style="opacity:.65;pointer-events:none;"
                              title="Sign-in and account creation open on {{ student_access_opens_at() }}">
                            <i class="fa-solid fa-hourglass-half"></i> Registration opens {{ student_access_opens_at('M j, Y') }}
                        </span>
                    @endif
                    <a href="#guidelines" class="btn btn-glass btn-lg2">
                        <i class="fa-solid fa-route"></i> How it works
                    </a>
                </div>

                @if ($showSlots)
                @php
                    $slotCap  = max(1, (int) ($stats['capacity'] ?? ev('capacity')));
                    $slotLeft = max(0, $slotCap - (int) ($stats['tickets'] ?? 0));
                    $slotPct  = min(100, round(($slotCap - $slotLeft) / $slotCap * 100));
                @endphp
                <div class="slot-meter fade-up d4 {{ $slotLeft === 0 ? 'is-full' : ($slotPct >= 80 ? 'is-low' : '') }}" role="status">
                    <div class="slot-top">
                        <span><i class="fa-solid fa-ticket"></i> {{ $slotLeft === 0 ? 'All tickets claimed' : 'Slots available' }}</span>
                        <strong><b>{{ number_format($slotLeft) }}</b> / {{ number_format($slotCap) }} left</strong>
                    </div>
                    <div class="slot-bar" aria-hidden="true"><i style="width:{{ $slotPct }}%"></i></div>
                </div>
                @endif

                <div class="hero-chips fade-up d4">
                    <div class="glass-chip"><i class="fa-solid fa-calendar-day"></i><div><span>Date</span><strong>{{ ev('date_text') }}</strong></div></div>
                    <div class="glass-chip"><i class="fa-solid fa-clock"></i><div><span>Time</span><strong>{{ ev('time_text') }}</strong></div></div>
                    <div class="glass-chip"><i class="fa-solid fa-location-dot"></i><div><span>Where</span><strong>{{ ev('venue') }}</strong></div></div>
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
                            <img class="t3-logo" src="{{ asset(ssc('logo_small')) }}" alt="Supreme Student Council seal">
                            <div class="t3-org"><strong>Supreme Student Council</strong><span>ARASOF-Nasugbu</span></div>
                            <span class="t3-admit">ADMIT ONE</span>
                        </div>

                        <div class="t3-body">
                            <span class="t3-kicker">Official ticket</span>
                            <div class="t3-title">SIGLA 2026: <em>A Student’s Night</em></div>

                            <div class="t3-when">
                                <div><span>Date</span><strong>Nov 26, 2026</strong></div>
                                <div><span>Time</span><strong>5 – 10 PM</strong></div>
                                <div><span>Where</span><strong>HEB Grounds</strong></div>
                            </div>

                            <div class="t3-acts">
                                @foreach (config('ereserve.performers') as [$act])<span>{{ $act }}</span>@endforeach
                            </div>
                        </div>

                        <div class="t3-stub">
                            <div class="t3-qr">{!! qr_svg('SIGLA-2026-SAMPLE', 120, ['cache' => true, 'label' => 'Sample ticket']) !!}</div>
                            <span class="t3-sample">Sample</span>
                        </div>
                    </div>

                    <!-- countdown, glass, floating in front -->
                    <div class="count-glass" data-countdown="{{ ev('date_iso') }}">
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
     WHO CAN JOIN
====================================================================== -->
<section class="section-tight who-join" id="who-can-join" aria-label="Who can join">
    <div class="container">
        <div class="text-center mb-4 reveal">
            <span class="eyebrow"><i class="fa-solid fa-users"></i> Who can join</span>
            <h2 class="section-title">Open to the <span class="grad-text">ARASOF-Nasugbu</span> community</h2>
        </div>

        <div class="row g-3 g-lg-4 justify-content-center">
            @foreach ([
                ['fa-user-graduate', 'Currently enrolled students', 'Students of BatStateU The NEU ARASOF-Nasugbu who are enrolled this semester, with an active university GSuite account.', 'Upload: waiver, COR and school ID', ['as' => 'student'], 'Register as a student'],
                ['fa-briefcase', 'Faculty, instructors & staff', 'BatStateU The NEU ARASOF-Nasugbu faculty, instructors and staff, using their university email.', 'Upload: your employee ID (one PDF)', ['as' => 'employee'], 'Register as faculty or staff'],
                ['fa-handshake', 'Sponsors', 'Companies and partners supporting SIGLA 2026. Any email works; you confirm it by link.', 'Upload: proof of sponsorship (one PDF)', ['as' => 'sponsor'], 'Register as a sponsor'],
            ] as $i => [$jIcon, $jTitle, $jDesc, $jUpload, $jQuery, $jCta])
                <div class="col-md-4 reveal" style="transition-delay:{{ $i * 90 }}ms;">
                    <div class="card-soft feature-card join-card h-100">
                        <div class="feature-icon"><i class="fa-solid {{ $jIcon }}"></i></div>
                        <h5>{{ $jTitle }}</h5>
                        <p>{{ $jDesc }}</p>
                        <span class="badge-soft badge-gold"><i class="fa-solid fa-file-pdf"></i> {{ $jUpload }}</span>
                        @if (student_access_open())
                            <a href="{{ route('register', $jQuery) }}" class="join-link">{{ $jCta }} <i class="fa-solid fa-arrow-right"></i></a>
                        @else
                            <span class="join-link" style="cursor:default;opacity:.8">
                                <i class="fa-solid fa-hourglass-half"></i> Opens {{ student_access_opens_at('M j, Y') }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-center cell-sub mt-3 mb-0">One person, one ticket. Tickets are not transferable.</p>
    </div>
</section>

<!-- ======================================================================
     SPONSORS (sliding)
====================================================================== -->
@if ($showSponsors)
<section class="sponsors" id="sponsors" aria-label="Sponsors">
    <div class="container">
        <div class="sponsors-head reveal">
            <span class="eyebrow"><i class="fa-solid fa-handshake"></i> With thanks to</span>
            <h3>Our sponsors &amp; partners</h3>
        </div>
    </div>

    @if ($sponsors)
    <div class="marquee" role="list">
        @for ($copy = 0; $copy < 2; $copy++)
            <div class="marquee-track" {{ $copy ? 'aria-hidden="true"' : '' }}>
                @foreach ($sponsors as [$sName, $sLogo, $sIcon, $sUrl])
                    <{{ $sUrl ? 'a href="' . e($sUrl) . '" target="_blank" rel="noopener nofollow"' : 'div' }} class="sponsor-item" {{ $copy ? '' : 'role="listitem"' }} title="{{ $sName }}">
                        @if ($sLogo)
                            <img src="{{ asset($sLogo) }}" alt="{{ $sName }}" loading="lazy">
                        @elseif ($sIcon)
                            <span class="sponsor-mark"><i class="fa-solid {{ $sIcon }}"></i></span>
                            <span class="sponsor-name">{{ $sName }}</span>
                        @else
                            <span class="sponsor-word">{{ $sName }}</span>
                        @endif
                    </{{ $sUrl ? 'a' : 'div' }}>
                @endforeach
            </div>
        @endfor
    </div>
    @else
    <div class="container">
        <div class="sponsors-empty reveal">
            <i class="fa-solid fa-handshake-angle"></i>
            <strong>Sponsors will be announced soon</strong>
            <span>SIGLA 2026 is open to partners who want to be part of A Student’s Night.</span>
        </div>
    </div>
    @endif

    <p class="sponsors-cta text-center">
        Want your brand here? <a href="mailto:{{ ssc('email') }}?subject=SIGLA%202026%20sponsorship">Talk to the SSC</a>
    </p>
</section>
@endif

<!-- ======================================================================
     BECOME A SPONSOR
====================================================================== -->
<section class="become-sponsor" id="become-a-sponsor" aria-label="Become a sponsor">
    <div class="container">
        <div class="bs-card reveal">
            <div class="bs-text">
                <span class="eyebrow"><i class="fa-solid fa-handshake-angle"></i> Partner with us</span>
                <h3>Want to sponsor SIGLA 2026?</h3>
                <p>
                    Put your brand in front of students, faculty and staff
                    at <strong>{{ ev('venue') }}</strong> on <strong>{{ ev('date_text') }}</strong>. Send the SSC a message and we will get back to you with the details.
                </p>
                <ul class="bs-points">
                    @if ($showSponsors)<li><i class="fa-solid fa-circle-check"></i> Your logo in the sponsor strip on this page</li>@endif
                    <li><i class="fa-solid fa-circle-check"></i> Your brand behind the 70th Founding Anniversary student night</li>
                    <li><i class="fa-solid fa-circle-check"></i> A direct line to the SSC officers — no middlemen</li>
                </ul>
            </div>
            <div class="bs-actions">
                <button type="button" class="btn btn-gold btn-lg2" data-bs-toggle="modal" data-bs-target="#sponsorModal">
                    <i class="fa-solid fa-paper-plane"></i> Become a sponsor
                </button>
                <a class="btn btn-ghost btn-lg2" href="{{ ssc('facebook') }}" target="_blank" rel="noopener">
                    <i class="fa-brands fa-facebook"></i> Message us on Facebook
                </a>
                <span class="bs-mail">or email <a href="mailto:{{ ssc('email') }}?subject={{ rawurlencode('SIGLA 2026 sponsorship') }}">{{ ssc('email') }}</a></span>
            </div>
        </div>
    </div>
</section>

<!-- Sponsor enquiry form -->
<div class="modal fade" id="sponsorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="post" action="{{ route('sponsor.inquiry') }}" class="modal-content" style="border:0;border-radius:22px;">
            @csrf
            <div class="modal-header border-0">
                <div><h5 class="modal-title mb-0">Become a sponsor</h5><span class="cell-sub">The SSC will reply to the email address you provide.</span></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <label class="form-label-sm d-block" for="sq-company">Company / organization</label>
                <input type="text" class="form-control" id="sq-company" name="company" value="{{ old('company') }}" maxlength="120" required>

                <div class="row g-3 mt-0">
                    <div class="col-sm-6">
                        <label class="form-label-sm d-block" for="sq-contact">Contact person</label>
                        <input type="text" class="form-control" id="sq-contact" name="contact_name" value="{{ old('contact_name') }}" maxlength="120" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label-sm d-block" for="sq-phone">Phone <span class="text-muted2">(optional)</span></label>
                        <input type="text" class="form-control" id="sq-phone" name="phone" value="{{ old('phone') }}" inputmode="tel" maxlength="32">
                    </div>
                </div>

                <label class="form-label-sm d-block mt-3" for="sq-email">Email</label>
                <input type="email" class="form-control" id="sq-email" name="email" value="{{ old('email') }}" maxlength="190" required>

                <label class="form-label-sm d-block mt-3" for="sq-msg">How would you like to help? <span class="text-muted2">(optional)</span></label>
                <textarea class="form-control" id="sq-msg" name="message" rows="3" maxlength="1000">{{ old('message') }}</textarea>

                <input type="text" name="website_url" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;opacity:0;">
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-gold"><i class="fa-solid fa-paper-plane"></i> Send to SSC</button>
            </div>
        </form>
    </div>
</div>
@if (session('open_sponsor'))
    @push('scripts')
    <script>document.addEventListener('DOMContentLoaded', function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('sponsorModal')).show(); });</script>
    @endpush
@endif

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
                    <strong class="text-deep">SIGLA 2026: A Student's Night</strong> is the flagship student gathering of the
                    70th Founding Anniversary of BatStateU The NEU ARASOF-Nasugbu.
                </p>
                <p class="text-soft" style="font-size:.9rem;">
                    Organized by the <strong>Supreme Student Council</strong>, the night brings every college, org and
                    year level under one roof for a celebration of talent, culture and campus pride — a living tribute to
                    the council's motto, <em class="text-gold fw-800">{{ ev('motto') }}</em>
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    @foreach (event_lineup() as [$act])
                        <span class="badge-soft badge-info"><i class="fa-solid fa-music"></i> {{ $act }}</span>
                    @endforeach
                </div>
            </div>

            <div class="col-lg-7">
                <div class="row g-3">
                    @foreach (about_highlights() as $i => [$icon, $title, $desc])
                        <div class="col-md-6 reveal" style="transition-delay:{{ $i * 90 }}ms;">
                            <div class="card-soft feature-card">
                                <div class="feature-icon"><i class="fa-solid {{ $icon }}"></i></div>
                                <h5>{{ $title }}</h5>
                                <p>{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
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
                    Everything you need to get through verification the first time: who can join, what to upload,
                    file rules, how your ticket works and what to expect at the gate.
                </p>

                <div class="guide-note mt-4">
                    <i class="fa-solid fa-lightbulb"></i>
                    <div>
                        <strong>Tip:</strong> most returned documents are blurry, cropped or unsigned.
                        A flat, well-lit scan avoids almost every problem.
                    </div>
                </div>

                <a href="{{ $cta }}" class="btn btn-green mt-4"><i class="fa-solid fa-arrow-right"></i> I’m ready</a>
            </div>

            <div class="col-lg-8 reveal">
                <div class="guide-card">
                    <div class="guide-tabs" role="tablist">
                        <?php $first = true; foreach (guidelines() as $key => $g): ?>
                            <button class="guide-tab {{ $first ? 'active' : '' }}" type="button" role="tab"
                                    data-bs-toggle="pill" data-bs-target="#guide-{{ $key }}"
                                    aria-selected="{{ $first ? 'true' : 'false' }}">
                                <i class="fa-solid {{ $g['icon'] }}"></i> <span>{{ $g['label'] }}</span>
                            </button>
                        <?php $first = false; endforeach; ?>
                    </div>

                    <div class="tab-content guide-body">
                        <?php $first = true; foreach (guidelines() as $key => $g): ?>
                            <div class="tab-pane fade {{ $first ? 'show active' : '' }}" id="guide-{{ $key }}" role="tabpanel">
                                <div class="row g-3">
                                    @foreach ($g['items'] as [$icon, $title, $text])
                                        <div class="col-md-6">
                                            <div class="guide-item">
                                                <span class="guide-icon"><i class="fa-solid {{ $icon }}"></i></span>
                                                <div>
                                                    <h6>{{ $title }}</h6>
                                                    <p>{{ $text }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>

                <!-- Hard rules, always visible -->
                <div class="row g-3 mt-1">
                    @foreach ([
                        ['fa-circle-xmark',  'danger', 'Not accepted',  'Photos (JPG/PNG), Word files, screenshots of documents, edited or expired IDs.'],
                        ['fa-circle-check',  'success','Accepted',      'A single clear PDF per requirement, up to 5 MB, showing your own name.'],
                        ['fa-user-slash',    'warning','Zero tolerance','Using someone else’s documents voids the ticket and is referred to Student Affairs.'],
                    ] as [$icon, $tone, $title, $text])
                        <div class="col-md-4">
                            <div class="rule-pill rule-{{ $tone }}">
                                <i class="fa-solid {{ $icon }}"></i>
                                <div><strong>{{ $title }}</strong><span>{{ $text }}</span></div>
                            </div>
                        </div>
                    @endforeach
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
                    <div class="fact"><i class="fa-solid fa-calendar-day"></i><div><span>Date</span><strong>{{ ev('date_text') }}</strong></div></div>
                    <div class="fact"><i class="fa-solid fa-clock"></i><div><span>Time</span><strong>{{ ev('time_text') }}</strong></div></div>
                    <div class="fact"><i class="fa-solid fa-location-dot"></i><div><span>Where</span><strong>{{ ev('venue') }}</strong></div></div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="row g-3">
                    @php
                        $lineup = event_lineup();
                        usort($lineup, static fn (array $a, array $b): int => ($a[0] === 'FitterKarma') <=> ($b[0] === 'FitterKarma'));
                    @endphp
                    @foreach ($lineup as $i => [$name, $kind, $icon, $photo])
                        <div class="col-sm-6 reveal" style="transition-delay:{{ $i * 90 }}ms;">
                            <article class="artist-card tilt" data-tilt="7">
                                @if ($photo)
                                    <div class="artist-photo" style="background-image:url('{{ asset_v($photo) }}');" role="img" aria-label="{{ $name }}"></div>
                                @else
                                    <div class="artist-photo artist-placeholder" role="img" aria-label="{{ $name }} — photo coming soon">
                                        <i class="fa-solid {{ $icon }}"></i><span>Photo coming soon</span>
                                    </div>
                                @endif
                                <div class="artist-info">
                                    <span class="artist-tag"><i class="fa-solid {{ $icon }}"></i> {{ $kind }}</span>
                                    <h5>{{ $name }}</h5>
                                </div>
                            </article>
                        </div>
                    @endforeach
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
                    @foreach (faqs() as $i => [$q, $a])
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}">
                                    {{ $q }}
                                </button>
                            </h2>
                            <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                                 data-bs-parent="#faqAccordion">
                                <div class="accordion-body">{{ $a }}</div>
                            </div>
                        </div>
                    @endforeach
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
                        Your ticket to SIGLA 2026 is a few uploads away.
                    </h2>
                    <p class="mb-0" style="color:rgba(255,255,255,.72);max-width:560px;">
                        Create your account, upload your required documents and get your QR-coded ticket once SSC approves them.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    @if (is_logged_in() || student_access_open())
                        <a href="{{ $cta }}" class="btn btn-gold btn-lg2 mb-2">
                            <i class="fa-solid fa-ticket"></i> Get my ticket
                        </a><br>
                    @else
                        <span class="btn btn-gold btn-lg2 mb-2" style="opacity:.65;pointer-events:none;"
                              title="Sign-in and account creation open on {{ student_access_opens_at() }}">
                            <i class="fa-solid fa-hourglass-half"></i> Registration opens {{ student_access_opens_at('M j, Y') }}
                        </span><br>
                    @endif
                    <a href="{{ route('ssc.login') }}" class="btn btn-outline-light2 btn-sm2">
                        <i class="fa-solid fa-user-shield"></i> Staff / Admin Panel
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

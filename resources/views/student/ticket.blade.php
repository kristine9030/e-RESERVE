@extends('layouts.app')
@section('title', 'My Ticket')
@section('crumb', 'Student Portal')
@section('nav', 'ticket')

@section('content')
@if (!$ticket)

    <div class="card-soft fade-up">
        <div class="empty-state">
            <div class="empty-art"><i class="fa-solid fa-ticket"></i></div>
            <h5>No ticket has been issued yet</h5>
            @if (!empty($user['is_employee']))
            <p>
                Your ticket is issued the moment your email is confirmed.
                @if (auth()->user()->hasVerifiedEmail())
                    All {{ number_format(ev('capacity')) }} tickets are taken right now. If a seat is released, you are next in line automatically.
                @else
                    Open the confirmation link we sent to <strong>{{ $user['email'] }}</strong>.
                @endif
            </p>
            <a href="{{ route('dashboard') }}" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            @else
            <p>
                Your ticket number appears once SSC approves your waiver, COR and school ID.
                You have uploaded <strong>{{ $progress['uploaded'] }} of {{ $progress['total'] }}</strong>
                and <strong>{{ $progress['approved'] }}</strong> {{ $progress['approved'] === 1 ? 'is' : 'are' }} approved so far.
                @if ($progress['all_approved'])
                    All {{ number_format(ev('capacity')) }} tickets are already taken. If a seat is released, you are next in line automatically.
                @endif
            </p>
            <a href="{{ route('documents.index') }}" class="btn btn-gold"><i class="fa-solid fa-file-arrow-up"></i> Upload documents</a>
            <a href="{{ route('dashboard') }}" class="btn btn-ghost ms-2"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            @endif
        </div>
    </div>

@else

    <?php // Exactly what the gate scanner reads off this ticket.
    $qrPayload = ticket_qr_payload($ticket);
    $isVoid    = $ticket['status'] === 'void'; ?>

    <div class="page-hero fade-up no-print">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <span class="eyebrow on-dark"><i class="fa-solid fa-circle-check"></i> Ticket Issued</span>
                <h2>You're going to SIGLA 2026!</h2>
                <p>Show the QR code below at the gate. Keep this ticket private — it admits one person only.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                @unless ($isVoid)
                    <a href="{{ route('ticket.image') }}" class="btn btn-gold js-save-ticket" download="ticket-{{ $ticket['ticket_no'] }}.jpg" data-file="ticket-{{ $ticket['ticket_no'] }}.jpg">
                        <i class="fa-solid fa-download"></i> Download as JPG
                    </a>
                @endunless
                <button class="btn btn-outline-light2 ms-1" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            </div>
        </div>
    </div>

    @if ($isVoid)
        <div class="callout callout-danger no-print">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div class="flex-grow-1">
                <strong>This ticket is on hold.</strong> A document was returned by SSC staff.
                Upload a corrected PDF and this same ticket becomes valid again.
            </div>
            <a href="{{ route('documents.index') }}" class="btn btn-gold btn-sm2">Fix my documents</a>
        </div>
    @endif

    <p class="cell-sub no-print mb-3 text-center">
        <i class="fa-solid fa-print me-1"></i> Printing? Choose <strong>A4</strong>, tick <strong>Background graphics</strong> and untick <strong>Headers and footers</strong>.
    </p>

    <!-- ================= The ticket ================= -->
    <?php /* Decorative barcode derived from the ticket number — the QR is what actually scans. */
    $bars = '';
    $x    = 0;
    foreach (str_split(hash('crc32b', $ticket['ticket_no']) . hash('crc32b', strrev($ticket['ticket_no'])) . hash('crc32b', $ticket['verify_hash'])) as $ch) {
        $v = hexdec($ch);
        $w = 1 + ($v % 3);
        if ($v % 2 === 0) {
            $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="40"/>';
        }
        $x += $w + 1 + ($v % 2);
    }
    $performers = implode('  ·  ', array_column(config('ereserve.performers'), 0)); ?>

    <div class="tix-wrap tilt mb-4" data-tilt="7">
        <article class="tix {{ $isVoid ? 'is-void' : '' }} {{ $ticket['checked_in'] ? 'is-used' : '' }}">

            <!-- ============ Main ============ -->
            <div class="tix-main">
                <header class="tix-top">
                    <img class="tix-logo" src="{{ asset(ssc('logo')) }}" alt="Supreme Student Council seal">
                    <div class="tix-org">
                        <span>{{ ev('org') }}</span>
                        <span>BatStateU TNEU · ARASOF-Nasugbu</span>
                    </div>
                    <span class="tix-admit">ADMIT ONE</span>
                </header>

                <div class="tix-title">
                    <span class="tix-kicker">Official Ticket · 70th Founding Anniversary</span>
                    <h3>SIGLA <em>A Student’s Night</em> 2026</h3>
                </div>

                <div class="tix-when">
                    <div><span>Date</span><strong>{{ $ticket['date_text'] }}</strong></div>
                    <div><span>Time</span><strong>{{ $ticket['time_text'] }}</strong></div>
                    <div><span>Venue</span><strong>{{ $ticket['venue'] }}</strong></div>
                </div>

                <div class="tix-holder">
                    <div class="tix-name">
                        <span>Ticket holder</span>
                        <strong>{{ $ticket['name'] }}</strong>
                    </div>
                    <div>
                        <span>{{ ($ticket['member_type'] ?? 'student') === 'student' ? 'SR-Code' : 'Employee ID' }}</span>
                        <strong class="mono">{{ $ticket['student_no'] }}</strong>
                    </div>
                    <div>
                        <span>{{ ($ticket['member_type'] ?? 'student') === 'student' ? 'Program' : 'Category' }}</span>
                        <strong>{{ trim($ticket['program'] . ' · ' . $ticket['year'], ' ·') }}</strong>
                    </div>
                </div>

                <footer class="tix-foot">
                    <svg class="tix-barcode" viewBox="0 0 {{ $x }} 40" preserveAspectRatio="none" aria-hidden="true">{!! $bars !!}</svg>
                    <span class="tix-perf-line">{{ $performers }}</span>
                </footer>
            </div>

            <!-- ============ Stub ============ -->
            <aside class="tix-stub">
                <div class="tix-stub-label">Scan at the gate</div>
                <div class="tix-qr">
                    {!! $isVoid
                        ? '<div class="qr-void"><i class="fa-solid fa-ban"></i><span>On hold</span></div>'
                        : qr_svg($qrPayload, 168, ['label' => 'Ticket ' . $ticket['ticket_no']]) !!}
                </div>
                <div class="tix-no-label">Ticket no.</div>
                <div class="tix-no mono">{{ $ticket['ticket_no'] }}</div>
                <span class="tix-state tix-state-{{ $isVoid ? 'void' : ($ticket['checked_in'] ? 'used' : 'valid') }}">
                    {{ $isVoid ? 'On hold' : ($ticket['checked_in'] ? 'Checked in' : 'Valid for entry') }}
                </span>
            </aside>

            @if ($isVoid)<div class="tix-stamp">ON HOLD</div>@endif
            @if ($ticket['checked_in'])<div class="tix-stamp tix-stamp-used">USED</div>@endif
        </article>
    </div>

    <div class="print-doc-foot">
        Issued {{ pretty_date($ticket['issued_at'], 'F d, Y · g:i A') }} · non-transferable · one scan per ticket ·
        questions: {{ ssc('email') }}
    </div>

    <!-- ================= Actions + reminders ================= -->
    <div class="row g-4 no-print">
        <div class="col-lg-7">
            <div class="card-soft fade-up d2">
                <div class="card-head"><h6 class="mb-0">Ticket Actions</h6></div>
                <div class="card-body-p d-grid gap-2">
                    @unless ($isVoid)
                    <a href="{{ route('ticket.image') }}" class="action-tile js-save-ticket" download="ticket-{{ $ticket['ticket_no'] }}.jpg" data-file="ticket-{{ $ticket['ticket_no'] }}.jpg">
                        <span class="t-icon"><i class="fa-solid fa-image"></i></span>
                        <span><span class="t-title d-block">Download as JPG</span>
                              <span class="t-sub">A JPG of this ticket for your phone gallery</span></span>
                        <i class="fa-solid fa-download t-arrow"></i>
                    </a>
                    @endunless

                    <button class="action-tile" onclick="window.print()">
                        <span class="t-icon"><i class="fa-solid fa-print"></i></span>
                        <span><span class="t-title d-block">Print / Save as PDF</span>
                              <span class="t-sub">Bring a printed copy as backup</span></span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </button>

                    <a href="{{ route('documents.index') }}" class="action-tile">
                        <span class="t-icon"><i class="fa-solid fa-file-pdf"></i></span>
                        <span><span class="t-title d-block">My Documents</span>
                              <span class="t-sub">The 3 required documents behind this ticket</span></span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </a>

                    <button class="action-tile" data-copy="{{ $ticket['ticket_no'] }}">
                        <span class="t-icon"><i class="fa-solid fa-copy"></i></span>
                        <span><span class="t-title d-block">Copy Ticket Number</span>
                              <span class="t-sub">{{ $ticket['ticket_no'] }}</span></span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-soft fade-up d3">
                <div class="card-head"><h6 class="mb-0">Before You Go</h6></div>
                <div class="card-body-p">
                    <?php $reminders = [
                        ['fa-clock',      'Arrive early',        'The event starts at 5:00 PM. Be at the gate before then so you are scanned in on time.'],
                        ['fa-id-badge',   'Bring your school ID','Gate staff match your QR code against your ID.'],
                        ['fa-mobile-screen','Screen brightness',  'Turn brightness up so the scanner reads the QR quickly.'],
                        ['fa-ban',        'Non-transferable',    'This ticket admits one student and cannot be resold.'],
                    ];
                    foreach ($reminders as [$icon, $t, $d]): ?>
                        <div class="info-row">
                            <span class="info-icon"><i class="fa-solid {{ $icon }}"></i></span>
                            <div>
                                <div class="info-value" style="font-size:.86rem;">{{ $t }}</div>
                                <div class="cell-sub">{{ $d }}</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

@endif
@endsection

@extends('layouts.app')
@section('title', 'Dashboard')
@section('crumb', 'Student Portal')
@section('nav', 'dashboard')

@section('content')
@php [$stageLabel, $stageTone, $stageIcon] = $stage; @endphp
<!-- ================= Welcome ================= -->
<section class="dash-welcome">
    <div class="dw-text">
        <span class="dw-greet"><i class="fa-solid fa-hand-sparkles"></i> {{ $greeting }}</span>
        <h2>Welcome, {{ $firstName }}!</h2>
        <p class="dw-meta">
            <span><i class="fa-solid fa-graduation-cap"></i> {{ $user['program'] }} · {{ $user['year'] }}</span>
            <span><i class="fa-solid fa-id-badge"></i> SR-Code <strong>{{ $user['student_no'] }}</strong></span>
        </p>
        <div class="dw-badges">
            <span class="badge-soft badge-{{ $stageTone }}"><i class="fa-solid {{ $stageIcon }}"></i> {{ $stageLabel }}</span>
            @if ($ticket && $ticket['status'] !== 'void')
                <span class="badge-soft badge-info"><i class="fa-solid fa-ticket"></i> {{ $ticket['ticket_no'] }}</span>
            @endif
        </div>
    </div>

    <div class="dw-count" data-countdown="{{ ev('date_iso') }}">
        <div class="dw-count-title">{{ ev('name') }} · {{ ev('date_text') }}</div>
        <div class="dw-count-row">
            <div><b data-cd="days">00</b><span>days</span></div>
            <div><b data-cd="hours">00</b><span>hrs</span></div>
            <div><b data-cd="minutes">00</b><span>min</span></div>
            <div><b data-cd="seconds">00</b><span>sec</span></div>
        </div>
    </div>
</section>

<div class="dash-grid">

    <!-- ================= Main column ================= -->
    <div class="dash-main">

        <!-- Next step -->
        <div class="next-step {{ $progress['blocked'] ? 'is-alert' : '' }}">
            <span class="next-icon"><i class="fa-solid {{ $next[0] }}"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="next-kicker">Your next step</div>
                <h4>{{ $next[1] }}</h4>
                <p>{{ $next[2] }}</p>
            </div>
            <a href="{{ $next[4] }}" class="btn btn-gold">{{ $next[3] }} <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <!-- Progress -->
        <div class="dash-card">
            <div class="dash-card-head">
                <h5><i class="fa-solid fa-route"></i> Your progress</h5>
                <?php $doneCt = count(array_filter($timeline, static fn($s) => $s['state'] === 'done')); ?>
                <span class="cell-sub">{{ $doneCt }} of {{ count($timeline) }} done</span>
            </div>
            <div class="pipeline pipeline-app pipeline-flat">
                @foreach ($timeline as $s)
                    <div class="pipe-step {{ $s['state'] }}">
                        <div class="pipe-node">
                            @if ($s['state'] === 'done')<i class="fa-solid fa-check"></i>
                            @else<i class="fa-solid {{ $s['icon'] }}"></i>@endif
                        </div>
                        <div class="pipe-body">
                            <div class="pipe-title">{{ $s['label'] }}</div>
                            <div class="pipe-sub">
                                @if ($s['state'] === 'done' && $s['stamp']){{ pretty_date($s['stamp'], 'M d · g:i A') }}
                                @elseif ($s['state'] === 'current')<span class="text-gold">In progress</span>
                                @else Up next @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Documents -->
        <div class="dash-card">
            <div class="dash-card-head">
                <h5><i class="fa-solid fa-file-circle-check"></i> Required documents</h5>
                <a href="{{ route('documents.index') }}" class="btn btn-ghost btn-sm2">Manage <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="dash-docs">
                <?php foreach (config('ereserve.documents') as $key => $meta):
                    $doc    = $docs[$key] ?? null;
                    $status = $doc['status'] ?? 'missing'; ?>
                    <a href="{{ route('documents.index') . '#doc-' . $key }}" class="doc-row state-{{ $status }}">
                        <span class="doc-row-icon"><i class="fa-solid {{ $meta['icon'] }}"></i></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-block fw-800 text-deep">{{ $meta['label'] }}</span>
                            <span class="cell-sub d-block text-truncate">
                                @if ($doc){{ $doc['name'] }}@else Not uploaded yet @endif
                            </span>
                        </span>
                        {!! status_badge($status) !!}
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ================= Side column ================= -->
    <aside class="dash-side">

        @if ($ticket && $ticket['status'] !== 'void')
            <a href="{{ route('ticket') }}" class="mini-ticket tilt d-block" data-tilt="8">
                <div class="mini-ticket-top">
                    <span class="eyebrow on-dark"><i class="fa-solid fa-ticket"></i> Your ticket</span>
                    <span class="badge-soft badge-{{ $ticket['checked_in'] ? 'info' : 'success' }}">{{ $ticket['checked_in'] ? 'Used' : 'Valid' }}</span>
                </div>
                <div class="mini-ticket-no mono">{{ $ticket['ticket_no'] }}</div>
                <div class="mini-ticket-foot">
                    <span>{{ $user['name'] }}</span>
                    <span>Show QR <i class="fa-solid fa-arrow-right ms-1"></i></span>
                </div>
            </a>
        @else
            <div class="dash-card dash-empty">
                <span class="dash-empty-icon"><i class="fa-solid fa-ticket"></i></span>
                <h6>No ticket yet</h6>
                <p>Your ticket is issued once SSC approves all 3 required documents. Only {{ number_format(ev('capacity')) }} tickets are available.</p>
            </div>
        @endif

        <div class="dash-card">
            <div class="dash-card-head"><h5><i class="fa-solid fa-calendar-day"></i> Event details</h5></div>
            <ul class="dash-facts">
                <li><i class="fa-solid fa-star"></i><div><span>Event</span><strong>{{ ev('name') }}</strong></div></li>
                <li><i class="fa-solid fa-calendar-day"></i><div><span>Date &amp; time</span><strong>{{ ev('date_text') }} · {{ ev('time_text') }}</strong></div></li>
                <li><i class="fa-solid fa-location-dot"></i><div><span>Venue</span><strong>{{ ev('venue') }}</strong></div></li>
            </ul>
        </div>

        <div class="dash-card">
            <div class="dash-card-head"><h5><i class="fa-solid fa-headset"></i> Need help?</h5></div>
            <ul class="dash-facts">
                <li><i class="fa-solid fa-envelope"></i><div><span>Email</span><strong><a href="mailto:{{ ssc('email') }}">{{ ssc('email') }}</a></strong></div></li>
                <li><i class="fa-brands fa-facebook"></i><div><span>Facebook</span><strong><a href="{{ ssc('facebook') }}" target="_blank" rel="noopener">facebook.com/SSCARASOF</a></strong></div></li>
            </ul>
            <div class="dash-help-links">
                <a href="{{ route('home') . '#guidelines' }}" class="btn btn-ghost btn-sm2"><i class="fa-solid fa-book-open"></i> Guidelines</a>
                <a href="{{ route('tracking') }}" class="btn btn-ghost btn-sm2"><i class="fa-solid fa-route"></i> Full timeline</a>
            </div>
        </div>
    </aside>
</div>
@endsection

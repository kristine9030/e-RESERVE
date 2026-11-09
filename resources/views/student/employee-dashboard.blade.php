@extends('layouts.app')
@section('title', 'Dashboard')
@section('crumb', 'Employee Portal')
@section('nav', 'dashboard')

@section('content')
<?php $hasTicket = $ticket && $ticket['status'] !== 'void'; ?>
<section class="dash-welcome">
    <div class="dw-text">
        <span class="dw-greet"><i class="fa-solid fa-hand-sparkles"></i> {{ $greeting }}</span>
        <h2>Welcome, {{ $firstName }}!</h2>
        <p class="dw-meta">
            <span><i class="fa-solid fa-briefcase"></i> {{ $user['program'] }}</span>
            <span><i class="fa-solid fa-id-badge"></i> Employee ID <strong>{{ $user['student_no'] }}</strong></span>
        </p>
        <div class="dw-badges">
            <span class="badge-soft badge-{{ $stage[1] }}"><i class="fa-solid {{ $stage[2] }}"></i> {{ $stage[0] }}</span>
            @if ($hasTicket)
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
    <div class="dash-main">

        <div class="next-step">
            <span class="next-icon"><i class="fa-solid {{ $next[0] }}"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="next-kicker">Your next step</div>
                <h4>{{ $next[1] }}</h4>
                <p>{{ $next[2] }}</p>
            </div>
            <a href="{{ $next[4] }}" class="btn btn-gold">{{ $next[3] }} <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="dash-card">
            <div class="dash-card-head">
                <h5><i class="fa-solid fa-route"></i> Your progress</h5>
                <span class="cell-sub">{{ count(array_filter($steps, fn ($s) => $s[2])) }} of {{ count($steps) }} done</span>
            </div>
            <div class="p-3">
                @foreach ($steps as [$icon, $label, $done, $sub])
                    <div class="info-row">
                        <span class="info-icon" style="{{ $done ? 'background:rgba(31,157,99,.12);color:var(--success);' : '' }}"><i class="fa-solid {{ $done ? 'fa-circle-check' : $icon }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="info-value" style="font-size:.92rem;">{{ $label }}</div>
                            <div class="cell-sub">{{ $sub }}</div>
                        </div>
                        <span class="badge-soft badge-{{ $done ? 'success' : 'muted' }}">{{ $done ? 'Done' : 'To do' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="dash-side">
        <div class="dash-card">
            <div class="dash-card-head"><h5><i class="fa-solid fa-ticket"></i> Your ticket</h5></div>
            <div class="p-3">
                @if ($hasTicket)
                    <div class="receipt-row"><span class="k">Ticket</span><span class="v"><span class="mono">{{ $ticket['ticket_no'] }}</span></span></div>
                    <div class="receipt-row"><span class="k">Date</span><span class="v">{{ ev('date_text') }}</span></div>
                    <div class="receipt-row"><span class="k">Venue</span><span class="v">{{ ev('venue') }}</span></div>
                    <a href="{{ route('ticket') }}" class="btn btn-gold btn-block2 mt-3"><i class="fa-solid fa-qrcode"></i> Open my ticket</a>
                @else
                    <p class="text-soft mb-0" style="font-size:.88rem;">
                        {{ $verified ? 'All ' . number_format(ev('capacity')) . ' tickets are taken right now. If a seat is released, yours is issued automatically.' : 'Your ticket appears as soon as you confirm your email.' }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

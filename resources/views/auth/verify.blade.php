@extends('layouts.base')
@section('title', 'Confirm Email')
@section('bodyClass', 'auth-page')

@section('content')
<div class="container" style="max-width:620px;padding:4rem 1rem;">

    <div class="text-center mb-4">
        <a class="brand-lockup d-inline-flex" href="{{ route('home') }}">
            <img class="brand-logo" src="{{ asset(ssc('logo')) }}" alt="Supreme Student Council seal">
            <span class="brand-text text-start">
                <span class="brand-name d-block">e-<span>RESERVE</span></span>
                <span class="brand-sub">SIGLA 2026</span>
            </span>
        </a>
    </div>

    <div class="card-soft p-4 p-md-5 fade-up text-center">

        @if ($state === 'confirmed')

            <span class="stat-icon green d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-circle-check"></i>
            </span>
            <h3 style="font-size:1.4rem;">Email confirmed</h3>
            <p class="text-soft" style="font-size:.9rem;">
                Thanks{{ $user ? ', ' . e(explode(' ', $user['name'])[0]) : '' }} — your address is verified
                and your account is ready.
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                @if (!empty($user['is_employee']))
                    <a href="{{ route('ticket') }}" class="btn btn-gold"><i class="fa-solid fa-ticket"></i> Open my ticket</a>
                @else
                    <a href="{{ route('documents.index') }}" class="btn btn-gold"><i class="fa-solid fa-file-arrow-up"></i> Upload my documents</a>
                @endif
                <a href="{{ route('dashboard') }}" class="btn btn-ghost">Go to dashboard</a>
            </div>

        @elseif ($state === 'sent')

            <span class="stat-icon gold d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-paper-plane"></i>
            </span>
            <h3 style="font-size:1.4rem;">Confirmation link sent</h3>
            <p class="text-soft" style="font-size:.9rem;">
                Check <strong class="text-deep">{{ $user['email'] ?? 'your inbox' }}</strong>.
                The link works for {{ (int) config('ereserve.verify_token_hours') }} hours. Look in Spam if it is not there.
            </p>

            @if ($devLink && in_array(config('mail.default'), ['log', 'array'], true) && !app()->isProduction())
                <div class="print-tip mt-4" style="text-align:left;">
                    <i class="fa-solid fa-flask"></i>
                    <div>
                        <strong>Mail is in log mode.</strong> Nothing was actually sent — the message was written to
                        <code>storage/logs/laravel.log</code>. Use this link to confirm:
                        <a href="{{ $devLink }}" class="d-block mt-2 text-gold fw-800" style="word-break:break-all;font-size:.78rem;">
                            {{ $devLink }}
                        </a>
                        <span class="d-block mt-2 cell-sub">
                            Set <code>MAIL_MAILER=smtp</code> and the <code>MAIL_*</code> values in
                            <code>.env</code> to send for real.
                        </span>
                    </div>
                </div>
            @endif

            <a href="{{ route('dashboard') }}" class="btn btn-ghost mt-4">Back to dashboard</a>

        @elseif ($state === 'waiting')

            <span class="stat-icon info d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-hourglass-half"></i>
            </span>
            <h3 style="font-size:1.4rem;">Already on its way</h3>
            <p class="text-soft" style="font-size:.9rem;">{{ $message }}</p>
            <p class="cell-sub">
                Sent to <strong class="text-deep">{{ $user['email'] ?? '' }}</strong> — check Spam too.
            </p>
            <a href="{{ route('dashboard') }}" class="btn btn-ghost mt-3">Back to dashboard</a>

        @elseif ($state === 'failed')

            <span class="stat-icon danger d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-circle-exclamation"></i>
            </span>
            <h3 style="font-size:1.4rem;">That did not work</h3>
            <p class="text-soft" style="font-size:.9rem;">{{ $message }}</p>

            @if (is_logged_in() && !$verified)
                <form method="post" class="mt-4">
                    {!! csrf_field() !!}
                    <button class="btn btn-gold"><i class="fa-solid fa-rotate"></i> Send me a new link</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-gold mt-4">Sign in</a>
            @endif

        @else

            <span class="stat-icon info d-inline-grid mb-3" style="width:64px;height:64px;font-size:1.6rem;">
                <i class="fa-solid fa-envelope-open-text"></i>
            </span>
            <h3 style="font-size:1.4rem;">Confirm your email</h3>

            @if ($verified)
                <p class="text-soft" style="font-size:.9rem;">
                    {{ $user['email'] }} is already confirmed. Nothing more to do.
                </p>
                <a href="{{ route('dashboard') }}" class="btn btn-gold mt-3">Go to dashboard</a>

            @elseif (is_logged_in())
                <p class="text-soft" style="font-size:.9rem;">
                    We sent a confirmation link to <strong class="text-deep">{{ $user['email'] }}</strong>.
                    Open it to finish setting up your account. Did not get it?
                </p>
                <form method="post" class="mt-3">
                    {!! csrf_field() !!}
                    <button class="btn btn-gold"><i class="fa-solid fa-rotate"></i> Resend the link</button>
                </form>

            @else
                <p class="text-soft" style="font-size:.9rem;">
                    Sign in first, then we can resend your confirmation link.
                </p>
                <a href="{{ route('login') }}" class="btn btn-gold mt-3">Sign in</a>
            @endif

        @endif
    </div>

    <p class="text-center text-muted2 mt-4 mb-0" style="font-size:.76rem;">
        {{ ev('org') }} · {{ ev('host') }}
    </p>
</div>
@endsection

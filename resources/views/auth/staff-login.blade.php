@extends('layouts.base')
@section('title', 'SSC Staff Sign In')
@section('bodyClass', 'auth-page')

@section('content')
<div class="auth-shell">

    @include('partials.auth_aside', ['asideMode' => 'staff'])

    <main class="auth-main">
        <span class="orb orb-gold orb-soft" style="width:340px;height:340px;top:-100px;right:-120px;"></span>
        <span class="orb orb-teal orb-soft" style="width:300px;height:300px;bottom:-120px;left:-100px;"></span>

        <div class="auth-card fade-up">
            <a href="{{ route('home') }}" class="text-muted2 fw-800 d-flex align-items-center gap-2 mb-4"
               style="font-size:.76rem;letter-spacing:1.2px;text-transform:uppercase;width:fit-content;">
                <i class="fa-solid fa-arrow-left"></i> Back to event page
            </a>

            <span class="badge-soft badge-info mb-3 d-flex align-items-center gap-2" style="width:fit-content;">
                <i class="fa-solid fa-user-shield"></i> SSC Staff
            </span>
            <h3 style="font-size:1.55rem;">Staff sign in</h3>
            <p class="text-soft mb-4" style="font-size:.87rem;">
                For SSC officers, the adviser and gate staff. Accounts are created by the SSC; there is no sign-up here.
            </p>

            @if (session('login_error'))
                <div class="alert d-flex align-items-start gap-2 mb-4"
                     style="background:rgba(217,72,59,.08);border:1px solid rgba(217,72,59,.22);border-radius:14px;color:#A8352A;font-size:.84rem;">
                    <i class="fa-solid fa-circle-exclamation mt-1"></i>
                    <span>{{ session('login_error') }}</span>
                </div>
            @endif

            <form method="post" action="{{ route('ssc.login.store') }}" novalidate>
                {!! csrf_field() !!}

                <div class="mb-3">
                    <label class="form-label-sm d-block" for="email">Staff Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" autocomplete="username"
                               placeholder="name@g.batstate-u.edu.ph" value="{{ old('email') }}"
                               style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label-sm d-block" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Your password" autocomplete="current-password" required
                               style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label text-soft" for="remember" style="font-size:.82rem;">Keep me signed in</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="text-gold fw-800" style="font-size:.8rem;">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-green btn-block2 btn-lg2">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign in to the SSC panel
                </button>
            </form>

            <p class="text-center text-muted2 mt-4 mb-1" style="font-size:.8rem;">
                A student? <a href="{{ route('login') }}" class="text-gold fw-800">Student sign-in</a>
            </p>
            <p class="text-center text-muted2 mb-0" style="font-size:.74rem;">
                <i class="fa-solid fa-shield-halved me-1"></i> Sign-ins and approvals are recorded in the audit log.
            </p>
        </div>
    </main>
</div>
@endsection

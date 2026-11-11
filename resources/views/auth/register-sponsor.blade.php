@extends('layouts.base')
@section('title', 'Create Account')
@section('bodyClass', 'auth-page')

@section('content')
@php
    $err   = session('register_error');
    $error = $err['message'] ?? '';
    $field = $err['field'] ?? '';
    $bad   = static fn(string $key): string => $field === $key ? ' is-invalid' : '';
@endphp
<div class="auth-shell">

    @include('partials.auth_aside', ['asideMode' => 'register'])

    <main class="auth-main">
        <span class="orb orb-gold orb-soft" style="width:340px;height:340px;top:-100px;right:-120px;"></span>
        <span class="orb orb-teal orb-soft" style="width:300px;height:300px;bottom:-120px;left:-100px;"></span>

        <div class="auth-card fade-up" style="max-width:600px;">
            <a href="{{ route('register') . ($next !== '' ? '?next=' . urlencode($next) : '') }}" class="auth-back">
                <i class="fa-solid fa-arrow-left"></i> I’m not a sponsor
            </a>

            @include('partials.auth_switch', ['active' => 'register'])

            <span class="badge-soft badge-gold mb-2"><i class="fa-solid fa-handshake"></i> Sponsor · Partner</span>
            <h3 style="font-size:1.55rem;">Create your account</h3>
            <p class="text-soft mb-4" style="font-size:.87rem;">
                Use any email you check and confirm it with the link we send. Then upload one PDF as proof of sponsorship; your ticket is issued once SSC approves it.
            </p>

            @if ($error !== '')
                <div class="alert d-flex align-items-start gap-2 mb-4"
                     style="background:rgba(217,72,59,.08);border:1px solid rgba(217,72,59,.22);border-radius:14px;color:#A8352A;font-size:.84rem;">
                    <i class="fa-solid fa-circle-exclamation mt-1"></i>
                    <span>{{ $error }}</span>
                </div>
            @endif

            <form method="post" id="registerForm" novalidate>
                {!! csrf_field() !!}
                <input type="hidden" name="as" value="sponsor">
                <input type="hidden" name="next" value="{{ $next }}">

                <div class="mb-3">
                    <label class="form-label-sm d-block" for="name">Company or Full Name</label>
                    <input type="text" class="form-control{{ $bad('name') }}" id="name" name="name" value="{{ old('name') }}"
                           placeholder="e.g. Juan Dela Cruz Trading" autocomplete="name" maxlength="160" required>
                </div>

                <div class="row g-3">
                    <div class="col-12 mb-1">
                        <label class="form-label-sm d-block" for="mobile">Mobile Number <span class="text-muted2">(optional)</span></label>
                        <input type="text" class="form-control{{ $bad('mobile') }}" id="mobile" name="mobile" value="{{ old('mobile') }}"
                               placeholder="09171234567" inputmode="numeric" maxlength="13">
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label class="form-label-sm d-block" for="email">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" class="form-control{{ $bad('email') }}" id="email" name="email" value="{{ old('email') }}"
                               placeholder="you@company.com" autocomplete="email"
                               style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;" required>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label class="form-label-sm d-block" for="password">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control{{ $bad('password') }}" id="password" name="password"
                                   placeholder="At least 8 characters" autocomplete="new-password"
                                   style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;" required>
                        </div>
                        <div class="cell-sub mt-1">Mix letters and numbers.</div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label-sm d-block" for="password_confirm">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control{{ $bad('password_confirm') }}" id="password_confirm"
                                   name="password_confirm" placeholder="Type it again" autocomplete="new-password"
                                   style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;" required>
                        </div>
                        <div class="cell-sub mt-1" data-match-hint></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-green btn-block2 btn-lg2">
                    <i class="fa-solid fa-user-plus"></i> Create Account
                </button>
            </form>

            <p class="text-center text-muted2 mt-4 mb-0" style="font-size:.74rem;">
                <i class="fa-solid fa-shield-halved me-1"></i>
                By registering you agree that the SSC may process your details for
                SIGLA 2026 ticketing, under the <a href="{{ route('privacy') }}" class="text-gold fw-800">BatStateU Data Privacy Policy</a>.
            </p>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var pass = document.getElementById('password'), again = document.getElementById('password_confirm');
        var hint = document.querySelector('[data-match-hint]');
        function checkMatch() {
            if (!again.value) { hint.textContent = ''; hint.style.color = ''; return; }
            var same = pass.value === again.value;
            hint.textContent = same ? 'Passwords match.' : 'Passwords do not match.';
            hint.style.color = same ? 'var(--success)' : 'var(--danger)';
        }
        pass.addEventListener('input', checkMatch);
        again.addEventListener('input', checkMatch);
        document.getElementById('registerForm').addEventListener('submit', function (e) {
            if (pass.value !== again.value) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Passwords do not match', confirmButtonText: 'Try again' });
            }
        });
    })();
</script>
@endpush

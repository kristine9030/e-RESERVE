@extends('layouts.base')
@section('title', 'Sign In')
@section('bodyClass', 'auth-page')

@section('content')
<div class="auth-shell">

    @include('partials.auth_aside', ['asideMode' => 'login'])

    <!-- ================= Form side ================= -->
    <main class="auth-main">
        <span class="orb orb-gold orb-soft" style="width:340px;height:340px;top:-100px;right:-120px;"></span>
        <span class="orb orb-teal orb-soft" style="width:300px;height:300px;bottom:-120px;left:-100px;"></span>

        <div class="auth-card fade-up">
            <a href="{{ route('home') }}" class="auth-back">
                <i class="fa-solid fa-arrow-left"></i> Back to event page
            </a>

            @include('partials.auth_switch', ['active' => 'login'])

            <h3 style="font-size:1.55rem;">Welcome back</h3>
            <p class="text-soft mb-4" style="font-size:.87rem;">
                Sign in with your official <strong class="text-deep">{{ allowed_domains_text() }}</strong> account.
            </p>

            @if (session('login_error'))
                <div class="alert d-flex align-items-start gap-2 mb-4"
                     style="background:rgba(217,72,59,.08);border:1px solid rgba(217,72,59,.22);border-radius:14px;color:#A8352A;font-size:.84rem;">
                    <i class="fa-solid fa-circle-exclamation mt-1"></i>
                    <span>{{ session('login_error') }}</span>
                </div>
            @endif

            <a href="{{ route('google.redirect') }}" class="btn-google">
                <svg class="g-logo" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.2 5.5-4.7 7.2l7.6 5.9c4.4-4.1 6.9-10.1 6.9-17.6z"/><path fill="#FBBC05" d="M10.5 28.7c-.5-1.5-.8-3-.8-4.7s.3-3.2.8-4.7l-7.9-6.1C.9 16.3 0 20 0 24s.9 7.7 2.6 10.8l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                <span>Continue with Google</span>
            </a>

            <div class="divider-or">or sign in with your email</div>

            <form method="post" id="loginForm" novalidate>
                {!! csrf_field() !!}
                <input type="hidden" name="next" value="{{ $next }}">

                <div class="mb-3">
                    <label class="form-label-sm d-block" for="email">GSuite Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-brands fa-google"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="juan@g.batstate-u.edu.ph"
                               value="{{ old('email') }}"
                               style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;" required>
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
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In
                </button>
            </form>

            <p class="text-center text-muted2 mt-4 mb-1" style="font-size:.8rem;">
                SSC officer or adviser? <a href="{{ route('ssc.login') }}" class="text-gold fw-800">Staff sign-in</a>
            </p>
            <p class="text-center text-muted2 mb-0" style="font-size:.74rem;">
                <i class="fa-solid fa-shield-halved me-1"></i>
                Protected by the BatStateU Data Privacy Policy
            </p>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    var ALLOWED_DOMAINS = {!! json_encode(array_map(fn($d) => '@' . $d, config('ereserve.allowed_email_domains'))) !!};

        /* Mirror the server-side "did you mean" so a typo is caught before the
           round trip. Small Levenshtein, kept inline to avoid a dependency. */
        function nearestDomain(typed) {
            var best = null, score = 99;
            ALLOWED_DOMAINS.forEach(function (d) {
                var a = typed, b = d.replace(/^@/, ''), m = [], i, j;
                for (i = 0; i <= a.length; i++) { m[i] = [i]; }
                for (j = 0; j <= b.length; j++) { m[0][j] = j; }
                for (i = 1; i <= a.length; i++) {
                    for (j = 1; j <= b.length; j++) {
                        m[i][j] = Math.min(m[i - 1][j] + 1, m[i][j - 1] + 1,
                                           m[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
                    }
                }
                if (m[a.length][b.length] < score) { score = m[a.length][b.length]; best = b; }
            });
            return (score > 0 && score <= 3) ? best : null;
        }

        /** Null when the address is fine, otherwise the message to show. */
        function domainWarning(email) {
            var at = email.lastIndexOf('@');
            if (at < 0) { return null; }

            var typed = email.slice(at + 1), local = email.slice(0, at);
            if (ALLOWED_DOMAINS.indexOf('@' + typed) !== -1) { return null; }

            var guess = nearestDomain(typed);
            /* Not a near miss of a university domain: let the server decide (sponsors sign in with any email). */
            return guess ? 'Check the spelling — did you mean <strong>' + local + '@' + guess + '</strong>?' : null;
        }

    /* Friendly client-side domain check before the round trip. */
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        var field = document.getElementById('email');
        var email = field.value.trim().toLowerCase();
        var warning = email ? domainWarning(email) : null;

        /* A one-letter slip in the domain is corrected for them and the sign-in carries on. */
        var at = email.lastIndexOf('@');
        if (warning && at > 0) {
            var guess = nearestDomain(email.slice(at + 1));
            if (guess) { field.value = email.slice(0, at) + '@' + guess; warning = null; }
        }
            if (warning) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'University Account Required',
                html: warning,
                confirmButtonText: 'Let me fix that'
            });
        }
    });
</script>
@endpush

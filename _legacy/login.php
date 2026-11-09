<?php
/**
 * login.php — BatStateU GSuite sign-in.
 *
 * Every account — student and staff alike — signs in with an email and a
 * password. Students create theirs on register.php; staff accounts are seeded
 * by the SSC and can never be self-registered.
 *
 * REPLACE LATER: swap the student branch for Google Workspace OAuth 2.0 with
 * hd=g.batstate-u.edu.ph. attempt_login() in includes/functions.php is the only
 * place that would change.
 */
require_once __DIR__ . '/includes/config.php';

$next  = $_GET['next']  ?? '';
$panel = $_GET['panel'] ?? '';
$error = '';

/* Already signed in? Go straight through. */
if (is_logged_in() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(is_admin() ? 'admin/index.php' : 'dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();

    $email = trim((string) ($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $goto  = (string) ($_POST['next'] ?? '');

    $result = attempt_login($email, $pass);

    if (!$result['ok']) {
        $error = $result['message'];
    } else {
        $user = $result['user'];

        set_flash(
            'success',
            'Welcome, ' . explode(' ', $user['name'])[0] . '!',
            'You are signed in to ' . APP_NAME . ' as <strong>' . e($user['email']) . '</strong>.'
        );

        if ($user['role'] === 'admin') {
            redirect('admin/index.php');
        }
        redirect($goto === 'documents' ? 'documents.php' : 'dashboard.php');
    }
}

/* Demo accounts shown as one-click chips. */
$quickAccounts = [
    ['demo.student@g.batstate-u.edu.ph', 'Demo Student',  'Clean start · submit 3 documents, get the ticket', DEMO_STUDENT_PASSWORD, 'DS'],
    ['demo.almost@g.batstate-u.edu.ph',  'Demo Almost There', '2 of 3 uploaded · one PDF away from a ticket', DEMO_STUDENT_PASSWORD, 'DA'],
];

$pageTitle = 'Sign In';
$bodyClass = 'auth-page';
require __DIR__ . '/layouts/head.php';
?>

<div class="auth-shell">

    <?php $asideMode = 'login'; require __DIR__ . '/layouts/auth_aside.php'; ?>

    <!-- ================= Form side ================= -->
    <main class="auth-main">
        <span class="orb orb-gold orb-soft" style="width:340px;height:340px;top:-100px;right:-120px;"></span>
        <span class="orb orb-teal orb-soft" style="width:300px;height:300px;bottom:-120px;left:-100px;"></span>

        <div class="auth-card fade-up">
            <a href="<?= url('index.php') ?>" class="text-muted2 fw-800 d-inline-flex align-items-center gap-2 mb-4"
               style="font-size:.76rem;letter-spacing:1.2px;text-transform:uppercase;">
                <i class="fa-solid fa-arrow-left"></i> Back to event page
            </a>

            <h3 style="font-size:1.55rem;">Sign in to e-RESERVE</h3>
            <p class="text-soft mb-4" style="font-size:.87rem;">
                Use your official <strong class="text-deep"><?= e(allowed_domains_text()) ?></strong> account.
                <span class="d-block text-muted2 mt-1" style="font-size:.78rem;">
                    <i class="fa-solid fa-circle-info me-1"></i> No account yet? <a href="<?= url('register.php' . ($next !== '' ? '?next=' . urlencode($next) : '')) ?>" class="text-gold fw-800">Create one here</a>.
                </span>
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert d-flex align-items-start gap-2 mb-4"
                     style="background:rgba(217,72,59,.08);border:1px solid rgba(217,72,59,.22);border-radius:14px;color:#A8352A;font-size:.84rem;">
                    <i class="fa-solid fa-circle-exclamation mt-1"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <a href="<?= url('google.php') ?>" class="btn-google">
                <svg class="g-logo" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.2 5.5-4.7 7.2l7.6 5.9c4.4-4.1 6.9-10.1 6.9-17.6z"/><path fill="#FBBC05" d="M10.5 28.7c-.5-1.5-.8-3-.8-4.7s.3-3.2.8-4.7l-7.9-6.1C.9 16.3 0 20 0 24s.9 7.7 2.6 10.8l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                <span>Continue with Google</span>
            </a>

            <div class="divider-or">or sign in with your email</div>

            <form method="post" id="loginForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <div class="mb-3">
                    <label class="form-label-sm d-block" for="email">GSuite Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-brands fa-google"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="juan.delacruz@g.batstate-u.edu.ph"
                               value="<?= e($_POST['email'] ?? '') ?>"
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
                        <input class="form-check-input" type="checkbox" id="remember" checked>
                        <label class="form-check-label text-soft" for="remember" style="font-size:.82rem;">Keep me signed in</label>
                    </div>
                    <a href="#" class="text-gold fw-800" style="font-size:.8rem;" data-soon="Password Recovery">Need help?</a>
                </div>

                <button type="submit" class="btn btn-green btn-block2 btn-lg2">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In
                </button>
            </form>

            <div class="divider-or">Demo accounts</div>

            <div class="d-grid gap-2">
                <?php foreach ($quickAccounts as [$mail, $name, $meta, $pass, $initials]): ?>
                    <button type="button" class="gsuite-btn js-quick"
                            data-email="<?= e($mail) ?>" data-password="<?= e($pass) ?>">
                        <span class="g-avatar"><?= e($initials) ?></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="g-name d-block text-truncate"><?= e($name) ?></span>
                            <span class="g-mail d-block text-truncate"><?= e($mail) ?></span>
                        </span>
                        <i class="fa-solid fa-chevron-right text-muted2"></i>
                    </button>
                <?php endforeach; ?>

                <button type="button" class="btn btn-ghost btn-sm2 w-100 js-quick mt-1"
                        data-email="ssc.admin@g.batstate-u.edu.ph" data-password="SSC@sigla2026">
                    <i class="fa-solid fa-user-shield"></i> SSC Staff
                </button>
            </div>

            <p class="text-center text-muted2 mt-4 mb-0" style="font-size:.74rem;">
                <i class="fa-solid fa-shield-halved me-1"></i>
                Protected by the BatStateU Data Privacy Policy
            </p>
        </div>
    </main>
</div>

<?php require __DIR__ . '/layouts/scripts.php'; ?>

<script>
    var ALLOWED_DOMAINS = <?= json_encode(array_map(fn($d) => '@' . $d, ALLOWED_EMAIL_DOMAINS)) ?>;

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
            return guess
                ? 'Check the spelling — did you mean <strong>' + local + '@' + guess + '</strong>?'
                : 'e-RESERVE only accepts ' + ALLOWED_DOMAINS.join(' or ') + ' addresses.';
        }

    /* One-click demo sign-in: fill the form and submit. */
    document.querySelectorAll('.js-quick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('email').value = btn.dataset.email;
            document.getElementById('password').value = btn.dataset.password || '';
            document.getElementById('loginForm').submit();
        });
    });

    /* Friendly client-side domain check before the round trip. */
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        var email = document.getElementById('email').value.trim().toLowerCase();
        var warning = email ? domainWarning(email) : null;
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

<?php
/**
 * login.php — Mock BatStateU GSuite sign-in.
 *
 * PROTOTYPE: no password check, no OAuth. The email is simply validated
 * against the university domain and a profile is fabricated.
 *
 * REPLACE LATER:
 *   - Google Workspace OAuth 2.0 (hd=g.batstate-u.edu.ph) OR
 *   - SELECT * FROM tbl_students WHERE email = ? AND status = 'active'
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
    $email = trim((string) ($_POST['email'] ?? ''));
    $role  = (string) ($_POST['role'] ?? 'student');
    $goto  = (string) ($_POST['next'] ?? '');

    if ($email === '') {
        $error = 'Please enter your BatStateU GSuite email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That does not look like a valid email address.';
    } elseif (!str_ends_with(strtolower($email), '@g.batstate-u.edu.ph')) {
        $error = 'Only @g.batstate-u.edu.ph accounts may sign in.';
    } else {
        // --- "Authenticate" -------------------------------------------------
        $_SESSION['user'] = in_array($role, ['admin', 'finance'], true)
            ? mock_staff_from_email($email, $role)
            : mock_student_from_email($email);

        $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');

        set_flash(
            'success',
            'Welcome, ' . explode(' ', $_SESSION['user']['name'])[0] . '!',
            'You are signed in to ' . APP_NAME . ' as <strong>' . e($_SESSION['user']['email']) . '</strong>.'
        );

        if (in_array($role, ['admin', 'finance'], true)) {
            redirect($role === 'finance' ? 'finance/index.php' : 'admin/index.php');
        }
        redirect($goto === 'reserve' ? 'reserve.php' : 'dashboard.php');
    }
}

/* Demo accounts shown as one-click chips. */
$quickAccounts = [
    ['althea.ramirez@g.batstate-u.edu.ph', 'Althea Mae Ramirez', 'BS Information Technology · 3rd Year', 'student', 'AR'],
    ['joshua.torres@g.batstate-u.edu.ph',  'Joshua Miguel Torres', 'BS Computer Science · 2nd Year',   'student', 'JT'],
];

$pageTitle = 'Sign In';
$bodyClass = 'auth-page';
require __DIR__ . '/layouts/head.php';
?>

<div class="auth-shell">

    <!-- ================= Brand side ================= -->
    <aside class="auth-aside">
        <div>
            <a class="brand-lockup" href="<?= url('index.php') ?>">
                <span class="brand-mark" style="background:rgba(255,255,255,.12);"><i class="fa-solid fa-ticket"></i></span>
                <span class="brand-text">
                    <span class="brand-name d-block text-white">e-<span style="color:var(--gold-400);">RESERVE</span></span>
                    <span class="brand-sub" style="color:rgba(255,255,255,.5);">SIGLA 2026</span>
                </span>
            </a>
        </div>

        <div>
            <span class="eyebrow on-dark mb-3"><i class="fa-solid fa-fire"></i> 70th Founding Anniversary</span>
            <h2 class="text-white mt-4" style="font-size:2.6rem;line-height:1.08;letter-spacing:-1.6px;">
                SIGLA:<br>
                <span style="background:var(--grad-gold);-webkit-background-clip:text;background-clip:text;color:transparent;">
                    A Student's Night
                </span><br>2026
            </h2>
            <p style="color:rgba(255,255,255,.66);max-width:400px;margin-top:1.2rem;">
                Sign in with your university GSuite account to reserve your slot, pay through GCash,
                and keep your e-ticket in your pocket.
            </p>

            <div class="countdown-wrap mt-4" data-countdown="<?= EVENT_DATE_ISO ?>" style="max-width:390px;">
                <div class="cd-box"><div class="cd-num" data-cd="days">00</div><div class="cd-label">Days</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="hours">00</div><div class="cd-label">Hrs</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="minutes">00</div><div class="cd-label">Min</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="seconds">00</div><div class="cd-label">Sec</div></div>
            </div>
        </div>

        <div style="font-size:.76rem;color:rgba(255,255,255,.45);">
            <span class="footer-motto d-block mb-1"><?= EVENT_MOTTO ?></span>
            Supreme Student Council · <?= EVENT_HOST ?>
        </div>
    </aside>

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
                Use your official <strong class="text-deep">@g.batstate-u.edu.ph</strong> account.
                <span class="d-block text-muted2 mt-1" style="font-size:.78rem;">
                    <i class="fa-solid fa-circle-info me-1"></i> Prototype mode — no password required.
                </span>
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert d-flex align-items-start gap-2 mb-4"
                     style="background:rgba(217,72,59,.08);border:1px solid rgba(217,72,59,.22);border-radius:14px;color:#A8352A;font-size:.84rem;">
                    <i class="fa-solid fa-circle-exclamation mt-1"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" id="loginForm" novalidate>
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <input type="hidden" name="role" id="roleField" value="<?= $panel === 'admin' ? 'admin' : 'student' ?>">

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
                        <input type="password" class="form-control" id="password"
                               placeholder="Not required in prototype mode"
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

            <div class="divider-or">Or continue as</div>

            <div class="d-grid gap-2">
                <?php foreach ($quickAccounts as [$mail, $name, $meta, $role, $initials]): ?>
                    <button type="button" class="gsuite-btn js-quick"
                            data-email="<?= e($mail) ?>" data-role="<?= e($role) ?>">
                        <span class="g-avatar"><?= e($initials) ?></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="g-name d-block text-truncate"><?= e($name) ?></span>
                            <span class="g-mail d-block text-truncate"><?= e($mail) ?></span>
                        </span>
                        <i class="fa-solid fa-chevron-right text-muted2"></i>
                    </button>
                <?php endforeach; ?>

                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <button type="button" class="btn btn-ghost btn-sm2 w-100 js-quick"
                                data-email="ssc.admin@g.batstate-u.edu.ph" data-role="admin">
                            <i class="fa-solid fa-user-shield"></i> Admin
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-ghost btn-sm2 w-100 js-quick"
                                data-email="finance.office@g.batstate-u.edu.ph" data-role="finance">
                            <i class="fa-solid fa-chart-pie"></i> Finance
                        </button>
                    </div>
                </div>
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
    /* One-click demo sign-in: fill the form, flag the role, submit. */
    document.querySelectorAll('.js-quick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('email').value = btn.dataset.email;
            document.getElementById('roleField').value = btn.dataset.role;
            document.getElementById('loginForm').submit();
        });
    });

    /* Friendly client-side domain check before the round trip. */
    document.getElementById('loginForm').addEventListener('submit', function (e) {
        var email = document.getElementById('email').value.trim().toLowerCase();
        if (email && !email.endsWith('@g.batstate-u.edu.ph')) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'University Account Required',
                html: 'e-RESERVE only accepts <strong>@g.batstate-u.edu.ph</strong> GSuite accounts.<br>' +
                      'Try <em>juan.delacruz@g.batstate-u.edu.ph</em>.',
                confirmButtonText: 'Let me fix that'
            });
        }
    });
</script>

<?php
/**
 * register.php — student sign-up.
 *
 * Only students register here. The role is written by register_student() and
 * never read from this form, so no amount of tampering with the POST body can
 * mint an admin account.
 */
require_once __DIR__ . '/includes/config.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'dashboard.php');
}

$next    = $_GET['next'] ?? '';
$pending = $_SESSION['google_pending'] ?? null;      // set by google.php after Google confirms the address
$error = '';
$field = '';
$old   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();

    $old    = $_POST;
    $result = $pending ? register_google_student($_POST) : register_student($_POST);

    if ($result['ok']) {
        if ($pending) {
            set_flash('success', 'Welcome, ' . explode(' ', $result['user']['name'])[0] . '!', 'Your account is ready. Upload your three documents to get your ticket.');
            redirect('documents.php');
        }
        set_flash(
            'success',
            'Welcome to e-RESERVE, ' . explode(' ', $result['user']['name'])[0] . '!',
            'One last step — open the confirmation link we sent to <strong>'
            . e($result['user']['email']) . '</strong>.'
        );
        /* Straight to the confirm screen: nothing can be uploaded until the
           address is proved, so sending them to the dashboard would mislead. */
        redirect('verify.php');
    }

    $error = $result['message'];
    $field = (string) ($result['field'] ?? '');
}

/** Re-fill a field after a failed submit. */
$val = static fn(string $key): string => e((string) ($old[$key] ?? ($key === 'name' ? ($pending['name'] ?? '') : '')));
/** Mark the field the validator complained about. */
$bad = static fn(string $key): string => $field === $key ? ' is-invalid' : '';

$pageTitle = 'Create Account';
$bodyClass = 'auth-page';
require __DIR__ . '/layouts/head.php';
?>

<div class="auth-shell">

    <?php $asideMode = 'register'; require __DIR__ . '/layouts/auth_aside.php'; ?>

    <!-- ================= Form side ================= -->
    <main class="auth-main">
        <span class="orb orb-gold orb-soft" style="width:340px;height:340px;top:-100px;right:-120px;"></span>
        <span class="orb orb-teal orb-soft" style="width:300px;height:300px;bottom:-120px;left:-100px;"></span>

        <div class="auth-card fade-up" style="max-width:520px;">
            <a href="<?= url('index.php') ?>" class="text-muted2 fw-800 d-inline-flex align-items-center gap-2 mb-4"
               style="font-size:.76rem;letter-spacing:1.2px;text-transform:uppercase;">
                <i class="fa-solid fa-arrow-left"></i> Back to event page
            </a>

            <h3 style="font-size:1.55rem;"><?= $pending ? 'Finish your profile' : 'Create your account' ?></h3>
            <p class="text-soft mb-4" style="font-size:.87rem;">
                Use your official <strong class="text-deep"><?= e(allowed_domains_text()) ?></strong> address.
                Already registered?
                <a href="<?= url('login.php' . ($next !== '' ? '?next=' . urlencode($next) : '')) ?>" class="text-gold fw-800">Sign in</a>.
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert d-flex align-items-start gap-2 mb-4"
                     style="background:rgba(217,72,59,.08);border:1px solid rgba(217,72,59,.22);border-radius:14px;color:#A8352A;font-size:.84rem;">
                    <i class="fa-solid fa-circle-exclamation mt-1"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($pending): ?>
                <div class="google-chip">
                    <svg class="g-logo" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.2 5.5-4.7 7.2l7.6 5.9c4.4-4.1 6.9-10.1 6.9-17.6z"/><path fill="#FBBC05" d="M10.5 28.7c-.5-1.5-.8-3-.8-4.7s.3-3.2.8-4.7l-7.9-6.1C.9 16.3 0 20 0 24s.9 7.7 2.6 10.8l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                    <div class="flex-grow-1 min-w-0">
                        <strong class="d-block text-truncate"><?= e($pending['email']) ?></strong>
                        <span>Confirmed by Google — no email link or password needed.</span>
                    </div>
                    <i class="fa-solid fa-circle-check text-green"></i>
                </div>
            <?php else: ?>
                <a href="<?= url('google.php') ?>" class="btn-google">
                    <svg class="g-logo" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.2 5.5-4.7 7.2l7.6 5.9c4.4-4.1 6.9-10.1 6.9-17.6z"/><path fill="#FBBC05" d="M10.5 28.7c-.5-1.5-.8-3-.8-4.7s.3-3.2.8-4.7l-7.9-6.1C.9 16.3 0 20 0 24s.9 7.7 2.6 10.8l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                    <span>Sign up with Google</span>
                </a>
                <div class="divider-or">or fill in the form</div>
            <?php endif; ?>

            <form method="post" id="registerForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <div class="mb-3">
                    <label class="form-label-sm d-block" for="name">Full Name</label>
                    <input type="text" class="form-control<?= $bad('name') ?>" id="name" name="name"
                           value="<?= $val('name') ?>" placeholder="Juan Miguel Dela Cruz"
                           autocomplete="name" maxlength="160" required>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6 mb-1">
                        <label class="form-label-sm d-block" for="student_no">SR-Code</label>
                        <input type="text" class="form-control<?= $bad('student_no') ?>" id="student_no" name="student_no"
                               value="<?= $val('student_no') ?>" placeholder="22-10234"
                               maxlength="9" autocapitalize="characters" required>
                    </div>
                    <div class="col-sm-6 mb-1">
                        <label class="form-label-sm d-block" for="mobile">Mobile Number <span class="text-muted2">(optional)</span></label>
                        <input type="text" class="form-control<?= $bad('mobile') ?>" id="mobile" name="mobile"
                               value="<?= $val('mobile') ?>" placeholder="09171234567"
                               inputmode="numeric" maxlength="13">
                    </div>
                </div>

                <?php if (!$pending): ?>
                <div class="mb-3 mt-3">
                    <label class="form-label-sm d-block" for="email">GSuite Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-brands fa-google"></i></span>
                        <input type="email" class="form-control<?= $bad('email') ?>" id="email" name="email"
                               value="<?= $val('email') ?>" placeholder="juan.delacruz@g.batstate-u.edu.ph"
                               autocomplete="email"
                               style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;" required>
                    </div>
                </div>

                <?php else: ?>
                    <div class="mt-3"></div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label-sm d-block" for="college">College</label>
                    <select class="form-select<?= $bad('college') ?>" id="college" name="college" required>
                        <option value="">Choose your college…</option>
                        <?php foreach (colleges() as $code => $label): ?>
                            <option value="<?= e($code) ?>" title="<?= e($label) ?>" <?= ($old['college'] ?? '') === $code ? 'selected' : '' ?>><?= e($code) ?> — <?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="cell-sub mt-1">Laboratory School students are not eligible for this event.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label-sm d-block" for="program">Programme</label>
                    <select class="form-select<?= $bad('program') ?>" id="program" name="program" required>
                        <option value=""><?= ($old['college'] ?? '') === '' ? 'Choose your college first…' : 'Choose your programme…' ?></option>
                        <?php foreach (college_programs()[$old['college'] ?? ''] ?? [] as $p): ?>
                            <option value="<?= e($p) ?>" <?= ($old['program'] ?? '') === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label-sm d-block" for="year_level">Year Level</label>
                    <select class="form-select<?= $bad('year_level') ?>" id="year_level" name="year_level" required>
                        <option value="">Choose…</option>
                        <?php foreach (year_levels() as $y): ?>
                            <option value="<?= e($y) ?>" <?= ($old['year_level'] ?? '') === $y ? 'selected' : '' ?>><?= e($y) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$pending): ?>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label class="form-label-sm d-block" for="password">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control<?= $bad('password') ?>" id="password" name="password"
                                   placeholder="At least 8 characters" autocomplete="new-password"
                                   style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;" required>
                        </div>
                        <div class="cell-sub mt-1">Mix letters and numbers.</div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label-sm d-block" for="password_confirm">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control<?= $bad('password_confirm') ?>" id="password_confirm"
                                   name="password_confirm" placeholder="Type it again" autocomplete="new-password"
                                   style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;" required>
                        </div>
                        <div class="cell-sub mt-1" data-match-hint></div>
                    </div>
                </div>

                <?php endif; ?>

                <button type="submit" class="btn btn-green btn-block2 btn-lg2">
                    <i class="fa-solid fa-user-plus"></i> <?= $pending ? 'Finish &amp; continue' : 'Create Account' ?>
                </button>
            </form>

            <p class="text-center text-muted2 mt-4 mb-0" style="font-size:.74rem;">
                <i class="fa-solid fa-shield-halved me-1"></i>
                By registering you agree that the SSC may process your details for
                SIGLA 2026 ticketing, under the BatStateU Data Privacy Policy.
            </p>
        </div>
    </main>
</div>

<?php require __DIR__ . '/layouts/scripts.php'; ?>

<script>
    (function () {
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
        /* Programme choices follow the college. The server re-checks them. */
        var PROGRAMS = <?= json_encode(college_programs(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        var collegeSel = document.getElementById('college');
        var programSel = document.getElementById('program');
        function fillPrograms(keep) {
            var list = PROGRAMS[collegeSel.value] || [];
            programSel.innerHTML = '';
            var first = document.createElement('option');
            first.value = '';
            first.textContent = list.length ? 'Choose your programme…' : 'Choose your college first…';
            programSel.appendChild(first);
            list.forEach(function (p) {
                var o = document.createElement('option');
                o.value = p; o.textContent = p;
                if (p === keep) { o.selected = true; }
                programSel.appendChild(o);
            });
            programSel.disabled = !list.length;
        }
        collegeSel.addEventListener('change', function () { fillPrograms(''); });
        fillPrograms(programSel.value);

        var form  = document.getElementById('registerForm');
        var pass  = document.getElementById('password');
        var again = document.getElementById('password_confirm');
        var hint  = document.querySelector('[data-match-hint]');

        /* Say so the moment the two boxes disagree, not after a round trip. */
        function checkMatch() {
            if (!again.value) { hint.textContent = ''; hint.style.color = ''; return; }
            var same = pass.value === again.value;
            hint.textContent = same ? 'Passwords match.' : 'Passwords do not match.';
            hint.style.color = same ? 'var(--success)' : 'var(--danger)';
        }
        if (pass && again) {
            pass.addEventListener('input', checkMatch);
            again.addEventListener('input', checkMatch);
        }

        /* Students type their number with or without the dash. */
        var no = document.getElementById('student_no');
        no.addEventListener('blur', function () {
            var digits = no.value.replace(/\D/g, '');
            if (digits.length === 7) { no.value = digits.slice(0, 2) + '-' + digits.slice(2); }
        });

        form.addEventListener('submit', function (e) {
            var emailBox = document.getElementById('email');
            var email = emailBox ? emailBox.value.trim().toLowerCase() : '';
            var warning = email ? domainWarning(email) : null;
            if (warning) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'University Account Required',
                    html: warning,
                    confirmButtonText: 'Let me fix that'
                });
                return;
            }
            if (pass && again && pass.value !== again.value) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Passwords do not match', confirmButtonText: 'Try again' });
            }
        });
    })();
</script>

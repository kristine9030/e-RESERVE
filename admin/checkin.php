<?php
/**
 * admin/checkin.php — Gate check-in simulator.
 *
 * A scanner would normally POST the decoded QR payload here.
 * REPLACE LATER:
 *   UPDATE tbl_tickets SET checked_in = 1, time_in = NOW(), gate = ?
 *   WHERE ticket_no = ? AND status = 'valid'
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$tickets = sample_tickets();
$scanLog = $_SESSION['gate_log'] ?? [];
$result  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim((string) ($_POST['ticket_no'] ?? '')));
    $gate = (string) ($_POST['gate'] ?? 'Gate A');

    // 1. Look in the sample registry.
    $match = null;
    foreach ($tickets as $t) {
        if (strtoupper($t['ticket_no']) === $code) { $match = $t; break; }
    }

    // 2. Also allow the live session ticket (the one the student just bought).
    $mine = get_ticket();
    if (!$match && $mine && strtoupper($mine['ticket_no']) === $code) {
        $match = $mine + ['checked_in' => (bool) $mine['checked_in']];
    }

    if ($code === '') {
        $result = ['tone' => 'warning', 'title' => 'No code entered', 'text' => 'Scan or type a ticket number first.'];
    } elseif (!$match) {
        $result = ['tone' => 'error', 'title' => 'Ticket not found', 'text' => 'No ticket matches <strong>' . e($code) . '</strong>.'];
    } elseif (in_array($code, array_column($scanLog, 'ticket_no'), true)) {
        $result = ['tone' => 'warning', 'title' => 'Already scanned', 'text' => '<strong>' . e($code) . '</strong> was checked in earlier at this gate session.'];
    } else {
        $entry = [
            'ticket_no' => $code,
            'name'      => $match['name'],
            'student_no'=> $match['student_no'],
            'program'   => $match['program'] ?? '—',
            'gate'      => $gate,
            'time_in'   => date('Y-m-d H:i:s'),
        ];
        array_unshift($scanLog, $entry);
        $_SESSION['gate_log'] = array_slice($scanLog, 0, 40);

        // Reflect it on the student's own ticket when it is the session one.
        if (isset($_SESSION['ticket']) && strtoupper($_SESSION['ticket']['ticket_no']) === $code) {
            $_SESSION['ticket']['checked_in'] = true;
            $_SESSION['ticket']['status']     = 'used';
        }

        $result = [
            'tone'  => 'success',
            'title' => 'Welcome, ' . explode(' ', $match['name'])[0] . '!',
            'text'  => '<strong>' . e($code) . '</strong> admitted at ' . e($gate) . '.',
        ];
    }

    $scanLog = $_SESSION['gate_log'] ?? [];
}

$pageTitle = 'Gate Check-in';
$crumb     = 'Administration';
$activeNav = 'checkin';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <span class="eyebrow on-dark"><i class="fa-solid fa-qrcode"></i> Entrance Control</span>
    <h2>Gate Check-in Simulator</h2>
    <p>Scan or type a ticket number to admit a student. Try a number from the Tickets registry.</p>
</div>

<div class="row g-4">

    <!-- ================= Scanner ================= -->
    <div class="col-lg-5">
        <div class="card-soft fade-up d1">
            <div class="card-head"><h5 class="mb-0">Scanner</h5>
                <span class="badge-soft badge-success"><i class="fa-solid fa-circle" style="font-size:.4rem;"></i> Online</span>
            </div>
            <div class="card-body-p">

                <div class="text-center mb-4 p-4" style="background:var(--green-050);border-radius:var(--radius-sm);border:2px dashed var(--green-200);">
                    <i class="fa-solid fa-qrcode text-green spin-slow" style="font-size:3.2rem;opacity:.4;"></i>
                    <p class="cell-sub mb-0 mt-3">Camera scanner placeholder<br>Manual entry works below</p>
                </div>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label-sm d-block" for="ticket_no">Ticket Number</label>
                        <input type="text" class="form-control mono" id="ticket_no" name="ticket_no"
                               placeholder="<?= TICKET_PREFIX ?>000100" autocomplete="off" autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-sm d-block" for="gate">Gate</label>
                        <select class="form-select" id="gate" name="gate">
                            <option>Gate A</option><option>Gate B</option><option>Gate C</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-green btn-block2 btn-lg2">
                        <i class="fa-solid fa-user-check"></i> Verify &amp; Admit
                    </button>
                </form>

                <?php $sample = $tickets[0]['ticket_no'] ?? TICKET_PREFIX . '000100'; ?>
                <p class="cell-sub text-center mt-3 mb-0">
                    Sample code:
                    <a href="#" class="text-gold fw-800 js-fill" data-code="<?= e($sample) ?>"><?= e($sample) ?></a>
                    <?php if ($mine = get_ticket()): ?>
                        · <a href="#" class="text-gold fw-800 js-fill" data-code="<?= e($mine['ticket_no']) ?>">yours</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ================= Log ================= -->
    <div class="col-lg-7">
        <div class="card-soft fade-up d2">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Check-in Log</h5>
                    <span class="cell-sub"><?= count($scanLog) ?> admitted this session</span>
                </div>
                <span class="badge-soft badge-gold"><i class="fa-solid fa-clock"></i> Live</span>
            </div>

            <?php if (!$scanLog): ?>
                <div class="empty-state">
                    <div class="empty-art"><i class="fa-solid fa-door-open"></i></div>
                    <h6>No one has been admitted yet</h6>
                    <p style="font-size:.86rem;">Scanned tickets appear here in real time.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0" style="width:100%;">
                        <thead>
                            <tr style="background:var(--green-050);">
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);padding:.9rem 1rem;">Ticket</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);">Student</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);">Gate</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);">Time In</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scanLog as $s): ?>
                                <tr>
                                    <td style="padding:.8rem 1rem;border-bottom:1px solid var(--line);">
                                        <span class="mono cell-strong"><?= e($s['ticket_no']) ?></span>
                                    </td>
                                    <td style="border-bottom:1px solid var(--line);">
                                        <div class="cell-strong"><?= e($s['name']) ?></div>
                                        <div class="cell-sub"><?= e($s['student_no']) ?></div>
                                    </td>
                                    <td style="border-bottom:1px solid var(--line);">
                                        <span class="badge-soft badge-info"><?= e($s['gate']) ?></span>
                                    </td>
                                    <td class="cell-sub" style="border-bottom:1px solid var(--line);">
                                        <?= pretty_date($s['time_in'], 'M d · g:i:s A') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

<script>
    document.querySelectorAll('.js-fill').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            document.getElementById('ticket_no').value = a.dataset.code;
            document.getElementById('ticket_no').focus();
        });
    });

    <?php if ($result): ?>
    Swal.fire({
        icon: <?= json_encode($result['tone']) ?>,
        title: <?= json_encode($result['title']) ?>,
        html: <?= json_encode($result['text']) ?>,
        confirmButtonText: 'Next student',
        timer: 3200,
        timerProgressBar: true
    });
    <?php endif; ?>
</script>

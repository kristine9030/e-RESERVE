<?php
/**
 * admin/checkin.php — Gate check-in and live attendance monitor.
 *
 * The camera scanner reads a real QR code off the student's e-ticket, decodes
 * the ticket number and submits it here — the same path the manual entry box
 * uses, so both routes hit one code path.
 *
 * How it is enforced:
 * A scan writes a real row into `checkins`. The UNIQUE index on ticket_id
 * is what makes a ticket single-use; the signature inside the QR payload is
 * what makes a fabricated ticket number fail.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$result  = null;
$scanLog = [];

/**
 * Pull a ticket number out of whatever the scanner handed us. Accepts the bare
 * code, a "code|student_no" pair, a verification URL or a JSON payload, so an
 * older ticket still scans after the payload format changes.
 */
function extract_ticket_code(string $raw): string
{
    $raw = trim($raw);

    if ($raw !== '' && ($json = json_decode($raw, true)) && isset($json['ticket'])) {
        $raw = (string) $json['ticket'];
    } elseif (stripos($raw, 'http') === 0) {
        parse_str((string) parse_url($raw, PHP_URL_QUERY), $query);
        $raw = (string) ($query['t'] ?? $raw);
    }

    if (preg_match('/' . preg_quote(TICKET_PREFIX, '/') . '\d+/i', $raw, $m)) {
        return strtoupper($m[0]);
    }

    return strtoupper(trim(explode('|', $raw)[0]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();

    $code = extract_ticket_code((string) ($_POST['ticket_no'] ?? ''));

    $gates = ['Gate A', 'Gate B', 'Gate C'];
    $gate  = (string) ($_POST['gate'] ?? 'Gate A');
    if (!in_array($gate, $gates, true)) {
        $gate = 'Gate A';
    }

    /* ticket_from_payload() also checks the signature carried in the QR, so a
       hand-typed or edited code is rejected before it ever reaches the DB. */
    $ticket = $code === '' ? null : ticket_from_payload((string) ($_POST['ticket_no'] ?? ''));

    if ($code === '') {
        $result = ['tone' => 'warning', 'title' => 'No code entered', 'text' => 'Scan or type a ticket number first.'];
    } elseif (!$ticket) {
        $result = [
            'tone'  => 'error',
            'title' => 'Ticket not recognised',
            'text'  => 'No valid ticket matches <strong>' . e($code) . '</strong>. '
                     . 'A fabricated or edited code fails this check.',
        ];
    } elseif ($ticket['status'] === 'void') {
        $result = ['tone' => 'error', 'title' => 'Ticket voided', 'text' => '<strong>' . e($code) . '</strong> is no longer valid for entry.'];
    } else {
        try {
            /* UNIQUE(ticket_id) on checkins is what actually prevents a second
               entry — two gates scanning at the same moment cannot both win. */
            db_run(
                'INSERT INTO checkins (ticket_id, gate, scanner, scanned_by, scanned_at) VALUES (?, ?, ?, ?, NOW())',
                [$ticket['id'], $gate, 'SSC-Scanner-1', current_user()['id']]
            );
            db_run('UPDATE tickets SET status = "used" WHERE id = ?', [$ticket['id']]);

            audit('gate.checkin', 'ticket', $ticket['ticket_no'], ['gate' => $gate]);

            $result = [
                'tone'  => 'success',
                'title' => 'Welcome, ' . explode(' ', $ticket['name'])[0] . '!',
                'text'  => '<strong>' . e($ticket['ticket_no']) . '</strong> · ' . e($ticket['student_no'])
                         . '<br>' . e($ticket['program']) . '<br>Admitted at ' . e($gate) . '.',
            ];
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }

            $seen = db_one('SELECT gate, scanned_at FROM checkins WHERE ticket_id = ?', [$ticket['id']]);

            $result = [
                'tone'  => 'warning',
                'title' => 'Already checked in',
                'text'  => '<strong>' . e($ticket['ticket_no']) . '</strong> was admitted at '
                         . e($seen['gate'] ?? 'the gate') . ' on ' . pretty_date($seen['scanned_at'] ?? null) . '.',
            ];
        }
    }
}

/* Recent scans, read back from the database rather than the session. */
$scanLog = db_all(
    'SELECT c.gate, c.scanned_at AS time_in, t.ticket_no,
            u.name, u.student_no, u.program, u.year_level
     FROM checkins c
     JOIN tickets t ON t.id = c.ticket_id
     JOIN users u   ON u.id = t.user_id
     ORDER BY c.scanned_at DESC, c.id DESC
     LIMIT 40'
);

/* --- Live monitor figures ------------------------------------------------ */
$expected  = (int) db_value('SELECT COUNT(*) FROM tickets WHERE status <> "void"', [], 0);
$admitted  = (int) db_value('SELECT COUNT(*) FROM checkins', [], 0);
$fillPct   = $expected > 0 ? (int) round($admitted / $expected * 100) : 0;
$byGate    = [];
foreach (db_all('SELECT gate, COUNT(*) AS n FROM checkins GROUP BY gate') as $g) {
    $byGate[$g['gate']] = (int) $g['n'];
}
$lastScan  = $scanLog[0]['time_in'] ?? null;

$pageTitle = 'Gate Scan';
$crumb     = 'Administration';
$activeNav = 'checkin';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-qrcode"></i> Entrance Control</span>
            <h2>Gate Scan &amp; Live Monitor</h2>
            <p>Point the camera at a student's ticket QR code — it is read, verified and the student is admitted automatically.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <div class="tg-label mb-1">Admitted so far</div>
            <div class="fw-900" style="font-size:2.6rem;line-height:1;color:var(--gold-400);letter-spacing:-2px;"><?= number_format($admitted) ?></div>
            <div style="font-size:.78rem;color:rgba(255,255,255,.6);"><?= $fillPct ?>% of <?= number_format($expected) ?> issued tickets</div>
        </div>
    </div>
</div>

<!-- ================= Live monitor strip ================= -->
<div class="row g-3 mb-4">
    <?php
    $monitor = [
        ['fa-user-check',     'green',  'Admitted',       number_format($admitted)],
        ['fa-hourglass-half', 'orange', 'Still Expected', number_format(max(0, $expected - $admitted))],
        ['fa-door-open',      'gold',   'Gate A / B / C', number_format($byGate['Gate A'] ?? 0) . ' / ' . number_format($byGate['Gate B'] ?? 0) . ' / ' . number_format($byGate['Gate C'] ?? 0)],
        ['fa-ticket',         'deep',   'Tickets Issued', number_format($expected)],
    ];
    foreach ($monitor as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value"><?= $val ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">

    <!-- ================= Scanner ================= -->
    <div class="col-lg-5">
        <div class="card-soft fade-up d1">
            <div class="card-head"><h5 class="mb-0">QR Scanner</h5>
                <span class="badge-soft badge-success" id="scanStatus"><i class="fa-solid fa-circle" style="font-size:.4rem;"></i> Ready</span>
            </div>
            <div class="card-body-p">

                <div class="scan-stage mb-3">
                    <div id="qrReader"></div>
                    <div class="scan-idle" id="scanIdle">
                        <i class="fa-solid fa-camera"></i>
                        <p class="cell-sub mb-0 mt-2">Start the camera to scan an e-ticket,<br>or type the number below.</p>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-4">
                    <button type="button" class="btn btn-green btn-block2" id="btnStartCam">
                        <i class="fa-solid fa-camera"></i> Start Camera
                    </button>
                    <button type="button" class="btn btn-ghost btn-block2 d-none" id="btnStopCam">
                        <i class="fa-solid fa-stop"></i> Stop
                    </button>
                </div>

                <form method="post" id="scanForm">
                    <?= csrf_field() ?>
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

                <?php $sample = db_value('SELECT ticket_no FROM tickets WHERE status = "valid" ORDER BY id LIMIT 1'); ?>
                <?php if ($sample): ?>
                    <p class="cell-sub text-center mt-3 mb-0">
                        Sample code:
                        <a href="#" class="text-gold fw-800 js-fill" data-code="<?= e($sample) ?>"><?= e($sample) ?></a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Demo QR: something to point the camera at without a second device -->
        <?php
        $demo = db_one(
            'SELECT t.*, u.name, u.student_no FROM tickets t JOIN users u ON u.id = t.user_id
             WHERE t.status = "valid" ORDER BY t.id LIMIT 1'
        );
        ?>
        <?php if ($demo): ?>
            <div class="card-soft mt-4 fade-up d2">
                <div class="card-head">
                    <div>
                        <h6 class="mb-0">Demo Ticket QR</h6>
                        <span class="cell-sub">For testing the scanner on this screen</span>
                    </div>
                    <span class="badge-soft badge-success">Valid</span>
                </div>
                <div class="card-body-p text-center">
                    <div class="qr-frame mb-3">
                        <?= qr_svg(ticket_qr_payload(['ticket_no' => $demo['ticket_no'], 'verify_hash' => $demo['verify_hash']]), 150, ['label' => 'Demo ticket ' . $demo['ticket_no']]) ?>
                    </div>
                    <div class="mono fw-800 text-deep" style="font-size:.8rem;"><?= e($demo['ticket_no']) ?></div>
                    <div class="cell-sub"><?= e($demo['name']) ?> · <?= e($demo['student_no']) ?></div>
                    <p class="cell-sub mt-3 mb-0">
                        Open this page on a phone (or print the ticket) and hold it up to the camera —
                        it is a real QR code, so any scanner reads it.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================= Log ================= -->
    <div class="col-lg-7">
        <div class="card-soft fade-up d2">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Check-in Log</h5>
                    <span class="cell-sub">
                        <?= count($scanLog) ?> admitted this session<?= $lastScan ? ' · last ' . pretty_date($lastScan, 'g:i:s A') : '' ?>
                    </span>
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
                                        <div class="cell-sub"><?= e($s['program']) ?></div>
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

<!-- html5-qrcode: decodes the camera stream in the browser -->
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    /* ---- Manual helpers ---- */
    document.querySelectorAll('.js-fill').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            document.getElementById('ticket_no').value = a.dataset.code;
            document.getElementById('ticket_no').focus();
        });
    });

    /* ---- Live camera scanning ---- */
    (function () {
        var form      = document.getElementById('scanForm');
        var field     = document.getElementById('ticket_no');
        var status    = document.getElementById('scanStatus');
        var idle      = document.getElementById('scanIdle');
        var startBtn  = document.getElementById('btnStartCam');
        var stopBtn   = document.getElementById('btnStopCam');
        var scanner   = null;
        var submitted = false;

        function setStatus(tone, icon, label) {
            status.className = 'badge-soft badge-' + tone;
            status.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + label;
        }

        function onDecoded(text) {
            if (submitted) return;                 // one admit per scan session
            submitted = true;

            field.value = text;
            setStatus('success', 'fa-circle-check', 'Code read');

            if (scanner) scanner.stop().catch(function () {});
            window.eReserve.processing('Verifying ticket…', 700).then(function () {
                form.submit();
            });
        }

        startBtn.addEventListener('click', function () {
            if (typeof Html5Qrcode === 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Scanner unavailable',
                    text: 'The QR library could not be loaded. Type the ticket number instead.'
                });
                return;
            }

            scanner = new Html5Qrcode('qrReader', { verbose: false });
            setStatus('warning', 'fa-spinner', 'Starting camera…');

            scanner.start(
                { facingMode: 'environment' },
                { fps: 12, qrbox: { width: 220, height: 220 } },
                onDecoded,
                function () { /* per-frame misses are normal — stay quiet */ }
            ).then(function () {
                idle.style.display = 'none';
                startBtn.classList.add('d-none');
                stopBtn.classList.remove('d-none');
                setStatus('success', 'fa-camera', 'Scanning');
            }).catch(function (err) {
                setStatus('danger', 'fa-triangle-exclamation', 'Camera blocked');
                Swal.fire({
                    icon: 'warning',
                    title: 'Camera not available',
                    html: 'The browser refused camera access.<br>' +
                          '<span style="font-size:.8rem;color:#7C8F88;">' + err + '</span><br><br>' +
                          'Manual entry below still works.'
                });
            });
        });

        stopBtn.addEventListener('click', function () {
            if (!scanner) return;
            scanner.stop().then(function () {
                idle.style.display = '';
                stopBtn.classList.add('d-none');
                startBtn.classList.remove('d-none');
                setStatus('success', 'fa-circle', 'Ready');
            }).catch(function () {});
        });
    })();

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

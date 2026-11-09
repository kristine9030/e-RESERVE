@extends('layouts.app')
@section('title', 'Gate Scan')
@section('crumb', 'Administration')
@section('nav', 'checkin')

@section('content')
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-qrcode"></i> Entrance Control</span>
            <h2>Gate Scan &amp; Live Monitor</h2>
            <p>Point the camera at a student's ticket QR code — it is read, verified and the student is admitted automatically.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <div class="tg-label mb-1">Admitted so far</div>
            <div class="fw-900" style="font-size:2.6rem;line-height:1;color:var(--gold-400);letter-spacing:-2px;">{{ number_format($admitted) }}</div>
            <div style="font-size:.78rem;color:rgba(255,255,255,.6);">{{ $fillPct }}% of {{ number_format($expected) }} issued tickets</div>
        </div>
    </div>
</div>

<!-- ================= Live monitor strip ================= -->
<div class="row g-3 mb-4">
    <?php $monitor = [
        ['fa-user-check',     'green',  'Admitted',       number_format($admitted)],
        ['fa-hourglass-half', 'orange', 'Still Expected', number_format(max(0, $expected - $admitted))],
        ['fa-door-open',      'gold',   'Gate A / B / C', number_format($byGate['Gate A'] ?? 0) . ' / ' . number_format($byGate['Gate B'] ?? 0) . ' / ' . number_format($byGate['Gate C'] ?? 0)],
        ['fa-ticket',         'deep',   'Tickets Issued', number_format($expected)],
    ];
    foreach ($monitor as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d{{ $i + 1 }}">
                <div class="stat-icon {{ $tone }}"><i class="fa-solid {{ $icon }}"></i></div>
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value">{{ $val }}</div>
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
                    {!! csrf_field() !!}
                    <div class="mb-3">
                        <label class="form-label-sm d-block" for="ticket_no">Ticket Number</label>
                        <input type="text" class="form-control mono" id="ticket_no" name="ticket_no"
                               placeholder="{{ config('ereserve.ticket.prefix') }}000100" autocomplete="off" autofocus>
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

            </div>
        </div>
    </div>

    <!-- ================= Log ================= -->
    <div class="col-lg-7">
        <div class="card-soft fade-up d2">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Check-in Log</h5>
                    <span class="cell-sub">
                        {{ count($scanLog) }} admitted this session{{ $lastScan ? ' · last ' . pretty_date($lastScan, 'g:i:s A') : '' }}
                    </span>
                </div>
                <span class="badge-soft badge-gold"><i class="fa-solid fa-clock"></i> Live</span>
            </div>

            @if (!$scanLog)
                <div class="empty-state">
                    <div class="empty-art"><i class="fa-solid fa-door-open"></i></div>
                    <h6>No one has been admitted yet</h6>
                    <p style="font-size:.86rem;">Scanned tickets appear here in real time.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-stack mb-0" style="width:100%;">
                        <thead>
                            <tr style="background:var(--green-050);">
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);padding:.9rem 1rem;">Ticket</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);">Student</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);">Gate</th>
                                <th style="border:0;font-size:.68rem;letter-spacing:1.1px;text-transform:uppercase;color:var(--green-800);">Time In</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($scanLog as $s)
                                <tr>
                                    <td style="padding:.8rem 1rem;border-bottom:1px solid var(--line);">
                                        <span class="mono cell-strong">{{ $s['ticket_no'] }}</span>
                                        <div class="cell-sub">{{ $s['program'] }}</div>
                                    </td>
                                    <td data-label="Student" style="border-bottom:1px solid var(--line);">
                                        <div class="cell-strong">{{ $s['name'] }}</div>
                                        <div class="cell-sub">{{ $s['student_no'] }}</div>
                                    </td>
                                    <td data-label="Gate" style="border-bottom:1px solid var(--line);">
                                        <span class="badge-soft badge-info">{{ $s['gate'] }}</span>
                                    </td>
                                    <td class="cell-sub" data-label="Time in" style="border-bottom:1px solid var(--line);">
                                        {{ pretty_date($s['time_in'], 'M d · g:i:s A') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
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

        function setStatus(tone, icon, label) {
            status.className = 'badge-soft badge-' + tone;
            status.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + label;
        }

        var busy = false, lastText = '', lastAt = 0;
        var scanUrl = {!! json_encode(route('admin.checkin.scan')) !!};
        var token   = form.querySelector('input[name=_token]').value;
        var gateSel = document.getElementById('gate');

        function resume() {
            busy = false;
            setStatus('success', 'fa-camera', 'Scanning');
            if (scanner) { try { scanner.resume(); } catch (e) {} }
        }

        function onDecoded(text) {
            var now = Date.now();
            if (busy || (text === lastText && now - lastAt < 4000)) return;   // ignore repeats of the same code
            busy = true; lastText = text; lastAt = now;

            field.value = text;
            setStatus('warning', 'fa-spinner', 'Verifying…');
            if (scanner) { try { scanner.pause(true); } catch (e) {} }

            fetch(scanUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ ticket_no: text, gate: gateSel.value })
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            }).then(function (res) {
                field.value = '';
                Swal.fire({
                    icon: res.tone, title: res.title, html: res.text,
                    confirmButtonText: 'Next student',
                    timer: res.ok ? 1800 : 3500, timerProgressBar: true
                }).then(function () {
                    resume();
                });
            }).catch(function (err) {
                Swal.fire({ icon: 'error', title: 'Could not verify',
                            text: 'Check the connection and scan again. (' + err.message + ')',
                            confirmButtonText: 'Scan again' }).then(resume);
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

    @if ($result)
    Swal.fire({
        icon: {!! json_encode($result['tone']) !!},
        title: {!! json_encode($result['title']) !!},
        html: {!! json_encode($result['text']) !!},
        confirmButtonText: 'Next student',
        timer: 3200,
        timerProgressBar: true
    });
    @endif
</script>
@endpush

@extends('layouts.app')
@section('title', 'My Documents')
@section('crumb', 'Student Portal')
@section('nav', 'documents')

@section('content')
@php $size = static fn(int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB'; @endphp
<div class="page-hero fade-up">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-file-arrow-up"></i> Step 2 of 4</span>
            <h2>{{ count($metas) > 1 ? 'Upload your ' . count($metas) . ' documents' : 'Upload your ' . reset($metas)['label'] }}</h2>
            <p>
                @if (count($metas) > 1)
                    One PDF each — your signed waiver, your COR and your school ID.
                    <strong class="text-white">SSC reviews them, and your ticket number is issued once all three are approved.</strong>
                @else
                    One PDF — {{ reset($metas)['hint'] }}
                    <strong class="text-white">SSC reviews it, and your ticket number is issued once it is approved.</strong>
                @endif
            </p>
        </div>
        <div class="col-lg-4">
            <div class="text-lg-end">
                <div class="tg-label mb-1">Uploaded</div>
                <div class="fw-900" style="font-size:2.6rem;line-height:1;color:var(--gold-400);letter-spacing:-2px;">
                    {{ $progress['uploaded'] }}<span style="font-size:1.4rem;color:rgba(255,255,255,.55);"> / {{ $progress['total'] }}</span>
                </div>
                <div class="prog-track mt-2" style="background:rgba(255,255,255,.14);"><div class="prog-fill gold" data-width="{{ $pct }}" style="width:0;"></div></div>
            </div>
        </div>
    </div>
</div>

@if ($ticket && $ticket['status'] === 'valid')
    <div class="callout callout-success fade-up">
        <i class="fa-solid fa-circle-check"></i>
        <div class="flex-grow-1">
            <strong>You already have a ticket — <span class="mono">{{ $ticket['ticket_no'] }}</span>.</strong>
            You can still replace a file below until staff approve it.
        </div>
        <a href="{{ route('ticket') }}" class="btn btn-green btn-sm2"><i class="fa-solid fa-qrcode"></i> Open ticket</a>
    </div>
@elseif ($progress['blocked'])
    <div class="callout callout-danger fade-up">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
            <strong>A document was returned.</strong>
            Your ticket is on hold. Read the note on the highlighted card, upload a corrected PDF and your ticket
            comes back with the same number.
        </div>
    </div>
@endif

<div class="row g-4">

    <!-- ================= Upload cards ================= -->
    <div class="col-xl-8">
        <?php foreach ($metas as $key => $meta):
            $doc     = $docs[$key] ?? null;
            $status  = $doc['status'] ?? 'missing';
            $locked  = $status === 'approved' || ($ticket['status'] ?? '') === 'used';
            $tone    = ['missing' => 'muted', 'submitted' => 'info', 'approved' => 'success', 'rejected' => 'danger'][$status]; ?>
            <form method="post" enctype="multipart/form-data" id="doc-{{ $key }}"
                  class="doc-card state-{{ $status }} fade-up js-doc-form" data-label="{{ $meta['label'] }}">
                {!! csrf_field() !!}
                <input type="hidden" name="doc_type" value="{{ $key }}">
                <input type="hidden" name="MAX_FILE_SIZE" value="{{ config('ereserve.doc_max_bytes') }}">

                <div class="doc-head">
                    <span class="doc-icon"><i class="fa-solid {{ $meta['icon'] }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="doc-step">Document {{ array_search($key, array_keys($metas), true) + 1 }} of {{ count($metas) }}</div>
                        <h5 class="mb-0">{{ $meta['label'] }}</h5>
                    </div>
                    {!! status_badge($status) !!}
                </div>

                <p class="doc-hint">{{ $meta['hint'] }}</p>

                <ul class="doc-rules">
                    @foreach ($meta['rules'] as $rule)
                        <li><i class="fa-solid fa-check"></i> {{ $rule }}</li>
                    @endforeach
                </ul>

                @if ($status === 'rejected' && !empty($doc['note']))
                    <div class="doc-note">
                        <i class="fa-solid fa-comment-dots"></i>
                        <div><strong>Reviewer’s note</strong><br>{{ $doc['note'] }}</div>
                    </div>
                @endif

                @if ($doc)
                    <div class="doc-file">
                        <i class="fa-solid fa-file-pdf"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="doc-file-name text-truncate">{{ $doc['name'] }}</div>
                            <div class="cell-sub">{{ $size($doc['size']) }} · uploaded {{ pretty_date($doc['uploaded_at']) }}</div>
                        </div>
                        <a href="{{ document_url($doc['id']) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm2">
                            <i class="fa-solid fa-eye"></i> View
                        </a>
                    </div>
                @endif

                @if ($locked)
                    <div class="doc-locked"><i class="fa-solid fa-lock"></i>
                        {{ $status === 'approved' ? 'Approved by SSC staff — this file is locked.' : 'Your ticket has been used — files are locked.' }}
                    </div>
                @else
                    <label class="dropzone" for="file-{{ $key }}">
                        <input type="file" id="file-{{ $key }}" name="document" accept="application/pdf,.pdf" class="dropzone-input" required>
                        <span class="dropzone-art"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                        <span class="dropzone-text">
                            <strong class="dz-title">{{ $doc ? 'Choose a replacement PDF' : 'Choose your PDF' }}</strong>
                            <span class="dz-sub">or drag and drop it here · PDF only · up to {{ (int) (config('ereserve.doc_max_bytes') / 1024 / 1024) }} MB</span>
                        </span>
                    </label>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <button type="submit" class="btn btn-gold btn-sm2 js-upload-btn" disabled>
                            <i class="fa-solid fa-upload"></i> {{ $doc ? 'Replace file' : 'Upload' }}
                        </button>
                        @if ($key === 'waiver')
                            <a href="{{ route('waiver') }}" class="btn btn-outline-green btn-sm2">
                                <i class="fa-solid fa-download"></i> Download waiver form
                            </a>
                        @endif
                    </div>
                @endif
            </form>
        <?php endforeach; ?>
    </div>

    <!-- ================= Help rail ================= -->
    <div class="col-xl-4">
        <div class="card-soft mb-4 fade-up d2">
            <div class="card-head"><h6 class="mb-0">What happens next</h6></div>
            <div class="card-body-p">
                <ul class="track-line">
                    <?php $next = [
                        ['fa-file-arrow-up', count($metas) > 1 ? 'Upload all three' : 'Upload your PDF',  'Replace the file whenever you need to.'],
                        ['fa-ticket',        'Ticket issued',     'Once SSC approves ' . (count($metas) > 1 ? 'all three' : 'it') . ', with your own ticket number and QR. Limited to ' . number_format(ev('capacity')) . ' tickets.'],
                        ['fa-user-shield',   'Staff review',      'SSC staff check each file. A returned file pauses your ticket until you fix it.'],
                        ['fa-qrcode',        'Scan at the gate',  'Show the QR on event night.'],
                    ];
                    foreach ($next as $i => [$icon, $t, $d]): ?>
                        <li class="track-step {{ $i === 0 ? 'current' : 'upcoming' }}" style="padding-bottom:{{ $i === 3 ? '0' : '1.2rem' }};">
                            <span class="track-dot" style="width:38px;height:38px;border-radius:12px;font-size:.82rem;"><i class="fa-solid {{ $icon }}"></i></span>
                            <div style="padding-top:.2rem;">
                                <h6 class="mb-0" style="font-size:.86rem;">{{ $t }}</h6>
                                <div class="cell-sub">{{ $d }}</div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card-soft fade-up d3">
            <div class="card-head"><h6 class="mb-0">Making a PDF</h6></div>
            <div class="card-body-p">
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-mobile-screen"></i></span>
                    <div><div class="info-value" style="font-size:.86rem;">From your phone</div>
                         <div class="cell-sub">Use “Scan document” in Google Drive, Microsoft Lens or the iPhone Notes app — each saves a PDF.</div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-image"></i></span>
                    <div><div class="info-value" style="font-size:.86rem;">Already a photo?</div>
                         <div class="cell-sub">Open it, choose Print, then “Save as PDF”.</div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><i class="fa-solid fa-weight-hanging"></i></span>
                    <div><div class="info-value" style="font-size:.86rem;">Too big?</div>
                         <div class="cell-sub">Scan at 150–200 dpi in black-and-white or grayscale to stay under {{ (int) (config('ereserve.doc_max_bytes') / 1024 / 1024) }} MB.</div></div>
                </div>
                <a href="{{ route('home') . '#guidelines' }}" class="btn btn-outline-green btn-block2 btn-sm2 mt-3">
                    <i class="fa-solid fa-book-open"></i> Read the full guidelines
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if (session('submitted_popup'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var email = {!! json_encode($user['email'] ?? '') !!};
    Swal.fire({
        icon: 'success',
        title: 'All documents submitted!',
        html:
            '<p style="margin:0 0 .85rem">Your waiver, COR and school ID are with SSC now. <strong>You can relax and wait for approval.</strong></p>' +
            '<div style="text-align:left;background:#F2F8FB;border:1px solid #D6E8F0;border-radius:14px;padding:.85rem 1rem;font-size:.9rem;line-height:1.6">' +
            '<div><i class="fa-solid fa-bell" style="width:1.4rem;color:#2A8DB5"></i> Check the <strong>bell</strong> at the top for updates</div>' +
            '<div><i class="fa-solid fa-route" style="width:1.4rem;color:#2A8DB5"></i> Or open the <strong>Track</strong> page any time</div>' +
            '<div><i class="fa-solid fa-envelope" style="width:1.4rem;color:#2A8DB5"></i> We will also email <strong>' +
            String(email).replace(/[<>&"]/g, '') + '</strong></div></div>' +
            {!! json_encode(collect(session('doc_warnings', []))->isEmpty() ? '' : ('<div style="margin-top:.85rem;text-align:left;background:#FFF6E3;border:1px solid #F4D79B;border-radius:12px;padding:.7rem .9rem;font-size:.85rem;color:#7A5410"><strong>Please double-check:</strong><br>' . collect(session('doc_warnings'))->map(fn ($w) => e($w))->implode('<br>') . '</div>')) !!} +
            '<p style="margin:.85rem 0 0;font-size:.85rem;color:#5b6b75">Your ticket is issued once SSC approves ' + {!! json_encode(count($metas) > 1 ? 'all three.' : 'it.') !!} + '</p>',
        confirmButtonText: 'Track my status',
        showCancelButton: true,
        cancelButtonText: 'Stay here',
        reverseButtons: true,
        allowOutsideClick: false
    }).then(function (r) { if (r.isConfirmed) { window.location.href = {!! json_encode(route('tracking')) !!}; } });
});
</script>
@endif
<script>
(function () {
    var MAX ={{ (int) config('ereserve.doc_max_bytes') }};

    function fmt(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }

    document.querySelectorAll('.js-doc-form').forEach(function (form) {
        var input = form.querySelector('.dropzone-input');
        if (!input) return;                                   // locked card

        var zone  = form.querySelector('.dropzone');
        var title = form.querySelector('.dz-title');
        var sub   = form.querySelector('.dz-sub');
        var btn   = form.querySelector('.js-upload-btn');
        var label = form.dataset.label;
        var idle  = { t: title.textContent, s: sub.textContent };

        function reset(msg) {
            input.value = '';
            btn.disabled = true;
            zone.classList.remove('has-file');
            title.textContent = idle.t;
            sub.textContent = idle.s;
            if (msg) { Swal.fire({ icon: 'warning', title: 'Check that file', text: msg, confirmButtonText: 'OK' }); }
        }

        function check() {
            var f = input.files && input.files[0];
            if (!f) { return reset(); }

            if (!/\.pdf$/i.test(f.name) || (f.type && f.type !== 'application/pdf')) {
                return reset('Your ' + label + ' must be a PDF. Photos and Word files need to be saved as PDF first.');
            }
            if (f.size > MAX) {
                return reset('That file is ' + fmt(f.size) + '. Please keep each PDF under ' + fmt(MAX) + '.');
            }

            zone.classList.add('has-file');
            title.textContent = f.name;
            sub.textContent = fmt(f.size) + ' · ready to upload';
            btn.disabled = false;
        }

        input.addEventListener('change', check);

        ['dragenter', 'dragover'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-drag'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-drag'); });
        });
        zone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                check();
            }
        });

        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading…';
        });
    });
})();
</script>
@endpush

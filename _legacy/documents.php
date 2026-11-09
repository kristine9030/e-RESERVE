<?php
/**
 * documents.php — Step 2: upload the three required PDFs.
 *
 * Signed waiver · Certificate of Registration (COR) · School ID.
 * Uploading the third file issues the student's ticket number.
 */
require_once __DIR__ . '/includes/config.php';
require_verified();

if (is_admin()) {
    redirect('admin/documents.php');
}

/* --- Save one document -------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();

    $type   = (string) ($_POST['doc_type'] ?? '');
    $result = submit_document($type, $_FILES['document'] ?? []);

    if (!$result['ok']) {
        set_flash('error', 'Could Not Upload', e($result['message']));
        redirect('documents.php#doc-' . (is_document_type($type) ? $type : 'waiver'));
    }

    if (in_array($result['ticket'], ['issued', 'restored'], true)) {
        set_flash('success', $result['ticket'] === 'issued' ? 'Your ticket is ready!' : 'Ticket restored', e($result['message']));
        redirect('ticket.php');
    }

    set_flash('success', 'Uploaded', e($result['message']));
    redirect('documents.php');
}

$user     = current_user();
$docs     = user_documents();
$progress = document_progress();
$ticket   = get_ticket();
$pct      = (int) round($progress['uploaded'] / $progress['total'] * 100);

/** "1.2 MB" / "340 KB". */
$size = static fn(int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';

$pageTitle = 'My Documents';
$crumb     = 'Student Portal';
$activeNav = 'documents';
require __DIR__ . '/layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-file-arrow-up"></i> Step 2 of 4</span>
            <h2>Upload your 3 documents</h2>
            <p>
                One PDF each — your signed waiver, your COR and your school ID.
                <strong class="text-white">The moment the third one is in, your ticket number is issued.</strong>
            </p>
        </div>
        <div class="col-lg-4">
            <div class="text-lg-end">
                <div class="tg-label mb-1">Uploaded</div>
                <div class="fw-900" style="font-size:2.6rem;line-height:1;color:var(--gold-400);letter-spacing:-2px;">
                    <?= $progress['uploaded'] ?><span style="font-size:1.4rem;color:rgba(255,255,255,.55);"> / <?= $progress['total'] ?></span>
                </div>
                <div class="prog-track mt-2" style="background:rgba(255,255,255,.14);"><div class="prog-fill gold" data-width="<?= $pct ?>" style="width:0;"></div></div>
            </div>
        </div>
    </div>
</div>

<?php if ($ticket && $ticket['status'] === 'valid'): ?>
    <div class="callout callout-success fade-up">
        <i class="fa-solid fa-circle-check"></i>
        <div class="flex-grow-1">
            <strong>You already have a ticket — <span class="mono"><?= e($ticket['ticket_no']) ?></span>.</strong>
            You can still replace a file below until staff approve it.
        </div>
        <a href="<?= url('ticket.php') ?>" class="btn btn-green btn-sm2"><i class="fa-solid fa-qrcode"></i> Open ticket</a>
    </div>
<?php elseif ($progress['blocked']): ?>
    <div class="callout callout-danger fade-up">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
            <strong>A document was returned.</strong>
            Your ticket is on hold. Read the note on the highlighted card, upload a corrected PDF and your ticket
            comes back with the same number.
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">

    <!-- ================= Upload cards ================= -->
    <div class="col-xl-8">
        <?php foreach (DOCUMENT_TYPES as $key => $meta):
            $doc     = $docs[$key] ?? null;
            $status  = $doc['status'] ?? 'missing';
            $locked  = $status === 'approved' || ($ticket['status'] ?? '') === 'used';
            $tone    = ['missing' => 'muted', 'submitted' => 'info', 'approved' => 'success', 'rejected' => 'danger'][$status];
        ?>
            <form method="post" enctype="multipart/form-data" id="doc-<?= $key ?>"
                  class="doc-card state-<?= $status ?> fade-up js-doc-form" data-label="<?= e($meta['label']) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="doc_type" value="<?= $key ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= DOC_MAX_BYTES ?>">

                <div class="doc-head">
                    <span class="doc-icon"><i class="fa-solid <?= $meta['icon'] ?>"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="doc-step">Document <?= array_search($key, array_keys(DOCUMENT_TYPES), true) + 1 ?> of 3</div>
                        <h5 class="mb-0"><?= e($meta['label']) ?></h5>
                    </div>
                    <?= status_badge($status) ?>
                </div>

                <p class="doc-hint"><?= e($meta['hint']) ?></p>

                <ul class="doc-rules">
                    <?php foreach ($meta['rules'] as $rule): ?>
                        <li><i class="fa-solid fa-check"></i> <?= e($rule) ?></li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($status === 'rejected' && !empty($doc['note'])): ?>
                    <div class="doc-note">
                        <i class="fa-solid fa-comment-dots"></i>
                        <div><strong>Reviewer’s note</strong><br><?= e($doc['note']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($doc): ?>
                    <div class="doc-file">
                        <i class="fa-solid fa-file-pdf"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="doc-file-name text-truncate"><?= e($doc['name']) ?></div>
                            <div class="cell-sub"><?= $size($doc['size']) ?> · uploaded <?= pretty_date($doc['uploaded_at']) ?></div>
                        </div>
                        <a href="<?= document_url($doc['id']) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm2">
                            <i class="fa-solid fa-eye"></i> View
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($locked): ?>
                    <div class="doc-locked"><i class="fa-solid fa-lock"></i>
                        <?= $status === 'approved' ? 'Approved by SSC staff — this file is locked.' : 'Your ticket has been used — files are locked.' ?>
                    </div>
                <?php else: ?>
                    <label class="dropzone" for="file-<?= $key ?>">
                        <input type="file" id="file-<?= $key ?>" name="document" accept="application/pdf,.pdf" class="dropzone-input" required>
                        <span class="dropzone-art"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                        <span class="dropzone-text">
                            <strong class="dz-title"><?= $doc ? 'Choose a replacement PDF' : 'Choose your PDF' ?></strong>
                            <span class="dz-sub">or drag and drop it here · PDF only · up to <?= (int) (DOC_MAX_BYTES / 1024 / 1024) ?> MB</span>
                        </span>
                    </label>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <button type="submit" class="btn btn-gold btn-sm2 js-upload-btn" disabled>
                            <i class="fa-solid fa-upload"></i> <?= $doc ? 'Replace file' : 'Upload' ?>
                        </button>
                        <?php if ($key === 'waiver'): ?>
                            <a href="<?= url('waiver.php') ?>" target="_blank" rel="noopener" class="btn btn-outline-green btn-sm2">
                                <i class="fa-solid fa-print"></i> Download waiver form
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        <?php endforeach; ?>
    </div>

    <!-- ================= Help rail ================= -->
    <div class="col-xl-4">
        <div class="card-soft mb-4 fade-up d2">
            <div class="card-head"><h6 class="mb-0">What happens next</h6></div>
            <div class="card-body-p">
                <ul class="track-line">
                    <?php
                    $next = [
                        ['fa-file-arrow-up', 'Upload all three',  'Any order. Replace a file whenever you need to.'],
                        ['fa-ticket',        'Ticket issued',     'Instantly, with your own ticket number and QR.'],
                        ['fa-user-shield',   'Staff review',      'SSC staff check each file. A returned file pauses your ticket until you fix it.'],
                        ['fa-qrcode',        'Scan at the gate',  'Show the QR on event night.'],
                    ];
                    foreach ($next as $i => [$icon, $t, $d]): ?>
                        <li class="track-step <?= $i === 0 ? 'current' : 'upcoming' ?>" style="padding-bottom:<?= $i === 3 ? '0' : '1.2rem' ?>;">
                            <span class="track-dot" style="width:38px;height:38px;border-radius:12px;font-size:.82rem;"><i class="fa-solid <?= $icon ?>"></i></span>
                            <div style="padding-top:.2rem;">
                                <h6 class="mb-0" style="font-size:.86rem;"><?= e($t) ?></h6>
                                <div class="cell-sub"><?= e($d) ?></div>
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
                         <div class="cell-sub">Scan at 150–200 dpi in black-and-white or grayscale to stay under <?= (int) (DOC_MAX_BYTES / 1024 / 1024) ?> MB.</div></div>
                </div>
                <a href="<?= url('index.php#guidelines') ?>" class="btn btn-outline-green btn-block2 btn-sm2 mt-3">
                    <i class="fa-solid fa-book-open"></i> Read the full guidelines
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/app_footer.php'; ?>

<script>
(function () {
    var MAX = <?= (int) DOC_MAX_BYTES ?>;

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

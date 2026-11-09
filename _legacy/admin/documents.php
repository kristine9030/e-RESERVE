<?php
/**
 * admin/documents.php — document review queue.
 *
 * Tickets are issued the moment a student uploads all three files, so review
 * here is the safety net: open each PDF, approve it, or return it with a note.
 * Returning a document voids that student's ticket until they replace the file.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

/* --- Review actions --------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();

    $documentId = (int) ($_POST['document_id'] ?? 0);
    $action     = (string) ($_POST['action'] ?? '');

    if ($action === 'approve') {
        $result = review_document($documentId, 'approved');
        $result['ok']
            ? set_flash('success', 'Document Approved', e($result['message']))
            : set_flash('error', 'Could Not Approve', e($result['message']));
    } elseif ($action === 'reject') {
        $result = review_document($documentId, 'rejected', (string) ($_POST['reason'] ?? ''));
        $result['ok']
            ? set_flash('success', 'Document Returned', e($result['message']))
            : set_flash('error', 'Could Not Return', e($result['message']));
    }

    redirect('admin/documents.php');
}

$queue    = pending_documents();
$reviewed = db_all(
    'SELECT d.*, u.name AS student_name, u.student_no, v.name AS reviewer
     FROM documents d
     JOIN users u      ON u.id = d.user_id
     LEFT JOIN users v ON v.id = d.reviewed_by
     WHERE d.status <> "submitted"
     ORDER BY d.reviewed_at DESC, d.id DESC
     LIMIT 80'
);

$stats = dashboard_stats();

$pageTitle = 'Review Documents';
$crumb     = 'Administration';
$activeNav = 'documents';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-user-shield"></i> Document Control</span>
            <h2>Review Documents</h2>
            <p>Open each PDF, check it against the student’s record, then approve it or return it with a note.
               Returning a document pauses that student’s ticket until they upload a corrected file.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="badge-soft badge-gold" style="font-size:.82rem;">
                <i class="fa-solid fa-inbox"></i> <?= count($queue) ?> waiting
            </span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-inbox',        'orange', 'For Review', count($queue)],
        ['fa-circle-check', 'green',  'Approved',   $stats['docs_approved']],
        ['fa-rotate-left',  'danger', 'Returned',   $stats['docs_rejected']],
        ['fa-ticket',       'gold',   'Tickets Issued', $stats['tickets']],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value" style="font-size:1.5rem;"><?= number_format((int) $val) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ================= Queue ================= -->
<div class="card-soft fade-up d3 mb-4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Waiting for review</h5>
            <span class="cell-sub">Oldest first</span>
        </div>
    </div>

    <div class="card-body-p">
        <?php if (!$queue): ?>

            <div class="text-center py-5">
                <span class="stat-icon green d-inline-grid mb-3" style="width:56px;height:56px;font-size:1.3rem;">
                    <i class="fa-solid fa-check"></i>
                </span>
                <h5 class="mb-1">Queue is clear</h5>
                <p class="text-soft mb-0" style="font-size:.88rem;">Every uploaded document has been reviewed.</p>
            </div>

        <?php else: ?>

            <?php foreach ($queue as $d):
                $meta    = DOCUMENT_TYPES[$d['doc_type']];
                $modalId = 'rej' . (int) $d['id'];
            ?>
                <div class="review-card">
                    <span class="review-icon"><i class="fa-solid <?= $meta['icon'] ?>"></i></span>

                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge-soft badge-gold"><?= e($meta['short']) ?></span>
                            <span class="cell-strong"><?= e($d['student_name']) ?></span>
                            <span class="cell-sub"><?= e($d['student_no']) ?> · <?= e($d['program']) ?></span>
                        </div>
                        <div class="cell-sub mt-1">
                            <i class="fa-solid fa-file-pdf me-1"></i><?= e($d['original_name']) ?>
                            · <?= number_format($d['size_bytes'] / 1024) ?> KB
                            · uploaded <?= pretty_date($d['uploaded_at']) ?>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= document_url((int) $d['id']) ?>" target="_blank" rel="noopener" class="btn btn-outline-green btn-sm2">
                            <i class="fa-solid fa-eye"></i> Open PDF
                        </a>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-green btn-sm2" type="submit"
                                    data-confirm-title="Approve this <?= e($meta['short']) ?>?"
                                    data-confirm-text="You are confirming it is a valid <?= e(strtolower($meta['label'])) ?> for <?= e($d['student_name']) ?>. The student can no longer replace it."
                                    data-confirm-icon="question"
                                    data-confirm-yes="Approve">
                                <i class="fa-solid fa-circle-check"></i> Approve
                            </button>
                        </form>
                        <button class="btn btn-ghost btn-sm2" type="button"
                                data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">
                            <i class="fa-solid fa-rotate-left"></i> Return
                        </button>
                    </div>
                </div>

                <!-- Return dialog -->
                <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="post" class="modal-content" style="border:0;border-radius:20px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_id" value="<?= (int) $d['id'] ?>">
                            <input type="hidden" name="action" value="reject">

                            <div class="modal-header border-0">
                                <h5 class="modal-title">Return this <?= e($meta['short']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body pt-0">
                                <p class="text-soft" style="font-size:.87rem;">
                                    <?= e($d['student_name']) ?>’s ticket is paused until they upload a corrected PDF.
                                    Tell them exactly what to fix.
                                </p>
                                <label class="form-label-sm" for="reason<?= (int) $d['id'] ?>">Note to the student *</label>
                                <textarea class="form-control" id="reason<?= (int) $d['id'] ?>" name="reason" rows="3" required minlength="5"
                                          placeholder="e.g. The waiver is missing your signature."></textarea>

                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <?php foreach ([
                                        'The file is blurry or unreadable. Please rescan it.',
                                        'The document is not signed.',
                                        'This is not for the current semester.',
                                        'Your name or SR-code is cropped out.',
                                    ] as $preset): ?>
                                        <button type="button" class="btn btn-ghost btn-sm2 js-preset"
                                                data-target="reason<?= (int) $d['id'] ?>"
                                                data-text="<?= e($preset) ?>"><?= e($preset) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-ghost btn-sm2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-gold btn-sm2">Return document</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>

<!-- ================= Reviewed history ================= -->
<div class="card-soft fade-up d4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Review history</h5>
            <span class="cell-sub">Every decision is written to the audit trail</span>
        </div>
        <a href="<?= url('reports/registrations.php') ?>" class="btn btn-ghost btn-sm2">Registration Report <i class="fa-solid fa-arrow-right"></i></a>
    </div>

    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="10" style="width:100%;">
            <thead>
                <tr>
                    <th>Reviewed</th><th>Student</th><th>Document</th>
                    <th>Decision</th><th>Reviewed by</th><th>Note</th><th class="no-sort">File</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviewed as $r): ?>
                    <tr>
                        <td class="cell-sub"><?= pretty_date($r['reviewed_at']) ?></td>
                        <td>
                            <div class="cell-strong"><?= e($r['student_name']) ?></div>
                            <div class="cell-sub"><?= e($r['student_no']) ?></div>
                        </td>
                        <td><?= e(DOCUMENT_TYPES[$r['doc_type']]['short']) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="cell-sub"><?= e($r['reviewer'] ?: '—') ?></td>
                        <td class="cell-sub"><?= e($r['review_note'] ?: '—') ?></td>
                        <td><a href="<?= document_url((int) $r['id']) ?>" target="_blank" rel="noopener" class="icon-btn" style="width:32px;height:32px;border-radius:9px;" title="Open PDF"><i class="fa-solid fa-file-pdf" style="font-size:.75rem;"></i></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

<script>
    /* Return-reason shortcuts. */
    document.querySelectorAll('.js-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = document.getElementById(btn.dataset.target);
            if (box) { box.value = btn.dataset.text; box.focus(); }
        });
    });
</script>

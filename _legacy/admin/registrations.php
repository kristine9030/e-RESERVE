<?php
/**
 * admin/registrations.php — every student, their three documents and their ticket.
 */
require_once __DIR__ . '/../includes/config.php';
require_admin();

$rows  = registrations();
$stats = dashboard_stats();

/** One small chip per document: icon + colour tell the status at a glance. */
$chip = static function (string $short, array $d): string {
    $status = $d['status'] ?: 'missing';
    $tone   = ['missing' => 'muted', 'submitted' => 'info', 'approved' => 'success', 'rejected' => 'danger'][$status];
    $label  = ['missing' => 'Not uploaded', 'submitted' => 'For review', 'approved' => 'Approved', 'rejected' => 'Returned'][$status];
    $inner  = '<span class="doc-chip chip-' . $tone . '" title="' . e($short . ' — ' . $label) . '">' . e($short) . '</span>';

    return $d['id'] ? '<a href="' . document_url($d['id']) . '" target="_blank" rel="noopener">' . $inner . '</a>' : $inner;
};

$pageTitle = 'Students';
$crumb     = 'Administration';
$activeNav = 'registrations';
require __DIR__ . '/../layouts/app_header.php';
?>

<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-users"></i> Records</span>
            <h2>Student Registrations</h2>
            <p>Every account for <?= EVENT_NAME ?>, with the status of the waiver, COR and school ID and the ticket issued.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <a class="btn btn-gold" href="<?= url('reports/export.php?report=registrations&format=sheet') ?>"><i class="fa-solid fa-file-excel"></i> Export</a>
            <button class="btn btn-outline-light2 ms-2" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['fa-users',          'deep',   'Students',          $stats['students']],
        ['fa-ticket',         'green',  'Tickets Issued',    $stats['tickets']],
        ['fa-hourglass-half', 'orange', 'Still Uploading',   $stats['incomplete']],
        ['fa-rotate-left',    'danger', 'Docs Returned',     $stats['docs_rejected']],
    ];
    foreach ($mini as $i => [$icon, $tone, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d<?= $i + 1 ?>">
                <div class="stat-icon <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                <div class="stat-label"><?= $label ?></div>
                <div class="stat-value" data-count-to="<?= $val ?>">0</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-soft fade-up d3">
    <div class="card-head">
        <div>
            <h5 class="mb-0">All Students</h5>
            <span class="cell-sub"><?= count($rows) ?> accounts</span>
        </div>
        <div class="d-flex gap-2 align-items-center no-print flex-wrap">
            <span class="doc-chip chip-success">Approved</span>
            <span class="doc-chip chip-info">For review</span>
            <span class="doc-chip chip-danger">Returned</span>
            <span class="doc-chip chip-muted">Missing</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table js-datatable" data-page-length="15" data-order-col="6" data-order-dir="desc" style="width:100%;">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Documents</th>
                    <th>Ticket No.</th>
                    <th>Ticket</th>
                    <th>Gate</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <div class="cell-strong"><?= e($r['name']) ?></div>
                            <div class="cell-sub"><?= e($r['student_no']) ?> · <?= e($r['email']) ?></div>
                        </td>
                        <td><?= e($r['program']) ?><div class="cell-sub"><?= e($r['year']) ?></div></td>
                        <td data-order="<?= $r['doc_count'] ?>">
                            <div class="d-flex gap-1 flex-wrap">
                                <?= $chip('Waiver', $r['docs']['waiver']) ?>
                                <?= $chip('COR', $r['docs']['cor']) ?>
                                <?= $chip('ID', $r['docs']['id']) ?>
                            </div>
                        </td>
                        <td><span class="mono"><?= e($r['ticket_no'] ?: '—') ?></span></td>
                        <td><?= $r['ticket_status'] ? status_badge($r['ticket_status']) : '<span class="cell-sub">—</span>' ?></td>
                        <td><?= $r['checked_in']
                            ? '<span class="badge-soft badge-info"><i class="fa-solid fa-user-check"></i> In</span>'
                            : '<span class="cell-sub">—</span>' ?></td>
                        <td class="cell-sub" data-order="<?= e($r['registered_at']) ?>"><?= pretty_date($r['registered_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/app_footer.php'; ?>

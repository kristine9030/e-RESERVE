<?php
/**
 * profile.php — Student profile.
 * Edit Profile is UI-only for now (opens a modal, no persistence).
 */
require_once __DIR__ . '/includes/config.php';
require_login();

$user   = current_user();
$progress = document_progress();
$docs     = user_documents();
$ticket = get_ticket();

$pageTitle = 'My Profile';
$crumb     = 'Student Portal';
$activeNav = 'profile';
require __DIR__ . '/layouts/app_header.php';
?>

<div class="row g-4">

    <!-- ================= Profile card ================= -->
    <div class="col-lg-5">
        <div class="card-soft overflow-hidden fade-up">
            <div class="profile-hero"></div>

            <div class="card-body-p text-center">
                <div class="profile-avatar mx-auto"><?= e($user['initials']) ?></div>

                <h4 class="mt-3 mb-1" style="font-size:1.25rem;"><?= e($user['name']) ?></h4>
                <p class="text-soft mb-2" style="font-size:.85rem;"><?= e($user['email']) ?></p>

                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                    <span class="badge-soft badge-success"><i class="fa-solid fa-circle-check"></i> Verified Student</span>
                    <span class="badge-soft badge-gold"><i class="fa-solid fa-graduation-cap"></i> <?= e($user['year']) ?></span>
                </div>

                <button class="btn btn-gold btn-block2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                </button>
                <button class="btn btn-ghost btn-block2 mt-2" data-soon="Change Password">
                    <i class="fa-solid fa-key"></i> Change Password
                </button>
            </div>
        </div>

        <div class="card-soft mt-4 fade-up d2">
            <div class="card-head"><h6 class="mb-0">My SIGLA 2026 Status</h6></div>
            <div class="card-body-p">
                <div class="receipt-row">
                    <span class="k">Documents</span>
                    <span class="v"><?= $progress['uploaded'] ?> of <?= $progress['total'] ?> uploaded</span>
                </div>
                <div class="receipt-row">
                    <span class="k">Ticket</span>
                    <span class="v"><?= $ticket ? '<span class="mono">' . e($ticket['ticket_no']) . '</span>' : '<span class="badge-soft badge-muted">Not issued</span>' ?></span>
                </div>
                <a href="<?= url('tracking.php') ?>" class="btn btn-outline-green btn-block2 btn-sm2 mt-3">
                    <i class="fa-solid fa-route"></i> Track My Progress
                </a>
            </div>
        </div>
    </div>

    <!-- ================= Details ================= -->
    <div class="col-lg-7">
        <div class="card-soft fade-up d1">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Student Information</h5>
                    <span class="cell-sub">Synced from the university registry</span>
                </div>
                <span class="badge-soft badge-info"><i class="fa-solid fa-lock"></i> Read-only</span>
            </div>

            <div class="card-body-p">
                <?php
                $fields = [
                    ['fa-user',            'Student Name',   $user['name']],
                    ['fa-id-card',         'SR-Code', $user['student_no']],
                    ['fa-envelope',        'GSuite Email',   $user['email']],
                    ['fa-graduation-cap',  'Program',        $user['program']],
                    ['fa-layer-group',     'Year Level',     $user['year']],
                    ['fa-building-columns','Campus',         $user['college']],
                    ['fa-phone',           'Contact Number', $user['contact']],
                ];
                foreach ($fields as [$icon, $label, $value]): ?>
                    <div class="info-row">
                        <span class="info-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="info-label"><?= e($label) ?></div>
                            <div class="info-value text-break"><?= e($value) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card-soft mt-4 fade-up d3">
            <div class="card-head"><h6 class="mb-0">Account Activity</h6></div>
            <div class="card-body-p">
                <?php
                $activity = [];
                if ($ticket) {
                    $activity[] = ['fa-ticket', 'Ticket number issued', $ticket['ticket_no'], $ticket['issued_at'], 'success'];
                }
                foreach ($docs as $d) {
                    $activity[] = ['fa-file-pdf', DOCUMENT_TYPES[$d['type']]['label'] . ' uploaded', $d['name'], $d['uploaded_at'], 'info'];
                }
                $activity[] = ['fa-right-to-bracket', 'Signed in to e-RESERVE', $user['email'], $_SESSION['logged_in_at'] ?? date('Y-m-d H:i:s'), 'muted'];

                foreach ($activity as [$icon, $title, $meta, $when, $tone]): ?>
                    <div class="info-row">
                        <span class="info-icon" style="<?= $tone === 'success' ? 'background:rgba(31,157,99,.12);color:var(--success);' : '' ?>">
                            <i class="fa-solid <?= $icon ?>"></i>
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="info-value" style="font-size:.88rem;"><?= e($title) ?></div>
                            <div class="cell-sub text-break"><?= e($meta) ?></div>
                        </div>
                        <span class="cell-sub text-nowrap"><?= pretty_date($when, 'M d · g:i A') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ================= Edit Profile modal (UI only) ================= -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Edit Profile</h5>
                    <span class="cell-sub">Registry fields are locked — only contact details are editable.</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editProfileForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-sm d-block">Student Name</label>
                            <input type="text" class="form-control" value="<?= e($user['name']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-sm d-block">SR-Code</label>
                            <input type="text" class="form-control" value="<?= e($user['student_no']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-sm d-block">Program</label>
                            <input type="text" class="form-control" value="<?= e($user['program']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-sm d-block">Year Level</label>
                            <select class="form-select" disabled>
                                <option><?= e($user['year']) ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-sm d-block">GSuite Email</label>
                            <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-sm d-block">Contact Number</label>
                            <input type="text" class="form-control" value="<?= e($user['contact']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label-sm d-block">Emergency Contact</label>
                            <input type="text" class="form-control" placeholder="Name and mobile number">
                        </div>
                        <div class="col-12">
                            <label class="form-label-sm d-block">Notes / Accessibility Needs</label>
                            <textarea class="form-control" rows="3" placeholder="Let the SSC know if you need assistance at the venue."></textarea>
                        </div>
                    </div>

                    <div class="alert mt-3 mb-0 d-flex align-items-start gap-2"
                         style="background:var(--gold-050);border:1px solid rgba(233,169,58,.3);border-radius:14px;color:#96690A;font-size:.82rem;">
                        <i class="fa-solid fa-circle-info mt-1"></i>
                        <span>Prototype mode — changes are not persisted. Wiring this form to
                              <code>UPDATE tbl_students</code> is the only step needed on MySQL integration.</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/app_footer.php'; ?>

<script>
    document.getElementById('editProfileForm').addEventListener('submit', function (e) {
        e.preventDefault();
        bootstrap.Modal.getInstance(document.getElementById('editProfileModal')).hide();
        window.eReserve.processing('Saving changes…', 1100).then(function () {
            Swal.fire({
                icon: 'success',
                title: 'Profile Updated',
                html: 'Your contact details were accepted by the interface.<br>' +
                      '<span style="font-size:.82rem;color:#7C8F88;">Persistence arrives with the MySQL integration.</span>',
                confirmButtonText: 'Done'
            });
        });
    });
</script>

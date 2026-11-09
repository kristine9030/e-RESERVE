<?php
/**
 * layouts/scripts.php — shared closing scripts + flash-message dispatcher.
 * Include this right before </body> on every page.
 */
$flash = take_flash();
?>
<!-- jQuery (required by DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5.3 bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- e-RESERVE behaviour -->
<script src="<?= url('assets/js/app.js') ?>"></script>
<script src="<?= url('assets/js/fx.js') ?>"></script>

<?php if ($flash): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: <?= json_encode($flash['type']) ?>,
            title: <?= json_encode($flash['title']) ?>,
            html: <?= json_encode($flash['text']) ?>,
            confirmButtonText: 'Continue'
        });
    });
</script>
<?php endif; ?>
</body>
</html>

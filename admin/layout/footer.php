    </div><!-- /.main-content -->
</div><!-- /.admin-wrapper -->

<!-- jQuery -->
<script src="<?= BASE_URL ?>/assets/js/jquery.min.js"></script>
<!-- Bootstrap 5 -->
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="<?= BASE_URL ?>/assets/js/datatables.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/datatables.bootstrap5.min.js"></script>
<!-- Select2 -->
<script src="<?= BASE_URL ?>/assets/js/select2.min.js"></script>
<!-- SweetAlert2 -->
<script src="<?= BASE_URL ?>/assets/js/sweetalert2.min.js"></script>
<!-- Chart.js -->
<script src="<?= BASE_URL ?>/assets/js/chart.min.js"></script>
<!-- App JS -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<script>
// Pass PHP data to JS
const APP = {
    baseUrl: '<?= BASE_URL ?>',
    userId: <?= Auth::id() ?? 'null' ?>,
    csrfToken: '<?= csrfToken() ?>',
    userName: '<?= e(Auth::user()['name']) ?>',
};
</script>

<?php if (isset($extraJs)) echo $extraJs; ?>

<script>
// Initialize DataTables globally
$(document).ready(function() {
    // Auto-init tables with .datatable class
    if ($.fn.DataTable) {
        $('.datatable').each(function() {
            if (!$.fn.DataTable.isDataTable(this)) {
                $(this).DataTable({
                    responsive: true,
                    language: {
                        url: '<?= BASE_URL ?>/assets/js/datatables-id.json',
                    },
                    pageLength: <?= DEFAULT_PER_PAGE ?>,
                    order: [],
                });
            }
        });
    }

    // Auto-init Select2
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
        });
    }

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert.alert-dismissible.auto-dismiss').alert('close');
    }, 5000);
});

// CSRF helper for AJAX
$.ajaxSetup({
    headers: { 'X-CSRF-Token': APP.csrfToken }
});

// Mark single notification read
function markRead(id) {
    $.post(APP.baseUrl + '/api/mark-read.php', { id: id, csrf_token: APP.csrfToken });
}

// Mark all notifications read
function markAllRead() {
    $.post(APP.baseUrl + '/api/mark-read.php', { all: 1, csrf_token: APP.csrfToken }, function() {
        $('#notifBadge').addClass('d-none').text('0');
        $('#notifList .notif-item').each(function() {
            $(this).removeClass('unread');
        });
    });
}

// SweetAlert2 confirm delete helper
function confirmDelete(url, name) {
    Swal.fire({
        title: 'Hapus Data?',
        html: 'Data <strong>' + name + '</strong> akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Ya, Hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}

// Toggle sidebar on mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('sidebar-open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}

// Close sidebar when overlay clicked
document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.remove('sidebar-open');
    this.classList.remove('show');
});
</script>

</body>
</html>

  </main><!-- /page-content -->
  <footer class="page-footer">
    <span><?= appName() ?> &copy; <?= date('Y') ?> — الإصدار <?= APP_VERSION ?></span>
  </footer>
</div><!-- /main-wrapper -->

<script>
// Live clock
function updateClock() {
  const now = new Date();
  const t = now.toLocaleTimeString('ar-EG', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
  const d = now.toLocaleDateString('ar-EG', {weekday:'long',year:'numeric',month:'long',day:'numeric'});
  const el = document.getElementById('live-clock');
  if (el) el.textContent = t;
  const de = document.getElementById('live-date');
  if (de) de.textContent = d;
}
updateClock();
setInterval(updateClock, 1000);

// Sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.body.classList.toggle('sidebar-collapsed');
});

// DataTables defaults
$.extend($.fn.dataTable.defaults, {
  language: {
    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json'
  },
  pageLength: 15,
  responsive: true,
});

// Global AJAX confirm delete
function confirmDelete(url, callback) {
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: 'لن تتمكن من استرجاع هذا البيان!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e74c3c',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'نعم، احذف!',
    cancelButtonText: 'إلغاء',
    reverseButtons: true,
  }).then(result => {
    if (result.isConfirmed) {
      $.post(url, {action:'delete'}, res => {
        if (res.success) {
          Swal.fire('تم الحذف!', res.message || '', 'success').then(() => {
            if (callback) callback(); else location.reload();
          });
        } else {
          Swal.fire('خطأ!', res.message || 'حدث خطأ', 'error');
        }
      }, 'json');
    }
  });
}

function toast(icon, title) {
  Swal.fire({
    toast: true, position: 'top-end', showConfirmButton: false,
    timer: 3000, timerProgressBar: true,
    icon: icon, title: title,
  });
}
</script>
</body>
</html>

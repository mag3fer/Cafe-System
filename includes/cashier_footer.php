  </main>
</div><!-- /main-wrapper -->

<script>
function updateClock() {
  const t = new Date().toLocaleTimeString('ar-EG',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  const el = document.getElementById('live-clock');
  if (el) el.textContent = t;
}
updateClock(); setInterval(updateClock, 1000);

document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.body.classList.toggle('sidebar-collapsed');
});

function toast(icon, title) {
  Swal.fire({ toast:true, position:'top-end', showConfirmButton:false,
    timer:3000, timerProgressBar:true, icon, title });
}
</script>
</body>
</html>

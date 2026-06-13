// Cafe Master — Global JavaScript
'use strict';

// ── jQuery AJAX defaults ───────────────────────────
$(function () {
  $.ajaxSetup({ cache: false });

  // Sidebar mobile toggle (click outside closes)
  $(document).on('click', function (e) {
    if ($(window).width() <= 991) {
      if (!$(e.target).closest('#sidebar, #sidebarToggle').length) {
        $('#sidebar').removeClass('open');
      }
    }
  });

  $('#sidebarToggle').on('click', function () {
    if ($(window).width() <= 991) {
      $('#sidebar').toggleClass('open');
    }
  });
});

// ── Format Currency ────────────────────────────────
function formatMoney(amount, currency) {
  currency = currency || 'ج.م';
  return parseFloat(amount || 0).toFixed(2) + ' ' + currency;
}

// ── Toast (SweetAlert2) ────────────────────────────
function toast(icon, title, timer) {
  if (typeof Swal === 'undefined') return;
  Swal.fire({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: timer || 3000,
    timerProgressBar: true,
    icon: icon,
    title: title,
  });
}

// ── Confirm Delete ─────────────────────────────────
function confirmDelete(url, callback) {
  if (typeof Swal === 'undefined') {
    if (!confirm('هل أنت متأكد من الحذف؟')) return;
    $.post(url, { action: 'delete' }, function (res) {
      if (res.success) { if (callback) callback(); else location.reload(); }
      else alert(res.message || 'حدث خطأ');
    }, 'json');
    return;
  }
  Swal.fire({
    title: 'هل أنت متأكد؟',
    text: 'لن تتمكن من استرجاع هذا البيان!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'نعم، احذف!',
    cancelButtonText: 'إلغاء',
    reverseButtons: true,
  }).then(function (result) {
    if (result.isConfirmed) {
      $.post(url, { action: 'delete' }, function (res) {
        if (res.success) {
          Swal.fire('تم الحذف!', res.message || '', 'success').then(function () {
            if (callback) callback();
            else location.reload();
          });
        } else {
          Swal.fire('خطأ!', res.message || 'حدث خطأ', 'error');
        }
      }, 'json');
    }
  });
}

// ── Print Helper ───────────────────────────────────
function printReceipt(url) {
  const w = window.open(url, '_blank', 'width=400,height=600,scrollbars=yes');
  w.focus();
  setTimeout(function () { w.print(); }, 800);
}

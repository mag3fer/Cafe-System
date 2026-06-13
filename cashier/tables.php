<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

runMigrations();
$shift = getActiveShift();
if (!$shift) {
    header('Location: ' . BASE_URL . '/cashier/?need_shift=1');
    exit;
}

$db = getDB();
$tables = $db->query("
    SELECT t.*, o.id as order_id, o.total as order_total, o.opened_at,
           TIMESTAMPDIFF(SECOND, o.opened_at, NOW()) as elapsed_sec,
           u.name as cashier_name
    FROM cafe_tables t
    LEFT JOIN orders o ON o.table_id=t.id AND o.status='open'
    LEFT JOIN users u ON u.id=o.user_id
    ORDER BY t.number
")->fetchAll();

$occupiedTables = array_filter($tables, function($t) { return $t['status'] === 'occupied'; });
$floorTotal     = array_sum(array_column(array_values($occupiedTables), 'order_total'));

$pageTitle = 'الطاولات';
require_once __DIR__ . '/../includes/cashier_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h5 class="fw-bold mb-0">اختر طاولة</h5>
    <small class="text-muted">اضغط على الطاولة المتاحة لفتحها، أو الطاولة المشغولة لإدارة طلبها</small>
  </div>
  <div class="d-flex gap-2">
    <span class="badge bg-success fs-6 px-3 py-2">
      <i class="fa fa-circle me-1"></i>
      <?= count(array_filter($tables, function($t) { return $t['status']==='available'; })) ?> متاحة
    </span>
    <span class="badge bg-danger fs-6 px-3 py-2">
      <i class="fa fa-circle me-1"></i>
      <?= count($occupiedTables) ?> مشغولة
    </span>
    <?php if ($floorTotal > 0): ?>
    <span class="badge fs-6 px-3 py-2" style="background:#7c3aed">
      <i class="fa fa-coins me-1"></i>
      إجمالي الأرضية: <?= money($floorTotal) ?>
    </span>
    <?php endif; ?>
    <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
      <i class="fa fa-arrows-rotate"></i>
    </button>
  </div>
</div>

<div class="table-grid">
  <?php foreach ($tables as $t):
    $isVip      = !empty($t['is_vip']);
    $cardClass  = $t['status'] . ($isVip ? ' vip' : '');
    $clickAct   = $t['status'] === 'available'
      ? 'openTable('.$t['id'].','.$t['number'].')'
      : 'location.href=\''.BASE_URL.'/cashier/order.php?table='.$t['id'].'\'';
  ?>
  <div class="table-card <?= $cardClass ?>" onclick="<?= $clickAct ?>">

    <!-- Header -->
    <div class="tc-header">
      <button class="vip-toggle <?= $isVip ? 'on' : '' ?>"
              onclick="toggleVip(event,<?= $t['id'] ?>)"
              title="<?= $isVip ? 'إلغاء VIP' : 'تعيين VIP' ?>">
        <i class="fa fa-crown"></i>
      </button>
      <div class="tc-num"><?= $t['number'] ?></div>
      <div class="tc-name"><?= e($t['name']) ?></div>
      <div class="tc-badges">
        <span class="tc-badge <?= $t['status'] ?>">
          <?php if ($t['status'] === 'available'): ?>
            <i class="fa fa-circle-check"></i> متاحة
          <?php else: ?>
            <i class="fa fa-fire"></i> مشغولة
          <?php endif; ?>
        </span>
        <?php if ($isVip): ?>
        <span class="tc-badge vip-tag"><i class="fa fa-crown"></i> VIP</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Body -->
    <div class="tc-body">
      <?php if ($t['status'] === 'occupied'): ?>
        <div>
          <div class="tc-amount"><?= number_format((float)$t['order_total'], 2) ?></div>
          <div class="tc-amount-cur"><?= CURRENCY ?></div>
        </div>
        <div class="tc-timer" data-elapsed="<?= max(0, (int)$t['elapsed_sec']) ?>">
          <i class="fa fa-clock me-1"></i><span class="timer-val">...</span>
        </div>
        <?php if ($t['cashier_name']): ?>
        <div class="tc-cashier"><i class="fa fa-user me-1"></i><?= e($t['cashier_name']) ?></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="tc-capacity"><i class="fa fa-users me-1"></i><?= $t['capacity'] ?> أشخاص</div>
        <div class="tc-open-hint"><i class="fa fa-plus-circle"></i> اضغط لفتح</div>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>
</div>

<script>
var _pageLoadAt = Date.now();

function fmtElapsed(sec) {
  if (sec < 60)  return 'أقل من دقيقة';
  var m = Math.floor(sec / 60);
  var h = Math.floor(m / 60);
  var mm = m % 60;
  if (h === 0)   return m + ' دقيقة';
  if (mm === 0)  return h + ' ساعة';
  return h + ' س ' + mm + ' د';
}
function updateTimers() {
  var addedSec = Math.floor((Date.now() - _pageLoadAt) / 1000);
  document.querySelectorAll('.tc-timer[data-elapsed]').forEach(function(el) {
    var sec = parseInt(el.dataset.elapsed, 10) + addedSec;
    el.querySelector('.timer-val').textContent = fmtElapsed(sec);
  });
}
updateTimers();
setInterval(updateTimers, 30000);

function toggleVip(e, tableId) {
  e.stopPropagation();
  const btn = e.currentTarget;
  btn.style.opacity = '.5';
  $.post('<?= BASE_URL ?>/cashier/api/tables.php',
    { action: 'toggle_vip', table_id: tableId },
    function(res) { if (res.success) location.reload(); },
    'json'
  );
}

function openTable(tableId, tableNum) {
  Swal.fire({
    title: `فتح طاولة ${tableNum}`,
    text: 'هل تريد فتح هذه الطاولة وبدء طلب جديد؟',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#f59e0b',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="fa fa-play me-1"></i> افتح الطاولة',
    cancelButtonText: 'إلغاء',
    reverseButtons: true,
  }).then(result => {
    if (result.isConfirmed) {
      $.post('<?= BASE_URL ?>/cashier/api/tables.php', {
        action: 'open', table_id: tableId
      }, res => {
        if (res.success) {
          window.location.href = '<?= BASE_URL ?>/cashier/order.php?table=' + tableId;
        } else {
          Swal.fire('خطأ', res.message, 'error');
        }
      }, 'json');
    }
  });
}
</script>

<?php require_once __DIR__ . '/../includes/cashier_footer.php'; ?>

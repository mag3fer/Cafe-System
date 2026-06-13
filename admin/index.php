<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// Stats
$tableStats = getTableStats();
$todayStats = getTodayStats();
$activeShifts = getActiveShiftsCount();
$lowStock = getLowStockCount();

// Monthly sales (last 7 days)
$dailySales = $db->query("
    SELECT DATE(closed_at) as day, SUM(final_total) as total, COUNT(*) as orders
    FROM orders WHERE status='closed' AND closed_at >= DATE_SUB(CURDATE(),INTERVAL 6 DAY)
    GROUP BY DATE(closed_at) ORDER BY day ASC
")->fetchAll();

// Top items today
$topItems = $db->query("
    SELECT oi.item_name, SUM(oi.quantity) as qty, SUM(oi.subtotal) as revenue
    FROM order_items oi
    JOIN orders o ON o.id=oi.order_id
    WHERE o.status='closed' AND DATE(o.closed_at)=CURDATE()
    GROUP BY oi.item_name ORDER BY qty DESC LIMIT 5
")->fetchAll();

// All tables with current order info
$tables = $db->query("
    SELECT t.*,
           o.id as order_id, o.total as order_total, o.opened_at,
           TIMESTAMPDIFF(SECOND, o.opened_at, NOW()) as elapsed_sec,
           u.name as cashier_name
    FROM cafe_tables t
    LEFT JOIN orders o ON o.table_id=t.id AND o.status='open'
    LEFT JOIN users u ON u.id=o.user_id
    ORDER BY t.number ASC
")->fetchAll();

$pageTitle = 'لوحة التحكم';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mb-4">
  <h4 class="fw-bold mb-1">مرحباً، <?= e($_SESSION['user_name']) ?> 👋</h4>
  <p class="text-muted mb-0" id="live-date"></p>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-xl-2 col-sm-4 col-6">
    <div class="stat-card">
      <div class="stat-icon amber"><i class="fa fa-table-cells-large"></i></div>
      <div>
        <div class="stat-value"><?= $tableStats['total'] ?></div>
        <div class="stat-label">إجمالي الطاولات</div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-sm-4 col-6">
    <div class="stat-card">
      <div class="stat-icon green"><i class="fa fa-circle-check"></i></div>
      <div>
        <div class="stat-value text-success"><?= $tableStats['available'] ?></div>
        <div class="stat-label">طاولات متاحة</div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-sm-4 col-6">
    <div class="stat-card">
      <div class="stat-icon red"><i class="fa fa-users"></i></div>
      <div>
        <div class="stat-value text-danger"><?= $tableStats['occupied'] ?></div>
        <div class="stat-label">طاولات مشغولة</div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-sm-4 col-6">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fa fa-receipt"></i></div>
      <div>
        <div class="stat-value"><?= $todayStats['orders'] ?></div>
        <div class="stat-label">أوردر اليوم</div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-sm-4 col-6">
    <div class="stat-card">
      <div class="stat-icon teal"><i class="fa fa-coins"></i></div>
      <div>
        <div class="stat-value" style="font-size:18px"><?= money($todayStats['sales']) ?></div>
        <div class="stat-label">مبيعات اليوم</div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-sm-4 col-6">
    <div class="stat-card">
      <div class="stat-icon <?= $lowStock > 0 ? 'red' : 'purple' ?>"><i class="fa fa-boxes-stacked"></i></div>
      <div>
        <div class="stat-value <?= $lowStock > 0 ? 'text-danger' : '' ?>"><?= $lowStock ?></div>
        <div class="stat-label">مواد منخفضة</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Table Map -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa fa-map-location-dot"></i> خريطة الطاولات</span>
        <div>
          <span class="badge bg-success me-1">متاحة</span>
          <span class="badge bg-danger">مشغولة</span>
        </div>
      </div>
      <div class="card-body">
        <div class="table-grid" id="tableMap">
          <?php foreach ($tables as $t):
            $isVip     = !empty($t['is_vip']);
            $cardClass = $t['status'] . ($isVip ? ' vip' : '');
            $clickAttr = $t['status'] === 'occupied' ? 'onclick="showOrderDetails('.$t['order_id'].')" style="cursor:pointer"' : '';
          ?>
          <div class="table-card <?= $cardClass ?>" <?= $clickAttr ?>>
            <div class="tc-header">
              <div class="tc-num"><?= $t['number'] ?></div>
              <div class="tc-name"><?= e($t['name']) ?></div>
              <div class="tc-badges">
                <span class="tc-badge <?= $t['status'] ?>">
                  <?= $t['status'] === 'available' ? '<i class="fa fa-circle-check"></i> متاحة' : '<i class="fa fa-fire"></i> مشغولة' ?>
                </span>
                <?php if ($isVip): ?>
                <span class="tc-badge vip-tag"><i class="fa fa-crown"></i> VIP</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="tc-body">
              <?php if ($t['status'] === 'occupied'): ?>
                <div>
                  <div class="tc-amount"><?= number_format((float)$t['order_total'], 2) ?></div>
                  <div class="tc-amount-cur"><?= CURRENCY ?></div>
                </div>
                <div class="tc-timer"
                     data-elapsed="<?= max(0,(int)$t['elapsed_sec']) ?>">
                  <i class="fa fa-clock me-1"></i><span class="timer-val">...</span>
                </div>
                <?php if ($t['cashier_name']): ?>
                <div class="tc-cashier"><i class="fa fa-user me-1"></i><?= e($t['cashier_name']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <div class="tc-capacity"><i class="fa fa-users me-1"></i><?= $t['capacity'] ?> أشخاص</div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer text-center">
        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
          <i class="fa fa-arrows-rotate me-1"></i> تحديث
        </button>
        <small class="text-muted ms-2">يتجدد كل 30 ثانية</small>
      </div>
    </div>
  </div>

  <!-- Right Column -->
  <div class="col-lg-4">
    <!-- Top Items -->
    <div class="card mb-4">
      <div class="card-header"><i class="fa fa-fire"></i> الأكثر طلباً اليوم</div>
      <div class="card-body p-0">
        <?php if (empty($topItems)): ?>
        <div class="text-center text-muted py-4">لا توجد طلبات اليوم</div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($topItems as $i => $item): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <span class="badge bg-warning text-dark me-2"><?= $i+1 ?></span>
              <span class="fw-600"><?= e($item['item_name']) ?></span>
            </div>
            <div class="text-end">
              <div class="fw-bold"><?= $item['qty'] ?> قطعة</div>
              <small class="text-success"><?= money((float)$item['revenue']) ?></small>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Active Shifts -->
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="fa fa-user-clock"></i> الشيفتات النشطة</span>
        <span class="badge bg-primary"><?= $activeShifts ?></span>
      </div>
      <div class="card-body p-0">
        <?php
        $activeShiftsList = fetchAll("
            SELECT s.*, u.name as user_name
            FROM shifts s JOIN users u ON u.id=s.user_id
            WHERE s.check_out IS NULL ORDER BY s.check_in DESC
        ");
        ?>
        <?php if (empty($activeShiftsList)): ?>
        <div class="text-center text-muted py-4">لا توجد شيفتات نشطة</div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($activeShiftsList as $s): ?>
          <div class="list-group-item">
            <div class="fw-bold"><?= e($s['user_name']) ?></div>
            <small class="text-muted">
              منذ <?= date('h:i A', strtotime($s['check_in'])) ?>
              — مبيعات: <?= money((float)$s['total_sales']) ?>
            </small>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Sales Chart -->
<div class="row mt-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="fa fa-chart-line"></i> مبيعات آخر 7 أيام</div>
      <div class="card-body">
        <canvas id="salesChart" height="80"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-receipt me-2"></i>تفاصيل الطلب</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="orderModalBody">جاري التحميل...</div>
    </div>
  </div>
</div>

<script>
// Sales Chart
const salesData = <?= json_encode(array_values($dailySales)) ?>;
const labels = salesData.map(d => {
  const dt = new Date(d.day);
  return dt.toLocaleDateString('ar-EG', {weekday:'short', month:'short', day:'numeric'});
});
new Chart(document.getElementById('salesChart'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'المبيعات (ج.م)',
      data: salesData.map(d => parseFloat(d.total || 0)),
      backgroundColor: 'rgba(245,158,11,.7)',
      borderColor: '#f59e0b',
      borderWidth: 2,
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } },
      x: { grid: { display: false } }
    }
  }
});

// Live timers
var _dashPageLoad = Date.now();
function fmtElapsed(sec) {
  if (sec < 60)  return 'أقل من دقيقة';
  var m = Math.floor(sec / 60), h = Math.floor(m / 60), mm = m % 60;
  if (h === 0)  return m + ' دقيقة';
  if (mm === 0) return h + ' ساعة';
  return h + ' س ' + mm + ' د';
}
function updateDashTimers() {
  var add = Math.floor((Date.now() - _dashPageLoad) / 1000);
  document.querySelectorAll('#tableMap .tc-timer[data-elapsed]').forEach(function(el) {
    el.querySelector('.timer-val').textContent = fmtElapsed(+el.dataset.elapsed + add);
  });
}
updateDashTimers();
setInterval(updateDashTimers, 30000);

// Auto-refresh table map every 30s
setInterval(() => {
  fetch('<?= BASE_URL ?>/admin/api/tables.php?action=map')
    .then(r => r.json())
    .then(data => {
      if (data.html) {
        document.getElementById('tableMap').innerHTML = data.html;
        _dashPageLoad = Date.now();
        updateDashTimers();
      }
    }).catch(() => {});
}, 30000);

function showOrderDetails(orderId) {
  if (!orderId) return;
  const modal = new bootstrap.Modal(document.getElementById('orderModal'));
  document.getElementById('orderModalBody').innerHTML = '<div class="text-center py-3"><i class="fa fa-spinner fa-spin fa-2x"></i></div>';
  modal.show();
  fetch(`<?= BASE_URL ?>/admin/api/tables.php?action=order_detail&id=${orderId}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('orderModalBody').innerHTML = data.html || 'لا توجد تفاصيل';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

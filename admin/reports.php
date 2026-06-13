<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// Filter params
$period = $_GET['period'] ?? 'today';
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');

switch ($period) {
    case 'today':   $from = $to = date('Y-m-d'); break;
    case 'week':    $from = date('Y-m-d', strtotime('-6 days')); $to = date('Y-m-d'); break;
    case 'month':   $from = date('Y-m-01'); $to = date('Y-m-d'); break;
    case 'custom':  break;
}

// Summary
$summary = $db->prepare("
    SELECT COUNT(*) as orders, COALESCE(SUM(final_total),0) as revenue,
           COALESCE(SUM(discount),0) as discounts,
           COALESCE(AVG(final_total),0) as avg_order
    FROM orders WHERE status='closed' AND DATE(closed_at) BETWEEN ? AND ?
");
$summary->execute([$from, $to]);
$sum = $summary->fetch();

// Daily breakdown
$daily = $db->prepare("
    SELECT DATE(closed_at) as day, COUNT(*) as orders, SUM(final_total) as revenue
    FROM orders WHERE status='closed' AND DATE(closed_at) BETWEEN ? AND ?
    GROUP BY DATE(closed_at) ORDER BY day
");
$daily->execute([$from, $to]);
$dailyData = $daily->fetchAll();

// Category breakdown
$catRev = $db->prepare("
    SELECT c.name as cat, SUM(oi.subtotal) as revenue, SUM(oi.quantity) as qty
    FROM order_items oi
    JOIN orders o ON o.id=oi.order_id
    JOIN items it ON it.id=oi.item_id
    JOIN categories c ON c.id=it.category_id
    WHERE o.status='closed' AND DATE(o.closed_at) BETWEEN ? AND ?
    GROUP BY c.name ORDER BY revenue DESC
");
$catRev->execute([$from, $to]);
$catData = $catRev->fetchAll();

// Top items
$topItems = $db->prepare("
    SELECT oi.item_name, SUM(oi.quantity) as qty, SUM(oi.subtotal) as revenue
    FROM order_items oi
    JOIN orders o ON o.id=oi.order_id
    WHERE o.status='closed' AND DATE(o.closed_at) BETWEEN ? AND ?
    GROUP BY oi.item_name ORDER BY revenue DESC LIMIT 10
");
$topItems->execute([$from, $to]);
$topData = $topItems->fetchAll();

// Employee performance
$empPerf = $db->prepare("
    SELECT u.name, COUNT(o.id) as orders, COALESCE(SUM(o.final_total),0) as revenue
    FROM users u
    LEFT JOIN orders o ON o.user_id=u.id AND o.status='closed' AND DATE(o.closed_at) BETWEEN ? AND ?
    WHERE u.role!='admin'
    GROUP BY u.id ORDER BY revenue DESC
");
$empPerf->execute([$from, $to]);
$empData = $empPerf->fetchAll();

// Orders list
$ordersList = $db->prepare("
    SELECT o.*, t.number as table_num, u.name as cashier_name
    FROM orders o
    JOIN cafe_tables t ON t.id=o.table_id
    JOIN users u ON u.id=o.user_id
    WHERE o.status='closed' AND DATE(o.closed_at) BETWEEN ? AND ?
    ORDER BY o.closed_at DESC LIMIT 100
");
$ordersList->execute([$from, $to]);
$orders = $ordersList->fetchAll();

// Shifts in date range with stats
$shiftsReport = $db->prepare("
    SELECT s.id, s.check_in, s.check_out,
           u.name as cashier_name,
           COUNT(o.id)                              as orders_count,
           COALESCE(SUM(o.final_total), 0)          as total_revenue,
           COALESCE(SUM(o.discount), 0)             as total_discount,
           COALESCE(AVG(o.final_total), 0)          as avg_order,
           TIMESTAMPDIFF(MINUTE, s.check_in,
               COALESCE(s.check_out, NOW()))        as duration_min
    FROM shifts s
    JOIN users u ON u.id = s.user_id
    LEFT JOIN orders o ON o.shift_id = s.id AND o.status = 'closed'
    WHERE DATE(s.check_in) BETWEEN ? AND ?
      AND u.role != 'admin'
    GROUP BY s.id, s.check_in, s.check_out, u.name
    ORDER BY s.check_in DESC
");
$shiftsReport->execute([$from, $to]);
$shiftsData = $shiftsReport->fetchAll();

// Cashier comparison in this period
$cashierComp = $db->prepare("
    SELECT u.name as cashier_name,
           COUNT(DISTINCT s.id)           as shifts_count,
           COUNT(o.id)                    as orders_count,
           COALESCE(SUM(o.final_total),0) as total_revenue,
           COALESCE(AVG(o.final_total),0) as avg_order
    FROM users u
    JOIN shifts s ON s.user_id = u.id
    LEFT JOIN orders o ON o.shift_id = s.id AND o.status = 'closed'
    WHERE DATE(s.check_in) BETWEEN ? AND ?
      AND u.role != 'admin'
    GROUP BY u.id, u.name
    ORDER BY total_revenue DESC
");
$cashierComp->execute([$from, $to]);
$cashierData = $cashierComp->fetchAll();

$pageTitle = 'التقارير';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Period Filter -->
<div class="card mb-4">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">الفترة</label>
        <select name="period" class="form-select" id="periodSel" onchange="toggleCustom(this.value)">
          <option value="today"  <?= $period==='today'  ? 'selected':'' ?>>اليوم</option>
          <option value="week"   <?= $period==='week'   ? 'selected':'' ?>>آخر 7 أيام</option>
          <option value="month"  <?= $period==='month'  ? 'selected':'' ?>>هذا الشهر</option>
          <option value="custom" <?= $period==='custom' ? 'selected':'' ?>>مخصص</option>
        </select>
      </div>
      <div id="customDates" class="col-md-5 <?= $period!=='custom' ? 'd-none':'' ?>">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">من</label>
            <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
          </div>
          <div class="col-6">
            <label class="form-label">إلى</label>
            <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-accent w-100"><i class="fa fa-search me-1"></i>عرض</button>
      </div>
      <div class="col-md-2">
        <button type="button" class="btn btn-outline-secondary w-100" onclick="window.print()">
          <i class="fa fa-print me-1"></i>طباعة
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3 col-6">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fa fa-receipt"></i></div>
      <div><div class="stat-value"><?= $sum['orders'] ?></div><div class="stat-label">إجمالي الطلبات</div></div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="stat-card">
      <div class="stat-icon green"><i class="fa fa-coins"></i></div>
      <div><div class="stat-value" style="font-size:18px"><?= money((float)$sum['revenue']) ?></div><div class="stat-label">إجمالي الإيرادات</div></div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="stat-card">
      <div class="stat-icon amber"><i class="fa fa-chart-bar"></i></div>
      <div><div class="stat-value" style="font-size:18px"><?= money((float)$sum['avg_order']) ?></div><div class="stat-label">متوسط الطلب</div></div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="stat-card">
      <div class="stat-icon red"><i class="fa fa-percent"></i></div>
      <div><div class="stat-value" style="font-size:18px"><?= money((float)$sum['discounts']) ?></div><div class="stat-label">إجمالي الخصومات</div></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Daily Chart -->
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><i class="fa fa-chart-line"></i> المبيعات اليومية</div>
      <div class="card-body"><canvas id="dailyChart" height="120"></canvas></div>
    </div>
  </div>
  <!-- Category Pie -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="fa fa-chart-pie"></i> توزيع الفئات</div>
      <div class="card-body"><canvas id="catChart"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Top Items -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="fa fa-fire"></i> أكثر الأصناف مبيعاً</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>#</th><th>الصنف</th><th>الكمية</th><th>الإيراد</th></tr></thead>
          <tbody>
            <?php foreach ($topData as $i => $item): ?>
            <tr>
              <td><span class="badge bg-warning text-dark"><?= $i+1 ?></span></td>
              <td><?= e($item['item_name']) ?></td>
              <td><?= $item['qty'] ?></td>
              <td class="fw-bold text-success"><?= money((float)$item['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topData)): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">لا توجد بيانات</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <!-- Employee Performance -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="fa fa-user-star"></i> أداء الموظفين</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>الموظف</th><th>الطلبات</th><th>المبيعات</th></tr></thead>
          <tbody>
            <?php foreach ($empData as $emp): ?>
            <tr>
              <td class="fw-bold"><?= e($emp['name']) ?></td>
              <td><?= $emp['orders'] ?></td>
              <td class="text-success fw-bold"><?= money((float)$emp['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Shifts Report ═══ -->
<?php if (!empty($shiftsData)): ?>
<div class="row g-4 mb-4">

  <!-- Shifts list -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa fa-user-clock me-2 text-warning"></i>تقرير الشيفتات</span>
        <span class="badge bg-secondary"><?= count($shiftsData) ?> شيفت</span>
      </div>
      <div class="card-body p-0">
        <?php foreach ($shiftsData as $idx => $sh):
          $dur_h  = floor($sh['duration_min'] / 60);
          $dur_m  = $sh['duration_min'] % 60;
          $active = !$sh['check_out'];
        ?>
        <!-- Shift row header -->
        <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom
                    <?= $active ? 'bg-success bg-opacity-10' : '' ?>"
             style="cursor:pointer" onclick="toggleShift(<?= $sh['id'] ?>)">

          <div class="su-avatar cashier-avatar flex-shrink-0" style="width:38px;height:38px;font-size:14px">
            <?= mb_substr($sh['cashier_name'], 0, 1) ?>
          </div>

          <div class="flex-grow-1">
            <div class="fw-bold"><?= e($sh['cashier_name']) ?>
              <?php if ($active): ?>
              <span class="badge bg-success ms-1" style="font-size:10px">نشط الآن</span>
              <?php endif; ?>
            </div>
            <div class="text-muted" style="font-size:11px">
              <i class="fa fa-clock me-1"></i>
              <?= date('h:i A', strtotime($sh['check_in'])) ?>
              <?php if ($sh['check_out']): ?>
              → <?= date('h:i A', strtotime($sh['check_out'])) ?>
              &nbsp;|&nbsp; <?= $dur_h ?>س <?= $dur_m ?>د
              <?php else: ?>
              → <span class="text-success">مستمر</span>
              <?php endif; ?>
              &nbsp;|&nbsp;
              <?= date('Y/m/d', strtotime($sh['check_in'])) ?>
            </div>
          </div>

          <div class="text-center px-3">
            <div class="fw-bold"><?= $sh['orders_count'] ?></div>
            <div style="font-size:10px;color:#64748b">طلب</div>
          </div>

          <div class="text-center px-3">
            <div class="fw-bold text-success"><?= money((float)$sh['total_revenue']) ?></div>
            <div style="font-size:10px;color:#64748b">إجمالي</div>
          </div>

          <div class="text-center px-3">
            <div class="fw-bold text-info" style="font-size:13px"><?= money((float)$sh['avg_order']) ?></div>
            <div style="font-size:10px;color:#64748b">متوسط</div>
          </div>

          <i class="fa fa-chevron-down text-muted" id="arrow-<?= $sh['id'] ?>"></i>
        </div>

        <!-- Shift orders (collapsed) -->
        <div id="shift-<?= $sh['id'] ?>" style="display:none;background:#f8fafc">
          <?php
          $shiftOrders = fetchAll("
              SELECT o.*, t.number as tnum
              FROM orders o
              JOIN cafe_tables t ON t.id=o.table_id
              WHERE o.shift_id=? AND o.status='closed'
              ORDER BY o.closed_at DESC
          ", [$sh['id']]);
          ?>
          <?php if (empty($shiftOrders)): ?>
          <div class="text-center text-muted py-3">لا توجد طلبات مغلقة في هذا الشيفت</div>
          <?php else: ?>
          <table class="table table-sm mb-0">
            <thead>
              <tr style="background:#f1f5f9">
                <th class="ps-4">#</th><th>الطاولة</th><th>المجموع</th>
                <th>الخصم</th><th>الصافي</th><th>الدفع</th><th>الوقت</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($shiftOrders as $so):
                $soItems = fetchAll("SELECT * FROM order_items WHERE order_id=?", [$so['id']]);
              ?>
              <tr>
                <td class="ps-4 text-muted"><?= generateOrderNumber($so['id']) ?></td>
                <td>طاولة <?= $so['tnum'] ?></td>
                <td><?= money((float)$so['total']) ?></td>
                <td><?= $so['discount'] > 0 ? '<span class="text-danger">- '.money((float)$so['discount']).'</span>' : '—' ?></td>
                <td class="fw-bold text-success"><?= money((float)$so['final_total']) ?></td>
                <td>
                  <?php if ($so['payment_method'] === 'split'): ?>
                  <span class="badge text-white" style="background:#7c3aed">مقسم</span>
                  <small class="d-block text-muted" style="font-size:10px;line-height:1.6">
                    <?php
                    if (($so['cash_amount']??0)>0)    echo 'ك '.money((float)$so['cash_amount']).'<br>';
                    if (($so['card_amount']??0)>0)     echo 'ب '.money((float)$so['card_amount']).'<br>';
                    if (($so['instapay_amount']??0)>0) echo 'ا '.money((float)$so['instapay_amount']);
                    ?>
                  </small>
                  <?php elseif ($so['payment_method'] === 'instapay'): ?>
                  <span class="badge text-white" style="background:#e91e8c">انستاباي</span>
                  <?php else: ?>
                  <span class="badge <?= $so['payment_method']==='cash'?'bg-success':'bg-primary' ?>">
                    <?= ['cash'=>'نقدي','card'=>'بطاقة','other'=>'أخرى'][$so['payment_method']] ?? '' ?>
                  </span>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px">
                  <?php if (!empty($so['opened_at'])): ?>
                  <span class="text-secondary"><?= date('h:i A', strtotime($so['opened_at'])) ?></span>
                  <span class="mx-1">→</span>
                  <?php endif; ?>
                  <span class="fw-bold"><?= date('h:i A', strtotime($so['closed_at'])) ?></span>
                </td>
                <td class="text-nowrap">
                  <button onclick="toggleItems(<?= $so['id'] ?>)"
                          class="btn btn-xs btn-outline-info me-1" title="تفاصيل الطلب">
                    <i class="fa fa-eye" id="eye-<?= $so['id'] ?>"></i>
                  </button>
                  <a href="<?= BASE_URL ?>/cashier/receipt.php?order=<?= $so['id'] ?>"
                     class="btn btn-xs btn-outline-secondary" target="_blank" title="إعادة طباعة">
                    <i class="fa fa-print"></i>
                  </a>
                </td>
              </tr>
              <tr id="items-<?= $so['id'] ?>" style="display:none;background:#f0f9ff">
                <td colspan="8" class="p-0">
                  <table class="table table-sm mb-0" style="font-size:12px">
                    <thead style="background:#dbeafe">
                      <tr>
                        <th class="ps-4">الصنف</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($soItems as $si): ?>
                      <tr>
                        <td class="ps-4"><?= e($si['item_name']) ?></td>
                        <td>× <?= $si['quantity'] ?></td>
                        <td><?= money((float)$si['price']) ?></td>
                        <td class="fw-bold"><?= money((float)$si['subtotal']) ?></td>
                      </tr>
                      <?php endforeach; ?>
                      <?php if (empty($soItems)): ?>
                      <tr><td colspan="4" class="text-center text-muted py-2">لا توجد أصناف</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </td>
              </tr>
              <?php endforeach; ?>
              <tr style="background:#f0fdf4">
                <td colspan="4" class="ps-4 fw-bold text-end">إجمالي الشيفت:</td>
                <td class="fw-bold text-success fs-6"><?= money((float)$sh['total_revenue']) ?></td>
                <td colspan="3"></td>
              </tr>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Cashier comparison -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <i class="fa fa-trophy me-2 text-warning"></i>مقارنة الكاشيرز
      </div>
      <div class="card-body p-0">
        <?php
        $maxRev = !empty($cashierData) ? max(array_column($cashierData, 'total_revenue')) : 1;
        foreach ($cashierData as $ci => $c):
          $pct = $maxRev > 0 ? ($c['total_revenue'] / $maxRev * 100) : 0;
          $colors = ['#f59e0b','#3b82f6','#22c55e','#ef4444','#8b5cf6'];
          $color  = $colors[$ci % count($colors)];
        ?>
        <div class="px-4 py-3 border-bottom">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
              <div class="su-avatar cashier-avatar" style="width:32px;height:32px;font-size:12px;background:<?= $color ?>">
                <?= mb_substr($c['cashier_name'], 0, 1) ?>
              </div>
              <div>
                <div class="fw-bold" style="font-size:13px"><?= e($c['cashier_name']) ?></div>
                <div style="font-size:10px;color:#64748b">
                  <?= $c['shifts_count'] ?> شيفت &nbsp;·&nbsp; <?= $c['orders_count'] ?> طلب
                </div>
              </div>
            </div>
            <div class="text-end">
              <div class="fw-bold text-success" style="font-size:13px"><?= money((float)$c['total_revenue']) ?></div>
              <div style="font-size:10px;color:#64748b">متوسط: <?= money((float)$c['avg_order']) ?></div>
            </div>
          </div>
          <div class="progress" style="height:5px">
            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($cashierData)): ?>
        <div class="text-center text-muted py-4">لا توجد بيانات</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Orders Detail -->
<div class="card">
  <div class="card-header"><i class="fa fa-list"></i> تفاصيل الطلبات</div>
  <div class="card-body">
    <table id="ordersTable" class="table table-hover align-middle table-sm">
      <thead>
        <tr><th>#</th><th>الطاولة</th><th>الكاشير</th><th>المجموع</th>
            <th>الخصم</th><th>الصافي</th><th>الدفع</th><th>التاريخ</th></tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= generateOrderNumber($o['id']) ?></td>
          <td>طاولة <?= $o['table_num'] ?></td>
          <td><?= e($o['cashier_name']) ?></td>
          <td><?= money((float)$o['total']) ?></td>
          <td><?= $o['discount'] > 0 ? money((float)$o['discount']) : '—' ?></td>
          <td class="fw-bold text-success"><?= money((float)$o['final_total']) ?></td>
          <td>
            <?php if ($o['payment_method'] === 'instapay'): ?>
            <span class="badge text-white" style="background:#e91e8c">انستاباي</span>
            <?php elseif ($o['payment_method'] === 'split'): ?>
            <span class="badge text-white" style="background:#7c3aed">مقسم</span>
            <?php else: ?>
            <span class="badge <?= $o['payment_method']==='cash' ? 'bg-success' : ($o['payment_method']==='card' ? 'bg-primary' : 'bg-secondary') ?>">
              <?= ['cash'=>'نقدي','card'=>'بطاقة','other'=>'أخرى'][$o['payment_method']] ?? e($o['payment_method']) ?>
            </span>
            <?php endif; ?>
          </td>
          <td><?= formatDateAr($o['closed_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">لا توجد طلبات في هذه الفترة</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleCustom(val) {
  document.getElementById('customDates').classList.toggle('d-none', val !== 'custom');
}

function toggleItems(id) {
  var row = document.getElementById('items-' + id);
  var eye = document.getElementById('eye-' + id);
  var open = row.style.display === 'none';
  row.style.display = open ? 'table-row' : 'none';
  eye.className = open ? 'fa fa-eye-slash' : 'fa fa-eye';
}

function toggleShift(id) {
  var el    = document.getElementById('shift-' + id);
  var arrow = document.getElementById('arrow-' + id);
  var open  = el.style.display === 'none';
  el.style.display = open ? 'block' : 'none';
  arrow.className  = open ? 'fa fa-chevron-up text-warning' : 'fa fa-chevron-down text-muted';
}

const daily = <?= json_encode($dailyData) ?>;
new Chart(document.getElementById('dailyChart'), {
  type: 'line',
  data: {
    labels: daily.map(d => new Date(d.day).toLocaleDateString('ar-EG',{month:'short',day:'numeric'})),
    datasets: [{
      label: 'الإيرادات',
      data: daily.map(d => parseFloat(d.revenue || 0)),
      borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.1)',
      borderWidth: 2, fill: true, tension: .4, pointRadius: 5,
    }]
  },
  options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true},x:{grid:{display:false}}} }
});

const cats = <?= json_encode($catData) ?>;
if (cats.length) {
  new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
      labels: cats.map(c => c.cat),
      datasets: [{ data: cats.map(c => parseFloat(c.revenue)),
        backgroundColor: ['#f59e0b','#3b82f6','#22c55e','#ef4444','#8b5cf6','#14b8a6'],
        borderWidth: 2 }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom'}} }
  });
}

$('#ordersTable').DataTable({ order:[[7,'desc']] });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$shift = getActiveShift();
if (!$shift) { header('Location: ' . BASE_URL . '/cashier/'); exit; }

$db      = getDB();
$tableId = (int)($_GET['table'] ?? 0);
if (!$tableId) { header('Location: ' . BASE_URL . '/cashier/tables.php'); exit; }

$table = fetchOne('SELECT * FROM cafe_tables WHERE id=?', [$tableId]);
if (!$table) { header('Location: ' . BASE_URL . '/cashier/tables.php'); exit; }

$order = fetchOne("SELECT * FROM orders WHERE table_id=? AND status='open' LIMIT 1", [$tableId]);
if (!$order) {
    $db->prepare("INSERT INTO orders(table_id,shift_id,user_id,status,opened_at) VALUES(?,?,?,'open',NOW())")
       ->execute([$tableId, $shift['id'], $_SESSION['user_id']]);
    $orderId = (int)$db->lastInsertId();
    $db->prepare("UPDATE cafe_tables SET status='occupied' WHERE id=?")->execute([$tableId]);
    $order = fetchOne('SELECT * FROM orders WHERE id=?', [$orderId]);
}

$categories = fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order,name");
$items      = fetchAll("SELECT i.*,c.name as cat_name FROM items i LEFT JOIN categories c ON c.id=i.category_id WHERE i.is_active=1 ORDER BY c.sort_order,i.name");
$orderItems = fetchAll("SELECT * FROM order_items WHERE order_id=? ORDER BY created_at", [$order['id']]);

// Category icon/color mapping
$catStyle = [
    'مشروبات ساخنة' => ['icon'=>'fa-mug-hot',      'color'=>'#f97316','bg'=>'#fff7ed','fa'=>'☕'],
    'مشروبات باردة' => ['icon'=>'fa-glass-water',   'color'=>'#0ea5e9','bg'=>'#f0f9ff','fa'=>'🧊'],
    'وجبات خفيفة'  => ['icon'=>'fa-burger',         'color'=>'#22c55e','bg'=>'#f0fdf4','fa'=>'🍔'],
    'حلويات وكيك'  => ['icon'=>'fa-cake-candles',   'color'=>'#ec4899','bg'=>'#fdf2f8','fa'=>'🍰'],
    'مشروبات'       => ['icon'=>'fa-mug-saucer',    'color'=>'#8b5cf6','bg'=>'#f5f3ff','fa'=>'☕'],
    'أكل'           => ['icon'=>'fa-utensils',       'color'=>'#f59e0b','bg'=>'#fffbeb','fa'=>'🍽️'],
];
$defaultStyle = ['icon'=>'fa-tag','color'=>'#8b5cf6','bg'=>'#f5f3ff','fa'=>'🏷️'];

$catById = [];
foreach ($categories as $cat) {
    $catById[$cat['id']] = $catStyle[$cat['name']] ?? $defaultStyle;
}

$taxEnabled  = getSetting('tax_enabled', '0') === '1';
$taxPct      = (float)getSetting('tax_percent', '0');
$taxLabel    = getSetting('tax_label', 'ضريبة القيمة المضافة');
$svcEnabled  = getSetting('service_enabled', '0') === '1';
$svcPct      = (float)getSetting('service_percent', '0');
$svcLabel    = getSetting('service_label', 'رسوم الخدمة');

$pageTitle = 'طاولة ' . $table['number'];
require_once __DIR__ . '/../includes/cashier_header.php';
?>

<style>
/* ══ POS Layout ══════════════════════════════════════════ */
.pos-layout{display:flex;height:calc(100vh - var(--navbar-h) - 48px);overflow:hidden;gap:0}
body{overflow:hidden}
.pos-menu{flex:1;display:flex;flex-direction:column;overflow:hidden;padding:14px 14px 0;background:#f8fafc}
.pos-order{width:310px;min-width:290px;display:flex;flex-direction:column;background:#fff;border-right:1px solid #e2e8f0;box-shadow:-2px 0 8px rgba(0,0,0,.06)}

/* ══ Category Tiles ══════════════════════════════════════ */
.cat-tiles-bar{display:flex;gap:10px;overflow-x:auto;padding:0 0 12px;scrollbar-width:none;-webkit-overflow-scrolling:touch;flex-shrink:0}
.cat-tiles-bar::-webkit-scrollbar{display:none}

.cat-tile{display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;min-width:82px;padding:12px 8px 10px;border-radius:18px;background:#fff;border:2px solid #e2e8f0;cursor:pointer;transition:all .18s cubic-bezier(.4,0,.2,1);-webkit-tap-highlight-color:transparent;user-select:none;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.cat-tile:active{transform:scale(.93)}
.cat-tile.active{background:#0f172a;border-color:#f59e0b;box-shadow:0 4px 14px rgba(15,23,42,.25)}
.cat-tile.active .ct-name{color:#fff}
.cat-tile.active .ct-count{background:#f59e0b;color:#0f172a}
.cat-tile.active .ct-icon-wrap{background:#1e293b!important;color:#f59e0b!important}

.ct-icon-wrap{width:48px;height:48px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:7px;transition:all .18s}
.ct-name{font-size:11px;font-weight:700;color:#334155;text-align:center;line-height:1.3;max-width:78px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.ct-count{margin-top:5px;font-size:10px;font-weight:700;background:#e2e8f0;color:#64748b;padding:2px 7px;border-radius:20px}

/* ══ Search ══════════════════════════════════════════════ */
.pos-search{position:relative;flex-shrink:0;margin-bottom:12px}
.pos-search input{width:100%;height:44px;border-radius:14px;border:2px solid #e2e8f0;padding:0 44px 0 16px;font-size:14px;font-family:'Cairo',sans-serif;background:#fff;transition:border-color .2s}
.pos-search input:focus{outline:none;border-color:#f59e0b}
.pos-search .si{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:16px;pointer-events:none}

/* ══ Menu Item Cards ══════════════════════════════════════ */
.menu-grid-pro{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;overflow-y:auto;padding-bottom:14px;-webkit-overflow-scrolling:touch}
.menu-grid-pro::-webkit-scrollbar{width:4px}
.menu-grid-pro::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px}

.mip-card{background:#fff;border:2px solid #f1f5f9;border-radius:20px;padding:16px 8px 14px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;cursor:pointer;transition:all .14s cubic-bezier(.4,0,.2,1);text-align:center;min-height:130px;user-select:none;-webkit-tap-highlight-color:transparent;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.mip-card:hover{border-color:#f59e0b60;transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.09)}
.mip-card:active{transform:scale(.93);border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.2)}

.mip-icon{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:10px;flex-shrink:0}
.mip-name{font-size:12.5px;font-weight:700;color:#1e293b;line-height:1.4;margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;width:100%}
.mip-price{font-size:14px;font-weight:800;color:#f59e0b;font-family:'Cairo',sans-serif;letter-spacing:.3px}

/* ══ Order Panel ══════════════════════════════════════════ */
.pos-order-header{padding:14px 16px 12px;border-bottom:1px solid #f1f5f9;background:#0f172a;color:#fff;flex-shrink:0}
.pos-order-header .order-num{display:inline-block;background:#f59e0b;color:#0f172a;font-size:11px;font-weight:800;padding:3px 10px;border-radius:20px}
.pos-order-items{flex:1;overflow-y:auto;padding:10px 12px;-webkit-overflow-scrolling:touch}
.pos-order-items::-webkit-scrollbar{width:3px}
.pos-order-items::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:3px}

.order-item-row{display:flex;align-items:center;gap:6px;padding:8px 10px;border-radius:12px;background:#f8fafc;margin-bottom:6px;transition:background .15s}
.order-item-row:active{background:#f1f5f9}
.oi-name{flex:1;font-size:12.5px;font-weight:700;color:#1e293b;line-height:1.3}
.oi-qty{display:flex;align-items:center;gap:4px;flex-shrink:0}
.qty-btn{width:28px;height:28px;border-radius:9px;border:none;background:#e2e8f0;color:#334155;font-size:16px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .12s;line-height:1;padding:0;-webkit-tap-highlight-color:transparent}
.qty-btn:active{background:#cbd5e1;transform:scale(.9)}
.qty-btn.text-danger{background:#fee2e2;color:#ef4444}
.qty-btn.text-danger:active{background:#fecaca}
.oi-qty span{min-width:22px;text-align:center;font-size:13px;font-weight:700;color:#1e293b}
.oi-price{font-size:12px;font-weight:700;color:#f59e0b;flex-shrink:0}

.pos-order-footer{padding:12px 14px;border-top:1px solid #f1f5f9;flex-shrink:0;background:#fff}

/* Payment buttons */
.pay-method-btn{flex:1;height:46px;border-radius:14px;border:2px solid #e2e8f0;background:#f8fafc;color:#64748b;font-family:'Cairo',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:6px;-webkit-tap-highlight-color:transparent}
.pay-method-btn:active{transform:scale(.95)}
.pay-method-btn.active-pay{border-color:#22c55e;background:#f0fdf4;color:#16a34a}
.pay-method-btn.active-pay.card-pay{border-color:#0ea5e9;background:#f0f9ff;color:#0284c7}
.pay-method-btn.active-pay.split-pay{border-color:#a855f7;background:#faf5ff;color:#7c3aed}
.pay-method-btn.active-pay.insta-pay{border-color:#e91e8c;background:#fdf0f7;color:#c2185b}

/* Back button — PRIMARY action */
.btn-back-tables{width:100%;height:58px;border-radius:16px;border:none;background:#0f172a;color:#fff;font-family:'Cairo',sans-serif;font-size:15px;font-weight:800;cursor:pointer;transition:all .18s;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0;-webkit-tap-highlight-color:transparent;box-shadow:0 4px 14px rgba(15,23,42,.3)}
.btn-back-tables:active{transform:scale(.97);box-shadow:0 2px 6px rgba(15,23,42,.2)}

/* Close/pay button — SECONDARY action */
.btn-close-order{width:100%;height:46px;border-radius:14px;border:2px solid #22c55e;background:#f0fdf4;color:#15803d;font-family:'Cairo',sans-serif;font-size:14px;font-weight:800;cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:8px;-webkit-tap-highlight-color:transparent}
.btn-close-order:hover{background:#dcfce7}
.btn-close-order:active{transform:scale(.97);background:#bbf7d0}
.btn-close-order:disabled{opacity:.55;cursor:not-allowed}

.btn-secondary-sm{flex:1;height:42px;border-radius:12px;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;font-family:'Cairo',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:all .15s;-webkit-tap-highlight-color:transparent}
.btn-secondary-sm:active{background:#f1f5f9}
.btn-danger-sm{border-color:#fee2e2;color:#ef4444}
.btn-danger-sm:active{background:#fee2e2}

.empty-order{text-align:center;padding:40px 10px;color:#94a3b8}
.empty-order i{font-size:40px;margin-bottom:10px;display:block;opacity:.4}

/* ══ SweetAlert2 Custom ══════════════════════════════════ */
.swal-rtl-popup{font-family:'Cairo',sans-serif!important;border-radius:24px!important;padding:28px!important;direction:rtl}
.swal-confirm-btn{border-radius:12px!important;font-family:'Cairo',sans-serif!important;font-weight:800!important;font-size:14px!important;padding:10px 24px!important;box-shadow:0 4px 14px rgba(22,163,74,.3)!important}
.swal-cancel-btn{border-radius:12px!important;font-family:'Cairo',sans-serif!important;font-weight:700!important;font-size:13px!important;padding:10px 20px!important}
.swal2-actions{gap:10px!important;flex-direction:row-reverse!important}
</style>

<div class="pos-layout">

  <!-- ══ RIGHT: Order Panel ══════════════════════════════ -->
  <div class="pos-order">

    <div class="pos-order-header">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
        <span style="font-size:16px;font-weight:800"><i class="fa fa-table-cells-large me-2" style="color:#f59e0b"></i>طاولة <?= $table['number'] ?></span>
        <span class="order-num"><?= generateOrderNumber($order['id']) ?></span>
      </div>
      <small style="opacity:.7;font-size:11.5px">
        <i class="fa fa-clock me-1"></i><?= date('h:i A', strtotime($order['opened_at'])) ?>
        — <?= e($_SESSION['user_name']) ?>
      </small>
    </div>

    <!-- Order Items -->
    <div class="pos-order-items" id="orderItemsList">
      <?php if (empty($orderItems)): ?>
      <div class="empty-order" id="emptyMsg">
        <i class="fa fa-basket-shopping"></i>
        لم يتم إضافة أصناف بعد
      </div>
      <?php endif; ?>
      <?php foreach ($orderItems as $oi): ?>
      <div class="order-item-row" id="oi-<?= $oi['id'] ?>">
        <div class="oi-name"><?= e($oi['item_name']) ?></div>
        <div class="oi-qty">
          <button class="qty-btn" onclick="updateQty(<?= $oi['id'] ?>, -1)">−</button>
          <span id="qty-<?= $oi['id'] ?>"><?= $oi['quantity'] ?></span>
          <button class="qty-btn" onclick="updateQty(<?= $oi['id'] ?>, 1)">+</button>
        </div>
        <div class="oi-price" id="sub-<?= $oi['id'] ?>"><?= money((float)$oi['subtotal']) ?></div>
        <button class="qty-btn text-danger" onclick="removeItem(<?= $oi['id'] ?>)" title="حذف">×</button>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Totals & Actions -->
    <div class="pos-order-footer">

      <!-- Subtotal -->
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px">
        <span style="color:#64748b">المجموع الفرعي:</span>
        <span style="font-weight:700" id="subtotalDisplay"><?= money((float)$order['total']) ?></span>
      </div>

      <!-- Discount -->
      <div style="display:flex;gap:6px;margin-bottom:8px">
        <select id="discType" style="width:80px;height:36px;border-radius:10px;border:1.5px solid #e2e8f0;font-family:'Cairo',sans-serif;font-size:12px;padding:0 6px;background:#f8fafc">
          <option value="amount">ج.م</option>
          <option value="percent">%</option>
        </select>
        <input type="number" id="discValue" style="flex:1;height:36px;border-radius:10px;border:1.5px solid #e2e8f0;font-family:'Cairo',sans-serif;font-size:13px;padding:0 10px;background:#f8fafc" placeholder="خصم" min="0" step="0.5" value="0" oninput="recalcTotal()">
        <input type="text" id="discNotes" style="flex:1.2;height:36px;border-radius:10px;border:1.5px solid #e2e8f0;font-family:'Cairo',sans-serif;font-size:12px;padding:0 10px;background:#f8fafc" placeholder="سبب الخصم">
      </div>

      <div style="display:flex;justify-content:space-between;font-size:12px;color:#ef4444;margin-bottom:6px">
        <span>الخصم:</span><span id="discDisplay">—</span>
      </div>
      <?php if ($svcEnabled): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:#8b5cf6;margin-bottom:4px">
        <span><?= e($svcLabel) ?> (<?= $svcPct ?>%):</span>
        <span id="svcDisplay">0.00 <?= CURRENCY ?></span>
      </div>
      <?php endif; ?>
      <?php if ($taxEnabled): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:#f97316;margin-bottom:4px">
        <span><?= e($taxLabel) ?> (<?= $taxPct ?>%):</span>
        <span id="taxDisplay">0.00 <?= CURRENCY ?></span>
      </div>
      <?php endif; ?>
      <div style="display:flex;justify-content:space-between;border-top:2px solid #f1f5f9;padding-top:8px;margin-bottom:10px">
        <span style="font-weight:800;font-size:16px">الإجمالي:</span>
        <span style="font-weight:800;font-size:16px;color:#16a34a" id="totalDisplay"><?= money((float)$order['total']) ?></span>
      </div>

      <!-- Payment Method -->
      <div style="display:flex;gap:6px;margin-bottom:8px">
        <button class="pay-method-btn active-pay" id="btnCash" onclick="selectPay('cash')">
          <i class="fa fa-money-bill"></i> نقدي
        </button>
        <button class="pay-method-btn card-pay" id="btnCard" onclick="selectPay('card')">
          <i class="fa fa-credit-card"></i> بطاقة
        </button>
        <button class="pay-method-btn insta-pay" id="btnInsta" onclick="selectPay('instapay')">
          <i class="fa fa-mobile-screen-button"></i> انستاباي
        </button>
        <button class="pay-method-btn split-pay" id="btnSplit" onclick="selectPay('split')">
          <i class="fa fa-code-branch"></i> مقسم
        </button>
      </div>
      <!-- Split Inputs -->
      <div id="splitInputs" style="display:none;margin-bottom:8px;background:#faf5ff;border-radius:12px;padding:10px 12px;border:1.5px dashed #d8b4fe">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <span style="font-size:11px;color:#7c3aed;font-weight:700">
            <i class="fa fa-code-branch me-1"></i>توزيع المبلغ:
          </span>
          <span style="font-size:11px;color:#374151;font-weight:700">
            الإجمالي: <span id="splitTotal" style="color:#7c3aed"></span>
          </span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <i class="fa fa-money-bill" style="color:#16a34a;width:14px;flex-shrink:0"></i>
          <span style="font-size:12px;color:#374151;min-width:52px">نقدي</span>
          <input type="number" id="splitCash"
                 style="flex:1;height:30px;border-radius:8px;border:1.5px solid #d8b4fe;font-family:'Cairo',sans-serif;font-size:13px;padding:0 8px;background:#fff"
                 placeholder="0.00" step="0.50" min="0" oninput="clampSplitField('splitCash')">
          <span style="font-size:10px;color:#94a3b8;flex-shrink:0"><?= CURRENCY ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <i class="fa fa-credit-card" style="color:#0284c7;width:14px;flex-shrink:0"></i>
          <span style="font-size:12px;color:#374151;min-width:52px">بطاقة</span>
          <input type="number" id="splitCard"
                 style="flex:1;height:30px;border-radius:8px;border:1.5px solid #d8b4fe;font-family:'Cairo',sans-serif;font-size:13px;padding:0 8px;background:#fff"
                 placeholder="0.00" step="0.50" min="0" oninput="clampSplitField('splitCard')">
          <span style="font-size:10px;color:#94a3b8;flex-shrink:0"><?= CURRENCY ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <i class="fa fa-mobile-screen-button" style="color:#c2185b;width:14px;flex-shrink:0"></i>
          <span style="font-size:12px;color:#374151;min-width:52px">انستاباي</span>
          <input type="number" id="splitInsta"
                 style="flex:1;height:30px;border-radius:8px;border:1.5px solid #d8b4fe;font-family:'Cairo',sans-serif;font-size:13px;padding:0 8px;background:#fff"
                 placeholder="0.00" step="0.50" min="0" oninput="clampSplitField('splitInsta')">
          <span style="font-size:10px;color:#94a3b8;flex-shrink:0"><?= CURRENCY ?></span>
        </div>
        <div style="border-top:1px dashed #d8b4fe;padding-top:6px;text-align:center">
          <span id="splitRemaining" style="font-size:12px;font-weight:700"></span>
        </div>
      </div>

      <!-- Notes -->
      <textarea id="orderNotes" style="width:100%;border-radius:10px;border:1.5px solid #e2e8f0;font-family:'Cairo',sans-serif;font-size:12px;padding:8px 10px;background:#f8fafc;resize:none;margin-bottom:10px" rows="1" placeholder="ملاحظات..."></textarea>

      <!-- Buttons -->
      <button class="btn-back-tables" onclick="history.back()">
        <i class="fa fa-table-cells-large"></i> رجوع للطاولات
        <small style="display:block;font-size:10px;font-weight:400;opacity:.8;margin-top:1px">الطاولة ستبقى مشغولة والطلب محفوظ</small>
      </button>
      <button class="btn-close-order" onclick="closeOrder()" id="closeBtn" style="margin-top:8px">
        <i class="fa fa-money-bill-wave"></i> حساب الفاتورة وإغلاق الطاولة
      </button>
      <div style="margin-top:6px">
        <button class="btn-secondary-sm btn-danger-sm" onclick="cancelOrder()" style="width:100%;height:36px;font-size:12px">
          <i class="fa fa-ban me-1"></i>إلغاء الطلب بدون دفع
        </button>
      </div>

    </div>
  </div><!-- /pos-order -->

  <!-- ══ LEFT: Menu ══════════════════════════════════════ -->
  <div class="pos-menu">

    <!-- Category Tiles -->
    <div class="cat-tiles-bar">
      <div class="cat-tile active" onclick="filterCat(0, this)">
        <div class="ct-icon-wrap" style="background:#f1f5f9;color:#64748b">
          <i class="fa fa-border-all"></i>
        </div>
        <span class="ct-name">الكل</span>
        <span class="ct-count"><?= count($items) ?></span>
      </div>
      <?php foreach ($categories as $cat):
        $cs = $catStyle[$cat['name']] ?? $defaultStyle;
        $cnt = count(array_filter($items, function($i) use ($cat) { return $i['category_id'] == $cat['id']; }));
      ?>
      <div class="cat-tile" onclick="filterCat(<?= $cat['id'] ?>, this)">
        <div class="ct-icon-wrap" style="background:<?= $cs['bg'] ?>;color:<?= $cs['color'] ?>">
          <i class="fa <?= $cs['icon'] ?>"></i>
        </div>
        <span class="ct-name"><?= e($cat['name']) ?></span>
        <span class="ct-count"><?= $cnt ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Search -->
    <div class="pos-search">
      <input type="text" id="itemSearch" placeholder="بحث عن صنف..." oninput="searchItems(this.value)">
      <i class="fa fa-magnifying-glass si"></i>
    </div>

    <!-- Menu Grid -->
    <div class="menu-grid-pro" id="menuGrid">
      <?php foreach ($items as $item):
        $cs = $catById[$item['category_id']] ?? $defaultStyle;
      ?>
      <div class="mip-card"
           data-cat="<?= $item['category_id'] ?>"
           data-name="<?= e(strtolower($item['name'])) ?>"
           onclick="addToOrder(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>)">
        <div class="mip-icon" style="background:<?= $cs['bg'] ?>;color:<?= $cs['color'] ?>">
          <i class="fa <?= $cs['icon'] ?>"></i>
        </div>
        <div class="mip-name"><?= e($item['name']) ?></div>
        <div class="mip-price"><?= money((float)$item['price']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

  </div><!-- /pos-menu -->

</div><!-- /pos-layout -->

<script>
const orderId   = <?= $order['id'] ?>;
const tableId   = <?= $tableId ?>;
const BASE      = '<?= BASE_URL ?>';
let subtotal    = <?= $order['total'] ?>;
let selectedPay = 'cash';
const SVC_ENABLED = <?= $svcEnabled ? 'true' : 'false' ?>;
const SVC_PCT     = <?= $svcPct ?>;
const SVC_LABEL   = '<?= addslashes($svcLabel) ?>';
const TAX_ENABLED = <?= $taxEnabled ? 'true' : 'false' ?>;
const TAX_PCT     = <?= $taxPct ?>;
const TAX_LABEL   = '<?= addslashes($taxLabel) ?>';

function filterCat(catId, el) {
  document.querySelectorAll('.cat-tile').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('.mip-card').forEach(card => {
    card.style.display = catId === 0 || card.dataset.cat == catId ? '' : 'none';
  });
}
function searchItems(q) {
  const lc = q.toLowerCase();
  document.querySelectorAll('.mip-card').forEach(card => {
    card.style.display = card.dataset.name.includes(lc) ? '' : 'none';
  });
}

function addToOrder(itemId, name, price) {
  $.post(BASE + '/cashier/api/order.php', {
    action: 'add_item', order_id: orderId, item_id: itemId
  }, function(res) {
    if (res.success) updateOrderDisplay(res.order_item, res.subtotal);
    else toast('error', res.message);
  }, 'json');
}

function updateOrderDisplay(oi, newSubtotal) {
  document.getElementById('emptyMsg')?.remove();
  const existing = document.getElementById('oi-' + oi.id);
  if (existing) {
    document.getElementById('qty-' + oi.id).textContent = oi.quantity;
    document.getElementById('sub-' + oi.id).textContent = fmoney(oi.subtotal);
  } else {
    const div = document.createElement('div');
    div.className = 'order-item-row';
    div.id = 'oi-' + oi.id;
    div.innerHTML =
      '<div class="oi-name">' + oi.item_name + '</div>' +
      '<div class="oi-qty">' +
        '<button class="qty-btn" onclick="updateQty(' + oi.id + ',-1)">−</button>' +
        '<span id="qty-' + oi.id + '">' + oi.quantity + '</span>' +
        '<button class="qty-btn" onclick="updateQty(' + oi.id + ',1)">+</button>' +
      '</div>' +
      '<div class="oi-price" id="sub-' + oi.id + '">' + fmoney(oi.subtotal) + '</div>' +
      '<button class="qty-btn text-danger" onclick="removeItem(' + oi.id + ')">×</button>';
    document.getElementById('orderItemsList').appendChild(div);
  }
  subtotal = parseFloat(newSubtotal);
  recalcTotal();
}

function fmoney(v) { return parseFloat(v).toFixed(2) + ' <?= CURRENCY ?>'; }

function updateQty(oiId, delta) {
  $.post(BASE + '/cashier/api/order.php', { action:'update_qty', oi_id:oiId, delta:delta }, function(res) {
    if (res.success) {
      if (res.removed) {
        document.getElementById('oi-' + oiId)?.remove();
      } else {
        document.getElementById('qty-' + oiId).textContent = res.quantity;
        document.getElementById('sub-' + oiId).textContent = fmoney(res.subtotal);
      }
      subtotal = parseFloat(res.order_total);
      recalcTotal();
      if (!document.querySelector('.order-item-row')) {
        const el = document.createElement('div');
        el.id = 'emptyMsg'; el.className = 'empty-order';
        el.innerHTML = '<i class="fa fa-basket-shopping"></i>لم يتم إضافة أصناف بعد';
        document.getElementById('orderItemsList').appendChild(el);
      }
    } else toast('error', res.message);
  }, 'json');
}

function removeItem(oiId) {
  $.post(BASE + '/cashier/api/order.php', { action:'remove_item', oi_id:oiId }, function(res) {
    if (res.success) {
      document.getElementById('oi-' + oiId)?.remove();
      subtotal = parseFloat(res.order_total);
      recalcTotal();
    }
  }, 'json');
}

function recalcTotal() {
  const dv = parseFloat(document.getElementById('discValue').value) || 0;
  const dt = document.getElementById('discType').value;
  let disc = dt === 'percent' ? subtotal * dv / 100 : dv;
  disc = Math.min(disc, subtotal);
  const afterDisc = subtotal - disc;
  const svcAmt = SVC_ENABLED ? Math.round(afterDisc * SVC_PCT) / 100 : 0;
  const taxAmt = TAX_ENABLED ? Math.round(afterDisc * TAX_PCT) / 100 : 0;
  const total  = afterDisc + svcAmt + taxAmt;
  document.getElementById('subtotalDisplay').textContent = fmoney(subtotal);
  document.getElementById('discDisplay').textContent = disc > 0 ? '- ' + fmoney(disc) : '—';
  if (document.getElementById('svcDisplay')) document.getElementById('svcDisplay').textContent = fmoney(svcAmt);
  if (document.getElementById('taxDisplay')) document.getElementById('taxDisplay').textContent = fmoney(taxAmt);
  document.getElementById('totalDisplay').textContent = fmoney(total);
  if (selectedPay === 'split') updateSplitRemaining();
}

function getCurrentTotal() {
  var dv = parseFloat(document.getElementById('discValue').value) || 0;
  var dt = document.getElementById('discType').value;
  var disc = dt === 'percent' ? subtotal * dv / 100 : dv;
  disc = Math.min(disc, subtotal);
  var afterDisc = subtotal - disc;
  var svc = SVC_ENABLED ? Math.round(afterDisc * SVC_PCT) / 100 : 0;
  var tax = TAX_ENABLED ? Math.round(afterDisc * TAX_PCT) / 100 : 0;
  return afterDisc + svc + tax;
}

function selectPay(method) {
  selectedPay = method;
  document.getElementById('btnCash').classList.toggle('active-pay', method === 'cash');
  document.getElementById('btnCard').classList.toggle('active-pay', method === 'card');
  document.getElementById('btnInsta').classList.toggle('active-pay', method === 'instapay');
  document.getElementById('btnSplit').classList.toggle('active-pay', method === 'split');
  var splitDiv = document.getElementById('splitInputs');
  if (method === 'split') {
    splitDiv.style.display = 'block';
    var total = getCurrentTotal();
    document.getElementById('splitCash').value  = total.toFixed(2);
    document.getElementById('splitCard').value  = '0.00';
    document.getElementById('splitInsta').value = '0.00';
    updateSplitRemaining();
  } else {
    splitDiv.style.display = 'none';
  }
}

function updateSplitRemaining() {
  var total = getCurrentTotal();
  var cash  = parseFloat(document.getElementById('splitCash').value)  || 0;
  var card  = parseFloat(document.getElementById('splitCard').value)  || 0;
  var insta = parseFloat(document.getElementById('splitInsta').value) || 0;
  var remaining = total - cash - card - insta;
  var el = document.getElementById('splitRemaining');
  document.getElementById('splitTotal').textContent = fmoney(total);
  if (Math.abs(remaining) < 0.01) {
    el.innerHTML = '<i class="fa fa-check-circle me-1"></i>مكتمل';
    el.style.color = '#16a34a';
  } else if (remaining > 0) {
    el.textContent = 'متبقي: ' + remaining.toFixed(2) + ' <?= CURRENCY ?>';
    el.style.color = '#ef4444';
  } else {
    el.textContent = 'زيادة: ' + Math.abs(remaining).toFixed(2) + ' <?= CURRENCY ?> — قلل أحد المبالغ';
    el.style.color = '#f97316';
  }
}

function clampSplitField(changedId) {
  var ids = ['splitCash','splitCard','splitInsta'];
  var total = getCurrentTotal();
  var others = 0;
  ids.forEach(function(id){ if (id !== changedId) others += parseFloat(document.getElementById(id).value) || 0; });
  var max = Math.max(0, total - others);
  var inp = document.getElementById(changedId);
  var val = parseFloat(inp.value) || 0;
  if (val > max) inp.value = max.toFixed(2);
  updateSplitRemaining();
}

function closeOrder() {
  if (!document.querySelector('.order-item-row')) {
    Swal.fire('تنبيه','لا يوجد أي أصناف في الطلب','warning'); return;
  }
  const disc  = parseFloat(document.getElementById('discValue').value) || 0;
  const dtype = document.getElementById('discType').value;
  const notes = document.getElementById('orderNotes').value;
  var splitCashAmt  = selectedPay === 'split' ? (parseFloat(document.getElementById('splitCash').value)  || 0) : 0;
  var splitCardAmt  = selectedPay === 'split' ? (parseFloat(document.getElementById('splitCard').value)  || 0) : 0;
  var splitInstaAmt = selectedPay === 'split' ? (parseFloat(document.getElementById('splitInsta').value) || 0) : 0;

  var splitParts = [];
  if (splitCashAmt  > 0) splitParts.push('نقدي '     + splitCashAmt.toFixed(2));
  if (splitCardAmt  > 0) splitParts.push('بطاقة '    + splitCardAmt.toFixed(2));
  if (splitInstaAmt > 0) splitParts.push('انستاباي ' + splitInstaAmt.toFixed(2));

  const payLabel = selectedPay === 'cash'
    ? '<span style="color:#16a34a;font-weight:700"><i class="fa fa-money-bills me-1"></i>نقدي</span>'
    : selectedPay === 'card'
    ? '<span style="color:#2563eb;font-weight:700"><i class="fa fa-credit-card me-1"></i>بطاقة</span>'
    : selectedPay === 'instapay'
    ? '<span style="color:#c2185b;font-weight:700"><i class="fa fa-mobile-screen-button me-1"></i>انستاباي</span>'
    : '<span style="color:#7c3aed;font-weight:700"><i class="fa fa-code-branch me-1"></i>مقسم</span>' +
      '<small style="display:block;color:#64748b;font-size:11px;margin-top:2px">' + splitParts.join(' + ') + ' <?= CURRENCY ?></small>';
  const itemCount  = document.querySelectorAll('.order-item-row').length;
  const discVal    = parseFloat(document.getElementById('discValue').value) || 0;
  const discType   = document.getElementById('discType').value;
  const discAmt    = discType === 'percent' ? subtotal * discVal / 100 : discVal;
  const afterDisc  = subtotal - Math.min(discAmt, subtotal);
  const svcAmt     = SVC_ENABLED ? Math.round(afterDisc * SVC_PCT) / 100 : 0;
  const taxAmt     = TAX_ENABLED ? Math.round(afterDisc * TAX_PCT) / 100 : 0;
  const grandTotal = afterDisc + svcAmt + taxAmt;

  if (selectedPay === 'split' && Math.abs(grandTotal - splitCashAmt - splitCardAmt - splitInstaAmt) > 0.01) {
    Swal.fire({title:'تنبيه', text:'مجموع الدفعات لا يساوي الإجمالي. تأكد من توزيع المبلغ كاملاً.', icon:'warning', customClass:{popup:'swal-rtl-popup'}}); return;
  }

  const extraRows = `
    ${SVC_ENABLED ? `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <span style="color:#8b5cf6;font-size:12px">${SVC_LABEL} (${SVC_PCT}%)</span>
      <span style="color:#8b5cf6;font-weight:700">+ ${parseFloat(svcAmt).toFixed(2)} <?= CURRENCY ?></span>
    </div>` : ''}
    ${TAX_ENABLED ? `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <span style="color:#f97316;font-size:12px">${TAX_LABEL} (${TAX_PCT}%)</span>
      <span style="color:#f97316;font-weight:700">+ ${parseFloat(taxAmt).toFixed(2)} <?= CURRENCY ?></span>
    </div>` : ''}
  `;

  Swal.fire({
    title: '',
    html: `
      <div style="text-align:center;font-family:'Cairo',sans-serif">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#f97316,#ea580c);margin:0 auto 16px;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(249,115,22,.35)">
          <i class="fa fa-cash-register" style="font-size:32px;color:#fff"></i>
        </div>
        <div style="font-size:20px;font-weight:900;color:#0f172a;margin-bottom:4px">إغلاق الطاولة وتحصيل الفاتورة</div>
        <div style="font-size:13px;color:#64748b;margin-bottom:20px">تأكيد إتمام الطلب وإنهاء الجلسة</div>

        <div style="background:#f8fafc;border-radius:16px;padding:16px;margin-bottom:16px;border:1px solid #e2e8f0;text-align:right">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="color:#64748b;font-size:13px">عدد الأصناف</span>
            <span style="font-weight:700;color:#334155">${itemCount} صنف</span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="color:#64748b;font-size:13px">المجموع الفرعي</span>
            <span style="font-weight:700;color:#334155">${parseFloat(subtotal).toFixed(2)} <?= CURRENCY ?></span>
          </div>
          ${discAmt > 0 ? `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="color:#ef4444;font-size:12px">الخصم</span>
            <span style="color:#ef4444;font-weight:700">- ${parseFloat(discAmt).toFixed(2)} <?= CURRENCY ?></span>
          </div>` : ''}
          ${extraRows}
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <span style="color:#64748b;font-size:13px">طريقة الدفع</span>
            <span style="font-size:13px">${payLabel}</span>
          </div>
          <div style="border-top:2px dashed #e2e8f0;margin:10px 0"></div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="color:#0f172a;font-size:15px;font-weight:700">الإجمالي المستحق</span>
            <span style="font-size:22px;font-weight:900;color:#f97316">${parseFloat(grandTotal).toFixed(2)} <?= CURRENCY ?></span>
          </div>
        </div>

        <div style="background:#fef9c3;border:1px solid #fde047;border-radius:12px;padding:10px 14px;display:flex;align-items:center;gap:8px;font-size:12px;color:#713f12">
          <i class="fa fa-triangle-exclamation" style="color:#eab308"></i>
          بعد التأكيد ستُغلق الطاولة ولا يمكن التراجع
        </div>
      </div>`,
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
    cancelButtonColor: '#94a3b8',
    confirmButtonText: '<i class="fa fa-check me-1"></i> تأكيد وطباعة الفاتورة',
    cancelButtonText: '<i class="fa fa-arrow-right me-1"></i> رجوع',
    reverseButtons: true,
    customClass: {
      popup: 'swal-rtl-popup',
      confirmButton: 'swal-confirm-btn',
      cancelButton: 'swal-cancel-btn',
    },
    width: 420,
    padding: '24px',
    showClass: { popup: 'animate__animated animate__zoomIn animate__faster' },
  }).then(function(r) {
    if (!r.isConfirmed) return;
    document.getElementById('closeBtn').disabled = true;
    var cashAmt  = selectedPay === 'split' ? splitCashAmt  : (selectedPay === 'cash'     ? grandTotal : 0);
    var cardAmt  = selectedPay === 'split' ? splitCardAmt  : (selectedPay === 'card'     ? grandTotal : 0);
    var instaAmt = selectedPay === 'split' ? splitInstaAmt : (selectedPay === 'instapay' ? grandTotal : 0);
    $.post(BASE + '/cashier/api/order.php', {
      action:'close_order', order_id:orderId,
      discount:disc, discount_type:dtype,
      payment_method:selectedPay, notes:notes,
      cash_amount: cashAmt, card_amount: cardAmt, instapay_amount: instaAmt
    }, function(res) {
      if (res.success) window.location.href = BASE + '/cashier/receipt.php?order=' + orderId;
      else { toast('error', res.message); document.getElementById('closeBtn').disabled = false; }
    }, 'json');
  });
}

// init totals on page load
recalcTotal();

function cancelOrder() {
  Swal.fire({
    title:'إلغاء الطلب؟', text:'سيتم إلغاء الطلب وتحرير الطاولة', icon:'warning',
    showCancelButton:true, confirmButtonColor:'#ef4444',
    confirmButtonText:'نعم، إلغاء', cancelButtonText:'تراجع', reverseButtons:true,
  }).then(function(r) {
    if (!r.isConfirmed) return;
    $.post(BASE + '/cashier/api/order.php', { action:'cancel_order', order_id:orderId }, function(res) {
      if (res.success) window.location.href = BASE + '/cashier/tables.php';
      else toast('error', res.message);
    }, 'json');
  });
}
</script>

<?php require_once __DIR__ . '/../includes/cashier_footer.php'; ?>

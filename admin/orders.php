<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm(['pos.settle', 'billing.manage']);
$title = 'Restaurant Orders';
$active = 'orders';

$id = input_int('id');
require __DIR__ . '/../includes/admin_header.php';

if ($id && ($o = get_order($id))):
    $kots = q_all('SELECT * FROM kots WHERE order_id=? ORDER BY id', [$id]);
    $inv = q_row('SELECT * FROM invoices WHERE order_id=? ORDER BY id DESC LIMIT 1', [$id]); ?>
  <div class="grid g-2-1">
    <div class="card">
      <div class="card-head"><h2><?= e($o['order_no']) ?> · <?= e($o['customer_name']) ?></h2>
        <div class="btn-row"><a class="btn btn-sm btn-ghost" target="_blank" href="<?= e(url('pos/receipt.php?order=' . $id)) ?>">🖨 Bill / receipt</a><a class="btn btn-sm btn-ghost" href="orders.php">← All orders</a></div></div>
      <table class="table"><thead><tr><th>Item</th><th>KOT</th><th>Notes</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Total</th></tr></thead><tbody>
      <?php foreach ($o['items'] as $i): ?>
        <tr><td><?= e($i['item_name']) ?></td><td class="small"><?= e($i['kot_no']) ?></td><td class="small muted"><?= e($i['notes']) ?></td><td class="num"><?= (int) $i['qty'] ?></td><td class="num"><?= money($i['unit_price']) ?></td><td class="num"><?= money($i['line_total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody><tfoot>
        <tr><td colspan="5" class="right">Subtotal</td><td class="num"><?= money($o['subtotal']) ?></td></tr>
        <?php if ($o['discount'] > 0): ?><tr><td colspan="5" class="right">Discount</td><td class="num">− <?= money($o['discount']) ?></td></tr><?php endif; ?>
        <tr><td colspan="5" class="right">Service charge</td><td class="num"><?= money($o['service_charge']) ?></td></tr>
        <tr><td colspan="5" class="right">Tax</td><td class="num"><?= money($o['tax']) ?></td></tr>
        <tr><td colspan="5" class="right">Total</td><td class="num"><?= money($o['total']) ?></td></tr>
      </tfoot></table>
    </div>
    <div class="card"><dl class="dl">
      <dt>Type</dt><dd><?= e(label($o['order_type'])) ?></dd><dt>Table</dt><dd><?= e($o['table_no'] ?: '—') ?></dd>
      <dt>Guest booking</dt><dd><?= $o['booking_id'] ? '<a href="booking_view.php?id=' . $o['booking_id'] . '">' . e($o['booking_ref']) . '</a> · room ' . e($o['room_number']) : '—' ?></dd>
      <dt>Status</dt><dd><?= badge($o['status']) ?> <?= badge($o['payment_status']) ?></dd><dt>Opened</dt><dd><?= fdatetime($o['created_at']) ?></dd>
      <dt>Closed</dt><dd><?= fdatetime($o['closed_at']) ?></dd><dt>Staff</dt><dd><?= e($o['cashier']) ?></dd>
      <dt>Invoice</dt><dd><?= $inv ? '<a href="invoice.php?id=' . $inv['id'] . '">' . e($inv['invoice_no']) . '</a>' : '—' ?></dd>
      <dt>KOTs</dt><dd><?php foreach ($kots as $k): ?><a target="_blank" href="<?= e(url('pos/receipt.php?kot=' . $k['id'])) ?>"><?= e($k['kot_no']) ?></a> <?= badge($k['status']) ?><br><?php endforeach; ?></dd>
      <?php foreach ($o['payments'] as $p): ?><dt>Payment</dt><dd><?= money($p['amount']) ?> · <?= e(label($p['method'])) ?></dd><?php endforeach; ?>
    </dl></div>
  </div>
<?php else:
    $date = valid_date(input('date')) ? input('date') : date('Y-m-d');
    $st = input('status');
    $params = [$date];
    $w = 'DATE(o.created_at) = ?';
    if (in_array($st, ['open', 'closed', 'cancelled'], true)) { $w .= ' AND o.status = ?'; $params[] = $st; }
    $rows = q_all("SELECT o.*, t.table_no, u.full_name cashier FROM orders o LEFT JOIN restaurant_tables t ON t.id=o.table_id LEFT JOIN users u ON u.id=o.cashier_id WHERE $w ORDER BY o.created_at DESC", $params);
    $sum = q_row("SELECT COUNT(*) n, COALESCE(SUM(total),0) total, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total END),0) paid,
                    COALESCE(SUM(CASE WHEN payment_status='room_charge' THEN total END),0) room FROM orders WHERE DATE(created_at)=? AND status='closed'", [$date]); ?>
  <div class="stats">
    <div class="stat"><div class="k">Closed orders</div><div class="v"><?= (int) $sum['n'] ?></div></div>
    <div class="stat" style="--c:var(--green)"><div class="k">Sales</div><div class="v"><?= money($sum['total']) ?></div></div>
    <div class="stat" style="--c:var(--blue)"><div class="k">Paid at POS</div><div class="v"><?= money($sum['paid']) ?></div></div>
    <div class="stat" style="--c:var(--purple)"><div class="k">Charged to rooms</div><div class="v"><?= money($sum['room']) ?></div></div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Orders</h2><?php if (can('pos.use')): ?><a class="btn" href="<?= e(url('pos/index.php')) ?>">Open POS</a><?php endif; ?></div>
    <form class="filters"><div class="field"><label>Date</label><input type="date" name="date" value="<?= e($date) ?>"></div>
      <div class="field"><label>Status</label><select name="status"><option value="">All</option><?= options(['open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled'], $st) ?></select></div><button class="btn btn-ghost">Show</button></form>
    <div class="table-wrap"><table class="table"><thead><tr><th>Order</th><th>Time</th><th>Type</th><th>Customer / table</th><th>Staff</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><a href="?id=<?= $r['id'] ?>"><?= e($r['order_no']) ?></a></td><td><?= date('H:i', strtotime($r['created_at'])) ?></td><td><?= e(label($r['order_type'])) ?></td>
      <td><?= e($r['customer_name']) ?></td><td class="small"><?= e($r['cashier']) ?></td><td><?= badge($r['status']) ?></td><td><?= badge($r['payment_status']) ?></td><td class="num"><?= money($r['total']) ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">No orders on this date.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
<?php endif;
require __DIR__ . '/../includes/admin_footer.php';

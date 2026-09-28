<?php
/** Thermal-style bill / receipt (?order=ID) or KOT slip (?kot=ID). */
require __DIR__ . '/../includes/bootstrap.php';
require_perm(['pos.use', 'billing.manage', 'kitchen.view']);

$kotId = input_int('kot');
if ($kotId) {
    $kot = q_row('SELECT k.*, o.order_no, o.order_type, o.customer_name, t.table_no, u.full_name waiter FROM kots k JOIN orders o ON o.id=k.order_id LEFT JOIN restaurant_tables t ON t.id=o.table_id LEFT JOIN users u ON u.id=k.created_by WHERE k.id=?', [$kotId]);
    $items = $kot ? q_all("SELECT * FROM order_items WHERE kot_id=? AND status<>'cancelled'", [$kotId]) : [];
}
$o = !$kotId ? get_order(input_int('order')) : null;
if (!$o && empty($kot)) { http_response_code(404); exit('Not found'); }
$invoice = $o ? q_row('SELECT * FROM invoices WHERE order_id=? AND status<>"void" ORDER BY id DESC LIMIT 1', [$o['id']]) : null;
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Receipt</title>
<style>
body{font:13px/1.35 "Courier New",monospace;margin:0;background:#eee}
.slip{width:300px;margin:10px auto;background:#fff;padding:14px 16px;box-shadow:0 1px 4px rgba(0,0,0,.2)}
h1{font-size:16px;text-align:center;margin:0}
.c{text-align:center}.r{text-align:right}
hr{border:0;border-top:1px dashed #000;margin:8px 0}
table{width:100%;border-collapse:collapse}td{vertical-align:top;padding:1px 0}
.big{font-size:16px;font-weight:bold}
.no-print{text-align:center;margin:10px}
@media print{body{background:#fff}.slip{box-shadow:none;margin:0;width:auto}.no-print{display:none}}
</style></head>
<body>
<div class="no-print"><button onclick="window.print()">🖨 Print</button> <button onclick="window.close()">Close</button></div>
<div class="slip">
<?php if (!empty($kot)): ?>
  <h1>KITCHEN ORDER TICKET</h1>
  <div class="c big"><?= e($kot['kot_no']) ?></div><hr>
  <div><?= e($kot['table_no'] ? 'TABLE ' . $kot['table_no'] : strtoupper($kot['customer_name'])) ?> · <?= e(label($kot['order_type'])) ?></div>
  <div><?= e($kot['order_no']) ?> · <?= fdatetime($kot['created_at']) ?></div>
  <div>By: <?= e($kot['waiter']) ?></div><hr>
  <?php foreach ($items as $i): ?><div class="big"><?= (int) $i['qty'] ?> × <?= e($i['item_name']) ?></div><?php if ($i['notes']): ?><div>  ** <?= e($i['notes']) ?></div><?php endif; ?><?php endforeach; ?>
<?php else: ?>
  <h1><?= e(setting('villa_name')) ?></h1>
  <div class="c"><?= e(setting('villa_address')) ?><br><?= e(setting('villa_phone')) ?><br><?= e(setting('website')) ?></div><hr>
  <div class="c big"><?= $o['status'] === 'open' ? 'GUEST BILL (UNPAID)' : ($o['payment_status'] === 'room_charge' ? 'ROOM CHARGE SLIP' : 'RECEIPT') ?></div>
  <div>Order: <?= e($o['order_no']) ?><?= $invoice ? ' · ' . e($invoice['invoice_no']) : '' ?></div>
  <div><?= e(label($o['order_type'])) ?><?= $o['table_no'] ? ' · Table ' . e($o['table_no']) : '' ?> · <?= (int) $o['covers'] ?> pax</div>
  <div><?= e($o['customer_name']) ?></div>
  <div><?= fdatetime($o['closed_at'] ?: $o['created_at']) ?> · <?= e($o['cashier']) ?></div><hr>
  <table>
  <?php foreach ($o['items'] as $i): ?>
    <tr><td><?= (int) $i['qty'] ?>× <?= e($i['item_name']) ?></td><td class="r"><?= number_format($i['line_total'], 2) ?></td></tr>
  <?php endforeach; ?>
  </table><hr>
  <table>
    <tr><td>Subtotal</td><td class="r"><?= number_format($o['subtotal'], 2) ?></td></tr>
    <?php if ($o['discount'] > 0): ?><tr><td>Discount</td><td class="r">-<?= number_format($o['discount'], 2) ?></td></tr><?php endif; ?>
    <tr><td>Service <?= e(setting('service_charge')) ?>%</td><td class="r"><?= number_format($o['service_charge'], 2) ?></td></tr>
    <tr><td>Tax <?= e(setting('tax_rate')) ?>%</td><td class="r"><?= number_format($o['tax'], 2) ?></td></tr>
    <tr class="big"><td>TOTAL</td><td class="r"><?= money($o['total']) ?></td></tr>
  </table>
  <?php foreach ($o['payments'] as $p): ?><div>Paid by <?= e(label($p['method'])) ?>: <?= money($p['amount']) ?><?= $p['reference'] ? ' (' . e($p['reference']) . ')' : '' ?></div><?php endforeach; ?>
  <?php if ($o['payment_status'] === 'room_charge'): ?>
    <hr><div>Charged to room <?= e($o['room_number']) ?> (<?= e($o['booking_ref']) ?>)</div><br><div>Guest signature: ____________________</div>
  <?php endif; ?>
  <hr><div class="c">Thank you! Please visit again.</div>
<?php endif; ?>
</div>
</body></html>

<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('billing.manage');
$title = 'Payments';
$active = 'payments';

$from = valid_date(input('from')) ? input('from') : date('Y-m-01');
$to = valid_date(input('to')) ? input('to') : date('Y-m-d');
$method = input('method');
$w = 'DATE(p.paid_at) BETWEEN ? AND ?';
$params = [$from, $to];
if (in_array($method, ['cash', 'card', 'bank_transfer', 'online', 'voucher'], true)) { $w .= ' AND p.method=?'; $params[] = $method; }
$rows = q_all("SELECT p.*, b.booking_ref, o.order_no, i.invoice_no, u.full_name staff,
                 COALESCE(CONCAT(g.first_name,' ',g.last_name), o.customer_name) payer
               FROM payments p LEFT JOIN bookings b ON b.id=p.booking_id LEFT JOIN guests g ON g.id=b.guest_id
               LEFT JOIN orders o ON o.id=p.order_id LEFT JOIN invoices i ON i.id=p.invoice_id LEFT JOIN users u ON u.id=p.received_by
               WHERE $w ORDER BY p.paid_at DESC", $params);
$byMethod = [];
foreach ($rows as $r) $byMethod[$r['method']] = ($byMethod[$r['method']] ?? 0) + $r['amount'];

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="stats">
  <div class="stat"><div class="k">Total received</div><div class="v"><?= money(array_sum($byMethod)) ?></div><div class="s"><?= count($rows) ?> payment(s)</div></div>
  <?php foreach ($byMethod as $m => $v): ?><div class="stat" style="--c:var(--accent)"><div class="k"><?= e(label($m)) ?></div><div class="v"><?= money($v) ?></div></div><?php endforeach; ?>
</div>
<div class="card">
  <form class="filters"><div class="field"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div><div class="field"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="field"><label>Method</label><select name="method"><option value="">All</option><?= options(['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'online' => 'Online', 'voucher' => 'Voucher'], $method) ?></select></div><button class="btn btn-ghost">Show</button></form>
  <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Payer</th><th>For</th><th>Invoice</th><th>Method</th><th>Reference</th><th>Received by</th><th class="num">Amount</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td class="nowrap"><?= fdatetime($r['paid_at']) ?></td><td><?= e($r['payer']) ?></td>
    <td><?= $r['booking_ref'] ? '<a href="booking_view.php?id=' . $r['booking_id'] . '">' . e($r['booking_ref']) . '</a>' : '' ?><?= $r['order_no'] ? '<a href="orders.php?id=' . $r['order_id'] . '">' . e($r['order_no']) . '</a>' : '' ?></td>
    <td><?= $r['invoice_no'] ? '<a href="invoice.php?id=' . $r['invoice_id'] . '">' . e($r['invoice_no']) . '</a>' : '<span class="muted">—</span>' ?></td>
    <td><?= e(label($r['method'])) ?></td><td class="small"><?= e($r['reference']) ?></td><td class="small"><?= e($r['staff'] ?? 'Online') ?></td><td class="num"><?= money($r['amount']) ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">No payments in this period.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('billing.manage');
$title = 'Invoices';
$active = 'invoices';

$type = input('type');
$status = input('status');
$s = input('q');
$w = ['1=1'];
$p = [];
if (in_array($type, ['room', 'restaurant'], true)) { $w[] = 'i.invoice_type=?'; $p[] = $type; }
if (in_array($status, ['unpaid', 'partial', 'paid', 'void'], true)) { $w[] = 'i.status=?'; $p[] = $status; }
if ($s !== '') { $w[] = '(i.invoice_no LIKE ? OR i.bill_to LIKE ?)'; $p[] = "%$s%"; $p[] = "%$s%"; }
$rows = q_all('SELECT i.*, b.booking_ref, o.order_no FROM invoices i LEFT JOIN bookings b ON b.id=i.booking_id LEFT JOIN orders o ON o.id=i.order_id WHERE ' . implode(' AND ', $w) . ' ORDER BY i.issued_at DESC LIMIT 300', $p);
$out = q_row("SELECT COALESCE(SUM(total-amount_paid),0) due, COUNT(*) n FROM invoices WHERE status IN ('unpaid','partial')");

require __DIR__ . '/../includes/admin_header.php';
?>
<?php if ($out['n']): ?><div class="alert alert-warning"><?= (int) $out['n'] ?> invoice(s) outstanding — total due <b><?= money($out['due']) ?></b>.</div><?php endif; ?>
<div class="card">
  <form class="filters">
    <div class="field"><label>Search</label><input name="q" value="<?= e($s) ?>" placeholder="Invoice no, bill to"></div>
    <div class="field"><label>Type</label><select name="type"><option value="">All</option><?= options(['room' => 'Room / folio', 'restaurant' => 'Restaurant'], $type) ?></select></div>
    <div class="field"><label>Status</label><select name="status"><option value="">All</option><?= options(['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'void' => 'Void'], $status) ?></select></div>
    <button class="btn btn-ghost">Filter</button>
  </form>
  <div class="table-wrap"><table class="table"><thead><tr><th>Invoice</th><th>Date</th><th>Type</th><th>Bill to</th><th>Ref</th><th class="num">Total</th><th class="num">Paid</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="invoice.php?id=<?= $r['id'] ?>"><b><?= e($r['invoice_no']) ?></b></a></td><td><?= fdate($r['issued_at']) ?></td><td><?= e(label($r['invoice_type'])) ?></td><td><?= e($r['bill_to']) ?></td>
    <td class="small"><?= $r['booking_ref'] ? '<a href="booking_view.php?id=' . $r['booking_id'] . '">' . e($r['booking_ref']) . '</a>' : '' ?> <?= $r['order_no'] ? '<a href="orders.php?id=' . $r['order_id'] . '">' . e($r['order_no']) . '</a>' : '' ?></td>
    <td class="num"><?= money($r['total']) ?></td><td class="num"><?= money($r['amount_paid']) ?></td><td><?= badge($r['status']) ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">No invoices.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

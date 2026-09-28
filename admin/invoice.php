<?php
/** Printable invoice (?id=) or live pro-forma folio for a booking (?booking=). */
require __DIR__ . '/../includes/bootstrap.php';
require_perm(['billing.manage', 'frontdesk.manage', 'bookings.manage']);
$active = 'invoices';

$id = input_int('id');
$bookingId = input_int('booking');

if (is_post() && input('action') === 'void' && $id) {
    if (!can('billing.manage')) { flash('error', 'Permission denied.'); redirect('admin/invoice.php?id=' . $id); }
    q("UPDATE invoices SET status='void' WHERE id=? AND amount_paid = 0", [$id]);
    log_activity('billing', "Voided invoice #$id");
    flash('success', 'Invoice voided (only unpaid invoices can be voided).');
    redirect('admin/invoice.php?id=' . $id);
}

if ($id) {
    $inv = q_row('SELECT * FROM invoices WHERE id=?', [$id]);
    if (!$inv) { flash('error', 'Invoice not found.'); redirect('admin/invoices.php'); }
    $items = q_all('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id', [$id]);
    $payments = $inv['invoice_type'] === 'room'
        ? q_all('SELECT * FROM payments WHERE invoice_id=? OR booking_id=? ORDER BY paid_at', [$id, $inv['booking_id'] ?? 0])
        : q_all('SELECT * FROM payments WHERE invoice_id=? OR order_id=? ORDER BY paid_at', [$id, $inv['order_id'] ?? 0]);
    $booking = $inv['booking_id'] ? get_booking((int) $inv['booking_id']) : null;
    $title = 'Invoice ' . $inv['invoice_no'];
    $docTitle = 'TAX INVOICE';
} elseif ($bookingId && ($booking = get_booking($bookingId))) {
    $f = folio_summary($bookingId);
    $items = array_map(fn($c) => ['description' => $c['description'], 'qty' => 1, 'unit_price' => $c['amount'], 'amount' => $c['amount']], $f['charges']);
    $payments = $f['payments'];
    $inv = ['invoice_no' => 'PRO-FORMA', 'issued_at' => date('Y-m-d H:i:s'), 'bill_to' => $booking['guest_name'], 'subtotal' => $f['room'] + $f['extras'],
            'discount' => 0, 'service_charge' => 0, 'tax' => $f['room_tax'], 'total' => $f['posted_total'], 'amount_paid' => $f['paid'],
            'status' => 'proforma', 'invoice_type' => 'room', 'order_id' => null];
    $title = 'Folio ' . $booking['booking_ref'];
    $docTitle = 'GUEST FOLIO (PRO-FORMA)';
} else {
    flash('error', 'Nothing to show.');
    redirect('admin/invoices.php');
}
$balance = round($inv['total'] - $inv['amount_paid'], 2);

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="btn-row no-print" style="margin-bottom:14px;justify-content:center">
  <button class="btn" onclick="window.print()">🖨 Print / Save PDF</button>
  <?php if ($booking): ?><a class="btn btn-ghost" href="booking_view.php?id=<?= $booking['id'] ?>">Open booking</a><?php endif; ?>
  <?php if ($id && $inv['status'] !== 'void' && (float) $inv['amount_paid'] == 0 && can('billing.manage')): ?>
    <form method="post" class="inline-form" data-confirm="Void this invoice?"><?= csrf_field() ?><input type="hidden" name="action" value="void"><button class="btn btn-danger">Void</button></form>
  <?php endif; ?>
</div>
<div class="invoice">
  <div class="inv-head">
    <div><h1><?= e(setting('villa_name')) ?></h1><div class="muted small"><?= e(setting('villa_address')) ?><br><?= e(setting('villa_phone')) ?> · <?= e(setting('villa_email')) ?><br><?= e(setting('website')) ?></div></div>
    <div class="right"><h2 style="margin:0"><?= e($docTitle) ?></h2><div><b><?= e($inv['invoice_no']) ?></b></div><div class="small muted"><?= fdatetime($inv['issued_at']) ?></div>
      <div style="margin-top:6px"><?= $inv['status'] === 'proforma' ? '<span class="badge badge-blue">Pro-forma</span>' : badge($inv['status']) ?></div></div>
  </div>
  <div class="grid g2" style="margin-bottom:18px">
    <div><div class="small muted">BILL TO</div><b><?= e($inv['bill_to']) ?></b>
      <?php if ($booking): ?><div class="small"><?= e($booking['email']) ?><br><?= e($booking['phone']) ?><br><?= e($booking['country']) ?></div><?php endif; ?></div>
    <?php if ($booking): ?>
    <div class="small"><div class="muted">STAY</div>Booking <b><?= e($booking['booking_ref']) ?></b><?= $booking['external_ref'] ? ' (' . e($booking['source_name']) . ' ' . e($booking['external_ref']) . ')' : '' ?><br>
      <?= e($booking['room_type']) ?>, room <?= e($booking['room_number'] ?: '—') ?><br><?= fdate($booking['check_in']) ?> → <?= fdate($booking['check_out']) ?> · <?= nights_between($booking['check_in'], $booking['check_out']) ?> night(s)</div>
    <?php endif; ?>
  </div>
  <table class="table">
    <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Amount</th></tr></thead>
    <tbody><?php foreach ($items as $it): ?><tr><td><?= e($it['description']) ?></td><td class="num"><?= rtrim(rtrim(number_format((float) $it['qty'], 2), '0'), '.') ?></td><td class="num"><?= money($it['unit_price']) ?></td><td class="num"><?= money($it['amount']) ?></td></tr><?php endforeach; ?></tbody>
  </table>
  <div style="display:flex;justify-content:flex-end;margin-top:14px"><div class="total-box" style="min-width:300px">
    <div class="row"><span>Subtotal</span><span><?= money($inv['subtotal']) ?></span></div>
    <?php if ($inv['discount'] > 0): ?><div class="row"><span>Discount</span><span>− <?= money($inv['discount']) ?></span></div><?php endif; ?>
    <?php if ($inv['service_charge'] > 0): ?><div class="row"><span>Service charge</span><span><?= money($inv['service_charge']) ?></span></div><?php endif; ?>
    <div class="row"><span><?= $inv['invoice_type'] === 'room' ? 'Room tax (' . e(setting('room_tax_rate')) . '% on room charges)' : 'Tax' ?></span><span><?= money($inv['tax']) ?></span></div>
    <div class="row grand"><span>Total</span><span><?= money($inv['total']) ?></span></div>
    <div class="row"><span>Paid</span><span><?= money($inv['amount_paid']) ?></span></div>
    <div class="row" style="font-weight:700"><span><?= $balance < 0 ? 'Credit / refund due' : 'Balance due' ?></span><span><?= money(abs($balance)) ?></span></div>
  </div></div>
  <?php if ($payments): ?>
    <h3 style="margin-top:18px">Payments</h3>
    <table class="table"><tbody><?php foreach ($payments as $p): ?><tr><td><?= fdatetime($p['paid_at']) ?></td><td><?= e(label($p['method'])) ?></td><td><?= e($p['reference']) ?></td><td class="num"><?= money($p['amount']) ?></td></tr><?php endforeach; ?></tbody></table>
  <?php endif; ?>
  <p class="small muted" style="margin-top:24px;text-align:center"><?= e(setting('invoice_footer')) ?></p>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

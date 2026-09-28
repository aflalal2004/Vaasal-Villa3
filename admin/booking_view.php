<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm(['bookings.manage', 'frontdesk.manage', 'billing.manage']);
$active = 'bookings';

$id = input_int('id');
$b = get_booking($id);
if (!$b) { flash('error', 'Booking not found.'); redirect('admin/bookings.php'); }

if (is_post()) {
    $action = input('action');
    $need = [
        'confirm' => 'bookings.manage', 'cancel' => 'bookings.manage', 'no_show' => 'bookings.manage',
        'checkin' => 'frontdesk.manage', 'checkout' => 'frontdesk.manage', 'night_audit' => 'frontdesk.manage',
        'charge' => ['billing.manage', 'frontdesk.manage'], 'payment' => ['billing.manage', 'frontdesk.manage'], 'refund' => 'billing.manage',
        'issue_card' => 'keycards.manage', 'card_status' => 'keycards.manage',
    ];
    try {
        if (!isset($need[$action]) || !can_any((array) $need[$action])) throw new RuntimeException('You are not allowed to do that.');
        switch ($action) {
            case 'confirm':
                if ($b['status'] !== 'pending') throw new RuntimeException('Only pending bookings can be confirmed.');
                q("UPDATE bookings SET status='confirmed' WHERE id=?", [$id]);
                log_activity('booking', "Confirmed {$b['booking_ref']}");
                flash('success', 'Booking confirmed.');
                break;
            case 'cancel':
            case 'no_show':
                cancel_booking($id, $action === 'cancel' ? 'cancelled' : 'no_show');
                flash('success', 'Booking marked as ' . label($action === 'cancel' ? 'cancelled' : 'no_show') . '.');
                break;
            case 'checkin':
                check_in_booking($id, input_int('room_id'), input_int('cards', 1));
                flash('success', 'Guest checked in and key card(s) issued.');
                break;
            case 'night_audit':
                $n = post_room_charges($id);
                flash('success', $n ? "$n night(s) of room charges posted." : 'Room charges are already up to date.');
                break;
            case 'charge':
                if (!in_array($b['status'], ['checked_in', 'confirmed'], true)) throw new RuntimeException('Charges can only be posted to active bookings.');
                $amt = input_float('amount');
                $types = ['minibar', 'laundry', 'spa', 'transport', 'tour', 'other'];
                if ($amt == 0 || !in_array(input('charge_type'), $types, true) || input('description') === '') throw new RuntimeException('Enter type, description and a non-zero amount.');
                q('INSERT INTO folio_charges (booking_id, charge_type, description, amount, posted_by) VALUES (?,?,?,?,?)', [$id, input('charge_type'), input('description'), $amt, uid()]);
                refresh_booking_payment_status($id);
                log_activity('billing', 'Posted ' . input('charge_type') . ' charge ' . money($amt) . " to {$b['booking_ref']}");
                flash('success', 'Charge posted to folio.');
                break;
            case 'payment':
                $method = input('method');
                if (!in_array($method, ['cash', 'card', 'bank_transfer', 'online', 'voucher'], true)) throw new RuntimeException('Choose a payment method.');
                add_booking_payment($id, input_float('amount'), $method, input('reference') ?: null);
                flash('success', 'Payment recorded.');
                break;
            case 'refund':
                $method = in_array(input('method'), ['cash', 'card', 'bank_transfer'], true) ? input('method') : 'cash';
                refund_booking_credit($id, input_float('amount'), $method, input('reference') ?: null);
                flash('success', 'Refund recorded.');
                break;
            case 'checkout':
                $inv = check_out_booking($id, !empty($_POST['allow_balance']));
                flash('success', 'Guest checked out. Invoice generated, key cards deactivated, housekeeping notified.');
                redirect('admin/invoice.php?id=' . $inv);
            case 'issue_card':
                if ($b['status'] !== 'checked_in' || !$b['room_id']) throw new RuntimeException('Key cards can only be issued to checked-in guests.');
                $card = issue_key_card($id, (int) $b['room_id'], $b['check_out'] . ' ' . setting('check_out_time', '11:00') . ':00');
                flash('success', "Key card $card issued.");
                break;
            case 'card_status':
                $st = input('status');
                if (!in_array($st, ['lost', 'deactivated', 'returned'], true)) throw new RuntimeException('Invalid status.');
                q("UPDATE key_cards SET status=?, closed_at=NOW() WHERE id=? AND booking_id=? AND status='active'", [$st, input_int('card_id'), $id]);
                log_activity('keycard', "Key card #" . input_int('card_id') . " marked $st");
                flash('success', 'Key card updated.');
                break;
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/booking_view.php?id=' . $id);
}

$title = 'Booking ' . $b['booking_ref'];
$f = folio_summary($id);
$nights = nights_between($b['check_in'], $b['check_out']);
$cards = q_all('SELECT k.*, r.room_number, u.full_name issuer FROM key_cards k JOIN rooms r ON r.id=k.room_id LEFT JOIN users u ON u.id=k.issued_by WHERE k.booking_id=? ORDER BY k.id DESC', [$id]);
$orders = q_all('SELECT * FROM orders WHERE booking_id = ? ORDER BY created_at DESC', [$id]);
$invoices = q_all('SELECT * FROM invoices WHERE booking_id = ? ORDER BY id DESC', [$id]);
$freeRooms = in_array($b['status'], ['pending', 'confirmed'], true) ? free_rooms((int) $b['room_type_id'], $b['check_in'], $b['check_out'], $id) : [];

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="card">
  <div class="card-head">
    <div><h2 style="margin-bottom:4px"><?= e($b['booking_ref']) ?> · <?= e($b['guest_name']) ?> <?= $b['vip'] ? '<span class="badge badge-purple">VIP</span>' : '' ?></h2>
      <?= badge($b['status']) ?> <?= badge($b['payment_status']) ?> <span class="muted small">via <?= e($b['source_name']) ?><?= $b['external_ref'] ? ' · ' . e($b['external_ref']) : '' ?></span></div>
    <div class="btn-row">
      <?php if (in_array($b['status'], ['pending', 'confirmed'], true) && can('bookings.manage')): ?>
        <a class="btn btn-ghost btn-sm" href="booking_form.php?id=<?= $id ?>">Edit</a>
        <?php if ($b['status'] === 'pending'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><button class="btn btn-sm">Confirm</button></form>
        <?php endif; ?>
        <form method="post" class="inline-form" data-confirm="Mark this booking as no-show?"><?= csrf_field() ?><input type="hidden" name="action" value="no_show"><button class="btn btn-sm btn-warn">No-show</button></form>
        <form method="post" class="inline-form" data-confirm="Cancel this booking?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-sm btn-danger">Cancel</button></form>
      <?php endif; ?>
      <a class="btn btn-ghost btn-sm" href="invoice.php?booking=<?= $id ?>">Folio / Pro-forma</a>
    </div>
  </div>
  <div class="grid g3">
    <dl class="dl">
      <dt>Room type</dt><dd><?= e($b['room_type']) ?></dd>
      <dt>Room</dt><dd><?= $b['room_number'] ? e($b['room_number']) : '<span class="muted">Not assigned</span>' ?></dd>
      <dt>Guests</dt><dd><?= (int) $b['adults'] ?> adult(s), <?= (int) $b['children'] ?> child(ren)</dd>
    </dl>
    <dl class="dl">
      <dt>Check-in</dt><dd><?= fdate($b['check_in'], 'D d M Y') ?><?= $b['checked_in_at'] ? ' <span class="muted small">(' . fdatetime($b['checked_in_at']) . ')</span>' : '' ?></dd>
      <dt>Check-out</dt><dd><?= fdate($b['check_out'], 'D d M Y') ?><?= $b['checked_out_at'] ? ' <span class="muted small">(' . fdatetime($b['checked_out_at']) . ')</span>' : '' ?></dd>
      <dt>Nights × rate</dt><dd><?= $nights ?> × <?= money($b['rate_per_night']) ?> = <b><?= money($b['room_total']) ?></b></dd>
    </dl>
    <dl class="dl">
      <dt>Email</dt><dd><?= e($b['email'] ?: '—') ?></dd>
      <dt>Phone</dt><dd><?= e($b['phone'] ?: '—') ?></dd>
      <dt>Requests</dt><dd><?= e($b['special_requests'] ?: '—') ?></dd>
    </dl>
  </div>
</div>

<?php if (in_array($b['status'], ['pending', 'confirmed'], true) && can('frontdesk.manage')): ?>
<div class="card" id="checkin">
  <h2>Check-in</h2>
  <?php if ($b['check_in'] > date('Y-m-d')): ?>
    <p class="muted">Arrival is on <?= fdate($b['check_in']) ?>. Check-in becomes available on that day.</p>
  <?php else: ?>
  <form method="post" class="filters"><?= csrf_field() ?><input type="hidden" name="action" value="checkin">
    <div class="field"><label>Room</label><select name="room_id" required>
      <?php foreach ($freeRooms as $r): ?>
        <option value="<?= $r['id'] ?>" <?= (int) $b['room_id'] === (int) $r['id'] ? 'selected' : '' ?> <?= $r['status'] !== 'available' ? 'disabled' : '' ?>>
          <?= e($r['room_number']) ?> — <?= e(label($r['status'])) ?> / <?= e(label($r['housekeeping_status'])) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="field"><label>Key cards</label><input type="number" name="cards" value="<?= min(4, max(1, (int) $b['adults'])) ?>" min="1" max="4"></div>
    <button class="btn">Check in &amp; issue key cards</button>
  </form>
  <?php if (!$freeRooms): ?><p class="alert alert-warning">No free room of this type. Check room statuses or move a booking.</p><?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid g-2-1">
  <div class="card">
    <div class="card-head"><h2>Guest folio</h2>
      <?php if ($b['status'] === 'checked_in' && can('frontdesk.manage')): ?>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="night_audit"><button class="btn btn-sm btn-ghost">Post room charges to date</button></form>
      <?php endif; ?>
    </div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>By</th><th class="num">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($f['charges'] as $c): ?>
        <tr><td class="nowrap"><?= fdatetime($c['posted_at']) ?></td><td><?= e(label($c['charge_type'])) ?></td>
        <td><?= e($c['description']) ?><?php if ($c['order_id']): ?> <a class="small" href="orders.php?id=<?= $c['order_id'] ?>">view order</a><?php endif; ?></td>
        <td class="small muted"><?= e($c['posted_by_name'] ?? 'System') ?></td><td class="num"><?= money($c['amount']) ?></td></tr>
      <?php endforeach; if (!$f['charges']): ?><tr><td colspan="5" class="empty">No charges posted yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
    <?php if (in_array($b['status'], ['checked_in', 'confirmed'], true) && can_any(['billing.manage', 'frontdesk.manage'])): ?>
    <form method="post" class="filters" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="action" value="charge">
      <div class="field"><label>Charge type</label><select name="charge_type"><?= options(['minibar' => 'Minibar', 'laundry' => 'Laundry', 'spa' => 'Spa', 'transport' => 'Transport', 'tour' => 'Tour / Excursion', 'other' => 'Other']) ?></select></div>
      <div class="field" style="flex:1"><label>Description</label><input name="description" required></div>
      <div class="field"><label>Amount</label><input type="number" step="0.01" name="amount" required></div>
      <button class="btn btn-ghost">Post charge</button>
    </form>
    <?php endif; ?>

    <h3 style="margin-top:20px">Payments</h3>
    <table class="table"><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Received by</th><th class="num">Amount</th></tr></thead><tbody>
    <?php foreach ($f['payments'] as $p): ?>
      <tr><td><?= fdatetime($p['paid_at']) ?></td><td><?= e(label($p['method'])) ?></td><td><?= e($p['reference']) ?></td><td class="small muted"><?= e($p['received_by_name'] ?? 'Online') ?></td><td class="num"><?= money($p['amount']) ?></td></tr>
    <?php endforeach; if (!$f['payments']): ?><tr><td colspan="5" class="empty">No payments yet.</td></tr><?php endif; ?>
    </tbody></table>
  </div>

  <div>
    <div class="card">
      <h2>Balance</h2>
      <div class="total-box">
        <div class="row"><span>Room charges posted</span><span><?= money($f['room']) ?></span></div>
        <div class="row"><span>Room tax (<?= e(setting('room_tax_rate')) ?>%)</span><span><?= money($f['room_tax']) ?></span></div>
        <div class="row"><span>Extras / restaurant</span><span><?= money($f['extras']) ?></span></div>
        <div class="row"><b>Posted total</b><b><?= money($f['posted_total']) ?></b></div>
        <div class="row"><span>Paid</span><span>− <?= money($f['paid']) ?></span></div>
        <div class="row grand"><span><?= $f['balance'] < 0 ? 'Guest credit' : 'Balance' ?></span><span><?= money(abs($f['balance'])) ?></span></div>
        <?php if ($b['status'] !== 'checked_out'): ?>
          <div class="row small muted"><span>Projected full stay</span><span><?= money($f['projected_total']) ?> (due <?= money($f['projected_balance']) ?>)</span></div>
        <?php endif; ?>
      </div>
      <?php if (!in_array($b['status'], ['cancelled', 'no_show'], true) && can_any(['billing.manage', 'frontdesk.manage'])): ?>
      <form method="post" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="action" value="payment">
        <div class="form-grid">
          <div class="field"><label>Amount</label><input type="number" step="0.01" min="0.01" name="amount" value="<?= e(number_format(max(0, $b['status'] === 'checked_out' ? $f['balance'] : $f['projected_balance']), 2, '.', '')) ?>" required></div>
          <div class="field"><label>Method</label><select name="method"><?= options(['cash' => 'Cash', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'online' => 'Online', 'voucher' => 'Voucher']) ?></select></div>
        </div>
        <div class="field"><label>Reference</label><input name="reference" placeholder="Card last 4, receipt no…"></div>
        <button class="btn" style="width:100%;justify-content:center">Record payment</button>
      </form>
      <?php endif; ?>
      <?php if ($b['status'] === 'checked_out' && $f['balance'] < -0.009 && can('billing.manage')): ?>
      <form method="post" style="margin-top:14px" data-confirm="Record this refund to the guest?"><?= csrf_field() ?><input type="hidden" name="action" value="refund">
        <div class="alert alert-warning" style="margin-bottom:10px">Guest overpaid by <?= money(-$f['balance']) ?> (e.g. early departure). Refund the difference:</div>
        <div class="form-grid">
          <div class="field"><label>Refund amount</label><input type="number" step="0.01" min="0.01" name="amount" value="<?= e(number_format(-$f['balance'], 2, '.', '')) ?>" required></div>
          <div class="field"><label>Method</label><select name="method"><?= options(['cash' => 'Cash', 'card' => 'Card reversal', 'bank_transfer' => 'Bank transfer']) ?></select></div>
        </div>
        <button class="btn btn-warn" style="width:100%;justify-content:center">Record refund</button>
      </form>
      <?php endif; ?>
    </div>

    <?php if ($b['status'] === 'checked_in' && can('frontdesk.manage')): ?>
    <div class="card" id="checkout">
      <h2>Check-out</h2>
      <p class="small muted">Posts remaining room nights, generates the final invoice, deactivates key cards, and sends the room to housekeeping.</p>
      <form method="post" data-confirm="Check out this guest now?"><?= csrf_field() ?><input type="hidden" name="action" value="checkout">
        <label class="check"><input type="checkbox" name="allow_balance" value="1"> Allow outstanding balance (city ledger / tour operator bill)</label>
        <button class="btn btn-warn" style="width:100%;justify-content:center;margin-top:10px">Check out guest</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h2>Key cards</h2>
        <?php if ($b['status'] === 'checked_in' && can('keycards.manage')): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="issue_card"><button class="btn btn-sm btn-ghost">+ Issue card</button></form>
        <?php endif; ?></div>
      <?php foreach ($cards as $k): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--line)">
          <div><code><?= e($k['card_number']) ?></code> <?= badge($k['status']) ?><div class="small muted">valid to <?= fdatetime($k['valid_until']) ?></div></div>
          <?php if ($k['status'] === 'active' && can('keycards.manage')): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="card_status"><input type="hidden" name="card_id" value="<?= $k['id'] ?>">
              <button class="btn btn-sm btn-ghost" name="status" value="lost" data-confirm="Mark card as lost and block it?">Lost</button>
              <button class="btn btn-sm btn-ghost" name="status" value="deactivated">Deactivate</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; if (!$cards): ?><p class="muted">No key cards issued.</p><?php endif; ?>
    </div>

    <div class="card">
      <h2>Restaurant &amp; invoices</h2>
      <?php foreach ($orders as $o): ?>
        <div class="small" style="padding:3px 0"><a href="orders.php?id=<?= $o['id'] ?>"><?= e($o['order_no']) ?></a> · <?= e(label($o['order_type'])) ?> · <?= money($o['total']) ?> <?= badge($o['payment_status']) ?></div>
      <?php endforeach; ?>
      <?php foreach ($invoices as $inv): ?>
        <div class="small" style="padding:3px 0">📄 <a href="invoice.php?id=<?= $inv['id'] ?>"><?= e($inv['invoice_no']) ?></a> · <?= e(label($inv['invoice_type'])) ?> · <?= money($inv['total']) ?> <?= badge($inv['status']) ?></div>
      <?php endforeach; if (!$orders && !$invoices): ?><p class="muted">None yet.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('keycards.manage');
$title = 'Key Card Control';
$active = 'keycards';

if (is_post()) {
    $action = input('action');
    try {
        if ($action === 'status') {
            $st = input('status');
            if (!in_array($st, ['lost', 'deactivated', 'returned'], true)) throw new RuntimeException('Invalid status.');
            q("UPDATE key_cards SET status=?, closed_at=NOW() WHERE id=? AND status='active'", [$st, input_int('id')]);
            log_activity('keycard', 'Key card #' . input_int('id') . " set to $st");
            flash('success', 'Key card updated.');
        } elseif ($action === 'issue') {
            $b = q_row("SELECT * FROM bookings WHERE id=? AND status='checked_in'", [input_int('booking_id')]);
            if (!$b) throw new RuntimeException('Select an in-house booking.');
            $card = issue_key_card((int) $b['id'], (int) $b['room_id'], $b['check_out'] . ' ' . setting('check_out_time', '11:00') . ':00');
            flash('success', "Card $card encoded for booking {$b['booking_ref']}.");
        } elseif ($action === 'expire') {
            $n = q("UPDATE key_cards SET status='deactivated', closed_at=NOW() WHERE status='active' AND valid_until < NOW()")->rowCount();
            log_activity('keycard', "Auto-deactivated $n expired card(s)");
            flash('success', "$n expired card(s) deactivated.");
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/keycards.php');
}

// Card validation lookup (simulates a door lock / encoder check)
$lookup = input('card');
$check = null;
if ($lookup !== '') {
    $check = q_row('SELECT k.*, r.room_number, b.booking_ref, b.status bstatus FROM key_cards k JOIN rooms r ON r.id=k.room_id JOIN bookings b ON b.id=k.booking_id WHERE k.card_number=?', [$lookup]);
}
$status = input('status', 'active');
$rows = q_all('SELECT k.*, r.room_number, b.booking_ref, b.id bid, CONCAT(g.first_name," ",g.last_name) guest, u.full_name issuer
               FROM key_cards k JOIN rooms r ON r.id=k.room_id JOIN bookings b ON b.id=k.booking_id JOIN guests g ON g.id=b.guest_id
               LEFT JOIN users u ON u.id=k.issued_by ' . ($status !== 'all' ? 'WHERE k.status = ?' : '') . ' ORDER BY k.issued_at DESC LIMIT 300',
               $status !== 'all' ? [$status] : []);
$inhouse = in_house_bookings();

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="grid g2">
  <div class="card">
    <h2>Encode new key card</h2>
    <form method="post" class="filters"><?= csrf_field() ?><input type="hidden" name="action" value="issue">
      <div class="field" style="flex:1"><label>In-house booking</label><select name="booking_id" required><option value="">Select…</option>
        <?= options(array_map(fn($b) => [$b['id'], $b['room_number'] . ' · ' . $b['guest_name'] . ' (' . $b['booking_ref'] . ')'], $inhouse)) ?></select></div>
      <button class="btn">Encode card</button>
    </form>
  </div>
  <div class="card">
    <h2>Validate card (door lock check)</h2>
    <form class="filters"><div class="field" style="flex:1"><label>Card number</label><input name="card" value="<?= e($lookup) ?>" placeholder="KC-G102-0001"></div><button class="btn btn-ghost">Check</button></form>
    <?php if ($lookup !== ''):
        $valid = $check && $check['status'] === 'active' && strtotime($check['valid_from']) <= time() && strtotime($check['valid_until']) >= time(); ?>
      <div class="alert alert-<?= $valid ? 'success' : 'error' ?>" style="margin:0">
        <?php if (!$check): ?>✖ Unknown card — ACCESS DENIED
        <?php else: ?><?= $valid ? '✔ ACCESS GRANTED' : '✖ ACCESS DENIED' ?> — room <b><?= e($check['room_number']) ?></b>, <?= e($check['booking_ref']) ?>, status <?= e($check['status']) ?>, valid until <?= fdatetime($check['valid_until']) ?><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<div class="card">
  <div class="card-head">
    <div class="tabs" style="margin:0"><?php foreach (['active', 'returned', 'lost', 'deactivated', 'all'] as $s): ?><a class="<?= $status === $s ? 'active' : '' ?>" href="?status=<?= $s ?>"><?= label($s) ?></a><?php endforeach; ?></div>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="expire"><button class="btn btn-ghost btn-sm">Deactivate expired cards</button></form>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Card</th><th>Room</th><th>Guest / booking</th><th>Valid</th><th>Issued</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $k): ?>
      <tr><td><code><?= e($k['card_number']) ?></code></td><td><b><?= e($k['room_number']) ?></b></td>
        <td><?= e($k['guest']) ?> · <a href="booking_view.php?id=<?= $k['bid'] ?>"><?= e($k['booking_ref']) ?></a></td>
        <td class="small"><?= fdatetime($k['valid_from']) ?><br>→ <?= fdatetime($k['valid_until']) ?><?= $k['status'] === 'active' && strtotime($k['valid_until']) < time() ? ' <span class="badge badge-red">expired</span>' : '' ?></td>
        <td class="small"><?= fdatetime($k['issued_at']) ?><br><span class="muted"><?= e($k['issuer'] ?? '') ?></span></td><td><?= badge($k['status']) ?></td>
        <td class="right"><?php if ($k['status'] === 'active'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $k['id'] ?>">
            <button class="btn btn-sm btn-ghost" name="status" value="returned">Returned</button>
            <button class="btn btn-sm btn-ghost" name="status" value="lost" data-confirm="Block this card as lost?">Lost</button>
            <button class="btn btn-sm btn-ghost" name="status" value="deactivated">Deactivate</button></form><?php endif; ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">No key cards.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

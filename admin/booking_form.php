<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('bookings.manage');
$active = 'bookings';

$id = input_int('id');
$booking = $id ? get_booking($id) : null;
if ($id && !$booking) { flash('error', 'Booking not found.'); redirect('admin/bookings.php'); }
if ($booking && !in_array($booking['status'], ['pending', 'confirmed'], true)) {
    flash('warning', 'Only pending or confirmed bookings can be edited.');
    redirect('admin/booking_view.php?id=' . $id);
}
$title = $booking ? 'Edit booking ' . $booking['booking_ref'] : 'New booking';

$types = q_all('SELECT * FROM room_types WHERE is_active = 1 ORDER BY base_rate');
$sources = q_all('SELECT id, name FROM booking_sources WHERE is_active = 1 ORDER BY id');
$guests = q_all('SELECT id, first_name, last_name, email FROM guests ORDER BY first_name, last_name');
$rooms = q_all('SELECT r.id, r.room_number, rt.name FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id ORDER BY r.room_number');

$f = $booking ?: [
    'guest_id' => input_int('guest_id'), 'source_id' => 1, 'room_type_id' => input_int('room_type_id') ?: ($types[0]['id'] ?? 0), 'room_id' => '',
    'check_in' => input('check_in') ?: date('Y-m-d'), 'check_out' => input('check_out') ?: date('Y-m-d', strtotime('+1 day')),
    'adults' => 2, 'children' => 0, 'rate_per_night' => '', 'status' => 'confirmed', 'external_ref' => '', 'special_requests' => '',
];

if (is_post()) {
    $f = array_merge($f, array_map(fn($v) => is_string($v) ? trim($v) : $v, $_POST));
    try {
        if ($booking) {
            // ---- update existing booking
            $typeId = (int) $f['room_type_id'];
            $type = q_row('SELECT * FROM room_types WHERE id = ?', [$typeId]);
            if (!$type) throw new RuntimeException('Invalid room type.');
            if (!valid_date($f['check_in']) || !valid_date($f['check_out']) || $f['check_out'] <= $f['check_in']) throw new RuntimeException('Invalid dates.');
            if ((int) $f['adults'] > $type['max_adults'] || (int) $f['children'] > $type['max_children']) throw new RuntimeException('Too many guests for this room type.');
            if (type_availability($typeId, $f['check_in'], $f['check_out'], $id) < 1) throw new RuntimeException('No availability for the new dates / room type.');
            $roomId = $f['room_id'] !== '' ? (int) $f['room_id'] : null;
            if ($roomId && !in_array($roomId, array_map('intval', array_column(free_rooms($typeId, $f['check_in'], $f['check_out'], $id), 'id')), true)) {
                throw new RuntimeException('Selected room is not free for those dates (or is a different type).');
            }
            $rate = $f['rate_per_night'] !== '' ? (float) $f['rate_per_night'] : (float) $type['base_rate'];
            q('UPDATE bookings SET source_id=?, external_ref=?, room_type_id=?, room_id=?, check_in=?, check_out=?, adults=?, children=?, rate_per_night=?, room_total=?, status=?, special_requests=? WHERE id=?', [
                (int) $f['source_id'], $f['external_ref'] ?: null, $typeId, $roomId, $f['check_in'], $f['check_out'], (int) $f['adults'], (int) $f['children'],
                $rate, round($rate * nights_between($f['check_in'], $f['check_out']), 2), in_array($f['status'], ['pending', 'confirmed'], true) ? $f['status'] : 'confirmed',
                $f['special_requests'] ?: null, $id,
            ]);
            refresh_booking_payment_status($id);
            log_activity('booking', "Updated booking {$booking['booking_ref']}");
            flash('success', 'Booking updated.');
            redirect('admin/booking_view.php?id=' . $id);
        }
        // ---- new booking
        $guestId = (int) ($f['guest_id'] ?? 0);
        if (!$guestId) {
            if (($f['first_name'] ?? '') === '' || ($f['last_name'] ?? '') === '') throw new RuntimeException('Select an existing guest or enter the new guest\'s name.');
            if (($f['email'] ?? '') !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid email address.');
            $guestId = find_or_create_guest($f);
        }
        $res = create_booking([
            'guest_id' => $guestId, 'source_id' => (int) $f['source_id'], 'room_type_id' => (int) $f['room_type_id'],
            'room_id' => $f['room_id'] ?? '', 'check_in' => $f['check_in'], 'check_out' => $f['check_out'],
            'adults' => (int) $f['adults'], 'children' => (int) $f['children'], 'rate_per_night' => $f['rate_per_night'],
            'status' => in_array($f['status'], ['pending', 'confirmed'], true) ? $f['status'] : 'confirmed',
            'external_ref' => $f['external_ref'] ?: null, 'special_requests' => $f['special_requests'] ?: null,
        ]);
        flash('success', "Booking {$res['ref']} created.");
        redirect('admin/booking_view.php?id=' . $res['id']);
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
}

require __DIR__ . '/../includes/admin_header.php';
?>
<form method="post" data-booking-form>
  <?= csrf_field() ?>
  <div class="grid g2">
    <div class="card">
      <h2>Stay details</h2>
      <div class="form-grid">
        <div class="field"><label>Check-in</label><input type="date" name="check_in" value="<?= e($f['check_in']) ?>" required></div>
        <div class="field"><label>Check-out</label><input type="date" name="check_out" value="<?= e($f['check_out']) ?>" required></div>
        <div class="field"><label>Adults</label><input type="number" name="adults" min="1" max="10" value="<?= e($f['adults']) ?>"></div>
        <div class="field"><label>Children</label><input type="number" name="children" min="0" max="10" value="<?= e($f['children']) ?>"></div>
      </div>
      <div class="field"><label>Room type</label>
        <select name="room_type_id" required>
          <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" data-rate="<?= e($t['base_rate']) ?>" <?= (int) $f['room_type_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> — <?= money($t['base_rate']) ?>/night (max <?= $t['max_adults'] ?>+<?= $t['max_children'] ?>)</option><?php endforeach; ?>
        </select></div>
      <div class="form-grid">
        <div class="field"><label>Assign room (optional)</label>
          <select name="room_id"><option value="">— assign at check-in —</option><?= options(array_map(fn($r) => [$r['id'], $r['room_number'] . ' · ' . $r['name']], $rooms), $f['room_id']) ?></select></div>
        <div class="field"><label>Rate per night (blank = standard)</label><input type="number" step="0.01" min="0" name="rate_per_night" value="<?= e($f['rate_per_night'] ?? '') ?>"></div>
      </div>
      <div class="total-box"><b data-total>—</b></div>
    </div>
    <div class="card">
      <h2>Guest &amp; source</h2>
      <?php if ($booking): ?>
        <p>Guest: <b><?= e($booking['guest_name']) ?></b> <span class="muted">(<a href="guests.php?edit=<?= $booking['guest_id'] ?>">edit guest</a>)</span></p>
      <?php else: ?>
        <div class="field"><label>Existing guest</label>
          <select name="guest_id"><option value="">— new guest (fill below) —</option><?= options(array_map(fn($g) => [$g['id'], $g['first_name'] . ' ' . $g['last_name'] . ($g['email'] ? ' · ' . $g['email'] : '')], $guests), $f['guest_id']) ?></select></div>
        <div class="form-grid">
          <div class="field"><label>First name</label><input name="first_name" value="<?= e($f['first_name'] ?? '') ?>"></div>
          <div class="field"><label>Last name</label><input name="last_name" value="<?= e($f['last_name'] ?? '') ?>"></div>
          <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($f['email'] ?? '') ?>"></div>
          <div class="field"><label>Phone</label><input name="phone" value="<?= e($f['phone'] ?? '') ?>"></div>
          <div class="field"><label>Country</label><input name="country" value="<?= e($f['country'] ?? '') ?>"></div>
        </div>
      <?php endif; ?>
      <div class="form-grid">
        <div class="field"><label>Booking source</label><select name="source_id"><?= options(array_map(fn($s) => [$s['id'], $s['name']], $sources), $f['source_id']) ?></select></div>
        <div class="field"><label>External / OTA ref</label><input name="external_ref" value="<?= e($f['external_ref']) ?>"></div>
        <div class="field"><label>Status</label><select name="status"><?= options(['confirmed' => 'Confirmed', 'pending' => 'Pending'], $f['status']) ?></select></div>
      </div>
      <div class="field"><label>Special requests</label><textarea name="special_requests"><?= e($f['special_requests']) ?></textarea></div>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn"><?= $booking ? 'Save changes' : 'Create booking' ?></button>
    <a class="btn btn-ghost" href="<?= $booking ? 'booking_view.php?id=' . $id : 'bookings.php' ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

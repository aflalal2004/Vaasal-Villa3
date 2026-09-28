<?php
/** Public online booking: creates a confirmed booking (source = website) in the shared database. */
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Book your stay';
$page = 'rooms';

$types = q_all('SELECT * FROM room_types WHERE is_active = 1 ORDER BY base_rate');
$f = [
    'room_type_id' => input_int('room_type_id') ?: ($types[0]['id'] ?? 0),
    'check_in' => input('check_in') ?: date('Y-m-d'), 'check_out' => input('check_out') ?: date('Y-m-d', strtotime('+2 day')),
    'adults' => max(1, input_int('adults', 2)), 'children' => max(0, input_int('children')),
    'first_name' => input('first_name'), 'last_name' => input('last_name'), 'email' => input('email'), 'phone' => input('phone'),
    'country' => input('country'), 'special_requests' => input('special_requests'),
];
$error = null;

if (is_post() && input('recalc') === '') {
    csrf_check();
    try {
        if (input('website') !== '') throw new RuntimeException('Spam detected.'); // honeypot
        if ($f['first_name'] === '' || $f['last_name'] === '') throw new RuntimeException('Please enter your first and last name.');
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Please enter a valid email address.');
        if ($f['phone'] === '') throw new RuntimeException('Please enter a phone number so we can reach you.');
        if (empty($_POST['agree'])) throw new RuntimeException('Please accept the booking conditions.');
        if (nights_between($f['check_in'], $f['check_out']) > 30) throw new RuntimeException('For stays longer than 30 nights please contact us directly.');
        $sourceId = (int) q_val("SELECT id FROM booking_sources WHERE type = 'website' ORDER BY id LIMIT 1");
        $guestId = find_or_create_guest(array_map(fn($v) => is_string($v) ? mb_substr($v, 0, 160) : $v, $f));
        $res = create_booking([
            'guest_id' => $guestId, 'source_id' => $sourceId, 'room_type_id' => $f['room_type_id'], 'check_in' => $f['check_in'],
            'check_out' => $f['check_out'], 'adults' => $f['adults'], 'children' => $f['children'], 'status' => 'confirmed',
            'special_requests' => mb_substr($f['special_requests'], 0, 1000) ?: null, 'public' => true,
        ]);
        $_SESSION['last_booking'] = $res['ref'];
        redirect('book.php?done=1');
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

$done = input('done') && !empty($_SESSION['last_booking']) ? q_row(
    'SELECT b.*, rt.name type_name, g.first_name, g.email FROM bookings b JOIN room_types rt ON rt.id=b.room_type_id JOIN guests g ON g.id=b.guest_id WHERE b.booking_ref = ?',
    [$_SESSION['last_booking']]) : null;

$sel = null;
foreach ($types as $t) if ((int) $t['id'] === (int) $f['room_type_id']) $sel = $t;
$nights = valid_date($f['check_in']) && valid_date($f['check_out']) ? nights_between($f['check_in'], $f['check_out']) : 0;
$avail = ($sel && $nights > 0) ? type_availability((int) $sel['id'], $f['check_in'], $f['check_out']) : null;
$taxRate = (float) setting('room_tax_rate');

require __DIR__ . '/includes/site_header.php';
?>
<?php site_page_head($done ? 'Booking confirmed' : 'Book your stay', $done ? 'Thank you for choosing ' . e(setting('villa_name')) . '.' : 'Instant confirmation · Pay on arrival · Best rate when you book direct', $sel['image_url'] ?? setting('hero_poster_url')); ?>
<section class="section" style="padding-top:50px">
<div class="wrap">
<?php if ($done): ?>
  <div class="form center" style="max-width:720px;margin:0 auto">
    <p>Your booking reference</p>
    <div class="confirm-ref"><?= e($done['booking_ref']) ?></div>
    <p>Dear <?= e($done['first_name']) ?>, your <b><?= e($done['type_name']) ?></b> is reserved from <b><?= fdate($done['check_in'], 'D d M Y') ?></b> to <b><?= fdate($done['check_out'], 'D d M Y') ?></b>
      (<?= nights_between($done['check_in'], $done['check_out']) ?> nights). Room total <b><?= money($done['room_total']) ?></b> + <?= e(setting('room_tax_rate')) ?>% tax, payable at the villa.</p>
    <p class="small">A copy has been recorded for <?= e($done['email']) ?>. Keep your reference to view or cancel your booking online.</p>
    <p><a class="btn" href="<?= e(url('manage-booking.php?ref=' . urlencode($done['booking_ref']))) ?>">Manage booking</a> &nbsp; <a class="btn ghost" href="<?= e(url('index.php')) ?>">Back to home</a></p>
  </div>
<?php else: ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="grid" style="grid-template-columns:1.6fr 1fr;align-items:start" id="bookForm">
    <?= csrf_field() ?>
    <div class="form">
      <h3>1. Your stay</h3>
      <div class="row">
        <div><label>Check-in</label><input type="date" name="check_in" data-min-today value="<?= e($f['check_in']) ?>" required></div>
        <div><label>Check-out</label><input type="date" name="check_out" data-min-today value="<?= e($f['check_out']) ?>" required></div>
      </div>
      <div class="f"><label>Room type</label><select name="room_type_id" required>
        <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" <?= (int) $t['id'] === (int) $f['room_type_id'] ? 'selected' : '' ?>><?= e($t['name']) ?> — <?= money($t['base_rate']) ?>/night (max <?= $t['max_adults'] ?> adults, <?= $t['max_children'] ?> children)</option><?php endforeach; ?>
      </select></div>
      <div class="row">
        <div><label>Adults</label><select name="adults"><?= options([1 => '1', 2 => '2', 3 => '3', 4 => '4'], $f['adults']) ?></select></div>
        <div><label>Children</label><select name="children"><?= options([0 => '0', 1 => '1', 2 => '2', 3 => '3'], $f['children']) ?></select></div>
      </div>
      <p><button type="submit" name="recalc" value="1" formnovalidate class="btn ghost">Update price &amp; availability</button></p>
      <h3 style="margin-top:26px">2. Guest details</h3>
      <div class="row">
        <div><label>First name</label><input name="first_name" value="<?= e($f['first_name']) ?>" required></div>
        <div><label>Last name</label><input name="last_name" value="<?= e($f['last_name']) ?>" required></div>
      </div>
      <div class="row">
        <div><label>Email</label><input type="email" name="email" value="<?= e($f['email']) ?>" required></div>
        <div><label>Phone / WhatsApp</label><input name="phone" value="<?= e($f['phone']) ?>" required></div>
      </div>
      <div class="f"><label>Country</label><input name="country" value="<?= e($f['country']) ?>"></div>
      <div class="f"><label>Special requests</label><textarea name="special_requests" placeholder="Arrival time, airport transfer, dietary needs, celebrations…"><?= e($f['special_requests']) ?></textarea></div>
      <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
      <label style="text-transform:none;letter-spacing:0;font-size:.95rem;font-weight:400;display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="agree" value="1" style="width:auto;margin-top:5px" required> I agree to the booking conditions: payment on arrival, free cancellation up to 48 hours before check-in.</label>
      <p style="margin-top:20px"><button class="btn gold" style="padding:14px 34px">Confirm booking</button></p>
    </div>
    <aside class="summary">
      <h3>Your booking</h3>
      <?php if ($sel && $nights > 0): ?>
        <p><b><?= e($sel['name']) ?></b><br><span class="small"><?= fdate($f['check_in'], 'D d M') ?> → <?= fdate($f['check_out'], 'D d M Y') ?></span></p>
        <div class="r"><span><?= $nights ?> night(s) × <?= money($sel['base_rate']) ?></span><span><?= money($sel['base_rate'] * $nights) ?></span></div>
        <div class="r"><span>Tax (<?= e($taxRate) ?>%)</span><span><?= money($sel['base_rate'] * $nights * $taxRate / 100) ?></span></div>
        <div class="r t"><span>Total</span><span><?= money($sel['base_rate'] * $nights * (1 + $taxRate / 100)) ?></span></div>
        <p class="avail <?= $avail ? 'ok' : 'no' ?>" style="margin-top:12px"><?= $avail ? '✔ Available — ' . $avail . ' left' : '✖ Not available for these dates — try other dates or room types' ?></p>
      <?php else: ?><p>Select valid dates to see your price.</p><?php endif; ?>
      <p class="small" style="margin-top:14px">✔ Instant confirmation<br>✔ No prepayment needed<br>✔ Free cancellation up to 48h</p>
    </aside>
  </form>
<?php endif; ?>
</div>
</section>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

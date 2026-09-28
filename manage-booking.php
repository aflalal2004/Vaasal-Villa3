<?php
/** Guests look up (and cancel) their booking using reference + email. */
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Manage my booking';
$page = 'manage';

$ref = strtoupper(input('ref'));
$email = input('email');
$b = null;
$msg = null;
$err = null;

$_SESSION['lookup_fails'] = $_SESSION['lookup_fails'] ?? 0;
if ($ref !== '' && $email !== '') {
    if ($_SESSION['lookup_fails'] >= 10) {
        $err = 'Too many attempts. Please contact reception.';
    } else {
        $b = q_row('SELECT b.*, rt.name type_name, r.room_number, g.first_name, g.last_name FROM bookings b JOIN guests g ON g.id=b.guest_id
                    JOIN room_types rt ON rt.id=b.room_type_id LEFT JOIN rooms r ON r.id=b.room_id WHERE b.booking_ref=? AND g.email=?', [$ref, $email]);
        if (!$b) { $_SESSION['lookup_fails']++; $err = 'No booking matches that reference and email.'; }
    }
}
$canCancel = $b && in_array($b['status'], ['pending', 'confirmed'], true) && strtotime($b['check_in']) - strtotime(date('Y-m-d')) >= 2 * 86400;

if ($b && is_post() && input('action') === 'cancel') {
    csrf_check();
    if ($canCancel) {
        cancel_booking((int) $b['id']);
        $msg = 'Your booking has been cancelled. We hope to welcome you another time.';
        $b['status'] = 'cancelled';
        $canCancel = false;
    } else {
        $err = 'This booking can no longer be cancelled online. Please contact reception.';
    }
}
require __DIR__ . '/includes/site_header.php';
?>
<?php site_page_head('Manage my booking', 'Enter your booking reference and the email used when booking.', q_val("SELECT image_url FROM gallery_images WHERE is_active = 1 AND category = 'villa' ORDER BY sort_order DESC LIMIT 1")); ?>
<section class="section" style="padding-top:50px">
  <div class="wrap" style="max-width:760px">
    <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
    <form class="form" method="post" action="manage-booking.php">
      <?= csrf_field() ?>
      <div class="row"><div><label>Booking reference</label><input name="ref" value="<?= e($ref) ?>" placeholder="VV-100005" required></div>
        <div><label>Email</label><input type="email" name="email" value="<?= e($email) ?>" required></div></div>
      <button class="btn">Find booking</button>
    </form>
    <?php if ($b): ?>
      <div class="summary" style="margin-top:26px">
        <h3><?= e($b['booking_ref']) ?> — <?= e(label($b['status'])) ?></h3>
        <div class="r"><span>Guest</span><b><?= e($b['first_name'] . ' ' . $b['last_name']) ?></b></div>
        <div class="r"><span>Room</span><b><?= e($b['type_name']) ?><?= $b['room_number'] ? ' · ' . e($b['room_number']) : '' ?></b></div>
        <div class="r"><span>Dates</span><b><?= fdate($b['check_in'], 'D d M Y') ?> → <?= fdate($b['check_out'], 'D d M Y') ?></b></div>
        <div class="r"><span>Guests</span><b><?= (int) $b['adults'] ?> adult(s), <?= (int) $b['children'] ?> child(ren)</b></div>
        <div class="r"><span>Payment</span><b><?= e(label($b['payment_status'])) ?></b></div>
        <div class="r t"><span>Room total</span><span><?= money($b['room_total']) ?> <small>+ tax</small></span></div>
        <?php if ($canCancel): ?>
          <form method="post" onsubmit="return confirm('Cancel this booking?')" style="margin-top:14px"><?= csrf_field() ?>
            <input type="hidden" name="ref" value="<?= e($ref) ?>"><input type="hidden" name="email" value="<?= e($email) ?>"><input type="hidden" name="action" value="cancel">
            <button class="btn" style="background:#b91c1c">Cancel booking</button></form>
        <?php elseif (in_array($b['status'], ['pending', 'confirmed'], true)): ?>
          <p class="small">Online cancellation closes 48 hours before arrival. Please call <?= e(setting('villa_phone')) ?>.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

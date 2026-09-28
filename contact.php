<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Contact us';
$page = 'contact';
$f = ['name' => input('name'), 'email' => input('email'), 'phone' => input('phone'), 'subject' => input('subject'), 'message' => input('message')];
$ok = false;
$err = null;
if (is_post()) {
    csrf_check();
    if (input('website') !== '') {
        $ok = true; // silently drop bots
    } elseif ($f['name'] === '' || !filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['message']) < 5) {
        $err = 'Please enter your name, a valid email and a message.';
    } else {
        q('INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?,?,?,?,?)',
          [mb_substr($f['name'], 0, 120), $f['email'], mb_substr($f['phone'], 0, 40) ?: null, mb_substr($f['subject'], 0, 160) ?: null, mb_substr($f['message'], 0, 5000)]);
        $ok = true;
    }
}
require __DIR__ . '/includes/site_header.php';
?>
<?php site_page_head('Contact & reservations', 'Weddings, groups, tour operators, table reservations — we reply within 24 hours.', q_val("SELECT image_url FROM gallery_images WHERE is_active = 1 AND category = 'experiences' ORDER BY sort_order DESC LIMIT 1")); ?>
<section class="section" style="padding-top:50px">
  <div class="wrap split" style="align-items:start">
    <div>
      <?php if ($ok): ?><div class="alert alert-success">Thank you! Your message has been sent to our reservations team.</div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
      <form class="form" method="post"><?= csrf_field() ?>
        <div class="row"><div><label>Name</label><input name="name" value="<?= e($ok ? '' : $f['name']) ?>" required></div><div><label>Email</label><input type="email" name="email" value="<?= e($ok ? '' : $f['email']) ?>" required></div></div>
        <div class="row"><div><label>Phone</label><input name="phone" value="<?= e($ok ? '' : $f['phone']) ?>"></div><div><label>Subject</label><input name="subject" value="<?= e($ok ? '' : $f['subject']) ?>"></div></div>
        <div class="f"><label>Message</label><textarea name="message" required><?= e($ok ? '' : $f['message']) ?></textarea></div>
        <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
        <button class="btn">Send message</button>
      </form>
    </div>
    <div class="summary">
      <h3><?= e(setting('villa_name')) ?></h3>
      <p>📍 <?= e(setting('villa_address')) ?><br>📞 <?= e(setting('villa_phone')) ?><br>✉ <a href="mailto:<?= e(setting('villa_email')) ?>"><?= e(setting('villa_email')) ?></a><br>🌐 <?= e(setting('website')) ?></p>
      <p>Reception is open 24 hours.<br>Restaurant: 7:00 – 22:30 daily.</p>
      <p><a class="btn" href="<?= e(url('book.php')) ?>">Book online</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

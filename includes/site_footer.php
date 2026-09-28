<footer class="site-footer">
  <div class="wrap cols">
    <div><div class="logo"><span class="logo-mark">V</span><span><?= e(setting('villa_name')) ?></span></div>
      <p><?= e(setting('villa_tagline')) ?>.</p></div>
    <div><h4>Visit</h4><p><?= e(setting('villa_address')) ?><br>Check-in <?= e(setting('check_in_time')) ?> · Check-out <?= e(setting('check_out_time')) ?></p></div>
    <div><h4>Contact</h4><p><?= e(setting('villa_phone')) ?><br><a href="mailto:<?= e(setting('villa_email')) ?>"><?= e(setting('villa_email')) ?></a><br><?= e(setting('website')) ?></p></div>
    <div><h4>Guests &amp; partners</h4><p><a href="<?= e(url('manage-booking.php')) ?>">Manage my booking</a><br><a href="<?= e(url('gallery.php')) ?>">Photo gallery</a><br><a href="<?= e(url('contact.php')) ?>">Tour operators &amp; groups</a><br><a href="<?= e(url('login.php')) ?>">Staff login</a></p></div>
  </div>
  <div class="wrap copy">© <?= date('Y') ?> <?= e(setting('villa_name')) ?>. All rights reserved.</div>
</footer>
<script src="<?= e(url('assets/js/site.js')) ?>"></script>
</body>
</html>

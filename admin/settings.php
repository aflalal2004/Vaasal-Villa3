<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('settings.manage');
$title = 'Settings';
$active = 'settings';

$fields = [
    'Property' => ['villa_name' => 'Villa name', 'villa_tagline' => 'Tagline', 'villa_address' => 'Address', 'villa_phone' => 'Phone', 'villa_email' => 'Email', 'website' => 'Website'],
    'Front office' => ['check_in_time' => 'Check-in time (HH:MM)', 'check_out_time' => 'Check-out time (HH:MM)', 'room_tax_rate' => 'Room tax %'],
    'Restaurant & billing' => ['currency_symbol' => 'Currency symbol', 'tax_rate' => 'Restaurant tax %', 'service_charge' => 'Service charge %', 'invoice_footer' => 'Invoice footer text'],
];
if (is_post()) {
    $errors = [];
    foreach ($fields as $group) {
        foreach ($group as $k => $l) {
            $v = input($k);
            if (in_array($k, ['room_tax_rate', 'tax_rate', 'service_charge'], true) && (!is_numeric($v) || $v < 0 || $v > 100)) { $errors[] = "$l must be 0–100."; continue; }
            if (in_array($k, ['check_in_time', 'check_out_time'], true) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) { $errors[] = "$l must be HH:MM."; continue; }
            q('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)', [$k, $v]);
        }
    }
    log_activity('settings', 'Updated system settings');
    $errors ? flash('error', implode(' ', $errors)) : flash('success', 'Settings saved.');
    redirect('admin/settings.php');
}
require __DIR__ . '/../includes/admin_header.php';
?>
<form method="post"><?= csrf_field() ?>
  <div class="grid g3">
  <?php foreach ($fields as $group => $items): ?>
    <div class="card"><h2><?= e($group) ?></h2>
      <?php foreach ($items as $k => $l): ?><div class="field"><label><?= e($l) ?></label><input name="<?= e($k) ?>" value="<?= e(setting($k)) ?>"></div><?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  </div>
  <button class="btn">Save settings</button>
</form>
<div class="card" style="margin-top:20px"><h2>System</h2>
  <dl class="dl"><dt>Version</dt><dd><?= e(APP_VERSION) ?></dd><dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd><dt>MySQL</dt><dd><?= e(q_val('SELECT VERSION()')) ?></dd>
  <dt>Database</dt><dd><?= e(DB_NAME) ?></dd><dt>Base URL</dt><dd><?= e(BASE_URL ?: '/') ?></dd><dt>Timezone</dt><dd><?= e(APP_TIMEZONE) ?></dd></dl>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

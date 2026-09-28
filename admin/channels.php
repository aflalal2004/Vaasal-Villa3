<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('settings.manage');
$title = 'Booking Channels, OTAs & Tour Operators';
$active = 'channels';

if (is_post()) {
    $a = input('action');
    try {
        if ($a === 'save') {
            $type = input('type');
            if (input('name') === '' || !in_array($type, ['direct', 'website', 'ota', 'tour_operator', 'walk_in', 'phone'], true)) throw new RuntimeException('Name and type are required.');
            $vals = [input('name'), $type, input('contact_person') ?: null, input('email') ?: null, input('phone') ?: null, input_float('commission_pct'), !empty($_POST['is_active']) ? 1 : 0];
            if (input_int('id')) q('UPDATE booking_sources SET name=?, type=?, contact_person=?, email=?, phone=?, commission_pct=?, is_active=? WHERE id=?', [...$vals, input_int('id')]);
            else q('INSERT INTO booking_sources (name, type, contact_person, email, phone, commission_pct, is_active) VALUES (?,?,?,?,?,?,?)', $vals);
            log_activity('channels', 'Saved channel ' . input('name'));
            flash('success', 'Channel saved.');
        } elseif ($a === 'key') {
            $key = bin2hex(random_bytes(20));
            q('UPDATE booking_sources SET api_key=? WHERE id=?', [$key, input_int('id')]);
            log_activity('channels', 'Regenerated API key for channel #' . input_int('id'));
            flash('success', 'New API key generated. Share it securely with the partner.');
        } elseif ($a === 'revoke') {
            q('UPDATE booking_sources SET api_key=NULL WHERE id=?', [input_int('id')]);
            flash('success', 'API access revoked.');
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/channels.php');
}

$rows = q_all("SELECT s.*, (SELECT COUNT(*) FROM bookings b WHERE b.source_id=s.id AND b.status NOT IN ('cancelled','no_show')) n FROM booking_sources s ORDER BY s.type, s.name");
$edit = input_int('edit') ? q_row('SELECT * FROM booking_sources WHERE id=?', [input_int('edit')]) : null;
$endpoint = (isset($_SERVER['HTTP_HOST']) ? (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . url('api/channel.php');

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="grid g-2-1">
  <div class="card">
    <h2>Channels</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Type</th><th>Contact</th><th class="num">Comm.</th><th class="num">Bookings</th><th>API key</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr style="<?= $r['is_active'] ? '' : 'opacity:.5' ?>"><td><b><?= e($r['name']) ?></b></td><td><?= e(label($r['type'])) ?></td><td class="small"><?= e($r['contact_person']) ?><br><?= e($r['email']) ?></td>
      <td class="num"><?= (float) $r['commission_pct'] ?>%</td><td class="num"><?= $r['n'] ?></td>
      <td class="small"><?= $r['api_key'] ? '<code>' . e(substr($r['api_key'], 0, 10)) . '…</code>' : '<span class="muted">none</span>' ?></td>
      <td class="right nowrap"><a class="btn btn-sm btn-ghost" href="?edit=<?= $r['id'] ?>">Edit</a>
        <?php if (in_array($r['type'], ['ota', 'tour_operator'], true)): ?>
        <form method="post" class="inline-form" data-confirm="Generate a new API key? The old key will stop working."><?= csrf_field() ?><input type="hidden" name="action" value="key"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-ghost">🔑 Key</button></form>
        <?php if ($r['api_key']): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-ghost">Revoke</button></form><?php endif; ?>
        <?php endif; ?></td></tr>
      <?php if ($r['api_key'] && $edit && $edit['id'] == $r['id']): ?><tr><td colspan="7" class="small">Full key: <code><?= e($r['api_key']) ?></code></td></tr><?php endif; ?>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="card">
    <?php $f = $edit ?: ['id' => 0, 'name' => '', 'type' => 'ota', 'contact_person' => '', 'email' => '', 'phone' => '', 'commission_pct' => 0, 'is_active' => 1]; ?>
    <h2><?= $edit ? 'Edit ' . e($f['name']) : 'Add channel / tour operator' ?></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $f['id'] ?>">
      <div class="field"><label>Name</label><input name="name" value="<?= e($f['name']) ?>" required></div>
      <div class="form-grid">
        <div class="field"><label>Type</label><select name="type"><?= options(['ota' => 'OTA / booking site', 'tour_operator' => 'Tour operator', 'website' => 'Own website', 'direct' => 'Direct', 'walk_in' => 'Walk-in', 'phone' => 'Phone'], $f['type']) ?></select></div>
        <div class="field"><label>Commission %</label><input type="number" step="0.01" name="commission_pct" value="<?= e($f['commission_pct']) ?>"></div>
        <div class="field"><label>Contact person</label><input name="contact_person" value="<?= e($f['contact_person']) ?>"></div>
        <div class="field"><label>Email</label><input name="email" value="<?= e($f['email']) ?>"></div>
        <div class="field"><label>Phone</label><input name="phone" value="<?= e($f['phone']) ?>"></div>
      </div>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $f['is_active'] ? 'checked' : '' ?>> Active</label>
      <div class="form-actions"><button class="btn">Save</button><?php if ($edit): ?><a class="btn btn-ghost" href="channels.php">New</a><?php endif; ?></div>
    </form>
  </div>
</div>
<div class="card">
  <h2>Channel API — for booking sites &amp; tour operators</h2>
  <p>Partners push reservations into Vaasal Villa with a JSON <code>POST</code> to:</p>
  <p><code><?= e($endpoint) ?></code></p>
  <p class="small">Header <code>X-Api-Key: &lt;partner key&gt;</code>. Actions: <code>availability</code>, <code>create</code>, <code>cancel</code>, <code>status</code>. Full examples are in <b>README.md</b>.</p>
<pre style="background:#0b3b37;color:#d7ece9;padding:14px;border-radius:10px;overflow:auto;font-size:12px">{
  "action": "create",
  "external_ref": "BDC-99812",
  "room_type_id": 2,
  "check_in": "<?= date('Y-m-d', strtotime('+10 day')) ?>",
  "check_out": "<?= date('Y-m-d', strtotime('+13 day')) ?>",
  "adults": 2, "children": 0,
  "rate_per_night": 199.00,
  "guest": { "first_name": "Anna", "last_name": "Berg", "email": "anna@example.com", "phone": "+46 70 000", "country": "Sweden" },
  "special_requests": "Honeymoon"
}</pre>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

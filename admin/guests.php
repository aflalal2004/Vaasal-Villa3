<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('guests.manage');
$title = 'Guests';
$active = 'guests';

$fields = ['first_name', 'last_name', 'email', 'phone', 'country', 'id_type', 'id_number', 'address', 'notes'];
if (is_post()) {
    $id = input_int('id');
    $data = [];
    foreach ($fields as $fld) $data[$fld] = input($fld) === '' ? null : input($fld);
    $data['vip'] = !empty($_POST['vip']) ? 1 : 0;
    if (!$data['first_name'] || !$data['last_name']) {
        flash('error', 'First and last name are required.');
    } elseif ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Invalid email address.');
    } else {
        if ($id) {
            q('UPDATE guests SET first_name=?, last_name=?, email=?, phone=?, country=?, id_type=?, id_number=?, address=?, notes=?, vip=? WHERE id=?', [...array_values($data), $id]);
            log_activity('guest', "Updated guest #$id");
            flash('success', 'Guest updated.');
        } else {
            q('INSERT INTO guests (first_name, last_name, email, phone, country, id_type, id_number, address, notes, vip) VALUES (?,?,?,?,?,?,?,?,?,?)', array_values($data));
            $id = (int) db()->lastInsertId();
            log_activity('guest', "Created guest #$id");
            flash('success', 'Guest created.');
        }
        redirect('admin/guests.php?view=' . $id);
    }
}

$edit = input_int('edit');
$view = input_int('view');
$new = input('new') !== '';
$guest = ($edit || $view) ? q_row('SELECT * FROM guests WHERE id = ?', [$edit ?: $view]) : null;

require __DIR__ . '/../includes/admin_header.php';

if ($new || $edit):
    $g = $guest ?: array_fill_keys($fields, '') + ['vip' => 0, 'id' => 0]; ?>
  <div class="card">
    <h2><?= $guest ? 'Edit guest' : 'New guest' ?></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($g['id'] ?? 0) ?>">
      <div class="form-grid">
        <?php foreach (['first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email', 'phone' => 'Phone', 'country' => 'Country', 'id_type' => 'ID type', 'id_number' => 'ID / Passport no.'] as $k => $l): ?>
          <div class="field"><label><?= $l ?></label><input name="<?= $k ?>" value="<?= e($g[$k]) ?>" <?= in_array($k, ['first_name', 'last_name']) ? 'required' : '' ?>></div>
        <?php endforeach; ?>
      </div>
      <div class="field"><label>Address</label><input name="address" value="<?= e($g['address']) ?>"></div>
      <div class="field"><label>Notes / preferences</label><textarea name="notes"><?= e($g['notes']) ?></textarea></div>
      <label class="check"><input type="checkbox" name="vip" value="1" <?= $g['vip'] ? 'checked' : '' ?>> VIP guest</label>
      <div class="form-actions"><button class="btn">Save guest</button><a class="btn btn-ghost" href="guests.php">Cancel</a></div>
    </form>
  </div>
<?php elseif ($view && $guest):
    $hist = q_all('SELECT b.*, rt.name room_type, r.room_number FROM bookings b JOIN room_types rt ON rt.id=b.room_type_id LEFT JOIN rooms r ON r.id=b.room_id WHERE b.guest_id=? ORDER BY b.check_in DESC', [$view]);
    $spent = (float) q_val('SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON b.id=p.booking_id WHERE b.guest_id=?', [$view]); ?>
  <div class="grid g-2-1">
    <div class="card">
      <div class="card-head"><h2><?= e($guest['first_name'] . ' ' . $guest['last_name']) ?> <?= $guest['vip'] ? '<span class="badge badge-purple">VIP</span>' : '' ?></h2>
        <div class="btn-row"><a class="btn btn-sm btn-ghost" href="?edit=<?= $view ?>">Edit</a><?php if (can('bookings.manage')): ?><a class="btn btn-sm" href="booking_form.php?guest_id=<?= $view ?>">+ New booking</a><?php endif; ?></div></div>
      <h3>Stay history</h3>
      <table class="table"><thead><tr><th>Ref</th><th>Room</th><th>Stay</th><th>Status</th><th class="num">Room total</th></tr></thead><tbody>
      <?php foreach ($hist as $h): ?>
        <tr><td><a href="booking_view.php?id=<?= $h['id'] ?>"><?= e($h['booking_ref']) ?></a></td><td><?= e($h['room_type']) ?> <?= e($h['room_number']) ?></td>
        <td><?= fdate($h['check_in']) ?> → <?= fdate($h['check_out']) ?></td><td><?= badge($h['status']) ?></td><td class="num"><?= money($h['room_total']) ?></td></tr>
      <?php endforeach; if (!$hist): ?><tr><td colspan="5" class="empty">No stays yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="card"><dl class="dl">
      <dt>Email</dt><dd><?= e($guest['email'] ?: '—') ?></dd><dt>Phone</dt><dd><?= e($guest['phone'] ?: '—') ?></dd>
      <dt>Country</dt><dd><?= e($guest['country'] ?: '—') ?></dd><dt>ID</dt><dd><?= e(trim($guest['id_type'] . ' ' . $guest['id_number']) ?: '—') ?></dd>
      <dt>Address</dt><dd><?= e($guest['address'] ?: '—') ?></dd><dt>Stays</dt><dd><?= count($hist) ?></dd>
      <dt>Total paid</dt><dd><?= money($spent) ?></dd><dt>Notes</dt><dd><?= nl2br(e($guest['notes'] ?: '—')) ?></dd>
    </dl></div>
  </div>
<?php else:
    $s = input('q');
    $rows = q_all("SELECT g.*, (SELECT COUNT(*) FROM bookings b WHERE b.guest_id=g.id) stays,
                     (SELECT MAX(check_in) FROM bookings b WHERE b.guest_id=g.id) last_stay
                   FROM guests g WHERE CONCAT(first_name,' ',last_name,' ',COALESCE(email,''),' ',COALESCE(phone,'')) LIKE ?
                   ORDER BY g.created_at DESC LIMIT 300", ["%$s%"]); ?>
  <div class="card">
    <div class="card-head"><h2>Guest directory</h2><a class="btn" href="?new=1">+ New guest</a></div>
    <form class="filters"><div class="field"><input name="q" value="<?= e($s) ?>" placeholder="Search name, email, phone"></div><button class="btn btn-ghost">Search</button></form>
    <div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Country</th><th>Stays</th><th>Last stay</th></tr></thead><tbody>
    <?php foreach ($rows as $g): ?>
      <tr><td><a href="?view=<?= $g['id'] ?>"><b><?= e($g['first_name'] . ' ' . $g['last_name']) ?></b></a> <?= $g['vip'] ? '<span class="badge badge-purple">VIP</span>' : '' ?></td>
      <td><?= e($g['email']) ?></td><td><?= e($g['phone']) ?></td><td><?= e($g['country']) ?></td><td><?= (int) $g['stays'] ?></td><td><?= fdate($g['last_stay']) ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No guests found.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
<?php endif;
require __DIR__ . '/../includes/admin_footer.php';

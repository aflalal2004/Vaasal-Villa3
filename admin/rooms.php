<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('rooms.manage');
$title = 'Rooms & Room Types';
$active = 'rooms';

if (is_post()) {
    $action = input('action');
    try {
        if ($action === 'save_type') {
            $id = input_int('id');
            $name = input('name');
            if ($name === '' || input_float('base_rate') <= 0) throw new RuntimeException('Name and a positive rate are required.');
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
            $vals = [$name, $slug, input('short_desc'), input('description'), input_float('base_rate'), max(1, input_int('max_adults', 2)),
                     max(0, input_int('max_children')), input_int('size_sqm') ?: null, input('bed_type'), input('amenities'),
                     preg_match('/^#[0-9a-f]{6}$/i', input('color')) ? input('color') : '#0f766e', !empty($_POST['is_active']) ? 1 : 0];
            // Photo: uploaded file wins over a pasted URL; otherwise keep the current one
            $img = save_upload('image_file', 'image');
            if (!$img && input('image_url') !== '') {
                if (!valid_media_url(input('image_url'))) throw new RuntimeException('Photo link must be a full https:// URL.');
                $img = input('image_url');
            }
            if ($id) {
                // Keep the existing slug so public room links (room.php?type=...) never break on rename
                $vals[1] = q_val('SELECT slug FROM room_types WHERE id=?', [$id]) ?: $slug;
                q('UPDATE room_types SET name=?, slug=?, short_desc=?, description=?, base_rate=?, max_adults=?, max_children=?, size_sqm=?, bed_type=?, amenities=?, color=?, is_active=? WHERE id=?', [...$vals, $id]);
            } else {
                // New type: make the slug unique
                $base = $slug ?: 'room'; $n = 1;
                while (q_val('SELECT id FROM room_types WHERE slug=?', [$vals[1]])) $vals[1] = $base . '-' . (++$n);
                q('INSERT INTO room_types (name, slug, short_desc, description, base_rate, max_adults, max_children, size_sqm, bed_type, amenities, color, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', $vals);
                $id = (int) db()->lastInsertId();
            }
            if ($img) {
                $old = q_val('SELECT image_url FROM room_types WHERE id=?', [$id]);
                if ($old !== $img) delete_upload($old);
                q('UPDATE room_types SET image_url=? WHERE id=?', [$img, $id]);
            }
            log_activity('rooms', "Saved room type $name");
            flash('success', 'Room type saved.');
        } elseif ($action === 'save_room') {
            $id = input_int('id');
            $no = strtoupper(input('room_number'));
            if ($no === '' || !input_int('room_type_id')) throw new RuntimeException('Room number and type are required.');
            $st = in_array(input('status'), ['available', 'occupied', 'maintenance'], true) ? input('status') : 'available';
            $hk = in_array(input('housekeeping_status'), ['clean', 'dirty', 'inspected', 'out_of_order'], true) ? input('housekeeping_status') : 'clean';
            if ($id) {
                $cur = q_row('SELECT * FROM rooms WHERE id=?', [$id]);
                $inHouse = q_val("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status='checked_in'", [$id]);
                if ($inHouse && $st !== 'occupied') throw new RuntimeException('Room has an in-house guest — check the guest out first.');
                if (!$inHouse && $st === 'occupied') throw new RuntimeException('Rooms become occupied only through check-in.');
                if ($inHouse && (int) $cur['room_type_id'] !== input_int('room_type_id')) throw new RuntimeException('Cannot change the type of an occupied room.');
                q('UPDATE rooms SET room_number=?, room_type_id=?, floor=?, status=?, housekeeping_status=?, notes=? WHERE id=?', [$no, input_int('room_type_id'), input('floor'), $st, $hk, input('notes'), $id]);
            } else {
                if ($st === 'occupied') $st = 'available';
                q('INSERT INTO rooms (room_number, room_type_id, floor, status, housekeeping_status, notes) VALUES (?,?,?,?,?,?)', [$no, input_int('room_type_id'), input('floor'), $st, $hk, input('notes')]);
            }
            log_activity('rooms', "Saved room $no");
            flash('success', "Room $no saved.");
        } elseif ($action === 'delete_room') {
            $id = input_int('id');
            if (q_val('SELECT COUNT(*) FROM bookings WHERE room_id=?', [$id]) || q_val('SELECT COUNT(*) FROM key_cards WHERE room_id=?', [$id])) {
                throw new RuntimeException('Room has booking history and cannot be deleted. Set it to maintenance instead.');
            }
            q('DELETE FROM rooms WHERE id=?', [$id]);
            flash('success', 'Room deleted.');
        }
    } catch (PDOException $ex) {
        flash('error', $ex->getCode() == 23000 ? 'That room number or name already exists.' : 'Database error: ' . $ex->getMessage());
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/rooms.php');
}

$types = q_all('SELECT rt.*, (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id=rt.id) rooms FROM room_types rt ORDER BY base_rate');
$rooms = q_all('SELECT r.*, rt.name type_name FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id ORDER BY r.room_number');
$editType = input_int('type') ? q_row('SELECT * FROM room_types WHERE id=?', [input_int('type')]) : null;
$editRoom = input_int('room') ? q_row('SELECT * FROM rooms WHERE id=?', [input_int('room')]) : null;
$typeOpts = array_map(fn($t) => [$t['id'], $t['name']], $types);

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="grid g-2-1">
  <div class="card">
    <div class="card-head"><h2>Rooms (<?= count($rooms) ?>)</h2></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Room</th><th>Type</th><th>Floor</th><th>Status</th><th>Housekeeping</th><th>Notes</th><th></th></tr></thead><tbody>
    <?php foreach ($rooms as $r): ?>
      <tr><td><b><?= e($r['room_number']) ?></b></td><td><?= e($r['type_name']) ?></td><td><?= e($r['floor']) ?></td><td><?= badge($r['status']) ?></td><td><?= badge($r['housekeeping_status']) ?></td>
      <td class="small muted"><?= e($r['notes']) ?></td>
      <td class="right nowrap"><a class="btn btn-sm btn-ghost" href="?room=<?= $r['id'] ?>#roomform">Edit</a>
        <form method="post" class="inline-form" data-confirm="Delete room <?= e($r['room_number']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_room"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-ghost">✕</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="card" id="roomform">
    <h2><?= $editRoom ? 'Edit room ' . e($editRoom['room_number']) : 'Add room' ?></h2>
    <?php $r = $editRoom ?: ['id' => 0, 'room_number' => '', 'room_type_id' => '', 'floor' => '', 'status' => 'available', 'housekeeping_status' => 'clean', 'notes' => '']; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_room"><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <div class="form-grid">
        <div class="field"><label>Room number</label><input name="room_number" value="<?= e($r['room_number']) ?>" required></div>
        <div class="field"><label>Floor / area</label><input name="floor" value="<?= e($r['floor']) ?>"></div>
      </div>
      <div class="field"><label>Room type</label><select name="room_type_id" required><?= options($typeOpts, $r['room_type_id']) ?></select></div>
      <div class="form-grid">
        <div class="field"><label>Status</label><select name="status"><?= options(['available' => 'Available', 'occupied' => 'Occupied', 'maintenance' => 'Maintenance'], $r['status']) ?></select></div>
        <div class="field"><label>Housekeeping</label><select name="housekeeping_status"><?= options(['clean' => 'Clean', 'dirty' => 'Dirty', 'inspected' => 'Inspected', 'out_of_order' => 'Out of order'], $r['housekeeping_status']) ?></select></div>
      </div>
      <div class="field"><label>Notes</label><input name="notes" value="<?= e($r['notes']) ?>"></div>
      <div class="form-actions"><button class="btn">Save room</button><?php if ($editRoom): ?><a class="btn btn-ghost" href="rooms.php">New</a><?php endif; ?></div>
    </form>
  </div>
</div>

<div class="grid g-2-1">
  <div class="card">
    <h2>Room types</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th class="num">Rate</th><th>Occupancy</th><th>Size</th><th>Rooms</th><th>Web</th><th></th></tr></thead><tbody>
    <?php foreach ($types as $t): ?>
      <tr><td><?php if ($t['image_url']): ?><img src="<?= e(media_sized($t['image_url'], 120)) ?>" alt="" style="width:48px;height:34px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:6px"><?php endif; ?><b><?= e($t['name']) ?></b><div class="small muted"><?= e($t['short_desc']) ?></div></td>
      <td class="num"><?= money($t['base_rate']) ?></td><td><?= $t['max_adults'] ?> + <?= $t['max_children'] ?></td><td><?= $t['size_sqm'] ? e($t['size_sqm']) . ' m²' : '' ?></td><td><?= $t['rooms'] ?></td>
      <td><?= $t['is_active'] ? badge('active') : badge('deactivated') ?></td><td class="right"><a class="btn btn-sm btn-ghost" href="?type=<?= $t['id'] ?>#typeform">Edit</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="card" id="typeform">
    <?php $t = $editType ?: ['id' => 0, 'name' => '', 'short_desc' => '', 'description' => '', 'base_rate' => '', 'max_adults' => 2, 'max_children' => 1, 'size_sqm' => '', 'bed_type' => '', 'amenities' => '', 'color' => '#0f766e', 'is_active' => 1]; ?>
    <h2><?= $editType ? 'Edit ' . e($t['name']) : 'Add room type' ?></h2>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save_type"><input type="hidden" name="id" value="<?= $t['id'] ?>">
      <?php if (!empty($t['image_url'])): ?><img src="<?= e(media_sized($t['image_url'], 600)) ?>" alt="" style="width:100%;height:150px;object-fit:cover;border-radius:10px;margin-bottom:10px"><?php endif; ?>
      <div class="form-grid">
        <div class="field"><label>Main photo — upload</label><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"></div>
        <div class="field"><label>…or photo URL</label><input name="image_url" placeholder="https://…" value="<?= e(str_starts_with((string) ($t['image_url'] ?? ''), 'http') ? $t['image_url'] : '') ?>"></div>
      </div>
      <p class="small muted" style="margin-top:-6px">More photos for the room slideshow: <a href="media.php?cat=rooms">Website Media</a>.</p>
      <div class="field"><label>Name</label><input name="name" value="<?= e($t['name']) ?>" required></div>
      <div class="field"><label>Short description</label><input name="short_desc" value="<?= e($t['short_desc']) ?>"></div>
      <div class="field"><label>Description (website)</label><textarea name="description"><?= e($t['description']) ?></textarea></div>
      <div class="form-grid">
        <div class="field"><label>Rate / night</label><input type="number" step="0.01" name="base_rate" value="<?= e($t['base_rate']) ?>" required></div>
        <div class="field"><label>Max adults</label><input type="number" name="max_adults" value="<?= e($t['max_adults']) ?>"></div>
        <div class="field"><label>Max children</label><input type="number" name="max_children" value="<?= e($t['max_children']) ?>"></div>
        <div class="field"><label>Size m²</label><input type="number" name="size_sqm" value="<?= e($t['size_sqm']) ?>"></div>
        <div class="field"><label>Bed</label><input name="bed_type" value="<?= e($t['bed_type']) ?>"></div>
        <div class="field"><label>Colour</label><input type="color" name="color" value="<?= e($t['color']) ?>"></div>
      </div>
      <div class="field"><label>Amenities (comma separated)</label><input name="amenities" value="<?= e($t['amenities']) ?>"></div>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $t['is_active'] ? 'checked' : '' ?>> Bookable on website</label>
      <div class="form-actions"><button class="btn">Save type</button><?php if ($editType): ?><a class="btn btn-ghost" href="rooms.php">New</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

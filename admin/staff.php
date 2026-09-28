<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_perm('staff.manage');
$title = 'Staff';
$active = 'staff';

$roles = q_all('SELECT id, name, slug FROM roles ORDER BY id');
if (is_post()) {
    $id = input_int('id');
    try {
        $roleId = input_int('role_id');
        $role = q_row('SELECT * FROM roles WHERE id=?', [$roleId]);
        if (input('full_name') === '' || !preg_match('/^[a-z0-9_.]{3,40}$/i', input('username')) || !$role) {
            throw new RuntimeException('Full name, a valid username (3-40 letters/digits/._) and role are required.');
        }
        // Only administrators may create or promote administrators
        if ($role['slug'] === 'admin' && $me['role_slug'] !== 'admin') throw new RuntimeException('Only administrators can assign the Administrator role.');
        $pwd = (string) ($_POST['password'] ?? '');
        if ((!$id && strlen($pwd) < 8) || ($pwd !== '' && strlen($pwd) < 8)) throw new RuntimeException('Password must be at least 8 characters.');
        if (input('email') !== '' && !filter_var(input('email'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid email.');
        $activeFlag = !empty($_POST['is_active']) ? 1 : 0;
        if ($id === (int) $me['id'] && (!$activeFlag || $roleId !== (int) $me['role_id'])) throw new RuntimeException('You cannot deactivate yourself or change your own role.');
        $vals = [$roleId, input('full_name'), input('username'), input('email') ?: null, input('phone') ?: null, input('department') ?: null, input_float('hourly_rate'), $activeFlag];
        if ($id) {
            $target = q_row('SELECT u.*, r.slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?', [$id]);
            if ($target['slug'] === 'admin' && $me['role_slug'] !== 'admin') throw new RuntimeException('Only administrators can edit administrator accounts.');
            q('UPDATE users SET role_id=?, full_name=?, username=?, email=?, phone=?, department=?, hourly_rate=?, is_active=? WHERE id=?', [...$vals, $id]);
            if ($pwd !== '') q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pwd, PASSWORD_DEFAULT), $id]);
        } else {
            q('INSERT INTO users (role_id, full_name, username, email, phone, department, hourly_rate, is_active, password_hash) VALUES (?,?,?,?,?,?,?,?,?)', [...$vals, password_hash($pwd, PASSWORD_DEFAULT)]);
            $id = (int) db()->lastInsertId();
        }
        log_activity('staff', 'Saved staff account ' . input('username'));
        flash('success', 'Staff member saved.');
        redirect('admin/staff.php');
    } catch (PDOException $ex) {
        flash('error', $ex->getCode() == 23000 ? 'That username is already taken.' : $ex->getMessage());
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/staff.php' . ($id ? '?edit=' . $id : '?new=1'));
}

$edit = input_int('edit');
$u = $edit ? q_row('SELECT * FROM users WHERE id=?', [$edit]) : null;
$rows = q_all('SELECT u.*, r.name role, (SELECT clock_in FROM attendance a WHERE a.user_id=u.id AND a.clock_out IS NULL LIMIT 1) on_duty,
                 (SELECT COALESCE(SUM(hours),0) FROM attendance a WHERE a.user_id=u.id AND a.work_date >= ?) month_hours
               FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.is_active DESC, r.id, u.full_name', [date('Y-m-01')]);

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="grid g-2-1">
  <div class="card">
    <div class="card-head"><h2>Team (<?= count($rows) ?>)</h2><a class="btn" href="staff.php?new=1#form">+ Add staff</a></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Department</th><th>Duty</th><th class="num">Hours (month)</th><th>Last login</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr style="<?= $r['is_active'] ? '' : 'opacity:.5' ?>"><td><b><?= e($r['full_name']) ?></b><div class="small muted"><?= e($r['email']) ?> <?= e($r['phone']) ?></div></td>
      <td><code><?= e($r['username']) ?></code></td><td><?= e($r['role']) ?></td><td><?= e($r['department']) ?></td>
      <td><?= $r['on_duty'] ? '<span class="badge badge-green">On since ' . date('H:i', strtotime($r['on_duty'])) . '</span>' : '<span class="badge badge-gray">Off</span>' ?></td>
      <td class="num"><?= number_format($r['month_hours'], 1) ?></td><td class="small"><?= fdatetime($r['last_login']) ?></td>
      <td><a class="btn btn-sm btn-ghost" href="?edit=<?= $r['id'] ?>#form">Edit</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="card" id="form">
    <?php $f = $u ?: ['id' => 0, 'full_name' => '', 'username' => '', 'email' => '', 'phone' => '', 'department' => '', 'hourly_rate' => 0, 'role_id' => 8, 'is_active' => 1]; ?>
    <h2><?= $u ? 'Edit ' . e($u['full_name']) : 'New staff member' ?></h2>
    <form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $f['id'] ?>">
      <div class="field"><label>Full name</label><input name="full_name" value="<?= e($f['full_name']) ?>" required></div>
      <div class="form-grid">
        <div class="field"><label>Username</label><input name="username" value="<?= e($f['username']) ?>" required></div>
        <div class="field"><label>Role</label><select name="role_id"><?= options(array_map(fn($r) => [$r['id'], $r['name']], $roles), $f['role_id']) ?></select></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($f['email']) ?>"></div>
        <div class="field"><label>Phone</label><input name="phone" value="<?= e($f['phone']) ?>"></div>
        <div class="field"><label>Department</label><input name="department" value="<?= e($f['department']) ?>"></div>
        <div class="field"><label>Hourly rate</label><input type="number" step="0.01" min="0" name="hourly_rate" value="<?= e($f['hourly_rate']) ?>"></div>
      </div>
      <div class="field"><label><?= $u ? 'New password (leave blank to keep)' : 'Password (min 8 chars)' ?></label><input type="password" name="password" autocomplete="new-password" <?= $u ? '' : 'required' ?>></div>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $f['is_active'] ? 'checked' : '' ?>> Active (can sign in)</label>
      <div class="form-actions"><button class="btn">Save</button><?php if ($u): ?><a class="btn btn-ghost" href="staff.php">Cancel</a><?php endif; ?></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

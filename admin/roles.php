<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('roles.manage');
$title = 'Roles & Permissions';
$active = 'roles';

if (is_post()) {
    $a = input('action');
    if ($a === 'save_matrix') {
        tx(function () {
            $grid = $_POST['perm'] ?? [];
            foreach (q_all("SELECT id FROM roles WHERE slug <> 'admin'") as $r) {
                q('DELETE FROM role_permissions WHERE role_id=?', [$r['id']]);
                foreach (array_keys((array) ($grid[$r['id']] ?? [])) as $pid) {
                    q('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)', [$r['id'], (int) $pid]);
                }
            }
        });
        log_activity('roles', 'Updated permission matrix');
        flash('success', 'Permissions saved.');
    } elseif ($a === 'add_role') {
        $name = input('name');
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
        if ($name === '' || $slug === '') {
            flash('error', 'Role name required.');
        } elseif (q_val('SELECT id FROM roles WHERE slug=?', [$slug])) {
            flash('error', 'A role with that name exists.');
        } else {
            q('INSERT INTO roles (name, slug, description) VALUES (?,?,?)', [$name, $slug, input('description') ?: null]);
            $rid = (int) db()->lastInsertId();
            q("INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE slug='attendance.self'", [$rid]);
            log_activity('roles', "Added role $name");
            flash('success', "Role $name added. Tick its permissions below.");
        }
    }
    redirect('admin/roles.php');
}

$roles = q_all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id=r.id) users FROM roles r ORDER BY r.id');
$perms = q_all('SELECT * FROM permissions ORDER BY grp, id');
$has = [];
foreach (q_all('SELECT role_id, permission_id FROM role_permissions') as $rp) $has[$rp['role_id']][$rp['permission_id']] = true;

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="card">
  <div class="card-head"><h2>Permission matrix</h2><span class="small muted">Administrator always has every permission.</span></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_matrix">
    <div class="table-wrap"><table class="table matrix">
      <thead><tr><th>Permission</th><?php foreach ($roles as $r): ?><th title="<?= e($r['description']) ?>"><?= e($r['name']) ?><div class="small muted" style="text-transform:none"><?= $r['users'] ?> user(s)</div></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php $grp = null; foreach ($perms as $p): if ($grp !== $p['grp']): $grp = $p['grp']; ?>
        <tr><td colspan="<?= count($roles) + 1 ?>" style="background:#f0fdfa;font-weight:700"><?= e($grp) ?></td></tr>
      <?php endif; ?>
        <tr><td><?= e($p['name']) ?> <div class="small muted"><code><?= e($p['slug']) ?></code></div></td>
        <?php foreach ($roles as $r): ?>
          <td><?php if ($r['slug'] === 'admin'): ?>✔<?php else: ?><input type="checkbox" name="perm[<?= $r['id'] ?>][<?= $p['id'] ?>]" value="1" <?= isset($has[$r['id']][$p['id']]) ? 'checked' : '' ?>><?php endif; ?></td>
        <?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="form-actions"><button class="btn">Save permissions</button></div>
  </form>
</div>
<div class="card">
  <h2>Add role</h2>
  <form method="post" class="filters"><?= csrf_field() ?><input type="hidden" name="action" value="add_role">
    <div class="field"><label>Name</label><input name="name" required></div>
    <div class="field" style="flex:1"><label>Description</label><input name="description"></div>
    <button class="btn btn-ghost">Add role</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

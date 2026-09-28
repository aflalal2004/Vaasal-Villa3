<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('housekeeping.manage');
$title = 'Housekeeping';
$active = 'housekeeping';

$taskTypes = ['checkout_clean' => 'Check-out clean', 'stayover_clean' => 'Stay-over clean', 'turndown' => 'Turndown', 'deep_clean' => 'Deep clean', 'inspection' => 'Inspection', 'maintenance' => 'Maintenance'];

if (is_post()) {
    $action = input('action');
    try {
        if ($action === 'create') {
            if (!input_int('room_id') || !isset($taskTypes[input('task_type')])) throw new RuntimeException('Room and task type are required.');
            $prio = in_array(input('priority'), ['low', 'normal', 'high', 'urgent'], true) ? input('priority') : 'normal';
            q('INSERT INTO housekeeping_tasks (room_id, assigned_to, task_type, priority, notes, created_by) VALUES (?,?,?,?,?,?)',
              [input_int('room_id'), input_int('assigned_to') ?: null, input('task_type'), $prio, input('notes') ?: null, uid()]);
            if (input('task_type') === 'maintenance' && !empty($_POST['ooo'])) {
                q("UPDATE rooms SET status='maintenance', housekeeping_status='out_of_order' WHERE id=? AND status<>'occupied'", [input_int('room_id')]);
            }
            log_activity('housekeeping', 'Created ' . input('task_type') . ' task for room #' . input_int('room_id'));
            flash('success', 'Task created.');
        } elseif ($action === 'assign') {
            q('UPDATE housekeeping_tasks SET assigned_to=? WHERE id=?', [input_int('assigned_to') ?: null, input_int('id')]);
            flash('success', 'Task reassigned.');
        } elseif ($action === 'status') {
            hk_set_status(input_int('id'), input('status'));
            flash('success', 'Task updated.');
        } elseif ($action === 'room_hk') {
            $st = input('housekeeping_status');
            if (!in_array($st, ['clean', 'dirty', 'inspected', 'out_of_order'], true)) throw new RuntimeException('Invalid status.');
            q('UPDATE rooms SET housekeeping_status=? WHERE id=?', [$st, input_int('room_id')]);
            if ($st !== 'out_of_order') q("UPDATE rooms SET status='available' WHERE id=? AND status='maintenance'", [input_int('room_id')]);
            log_activity('housekeeping', 'Room #' . input_int('room_id') . " marked $st");
            flash('success', 'Room status updated.');
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/housekeeping.php?' . http_build_query(['show' => input('show', 'open')]));
}

$show = input('show', 'open');
$filter = $show === 'open' ? "WHERE t.status IN ('pending','in_progress','done')" : ($show === 'all' ? '' : "WHERE t.status = 'verified'");
$tasks = q_all("SELECT t.*, r.room_number, r.housekeeping_status, u.full_name staff FROM housekeeping_tasks t JOIN rooms r ON r.id=t.room_id
                LEFT JOIN users u ON u.id=t.assigned_to $filter
                ORDER BY FIELD(t.status,'pending','in_progress','done','verified'), FIELD(t.priority,'urgent','high','normal','low'), t.created_at DESC LIMIT 300");
$rooms = q_all('SELECT r.*, rt.name type_name FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id ORDER BY room_number');
$staff = q_all("SELECT u.id, u.full_name FROM users u JOIN role_permissions rp ON rp.role_id=u.role_id JOIN permissions p ON p.id=rp.permission_id
                WHERE p.slug='housekeeping.work' AND u.is_active=1 ORDER BY u.full_name");
$staffOpts = array_map(fn($s) => [$s['id'], $s['full_name']], $staff);
$counts = q_row("SELECT SUM(housekeeping_status='clean') clean, SUM(housekeeping_status='dirty') dirty, SUM(housekeeping_status='inspected') inspected, SUM(housekeeping_status='out_of_order') ooo FROM rooms");

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="stats">
  <div class="stat" style="--c:var(--green)"><div class="k">Clean</div><div class="v"><?= (int) $counts['clean'] ?></div></div>
  <div class="stat" style="--c:var(--amber)"><div class="k">Dirty</div><div class="v"><?= (int) $counts['dirty'] ?></div></div>
  <div class="stat" style="--c:var(--teal)"><div class="k">Inspected</div><div class="v"><?= (int) $counts['inspected'] ?></div></div>
  <div class="stat" style="--c:var(--red)"><div class="k">Out of order</div><div class="v"><?= (int) $counts['ooo'] ?></div></div>
</div>
<div class="grid g-2-1">
  <div class="card">
    <div class="card-head"><h2>Tasks</h2><div class="tabs" style="margin:0"><?php foreach (['open' => 'Open', 'verified' => 'Verified', 'all' => 'All'] as $k => $l): ?><a class="<?= $show === $k ? 'active' : '' ?>" href="?show=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Room</th><th>Task</th><th>Priority</th><th>Assigned</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($tasks as $t): ?>
      <tr><td><b><?= e($t['room_number']) ?></b></td>
        <td><?= e($taskTypes[$t['task_type']]) ?><div class="small muted"><?= e($t['notes']) ?> · <?= fdatetime($t['created_at']) ?></div></td>
        <td><?= badge($t['priority']) ?></td>
        <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="show" value="<?= e($show) ?>">
          <select name="assigned_to" onchange="this.form.submit()" style="min-width:130px"><option value="">Unassigned</option><?= options($staffOpts, $t['assigned_to']) ?></select></form></td>
        <td><?= badge($t['status']) ?></td>
        <td class="right nowrap"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="show" value="<?= e($show) ?>">
          <?php if ($t['status'] === 'pending'): ?><button class="btn btn-sm btn-ghost" name="status" value="in_progress">Start</button><?php endif; ?>
          <?php if (in_array($t['status'], ['pending', 'in_progress'])): ?><button class="btn btn-sm btn-ghost" name="status" value="done">Done</button><?php endif; ?>
          <?php if ($t['status'] === 'done'): ?><button class="btn btn-sm" name="status" value="verified">Verify ✓</button><?php endif; ?>
        </form></td></tr>
    <?php endforeach; if (!$tasks): ?><tr><td colspan="6" class="empty">No tasks.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <div>
    <div class="card">
      <h2>New task</h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="field"><label>Room</label><select name="room_id" required><?= options(array_map(fn($r) => [$r['id'], $r['room_number'] . ' · ' . $r['type_name']], $rooms)) ?></select></div>
        <div class="form-grid">
          <div class="field"><label>Task</label><select name="task_type"><?= options($taskTypes) ?></select></div>
          <div class="field"><label>Priority</label><select name="priority"><?= options(['normal' => 'Normal', 'low' => 'Low', 'high' => 'High', 'urgent' => 'Urgent']) ?></select></div>
        </div>
        <div class="field"><label>Assign to</label><select name="assigned_to"><option value="">Unassigned</option><?= options($staffOpts) ?></select></div>
        <div class="field"><label>Notes</label><input name="notes"></div>
        <label class="check"><input type="checkbox" name="ooo" value="1"> Maintenance: take room out of order</label>
        <div class="form-actions"><button class="btn">Create task</button></div>
      </form>
    </div>
    <div class="card">
      <h2>Room status board</h2>
      <?php foreach ($rooms as $r): ?>
        <form method="post" style="display:flex;gap:8px;align-items:center;padding:4px 0;border-bottom:1px solid var(--line)"><?= csrf_field() ?><input type="hidden" name="action" value="room_hk"><input type="hidden" name="room_id" value="<?= $r['id'] ?>">
          <b style="width:52px"><?= e($r['room_number']) ?></b><span style="flex:1"><?= badge($r['status']) ?></span>
          <select name="housekeeping_status" onchange="this.form.submit()" style="width:140px"><?= options(['clean' => 'Clean', 'dirty' => 'Dirty', 'inspected' => 'Inspected', 'out_of_order' => 'Out of order'], $r['housekeeping_status']) ?></select>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

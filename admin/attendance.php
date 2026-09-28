<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('staff.manage');
$title = 'Staff Attendance & Time Tracking';
$active = 'attendance';

if (is_post()) {
    $a = input('action');
    try {
        if ($a === 'clock_in') {
            clock_in(input_int('user_id'));
            flash('success', 'Clocked in.');
        } elseif ($a === 'clock_out') {
            clock_out(input_int('user_id'));
            flash('success', 'Clocked out.');
        } elseif ($a === 'save') {
            $in = str_replace('T', ' ', input('clock_in'));
            $out = input('clock_out') !== '' ? str_replace('T', ' ', input('clock_out')) : null;
            if (!strtotime($in) || ($out && strtotime($out) <= strtotime($in))) throw new RuntimeException('Clock-out must be after clock-in.');
            $hours = $out ? round((strtotime($out) - strtotime($in)) / 3600, 2) : null;
            $vals = [date('Y-m-d', strtotime($in)), date('Y-m-d H:i:s', strtotime($in)), $out ? date('Y-m-d H:i:s', strtotime($out)) : null, $hours, input('notes') ?: null];
            if (input_int('id')) q('UPDATE attendance SET work_date=?, clock_in=?, clock_out=?, hours=?, notes=? WHERE id=?', [...$vals, input_int('id')]);
            else q('INSERT INTO attendance (user_id, work_date, clock_in, clock_out, hours, notes) VALUES (?,?,?,?,?,?)', [input_int('user_id'), ...$vals]);
            log_activity('attendance', 'Manual attendance entry saved');
            flash('success', 'Attendance record saved.');
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/attendance.php?' . http_build_query(['from' => input('from'), 'to' => input('to'), 'user' => input('user')]));
}

$from = valid_date(input('from')) ? input('from') : date('Y-m-d', strtotime('-6 day'));
$to = valid_date(input('to')) ? input('to') : date('Y-m-d');
$user = input_int('user');
$staff = q_all('SELECT u.id, u.full_name, u.hourly_rate, r.name role, (SELECT id FROM attendance a WHERE a.user_id=u.id AND a.clock_out IS NULL LIMIT 1) open_id
                FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 ORDER BY u.full_name');
$params = [$from, $to];
$w = 'a.work_date BETWEEN ? AND ?';
if ($user) { $w .= ' AND a.user_id=?'; $params[] = $user; }
$rows = q_all("SELECT a.*, u.full_name, u.hourly_rate FROM attendance a JOIN users u ON u.id=a.user_id WHERE $w ORDER BY a.clock_in DESC", $params);
$summary = q_all("SELECT u.full_name, u.hourly_rate, COUNT(a.id) shifts, COALESCE(SUM(a.hours),0) hours FROM attendance a JOIN users u ON u.id=a.user_id WHERE $w GROUP BY u.id ORDER BY hours DESC", $params);
$editRow = input_int('edit') ? q_row('SELECT * FROM attendance WHERE id=?', [input_int('edit')]) : null;

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="grid g-2-1">
  <div class="card">
    <h2>Duty board — today</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Staff</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($staff as $s): ?>
      <tr><td><?= e($s['full_name']) ?></td><td class="small"><?= e($s['role']) ?></td><td><?= $s['open_id'] ? badge('active') : '<span class="badge badge-gray">Off duty</span>' ?></td>
      <td class="right"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= $s['id'] ?>">
        <?php if ($s['open_id']): ?><button class="btn btn-sm btn-warn" name="action" value="clock_out">Clock out</button><?php else: ?><button class="btn btn-sm btn-ghost" name="action" value="clock_in">Clock in</button><?php endif; ?></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="card">
    <h2><?= $editRow ? 'Edit record' : 'Manual entry' ?></h2>
    <?php $er = $editRow ?: ['id' => 0, 'user_id' => $user, 'clock_in' => date('Y-m-d') . ' 08:00', 'clock_out' => date('Y-m-d') . ' 16:00', 'notes' => '']; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $er['id'] ?>">
      <?php if (!$editRow): ?><div class="field"><label>Staff</label><select name="user_id" required><?= options(array_map(fn($s) => [$s['id'], $s['full_name']], $staff), $er['user_id']) ?></select></div><?php endif; ?>
      <div class="field"><label>Clock in</label><input type="datetime-local" name="clock_in" value="<?= e(date('Y-m-d\TH:i', strtotime($er['clock_in']))) ?>" required></div>
      <div class="field"><label>Clock out</label><input type="datetime-local" name="clock_out" value="<?= $er['clock_out'] ? e(date('Y-m-d\TH:i', strtotime($er['clock_out']))) : '' ?>"></div>
      <div class="field"><label>Notes</label><input name="notes" value="<?= e($er['notes']) ?>"></div>
      <div class="form-actions"><button class="btn">Save</button><?php if ($editRow): ?><a class="btn btn-ghost" href="attendance.php">Cancel</a><?php endif; ?></div>
    </form>
  </div>
</div>
<div class="card">
  <form class="filters"><div class="field"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div><div class="field"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="field"><label>Staff</label><select name="user"><option value="">All staff</option><?= options(array_map(fn($s) => [$s['id'], $s['full_name']], $staff), $user) ?></select></div><button class="btn btn-ghost">Show</button></form>
  <div class="grid g-2-1">
    <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Staff</th><th>In</th><th>Out</th><th class="num">Hours</th><th>Notes</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= fdate($r['work_date']) ?></td><td><?= e($r['full_name']) ?></td><td><?= date('H:i', strtotime($r['clock_in'])) ?></td>
      <td><?= $r['clock_out'] ? date('H:i', strtotime($r['clock_out'])) . (substr($r['clock_out'], 0, 10) !== $r['work_date'] ? ' <span class="small muted">+1</span>' : '') : '<span class="badge badge-green">on duty</span>' ?></td>
      <td class="num"><?= $r['hours'] !== null ? number_format($r['hours'], 2) : '—' ?></td><td class="small"><?= e($r['notes']) ?></td><td><a class="small" href="?edit=<?= $r['id'] ?>">edit</a></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">No records.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div><h3>Summary</h3><table class="table"><thead><tr><th>Staff</th><th class="num">Shifts</th><th class="num">Hours</th><th class="num">Est. pay</th></tr></thead><tbody>
      <?php foreach ($summary as $s): ?><tr><td><?= e($s['full_name']) ?></td><td class="num"><?= $s['shifts'] ?></td><td class="num"><?= number_format($s['hours'], 2) ?></td><td class="num"><?= money($s['hours'] * $s['hourly_rate']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

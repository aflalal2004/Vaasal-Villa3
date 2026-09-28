<?php
/** Staff self-service portal: clock in/out, own housekeeping tasks, own timesheet. */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
$title = 'My Portal';
$active = 'portal';

if (is_post()) {
    try {
        switch (input('action')) {
            case 'clock_in':
                if (!can('attendance.self')) throw new RuntimeException('Not permitted.');
                clock_in((int) $me['id']);
                flash('success', 'Clocked in at ' . date('H:i') . '. Have a great shift!');
                break;
            case 'clock_out':
                if (!can('attendance.self')) throw new RuntimeException('Not permitted.');
                clock_out((int) $me['id']);
                flash('success', 'Clocked out at ' . date('H:i') . '. Thank you!');
                break;
            case 'task':
                if (!can_any(['housekeeping.work', 'housekeeping.manage'])) throw new RuntimeException('Not permitted.');
                hk_set_status(input_int('id'), input('status'), can('housekeeping.manage') ? null : (int) $me['id']);
                flash('success', 'Task updated.');
                break;
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('staff/index.php');
}

$open = q_row('SELECT * FROM attendance WHERE user_id=? AND clock_out IS NULL ORDER BY id DESC LIMIT 1', [$me['id']]);
$sheet = q_all('SELECT * FROM attendance WHERE user_id=? AND work_date >= ? ORDER BY clock_in DESC', [$me['id'], date('Y-m-d', strtotime('-13 day'))]);
$weekHours = (float) q_val('SELECT COALESCE(SUM(hours),0) FROM attendance WHERE user_id=? AND work_date >= ?', [$me['id'], date('Y-m-d', strtotime('monday this week'))]);
$tasks = can('housekeeping.work') ? q_all("SELECT t.*, r.room_number, rt.name type_name, r.status room_status FROM housekeeping_tasks t JOIN rooms r ON r.id=t.room_id JOIN room_types rt ON rt.id=r.room_type_id
                 WHERE t.assigned_to=? AND t.status IN ('pending','in_progress') ORDER BY FIELD(t.priority,'urgent','high','normal','low'), t.created_at", [$me['id']]) : [];
$taskTypes = ['checkout_clean' => 'Check-out clean', 'stayover_clean' => 'Stay-over clean', 'turndown' => 'Turndown', 'deep_clean' => 'Deep clean', 'inspection' => 'Inspection', 'maintenance' => 'Maintenance'];

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="grid g2">
  <div class="card" style="text-align:center">
    <h2>Hello, <?= e(explode(' ', $me['full_name'])[0]) ?> 👋</h2>
    <p class="muted"><?= e($me['role_name']) ?> · <?= date('l, d F Y') ?></p>
    <div style="font-size:2.4rem;font-weight:700;margin:6px 0" id="clk"><?= date('H:i:s') ?></div>
    <?php if (can('attendance.self')): ?>
      <?php if ($open): ?>
        <p>On duty since <b><?= date('H:i', strtotime($open['clock_in'])) ?></b></p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="clock_out"><button class="btn btn-warn" style="font-size:1.1rem;padding:14px 40px">⏹ Clock out</button></form>
      <?php else: ?>
        <p class="muted">You are off duty.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="clock_in"><button class="btn" style="font-size:1.1rem;padding:14px 40px">▶ Clock in</button></form>
      <?php endif; ?>
    <?php endif; ?>
    <p class="small muted" style="margin-top:12px">This week: <b><?= number_format($weekHours, 2) ?> h</b> completed</p>
  </div>
  <div class="card">
    <h2>Quick links</h2>
    <div class="btn-row">
      <?php if (can('pos.use')): ?><a class="btn" href="<?= e(url('pos/index.php')) ?>">🧾 POS Terminal</a><?php endif; ?>
      <?php if (can('kitchen.view')): ?><a class="btn" href="<?= e(url('pos/kitchen.php')) ?>">🍳 Kitchen Display</a><?php endif; ?>
      <?php if (can('dashboard.view')): ?><a class="btn" href="<?= e(url('admin/index.php')) ?>">▦ Dashboard</a><?php endif; ?>
      <?php if (can('frontdesk.manage')): ?><a class="btn" href="<?= e(url('admin/frontdesk.php')) ?>">🛎 Front Desk</a><?php endif; ?>
      <?php if (can('housekeeping.manage')): ?><a class="btn" href="<?= e(url('admin/housekeeping.php')) ?>">🧹 Housekeeping board</a><?php endif; ?>
    </div>
    <h3 style="margin-top:18px">My timesheet (14 days)</h3>
    <table class="table"><thead><tr><th>Date</th><th>In</th><th>Out</th><th class="num">Hours</th></tr></thead><tbody>
    <?php foreach ($sheet as $s): ?><tr><td><?= fdate($s['work_date'], 'D d M') ?></td><td><?= date('H:i', strtotime($s['clock_in'])) ?></td><td><?= $s['clock_out'] ? date('H:i', strtotime($s['clock_out'])) : '—' ?></td><td class="num"><?= $s['hours'] !== null ? number_format($s['hours'], 2) : '…' ?></td></tr><?php endforeach; ?>
    <?php if (!$sheet): ?><tr><td colspan="4" class="empty">No shifts recorded.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php if (can('housekeeping.work')): ?>
<div class="card">
  <h2>My housekeeping tasks (<?= count($tasks) ?>)</h2>
  <div class="rack">
  <?php foreach ($tasks as $t): ?>
    <div class="room-tile <?= $t['priority'] === 'urgent' ? 'maintenance' : 'available' ?>">
      <div style="display:flex;justify-content:space-between"><span class="no"><?= e($t['room_number']) ?></span><?= badge($t['priority']) ?></div>
      <div class="type"><?= e($t['type_name']) ?> · room <?= e($t['room_status']) ?></div>
      <div style="margin:6px 0;font-weight:600"><?= e($taskTypes[$t['task_type']]) ?></div>
      <?php if ($t['notes']): ?><div class="small muted"><?= e($t['notes']) ?></div><?php endif; ?>
      <form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="task"><input type="hidden" name="id" value="<?= $t['id'] ?>">
        <?php if ($t['status'] === 'pending'): ?><button class="btn btn-sm btn-ghost" name="status" value="in_progress">Start</button><?php endif; ?>
        <button class="btn btn-sm" name="status" value="done">✓ Done</button></form>
    </div>
  <?php endforeach; if (!$tasks): ?><p class="muted">No open tasks — nice work!</p><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<script>setInterval(function(){document.getElementById('clk').textContent=new Date().toTimeString().slice(0,8)},1000)</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

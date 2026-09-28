<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('settings.manage');
$title = 'Activity Log';
$active = 'log';

$module = input('module');
$rows = q_all('SELECT l.*, u.full_name FROM activity_log l LEFT JOIN users u ON u.id=l.user_id ' . ($module ? 'WHERE l.module=?' : '') . ' ORDER BY l.id DESC LIMIT 500', $module ? [$module] : []);
$modules = array_column(q_all('SELECT DISTINCT module FROM activity_log ORDER BY module'), 'module');
$logins = q_all('SELECT * FROM login_attempts ORDER BY id DESC LIMIT 30');
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="grid g-2-1">
  <div class="card">
    <div class="tabs"><a class="<?= !$module ? 'active' : '' ?>" href="activity.php">All</a><?php foreach ($modules as $m): ?><a class="<?= $module === $m ? 'active' : '' ?>" href="?module=<?= e(urlencode($m)) ?>"><?= e(label($m)) ?></a><?php endforeach; ?></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Time</th><th>User</th><th>Module</th><th>Action</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td class="nowrap small"><?= fdatetime($r['created_at']) ?></td><td><?= e($r['full_name'] ?? 'System / API') ?></td><td><?= e($r['module']) ?></td><td><?= e($r['action']) ?></td><td class="small muted"><?= e($r['ip']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="card"><h2>Recent sign-in attempts</h2>
    <table class="table"><tbody><?php foreach ($logins as $l): ?><tr><td class="small"><?= fdatetime($l['created_at']) ?></td><td><?= e($l['username']) ?></td><td><?= $l['success'] ? badge('active') : '<span class="badge badge-red">failed</span>' ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

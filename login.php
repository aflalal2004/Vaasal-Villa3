<?php
/** Single sign-in for all staff: admin, front office, POS, kitchen, housekeeping. */
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect(home_path());

$error = null;
$username = '';
if (is_post()) {
    csrf_check();
    $username = input('username');
    $error = attempt_login($username, (string) ($_POST['password'] ?? ''));
    if (!$error) {
        $next = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        // only follow local paths inside this app
        if ($next && str_starts_with($next, BASE_URL . '/') && !str_contains($next, '//') && !str_contains($next, 'logout')) {
            header('Location: ' . $next);
            exit;
        }
        redirect(home_path());
    }
}
$demo = [
    ['admin', 'Administrator'], ['manager', 'General Manager'], ['reception', 'Front Office'], ['cashier', 'Cashier / POS'],
    ['waiter', 'Waiter'], ['kitchen', 'Kitchen (KOT)'], ['housekeep', 'Housekeeping'], ['staff', 'General staff'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff Sign In · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark">V</span><span>Vaasal Villa<small>Staff &amp; Management Sign In</small></span></a>
    <?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="field"><label for="username">Username</label><input id="username" name="username" value="<?= e($username) ?>" required autofocus></div>
      <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required></div>
      <button class="btn" style="width:100%;justify-content:center;margin-top:6px">Sign in</button>
    </form>
    <div class="demo-accounts">
      <b>Demo accounts</b> — password <code>Villa@123</code>
      <table><?php foreach ($demo as [$un, $role]): ?>
        <tr><td><button type="button" onclick="document.getElementById('username').value='<?= e($un) ?>';document.getElementById('password').value='Villa@123'"><?= e($un) ?></button></td><td><?= e($role) ?></td></tr>
      <?php endforeach; ?></table>
    </div>
    <p class="small muted" style="margin-top:14px;text-align:center"><a href="<?= e(url('index.php')) ?>">← Back to website</a></p>
  </div>
</div>
</body>
</html>

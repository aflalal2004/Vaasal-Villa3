<?php
/** Back-office layout (top). Expects $title; optional $active (nav key). */
$__u = current_user();
$active = $active ?? '';
$__nav = [
    ['Operations', [
        ['dashboard',   'admin/index.php',        'Dashboard',        '▦', ['dashboard.view']],
        ['frontdesk',   'admin/frontdesk.php',    'Front Desk',       '🛎', ['frontdesk.manage']],
        ['bookings',    'admin/bookings.php',     'Bookings',         '📅', ['bookings.manage']],
        ['calendar',    'admin/calendar.php',     'Availability',     '🗓', ['bookings.manage', 'frontdesk.manage']],
        ['guests',      'admin/guests.php',       'Guests',           '👤', ['guests.manage']],
        ['keycards',    'admin/keycards.php',     'Key Cards',        '🔑', ['keycards.manage']],
        ['housekeeping','admin/housekeeping.php', 'Housekeeping',     '🧹', ['housekeeping.manage']],
        ['rooms',       'admin/rooms.php',        'Rooms & Types',    '🏠', ['rooms.manage']],
    ]],
    ['Restaurant', [
        ['pos',         'pos/index.php',          'POS Terminal',     '🧾', ['pos.use']],
        ['kitchen',     'pos/kitchen.php',        'Kitchen (KOT)',    '🍳', ['kitchen.view']],
        ['orders',      'admin/orders.php',       'Orders',           '📋', ['pos.settle', 'billing.manage']],
        ['menu',        'admin/menu.php',         'Menu & Tables',    '🍽', ['menu.manage']],
    ]],
    ['Finance', [
        ['invoices',    'admin/invoices.php',     'Invoices',         '📄', ['billing.manage']],
        ['payments',    'admin/payments.php',     'Payments',         '💳', ['billing.manage']],
        ['reports',     'admin/reports.php',      'Reports',          '📈', ['reports.view']],
    ]],
    ['People', [
        ['staff',       'admin/staff.php',        'Staff',            '👥', ['staff.manage']],
        ['attendance',  'admin/attendance.php',   'Attendance',       '⏱', ['staff.manage']],
        ['roles',       'admin/roles.php',        'Roles & Permissions','🛡', ['roles.manage']],
    ]],
    ['System', [
        ['media',       'admin/media.php',        'Website Media',    '🖼', ['settings.manage']],
        ['channels',    'admin/channels.php',     'Booking Channels', '🌐', ['settings.manage']],
        ['messages',    'admin/messages.php',     'Web Enquiries',    '✉', ['dashboard.view']],
        ['log',         'admin/activity.php',     'Activity Log',     '📜', ['settings.manage']],
        ['settings',    'admin/settings.php',     'Settings',         '⚙', ['settings.manage']],
    ]],
];
$__myDuty = $__u ? q_val('SELECT id FROM attendance WHERE user_id = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1', [$__u['id']]) : null;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e(($title ?? 'Dashboard') . ' · ' . APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= e(url(home_path())) ?>"><span class="brand-mark">V</span><span>Vaasal Villa<small>Management System</small></span></a>
  <nav>
  <?php foreach ($__nav as [$__group, $__items]):
      $__visible = array_filter($__items, fn($__i) => can_any($__i[4]));
      if (!$__visible) continue; ?>
    <div class="nav-group"><?= e($__group) ?></div>
    <?php foreach ($__visible as [$__key, $__href, $__text, $__icon]): ?>
      <a class="nav-link<?= $active === $__key ? ' active' : '' ?>" href="<?= e(url($__href)) ?>"><span class="ico"><?= $__icon ?></span><?= e($__text) ?></a>
    <?php endforeach; ?>
  <?php endforeach; ?>
    <div class="nav-group">Me</div>
    <a class="nav-link<?= $active === 'portal' ? ' active' : '' ?>" href="<?= e(url('staff/index.php')) ?>"><span class="ico">🙋</span>My Portal</a>
    <a class="nav-link" href="<?= e(url('index.php')) ?>" target="_blank"><span class="ico">🌴</span>View Website</a>
  </nav>
</aside>
<div class="main">
  <header class="topbar">
    <button class="menu-toggle" onclick="document.body.classList.toggle('nav-open')" aria-label="Menu">☰</button>
    <h1><?= e($title ?? '') ?></h1>
    <div class="topbar-right">
      <?php if (can('attendance.self')): ?>
        <a href="<?= e(url('staff/index.php')) ?>" class="duty <?= $__myDuty ? 'on' : 'off' ?>"><?= $__myDuty ? '● On duty' : '○ Off duty' ?></a>
      <?php endif; ?>
      <div class="user-chip"><b><?= e($__u['full_name'] ?? '') ?></b><small><?= e($__u['role_name'] ?? '') ?></small></div>
      <a class="btn btn-sm btn-ghost" href="<?= e(url('logout.php')) ?>">Sign out</a>
    </div>
  </header>
  <main class="content">
  <?php foreach (flashes() as $__f): ?>
    <div class="alert alert-<?= e($__f['type']) ?>"><?= e($__f['msg']) ?></div>
  <?php endforeach; ?>

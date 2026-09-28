<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('dashboard.view');
$title = 'Dashboard';
$active = 'dashboard';
$today = date('Y-m-d');

$totalRooms  = (int) q_val("SELECT COUNT(*) FROM rooms WHERE status <> 'maintenance'");
$occupied    = (int) q_val("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'");
$arrivals    = q_all("SELECT b.id, b.booking_ref, b.status, CONCAT(g.first_name,' ',g.last_name) guest, rt.name type, s.name source
                      FROM bookings b JOIN guests g ON g.id=b.guest_id JOIN room_types rt ON rt.id=b.room_type_id JOIN booking_sources s ON s.id=b.source_id
                      WHERE b.check_in = ? AND b.status IN ('pending','confirmed') ORDER BY b.id", [$today]);
$departures  = q_all("SELECT b.id, b.booking_ref, CONCAT(g.first_name,' ',g.last_name) guest, r.room_number
                      FROM bookings b JOIN guests g ON g.id=b.guest_id LEFT JOIN rooms r ON r.id=b.room_id
                      WHERE b.check_out <= ? AND b.status = 'checked_in' ORDER BY r.room_number", [$today]);
$revenueToday = (float) q_val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = ?', [$today]);
$restSales   = (float) q_val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='closed' AND DATE(closed_at) = ?", [$today]);
$openOrders  = (int) q_val("SELECT COUNT(*) FROM orders WHERE status = 'open'");
$pendingHK   = (int) q_val("SELECT COUNT(*) FROM housekeeping_tasks WHERE status IN ('pending','in_progress')");
$onDuty      = q_all('SELECT u.full_name, r.name role, a.clock_in FROM attendance a JOIN users u ON u.id=a.user_id JOIN roles r ON r.id=u.role_id WHERE a.clock_out IS NULL ORDER BY a.clock_in');
$newBookings = q_all("SELECT b.id, b.booking_ref, b.check_in, b.check_out, b.status, b.room_total, s.name source, CONCAT(g.first_name,' ',g.last_name) guest
                      FROM bookings b JOIN guests g ON g.id=b.guest_id JOIN booking_sources s ON s.id=b.source_id ORDER BY b.created_at DESC LIMIT 6");
$unread      = (int) q_val('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
$occPct      = $totalRooms ? round($occupied / $totalRooms * 100) : 0;

// 7-day revenue sparkline data
$week = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $week[$d] = (float) q_val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = ?', [$d]);
}
$maxWeek = max(1, max($week));

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="stats">
  <div class="stat" style="--c:var(--blue)"><div class="k">Occupancy</div><div class="v"><?= $occPct ?>%</div><div class="s"><?= $occupied ?> of <?= $totalRooms ?> rooms</div></div>
  <div class="stat" style="--c:var(--green)"><div class="k">Arrivals today</div><div class="v"><?= count($arrivals) ?></div><div class="s">awaiting check-in</div></div>
  <div class="stat" style="--c:var(--amber)"><div class="k">Departures due</div><div class="v"><?= count($departures) ?></div><div class="s">to check out</div></div>
  <div class="stat" style="--c:var(--brand)"><div class="k">Payments today</div><div class="v"><?= money($revenueToday) ?></div><div class="s">all modules</div></div>
  <div class="stat" style="--c:var(--accent)"><div class="k">Restaurant sales</div><div class="v"><?= money($restSales) ?></div><div class="s"><?= $openOrders ?> open order(s)</div></div>
  <div class="stat" style="--c:var(--purple)"><div class="k">Housekeeping</div><div class="v"><?= $pendingHK ?></div><div class="s">open tasks</div></div>
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-head"><h2>Today's arrivals</h2><a class="btn btn-sm btn-ghost" href="frontdesk.php">Front desk →</a></div>
    <table class="table"><thead><tr><th>Ref</th><th>Guest</th><th>Room type</th><th>Source</th><th></th></tr></thead><tbody>
    <?php foreach ($arrivals as $a): ?>
      <tr><td><a href="booking_view.php?id=<?= $a['id'] ?>"><?= e($a['booking_ref']) ?></a></td><td><?= e($a['guest']) ?></td><td><?= e($a['type']) ?></td><td><?= e($a['source']) ?></td>
      <td class="right"><a class="btn btn-sm" href="booking_view.php?id=<?= $a['id'] ?>#checkin">Check in</a></td></tr>
    <?php endforeach; if (!$arrivals): ?><tr><td colspan="5" class="empty">No arrivals pending today.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <div class="card">
    <div class="card-head"><h2>Departures</h2></div>
    <table class="table"><thead><tr><th>Ref</th><th>Guest</th><th>Room</th><th></th></tr></thead><tbody>
    <?php foreach ($departures as $d): ?>
      <tr><td><a href="booking_view.php?id=<?= $d['id'] ?>"><?= e($d['booking_ref']) ?></a></td><td><?= e($d['guest']) ?></td><td><?= e($d['room_number']) ?></td>
      <td class="right"><a class="btn btn-sm btn-warn" href="booking_view.php?id=<?= $d['id'] ?>#checkout">Check out</a></td></tr>
    <?php endforeach; if (!$departures): ?><tr><td colspan="4" class="empty">No departures due.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>

<div class="grid g-2-1">
  <div class="card">
    <div class="card-head"><h2>Latest bookings</h2><a class="btn btn-sm btn-ghost" href="bookings.php">All bookings →</a></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Ref</th><th>Guest</th><th>Stay</th><th>Source</th><th>Status</th><th class="num">Amount</th></tr></thead><tbody>
    <?php foreach ($newBookings as $b): ?>
      <tr><td><a href="booking_view.php?id=<?= $b['id'] ?>"><?= e($b['booking_ref']) ?></a></td><td><?= e($b['guest']) ?></td>
      <td class="nowrap"><?= fdate($b['check_in'], 'd M') ?> → <?= fdate($b['check_out'], 'd M') ?></td><td><?= e($b['source']) ?></td><td><?= badge($b['status']) ?></td><td class="num"><?= money($b['room_total']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div>
    <div class="card">
      <h2>Revenue — last 7 days</h2>
      <svg viewBox="0 0 280 120" width="100%" role="img" aria-label="Revenue last 7 days">
        <?php $i = 0; foreach ($week as $d => $v): $h = $v / $maxWeek * 90; ?>
          <rect x="<?= 8 + $i * 39 ?>" y="<?= 100 - $h ?>" width="26" height="<?= max(1, $h) ?>" rx="4" fill="#0f766e"><title><?= fdate($d) ?>: <?= money($v) ?></title></rect>
          <text x="<?= 21 + $i * 39 ?>" y="115" font-size="9" text-anchor="middle" fill="#667479"><?= date('D', strtotime($d)) ?></text>
        <?php $i++; endforeach; ?>
      </svg>
      <p class="small muted">Payments received across rooms and restaurant.</p>
    </div>
    <div class="card">
      <h2>Staff on duty (<?= count($onDuty) ?>)</h2>
      <?php foreach ($onDuty as $s): ?>
        <div style="display:flex;justify-content:space-between;padding:4px 0"><span><?= e($s['full_name']) ?> <span class="muted small">· <?= e($s['role']) ?></span></span><span class="small muted">since <?= date('H:i', strtotime($s['clock_in'])) ?></span></div>
      <?php endforeach; if (!$onDuty): ?><p class="muted">Nobody is clocked in.</p><?php endif; ?>
    </div>
    <?php if ($unread): ?><div class="alert alert-info">✉ <?= $unread ?> unread website enquiry(ies). <a href="messages.php">Open</a></div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

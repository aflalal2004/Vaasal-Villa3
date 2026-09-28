<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('frontdesk.manage');
$title = 'Front Desk';
$active = 'frontdesk';
$today = date('Y-m-d');

if (is_post() && input('action') === 'night_audit_all') {
    $total = 0;
    foreach (q_all("SELECT id FROM bookings WHERE status = 'checked_in'") as $r) $total += post_room_charges((int) $r['id']);
    log_activity('frontdesk', "Night audit posted $total room night(s)");
    flash('success', "Night audit complete: $total room night(s) posted.");
    redirect('admin/frontdesk.php');
}

$tab = input('tab', 'arrivals');
$lists = [
    'arrivals'   => ["b.check_in <= ? AND b.status IN ('pending','confirmed') AND b.check_out > ?", [$today, $today]],
    'inhouse'    => ["b.status = 'checked_in'", []],
    'departures' => ["b.status = 'checked_in' AND b.check_out <= ?", [$today]],
    'upcoming'   => ["b.check_in > ? AND b.status IN ('pending','confirmed')", [$today]],
];
[$cond, $params] = $lists[$tab] ?? $lists['arrivals'];
$rows = q_all("SELECT b.*, CONCAT(g.first_name,' ',g.last_name) guest, g.vip, rt.name room_type, r.room_number, s.name source_name
               FROM bookings b JOIN guests g ON g.id=b.guest_id JOIN room_types rt ON rt.id=b.room_type_id
               JOIN booking_sources s ON s.id=b.source_id LEFT JOIN rooms r ON r.id=b.room_id
               WHERE $cond ORDER BY b.check_in, r.room_number LIMIT 200", $params);
$counts = [];
foreach ($lists as $k => [$c, $p]) {
    $counts[$k] = (int) q_val("SELECT COUNT(*) FROM bookings b WHERE $c", $p);
}
$rooms = q_all("SELECT r.*, rt.name type_name,
                  (SELECT CONCAT(g.first_name,' ',g.last_name,'|',b.id,'|',b.check_out) FROM bookings b JOIN guests g ON g.id=b.guest_id
                   WHERE b.room_id=r.id AND b.status='checked_in' LIMIT 1) AS occupant
                FROM rooms r JOIN room_types rt ON rt.id=r.room_type_id ORDER BY r.room_number");

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="card-head">
  <div class="tabs" style="margin:0">
    <?php foreach (['arrivals' => 'Arrivals', 'inhouse' => 'In-house', 'departures' => 'Departures', 'upcoming' => 'Upcoming'] as $k => $l): ?>
      <a class="<?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= $l ?> (<?= $counts[$k] ?>)</a>
    <?php endforeach; ?>
  </div>
  <div class="btn-row">
    <a class="btn" href="booking_form.php">+ Walk-in / New booking</a>
    <form method="post" class="inline-form" data-confirm="Post room charges for all in-house guests up to today?"><?= csrf_field() ?><input type="hidden" name="action" value="night_audit_all"><button class="btn btn-ghost">Run night audit</button></form>
  </div>
</div>
<div class="card" style="margin-top:14px">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Ref</th><th>Guest</th><th>Room</th><th>Stay</th><th>Source</th><th>Status</th><th>Payment</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $b): ?>
      <tr>
        <td><a href="booking_view.php?id=<?= $b['id'] ?>"><?= e($b['booking_ref']) ?></a></td>
        <td><?= e($b['guest']) ?> <?= $b['vip'] ? '<span class="badge badge-purple">VIP</span>' : '' ?></td>
        <td><?= $b['room_number'] ? '<b>' . e($b['room_number']) . '</b>' : '<span class="muted">—</span>' ?> <span class="small muted"><?= e($b['room_type']) ?></span></td>
        <td class="nowrap"><?= fdate($b['check_in'], 'd M') ?> → <?= fdate($b['check_out'], 'd M') ?></td>
        <td><?= e($b['source_name']) ?></td><td><?= badge($b['status']) ?></td><td><?= badge($b['payment_status']) ?></td>
        <td class="right">
          <?php if ($tab === 'arrivals'): ?><a class="btn btn-sm" href="booking_view.php?id=<?= $b['id'] ?>#checkin">Check in</a>
          <?php elseif ($b['status'] === 'checked_in'): ?><a class="btn btn-sm btn-warn" href="booking_view.php?id=<?= $b['id'] ?>#checkout">Folio / Check out</a>
          <?php else: ?><a class="btn btn-sm btn-ghost" href="booking_view.php?id=<?= $b['id'] ?>">Open</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">Nothing here.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="card">
  <div class="card-head"><h2>Room rack</h2><span class="small muted">Top colour = room status · badge = housekeeping</span></div>
  <div class="rack">
    <?php foreach ($rooms as $r):
        $occ = $r['occupant'] ? explode('|', $r['occupant']) : null; ?>
      <div class="room-tile <?= e($r['status']) ?>">
        <div style="display:flex;justify-content:space-between;align-items:center"><span class="no"><?= e($r['room_number']) ?></span><?= badge($r['housekeeping_status']) ?></div>
        <div class="type"><?= e($r['type_name']) ?></div>
        <?php if ($occ): ?>
          <div class="guest">👤 <a href="booking_view.php?id=<?= (int) $occ[1] ?>"><?= e($occ[0]) ?></a><div class="small muted">out <?= fdate($occ[2], 'd M') ?></div></div>
        <?php else: ?><div class="guest muted"><?= e(label($r['status'])) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

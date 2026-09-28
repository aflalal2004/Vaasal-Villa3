<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm(['bookings.manage', 'frontdesk.manage']);
$title = 'Availability Calendar';
$active = 'calendar';

$start = valid_date(input('start')) ? input('start') : date('Y-m-d');
$days = 14;
$end = date('Y-m-d', strtotime("$start +$days day"));
$dates = [];
for ($i = 0; $i < $days; $i++) $dates[] = date('Y-m-d', strtotime("$start +$i day"));

$types = q_all('SELECT * FROM room_types ORDER BY base_rate');
$rooms = q_all('SELECT * FROM rooms ORDER BY room_number');
$bookings = q_all("SELECT b.id, b.booking_ref, b.room_id, b.room_type_id, b.check_in, b.check_out, b.status, g.last_name
                   FROM bookings b JOIN guests g ON g.id=b.guest_id
                   WHERE b.status IN ('pending','confirmed','checked_in') AND b.check_in < ? AND b.check_out > ?", [$end, $start]);

// index: room_id => date => booking ; unassigned per type
$grid = [];
$unassigned = [];
foreach ($bookings as $bk) {
    foreach ($dates as $d) {
        if ($d >= $bk['check_in'] && $d < $bk['check_out']) {
            if ($bk['room_id']) $grid[$bk['room_id']][$d] = $bk;
            else $unassigned[$bk['room_type_id']][$d][] = $bk;
        }
    }
}
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="card">
  <div class="card-head">
    <h2><?= fdate($start) ?> – <?= fdate(date('Y-m-d', strtotime("$end -1 day"))) ?></h2>
    <div class="btn-row">
      <a class="btn btn-ghost btn-sm" href="?start=<?= date('Y-m-d', strtotime("$start -$days day")) ?>">← Prev</a>
      <a class="btn btn-ghost btn-sm" href="?start=<?= date('Y-m-d') ?>">Today</a>
      <a class="btn btn-ghost btn-sm" href="?start=<?= $end ?>">Next →</a>
    </div>
  </div>
  <div class="table-wrap">
  <table class="cal">
    <thead><tr><th class="room">Room</th>
      <?php foreach ($dates as $d): $w = in_array(date('N', strtotime($d)), ['6', '7']); ?>
        <th class="<?= $w ? 'wknd' : '' ?> <?= $d === date('Y-m-d') ? 'today' : '' ?>"><?= date('D', strtotime($d)) ?><br><?= date('d', strtotime($d)) ?></th>
      <?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($types as $t): ?>
      <tr><th class="room" colspan="<?= $days + 1 ?>" style="background:#f0fdfa"><?= e($t['name']) ?> <span class="muted small">· <?= money($t['base_rate']) ?>/night</span></th></tr>
      <tr><th class="room small muted">Available</th>
        <?php foreach ($dates as $d): $av = type_availability((int) $t['id'], $d, date('Y-m-d', strtotime("$d +1 day"))); ?>
          <td style="font-weight:700;color:<?= $av ? '#16a34a' : '#dc2626' ?>"><?php if ($av): ?><a href="booking_form.php?room_type_id=<?= $t['id'] ?>&check_in=<?= $d ?>&check_out=<?= date('Y-m-d', strtotime("$d +1 day")) ?>" title="Book"><?= $av ?></a><?php else: ?>0<?php endif; ?></td>
        <?php endforeach; ?></tr>
      <?php foreach ($rooms as $r): if ((int) $r['room_type_id'] !== (int) $t['id']) continue; ?>
        <tr><th class="room"><?= e($r['room_number']) ?> <?= $r['status'] === 'maintenance' ? '<span class="badge badge-red">OOO</span>' : '' ?></th>
          <?php foreach ($dates as $d): $bk = $grid[$r['id']][$d] ?? null; ?>
            <?php if ($bk): ?><td class="bk st-<?= e($bk['status']) ?>" title="<?= e($bk['booking_ref'] . ' · ' . $bk['last_name']) ?>"><a href="booking_view.php?id=<?= $bk['id'] ?>"><?= e($bk['last_name']) ?></a></td>
            <?php else: ?><td class="<?= in_array(date('N', strtotime($d)), ['6', '7']) ? 'wknd' : '' ?>"></td><?php endif; ?>
          <?php endforeach; ?></tr>
      <?php endforeach; ?>
      <?php if (!empty($unassigned[$t['id']])): ?>
        <tr class="unassigned"><th class="room small">Unassigned</th>
        <?php foreach ($dates as $d): $list = $unassigned[$t['id']][$d] ?? []; ?>
          <td class="small"><?php foreach ($list as $bk): ?><a href="booking_view.php?id=<?= $bk['id'] ?>" title="<?= e($bk['booking_ref']) ?>"><?= e(mb_substr($bk['last_name'], 0, 5)) ?></a> <?php endforeach; ?></td>
        <?php endforeach; ?></tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="legend"><span style="--c:#f59e0b">Pending</span><span style="--c:#3b82f6">Confirmed</span><span style="--c:#16a34a">Checked in</span><span style="--c:#fffbeb">Unassigned booking</span></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

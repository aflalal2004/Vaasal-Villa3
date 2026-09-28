<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('bookings.manage');
$title = 'Bookings';
$active = 'bookings';

$status = input('status');
$source = input_int('source');
$from = input('from');
$to = input('to');
$search = input('q');

$where = ['1=1'];
$params = [];
if ($status !== '') { $where[] = 'b.status = ?'; $params[] = $status; }
if ($source) { $where[] = 'b.source_id = ?'; $params[] = $source; }
if (valid_date($from)) { $where[] = 'b.check_out >= ?'; $params[] = $from; }
if (valid_date($to)) { $where[] = 'b.check_in <= ?'; $params[] = $to; }
if ($search !== '') {
    $where[] = '(b.booking_ref LIKE ? OR b.external_ref LIKE ? OR CONCAT(g.first_name," ",g.last_name) LIKE ? OR g.email LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$rows = q_all('SELECT b.*, CONCAT(g.first_name," ",g.last_name) guest, rt.name room_type, r.room_number, s.name source_name
               FROM bookings b JOIN guests g ON g.id=b.guest_id JOIN room_types rt ON rt.id=b.room_type_id
               JOIN booking_sources s ON s.id=b.source_id LEFT JOIN rooms r ON r.id=b.room_id
               WHERE ' . implode(' AND ', $where) . ' ORDER BY b.check_in DESC, b.id DESC LIMIT 300', $params);
$sources = q_all('SELECT id, name FROM booking_sources ORDER BY name');
$statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'];

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="card">
  <div class="card-head"><h2>All bookings</h2><a class="btn" href="booking_form.php">+ New booking</a></div>
  <form class="filters" method="get">
    <div class="field"><label>Search</label><input name="q" value="<?= e($search) ?>" placeholder="Ref, guest, email, OTA ref"></div>
    <div class="field"><label>Status</label><select name="status"><option value="">All</option><?= options(array_combine($statuses, array_map('label', $statuses)), $status) ?></select></div>
    <div class="field"><label>Source</label><select name="source"><option value="">All</option><?= options(array_map(fn($s) => [$s['id'], $s['name']], $sources), $source) ?></select></div>
    <div class="field"><label>Staying from</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label>to</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-ghost">Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Ref</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Pax</th><th>Source</th><th>Status</th><th>Payment</th><th class="num">Room total</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $b): ?>
      <tr>
        <td><a href="booking_view.php?id=<?= $b['id'] ?>"><b><?= e($b['booking_ref']) ?></b></a><?php if ($b['external_ref']): ?><div class="small muted"><?= e($b['external_ref']) ?></div><?php endif; ?></td>
        <td><?= e($b['guest']) ?></td>
        <td><?= e($b['room_type']) ?><div class="small muted"><?= $b['room_number'] ? 'Room ' . e($b['room_number']) : 'Unassigned' ?></div></td>
        <td class="nowrap"><?= fdate($b['check_in']) ?></td><td class="nowrap"><?= fdate($b['check_out']) ?></td>
        <td><?= (int) $b['adults'] ?>+<?= (int) $b['children'] ?></td>
        <td><?= e($b['source_name']) ?></td><td><?= badge($b['status']) ?></td><td><?= badge($b['payment_status']) ?></td>
        <td class="num"><?= money($b['room_total']) ?></td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="10" class="empty">No bookings match your filters.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

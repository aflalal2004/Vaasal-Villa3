<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('reports.view');
$title = 'Reports';
$active = 'reports';

$from = valid_date(input('from')) ? input('from') : date('Y-m-01');
$to = valid_date(input('to')) ? input('to') : date('Y-m-d');
if ($to < $from) [$from, $to] = [$to, $from];
if (nights_between($from, $to) > 366) $from = date('Y-m-d', strtotime("$to -366 day"));
$rng = [$from, $to];

// ---- revenue
$roomRev   = (float) q_val("SELECT COALESCE(SUM(amount),0) FROM folio_charges WHERE charge_type='room' AND DATE(posted_at) BETWEEN ? AND ?", $rng);
$extrasRev = (float) q_val("SELECT COALESCE(SUM(amount),0) FROM folio_charges WHERE charge_type NOT IN ('room','restaurant') AND DATE(posted_at) BETWEEN ? AND ?", $rng);
$restRev   = (float) q_val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='closed' AND DATE(closed_at) BETWEEN ? AND ?", $rng);
$payments  = (float) q_val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) BETWEEN ? AND ?', $rng);

// ---- occupancy (room nights)
$roomCount = (int) q_val("SELECT COUNT(*) FROM rooms WHERE status <> 'maintenance'");
$daily = [];
$sold = 0;
for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime("$d +1 day"))) {
    $n = (int) q_val("SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed','checked_in','checked_out') AND check_in <= ? AND check_out > ?", [$d, $d]);
    $arr = (int) q_val("SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed','checked_in','checked_out') AND check_in = ?", [$d]);
    $dep = (int) q_val("SELECT COUNT(*) FROM bookings WHERE status IN ('checked_in','checked_out') AND check_out = ?", [$d]);
    $daily[$d] = ['occ' => $n, 'arr' => $arr, 'dep' => $dep];
    $sold += $n;
}
$days = count($daily);
$available = max(1, $roomCount * $days);
$occPct = round($sold / $available * 100, 1);
// ADR from stays overlapping the range (weighted by nights inside range)
$stays = q_all("SELECT rate_per_night, GREATEST(check_in, ?) s, LEAST(check_out, DATE_ADD(?, INTERVAL 1 DAY)) e FROM bookings
                WHERE status IN ('confirmed','checked_in','checked_out') AND check_in <= ? AND check_out > ?", [$from, $to, $to, $from]);
$nightRev = 0;
foreach ($stays as $s) $nightRev += $s['rate_per_night'] * nights_between($s['s'], $s['e']);
$adr = $sold ? $nightRev / $sold : 0;
$revpar = $nightRev / $available;

// ---- breakdowns
$bySource = q_all("SELECT s.name, s.type, s.commission_pct, COUNT(b.id) n, COALESCE(SUM(b.room_total),0) rev
                   FROM booking_sources s LEFT JOIN bookings b ON b.source_id=s.id AND b.status NOT IN ('cancelled','no_show') AND DATE(b.created_at) BETWEEN ? AND ?
                   GROUP BY s.id ORDER BY rev DESC", $rng);
$byMethod = q_all('SELECT method, COUNT(*) n, SUM(amount) total FROM payments WHERE DATE(paid_at) BETWEEN ? AND ? GROUP BY method ORDER BY total DESC', $rng);
$byType = q_all("SELECT order_type, COUNT(*) n, SUM(total) total FROM orders WHERE status='closed' AND DATE(closed_at) BETWEEN ? AND ? GROUP BY order_type", $rng);
$topItems = q_all("SELECT oi.item_name, SUM(oi.qty) qty, SUM(oi.line_total) rev FROM order_items oi JOIN orders o ON o.id=oi.order_id
                   WHERE o.status='closed' AND oi.status<>'cancelled' AND DATE(o.closed_at) BETWEEN ? AND ? GROUP BY oi.item_name ORDER BY qty DESC LIMIT 10", $rng);
$staffHours = q_all('SELECT u.full_name, r.name role, COUNT(a.id) shifts, COALESCE(SUM(a.hours),0) hrs, COALESCE(SUM(a.hours),0)*u.hourly_rate cost
                     FROM attendance a JOIN users u ON u.id=a.user_id JOIN roles r ON r.id=u.role_id WHERE a.work_date BETWEEN ? AND ? GROUP BY u.id ORDER BY hrs DESC', $rng);
$hk = q_row("SELECT COUNT(*) n, SUM(status IN ('done','verified')) done, AVG(CASE WHEN completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, completed_at) END) avg_min
             FROM housekeeping_tasks WHERE DATE(created_at) BETWEEN ? AND ?", $rng);

// CSV export of daily figures
if (input('export') === 'daily') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="vaasal-daily-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Rooms occupied', 'Occupancy %', 'Arrivals', 'Departures']);
    foreach ($daily as $d => $v) fputcsv($out, [$d, $v['occ'], $roomCount ? round($v['occ'] / $roomCount * 100, 1) : 0, $v['arr'], $v['dep']]);
    exit;
}

require __DIR__ . '/../includes/admin_header.php';
$bar = function (float $v, float $max, string $color = '#0f766e') {
    $w = $max > 0 ? max(2, round($v / $max * 100)) : 0;
    return '<div style="background:#eef2f1;border-radius:4px;height:8px;min-width:80px"><div style="width:' . $w . '%;height:8px;border-radius:4px;background:' . $color . '"></div></div>';
};
?>
<div class="card no-print">
  <form class="filters" style="margin:0"><div class="field"><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div><div class="field"><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn">Run report</button>
    <a class="btn btn-ghost" href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>">Today</a>
    <a class="btn btn-ghost" href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-t') ?>">This month</a>
    <a class="btn btn-ghost" href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-12-31') ?>">This year</a>
    <button type="button" class="btn btn-ghost" onclick="print()">🖨 Print</button>
    <a class="btn btn-ghost" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=daily">⬇ CSV</a></form>
</div>
<h2><?= fdate($from) ?> – <?= fdate($to) ?> <span class="muted small">(<?= $days ?> days)</span></h2>
<div class="stats">
  <div class="stat" style="--c:var(--blue)"><div class="k">Occupancy</div><div class="v"><?= $occPct ?>%</div><div class="s"><?= $sold ?> / <?= $available ?> room nights</div></div>
  <div class="stat" style="--c:var(--brand)"><div class="k">ADR</div><div class="v"><?= money($adr) ?></div><div class="s">average daily rate</div></div>
  <div class="stat" style="--c:var(--purple)"><div class="k">RevPAR</div><div class="v"><?= money($revpar) ?></div><div class="s">revenue per available room</div></div>
  <div class="stat" style="--c:var(--green)"><div class="k">Room revenue</div><div class="v"><?= money($roomRev) ?></div><div class="s">posted to folios</div></div>
  <div class="stat" style="--c:var(--accent)"><div class="k">Restaurant</div><div class="v"><?= money($restRev) ?></div><div class="s">closed orders</div></div>
  <div class="stat" style="--c:var(--amber)"><div class="k">Other services</div><div class="v"><?= money($extrasRev) ?></div><div class="s">spa, laundry, tours…</div></div>
  <div class="stat"><div class="k">Payments received</div><div class="v"><?= money($payments) ?></div></div>
</div>

<div class="grid g2">
  <div class="card"><h2>Bookings by channel</h2>
    <?php $mx = max(array_merge([1], array_column($bySource, 'rev'))); ?>
    <table class="table"><thead><tr><th>Source</th><th class="num">Bookings</th><th class="num">Room revenue</th><th class="num">Commission</th><th></th></tr></thead><tbody>
    <?php foreach ($bySource as $s): ?><tr><td><?= e($s['name']) ?> <span class="small muted"><?= e(label($s['type'])) ?></span></td><td class="num"><?= $s['n'] ?></td><td class="num"><?= money($s['rev']) ?></td><td class="num"><?= money($s['rev'] * $s['commission_pct'] / 100) ?></td><td><?= $bar((float) $s['rev'], (float) $mx) ?></td></tr><?php endforeach; ?>
    </tbody></table><p class="small muted">By booking creation date.</p></div>
  <div class="card"><h2>Payments by method</h2>
    <?php $mx = max(array_merge([1], array_column($byMethod, 'total'))); ?>
    <table class="table"><thead><tr><th>Method</th><th class="num">Count</th><th class="num">Amount</th><th></th></tr></thead><tbody>
    <?php foreach ($byMethod as $m): ?><tr><td><?= e(label($m['method'])) ?></td><td class="num"><?= $m['n'] ?></td><td class="num"><?= money($m['total']) ?></td><td><?= $bar((float) $m['total'], (float) $mx, '#c08a3e') ?></td></tr><?php endforeach; if (!$byMethod): ?><tr><td colspan="4" class="empty">No payments.</td></tr><?php endif; ?>
    </tbody></table>
    <h3 style="margin-top:16px">Restaurant by order type</h3>
    <table class="table"><tbody><?php foreach ($byType as $t): ?><tr><td><?= e(label($t['order_type'])) ?></td><td class="num"><?= $t['n'] ?> orders</td><td class="num"><?= money($t['total']) ?></td></tr><?php endforeach; if (!$byType): ?><tr><td class="empty">No closed orders.</td></tr><?php endif; ?></tbody></table>
  </div>
</div>
<div class="grid g2">
  <div class="card"><h2>Top menu items</h2>
    <table class="table"><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Revenue</th></tr></thead><tbody>
    <?php foreach ($topItems as $t): ?><tr><td><?= e($t['item_name']) ?></td><td class="num"><?= $t['qty'] ?></td><td class="num"><?= money($t['rev']) ?></td></tr><?php endforeach; if (!$topItems): ?><tr><td colspan="3" class="empty">No sales.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="card"><h2>Staff hours &amp; labour cost</h2>
    <table class="table"><thead><tr><th>Staff</th><th class="num">Shifts</th><th class="num">Hours</th><th class="num">Cost</th></tr></thead><tbody>
    <?php foreach ($staffHours as $s): ?><tr><td><?= e($s['full_name']) ?> <span class="small muted"><?= e($s['role']) ?></span></td><td class="num"><?= $s['shifts'] ?></td><td class="num"><?= number_format($s['hrs'], 1) ?></td><td class="num"><?= money($s['cost']) ?></td></tr><?php endforeach; if (!$staffHours): ?><tr><td colspan="4" class="empty">No attendance.</td></tr><?php endif; ?>
    </tbody></table>
    <p class="small muted">Housekeeping: <?= (int) $hk['n'] ?> tasks created, <?= (int) $hk['done'] ?> completed<?= $hk['avg_min'] ? ', avg ' . round($hk['avg_min']) . ' min to complete' : '' ?>.</p></div>
</div>
<div class="card"><h2>Daily occupancy</h2>
  <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th class="num">Occupied</th><th class="num">Occ %</th><th></th><th class="num">Arrivals</th><th class="num">Departures</th></tr></thead><tbody>
  <?php foreach ($daily as $d => $v): $p = $roomCount ? round($v['occ'] / $roomCount * 100) : 0; ?>
    <tr><td><?= fdate($d, 'D d M') ?></td><td class="num"><?= $v['occ'] ?></td><td class="num"><?= $p ?>%</td><td style="width:40%"><?= $bar((float) $v['occ'], (float) max(1, $roomCount), '#2563eb') ?></td><td class="num"><?= $v['arr'] ?></td><td class="num"><?= $v['dep'] ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

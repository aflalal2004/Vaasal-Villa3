<?php
/** Room type detail: photo slideshow, facts, amenities, live availability check. */
require __DIR__ . '/includes/bootstrap.php';
$page = 'rooms';

$t = q_row('SELECT * FROM room_types WHERE slug = ? AND is_active = 1', [input('type')]);
if (!$t) { http_response_code(404); $pageTitle = 'Room not found'; require __DIR__ . '/includes/site_header.php';
    site_page_head('Room not found', 'This room type does not exist or is no longer available. <a style="color:#f3d9a8" href="' . e(url('rooms.php')) . '">See all rooms</a>');
    require __DIR__ . '/includes/site_footer.php'; exit; }
$pageTitle = $t['name'];

$slides = [];
if ($t['image_url']) $slides[] = ['image_url' => $t['image_url'], 'caption' => $t['name']];
foreach (q_all('SELECT image_url, caption FROM gallery_images WHERE room_type_id = ? AND is_active = 1 ORDER BY sort_order, id', [$t['id']]) as $g) $slides[] = $g;

$in = valid_date(input('check_in')) ? input('check_in') : date('Y-m-d');
$out = valid_date(input('check_out')) && input('check_out') > $in ? input('check_out') : date('Y-m-d', strtotime("$in +2 day"));
$avail = $in >= date('Y-m-d') ? type_availability((int) $t['id'], $in, $out) : 0;
$nights = nights_between($in, $out);
$others = q_all('SELECT * FROM room_types WHERE is_active = 1 AND id <> ? ORDER BY base_rate LIMIT 3', [$t['id']]);

require __DIR__ . '/includes/site_header.php';
site_page_head($t['name'], e($t['short_desc']), $t['image_url']);
?>
<section class="section" style="padding-top:50px">
  <div class="wrap grid" style="grid-template-columns:1.6fr 1fr;align-items:start" id="roomLayout">
    <div class="reveal">
      <?php if ($slides): ?>
      <div class="slider">
        <?php foreach ($slides as $i => $s): ?>
          <div class="slide<?= $i ? '' : ' on' ?>"><img src="<?= e(media_sized($s['image_url'], 1400)) ?>" alt="<?= e($s['caption']) ?>" data-full="<?= e(media_sized($s['image_url'], 1920)) ?>" data-caption="<?= e($s['caption']) ?>" data-group="room" <?= $i ? 'loading="lazy"' : '' ?>></div>
        <?php endforeach; ?>
        <button class="nav-btn p" type="button" aria-label="Previous photo">‹</button><button class="nav-btn n" type="button" aria-label="Next photo">›</button>
        <div class="dots"><?php foreach ($slides as $i => $s): ?><button type="button" aria-label="Photo <?= $i + 1 ?>"></button><?php endforeach; ?></div>
      </div>
      <?php if (count($slides) > 1): ?><div class="thumbs"><?php foreach ($slides as $s): ?><img src="<?= e(media_sized($s['image_url'], 200)) ?>" alt="" loading="lazy"><?php endforeach; ?></div><?php endif; ?>
      <?php endif; ?>
      <div class="facts">
        <div><b><?= (int) $t['size_sqm'] ?> m²</b><span>Size</span></div>
        <div><b><?= e($t['bed_type']) ?></b><span>Bed</span></div>
        <div><b><?= (int) $t['max_adults'] ?></b><span>Adults</span></div>
        <div><b><?= (int) $t['max_children'] ?></b><span>Children</span></div>
      </div>
      <h2>About this <?= stripos($t['name'], 'villa') !== false ? 'villa' : 'room' ?></h2>
      <p class="lead"><?= e($t['description']) ?></p>
      <h3 style="margin-top:24px">Amenities</h3>
      <div class="chips"><?php foreach (array_filter(explode(',', (string) $t['amenities'])) as $a): ?><span class="chip"><?= e(trim($a)) ?></span><?php endforeach; ?></div>
    </div>
    <aside class="summary reveal right" style="position:sticky;top:90px">
      <div class="price"><?= money($t['base_rate']) ?> <small>/ night</small></div>
      <form method="get" style="margin-top:14px">
        <input type="hidden" name="type" value="<?= e($t['slug']) ?>">
        <div class="f" style="margin-bottom:10px"><label>Check-in</label><input type="date" name="check_in" data-min-today value="<?= e($in) ?>"></div>
        <div class="f" style="margin-bottom:10px"><label>Check-out</label><input type="date" name="check_out" data-min-today value="<?= e($out) ?>"></div>
        <button class="btn ghost" style="width:100%">Check availability</button>
      </form>
      <p class="avail <?= $avail ? 'ok' : 'no' ?>" style="margin:14px 0 6px"><?= $avail ? '✔ ' . $avail . ' available · ' . $nights . ' night(s) = ' . money($t['base_rate'] * $nights) : '✖ Not available for these dates' ?></p>
      <?php if ($avail): ?><a class="btn gold" style="width:100%;text-align:center" href="<?= e(url('book.php?' . http_build_query(['room_type_id' => $t['id'], 'check_in' => $in, 'check_out' => $out]))) ?>">Book this <?= stripos($t['name'], 'villa') !== false ? 'villa' : 'room' ?></a><?php endif; ?>
      <p class="small" style="margin-top:14px">✔ Instant confirmation · ✔ Pay on arrival · ✔ Free cancellation up to 48h</p>
    </aside>
  </div>
</section>
<?php if ($others): ?>
<section class="section alt">
  <div class="wrap center reveal"><h2>You may also like</h2></div>
  <div class="wrap grid g3" style="margin-top:30px">
    <?php foreach ($others as $i => $o): ?>
      <article class="room-card reveal" style="--d:<?= $i * .12 ?>s">
        <a class="room-img<?= $o['image_url'] ? ' photo' : '' ?>" style="--c:<?= e($o['color']) ?>" href="<?= e(url('room.php?type=' . urlencode($o['slug']))) ?>"><?php if ($o['image_url']): ?><img src="<?= e(media_sized($o['image_url'], 800)) ?>" alt="<?= e($o['name']) ?>" loading="lazy"><?php endif; ?><span><?= money($o['base_rate']) ?> / night</span></a>
        <div class="room-body"><h3><?= e($o['name']) ?></h3><p><?= e($o['short_desc']) ?></p></div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<style>@media (max-width:960px){#roomLayout{grid-template-columns:1fr!important}#roomLayout aside{position:static!important}}</style>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

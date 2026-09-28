<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Rooms & Availability';
$page = 'rooms';

$in = input('check_in');
$out = input('check_out');
$adults = max(1, input_int('adults', 2));
$children = max(0, input_int('children'));
$searched = valid_date($in) && valid_date($out);
$error = null;
if ($searched && ($out <= $in || $in < date('Y-m-d'))) { $error = 'Please choose a check-in date from today and a check-out date after it.'; $searched = false; }
$types = $searched ? search_availability($in, $out, $adults, $children) : q_all('SELECT * FROM room_types WHERE is_active = 1 ORDER BY base_rate');

require __DIR__ . '/includes/site_header.php';
?>
<?php site_page_head('Rooms & Villas', $searched ? fdate($in, 'D d M Y') . ' → ' . fdate($out, 'D d M Y') . ' · ' . nights_between($in, $out) . ' night(s) · ' . $adults . ' adult(s)' . ($children ? ", $children child(ren)" : '') : 'Choose your dates to see live availability.',
    q_val("SELECT image_url FROM gallery_images WHERE is_active = 1 AND category = 'villa' ORDER BY sort_order LIMIT 1 OFFSET 1")); ?>
<form class="booking-bar" style="margin-top:-30px" method="get">
  <div><label>Check-in</label><input type="date" name="check_in" data-min-today value="<?= e($in ?: date('Y-m-d')) ?>" required></div>
  <div><label>Check-out</label><input type="date" name="check_out" data-min-today value="<?= e($out ?: date('Y-m-d', strtotime('+1 day'))) ?>" required></div>
  <div><label>Adults</label><select name="adults"><?= options([1 => '1', 2 => '2', 3 => '3', 4 => '4'], $adults) ?></select></div>
  <div><label>Children</label><select name="children"><?= options([0 => '0', 1 => '1', 2 => '2', 3 => '3'], $children) ?></select></div>
  <button class="btn">Check availability</button>
</form>
<section class="section" style="padding-top:50px">
  <div class="wrap">
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="grid g2">
    <?php foreach ($types as $t):
        $ok = !$searched || ($t['available'] > 0 && $t['fits']);
        $q = http_build_query(array_filter(['room_type_id' => $t['id'], 'check_in' => $in, 'check_out' => $out, 'adults' => $adults, 'children' => $children])); ?>
      <article class="room-card">
        <a class="room-img<?= $t['image_url'] ? ' photo' : '' ?>" style="--c:<?= e($t['color']) ?>;height:260px" href="<?= e(url('room.php?type=' . urlencode($t['slug']) . ($searched ? '&check_in=' . $in . '&check_out=' . $out : ''))) ?>">
          <?php if ($t['image_url']): ?><img src="<?= e(media_sized($t['image_url'], 900)) ?>" alt="<?= e($t['name']) ?>" loading="lazy"><?php endif; ?>
          <span><?= (int) $t['size_sqm'] ?> m² · <?= e($t['bed_type']) ?> · up to <?= (int) $t['max_adults'] ?> adults</span></a>
        <div class="room-body">
          <h3><a href="<?= e(url('room.php?type=' . urlencode($t['slug']))) ?>" style="color:inherit;text-decoration:none"><?= e($t['name']) ?></a></h3>
          <p><?= e($t['description']) ?></p>
          <div class="chips"><?php foreach (array_filter(explode(',', (string) $t['amenities'])) as $a): ?><span class="chip"><?= e(trim($a)) ?></span><?php endforeach; ?></div>
          <div class="room-foot">
            <div>
              <div class="price"><?= money($t['base_rate']) ?> <small>/ night</small></div>
              <?php if ($searched): ?>
                <div class="avail <?= $ok ? 'ok' : 'no' ?>"><?php
                  if (!$t['fits']) echo 'Too many guests for this room';
                  elseif ($t['available'] < 1) echo 'Sold out for these dates';
                  else echo ($t['available'] <= 2 ? 'Only ' . $t['available'] . ' left! · ' : 'Available · ') . money($t['total']) . ' total'; ?></div>
              <?php endif; ?>
            </div>
            <?php if ($ok): ?><a class="btn" href="<?= e(url('book.php?' . $q)) ?>">Book now</a><?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

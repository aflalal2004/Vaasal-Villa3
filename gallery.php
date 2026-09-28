<?php
/** Public photo gallery with category filter and lightbox (managed in Admin › Website Media). */
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Photo Gallery';
$page = 'gallery';
$cats = ['villa' => 'Villa & pool', 'rooms' => 'Rooms', 'dining' => 'Dining', 'spa' => 'Spa', 'experiences' => 'Experiences'];
$photos = q_all('SELECT * FROM gallery_images WHERE is_active = 1 ORDER BY sort_order, id');
$present = array_unique(array_column($photos, 'category'));
require __DIR__ . '/includes/site_header.php';
site_page_head('Gallery', 'A glimpse of life at ' . e(setting('villa_name')) . ' — tap any photo to enlarge.', $photos[0]['image_url'] ?? setting('hero_poster_url'));
?>
<section class="section" style="padding-top:30px">
  <div class="wrap">
    <div class="gallery-filter" role="toolbar" aria-label="Filter photos">
      <button type="button" class="on" data-filter="all">All (<?= count($photos) ?>)</button>
      <?php foreach ($cats as $k => $l): if (!in_array($k, $present, true)) continue; ?><button type="button" data-filter="<?= $k ?>"><?= e($l) ?></button><?php endforeach; ?>
    </div>
    <div class="masonry">
      <?php foreach ($photos as $i => $p): ?>
        <figure class="reveal zoom" style="--d:<?= ($i % 4) * 0.08 ?>s" data-cat="<?= e($p['category']) ?>" data-full="<?= e(media_sized($p['image_url'], 1920)) ?>" data-caption="<?= e($p['caption']) ?>" data-group="gallery">
          <img src="<?= e(media_sized($p['image_url'], 700)) ?>" alt="<?= e($p['caption'] ?: 'Vaasal Villa photo') ?>" loading="lazy">
          <?php if ($p['caption']): ?><figcaption><?= e($p['caption']) ?></figcaption><?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
    <?php if (!$photos): ?><p class="center lead" style="margin:40px auto">Photos are coming soon.</p><?php endif; ?>
    <div class="center" style="margin-top:30px"><a class="btn gold" href="<?= e(url('book.php')) ?>">Book your stay</a></div>
  </div>
</section>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

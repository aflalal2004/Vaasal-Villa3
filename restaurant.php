<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Restaurant Menu';
$page = 'dining';
// Live menu straight from the POS database — changes in Admin > Menu appear here instantly.
$cats = q_all('SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY sort_order');
$items = q_all('SELECT * FROM menu_items WHERE is_available = 1 ORDER BY name');
$byCat = [];
foreach ($items as $i) $byCat[$i['category_id']][] = $i;
require __DIR__ . '/includes/site_header.php';
?>
<?php $dining = q_all("SELECT * FROM gallery_images WHERE is_active = 1 AND category = 'dining' ORDER BY sort_order, id");
site_page_head('The Vaasal Kitchen', 'Open daily 7:00 – 22:30 · Dine-in, takeaway &amp; in-room dining · Prices exclude ' . e(setting('service_charge')) . '% service and ' . e(setting('tax_rate')) . '% tax', $dining[0]['image_url'] ?? null); ?>
<?php if (count($dining) > 1): ?>
<section class="section" style="padding:40px 0 0"><div class="wrap grid g3">
  <?php foreach (array_slice($dining, 0, 3) as $i => $d): ?>
    <figure class="reveal zoom" style="--d:<?= $i * .12 ?>s;margin:0;border-radius:16px;overflow:hidden;cursor:zoom-in;height:220px" data-full="<?= e(media_sized($d['image_url'], 1800)) ?>" data-caption="<?= e($d['caption']) ?>" data-group="dining">
      <img src="<?= e(media_sized($d['image_url'], 700)) ?>" alt="<?= e($d['caption']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;transition:transform .7s" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform=''"></figure>
  <?php endforeach; ?>
</div></section>
<?php endif; ?>
<section class="section" style="padding-top:50px">
  <div class="wrap grid g2">
    <?php foreach ($cats as $c): if (empty($byCat[$c['id']])) continue; ?>
      <div class="form">
        <h2 style="font-size:1.6rem"><?= e($c['name']) ?></h2>
        <div class="menu-list">
          <?php foreach ($byCat[$c['id']] as $m): ?>
            <div class="it"><span><b><?= e($m['name']) ?></b><?= $m['is_veg'] ? ' <span class="chip">veg</span>' : '' ?><small><?= e($m['description']) ?></small></span><span class="price" style="font-size:1.1rem"><?= money($m['price']) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="wrap center" style="margin-top:40px"><p class="lead">Staying with us? Order from your room and simply charge it to your room bill.</p><a class="btn" href="<?= e(url('contact.php?subject=Table%20reservation')) ?>">Reserve a table</a></div>
</section>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

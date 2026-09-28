<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Boutique Villa, Suites & Restaurant';
$page = 'home';
$types = q_all('SELECT * FROM room_types WHERE is_active = 1 ORDER BY base_rate');
$signature = q_all("SELECT i.name, i.description, i.price FROM menu_items i JOIN menu_categories c ON c.id=i.category_id
                    WHERE i.is_available = 1 AND c.name IN ('Seafood Grill','Mains','Cocktails') ORDER BY i.price DESC LIMIT 5");
$photos = q_all('SELECT * FROM gallery_images WHERE is_active = 1 ORDER BY sort_order, id');
$byCat = [];
foreach ($photos as $p) $byCat[$p['category']][] = $p;
$pick = fn(string $cat, int $i = 0, string $fallback = '') => $byCat[$cat][$i]['image_url'] ?? $fallback;
$heroVideo = setting('hero_video_url');
$poster = setting('hero_poster_url');
$promo = setting('promo_video_url');
$roomCount = (int) q_val("SELECT COUNT(*) FROM rooms WHERE status <> 'maintenance'");
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$experiences = [
    ['🌊', 'Beachfront living', 'Wake to the waves, spend afternoons by the pool and end the day with sunset drinks on the sand.', $pick('villa', 4)],
    ['🛶', 'Guided tours', 'Lagoon kayaking, tea-country day trips, whale watching and temple visits — arranged by our concierge.', $pick('experiences', 0)],
    ['💆', 'Spa & wellness', 'Ayurvedic treatments, couples massages and sunrise yoga in the garden pavilion.', $pick('spa', 0)],
    ['🍽', 'Seaside dining', 'Fresh catch grilled to order, authentic rice & curry and tropical cocktails at our restaurant.', $pick('dining', 0)],
    ['💍', 'Weddings & events', 'Intimate beach ceremonies and private celebrations for up to 60 guests.', $pick('experiences', 2)],
    ['🚤', 'Boat trips', 'Snorkelling, sunset cruises and island hopping from the jetty — ask when you book.', $pick('experiences', 1)],
];
require __DIR__ . '/includes/site_header.php';
?>
<section class="hero<?= ($heroVideo || $poster) ? ' has-video' : '' ?>">
  <?php if ($heroVideo || $poster): ?>
    <div class="hero-media">
      <?php if ($heroVideo): ?>
        <video data-bg autoplay muted loop playsinline preload="auto" <?= $poster ? 'poster="' . e(media_sized($poster, 1920)) . '"' : '' ?> aria-hidden="true">
          <?php if ($mobileVideo = setting('hero_video_mobile_url')): ?><source media="(max-width: 767px)" src="<?= e(media_url($mobileVideo)) ?>" type="<?= video_mime($mobileVideo) ?>"><?php endif; ?>
          <source src="<?= e(media_url($heroVideo)) ?>" type="<?= video_mime($heroVideo) ?>">
        </video>
      <?php else: ?>
        <img src="<?= e(media_sized($poster, 1920)) ?>" alt="">
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <svg class="palm" viewBox="0 0 200 200" aria-hidden="true"><path d="M100 200 C98 150 96 110 104 70" stroke="#fff" stroke-width="6" fill="none"/><path d="M104 70 C80 40 40 40 10 60 C50 50 80 60 104 70 Z M104 70 C120 35 160 25 190 45 C150 42 125 55 104 70 Z M104 70 C90 30 100 10 120 0 C110 25 108 45 104 70 Z M104 70 C140 60 170 80 185 110 C160 85 130 75 104 70 Z M104 70 C70 70 40 95 30 125 C55 100 80 85 104 70 Z" fill="#fff"/></svg>
  <div class="wrap hero-inner">
    <div class="eyebrow" style="color:#f3d9a8">Welcome to <?= e(setting('villa_name')) ?></div>
    <h1><?= e(setting('villa_tagline')) ?></h1>
    <p>Garden rooms, ocean-view suites and private pool villas steps from the beach — with a seaside restaurant serving island flavours from sunrise to starlight.</p>
    <p style="margin-top:26px"><a class="btn gold" href="#book">Check availability</a> &nbsp; <a class="btn ghost" style="color:#fff!important;border-color:#fff" href="<?= e(url('rooms.php')) ?>">Explore rooms</a></p>
  </div>
  <?php if ($heroVideo): ?><button class="video-toggle" type="button">❚❚ Pause video</button><?php endif; ?>
  <a class="scroll-cue" href="#about" aria-label="Scroll down"></a>
</section>

<form class="booking-bar reveal" id="book" action="<?= e(url('rooms.php')) ?>" method="get">
  <div><label>Check-in</label><input type="date" name="check_in" data-min-today value="<?= date('Y-m-d') ?>" required></div>
  <div><label>Check-out</label><input type="date" name="check_out" data-min-today value="<?= $tomorrow ?>" required></div>
  <div><label>Adults</label><select name="adults"><?= options([1 => '1', 2 => '2', 3 => '3', 4 => '4'], 2) ?></select></div>
  <div><label>Children</label><select name="children"><?= options([0 => '0', 1 => '1', 2 => '2', 3 => '3'], 0) ?></select></div>
  <button class="btn">Search</button>
</form>

<section class="section" id="about">
  <div class="wrap split">
    <div class="art-stack reveal left">
      <img src="<?= e(media_sized($pick('villa', 1, $poster), 1000)) ?>" alt="Infinity pool at Vaasal Villa" loading="lazy">
      <img src="<?= e(media_sized($pick('villa', 2, $poster), 700)) ?>" alt="The villa from above" loading="lazy">
    </div>
    <div class="reveal right">
      <div class="eyebrow">Our story</div>
      <h2>A gateway to slow, sunlit days</h2>
      <p class="lead">“Vaasal” means <i>gateway</i>. Our boutique villa opens onto a quiet bay, with frangipani gardens, a lagoon-edge pool and a kitchen that cooks with what the fishermen bring in each morning.</p>
      <p>Whether you come for a honeymoon, a family holiday or a small wedding, our team looks after every detail — from airport pick-up to the last sunset.</p>
      <div class="stats-band" style="margin-top:28px;text-align:left">
        <div><div class="n" data-count="<?= $roomCount ?>">0</div><div class="l">Rooms &amp; villas</div></div>
        <div><div class="n" data-count="4.9">0</div><div class="l">Guest rating</div></div>
        <div><div class="n" data-count="50" data-suffix="m">0</div><div class="l">To the beach</div></div>
        <div><div class="n" data-count="24" data-suffix="/7">0</div><div class="l">Concierge</div></div>
      </div>
    </div>
  </div>
</section>

<section class="section alt">
  <div class="wrap center reveal">
    <div class="eyebrow">Stay</div>
    <h2>Rooms, suites &amp; villas</h2>
    <p class="lead">Every space is designed around light, breeze and privacy. Book direct for our best available rate.</p>
  </div>
  <div class="wrap grid g4" style="margin-top:40px">
    <?php foreach ($types as $i => $t): ?>
      <article class="room-card reveal" style="--d:<?= $i * 0.12 ?>s">
        <a class="room-img<?= $t['image_url'] ? ' photo' : '' ?>" style="--c:<?= e($t['color']) ?>" href="<?= e(url('room.php?type=' . urlencode($t['slug']))) ?>">
          <?php if ($t['image_url']): ?><img src="<?= e(media_sized($t['image_url'], 800)) ?>" alt="<?= e($t['name']) ?>" loading="lazy"><?php endif; ?>
          <span><?= (int) $t['size_sqm'] ?> m² · <?= e($t['bed_type']) ?></span></a>
        <div class="room-body">
          <h3><?= e($t['name']) ?></h3>
          <p><?= e($t['short_desc']) ?></p>
          <div class="room-foot"><div class="price"><?= money($t['base_rate']) ?> <small>/ night</small></div>
            <a class="btn" href="<?= e(url('room.php?type=' . urlencode($t['slug']))) ?>">View</a></div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="section" id="experiences">
  <div class="wrap center reveal"><div class="eyebrow">Experiences</div><h2>Slow days, unforgettable evenings</h2></div>
  <div class="wrap grid g3" style="margin-top:40px">
    <?php foreach ($experiences as $i => [$ic, $h, $txt, $img]): ?>
      <div class="feature reveal" style="--d:<?= ($i % 3) * 0.12 ?>s">
        <?php if ($img): ?><div class="feature-img"><img src="<?= e(media_sized($img, 700)) ?>" alt="<?= e($h) ?>" loading="lazy"></div><?php endif; ?>
        <div class="feature-body"><div class="ic"><?= $ic ?></div><h3><?= e($h) ?></h3><p><?= e($txt) ?></p></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($promo): ?>
<section class="video-band">
  <video id="promoVideo" data-bg muted loop playsinline preload="none" <?= $poster ? 'poster="' . e(media_sized($pick('villa', 0, $poster), 1600)) . '"' : '' ?>>
    <source src="<?= e(media_url($promo)) ?>" type="<?= video_mime($promo) ?>">
  </video>
  <div class="wrap reveal zoom">
    <button class="play-btn" type="button" data-play="promoVideo" aria-label="Play video with sound">▶</button>
    <div class="eyebrow" style="color:#f3d9a8">Discover</div>
    <h2>Where the jungle meets the ocean</h2>
    <p style="opacity:.9;max-width:560px;margin:0 auto">Turquoise water, soft sand and warm evenings — see why guests return every year.</p>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="wrap split">
    <div class="art-stack reveal left">
      <img src="<?= e(media_sized($pick('dining', 0, $poster), 1000)) ?>" alt="Seaside terrace restaurant" loading="lazy">
      <img src="<?= e(media_sized($pick('dining', 1, $poster), 700)) ?>" alt="Chef's plates" loading="lazy">
    </div>
    <div class="reveal right">
      <div class="eyebrow">Dining</div>
      <h2>The Vaasal Kitchen</h2>
      <p class="lead">Open to in-house guests and visitors. Dine in, take away, or order to your room — all charged to your room bill if you are staying with us.</p>
      <div class="menu-list" style="margin:20px 0">
        <?php foreach ($signature as $m): ?>
          <div class="it"><span><b><?= e($m['name']) ?></b><small><?= e($m['description']) ?></small></span><span class="price" style="font-size:1.1rem"><?= money($m['price']) ?></span></div>
        <?php endforeach; ?>
      </div>
      <a class="btn" href="<?= e(url('restaurant.php')) ?>">View full menu</a>
    </div>
  </div>
</section>

<?php if ($photos): ?>
<section class="section alt">
  <div class="wrap center reveal"><div class="eyebrow">Gallery</div><h2>Moments at Vaasal Villa</h2></div>
  <div class="wrap masonry" style="margin-top:36px">
    <?php foreach (array_slice($photos, 0, 8) as $i => $p): ?>
      <figure class="reveal zoom" style="--d:<?= ($i % 4) * 0.08 ?>s" data-full="<?= e(media_sized($p['image_url'], 1800)) ?>" data-caption="<?= e($p['caption']) ?>" data-group="home">
        <img src="<?= e(media_sized($p['image_url'], 700)) ?>" alt="<?= e($p['caption']) ?>" loading="lazy"><figcaption><?= e($p['caption']) ?></figcaption></figure>
    <?php endforeach; ?>
  </div>
  <div class="wrap center" style="margin-top:20px"><a class="btn ghost" href="<?= e(url('gallery.php')) ?>">See the full gallery</a></div>
</section>
<?php endif; ?>

<section class="section">
  <div class="wrap center reveal"><div class="eyebrow">Guest stories</div><h2>What our guests say</h2></div>
  <div class="wrap grid g3" style="margin-top:36px">
    <div class="quote reveal">“The pool villa was pure magic. Staff remembered every little preference.”<b>— Emma, Sweden</b></div>
    <div class="quote reveal" style="--d:.12s">“Best prawns of our trip and the sunsets from the suite balcony are unreal.”<b>— Liam, Ireland</b></div>
    <div class="quote reveal" style="--d:.24s">“Perfect for our family — spacious villa, kind team and easy tours from the front desk.”<b>— Priya, India</b></div>
  </div>
</section>

<section class="section alt">
  <div class="wrap split">
    <div class="reveal left">
      <div class="eyebrow">Tour operators &amp; travel agents</div>
      <h2>Partner with us</h2>
      <p class="lead">We work with leading booking sites and tour operators through a direct channel connection. Contact our reservations team for contracted rates, allotments and API access.</p>
      <p><a class="btn gold" href="<?= e(url('contact.php?subject=Tour%20operator%20partnership')) ?>">Contact reservations</a></p>
    </div>
    <div class="summary reveal right">
      <h3>Why book direct?</h3>
      <p>✔ Best available rate guaranteed<br>✔ Instant confirmation<br>✔ Free cancellation up to 48 hours before arrival<br>✔ Manage your booking online<br>✔ Welcome drink on arrival</p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

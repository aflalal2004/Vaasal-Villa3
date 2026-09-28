<?php
/** Public website layout (top). Expects $pageTitle; optional $page (nav key). */
$page = $page ?? '';
$__links = ['index.php' => ['home', 'Home'], 'rooms.php' => ['rooms', 'Rooms & Villas'], 'restaurant.php' => ['dining', 'Dining'],
            'gallery.php' => ['gallery', 'Gallery'], 'contact.php' => ['contact', 'Contact'], 'manage-booking.php' => ['manage', 'My Booking']];

/** Photo banner used at the top of inner pages. */
function site_page_head(string $title, string $sub, ?string $img = null): void
{
    echo '<div class="page-head' . ($img ? ' has-img' : '') . '">';
    if ($img) echo '<div class="bg" style="background-image:url(\'' . e(media_sized($img, 1920)) . '\')" role="img" aria-label=""></div>';
    echo '<div class="wrap"><h1>' . e($title) . '</h1><p>' . $sub . '</p></div></div>';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($pageTitle ?? 'Welcome') . ' · ' . setting('villa_name', 'Vaasal Villa')) ?></title>
<meta name="description" content="<?= e(setting('villa_name')) ?> — <?= e(setting('villa_tagline')) ?>. Book rooms, suites and private pool villas directly for the best rate.">
<meta property="og:title" content="<?= e(setting('villa_name')) ?>">
<meta property="og:description" content="<?= e(setting('villa_tagline')) ?>">
<?php if (setting('hero_poster_url')): ?><meta property="og:image" content="<?= e(media_sized(setting('hero_poster_url'), 1200)) ?>"><?php endif; ?>
<script>document.documentElement.className += ' js';</script>
<link rel="preconnect" href="https://images.unsplash.com">
<link rel="stylesheet" href="<?= e(url('assets/css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/site-media.css')) ?>">
</head>
<body>
<header class="site-header" id="top">
  <div class="wrap nav">
    <a class="logo" href="<?= e(url('index.php')) ?>"><span class="logo-mark">V</span><span><?= e(setting('villa_name')) ?><small>Boutique villa &amp; restaurant</small></span></a>
    <button class="burger" onclick="document.body.classList.toggle('menu-open')" aria-label="Menu">☰</button>
    <nav class="links">
      <?php foreach ($__links as $href => [$key, $text]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $page === $key ? 'on' : '' ?>"><?= e($text) ?></a>
      <?php endforeach; ?>
      <a class="cta" href="<?= e(url('book.php')) ?>">Book now</a>
    </nav>
  </div>
</header>

<?php
/** Website media manager: hero video, poster, promo video and photo gallery (upload or URL). */
require __DIR__ . '/../includes/bootstrap.php';
require_perm('settings.manage');
$title = 'Website Media';
$active = 'media';

$categories = ['villa' => 'Villa & pool', 'rooms' => 'Rooms', 'dining' => 'Dining', 'spa' => 'Spa & wellness', 'experiences' => 'Experiences'];
$videoKeys = [
    'hero_video_url'  => ['Homepage hero video (desktop, ~720p)', 'video'],
    'hero_video_mobile_url' => ['Hero video for phones (optional, small ~360p file)', 'video'],
    'hero_poster_url' => ['Hero poster image (shown while the video loads / on slow connections)', 'image'],
    'promo_video_url' => ['"Discover the villa" video band', 'video'],
];

/** Take a file upload if present, else a typed URL. Returns null when neither was given. */
function media_from_request(string $fileField, string $urlField, string $kind): ?string
{
    $path = save_upload($fileField, $kind);
    if ($path) return $path;
    $u = trim((string) ($_POST[$urlField] ?? ''));
    if ($u === '') return null;
    if (!valid_media_url($u)) throw new RuntimeException('Media links must be full https:// URLs.');
    return $u;
}

if (is_post()) {
    $a = input('action');
    try {
        if ($a === 'hero') {
            $changed = 0;
            foreach ($videoKeys as $key => [$lab, $kind]) {
                if (!empty($_POST['clear_' . $key])) {
                    delete_upload(setting($key));
                    q('INSERT INTO settings (skey, svalue) VALUES (?, "") ON DUPLICATE KEY UPDATE svalue = ""', [$key]);
                    $changed++;
                    continue;
                }
                $v = media_from_request('file_' . $key, 'url_' . $key, $kind);
                if ($v !== null && $v !== setting($key)) {
                    delete_upload(setting($key));
                    q('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, $v]);
                    $changed++;
                }
            }
            log_activity('media', "Updated hero/promo media ($changed change(s))");
            flash('success', $changed ? 'Website video settings saved.' : 'Nothing changed.');
        } elseif ($a === 'add') {
            $v = media_from_request('photo', 'photo_url', 'image');
            if (!$v) throw new RuntimeException('Choose a photo to upload or paste an https:// image URL.');
            $cat = isset($categories[input('category')]) ? input('category') : 'villa';
            q('INSERT INTO gallery_images (image_url, caption, category, room_type_id, sort_order) VALUES (?,?,?,?,?)',
              [$v, mb_substr(input('caption'), 0, 160) ?: null, $cat, input_int('room_type_id') ?: null, input_int('sort_order')]);
            log_activity('media', 'Added gallery photo');
            flash('success', 'Photo added to the gallery.');
        } elseif ($a === 'update') {
            $cat = isset($categories[input('category')]) ? input('category') : 'villa';
            q('UPDATE gallery_images SET caption=?, category=?, room_type_id=?, sort_order=?, is_active=? WHERE id=?',
              [mb_substr(input('caption'), 0, 160) ?: null, $cat, input_int('room_type_id') ?: null, input_int('sort_order'), !empty($_POST['is_active']) ? 1 : 0, input_int('id')]);
            flash('success', 'Photo updated.');
        } elseif ($a === 'delete') {
            $img = q_row('SELECT * FROM gallery_images WHERE id=?', [input_int('id')]);
            if ($img) {
                q('DELETE FROM gallery_images WHERE id=?', [$img['id']]);
                delete_upload($img['image_url']);
                log_activity('media', 'Deleted gallery photo #' . $img['id']);
            }
            flash('success', 'Photo removed.');
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/media.php' . (input('cat') ? '?cat=' . urlencode(input('cat')) : ''));
}

$cat = isset($categories[input('cat')]) ? input('cat') : '';
$photos = q_all('SELECT g.*, rt.name room_type FROM gallery_images g LEFT JOIN room_types rt ON rt.id=g.room_type_id '
    . ($cat ? 'WHERE g.category = ? ' : '') . 'ORDER BY g.sort_order, g.id', $cat ? [$cat] : []);
$types = q_all('SELECT id, name FROM room_types ORDER BY base_rate');
$typeOpts = array_map(fn($t) => [$t['id'], $t['name']], $types);

require __DIR__ . '/../includes/admin_header.php';
?>
<style>
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px}
.media-card{border:1px solid var(--line);border-radius:12px;overflow:hidden;background:#fff}
.media-card img{width:100%;height:150px;object-fit:cover;display:block;background:#eef2f1}
.media-card form{padding:10px}
.media-card .field{margin-bottom:6px}
.media-card input,.media-card select{padding:6px 8px;font-size:.85rem}
.media-card.off img{opacity:.35}
.hero-prev{width:100%;max-height:160px;object-fit:cover;border-radius:10px;background:#0b3b37;display:block;margin-bottom:8px}
</style>

<div class="card">
  <div class="card-head"><h2>Homepage video &amp; hero</h2><a class="btn btn-sm btn-ghost" href="<?= e(url('index.php')) ?>" target="_blank">View website ↗</a></div>
  <p class="small muted">Upload your own MP4/WebM (max <?= e(min(40, (int) ini_get('upload_max_filesize')) ?: 40) ?> MB) or paste an https:// link (e.g. from your own hosting, Pexels or Mixkit). Videos play muted and looped; visitors who prefer reduced motion see the poster image instead.</p>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="hero">
    <div class="grid g3">
    <?php foreach ($videoKeys as $key => [$lab, $kind]): $cur = setting($key); ?>
      <div>
        <h3 style="font-size:.95rem"><?= e($lab) ?></h3>
        <?php if ($cur && $kind === 'video'): ?><video class="hero-prev" src="<?= e(media_url($cur)) ?>" muted loop autoplay playsinline preload="metadata"></video>
        <?php elseif ($cur): ?><img class="hero-prev" src="<?= e(media_sized($cur, 800)) ?>" alt=""><?php else: ?><div class="hero-prev" style="height:120px;display:grid;place-items:center;color:#9cc9c3">none</div><?php endif; ?>
        <div class="field"><label>Current</label><input value="<?= e($cur) ?>" readonly class="small"></div>
        <div class="field"><label>Upload new <?= $kind ?></label><input type="file" name="file_<?= $key ?>" accept="<?= $kind === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp' ?>"></div>
        <div class="field"><label>…or paste URL</label><input name="url_<?= $key ?>" placeholder="https://…"></div>
        <label class="check small"><input type="checkbox" name="clear_<?= $key ?>" value="1"> Remove</label>
      </div>
    <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="btn">Save video settings</button></div>
  </form>
</div>

<div class="grid g-2-1">
  <div class="card">
    <div class="card-head"><h2>Photo gallery (<?= count($photos) ?>)</h2>
      <div class="tabs" style="margin:0"><a class="<?= !$cat ? 'active' : '' ?>" href="media.php">All</a><?php foreach ($categories as $k => $l): ?><a class="<?= $cat === $k ? 'active' : '' ?>" href="?cat=<?= $k ?>"><?= e($l) ?></a><?php endforeach; ?></div></div>
    <div class="media-grid">
    <?php foreach ($photos as $p): ?>
      <div class="media-card <?= $p['is_active'] ? '' : 'off' ?>">
        <a href="<?= e(media_url($p['image_url'])) ?>" target="_blank"><img src="<?= e(media_sized($p['image_url'], 500)) ?>" alt="<?= e($p['caption']) ?>" loading="lazy"></a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="cat" value="<?= e($cat) ?>">
          <div class="field"><input name="caption" value="<?= e($p['caption']) ?>" placeholder="Caption"></div>
          <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:6px">
            <div class="field"><select name="category"><?= options($categories, $p['category']) ?></select></div>
            <div class="field"><select name="room_type_id"><option value="">No room type</option><?= options($typeOpts, $p['room_type_id']) ?></select></div>
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="number" name="sort_order" value="<?= (int) $p['sort_order'] ?>" style="width:70px" title="Sort order">
            <label class="check small" style="margin:0"><input type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?>> Show</label>
            <button class="btn btn-sm" name="action" value="update" style="margin-left:auto">Save</button>
            <button class="btn btn-sm btn-ghost" name="action" value="delete" data-confirm="Remove this photo from the website?">✕</button>
          </div>
        </form>
      </div>
    <?php endforeach; if (!$photos): ?><p class="muted">No photos in this category yet.</p><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <h2>Add photo</h2>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="cat" value="<?= e($cat) ?>">
      <div class="field"><label>Upload image (JPG, PNG, WebP · max 8 MB)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
      <div class="field"><label>…or image URL</label><input name="photo_url" placeholder="https://images.unsplash.com/…"></div>
      <div class="field"><label>Caption</label><input name="caption"></div>
      <div class="form-grid">
        <div class="field"><label>Category</label><select name="category"><?= options($categories, $cat ?: 'villa') ?></select></div>
        <div class="field"><label>Room type (optional)</label><select name="room_type_id"><option value="">—</option><?= options($typeOpts) ?></select></div>
        <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="50"></div>
      </div>
      <p class="small muted">Photos linked to a room type appear in that room's slideshow on the website.</p>
      <div class="form-actions"><button class="btn">Add to gallery</button></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

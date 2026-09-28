<?php
require __DIR__ . '/../includes/bootstrap.php';
require_perm('menu.manage');
$title = 'Menu & Tables';
$active = 'menu';

if (is_post()) {
    $a = input('action');
    try {
        switch ($a) {
            case 'save_item':
                if (input('name') === '' || input_float('price') < 0 || !input_int('category_id')) throw new RuntimeException('Name, category and price are required.');
                $vals = [input_int('category_id'), input('name'), input('description') ?: null, input_float('price'), !empty($_POST['is_veg']) ? 1 : 0, !empty($_POST['is_available']) ? 1 : 0];
                if (input_int('id')) q('UPDATE menu_items SET category_id=?, name=?, description=?, price=?, is_veg=?, is_available=? WHERE id=?', [...$vals, input_int('id')]);
                else q('INSERT INTO menu_items (category_id, name, description, price, is_veg, is_available) VALUES (?,?,?,?,?,?)', $vals);
                log_activity('menu', 'Saved menu item ' . input('name'));
                flash('success', 'Menu item saved.');
                break;
            case 'toggle_item':
                q('UPDATE menu_items SET is_available = 1 - is_available WHERE id=?', [input_int('id')]);
                flash('success', 'Availability updated.');
                break;
            case 'save_cat':
                if (input('name') === '') throw new RuntimeException('Category name required.');
                $station = in_array(input('station'), ['kitchen', 'bar', 'pastry'], true) ? input('station') : 'kitchen';
                if (input_int('id')) q('UPDATE menu_categories SET name=?, station=?, sort_order=?, is_active=? WHERE id=?', [input('name'), $station, input_int('sort_order'), !empty($_POST['is_active']) ? 1 : 0, input_int('id')]);
                else q('INSERT INTO menu_categories (name, station, sort_order) VALUES (?,?,?)', [input('name'), $station, input_int('sort_order')]);
                flash('success', 'Category saved.');
                break;
            case 'save_table':
                if (input('table_no') === '') throw new RuntimeException('Table number required.');
                if (input_int('id')) q('UPDATE restaurant_tables SET table_no=?, area=?, seats=? WHERE id=?', [strtoupper(input('table_no')), input('area') ?: 'Main', max(1, input_int('seats', 2)), input_int('id')]);
                else q('INSERT INTO restaurant_tables (table_no, area, seats) VALUES (?,?,?)', [strtoupper(input('table_no')), input('area') ?: 'Main', max(1, input_int('seats', 2))]);
                flash('success', 'Table saved.');
                break;
            case 'table_status':
                $st = input('status');
                if (!in_array($st, ['free', 'reserved'], true)) throw new RuntimeException('Invalid status.');
                if (q_val("SELECT COUNT(*) FROM orders WHERE table_id=? AND status='open'", [input_int('id')])) throw new RuntimeException('Table has an open order.');
                q('UPDATE restaurant_tables SET status=? WHERE id=?', [$st, input_int('id')]);
                flash('success', 'Table updated.');
                break;
        }
    } catch (PDOException $ex) {
        flash('error', $ex->getCode() == 23000 ? 'Duplicate value.' : $ex->getMessage());
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/menu.php' . (input_int('cat') ? '?cat=' . input_int('cat') : ''));
}

$cats = q_all('SELECT c.*, (SELECT COUNT(*) FROM menu_items i WHERE i.category_id=c.id) n FROM menu_categories c ORDER BY sort_order, name');
$catFilter = input_int('cat');
$items = q_all('SELECT i.*, c.name cat FROM menu_items i JOIN menu_categories c ON c.id=i.category_id ' . ($catFilter ? 'WHERE i.category_id=?' : '') . ' ORDER BY c.sort_order, i.name', $catFilter ? [$catFilter] : []);
$tables = q_all('SELECT * FROM restaurant_tables ORDER BY area, table_no');
$ei = input_int('item') ? q_row('SELECT * FROM menu_items WHERE id=?', [input_int('item')]) : null;
$ec = input_int('editcat') ? q_row('SELECT * FROM menu_categories WHERE id=?', [input_int('editcat')]) : null;
$et = input_int('table') ? q_row('SELECT * FROM restaurant_tables WHERE id=?', [input_int('table')]) : null;
$catOpts = array_map(fn($c) => [$c['id'], $c['name']], $cats);

require __DIR__ . '/../includes/admin_header.php';
?>
<div class="grid g-2-1">
  <div class="card">
    <div class="card-head"><h2>Menu items</h2>
      <div class="tabs" style="margin:0"><a class="<?= !$catFilter ? 'active' : '' ?>" href="menu.php">All</a><?php foreach ($cats as $c): ?><a class="<?= $catFilter == $c['id'] ? 'active' : '' ?>" href="?cat=<?= $c['id'] ?>"><?= e($c['name']) ?></a><?php endforeach; ?></div></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Item</th><th>Category</th><th class="num">Price</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($items as $i): ?>
      <tr><td><b><?= e($i['name']) ?></b> <?= $i['is_veg'] ? '<span class="badge badge-green">veg</span>' : '' ?><div class="small muted"><?= e($i['description']) ?></div></td>
      <td><?= e($i['cat']) ?></td><td class="num"><?= money($i['price']) ?></td><td><?= $i['is_available'] ? badge('active') : '<span class="badge badge-red">86 / Sold out</span>' ?></td>
      <td class="right nowrap"><a class="btn btn-sm btn-ghost" href="?item=<?= $i['id'] ?>&cat=<?= $catFilter ?>#itemform">Edit</a>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_item"><input type="hidden" name="id" value="<?= $i['id'] ?>"><input type="hidden" name="cat" value="<?= $catFilter ?>"><button class="btn btn-sm btn-ghost"><?= $i['is_available'] ? 'Sold out' : 'Available' ?></button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div>
    <div class="card" id="itemform">
      <?php $i = $ei ?: ['id' => 0, 'category_id' => $catFilter, 'name' => '', 'description' => '', 'price' => '', 'is_veg' => 0, 'is_available' => 1]; ?>
      <h2><?= $ei ? 'Edit item' : 'Add menu item' ?></h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_item"><input type="hidden" name="id" value="<?= $i['id'] ?>"><input type="hidden" name="cat" value="<?= $catFilter ?>">
        <div class="field"><label>Name</label><input name="name" value="<?= e($i['name']) ?>" required></div>
        <div class="form-grid"><div class="field"><label>Category</label><select name="category_id"><?= options($catOpts, $i['category_id']) ?></select></div>
          <div class="field"><label>Price</label><input type="number" step="0.01" min="0" name="price" value="<?= e($i['price']) ?>" required></div></div>
        <div class="field"><label>Description</label><input name="description" value="<?= e($i['description']) ?>"></div>
        <label class="check"><input type="checkbox" name="is_veg" value="1" <?= $i['is_veg'] ? 'checked' : '' ?>> Vegetarian</label>
        <label class="check"><input type="checkbox" name="is_available" value="1" <?= $i['is_available'] ? 'checked' : '' ?>> Available</label>
        <div class="form-actions"><button class="btn">Save item</button><?php if ($ei): ?><a class="btn btn-ghost" href="menu.php">New</a><?php endif; ?></div>
      </form>
    </div>
    <div class="card">
      <h2>Categories</h2>
      <?php foreach ($cats as $c): ?><div style="display:flex;justify-content:space-between;padding:3px 0"><span><?= e($c['name']) ?> <span class="small muted">· <?= e($c['station']) ?> · <?= $c['n'] ?> items<?= $c['is_active'] ? '' : ' · hidden' ?></span></span><a class="small" href="?editcat=<?= $c['id'] ?>">edit</a></div><?php endforeach; ?>
      <?php $c = $ec ?: ['id' => 0, 'name' => '', 'station' => 'kitchen', 'sort_order' => count($cats) + 1, 'is_active' => 1]; ?>
      <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="save_cat"><input type="hidden" name="id" value="<?= $c['id'] ?>">
        <div class="form-grid"><div class="field"><label><?= $ec ? 'Edit' : 'New' ?> category</label><input name="name" value="<?= e($c['name']) ?>" required></div>
          <div class="field"><label>Station</label><select name="station"><?= options(['kitchen' => 'Kitchen', 'bar' => 'Bar', 'pastry' => 'Pastry'], $c['station']) ?></select></div>
          <div class="field"><label>Sort</label><input type="number" name="sort_order" value="<?= e($c['sort_order']) ?>"></div></div>
        <?php if ($ec): ?><label class="check"><input type="checkbox" name="is_active" value="1" <?= $c['is_active'] ? 'checked' : '' ?>> Active</label><?php else: ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
        <button class="btn btn-ghost btn-sm">Save category</button>
      </form>
    </div>
    <div class="card">
      <h2>Tables</h2>
      <?php foreach ($tables as $t): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:3px 0"><span><b><?= e($t['table_no']) ?></b> <span class="small muted"><?= e($t['area']) ?> · <?= $t['seats'] ?> seats</span> <?= badge($t['status']) ?></span>
          <span class="nowrap"><a class="small" href="?table=<?= $t['id'] ?>">edit</a>
          <?php if ($t['status'] !== 'occupied'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="table_status"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="btn btn-sm btn-ghost" name="status" value="<?= $t['status'] === 'free' ? 'reserved' : 'free' ?>"><?= $t['status'] === 'free' ? 'Reserve' : 'Free' ?></button></form><?php endif; ?></span></div>
      <?php endforeach; ?>
      <?php $t = $et ?: ['id' => 0, 'table_no' => '', 'area' => 'Main', 'seats' => 4]; ?>
      <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="save_table"><input type="hidden" name="id" value="<?= $t['id'] ?>">
        <div class="form-grid"><div class="field"><label><?= $et ? 'Edit' : 'New' ?> table</label><input name="table_no" value="<?= e($t['table_no']) ?>" required></div>
          <div class="field"><label>Area</label><input name="area" value="<?= e($t['area']) ?>"></div><div class="field"><label>Seats</label><input type="number" name="seats" value="<?= e($t['seats']) ?>"></div></div>
        <button class="btn btn-ghost btn-sm">Save table</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>

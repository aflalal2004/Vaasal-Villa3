<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_perm('kitchen.view');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title>Kitchen Display · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/pos.css')) ?>">
</head>
<body class="kds">
<header class="kds-top">
  <h1>🍳 Kitchen Display — KOT</h1>
  <div class="kds-filter" id="filter">
    <button data-st="all" class="active">All</button><button data-st="kitchen">Kitchen</button><button data-st="bar">Bar</button><button data-st="pastry">Pastry</button>
  </div>
  <span id="clock"></span>
  <a href="<?= e(url(home_path())) ?>">⌂</a>
  <a href="<?= e(url('logout.php')) ?>">Sign out (<?= e($u['full_name']) ?>)</a>
</header>
<div class="kds-board" id="board"><div class="kds-empty">Loading…</div></div>
<script>
(function () {
  var API = <?= json_encode(url('pos/api.php')) ?>, CSRF = document.querySelector('meta[name=csrf-token]').content;
  var station = 'all', lastIds = null;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function call(action, body) {
    var o = { headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }, credentials: 'same-origin' };
    if (body) { o.method = 'POST'; o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(body); }
    return fetch(API + '?action=' + action, o).then(function (r) { return r.json(); });
  }
  function beep() { try { var c = new (window.AudioContext || window.webkitAudioContext)(), o = c.createOscillator(); o.connect(c.destination); o.frequency.value = 880; o.start(); o.stop(c.currentTime + 0.25); } catch (e) {} }
  function load() {
    call('kots').then(function (j) {
      if (!j.ok) { if (j.error === 'Not signed in') location.reload(); return; }
      var ids = j.kots.map(function (k) { return k.id; });
      if (lastIds && ids.some(function (id) { return lastIds.indexOf(id) < 0; })) beep();
      lastIds = ids;
      var html = j.kots.map(function (k) {
        var items = k.items.filter(function (i) { return station === 'all' || i.station === station; });
        if (!items.length) return '';
        var next = { 'new': ['preparing', 'Start cooking', ''], preparing: ['ready', 'Mark ready', 'go'], ready: ['served', 'Served / picked up', 'done'] }[k.status];
        var where = k.table_no ? 'Table ' + k.table_no : k.customer_name;
        return '<div class="kot ' + k.status + (k.age >= 20 && k.status !== 'ready' ? ' late' : '') + '">' +
          '<div class="kot-h"><div><b>' + esc(where) + '</b><div class="t">' + esc(k.kot_no) + ' · ' + esc(k.order_type.replace('_', ' ')) + ' · ' + esc(k.waiter || '') + '</div></div>' +
          '<div class="t">' + k.age + ' min<br>' + esc(k.status) + '</div></div>' +
          '<div class="kot-items">' + items.map(function (i) {
            return '<div><span class="q">' + i.qty + '×</span>' + esc(i.item_name) + '<span class="st">' + esc(i.station || '') + '</span>' + (i.notes ? '<span class="note">⚠ ' + esc(i.notes) + '</span>' : '') + '</div>';
          }).join('') + '</div>' +
          '<div class="kot-a"><button class="' + next[2] + '" data-kot="' + k.id + '" data-st="' + next[0] + '">' + next[1] + '</button></div></div>';
      }).join('');
      document.getElementById('board').innerHTML = html || '<div class="kds-empty">✓ No pending tickets</div>';
    }).catch(function () {});
  }
  document.getElementById('board').addEventListener('click', function (e) {
    var b = e.target.closest('[data-kot]'); if (!b) return;
    b.disabled = true;
    call('kot_status', { kot_id: b.dataset.kot, status: b.dataset.st }).then(load);
  });
  document.getElementById('filter').addEventListener('click', function (e) {
    var b = e.target.closest('[data-st]'); if (!b) return;
    station = b.dataset.st;
    this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('active', x === b); });
    load();
  });
  setInterval(function () { document.getElementById('clock').textContent = new Date().toLocaleTimeString(); }, 1000);
  setInterval(load, 8000);
  load();
})();
</script>
</body>
</html>

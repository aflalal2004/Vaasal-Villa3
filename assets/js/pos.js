// Vaasal Villa POS terminal
(function () {
  'use strict';
  var CSRF = document.querySelector('meta[name=csrf-token]').content;
  var $ = function (s) { return document.querySelector(s); };
  var data = { categories: [], items: [], tables: [], bookings: [], orders: [], settings: { currency: '$', tax: 0, service: 0 }, can_settle: false };
  var cur = null;          // current ticket
  var activeCat = 0;       // 0 = all
  var newType = null;
  var selTable = null;

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function money(v) { return data.settings.currency + (Math.round(v * 100) / 100).toFixed(2); }
  function label(s) { return String(s).replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }
  function toast(msg, err) {
    var t = $('#toast'); t.textContent = msg; t.className = 'toast show' + (err ? ' err' : '');
    clearTimeout(toast.h); toast.h = setTimeout(function () { t.className = 'toast'; }, 3500);
  }

  function api(action, body, query) {
    var opts = { headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }, credentials: 'same-origin' };
    if (body) { opts.method = 'POST'; opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(POS.api + '?action=' + action + (query || ''), opts).then(function (r) {
      return r.json().catch(function () { throw new Error('Server error (' + r.status + ')'); });
    }).then(function (j) {
      if (!j.ok) { if (j.error === 'Not signed in') location.reload(); throw new Error(j.error || 'Request failed'); }
      return j;
    });
  }

  // ------------------------------------------------------------ loading
  function load() {
    return api('bootstrap').then(function (j) {
      data = j;
      renderCats(); renderItems(); renderOrders(); renderTicket();
    }).catch(function (e) { toast(e.message, true); });
  }
  function refreshOrders() {
    api('bootstrap').then(function (j) { data.orders = j.orders; data.tables = j.tables; data.bookings = j.bookings; renderOrders(); }).catch(function () {});
  }

  // ------------------------------------------------------------ rendering
  function renderCats() {
    var h = '<button data-cat="0" class="' + (activeCat === 0 ? 'active' : '') + '">All</button>';
    data.categories.forEach(function (c) { h += '<button data-cat="' + c.id + '" class="' + (activeCat == c.id ? 'active' : '') + '">' + esc(c.name) + '</button>'; });
    $('#cats').innerHTML = h;
  }
  function renderItems() {
    var q = $('#search').value.trim().toLowerCase();
    var list = data.items.filter(function (i) {
      return (activeCat === 0 || i.category_id == activeCat) && (!q || i.name.toLowerCase().indexOf(q) > -1);
    });
    $('#items').innerHTML = list.map(function (i) {
      return '<button class="item" data-item="' + i.id + '"><span class="n">' + esc(i.name) + (i.is_veg == 1 ? ' <span class="veg">● veg</span>' : '') +
        '</span><span class="p">' + money(i.price) + '</span></button>';
    }).join('') || '<p style="color:#6b7a7e;padding:10px">No items.</p>';
  }
  function renderOrders() {
    var stages = ['', 'New', 'Cooking', 'Ready', 'Served'];
    var h = data.orders.map(function (o) {
      var st = parseInt(o.kot_stage || 0, 10);
      return '<div class="order-card' + (cur && cur.id == o.id ? ' active' : '') + '" data-order="' + o.id + '">' +
        '<div class="top"><span>' + esc(o.table_no ? 'Table ' + o.table_no : o.customer_name) + '</span><span>' + money(o.total) + '</span></div>' +
        '<div class="sub"><span>' + esc(o.order_no) + ' · ' + esc(label(o.order_type)) + '</span>' + (st ? '<span class="stage stage-' + st + '">' + stages[st] + '</span>' : '') + '</div></div>';
    }).join('');
    if (cur && !cur.id) h = '<div class="order-card active"><div class="top"><span>' + esc(ticketTitle()) + '</span><span>new</span></div><div class="sub"><span>Not yet sent</span></div></div>' + h;
    $('#orderList').innerHTML = h || '<p style="color:#6b7a7e;text-align:center;padding:20px">No open orders</p>';
  }
  function ticketTitle() {
    if (!cur) return '';
    if (cur.table_no) return 'Table ' + cur.table_no;
    return cur.customer_name || label(cur.order_type);
  }
  function totals() {
    var sub = 0;
    if (cur) {
      cur.sent.forEach(function (l) { sub += parseFloat(l.line_total); });
      cur.pending.forEach(function (l) { sub += l.price * l.qty; });
    }
    return calc(sub, 0);
  }
  function calc(sub, disc) {
    disc = Math.min(Math.max(0, disc || 0), sub);
    var net = sub - disc;
    var svc = Math.round(net * data.settings.service) / 100;
    var tax = Math.round((net + svc) * data.settings.tax) / 100;
    return { sub: sub, disc: disc, svc: svc, tax: tax, total: Math.round((net + svc + tax) * 100) / 100 };
  }
  function renderTicket() {
    var head = $('#ticketHead'), lines = $('#ticketLines');
    if (!cur) {
      head.innerHTML = '<h3>No order selected</h3><div class="meta">Start a new order or pick an open one.</div>';
      lines.innerHTML = '<div class="empty-ticket">🧾<br>Tap a menu item to start a quick takeaway,<br>or choose Dine-in / Guest on the left.</div>';
    } else {
      head.innerHTML = '<h3>' + esc(ticketTitle()) + '</h3><div class="meta">' + (cur.order_no ? esc(cur.order_no) + ' · ' : 'New · ') +
        esc(label(cur.order_type)) + (cur.room_number ? ' · Room ' + esc(cur.room_number) : '') + ' · ' + (cur.covers || 1) + ' cover(s)</div>';
      var h = '';
      cur.sent.forEach(function (l) {
        h += '<div class="line sent"><span class="nm">' + esc(l.item_name) + ' × ' + l.qty + '</span><span class="amt">' + money(l.line_total) + '</span>' +
          '<div class="ctl">' + esc(l.kot_no || '') + (l.notes ? ' · ' + esc(l.notes) : '') +
          (data.can_settle ? '<button class="void" data-void="' + l.id + '" title="Void item">void</button>' : '') + '</div></div>';
      });
      cur.pending.forEach(function (l, i) {
        h += '<div class="line"><span class="nm">' + esc(l.name) + '</span><span class="amt">' + money(l.price * l.qty) + '</span>' +
          '<div class="ctl"><button data-dec="' + i + '">−</button><b>' + l.qty + '</b><button data-inc="' + i + '">+</button>' +
          '<input data-note="' + i + '" placeholder="note (e.g. no chilli)" value="' + esc(l.notes) + '"></div></div>';
      });
      lines.innerHTML = h || '<div class="empty-ticket">Add items from the menu.</div>';
    }
    var t = totals();
    $('#ticketTotals').innerHTML =
      '<div class="r"><span>Subtotal</span><span>' + money(t.sub) + '</span></div>' +
      '<div class="r"><span>Service ' + data.settings.service + '%</span><span>' + money(t.svc) + '</span></div>' +
      '<div class="r"><span>Tax ' + data.settings.tax + '%</span><span>' + money(t.tax) + '</span></div>' +
      '<div class="r g"><span>Total</span><span>' + money(t.total) + '</span></div>';
    var hasPending = cur && cur.pending.length > 0;
    var saved = cur && cur.id;
    $('#btnSend').disabled = !hasPending;
    $('#btnBill').disabled = !saved;
    $('#btnSettle').disabled = !saved || hasPending || !data.can_settle;
    $('#btnRoom').disabled = !saved || hasPending || !data.can_settle || !data.bookings.length;
    $('#btnCancel').disabled = !cur || (saved && !data.can_settle);
    renderOrders();
  }

  // ------------------------------------------------------------ order selection
  function openOrder(id) {
    if (cur && cur.pending.length && !confirm('Discard unsent items on the current ticket?')) return;
    api('order', null, '&id=' + id).then(function (j) { setCur(j.order); }).catch(function (e) { toast(e.message, true); });
  }
  function setCur(o) {
    cur = { id: o.id, order_no: o.order_no, order_type: o.order_type, table_id: o.table_id, table_no: o.table_no, booking_id: o.booking_id,
      room_number: o.room_number, customer_name: o.customer_name, covers: o.covers, sent: o.items, pending: [] };
    renderTicket();
  }
  function addItem(id) {
    var it = data.items.find(function (i) { return i.id == id; });
    if (!it) return;
    if (!cur) { cur = { id: null, order_type: 'takeaway', customer_name: 'Takeaway', covers: 1, sent: [], pending: [] }; toast('Quick takeaway started'); }
    var ex = cur.pending.find(function (l) { return l.menu_item_id == id && !l.notes; });
    if (ex) ex.qty++; else cur.pending.push({ menu_item_id: it.id, name: it.name, price: parseFloat(it.price), qty: 1, notes: '' });
    renderTicket();
  }

  // ------------------------------------------------------------ new order dialog
  function openNew(type) {
    if (cur && cur.pending.length && !confirm('Discard unsent items on the current ticket?')) return;
    newType = type; selTable = null;
    $('#dlgNewTitle').textContent = { dine_in: 'New dine-in order', takeaway: 'New takeaway order', room_service: 'Existing guest / room service' }[type];
    $('#newTable').style.display = type === 'dine_in' ? '' : 'none';
    $('#newGuest').style.display = type === 'takeaway' ? 'none' : '';
    $('#newGuest label').textContent = type === 'room_service' ? 'In-house guest (room)' : 'In-house guest (optional — to charge to room)';
    $('#tableGrid').innerHTML = data.tables.map(function (t) {
      return '<button type="button" class="' + t.status + '" data-table="' + t.id + '">' + esc(t.table_no) + '<small>' + esc(t.area) + ' · ' + t.seats + '</small></button>';
    }).join('');
    $('#newBooking').innerHTML = (type === 'room_service' ? '' : '<option value="">— not a hotel guest —</option>') + data.bookings.map(function (b) {
      return '<option value="' + b.id + '">' + esc(b.room_number + ' · ' + b.guest_name + ' (' + b.booking_ref + ')') + '</option>';
    }).join('');
    if (type === 'room_service' && !data.bookings.length) { toast('No in-house guests are checked in.', true); return; }
    $('#newName').value = ''; $('#newCovers').value = type === 'takeaway' ? 1 : 2;
    $('#dlgNew').returnValue = ''; $('#dlgNew').showModal();
  }
  $('#tableGrid').addEventListener('click', function (e) {
    var b = e.target.closest('[data-table]'); if (!b) return;
    selTable = b.getAttribute('data-table');
    this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('sel', x === b); });
  });
  $('#dlgNew').addEventListener('close', function () {
    if (this.returnValue !== 'ok') return;
    if (newType === 'dine_in' && !selTable) { toast('Select a table', true); return; }
    var t = data.tables.find(function (x) { return x.id == selTable; });
    var bid = $('#newBooking').value;
    var bk = data.bookings.find(function (x) { return x.id == bid; });
    cur = { id: null, order_type: newType, table_id: newType === 'dine_in' ? selTable : null, table_no: newType === 'dine_in' && t ? t.table_no : null,
      booking_id: newType === 'takeaway' ? null : (bid || null), room_number: bk && newType !== 'takeaway' ? bk.room_number : null,
      customer_name: $('#newName').value.trim() || (bk && newType !== 'takeaway' ? bk.guest_name + ' (' + bk.room_number + ')' : ''), covers: parseInt($('#newCovers').value, 10) || 1, sent: [], pending: [] };
    renderTicket();
  });

  // ------------------------------------------------------------ actions
  function send() {
    var body = { order_id: cur.id, order_type: cur.order_type, table_id: cur.table_id, booking_id: cur.booking_id, customer_name: cur.customer_name, covers: cur.covers,
      items: cur.pending.map(function (l) { return { menu_item_id: l.menu_item_id, qty: l.qty, notes: l.notes }; }) };
    $('#btnSend').disabled = true;
    api('send', body).then(function (j) {
      data.orders = j.orders; setCur(j.order);
      toast(j.kot_no + ' sent to kitchen ✓');
      refreshOrders();
    }).catch(function (e) { toast(e.message, true); renderTicket(); });
  }
  function updatePay() {
    var o = data.orders.find(function (x) { return x.id == cur.id; });
    var sub = cur.sent.reduce(function (s, l) { return s + parseFloat(l.line_total); }, 0);
    var t = calc(sub, parseFloat($('#payDiscount').value) || 0);
    $('#payTotals').innerHTML = '<div class="r"><span>Subtotal</span><span>' + money(t.sub) + '</span></div>' +
      (t.disc ? '<div class="r"><span>Discount</span><span>− ' + money(t.disc) + '</span></div>' : '') +
      '<div class="r"><span>Service + tax</span><span>' + money(t.svc + t.tax) + '</span></div><div class="r g"><span>Total</span><span>' + money(t.total) + '</span></div>';
    var m = document.querySelector('input[name=pm]:checked').value;
    $('#tenderedWrap').style.display = m === 'cash' ? '' : 'none';
    var tend = parseFloat($('#payTendered').value);
    $('#payChange').textContent = m === 'cash' && tend >= t.total ? 'Change: ' + money(tend - t.total) : '';
    return t;
  }
  function openPay() {
    $('#payDiscount').value = 0; $('#payTendered').value = ''; $('#payRef').value = '';
    var t = updatePay(); $('#payTendered').value = t.total.toFixed(2); updatePay();
    $('#dlgPay').returnValue = ''; $('#dlgPay').showModal();
  }
  $('#formPay').addEventListener('input', updatePay);
  $('#dlgPay').addEventListener('close', function () {
    if (this.returnValue !== 'ok') return;
    var body = { order_id: cur.id, method: document.querySelector('input[name=pm]:checked').value, discount: parseFloat($('#payDiscount').value) || 0,
      tendered: parseFloat($('#payTendered').value) || 0, reference: $('#payRef').value };
    api('settle', body).then(function (j) {
      data.orders = j.orders;
      toast('Paid ' + money(j.total) + (j.change > 0 ? ' — change ' + money(j.change) : '') + ' ✓');
      window.open(POS.receipt + '?order=' + j.order_id, 'receipt', 'width=420,height=700');
      cur = null; renderTicket(); refreshOrders();
    }).catch(function (e) { toast(e.message, true); });
  });
  function openRoom() {
    $('#roomBooking').innerHTML = data.bookings.map(function (b) {
      return '<option value="' + b.id + '"' + (cur.booking_id == b.id ? ' selected' : '') + '>' + esc(b.room_number + ' · ' + b.guest_name + ' (' + b.booking_ref + ')') + '</option>';
    }).join('');
    $('#roomDiscount').value = 0;
    $('#dlgRoom').returnValue = ''; $('#dlgRoom').showModal();
  }
  $('#dlgRoom').addEventListener('close', function () {
    if (this.returnValue !== 'ok') return;
    api('room_charge', { order_id: cur.id, booking_id: $('#roomBooking').value, discount: parseFloat($('#roomDiscount').value) || 0 }).then(function (j) {
      data.orders = j.orders;
      toast(money(j.total) + ' charged to room ' + j.room + ' ✓');
      window.open(POS.receipt + '?order=' + j.order_id, 'receipt', 'width=420,height=700');
      cur = null; renderTicket(); refreshOrders();
    }).catch(function (e) { toast(e.message, true); });
  });
  function cancelOrder() {
    if (!cur.id) { cur = null; renderTicket(); return; }
    if (!confirm('Cancel order ' + cur.order_no + '? This cannot be undone.')) return;
    api('cancel_order', { order_id: cur.id }).then(function (j) { data.orders = j.orders; cur = null; renderTicket(); toast('Order cancelled'); refreshOrders(); })
      .catch(function (e) { toast(e.message, true); });
  }

  // ------------------------------------------------------------ events
  $('#cats').addEventListener('click', function (e) { var b = e.target.closest('[data-cat]'); if (!b) return; activeCat = parseInt(b.dataset.cat, 10); renderCats(); renderItems(); });
  $('#items').addEventListener('click', function (e) { var b = e.target.closest('[data-item]'); if (b) addItem(b.dataset.item); });
  $('#search').addEventListener('input', renderItems);
  $('#orderList').addEventListener('click', function (e) { var c = e.target.closest('[data-order]'); if (c) openOrder(c.dataset.order); });
  document.querySelectorAll('[data-new]').forEach(function (b) { b.addEventListener('click', function () { openNew(b.dataset.new); }); });
  $('#ticketLines').addEventListener('click', function (e) {
    var t = e.target;
    if (t.dataset.inc !== undefined) { cur.pending[t.dataset.inc].qty++; renderTicket(); }
    if (t.dataset.dec !== undefined) { var l = cur.pending[t.dataset.dec]; if (--l.qty <= 0) cur.pending.splice(t.dataset.dec, 1); renderTicket(); }
    if (t.dataset.void !== undefined) {
      if (!confirm('Void this item?')) return;
      api('void_item', { item_id: t.dataset.void }).then(function (j) { data.orders = j.orders; setCur(j.order); toast('Item voided'); }).catch(function (e) { toast(e.message, true); });
    }
  });
  $('#ticketLines').addEventListener('input', function (e) { if (e.target.dataset.note !== undefined) cur.pending[e.target.dataset.note].notes = e.target.value; });
  $('#btnSend').addEventListener('click', send);
  $('#btnBill').addEventListener('click', function () { window.open(POS.receipt + '?order=' + cur.id, 'receipt', 'width=420,height=700'); });
  $('#btnSettle').addEventListener('click', openPay);
  $('#btnRoom').addEventListener('click', openRoom);
  $('#btnCancel').addEventListener('click', cancelOrder);

  function tick() { $('#clock').textContent = new Date().toLocaleString(); }
  setInterval(tick, 1000); tick();
  setInterval(refreshOrders, 15000);
  load();
})();

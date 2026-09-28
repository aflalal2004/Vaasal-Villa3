<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_perm('pos.use');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title>POS Terminal · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/pos.css')) ?>">
</head>
<body>
<header class="pos-top">
  <div class="pos-brand"><span class="mark">V</span> Vaasal Villa <b>POS</b></div>
  <div class="pos-clock" id="clock"></div>
  <nav>
    <?php if (can('kitchen.view')): ?><a href="kitchen.php" target="_blank">🍳 Kitchen</a><?php endif; ?>
    <?php if (can_any(['pos.settle', 'billing.manage'])): ?><a href="<?= e(url('admin/orders.php')) ?>">📋 Orders</a><?php endif; ?>
    <a href="<?= e(url(can('dashboard.view') ? 'admin/index.php' : 'staff/index.php')) ?>">⌂ Back office</a>
    <span class="who"><?= e($u['full_name']) ?> · <?= e($u['role_name']) ?></span>
    <a href="<?= e(url('logout.php')) ?>" class="out">Sign out</a>
  </nav>
</header>

<div class="pos">
  <!-- Open orders -->
  <section class="pane orders">
    <div class="pane-head">
      <h3>Open orders</h3>
    </div>
    <div class="new-buttons">
      <button data-new="dine_in">🍽 Dine-in</button>
      <button data-new="takeaway">🥡 Takeaway</button>
      <button data-new="room_service">🛎 Guest / Room</button>
    </div>
    <div id="orderList" class="order-list"></div>
  </section>

  <!-- Menu -->
  <section class="pane menu">
    <div class="menu-tools">
      <input id="search" type="search" placeholder="Search menu…">
    </div>
    <div id="cats" class="cats"></div>
    <div id="items" class="items"></div>
  </section>

  <!-- Ticket -->
  <section class="pane ticket">
    <div id="ticketHead" class="ticket-head"></div>
    <div id="ticketLines" class="ticket-lines"></div>
    <div id="ticketTotals" class="ticket-totals"></div>
    <div class="ticket-actions">
      <button id="btnSend" class="primary">Send to kitchen (KOT)</button>
      <div class="row">
        <button id="btnBill">Print bill</button>
        <button id="btnSettle" class="pay">Settle</button>
      </div>
      <div class="row">
        <button id="btnRoom" class="room">Charge to room</button>
        <button id="btnCancel" class="danger">Cancel order</button>
      </div>
    </div>
  </section>
</div>

<!-- New order dialog -->
<dialog id="dlgNew">
  <form method="dialog" id="formNew">
    <h3 id="dlgNewTitle">New order</h3>
    <div id="newTable" class="fld"><label>Table</label><div id="tableGrid" class="table-grid"></div></div>
    <div id="newGuest" class="fld"><label>In-house guest (room)</label><select id="newBooking"></select></div>
    <div class="fld"><label>Customer name <small>(optional)</small></label><input id="newName"></div>
    <div class="fld"><label>Covers</label><input id="newCovers" type="number" min="1" value="2"></div>
    <div class="dlg-actions"><button value="cancel" formnovalidate>Cancel</button><button id="newOk" value="ok" class="primary">Start order</button></div>
  </form>
</dialog>

<!-- Settle dialog -->
<dialog id="dlgPay">
  <form method="dialog" id="formPay">
    <h3>Settle bill</h3>
    <div class="fld"><label>Discount (amount)</label><input id="payDiscount" type="number" step="0.01" min="0" value="0"></div>
    <div id="payTotals" class="pay-totals"></div>
    <div class="fld"><label>Method</label>
      <div class="methods">
        <label><input type="radio" name="pm" value="cash" checked> Cash</label>
        <label><input type="radio" name="pm" value="card"> Card</label>
        <label><input type="radio" name="pm" value="online"> Online/QR</label>
        <label><input type="radio" name="pm" value="voucher"> Voucher</label>
      </div></div>
    <div class="fld" id="tenderedWrap"><label>Cash tendered</label><input id="payTendered" type="number" step="0.01" min="0"><div id="payChange" class="change"></div></div>
    <div class="fld"><label>Reference</label><input id="payRef" placeholder="Card approval / voucher no."></div>
    <div class="dlg-actions"><button value="cancel" formnovalidate>Cancel</button><button value="ok" class="pay">Confirm payment</button></div>
  </form>
</dialog>

<!-- Room charge dialog -->
<dialog id="dlgRoom">
  <form method="dialog" id="formRoom">
    <h3>Charge to guest room</h3>
    <p class="hint">The bill is posted to the guest folio and settled at check-out.</p>
    <div class="fld"><label>Guest / room</label><select id="roomBooking"></select></div>
    <div class="fld"><label>Discount (amount)</label><input id="roomDiscount" type="number" step="0.01" min="0" value="0"></div>
    <div class="dlg-actions"><button value="cancel" formnovalidate>Cancel</button><button value="ok" class="room">Post to room</button></div>
  </form>
</dialog>

<div id="toast" class="toast"></div>
<script>
window.POS = { api: <?= json_encode(url('pos/api.php')) ?>, receipt: <?= json_encode(url('pos/receipt.php')) ?> };
</script>
<script src="<?= e(url('assets/js/pos.js')) ?>"></script>
</body>
</html>

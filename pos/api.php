<?php
/**
 * POS & Kitchen JSON API. All POST calls need the X-CSRF-Token header.
 * Actions: bootstrap, order, send, settle, room_charge, void_item, cancel_order, kots, kot_status
 */
require __DIR__ . '/../includes/bootstrap.php';

$action = $_GET['action'] ?? '';
$body = [];
if (is_post()) {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '[]', true) ?: $_POST;
}
$in = fn(string $k, $d = null) => $body[$k] ?? $d;

try {
    switch ($action) {
        // ------------------------------------------------------------ reads
        case 'bootstrap':
            require_perm('pos.use');
            json_out([
                'ok' => true,
                'categories' => q_all('SELECT id, name, station FROM menu_categories WHERE is_active = 1 ORDER BY sort_order, name'),
                'items' => q_all('SELECT i.id, i.category_id, i.name, i.description, i.price + 0 AS price, i.is_veg FROM menu_items i JOIN menu_categories c ON c.id = i.category_id WHERE i.is_available = 1 AND c.is_active = 1 ORDER BY i.name'),
                'tables' => q_all('SELECT id, table_no, area, seats, status FROM restaurant_tables ORDER BY area, table_no'),
                'bookings' => in_house_bookings(),
                'orders' => open_orders(),
                'can_settle' => can('pos.settle'),
                'settings' => ['currency' => setting('currency_symbol', '$'), 'tax' => (float) setting('tax_rate'), 'service' => (float) setting('service_charge')],
            ]);

        case 'order':
            require_perm('pos.use');
            $o = get_order((int) ($_GET['id'] ?? 0));
            if (!$o) throw new RuntimeException('Order not found.');
            json_out(['ok' => true, 'order' => $o]);

        // ------------------------------------------------------------ send items to kitchen (creates order if needed)
        case 'send':
            require_perm('pos.use');
            if (!is_post()) throw new RuntimeException('POST required.');
            $items = array_values(array_filter((array) $in('items', []), fn($i) => (int) ($i['qty'] ?? 0) > 0));
            if (!$items) throw new RuntimeException('Add at least one item.');
            $res = tx(function () use ($in, $items) {
                $orderId = (int) $in('order_id', 0);
                if ($orderId) {
                    $o = q_row("SELECT * FROM orders WHERE id = ? AND status = 'open' FOR UPDATE", [$orderId]);
                    if (!$o) throw new RuntimeException('Order is no longer open.');
                } else {
                    $type = $in('order_type');
                    if (!in_array($type, ['dine_in', 'takeaway', 'room_service'], true)) throw new RuntimeException('Choose an order type.');
                    $tableId = $type === 'dine_in' ? (int) $in('table_id', 0) : 0;
                    $bookingId = (int) $in('booking_id', 0);
                    if ($type === 'dine_in' && !$tableId) throw new RuntimeException('Select a table for dine-in.');
                    if ($type === 'room_service' && !$bookingId) throw new RuntimeException('Select the in-house guest / room.');
                    $name = trim((string) $in('customer_name', ''));
                    if ($bookingId) {
                        $bk = q_row("SELECT b.id, r.room_number, CONCAT(g.first_name,' ',g.last_name) n FROM bookings b JOIN guests g ON g.id=b.guest_id LEFT JOIN rooms r ON r.id=b.room_id WHERE b.id=? AND b.status='checked_in'", [$bookingId]);
                        if (!$bk) throw new RuntimeException('That guest is not checked in.');
                        if ($name === '') $name = "{$bk['n']} ({$bk['room_number']})";
                    }
                    if ($tableId) {
                        $tno = q_val('SELECT table_no FROM restaurant_tables WHERE id = ?', [$tableId]);
                        if (!$tno) throw new RuntimeException('Invalid table.');
                        if ($name === '') $name = "Table $tno";
                        q("UPDATE restaurant_tables SET status = 'occupied' WHERE id = ?", [$tableId]);
                    }
                    if ($name === '') $name = 'Takeaway';
                    q('INSERT INTO orders (order_no, order_type, table_id, booking_id, customer_name, covers, cashier_id) VALUES (?,?,?,?,?,?,?)',
                      [next_code('orders', 'order_no', 'ORD'), $type, $tableId ?: null, $bookingId ?: null, mb_substr($name, 0, 120), max(1, (int) $in('covers', 1)), uid()]);
                    $orderId = (int) db()->lastInsertId();
                }
                $kotNo = next_code('kots', 'kot_no', 'KOT');
                q('INSERT INTO kots (kot_no, order_id, created_by) VALUES (?,?,?)', [$kotNo, $orderId, uid()]);
                $kotId = (int) db()->lastInsertId();
                foreach ($items as $it) {
                    $m = q_row('SELECT id, name, price FROM menu_items WHERE id = ? AND is_available = 1', [(int) $it['menu_item_id']]);
                    if (!$m) throw new RuntimeException('An item is no longer available.');
                    $qty = min(99, (int) $it['qty']);
                    q("INSERT INTO order_items (order_id, menu_item_id, kot_id, item_name, qty, unit_price, line_total, notes, status) VALUES (?,?,?,?,?,?,?,?, 'sent')",
                      [$orderId, $m['id'], $kotId, $m['name'], $qty, $m['price'], round($m['price'] * $qty, 2), mb_substr(trim((string) ($it['notes'] ?? '')), 0, 255) ?: null]);
                }
                recalc_order($orderId);
                log_activity('pos', "KOT $kotNo sent for order #$orderId");
                return ['order_id' => $orderId, 'kot_no' => $kotNo, 'kot_id' => $kotId];
            });
            json_out(['ok' => true] + $res + ['order' => get_order($res['order_id']), 'orders' => open_orders()]);

        // ------------------------------------------------------------ settle with cash / card
        case 'settle':
            require_perm('pos.settle');
            $res = tx(function () use ($in) {
                $o = q_row("SELECT * FROM orders WHERE id = ? AND status = 'open' FOR UPDATE", [(int) $in('order_id')]);
                if (!$o) throw new RuntimeException('Order is not open.');
                $method = $in('method');
                if (!in_array($method, ['cash', 'card', 'online', 'voucher'], true)) throw new RuntimeException('Choose a payment method.');
                $t = recalc_order((int) $o['id'], (float) $in('discount', 0));
                if ($t['total'] <= 0 && $t['subtotal'] <= 0) throw new RuntimeException('Order has no items.');
                $tendered = $method === 'cash' ? (float) $in('tendered', $t['total']) : $t['total'];
                if ($tendered + 0.009 < $t['total']) throw new RuntimeException('Amount tendered is less than the total.');
                q("UPDATE orders SET status='closed', payment_status='paid', closed_at=NOW() WHERE id=?", [$o['id']]);
                $inv = create_restaurant_invoice((int) $o['id'], $t['total']);
                q('INSERT INTO payments (order_id, invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?,?)',
                  [$o['id'], $inv, $t['total'], $method, mb_substr((string) $in('reference', ''), 0, 100) ?: null, uid()]);
                q("UPDATE kots SET status='served', updated_at=NOW() WHERE order_id=? AND status='ready'", [$o['id']]);
                release_table($o['table_id'] ? (int) $o['table_id'] : null);
                log_activity('pos', "Settled {$o['order_no']} " . money($t['total']) . " by $method");
                return ['order_id' => (int) $o['id'], 'invoice_id' => $inv, 'total' => $t['total'], 'change' => round($tendered - $t['total'], 2)];
            });
            json_out(['ok' => true] + $res + ['orders' => open_orders()]);

        // ------------------------------------------------------------ charge to guest room folio
        case 'room_charge':
            require_perm('pos.settle');
            $res = tx(function () use ($in) {
                $o = q_row("SELECT * FROM orders WHERE id = ? AND status = 'open' FOR UPDATE", [(int) $in('order_id')]);
                if (!$o) throw new RuntimeException('Order is not open.');
                $bookingId = (int) $in('booking_id', $o['booking_id']);
                $bk = q_row("SELECT b.*, r.room_number FROM bookings b LEFT JOIN rooms r ON r.id=b.room_id WHERE b.id=? AND b.status='checked_in'", [$bookingId]);
                if (!$bk) throw new RuntimeException('Room charge needs a checked-in guest.');
                $t = recalc_order((int) $o['id'], (float) $in('discount', 0));
                if ($t['subtotal'] <= 0) throw new RuntimeException('Order has no items.');
                q("UPDATE orders SET status='closed', payment_status='room_charge', booking_id=?, closed_at=NOW() WHERE id=?", [$bookingId, $o['id']]);
                q("INSERT INTO folio_charges (booking_id, charge_type, description, amount, order_id, posted_by) VALUES (?, 'restaurant', ?, ?, ?, ?)",
                  [$bookingId, "Restaurant {$o['order_no']} (" . label($o['order_type']) . ')', $t['total'], $o['id'], uid()]);
                refresh_booking_payment_status($bookingId);
                q("UPDATE kots SET status='served', updated_at=NOW() WHERE order_id=? AND status='ready'", [$o['id']]);
                release_table($o['table_id'] ? (int) $o['table_id'] : null);
                log_activity('pos', "Charged {$o['order_no']} " . money($t['total']) . " to room {$bk['room_number']} ({$bk['booking_ref']})");
                return ['order_id' => (int) $o['id'], 'total' => $t['total'], 'room' => $bk['room_number']];
            });
            json_out(['ok' => true] + $res + ['orders' => open_orders()]);

        case 'void_item':
            require_perm('pos.settle');
            $it = q_row("SELECT oi.* FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.id=? AND o.status='open'", [(int) $in('item_id')]);
            if (!$it) throw new RuntimeException('Item not found on an open order.');
            q("UPDATE order_items SET status='cancelled' WHERE id=?", [$it['id']]);
            recalc_order((int) $it['order_id']);
            log_activity('pos', "Voided item {$it['item_name']} x{$it['qty']} on order #{$it['order_id']}");
            json_out(['ok' => true, 'order' => get_order((int) $it['order_id']), 'orders' => open_orders()]);

        case 'cancel_order':
            require_perm('pos.settle');
            $o = q_row("SELECT * FROM orders WHERE id=? AND status='open'", [(int) $in('order_id')]);
            if (!$o) throw new RuntimeException('Order is not open.');
            q("UPDATE orders SET status='cancelled', closed_at=NOW() WHERE id=?", [$o['id']]);
            q("UPDATE order_items SET status='cancelled' WHERE order_id=?", [$o['id']]);
            q("UPDATE kots SET status='served', updated_at=NOW() WHERE order_id=?", [$o['id']]);
            release_table($o['table_id'] ? (int) $o['table_id'] : null);
            log_activity('pos', "Cancelled order {$o['order_no']}");
            json_out(['ok' => true, 'orders' => open_orders()]);

        // ------------------------------------------------------------ kitchen display
        case 'kots':
            require_perm('kitchen.view');
            $kots = q_all("SELECT k.*, o.order_no, o.order_type, o.customer_name, t.table_no, u.full_name waiter,
                             TIMESTAMPDIFF(MINUTE, k.created_at, NOW()) AS age
                           FROM kots k JOIN orders o ON o.id=k.order_id LEFT JOIN restaurant_tables t ON t.id=o.table_id LEFT JOIN users u ON u.id=k.created_by
                           WHERE k.status IN ('new','preparing','ready') AND o.status <> 'cancelled' ORDER BY k.created_at");
            foreach ($kots as &$k) {
                $k['items'] = q_all("SELECT oi.item_name, oi.qty, oi.notes, oi.status, c.station FROM order_items oi LEFT JOIN menu_items m ON m.id=oi.menu_item_id LEFT JOIN menu_categories c ON c.id=m.category_id WHERE oi.kot_id=? AND oi.status <> 'cancelled'", [$k['id']]);
            }
            json_out(['ok' => true, 'kots' => array_values(array_filter($kots, fn($k) => $k['items']))]);

        case 'kot_status':
            require_perm(['kitchen.view', 'pos.use']);
            $st = $in('status');
            if (!in_array($st, ['new', 'preparing', 'ready', 'served'], true)) throw new RuntimeException('Invalid status.');
            q('UPDATE kots SET status=?, updated_at=NOW() WHERE id=?', [$st, (int) $in('kot_id')]);
            log_activity('kitchen', 'KOT #' . (int) $in('kot_id') . " → $st");
            json_out(['ok' => true]);

        default:
            json_out(['ok' => false, 'error' => 'Unknown action'], 400);
    }
} catch (RuntimeException $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    json_out(['ok' => false, 'error' => APP_DEBUG ? $ex->getMessage() : 'Server error'], 500);
}

function open_orders(): array
{
    return q_all("SELECT o.id, o.order_no, o.order_type, o.customer_name, o.total + 0 AS total, o.created_at, t.table_no,
                    (SELECT MIN(FIELD(k.status,'new','preparing','ready','served')) FROM kots k WHERE k.order_id=o.id) AS kot_stage
                  FROM orders o LEFT JOIN restaurant_tables t ON t.id=o.table_id WHERE o.status='open' ORDER BY o.created_at");
}

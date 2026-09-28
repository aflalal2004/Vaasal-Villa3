<?php
/**
 * Shared business logic. Website, admin, POS and channel API all call these
 * functions so that bookings, rooms, folios, orders and invoices stay consistent.
 */

const ACTIVE_BOOKING_STATUSES = "'pending','confirmed','checked_in'";

// =====================================================================
// Availability
// =====================================================================

/** Number of rooms of a type still bookable for a date range. */
function type_availability(int $typeId, string $in, string $out, ?int $excludeBooking = null): int
{
    $total = (int) q_val("SELECT COUNT(*) FROM rooms WHERE room_type_id = ? AND status <> 'maintenance'", [$typeId]);
    $booked = (int) q_val(
        'SELECT COUNT(*) FROM bookings WHERE room_type_id = ? AND status IN (' . ACTIVE_BOOKING_STATUSES . ')
         AND check_in < ? AND check_out > ? AND id <> ?',
        [$typeId, $out, $in, $excludeBooking ?? 0]
    );
    return max(0, $total - $booked);
}

/** All active room types with availability & price for a date range. */
function search_availability(string $in, string $out, int $adults = 1, int $children = 0): array
{
    $nights = nights_between($in, $out);
    $types = q_all('SELECT * FROM room_types WHERE is_active = 1 ORDER BY base_rate');
    foreach ($types as &$t) {
        $t['available'] = type_availability((int) $t['id'], $in, $out);
        $t['fits'] = $adults <= $t['max_adults'] && $children <= $t['max_children'];
        $t['nights'] = $nights;
        $t['total'] = round($t['base_rate'] * $nights, 2);
    }
    return $types;
}

/** Physical rooms of a type free for the whole date range (not assigned to an overlapping booking). */
function free_rooms(int $typeId, string $in, string $out, ?int $excludeBooking = null): array
{
    return q_all(
        "SELECT r.* FROM rooms r
         WHERE r.room_type_id = ? AND r.status <> 'maintenance'
           AND r.id NOT IN (
             SELECT room_id FROM bookings
             WHERE room_id IS NOT NULL AND status IN (" . ACTIVE_BOOKING_STATUSES . ")
               AND check_in < ? AND check_out > ? AND id <> ?)
         ORDER BY r.room_number",
        [$typeId, $out, $in, $excludeBooking ?? 0]
    );
}

// =====================================================================
// Guests & bookings
// =====================================================================

/** Re-use a guest with the same email, otherwise create. Returns guest id. */
function find_or_create_guest(array $g): int
{
    $email = trim($g['email'] ?? '');
    if ($email !== '') {
        $id = q_val('SELECT id FROM guests WHERE email = ? ORDER BY id LIMIT 1', [$email]);
        if ($id) {
            q('UPDATE guests SET phone = COALESCE(NULLIF(?, ""), phone), country = COALESCE(NULLIF(?, ""), country) WHERE id = ?',
              [$g['phone'] ?? '', $g['country'] ?? '', $id]);
            return (int) $id;
        }
    }
    q('INSERT INTO guests (first_name, last_name, email, phone, country, id_type, id_number, address) VALUES (?,?,?,?,?,?,?,?)', [
        $g['first_name'], $g['last_name'], $email ?: null, $g['phone'] ?? null, $g['country'] ?? null,
        $g['id_type'] ?? null, $g['id_number'] ?? null, $g['address'] ?? null,
    ]);
    return (int) db()->lastInsertId();
}

/**
 * Create a booking after validating dates, occupancy and availability.
 * $d keys: guest_id, source_id, room_type_id, check_in, check_out, adults, children,
 *          [room_id, rate_per_night, status, external_ref, special_requests, payment_status]
 * Returns ['id'=>..,'ref'=>..]; throws RuntimeException with a user-facing message.
 */
function create_booking(array $d): array
{
    $in = $d['check_in'] ?? '';
    $out = $d['check_out'] ?? '';
    if (!valid_date($in) || !valid_date($out) || $out <= $in) {
        throw new RuntimeException('Please choose valid check-in and check-out dates.');
    }
    if (!empty($d['public']) && $in < date('Y-m-d')) {
        throw new RuntimeException('Check-in date cannot be in the past.');
    }
    $type = q_row('SELECT * FROM room_types WHERE id = ? AND is_active = 1', [(int) $d['room_type_id']]);
    if (!$type) throw new RuntimeException('Selected room type does not exist.');
    $adults = max(1, (int) ($d['adults'] ?? 1));
    $children = max(0, (int) ($d['children'] ?? 0));
    if ($adults > $type['max_adults'] || $children > $type['max_children']) {
        throw new RuntimeException("{$type['name']} allows up to {$type['max_adults']} adults and {$type['max_children']} children.");
    }

    return tx(function () use ($d, $type, $in, $out, $adults, $children) {
        // lock room type rows to avoid double booking on concurrent requests
        q('SELECT id FROM rooms WHERE room_type_id = ? FOR UPDATE', [$type['id']]);
        if (type_availability((int) $type['id'], $in, $out) < 1) {
            throw new RuntimeException("Sorry, no {$type['name']} is available for those dates.");
        }
        $roomId = !empty($d['room_id']) ? (int) $d['room_id'] : null;
        if ($roomId && !in_array($roomId, array_map('intval', array_column(free_rooms((int) $type['id'], $in, $out), 'id')), true)) {
            throw new RuntimeException('The selected room is not free for those dates.');
        }
        $rate = isset($d['rate_per_night']) && $d['rate_per_night'] !== '' ? (float) $d['rate_per_night'] : (float) $type['base_rate'];
        $nights = nights_between($in, $out);
        $ref = next_code('bookings', 'booking_ref', 'VV');
        q('INSERT INTO bookings (booking_ref, guest_id, source_id, external_ref, room_type_id, room_id, check_in, check_out,
              adults, children, rate_per_night, room_total, status, payment_status, special_requests, created_by)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $ref, (int) $d['guest_id'], (int) $d['source_id'], $d['external_ref'] ?? null, $type['id'], $roomId,
            $in, $out, $adults, $children, $rate, round($rate * $nights, 2),
            $d['status'] ?? 'confirmed', $d['payment_status'] ?? 'unpaid', $d['special_requests'] ?? null, uid(),
        ]);
        $id = (int) db()->lastInsertId();
        log_activity('booking', "Created booking $ref ({$type['name']}, $in → $out)");
        return ['id' => $id, 'ref' => $ref];
    });
}

function get_booking(int $id): ?array
{
    return q_row(
        'SELECT b.*, g.first_name, g.last_name, g.email, g.phone, g.country, g.vip,
                CONCAT(g.first_name, " ", g.last_name) AS guest_name,
                rt.name AS room_type, r.room_number, s.name AS source_name, s.type AS source_type
         FROM bookings b
         JOIN guests g ON g.id = b.guest_id
         JOIN room_types rt ON rt.id = b.room_type_id
         JOIN booking_sources s ON s.id = b.source_id
         LEFT JOIN rooms r ON r.id = b.room_id
         WHERE b.id = ?', [$id]);
}

// =====================================================================
// Front office: check-in, key cards, room charges, check-out
// =====================================================================

function issue_key_card(int $bookingId, int $roomId, string $validUntil): string
{
    $room = q_val('SELECT room_number FROM rooms WHERE id = ?', [$roomId]);
    $seq = (int) q_val('SELECT COUNT(*) FROM key_cards') + 1;
    $card = sprintf('KC-%s-%04d', $room, $seq);
    q('INSERT INTO key_cards (card_number, booking_id, room_id, valid_from, valid_until, issued_by) VALUES (?,?,?,NOW(),?,?)',
      [$card, $bookingId, $roomId, $validUntil, uid()]);
    log_activity('keycard', "Issued key card $card for room $room");
    return $card;
}

function deactivate_key_cards(int $bookingId, string $newStatus = 'returned'): void
{
    q("UPDATE key_cards SET status = ?, closed_at = NOW() WHERE booking_id = ? AND status = 'active'", [$newStatus, $bookingId]);
}

function check_in_booking(int $bookingId, int $roomId, int $cards = 1): void
{
    tx(function () use ($bookingId, $roomId, $cards) {
        $b = q_row('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
        if (!$b || !in_array($b['status'], ['pending', 'confirmed'], true)) {
            throw new RuntimeException('Only pending or confirmed bookings can be checked in.');
        }
        if ($b['check_in'] > date('Y-m-d')) {
            throw new RuntimeException('This booking arrives on ' . fdate($b['check_in']) . ' — it cannot be checked in early.');
        }
        $room = q_row('SELECT * FROM rooms WHERE id = ? FOR UPDATE', [$roomId]);
        if (!$room || (int) $room['room_type_id'] !== (int) $b['room_type_id']) {
            throw new RuntimeException('Please select a room of the booked type.');
        }
        if ($room['status'] !== 'available') {
            throw new RuntimeException("Room {$room['room_number']} is currently {$room['status']}.");
        }
        $free = array_map('intval', array_column(free_rooms((int) $b['room_type_id'], $b['check_in'], $b['check_out'], $bookingId), 'id'));
        if (!in_array($roomId, $free, true)) {
            throw new RuntimeException("Room {$room['room_number']} is assigned to another booking for these dates.");
        }
        q("UPDATE bookings SET status = 'checked_in', room_id = ?, checked_in_at = NOW() WHERE id = ?", [$roomId, $bookingId]);
        q("UPDATE rooms SET status = 'occupied' WHERE id = ?", [$roomId]);
        $until = $b['check_out'] . ' ' . setting('check_out_time', '11:00') . ':00';
        for ($i = 0; $i < max(1, min(4, $cards)); $i++) {
            issue_key_card($bookingId, $roomId, $until);
        }
        log_activity('frontdesk', "Checked in {$b['booking_ref']} to room {$room['room_number']}");
    });
}

/**
 * Post nightly room charges to the folio. Normal mode posts nights elapsed so far
 * (night audit). Final mode (check-out) posts every remaining chargeable night.
 */
function post_room_charges(int $bookingId, bool $final = false): int
{
    $b = q_row('SELECT b.*, r.room_number FROM bookings b LEFT JOIN rooms r ON r.id = b.room_id WHERE b.id = ?', [$bookingId]);
    if (!$b || $b['status'] !== 'checked_in') return 0;
    $planned = nights_between($b['check_in'], $b['check_out']);
    $elapsed = nights_between($b['check_in'], date('Y-m-d'));
    $due = $final ? max(1, min($planned, max($elapsed, 1))) : min($planned, $elapsed);
    if ($final && $elapsed >= $planned) $due = $planned;
    $posted = (int) q_val("SELECT COUNT(*) FROM folio_charges WHERE booking_id = ? AND charge_type = 'room'", [$bookingId]);
    $count = 0;
    for ($n = $posted + 1; $n <= $due; $n++) {
        q("INSERT INTO folio_charges (booking_id, charge_type, description, amount, posted_by) VALUES (?, 'room', ?, ?, ?)",
          [$bookingId, "Room charge {$b['room_number']} (night $n)", $b['rate_per_night'], uid()]);
        $count++;
    }
    return $count;
}

/** Folio totals for a booking. */
function folio_summary(int $bookingId): array
{
    $b = q_row('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
    $charges = q_all('SELECT fc.*, u.full_name AS posted_by_name FROM folio_charges fc LEFT JOIN users u ON u.id = fc.posted_by WHERE booking_id = ? ORDER BY posted_at, id', [$bookingId]);
    $payments = q_all('SELECT p.*, u.full_name AS received_by_name FROM payments p LEFT JOIN users u ON u.id = p.received_by WHERE booking_id = ? ORDER BY paid_at', [$bookingId]);
    $room = $extras = 0.0;
    foreach ($charges as $c) {
        if ($c['charge_type'] === 'room') $room += $c['amount']; else $extras += $c['amount'];
    }
    $taxRate = (float) setting('room_tax_rate', 0) / 100;
    $roomTax = round($room * $taxRate, 2);
    $posted = round($room + $roomTax + $extras, 2);
    $paid = round(array_sum(array_column($payments, 'amount')), 2);
    // Projected bill for the whole stay (used before check-out)
    $projected = in_array($b['status'] ?? '', ['checked_out'], true)
        ? $posted
        : round(max($room, (float) $b['room_total']) * (1 + $taxRate) + $extras, 2);
    return [
        'charges' => $charges, 'payments' => $payments, 'room' => round($room, 2), 'extras' => round($extras, 2),
        'room_tax' => $roomTax, 'posted_total' => $posted, 'projected_total' => $projected,
        'paid' => $paid, 'balance' => round($posted - $paid, 2), 'projected_balance' => round($projected - $paid, 2),
    ];
}

function refresh_booking_payment_status(int $bookingId): void
{
    $f = folio_summary($bookingId);
    $status = $f['paid'] <= 0 ? 'unpaid' : ($f['paid'] + 0.009 >= $f['projected_total'] ? 'paid' : 'partial');
    q('UPDATE bookings SET payment_status = ? WHERE id = ?', [$status, $bookingId]);
    // keep any room invoice in sync
    $inv = q_row("SELECT id, total FROM invoices WHERE booking_id = ? AND invoice_type = 'room' AND status <> 'void'", [$bookingId]);
    if ($inv) {
        $istatus = $f['paid'] <= 0 ? 'unpaid' : ($f['paid'] + 0.009 >= $inv['total'] ? 'paid' : 'partial');
        q('UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?', [$f['paid'], $istatus, $inv['id']]);
    }
}

function add_booking_payment(int $bookingId, float $amount, string $method, ?string $ref = null): void
{
    if ($amount <= 0) throw new RuntimeException('Payment amount must be greater than zero.');
    $inv = q_val("SELECT id FROM invoices WHERE booking_id = ? AND invoice_type = 'room' AND status <> 'void'", [$bookingId]);
    q('INSERT INTO payments (booking_id, invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?,?)',
      [$bookingId, $inv ?: null, $amount, $method, $ref, uid()]);
    refresh_booking_payment_status($bookingId);
    log_activity('billing', 'Payment ' . money($amount) . " ($method) for booking #$bookingId");
}

/** Refund a guest credit (overpayment, e.g. early departure). Stored as a negative payment. */
function refund_booking_credit(int $bookingId, float $amount, string $method, ?string $ref = null): void
{
    $f = folio_summary($bookingId);
    $credit = -$f['balance'];
    if ($amount <= 0 || $amount > $credit + 0.009) throw new RuntimeException('Refund must be between 0 and the guest credit of ' . money(max(0, $credit)) . '.');
    $inv = q_val("SELECT id FROM invoices WHERE booking_id = ? AND invoice_type = 'room' AND status <> 'void'", [$bookingId]);
    q('INSERT INTO payments (booking_id, invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?,?)',
      [$bookingId, $inv ?: null, -$amount, $method, 'REFUND' . ($ref ? " $ref" : ''), uid()]);
    refresh_booking_payment_status($bookingId);
    log_activity('billing', 'Refund ' . money($amount) . " ($method) for booking #$bookingId");
}

/** Build and store the final room invoice from the folio. Returns invoice id. */
function create_room_invoice(int $bookingId): int
{
    $b = get_booking($bookingId);
    $f = folio_summary($bookingId);
    $no = next_code('invoices', 'invoice_no', 'INV');
    q("INSERT INTO invoices (invoice_no, invoice_type, booking_id, guest_id, bill_to, subtotal, tax, total, amount_paid, status, created_by)
       VALUES (?, 'room', ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?)",
      [$no, $bookingId, $b['guest_id'], $b['guest_name'], $f['room'] + $f['extras'], $f['room_tax'], $f['posted_total'], $f['paid'], uid()]);
    $invId = (int) db()->lastInsertId();
    foreach ($f['charges'] as $c) {
        q('INSERT INTO invoice_items (invoice_id, description, qty, unit_price, amount) VALUES (?,?,1,?,?)',
          [$invId, $c['description'], $c['amount'], $c['amount']]);
    }
    q('UPDATE payments SET invoice_id = ? WHERE booking_id = ? AND invoice_id IS NULL', [$invId, $bookingId]);
    refresh_booking_payment_status($bookingId);
    log_activity('billing', "Generated invoice $no for {$b['booking_ref']}");
    return $invId;
}

/** Check-out: final charges, invoice, key cards off, room to housekeeping. Returns invoice id. */
function check_out_booking(int $bookingId, bool $allowBalance = false): int
{
    return tx(function () use ($bookingId, $allowBalance) {
        $b = q_row('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
        if (!$b || $b['status'] !== 'checked_in') throw new RuntimeException('Only checked-in bookings can be checked out.');
        $open = (int) q_val("SELECT COUNT(*) FROM orders WHERE booking_id = ? AND status = 'open'", [$bookingId]);
        if ($open) throw new RuntimeException('This guest has an open restaurant order. Settle or charge it to the room first.');
        post_room_charges($bookingId, true);
        $f = folio_summary($bookingId);
        if ($f['balance'] > 0.009 && !$allowBalance) {
            throw new RuntimeException('Outstanding balance of ' . money($f['balance']) . '. Take payment or tick "allow outstanding balance".');
        }
        q("UPDATE bookings SET status = 'checked_out', checked_out_at = NOW() WHERE id = ?", [$bookingId]);
        $invId = create_room_invoice($bookingId);
        deactivate_key_cards($bookingId, 'returned');
        if ($b['room_id']) {
            q("UPDATE rooms SET status = 'available', housekeeping_status = 'dirty' WHERE id = ?", [$b['room_id']]);
            q("INSERT INTO housekeeping_tasks (room_id, task_type, priority, notes, created_by) VALUES (?, 'checkout_clean', 'high', ?, ?)",
              [$b['room_id'], "Auto: guest checked out ({$b['booking_ref']})", uid()]);
        }
        log_activity('frontdesk', "Checked out {$b['booking_ref']}");
        return $invId;
    });
}

function cancel_booking(int $bookingId, string $status = 'cancelled'): void
{
    $b = q_row('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
    if (!$b || !in_array($b['status'], ['pending', 'confirmed'], true)) {
        throw new RuntimeException('Only pending or confirmed bookings can be cancelled.');
    }
    q('UPDATE bookings SET status = ? WHERE id = ?', [$status, $bookingId]);
    log_activity('booking', label($status) . " booking {$b['booking_ref']}");
}

// =====================================================================
// Staff time tracking
// =====================================================================

function clock_in(int $userId): void
{
    if (!q_val('SELECT id FROM users WHERE id = ? AND is_active = 1', [$userId])) throw new RuntimeException('Unknown staff member.');
    if (q_val('SELECT id FROM attendance WHERE user_id = ? AND clock_out IS NULL', [$userId])) throw new RuntimeException('Already clocked in.');
    q('INSERT INTO attendance (user_id, work_date, clock_in) VALUES (?, CURDATE(), NOW())', [$userId]);
    log_activity('attendance', "Clock in (user #$userId)");
}

function clock_out(int $userId): void
{
    $open = q_row('SELECT * FROM attendance WHERE user_id = ? AND clock_out IS NULL ORDER BY id DESC LIMIT 1', [$userId]);
    if (!$open) throw new RuntimeException('Not clocked in.');
    q('UPDATE attendance SET clock_out = NOW(), hours = ROUND(TIMESTAMPDIFF(SECOND, clock_in, NOW()) / 3600, 2) WHERE id = ?', [$open['id']]);
    log_activity('attendance', "Clock out (user #$userId)");
}

// =====================================================================
// Housekeeping
// =====================================================================

/** Move a housekeeping task along its workflow and update the room. $ownerId restricts to own tasks. */
function hk_set_status(int $taskId, string $status, ?int $ownerId = null): void
{
    $t = q_row('SELECT * FROM housekeeping_tasks WHERE id = ?', [$taskId]);
    if (!$t || ($ownerId !== null && (int) $t['assigned_to'] !== $ownerId)) throw new RuntimeException('Task not found.');
    $allowed = ['pending' => ['in_progress', 'done'], 'in_progress' => ['done'], 'done' => ['verified', 'in_progress'], 'verified' => []];
    if (!in_array($status, $allowed[$t['status']] ?? [], true)) throw new RuntimeException('Invalid status change.');
    if ($status === 'verified' && !can('housekeeping.manage')) throw new RuntimeException('Only supervisors can verify tasks.');
    q('UPDATE housekeeping_tasks SET status = ?, started_at = IF(? = "in_progress" AND started_at IS NULL, NOW(), started_at),
       completed_at = IF(? IN ("done","verified"), COALESCE(completed_at, NOW()), NULL) WHERE id = ?', [$status, $status, $status, $taskId]);
    if ($status === 'done') {
        if ($t['task_type'] === 'maintenance') {
            q("UPDATE rooms SET status = 'available', housekeeping_status = 'dirty' WHERE id = ? AND status = 'maintenance'", [$t['room_id']]);
        } elseif ($t['task_type'] !== 'inspection') {
            q("UPDATE rooms SET housekeeping_status = 'clean' WHERE id = ? AND housekeeping_status <> 'out_of_order'", [$t['room_id']]);
        }
    }
    if ($status === 'verified' || ($status === 'done' && $t['task_type'] === 'inspection')) {
        q("UPDATE rooms SET housekeeping_status = 'inspected' WHERE id = ? AND housekeeping_status IN ('clean','inspected')", [$t['room_id']]);
    }
    log_activity('housekeeping', "Task #$taskId → $status");
}

// =====================================================================
// Restaurant: orders, KOT, settlement
// =====================================================================

function order_totals(float $subtotal, float $discount): array
{
    $discount = min(max(0, $discount), $subtotal);
    $net = $subtotal - $discount;
    $svc = round($net * (float) setting('service_charge', 0) / 100, 2);
    $tax = round(($net + $svc) * (float) setting('tax_rate', 0) / 100, 2);
    return ['subtotal' => round($subtotal, 2), 'discount' => round($discount, 2), 'service_charge' => $svc, 'tax' => $tax, 'total' => round($net + $svc + $tax, 2)];
}

function recalc_order(int $orderId, ?float $discount = null): array
{
    $o = q_row('SELECT * FROM orders WHERE id = ?', [$orderId]);
    $sub = (float) q_val("SELECT COALESCE(SUM(line_total),0) FROM order_items WHERE order_id = ? AND status <> 'cancelled'", [$orderId]);
    $t = order_totals($sub, $discount ?? (float) $o['discount']);
    q('UPDATE orders SET subtotal=?, discount=?, service_charge=?, tax=?, total=? WHERE id=?',
      [$t['subtotal'], $t['discount'], $t['service_charge'], $t['tax'], $t['total'], $orderId]);
    return $t;
}

function get_order(int $id): ?array
{
    $o = q_row('SELECT o.*, t.table_no, u.full_name AS cashier, b.booking_ref, rm.room_number
                FROM orders o LEFT JOIN restaurant_tables t ON t.id = o.table_id LEFT JOIN users u ON u.id = o.cashier_id
                LEFT JOIN bookings b ON b.id = o.booking_id LEFT JOIN rooms rm ON rm.id = b.room_id WHERE o.id = ?', [$id]);
    if ($o) {
        $o['items'] = q_all("SELECT oi.*, k.kot_no, k.status AS kot_status FROM order_items oi LEFT JOIN kots k ON k.id = oi.kot_id WHERE oi.order_id = ? AND oi.status <> 'cancelled' ORDER BY oi.id", [$id]);
        $o['payments'] = q_all('SELECT * FROM payments WHERE order_id = ?', [$id]);
    }
    return $o;
}

function create_restaurant_invoice(int $orderId, float $paid): int
{
    $o = get_order($orderId);
    $no = next_code('invoices', 'invoice_no', 'INV');
    $guestId = $o['booking_id'] ? q_val('SELECT guest_id FROM bookings WHERE id = ?', [$o['booking_id']]) : null;
    $status = $paid + 0.009 >= $o['total'] ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
    q("INSERT INTO invoices (invoice_no, invoice_type, order_id, booking_id, guest_id, bill_to, subtotal, discount, service_charge, tax, total, amount_paid, status, created_by)
       VALUES (?, 'restaurant', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
      [$no, $orderId, $o['booking_id'], $guestId, $o['customer_name'] ?: 'Walk-in customer', $o['subtotal'], $o['discount'],
       $o['service_charge'], $o['tax'], $o['total'], min($paid, $o['total']), $status, uid()]);
    $invId = (int) db()->lastInsertId();
    foreach ($o['items'] as $it) {
        q('INSERT INTO invoice_items (invoice_id, description, qty, unit_price, amount) VALUES (?,?,?,?,?)',
          [$invId, $it['item_name'], $it['qty'], $it['unit_price'], $it['line_total']]);
    }
    return $invId;
}

/** Release a restaurant table when no other open order uses it. */
function release_table(?int $tableId): void
{
    if (!$tableId) return;
    $open = (int) q_val("SELECT COUNT(*) FROM orders WHERE table_id = ? AND status = 'open'", [$tableId]);
    if (!$open) q("UPDATE restaurant_tables SET status = 'free' WHERE id = ?", [$tableId]);
}

/** In-house guests (checked in) that restaurant can charge to. */
function in_house_bookings(): array
{
    return q_all("SELECT b.id, b.booking_ref, r.room_number, CONCAT(g.first_name,' ',g.last_name) AS guest_name
                  FROM bookings b JOIN guests g ON g.id = b.guest_id LEFT JOIN rooms r ON r.id = b.room_id
                  WHERE b.status = 'checked_in' ORDER BY r.room_number");
}

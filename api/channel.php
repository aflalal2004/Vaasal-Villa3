<?php
/**
 * Channel API for external booking sites (OTAs) and tour operators.
 * POST JSON with header  X-Api-Key: <key from Admin > Booking Channels>
 * Actions: availability | create | cancel | status
 */
require __DIR__ . '/../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false, 'error' => 'POST JSON required'], 405);
$key = $_SERVER['HTTP_X_API_KEY'] ?? '';
$source = $key !== '' ? q_row("SELECT * FROM booking_sources WHERE api_key = ? AND is_active = 1 AND type IN ('ota','tour_operator')", [$key]) : null;
if (!$source) json_out(['ok' => false, 'error' => 'Invalid or missing API key'], 401);

$req = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($req)) json_out(['ok' => false, 'error' => 'Body must be JSON'], 400);

try {
    switch ($req['action'] ?? '') {
        case 'availability':
            $in = (string) ($req['check_in'] ?? '');
            $out = (string) ($req['check_out'] ?? '');
            if (!valid_date($in) || !valid_date($out) || $out <= $in) throw new RuntimeException('Invalid dates');
            $types = array_map(fn($t) => ['room_type_id' => (int) $t['id'], 'name' => $t['name'], 'available' => $t['available'], 'rate_per_night' => (float) $t['base_rate']],
                search_availability($in, $out));
            json_out(['ok' => true, 'room_types' => $types]);

        case 'create':
            $g = (array) ($req['guest'] ?? []);
            if (empty($g['first_name']) || empty($g['last_name'])) throw new RuntimeException('guest.first_name and guest.last_name are required');
            if (!empty($g['email']) && !filter_var($g['email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid guest email');
            $ext = trim((string) ($req['external_ref'] ?? ''));
            if ($ext !== '' && ($dup = q_row('SELECT booking_ref FROM bookings WHERE source_id=? AND external_ref=?', [$source['id'], $ext]))) {
                json_out(['ok' => true, 'duplicate' => true, 'booking_ref' => $dup['booking_ref']]);
            }
            $guestId = find_or_create_guest(array_map(fn($v) => mb_substr(trim((string) $v), 0, 160), $g));
            $res = create_booking([
                'guest_id' => $guestId, 'source_id' => $source['id'], 'room_type_id' => (int) ($req['room_type_id'] ?? 0),
                'check_in' => (string) ($req['check_in'] ?? ''), 'check_out' => (string) ($req['check_out'] ?? ''),
                'adults' => (int) ($req['adults'] ?? 1), 'children' => (int) ($req['children'] ?? 0),
                'rate_per_night' => isset($req['rate_per_night']) ? (float) $req['rate_per_night'] : '',
                'status' => 'confirmed', 'external_ref' => $ext ?: null, 'public' => true,
                'special_requests' => mb_substr((string) ($req['special_requests'] ?? ''), 0, 1000) ?: null,
            ]);
            log_activity('channel', "{$source['name']} created booking {$res['ref']}" . ($ext ? " ($ext)" : ''));
            json_out(['ok' => true, 'booking_ref' => $res['ref'], 'status' => 'confirmed'], 201);

        case 'cancel':
        case 'status':
            $b = q_row('SELECT * FROM bookings WHERE source_id = ? AND (booking_ref = ? OR external_ref = ?)',
                       [$source['id'], (string) ($req['booking_ref'] ?? ''), (string) ($req['external_ref'] ?? '#none#')]);
            if (!$b) throw new RuntimeException('Booking not found for this channel');
            if ($req['action'] === 'cancel') {
                cancel_booking((int) $b['id']);
                $b['status'] = 'cancelled';
            }
            json_out(['ok' => true, 'booking_ref' => $b['booking_ref'], 'external_ref' => $b['external_ref'], 'status' => $b['status'],
                      'check_in' => $b['check_in'], 'check_out' => $b['check_out'], 'room_total' => (float) $b['room_total']]);

        default:
            throw new RuntimeException('Unknown action');
    }
} catch (RuntimeException $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 422);
}

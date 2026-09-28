<?php
/**
 * Public availability API used by the website booking widget.
 * GET ?check_in=YYYY-MM-DD&check_out=YYYY-MM-DD&adults=2&children=0
 */
require __DIR__ . '/../includes/bootstrap.php';
header('Access-Control-Allow-Origin: *');

$in = input('check_in');
$out = input('check_out');
if (!valid_date($in) || !valid_date($out) || $out <= $in) json_out(['ok' => false, 'error' => 'Provide valid check_in and check_out dates.'], 422);
if ($in < date('Y-m-d')) json_out(['ok' => false, 'error' => 'check_in cannot be in the past.'], 422);
if (nights_between($in, $out) > 60) json_out(['ok' => false, 'error' => 'Maximum stay is 60 nights.'], 422);

$res = array_map(fn($t) => [
    'room_type_id' => (int) $t['id'], 'name' => $t['name'], 'rate_per_night' => (float) $t['base_rate'], 'nights' => $t['nights'],
    'total' => $t['total'], 'available' => $t['available'], 'fits_party' => $t['fits'],
    'max_adults' => (int) $t['max_adults'], 'max_children' => (int) $t['max_children'],
], search_availability($in, $out, max(1, input_int('adults', 1)), max(0, input_int('children'))));

json_out(['ok' => true, 'check_in' => $in, 'check_out' => $out, 'currency' => setting('currency_symbol'), 'room_types' => $res]);

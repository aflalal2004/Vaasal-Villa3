<?php
/** General helpers: escaping, URLs, flash messages, CSRF, formatting. */

function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

function input_float(string $key, float $default = 0.0): float
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($v) ? (float) $v : $default;
}

function valid_date(?string $d): bool
{
    if (!$d) return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ---------------------------------------------------------------- flash
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------- CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** Abort any POST that lacks a valid CSRF token. */
function csrf_check(): void
{
    if (is_post() && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && !isset($_SERVER['HTTP_X_CSRF_TOKEN'])
        && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data')) {
        http_response_code(413);
        exit('The uploaded file is larger than the server limit (' . e(ini_get('post_max_size')) . '). <a href="javascript:history.back()">Go back</a> and choose a smaller file.');
    }
    if (is_post() && !csrf_valid()) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            json_out(['ok' => false, 'error' => 'Security token expired. Please reload the page.'], 403);
        }
        http_response_code(403);
        exit('Security token expired or invalid. <a href="javascript:history.back()">Go back</a> and reload the page.');
    }
}

// ---------------------------------------------------------------- settings / formatting
function setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q_all('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return $cache[$key] ?? $default;
}

function money($amount): string
{
    return setting('currency_symbol', '$') . number_format((float) $amount, 2);
}

function fdate(?string $d, string $fmt = 'd M Y'): string
{
    return $d ? date($fmt, strtotime($d)) : '—';
}

function fdatetime(?string $d): string
{
    return $d ? date('d M Y H:i', strtotime($d)) : '—';
}

function nights_between(string $in, string $out): int
{
    return max(0, (int) ((strtotime($out) - strtotime($in)) / 86400));
}

function label(string $s): string
{
    return ucwords(str_replace('_', ' ', $s));
}

/** Coloured status pill. */
function badge(string $status): string
{
    $map = [
        'confirmed' => 'blue', 'pending' => 'amber', 'checked_in' => 'green', 'checked_out' => 'gray',
        'cancelled' => 'red', 'no_show' => 'red', 'available' => 'green', 'occupied' => 'blue',
        'maintenance' => 'red', 'clean' => 'green', 'dirty' => 'amber', 'inspected' => 'teal',
        'out_of_order' => 'red', 'active' => 'green', 'returned' => 'gray', 'lost' => 'red',
        'deactivated' => 'gray', 'in_progress' => 'blue', 'done' => 'teal', 'verified' => 'green',
        'paid' => 'green', 'unpaid' => 'red', 'partial' => 'amber', 'void' => 'gray', 'open' => 'blue',
        'closed' => 'gray', 'room_charge' => 'purple', 'new' => 'red', 'preparing' => 'amber',
        'ready' => 'green', 'served' => 'gray', 'free' => 'green', 'reserved' => 'amber',
        'urgent' => 'red', 'high' => 'amber', 'normal' => 'blue', 'low' => 'gray',
    ];
    $c = $map[$status] ?? 'gray';
    return '<span class="badge badge-' . $c . '">' . e(label($status)) . '</span>';
}

/** Generate the next sequential code, e.g. ORD-000123. */
function next_code(string $table, string $column, string $prefix, int $pad = 6): string
{
    $allowed = ['bookings' => 'booking_ref', 'orders' => 'order_no', 'kots' => 'kot_no', 'invoices' => 'invoice_no'];
    if (($allowed[$table] ?? null) !== $column) {
        throw new InvalidArgumentException('Invalid code table');
    }
    $last = q_val("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '-%']);
    $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
    return $prefix . '-' . str_pad((string) $n, $pad, '0', STR_PAD_LEFT);
}

// ---------------------------------------------------------------- media
/** Absolute URL for a stored media value (remote https URL or local uploads path). */
function media_url(?string $v, string $fallback = ''): string
{
    $v = trim((string) $v);
    if ($v === '') return $fallback;
    if (preg_match('#^https?://#i', $v)) return $v;
    return url($v);
}

/** Resize hint for Unsplash URLs (other URLs are returned unchanged). */
function media_sized(?string $v, int $w): string
{
    $u = media_url($v);
    if (str_contains($u, 'images.unsplash.com')) {
        $u = preg_replace('/([?&])w=\d+/', '$1w=' . $w, $u);
    }
    return $u;
}

function video_mime(string $v): string
{
    return str_ends_with(strtolower((string) parse_url($v, PHP_URL_PATH)), '.webm') ? 'video/webm' : 'video/mp4';
}

/** Validate a remote media URL typed by an admin (https only). */
function valid_media_url(string $u): bool
{
    return (bool) preg_match('#^https://[^\s"\'<>]+$#i', $u) && filter_var($u, FILTER_VALIDATE_URL);
}

/**
 * Save an uploaded image/video to assets/uploads. Returns the relative path, or null if no file was sent.
 * $kind = 'image' | 'video'. Type is checked from the file contents, not the name.
 */
function save_upload(string $field, string $kind = 'image'): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'File is too large for the server upload limit (' . ini_get('upload_max_filesize') . ').' : 'Upload failed (code ' . $f['error'] . ').');
    }
    $types = $kind === 'video'
        ? ['video/mp4' => 'mp4', 'video/webm' => 'webm']
        : ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $max = $kind === 'video' ? 40 * 1024 * 1024 : 8 * 1024 * 1024;
    if ($f['size'] > $max) throw new RuntimeException('File is larger than ' . ($max / 1048576) . ' MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($types[$mime])) throw new RuntimeException('Unsupported file type (' . $mime . '). Allowed: ' . implode(', ', array_values($types)) . '.');
    if ($kind === 'image' && @getimagesize($f['tmp_name']) === false) throw new RuntimeException('The file is not a valid image.');
    $dir = APP_ROOT . '/assets/uploads';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) throw new RuntimeException('Could not save the uploaded file.');
    return 'assets/uploads/' . $name;
}

/** Delete a previously uploaded local file (never touches remote URLs or files outside uploads). */
function delete_upload(?string $path): void
{
    if ($path && preg_match('#^assets/uploads/[A-Za-z0-9._-]+$#', $path) && is_file(APP_ROOT . '/' . $path)) {
        @unlink(APP_ROOT . '/' . $path);
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function log_activity(string $module, string $action, ?int $userId = null): void
{
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    q('INSERT INTO activity_log (user_id, module, action, ip) VALUES (?,?,?,?)', [$userId, $module, mb_substr($action, 0, 255), client_ip()]);
}

/** Render <option> tags. $rows = [[value, label], ...] or assoc value=>label. */
function options(array $rows, $selected = null): string
{
    $out = '';
    foreach ($rows as $k => $v) {
        [$val, $lab] = is_array($v) ? [$v[0], $v[1]] : [$k, $v];
        $sel = ((string) $val === (string) $selected) ? ' selected' : '';
        $out .= '<option value="' . e($val) . '"' . $sel . '>' . e($lab) . '</option>';
    }
    return $out;
}

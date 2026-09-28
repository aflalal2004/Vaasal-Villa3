<?php
/** Authentication, session timeout, roles & permissions. */

function current_user(): ?array
{
    // Cache per request, keyed by session user id (so it refreshes right after login/logout)
    static $user = false, $cachedFor = null;
    $sid = $_SESSION['user_id'] ?? null;
    if ($user !== false && $cachedFor === $sid) return $user;
    $cachedFor = $sid;
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        // Idle timeout
        if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > SESSION_IDLE_TIMEOUT) {
            logout_user();
            flash('warning', 'Your session expired due to inactivity. Please sign in again.');
            return null;
        }
        $_SESSION['last_seen'] = time();
        $user = q_row('SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1', [$_SESSION['user_id']]);
        if (!$user) {
            logout_user();
            return null;
        }
        $user['permissions'] = array_column(q_all(
            'SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [$user['role_id']]
        ), 'slug');
    }
    return $user;
}

function uid(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function can(string $perm): bool
{
    $u = current_user();
    if (!$u) return false;
    return $u['role_slug'] === 'admin' || in_array($perm, $u['permissions'], true);
}

function can_any(array $perms): bool
{
    foreach ($perms as $p) if (can($p)) return true;
    return false;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            json_out(['ok' => false, 'error' => 'Not signed in'], 401);
        }
        redirect('login.php');
    }
    csrf_check();
    return $u;
}

/** Require one of the given permissions (string or array). */
function require_perm($perms): array
{
    $u = require_login();
    $perms = (array) $perms;
    if (!can_any($perms)) {
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            json_out(['ok' => false, 'error' => 'Permission denied'], 403);
        }
        http_response_code(403);
        $title = 'Access denied';
        require APP_ROOT . '/includes/admin_header.php';
        echo '<div class="card"><h2>Access denied</h2><p>Your role (<b>' . e($u['role_name']) . '</b>) does not have permission to open this page.</p>'
            . '<a class="btn" href="' . e(url(home_path())) . '">Go to my home page</a></div>';
        require APP_ROOT . '/includes/admin_footer.php';
        exit;
    }
    return $u;
}

/** Try to sign in. Returns error message or null on success. */
function attempt_login(string $username, string $password): ?string
{
    $ip = client_ip();
    $fails = (int) q_val(
        'SELECT COUNT(*) FROM login_attempts WHERE (username = ? OR ip = ?) AND success = 0 AND created_at > NOW() - INTERVAL ? MINUTE',
        [$username, $ip, LOGIN_LOCK_MINUTES]
    );
    if ($fails >= LOGIN_MAX_ATTEMPTS) {
        return 'Too many failed attempts. Please wait ' . LOGIN_LOCK_MINUTES . ' minutes and try again.';
    }
    $user = q_row('SELECT id, password_hash, is_active FROM users WHERE username = ?', [$username]);
    $ok = $user && $user['is_active'] && password_verify($password, $user['password_hash']);
    q('INSERT INTO login_attempts (username, ip, success) VALUES (?,?,?)', [$username, $ip, $ok ? 1 : 0]);
    if (!$ok) {
        return 'Invalid username or password.';
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_seen'] = time();
    unset($_SESSION['csrf']);
    q('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
    log_activity('auth', 'Signed in', (int) $user['id']);
    return null;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
}

/** The landing page for the signed-in user, based on permissions. */
function home_path(): string
{
    if (can('dashboard.view')) return 'admin/index.php';
    if (can('pos.use'))        return 'pos/index.php';
    if (can('kitchen.view'))   return 'pos/kitchen.php';
    return 'staff/index.php';
}

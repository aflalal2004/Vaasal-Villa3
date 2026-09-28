<?php
/**
 * Loaded by every entry point. Sets up config, error handling, the
 * database connection, secure session, and shared helpers.
 */

require_once __DIR__ . '/../config/config.php';

date_default_timezone_set(APP_TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

define('APP_ROOT', dirname(__DIR__));

// ---- Base URL (auto-detected relative to the web server document root) ----
if (APP_BASE_URL !== null) {
    define('BASE_URL', rtrim(APP_BASE_URL, '/'));
} else {
    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $base = ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
    define('BASE_URL', rtrim($base, '/'));
}

// ---- Secure session ----
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Basic security headers
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/services.php';

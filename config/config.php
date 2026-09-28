<?php
/**
 * Vaasal Villa Integrated Management System - configuration.
 * Edit the database credentials below if your XAMPP MySQL is not the default.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'vaasal_villa_ims3');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default: empty

define('APP_NAME', 'Vaasal Villa IMS');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Colombo');

// Base URL of the project. Leave null to auto-detect
// (e.g. /vaasal_villa_integrated_management_system3).
define('APP_BASE_URL', null);

define('SESSION_NAME', 'VVIMSSESSID');
define('SESSION_IDLE_TIMEOUT', 3600);   // seconds of inactivity before logout
define('LOGIN_MAX_ATTEMPTS', 5);        // failed attempts allowed ...
define('LOGIN_LOCK_MINUTES', 15);       // ... within this many minutes

define('APP_DEBUG', true);              // set false in production

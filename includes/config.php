<?php

/**
 * Konfigurasi aplikasi Note/Repository
 * Tanpa database - semua data disimpan di file JSON lokal.
 */

// Memulai session dengan batas cookie yang wajar
if (session_status() === PHP_SESSION_NONE) {
    // Batasi lifetime cookie session agar tidak bertumpuk di perangkat
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    session_regenerate_id(true);
}

// Wajib menggunakan absolute path agar aman
if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__)); // root project
}

// Password login tetap
if (!defined('APP_PASSWORD')) {
    define('APP_PASSWORD', 'password123');
}

// Batas maksimal upload file (10 MB)
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
}

// Direktori penyimpanan data
if (!defined('DATA_DIR')) {
    define('DATA_DIR', BASE_DIR . '/data');
}
if (!defined('NOTES_DIR')) {
    define('NOTES_DIR', DATA_DIR . '/notes');
}
if (!defined('UPLOADS_DIR')) {
    define('UPLOADS_DIR', DATA_DIR . '/uploads');
}

// URL dasar untuk keperluan redirect/link
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$baseUrl = $scheme . '://' . $host . $scriptDir;
if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}

// Membuat direktori jika belum ada
foreach ([DATA_DIR, NOTES_DIR, UPLOADS_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

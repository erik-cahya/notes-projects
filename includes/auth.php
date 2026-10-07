<?php
require_once __DIR__ . '/config.php';

/**
 * Memeriksa apakah pengguna sudah login.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Memaksa pengguna login, jika belum redirect ke halaman login.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Melakukan proses login.
 */
function attemptLogin(string $password): bool
{
    return hash_equals(APP_PASSWORD, $password);
}

/**
 * Logout pengguna.
 */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

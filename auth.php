<?php
// auth.php - Helper Otentikasi & Session Loot-E

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/koneksi.php";
require_once __DIR__ . "/config/helpers.php";

/**
 * Cek apakah pengguna sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Ambil data user yang sedang login
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'          => (int)$_SESSION['user_id'],
        'nama'        => $_SESSION['nama'] ?? 'Pengguna',
        'email'       => $_SESSION['email'] ?? '',
        'peran'       => $_SESSION['peran'] ?? 'user',
        'is_verified' => (int)($_SESSION['is_verified'] ?? 0)
    ];
}

/**
 * Cek peran pengguna ('user' atau 'admin')
 */
function hasRole($allowed_roles = []) {
    if (!isLoggedIn()) {
        return false;
    }
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    return in_array($_SESSION['peran'] ?? '', $allowed_roles, true);
}

/**
 * Cek apakah pengguna sudah terverifikasi untuk menjual barang (BR-04)
 */
function isVerifiedSeller() {
    return isLoggedIn() && (int)($_SESSION['is_verified'] ?? 0) === 1;
}

/**
 * Middleware sederhana untuk membatasi akses halaman
 */
function requireAuth($allowed_roles = []) {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }

    if (!empty($allowed_roles) && !hasRole($allowed_roles)) {
        http_response_code(403);
        die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
    }
}

// Logout jika diakses dengan query ?action=logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    header("Location: landingpage.php");
    exit;
}

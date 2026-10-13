<?php
// auth.php - Helper Autentikasi dan Manajemen Sesi Loot-E

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Memeriksa apakah pengguna sudah terautentikasi (via Sesi atau Cookie Remember Me)
 * @return bool
 */
function isLoggedIn() {
    global $pdo;

    // 1. Cek dari Sesi Aktif
    if (isset($_SESSION['user_id'])) {
        return true;
    }

    // 2. Cek dari Cookie "Remember Me"
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user) {
                // Auto-login via cookie token
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['email'] = $user['email'];
                return true;
            } else {
                // Hapus cookie jika token tidak ditemukan di database
                setcookie('remember_token', '', time() - 3600, "/");
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    return false;
}

/**
 * Memproteksi halaman yang membutuhkan login (misal: dashboard.php)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Mengalihkan pengguna yang sudah login agar tidak bisa mengakses login/register lagi
 * @param string $target
 */
function redirectIfLoggedIn($target = 'dashboard.php') {
    if (isLoggedIn()) {
        header("Location: $target");
        exit();
    }
}

/**
 * Mendapatkan data detail pengguna yang sedang login
 * @return array|null
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, nama_lengkap, email, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Memproses Logout dan membersihkan Sesi & Cookie
 */
function logoutUser() {
    global $pdo;

    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Abaikan error DB saat logout
        }
    }

    // Hapus Cookie Remember Me
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, "/");
    }

    // Bersihkan Sesi
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}
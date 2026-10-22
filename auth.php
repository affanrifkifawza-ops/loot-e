<?php
// auth.php - Helper Otentikasi & Koneksi Database PDO

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/koneksi.php";

/**
 * Fungsi Generator UUID v4 untuk kolom id CHAR(36)
 */
function generateUuidV4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

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
        'id'    => $_SESSION['user_id'] ?? '',
        'nama'  => $_SESSION['nama'] ?? 'Pengguna',
        'email' => $_SESSION['email'] ?? '',
        'peran' => $_SESSION['peran'] ?? 'buyer'
    ];
}

/**
 * Cek peran pengguna (buyer, seller, admin)
 */
function hasRole($allowed_roles = []) {
    if (!isLoggedIn()) {
        return false;
    }
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    return in_array($_SESSION['peran'] ?? '', $allowed_roles);
}

/**
 * Middleware untuk membatasi akses endpoint
 */
function requireAuth($allowed_roles = []) {
    if (!isLoggedIn()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            http_response_code(401);
            header("Content-Type: application/json");
            echo json_encode(["status" => "error", "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
        } else {
            header("Location: login.php");
        }
        exit;
    }
    
    if (!empty($allowed_roles) && !hasRole($allowed_roles)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header("Content-Type: application/json");
            echo json_encode(["status" => "error", "message" => "Akses ditolak. Anda tidak memiliki hak akses yang sesuai."]);
        } else {
            echo "Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.";
        }
        exit;
    }
}

// Logout jika diakses dengan query ?action=logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: landing.php");
    exit;
}
?>
<?php
// auth.php - Helper Autentikasi dan Manajemen Sesi Loot-E (Database: db_loote, Tabel: pengguna)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Kunci rahasia untuk tanda tangan cookie Remember Me
define('LOOTE_SECRET_KEY', 'loote_secure_salt_key_2026_jwt_token_auth');

/**
 * Menghasilkan UUID v4 untuk primary key (id char(36) pada tabel pengguna)
 * @return string
 */
function generateUuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100 (v4)
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Memeriksa apakah pengguna sudah terautentikasi (via Sesi atau Cookie Terverifikasi)
 * @return bool
 */
function isLoggedIn() {
    global $pdo;

    // 1. Cek dari Sesi Aktif
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    // 2. Cek dari Cookie "loote_remember"
    if (!empty($_COOKIE['loote_remember'])) {
        $cookieVal = $_COOKIE['loote_remember'];
        $parts = explode(':', $cookieVal, 2);
        if (count($parts) === 2) {
            $userId = $parts[0];
            $tokenHash = $parts[1];

            // Validasi HMAC signature
            $expectedHash = hash_hmac('sha256', $userId, LOOTE_SECRET_KEY);
            if (hash_equals($expectedHash, $tokenHash)) {
                try {
                    $stmt = $pdo->prepare("SELECT id, nama, email, peran FROM pengguna WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();

                    if ($user) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['nama'] = $user['nama'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['peran'] = $user['peran'] ?? 'buyer';
                        return true;
                    }
                } catch (PDOException $e) {
                    return false;
                }
            }
        }
        // Hapus cookie tidak valid
        setcookie('loote_remember', '', time() - 3600, "/");
    }

    return false;
}

/**
 * Memproteksi endpoint/halaman yang membutuhkan login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized. Sesi login diperlukan.',
            'redirect' => 'login.html'
        ]);
        exit();
    }
}

/**
 * Mengalihkan pengguna yang sudah login
 */
function redirectIfLoggedIn($target = 'dashboard.html') {
    if (isLoggedIn()) {
        header("Location: $target");
        exit();
    }
}

/**
 * Mendapatkan data detail pengguna yang sedang login dari tabel pengguna
 * @return array|null
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) {
        return null;
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, nama, email, peran FROM pengguna WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            // Berikan alias 'nama_lengkap' untuk kompatibilitas frontend
            $user['nama_lengkap'] = $user['nama'];
            return $user;
        }
    } catch (PDOException $e) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'nama' => $_SESSION['nama'] ?? 'Pengguna',
        'nama_lengkap' => $_SESSION['nama'] ?? 'Pengguna',
        'email' => $_SESSION['email'] ?? '',
        'peran' => $_SESSION['peran'] ?? 'buyer'
    ];
}

/**
 * Memproses Logout dan membersihkan Sesi & Cookie
 */
function logoutUser() {
    // Hapus Cookie Remember Me
    if (isset($_COOKIE['loote_remember'])) {
        setcookie('loote_remember', '', time() - 3600, "/");
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

// Jika file auth.php dipanggil langsung sebagai endpoint verifikasi status login
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'auth.php') {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header("Access-Control-Allow-Credentials: true");
    }
    header('Content-Type: application/json; charset=utf-8');
    $logged = isLoggedIn();
    $user = $logged ? getCurrentUser() : null;
    echo json_encode([
        'success' => true,
        'logged_in' => $logged,
        'user' => $user
    ]);
    exit();
}
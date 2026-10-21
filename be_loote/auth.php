<?php
// auth.php - Helper Otentikasi & Koneksi Database PDO

session_start();

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_loote');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Fungsi Koneksi Database PDO
 */
function getDbConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $e->getMessage()]);
        exit;
    }
}

/**
 * Fungsi Generator UUID v4 untuk kolom id CHAR(36)
 */
function generateUuidV4() {
    $data = random_bytes(16);
    $data[2] = chr(ord($data[2]) & 0x0f | 0x40);
    $data = chr(ord($data) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Cek apakah pengguna sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
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
    return in_array($_SESSION['peran'], $allowed_roles);
}

/**
 * Middleware untuk membatasi akses endpoint
 */
function requireAuth($allowed_roles = []) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Akses ditolak. Silakan login terlebih dahulu."]);
        exit;
    }
    
    if (!empty($allowed_roles) && !hasRole($allowed_roles)) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Akses ditolak. Anda tidak memiliki hak akses yang sesuai."]);
        exit;
    }
}

// Fitur Logout jika diakses dengan query ?action=logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Content-Type: application/json");
    echo json_encode(["status" => "success", "message" => "Berhasil logout."]);
    exit;
}
?>
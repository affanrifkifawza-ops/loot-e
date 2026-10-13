<?php
// dashboard.php - Backend API Dashboard & Data Pengguna (Database: db_loote, Tabel: pengguna)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORS Headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sesi tidak aktif atau belum login.',
        'redirect' => 'login.html'
    ]);
    exit();
}

$user = getCurrentUser();

echo json_encode([
    'success' => true,
    'message' => 'Data sesi pengguna berhasil dimuat.',
    'user' => [
        'id' => $user['id'] ?? $_SESSION['user_id'],
        'nama' => $user['nama'] ?? $_SESSION['nama'] ?? 'Pengguna',
        'nama_lengkap' => $user['nama_lengkap'] ?? $user['nama'] ?? $_SESSION['nama'] ?? 'Pengguna',
        'email' => $user['email'] ?? $_SESSION['email'] ?? '',
        'peran' => $user['peran'] ?? $_SESSION['peran'] ?? 'buyer'
    ]
]);

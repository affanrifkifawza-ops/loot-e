<?php
// login.php - Endpoint Login Pengguna

header("Content-Type: application/json");
require_once "auth.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Metode request tidak diizinkan. Gunakan POST."]);
    exit;
}

// Ambil input dari JSON payload atau Form Data
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

if (!$input) {
    $input = $_POST;
}

$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $input['password'] ?? '';

if (!$email || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email valid dan password wajib diisi."]);
    exit;
}

$db = getDbConnection();

try {
    // Cari pengguna berdasarkan email di tabel pengguna
    $stmt = $db->prepare("SELECT id, nama, email, password, peran FROM pengguna WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Email atau password salah."]);
        exit;
    }

    // Set data sesi pengguna
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nama'] = $user['nama'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['peran'] = $user['peran'];

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Login berhasil.",
        "data" => [
            "id" => $user['id'],
            "nama" => $user['nama'],
            "email" => $user['email'],
            "peran" => $user['peran']
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Terjadi kesalahan sistem: " . $e->getMessage()]);
}
?>
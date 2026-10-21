<?php
// register.php - Endpoint Registrasi Pengguna Baru

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

$nama = trim($input['nama'] ?? '');
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $input['password'] ?? '';
$peran = trim($input['peran'] ?? 'buyer'); // Default peran: buyer

// Validasi kelengkapan input
if (empty($nama) || !$email || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Nama, email valid, dan password wajib diisi."]);
    exit;
}

// Validasi peran sesuai schema database ('buyer', 'seller', 'admin')
$validPeran = ['buyer', 'seller', 'admin'];
if (!in_array($peran, $validPeran)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Peran tidak valid. Pilihan: buyer, seller, admin."]);
    exit;
}

$db = getDbConnection();

try {
    // Cek apakah email sudah terdaftar
    $stmtCheck = $db->prepare("SELECT id FROM pengguna WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Email sudah terdaftar."]);
        exit;
    }

    // Encrypt password dengan BCRYPT
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Generate UUID CHAR(36)
    $id = generateUuidV4();

    // Insert data ke tabel pengguna
    $stmtInsert = $db->prepare("INSERT INTO pengguna (id, nama, email, password, peran) VALUES (?, ?, ?, ?, ?)");
    $stmtInsert->execute([$id, $nama, $email, $hashedPassword, $peran]);

    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Registrasi akun berhasil.",
        "data" => [
            "id" => $id,
            "nama" => $nama,
            "email" => $email,
            "peran" => $peran
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal mendaftarkan pengguna: " . $e->getMessage()]);
}
?>
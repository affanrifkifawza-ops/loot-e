<?php
// register.php - Backend API Register (Database: db_loote, Tabel: pengguna)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORS Headers untuk fleksibilitas request frontend
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

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Mendukung request JSON payload (fetch) dan FormData / x-www-form-urlencoded
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $input = !empty($raw) ? (json_decode($raw, true) ?? []) : $_POST;
    } else {
        $input = $_POST;
    }

    $nama = trim($input['nama'] ?? $input['nama_lengkap'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';
    $terms = isset($input['terms']) && ($input['terms'] === '1' || $input['terms'] === 'true' || $input['terms'] === 'on' || $input['terms'] === true);
    $peran = trim($input['peran'] ?? 'buyer');

    // Validasi data
    if (empty($nama)) {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

    if (empty($email)) {
        $errors[] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format alamat email tidak valid.';
    } else {
        try {
            // Cek duplikasi email di tabel pengguna (db_loote)
            $stmt = $pdo->prepare("SELECT id FROM pengguna WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Alamat email sudah terdaftar. Silakan gunakan email lain atau masuk.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Terjadi kesalahan sistem database: ' . $e->getMessage();
        }
    }

    if (empty($password)) {
        $errors[] = 'Kata sandi wajib diisi.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Kata sandi minimal 8 karakter.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Konfirmasi kata sandi tidak cocok.';
    }

    if (!$terms) {
        $errors[] = 'Anda harus menyetujui Syarat dan Ketentuan.';
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $newId = generateUuid(); // char(36) UUID

        // Pastikan peran valid sesuai komentar skema db_loote: 'buyer', 'seller', 'admin'
        if (!in_array($peran, ['buyer', 'seller', 'admin'])) {
            $peran = 'buyer';
        }

        try {
            // Insert ke tabel pengguna sesuai skema db_loote.sql
            $stmt = $pdo->prepare("INSERT INTO pengguna (id, nama, email, password, peran) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$newId, $nama, $email, $hashed_password, $peran]);

            $_SESSION['user_id'] = $newId;
            $_SESSION['nama'] = $nama;
            $_SESSION['nama_lengkap'] = $nama;
            $_SESSION['email'] = $email;
            $_SESSION['peran'] = $peran;
            $_SESSION['registration_success'] = true;

            echo json_encode([
                'success' => true,
                'message' => 'Pendaftaran akun berhasil!',
                'redirect' => 'success.html',
                'user' => [
                    'id' => $newId,
                    'nama' => $nama,
                    'email' => $email,
                    'peran' => $peran
                ]
            ]);
            exit();
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan data pengguna: ' . $e->getMessage();
        }
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => !empty($errors) ? $errors[0] : 'Validasi gagal.',
        'errors' => $errors
    ]);
    exit();
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Metode request tidak diizinkan. Gunakan POST.'
]);
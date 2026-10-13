<?php
// login.php - Backend API Login (Database: db_loote, Tabel: pengguna)
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

$error = '';
$email_input = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Mendukung request JSON payload (fetch) dan FormData / x-www-form-urlencoded
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $jsonData = !empty($raw) ? (json_decode($raw, true) ?? []) : $_POST;
        $email_input = trim($jsonData['email'] ?? '');
        $password = $jsonData['password'] ?? '';
        $remember = !empty($jsonData['remember']);
    } else {
        $email_input = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && ($_POST['remember'] === '1' || $_POST['remember'] === 'true' || $_POST['remember'] === 'on');
    }

    if (empty($email_input) || empty($password)) {
        $error = 'Alamat email dan kata sandi wajib diisi.';
    } else {
        try {
            // Ambil data akun dari tabel pengguna (db_loote)
            $stmt = $pdo->prepare("SELECT id, nama, email, password, peran FROM pengguna WHERE email = ?");
            $stmt->execute([$email_input]);
            $user = $stmt->fetch();

            $passwordValid = false;
            if ($user && !empty($user['password'])) {
                if (password_verify($password, $user['password'])) {
                    $passwordValid = true;
                } elseif ($password === $user['password']) {
                    // Fallback jika data terisi password teks biasa saat import, otomatis amankan dengan hash BCRYPT
                    $passwordValid = true;
                    $rehash = password_hash($password, PASSWORD_BCRYPT);
                    $updateStmt = $pdo->prepare("UPDATE pengguna SET password = ? WHERE id = ?");
                    $updateStmt->execute([$rehash, $user['id']]);
                }
            }

            if ($user && $passwordValid) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['nama_lengkap'] = $user['nama'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['peran'] = $user['peran'] ?? 'buyer';

                // Jika pengguna memilih Ingat Saya, pasang cookie terenkripsi HMAC
                if ($remember) {
                    $cookieVal = $user['id'] . ':' . hash_hmac('sha256', $user['id'], LOOTE_SECRET_KEY);
                    setcookie('loote_remember', $cookieVal, time() + (86400 * 30), "/", "", false, true);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Login berhasil!',
                    'redirect' => 'dashboard.html',
                    'user' => [
                        'id' => $user['id'],
                        'nama' => $user['nama'],
                        'nama_lengkap' => $user['nama'],
                        'email' => $user['email'],
                        'peran' => $user['peran'] ?? 'buyer'
                    ]
                ]);
                exit();
            } else {
                $error = 'Alamat email atau kata sandi salah.';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem database: ' . $e->getMessage();
        }
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $error
    ]);
    exit();
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Metode request tidak diizinkan. Gunakan POST.'
]);
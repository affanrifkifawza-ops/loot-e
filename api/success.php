<?php
// success.php - Backend Status Check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Credentials: true");
}

if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
    header("Location: ../success.html");
    exit();
}

header('Content-Type: application/json; charset=utf-8');
$registered = !empty($_SESSION['registration_success']);

echo json_encode([
    'success' => $registered,
    'message' => $registered ? 'Registrasi berhasil dikonfirmasi.' : 'Tidak ada sesi registrasi baru.'
]);
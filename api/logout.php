<?php
// logout.php - Backend API Logout
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

require_once __DIR__ . '/auth.php';

logoutUser();

// Jika diakses langsung lewat link browser <a href="api/logout.php?redirect=true">
if (isset($_GET['redirect']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false && !isset($_SERVER['HTTP_X_REQUESTED_WITH']))) {
    header("Location: ../login.html");
    exit();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'message' => 'Berhasil logout.',
    'redirect' => 'login.html'
]);

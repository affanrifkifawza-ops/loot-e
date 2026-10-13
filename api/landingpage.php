<?php
// landingpage.php - Backend API / Redirect
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Credentials: true");
}

require_once __DIR__ . '/auth.php';

if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
    header("Location: ../landingpage.html");
    exit();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'message' => 'Loot-E Platform API',
    'is_logged_in' => isLoggedIn(),
    'current_user' => getCurrentUser()
]);
<?php
// dashboard.php - Halaman Utama Setelah Login
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
</head>
<body>
    <h2>Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!</h2>
    <p>Alamat Email: <?= htmlspecialchars($_SESSION['email']) ?></p>
    <a href="logout.php">Keluar (Logout)</a>
</body>
</html>

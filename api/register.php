<?php
// register.php
session_start();
require_once 'db.php';

$errors = [];
$nama_lengkap = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']);

    if (empty($nama_lengkap)) {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

    if (empty($email)) {
        $errors[] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format alamat email tidak valid.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Alamat email sudah terdaftar. Silakan gunakan email lain atau masuk.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
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

        try {
            $stmt = $pdo->prepare("INSERT INTO users (nama_lengkap, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$nama_lengkap, $email, $hashed_password]);

            $_SESSION['registration_success'] = true;
            header("Location: success.php");
            exit();
        } catch (PDOException $e) {
            $errors[] = 'Gagal mendaftar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Loot - E</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-layout">
        <!-- Bagian Kiri: Formulir Pendaftaran -->
        <div class="form-container">
            <!-- Logo & Brand (Logo Bulat) -->
            <div class="brand-logo">
                <img src="assets/logo loote.jpeg" alt="Logo Loot - E" class="logo-image">
                <span class="brand-name">Loot - E</span>
            </div>

            <!-- Konten Utama Pendaftaran -->
            <div class="login-content">
                <h1 class="main-title">Daftar Akun</h1>
                <p class="subtitle">Buat akun baru untuk mulai menggunakan layanan Loot-E.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert-error">
                        <ul style="margin-left: 1.25rem; list-style-type: disc;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" class="login-form">
                    <!-- Input Nama Lengkap -->
                    <div class="form-group">
                        <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($nama_lengkap) ?>" placeholder="Masukkan nama lengkap Anda" required class="form-input">
                    </div>

                    <!-- Input Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">Alamat Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="nama@contoh.com" required class="form-input">
                    </div>

                    <!-- Input Kata Sandi -->
                    <div class="form-group">
                        <label for="password" class="form-label">Kata Sandi</label>
                        <input type="password" id="password" name="password" placeholder="Minimal 8 karakter" required class="form-input">
                    </div>

                    <!-- Input Konfirmasi Kata Sandi -->
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Konfirmasi Kata Sandi</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi kata sandi Anda" required class="form-input">
                    </div>

                    <!-- Syarat & Ketentuan Checkbox -->
                    <div class="terms-row" style="margin-bottom: 1.5rem;">
                        <label for="terms" class="remember-label" style="justify-content: flex-start;">
                            <input type="checkbox" id="terms" name="terms" class="checkbox-input" required>
                            <span>Saya menyetujui <a href="#" class="signup-link" style="margin-left:0;">Syarat & Ketentuan</a></span>
                        </label>
                    </div>

                    <!-- Tombol Daftar -->
                    <button type="submit" class="btn-submit">Daftar Akun</button>
                </form>

                <!-- Tautan Login -->
                <p class="signup-prompt">
                    Sudah punya akun? <a href="login.php" class="signup-link">Masuk</a>
                </p>
            </div>

            <!-- Footer Halaman -->
            <div class="page-footer">
                <span class="copyright">&copy; 2026 Loot-E</span>
                <div class="footer-links">
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                </div>
            </div>
        </div>

        <!-- Bagian Kanan: Panel Visual -->
        <div class="visual-container">
            <div class="carousel-dots">
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        </div>
    </div>
</body>
</html>
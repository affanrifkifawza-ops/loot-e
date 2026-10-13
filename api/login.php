<?php
// login.php
session_start();
require_once 'db.php';

$error = '';
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_input = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email_input) || empty($password)) {
        $error = 'Alamat email dan kata sandi wajib diisi.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email_input]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['email'] = $user['email'];

                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
                    
                    $update_stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $update_stmt->execute([$token, $user['id']]);
                }

                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Alamat email atau kata sandi salah.';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Loot - E</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-layout">
        <!-- Bagian Kiri: Formulir Login -->
        <div class="form-container">
            <!-- Logo & Brand (Membaca logo dari folder assets) -->
            <div class="brand-logo">
                <img src="assets/logo loote.jpeg" alt="Logo Loot - E" class="logo-image">
                <span class="brand-name">Loot - E</span>
            </div>

            <!-- Konten Utama Login -->
            <div class="login-content">
                <h1 class="main-title">Selamat Datang Kembali</h1>
                <p class="subtitle">Masuk dengan email dan kata sandi Anda untuk melanjutkan.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="login-form">
                    <!-- Input Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">Alamat Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email_input) ?>" placeholder="nama@contoh.com" required class="form-input">
                    </div>

                    <!-- Input Kata Sandi -->
                    <div class="form-group">
                        <div class="label-row">
                            <label for="password" class="form-label">Kata Sandi</label>
                            <a href="#" class="forgot-link">Lupa Kata Sandi?</a>
                        </div>
                        <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required class="form-input">
                    </div>

                    <!-- Opsi Ingat Saya -->
                    <div class="remember-row">
                        <label for="remember" class="remember-label">
                            <span>Ingat saya</span>
                            <input type="checkbox" id="remember" name="remember" class="checkbox-input">
                        </label>
                    </div>

                    <!-- Tombol Masuk -->
                    <button type="submit" class="btn-submit">Masuk</button>
                </form>

                <!-- Tautan Pendaftaran -->
                <p class="signup-prompt">
                    Belum punya akun? <a href="register.php" class="signup-link">Daftar</a>
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
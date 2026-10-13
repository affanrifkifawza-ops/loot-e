<?php
// landingpage.php - Halaman Utama Platform Loot-E
require_once 'auth.php';

$is_logged_in = isLoggedIn();
$current_user = $is_logged_in ? getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loot - E | Platform Solusi Keberlanjutan & Ekonomi Sirkular</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navbar Navigation -->
    <header class="navbar">
        <div class="nav-container">
            <a href="landingpage.php" class="brand-logo">
                <img src="assets/logo loote.jpeg" alt="Logo Loot-E" class="logo-image">
                <span class="brand-name">Loot - E</span>
            </a>

            <nav class="nav-menu">
                <a href="#beranda" class="nav-link active">Beranda</a>
                <a href="#fitur" class="nav-link">Fitur Utama</a>
                <a href="#cara-kerja" class="nav-link">Cara Kerja</a>
                <a href="#tentang" class="nav-link">Tentang Kami</a>
            </nav>

            <div class="nav-actions">
                <?php if ($is_logged_in): ?>
                    <span class="user-greeting">Halo, <strong><?= htmlspecialchars($current_user['nama_lengkap'] ?? 'Pengguna') ?></strong></span>
                    <a href="dashboard.php" class="btn-primary">Dashboard</a>
                    <a href="logout.php" class="btn-outline">Keluar</a>
                <?php else: ?>
                    <a href="login.php" class="btn-outline">Masuk</a>
                    <a href="register.php" class="btn-primary">Daftar Akun</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="beranda" class="hero-section">
        <div class="hero-container">
            <div class="hero-text">
                <span class="badge-tag">🌱 Inovasi Hijau Masa Depan</span>
                <h1 class="hero-title">Ubah Sampah Jadi Berkah Bersama <span class="highlight">Loot - E</span></h1>
                <p class="hero-subtitle">
                    Platform digital terintegrasi untuk pengelolaan limbah, tukar barang bekas berharga, dan dapatkan poin imbalan secara bijak demi keberlanjutan bumi kita.
                </p>
                <div class="hero-buttons">
                    <?php if ($is_logged_in): ?>
                        <a href="dashboard.php" class="btn-hero-primary">Buka Dashboard Anda &rarr;</a>
                    <?php else: ?>
                        <a href="register.php" class="btn-hero-primary">Mulai Sekarang — Gratis</a>
                        <a href="login.php" class="btn-hero-secondary">Masuk ke Akun</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hero-visual">
                <div class="visual-card">
                    <img src="assets/logo loote.jpeg" alt="Loot-E Logo Graphic" class="hero-logo-large">
                    <div class="stats-badge">
                        <span class="stats-number">100%</span>
                        <span class="stats-label">Komitmen Daur Ulang & Eco-Friendly</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="fitur" class="features-section">
        <div class="section-header">
            <h2 class="section-title">Fitur Unggulan Loot - E</h2>
            <p class="section-subtitle">Solusi cerdas yang dirancang untuk memudahkan aksi ramah lingkungan Anda sehari-hari.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <h3 class="feature-title">Penukaran Limbah & Barang Bekas</h3>
                <p class="feature-desc">Tukarkan sampah terpilah atau barang tak terpakai Anda untuk dikelola dan didaur ulang secara profesional.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="feature-title">Loot-E Reward Points</h3>
                <p class="feature-desc">Dapatkan poin harian dan imbalan langsung dari setiap transaksi daur ulang yang berhasil diproses.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon navy">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 19v-6a2 2 0 012-2h2a2 2 0 012 2v6a2 2 0 01-2 2h-2a2 2 0 01-2-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="feature-title">Laporan Dampak Lingkungan</h3>
                <p class="feature-desc">Pantau statistik pribadi jumlah karbon yang berhasil Anda kurangi dari aktivitas ramah lingkungan Anda.</p>
            </div>
        </div>
    </section>

    <!-- How it Works Section -->
    <section id="cara-kerja" class="steps-section">
        <div class="section-header">
            <h2 class="section-title">Langkah Mudah Memulai</h2>
            <p class="section-subtitle">Hanya butuh 3 langkah sederhana untuk menjadi bagian dari gerakan lingkungan.</p>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">01</div>
                <h3 class="step-title">Daftar Akun Gratis</h3>
                <p class="step-desc">Buat akun Loot-E Anda dalam hitungan detik menggunakan alamat email aktif.</p>
            </div>

            <div class="step-card">
                <div class="step-number">02</div>
                <h3 class="step-title">Setor & Kumpulkan</h3>
                <p class="step-desc">Pilah barang bekas atau daur ulang Anda dan serahkan pada titik layanan terdekat.</p>
            </div>

            <div class="step-card">
                <div class="step-number">03</div>
                <h3 class="step-title">Nikmati Imbalan</h3>
                <p class="step-desc">Poin yang Anda kumpulkan dapat ditukarkan dengan berbagai voucher menarik.</p>
            </div>
        </div>
    </section>

    <!-- CTA Banner Section -->
    <section class="cta-section">
        <div class="cta-container">
            <h2 class="cta-title">Siap Menjadi Pahlawan Lingkungan?</h2>
            <p class="cta-subtitle">Bergabunglah bersama ribuan pengguna lainnya dan mulailah langkah kecil Anda hari ini.</p>
            <?php if (!$is_logged_in): ?>
                <a href="register.php" class="btn-cta">Daftar Sekarang — Gratis</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn-cta">Masuk ke Dashboard</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer id="tentang" class="footer">
        <div class="footer-container">
            <div class="footer-brand">
                <div class="brand-logo">
                    <img src="assets/logo loote.jpeg" alt="Logo Loot-E" class="logo-image">
                    <span class="brand-name">Loot - E</span>
                </div>
                <p class="footer-desc">Platform solusi keberlanjutan dan ekonomi sirkular ramah lingkungan bagi masa depan yang lebih bersih.</p>
            </div>

            <div class="footer-col">
                <h4 class="footer-heading">Navigasi</h4>
                <a href="#beranda">Beranda</a>
                <a href="#fitur">Fitur</a>
                <a href="#cara-kerja">Cara Kerja</a>
            </div>

            <div class="footer-col">
                <h4 class="footer-heading">Akun</h4>
                <a href="login.php">Masuk</a>
                <a href="register.php">Daftar Akun</a>
                <a href="dashboard.php">Dashboard</a>
            </div>

            <div class="footer-col">
                <h4 class="footer-heading">Legal</h4>
                <a href="#">Kebijakan Privasi</a>
                <a href="#">Syarat & Ketentuan</a>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2026 Loot-E. Seluruh Hak Cipta Dilindungi.</p>
        </div>
    </footer>
</body>
</html>
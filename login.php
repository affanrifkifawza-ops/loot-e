<?php
// login.php - Login Pengguna Loot-E
require_once "auth.php";

// Jika sudah login, langsung arahkan ke beranda
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';

// Proses saat form disubmit via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || empty($password)) {
        $error = "Email valid dan kata sandi wajib diisi.";
    } else {
        try {
            $db   = getDbConnection();
            $stmt = $db->prepare("SELECT id, nama, email, password, peran FROM pengguna WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $error = "Email atau kata sandi salah. Silakan coba lagi.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama']    = $user['nama'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['peran']   = $user['peran'];

                header("Location: index.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Terjadi gangguan sistem koneksi. Silakan coba lagi.";
        }
    }
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Loot-E</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ["Inter", "sans-serif"] },
          colors: {
            brand: {
              yellow: "#F5B700",
              dark: "#303135",
              footer: "#383838",
              bg: "#F8F9FA",
              accent: "#16A34A",
            },
          },
        },
      },
    };
  </script>
  <link rel="stylesheet" href="style.css" />
</head>

<body class="bg-[#F8F9FA] font-sans antialiased text-gray-900 min-h-screen">
  <div class="min-h-screen flex flex-col lg:flex-row">
    <!-- Bagian Kiri: Formulir Login -->
    <div class="w-full lg:w-1/2 flex flex-col justify-between p-6 sm:p-10 md:p-14 lg:p-16 bg-white shadow-sm z-10">
      <!-- Header Brand -->
      <div>
        <a href="index.php" class="inline-flex items-center gap-3 group">
          <img src="assets/logo.png" alt="Logo Loot - E"
            class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm transition group-hover:scale-105" />
          <span class="font-bold text-xl tracking-tight text-gray-900">Loot - <span class="text-brand-yellow">E</span></span>
        </a>
      </div>

      <!-- Konten Utama Login -->
      <div class="w-full max-w-md mx-auto my-8">
        <div class="mb-8">
          <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">
            Selamat Datang Kembali
          </h1>
          <p class="text-gray-500 text-sm sm:text-base mt-2">
            Masuk dengan email dan kata sandi Anda untuk melanjutkan.
          </p>
        </div>

        <!-- Alert Error -->
        <?php if (!empty($error)): ?>
          <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-3">
            <i class="ph ph-warning-circle text-xl flex-shrink-0 text-red-600 mt-0.5"></i>
            <div class="flex-1 font-medium"><?= htmlspecialchars($error) ?></div>
          </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-5">
          <!-- Input Email -->
          <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">Alamat Email</label>
            <div class="relative">
              <i class="ph ph-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
              <input type="email" id="email" name="email" placeholder="nama@contoh.com" required
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-300 bg-white text-gray-900 text-sm placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition shadow-sm" />
            </div>
          </div>

          <!-- Input Kata Sandi -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label for="password" class="block text-sm font-semibold text-gray-700">Kata Sandi</label>
              <a href="#" class="text-xs font-semibold text-brand-yellow hover:text-yellow-600 transition">Lupa Kata Sandi?</a>
            </div>
            <div class="relative">
              <i class="ph ph-lock-key absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
              <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required
                class="w-full pl-11 pr-11 py-3 rounded-xl border border-gray-300 bg-white text-gray-900 text-sm placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition shadow-sm" />
              <button type="button" onclick="toggleVis('password', this)"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition cursor-pointer">
                <i class="ph ph-eye text-lg"></i>
              </button>
            </div>
          </div>

          <!-- Opsi Ingat Saya -->
          <div>
            <label class="flex items-center gap-2.5 cursor-pointer text-sm text-gray-600 select-none">
              <input type="checkbox" name="remember"
                class="w-4 h-4 rounded border-gray-300 text-brand-yellow focus:ring-brand-yellow/40" />
              <span>Ingat saya</span>
            </label>
          </div>

          <!-- Tombol Masuk -->
          <button type="submit"
            class="w-full py-3.5 px-4 rounded-xl font-bold text-white bg-brand-yellow hover:bg-yellow-500 active:scale-[0.99] transition shadow-md hover:shadow-lg flex items-center justify-center gap-2 cursor-pointer">
            Masuk
            <i class="ph ph-arrow-right font-bold text-lg"></i>
          </button>
        </form>

        <!-- Tautan Pendaftaran -->
        <p class="text-center text-sm text-gray-500 mt-8">
          Belum punya akun?
          <a href="register.php" class="font-bold text-brand-yellow hover:text-yellow-600 hover:underline transition ml-1">Daftar Akun Baru</a>
        </p>
      </div>

      <!-- Footer Halaman -->
      <div class="flex flex-col sm:flex-row items-center justify-between gap-2 pt-6 border-t border-gray-100 text-xs text-gray-400">
        <span>&copy; <?= date('Y') ?> Loot-E. All rights reserved.</span>
        <div class="flex gap-4">
          <a href="index.php" class="hover:text-gray-600 transition">Beranda</a>
        </div>
      </div>
    </div>

    <!-- Bagian Kanan: Panel Visual -->
    <div class="hidden lg:flex lg:w-1/2 relative items-center justify-center p-12 overflow-hidden bg-brand-dark text-white">
      <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-brand-yellow/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="max-w-md text-center relative z-10 flex flex-col items-center">
        <img src="assets/image.png" alt="Loot-E Preview" class="w-80 h-auto mb-8 drop-shadow-2xl" />
        <h2 class="text-2xl font-bold text-white mb-2">Find Better, Trade Smarter</h2>
        <p class="text-gray-400 text-sm">Akses jutaan produk elektronik terverifikasi dan lakukan transaksi serta barter dengan aman.</p>
      </div>
    </div>
  </div>

  <script>
    function toggleVis(fieldId, btn) {
      var inp = document.getElementById(fieldId);
      var isPassword = inp.type === 'password';
      inp.type = isPassword ? 'text' : 'password';
      btn.innerHTML = isPassword
        ? '<i class="ph ph-eye-slash text-lg"></i>'
        : '<i class="ph ph-eye text-lg"></i>';
    }
  </script>
</body>

</html>
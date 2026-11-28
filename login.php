<?php
// login.php - Login Pengguna Loot-E
require_once "auth.php";

$redirect = $_GET["redirect"] ?? $_POST["redirect"] ?? "index.php";
$allowedRedirects = ["index.php", "categories.php", "search.php", "detail_produk.php", "cart.php", "notifikasi.php", "profile.php", "pesanan.php", "dashboard_seller.php", "katalog.php", "upload_produk.php", "verifikasi_akun.php", "alamat_user.php"];
$redirectBase = parse_url($redirect, PHP_URL_PATH);
if (!in_array($redirectBase, $allowedRedirects, true)) {
  $redirect = "index.php";
}

// Jika sudah login, langsung arahkan ke tujuan
if (isLoggedIn()) {
  header("Location: " . $redirect);
  exit;
}

$error = "";
$info = "";
$fieldErrors = [];
if ($redirect !== "index.php" && $_SERVER["REQUEST_METHOD"] !== "POST") {
  $info = "Silakan login terlebih dahulu untuk melanjutkan.";
}

// Proses saat form disubmit via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rawEmail = trim($_POST['email'] ?? '');
  $email    = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
  $password = $_POST['password'] ?? '';

  if (empty($rawEmail)) {
    $fieldErrors['email'] = "Alamat email wajib diisi.";
  } elseif (!$email) {
    $fieldErrors['email'] = "Format email tidak valid (contoh: nama@contoh.com).";
  }

  if (empty($password)) {
    $fieldErrors['password'] = "Kata sandi wajib diisi.";
  }

  if (empty($fieldErrors)) {
    try {
      $db   = getDbConnection();
      $stmt = $db->prepare("SELECT id, nama, email, password, peran, is_verified FROM pengguna WHERE email = ?");
      $stmt->execute([$email]);
      $user = $stmt->fetch();

      if (!$user || !password_verify($password, $user['password'])) {
        $error = "Email atau kata sandi salah. Silakan periksa kembali.";
        $fieldErrors['password'] = "Email atau kata sandi tidak cocok.";
      } else {
        session_regenerate_id(true);
        $_SESSION['user_id']     = (int)$user['id'];
        $_SESSION['nama']        = $user['nama'];
        $_SESSION['email']       = $user['email'];
        $_SESSION['peran']       = $user['peran'];
        $_SESSION['is_verified'] = (int)$user['is_verified'];

        // shortcut: fallback ke $redirect sampai admin/index.php dibuat
        if ($user['peran'] === 'admin' && file_exists(__DIR__ . '/admin/index.php')) {
          header("Location: admin/index.php");
        } else {
          header("Location: " . $redirect);
        }
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
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif']
          },
          colors: {
            brand: {
              yellow: "#FFC914",
              dark: "#272727",
              footer: "#272727",
              bg: "#F8F9FA",
              accent: "#16A34A",
            },
          },
        },
      },
    };
  </script>
</head>

<body class="bg-[#F8F9FA] font-sans antialiased text-gray-900 min-h-screen">
  <div class="min-h-screen flex flex-col lg:flex-row">
    <!-- Bagian Kiri: Formulir Login -->
    <div class="w-full lg:w-1/2 flex flex-col justify-between p-6 sm:p-10 md:p-14 lg:p-16 bg-white shadow-sm z-10">
      <!-- Header Brand -->
      <div>
        <a href="landingpage.php" class="inline-flex items-center gap-3 group">
          <img src="assets/logo.png" alt="Logo Loot - E"
            class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm" />
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

        <!-- Alert Info -->
        <?php if (!empty($info)): ?>
          <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm flex items-start gap-3">
            <i class="ph ph-info text-xl flex-shrink-0 text-amber-600 mt-0.5"></i>
            <div class="flex-1 font-medium"><?= htmlspecialchars($info) ?></div>
          </div>
        <?php endif; ?>

        <!-- Alert Error -->
        <?php if (!empty($error)): ?>
          <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-3">
            <i class="ph ph-warning-circle text-xl flex-shrink-0 text-red-600 mt-0.5"></i>
            <div class="flex-1 font-medium"><?= htmlspecialchars($error) ?></div>
          </div>
        <?php endif; ?>

        <form id="form-login" action="login.php<?= $redirect !== 'index.php' ? '?redirect=' . urlencode($redirect) : '' ?>" method="POST" class="space-y-5" novalidate>
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>" />
          <!-- Input Email -->
          <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">Alamat Email</label>
            <div class="relative">
              <i class="ph ph-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none"></i>
              <input type="email" id="email" name="email" placeholder="nama@contoh.com" required
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                aria-describedby="email-error"
                aria-invalid="<?= isset($fieldErrors['email']) ? 'true' : 'false' ?>"
                class="w-full pl-11 pr-4 py-3 rounded-xl border <?= isset($fieldErrors['email']) ? 'border-red-400 bg-red-50/20 focus:border-red-500 focus:ring-red-500/20' : 'border-gray-300 bg-white focus:border-brand-yellow focus:ring-brand-yellow/30' ?> text-gray-900 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 transition shadow-sm" />
            </div>
            <p id="email-error" class="<?= isset($fieldErrors['email']) ? 'flex' : 'hidden' ?> items-center gap-1.5 text-xs text-red-600 font-medium mt-1.5" role="alert">
              <i class="ph ph-warning-circle text-base flex-shrink-0 text-red-500"></i>
              <span class="error-text"><?= htmlspecialchars($fieldErrors['email'] ?? '') ?></span>
            </p>
          </div>

          <!-- Input Kata Sandi -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label for="password" class="block text-sm font-semibold text-gray-700">Kata Sandi</label>
            </div>
            <div class="relative">
              <i class="ph ph-lock-key absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none"></i>
              <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required
                aria-describedby="password-error"
                aria-invalid="<?= isset($fieldErrors['password']) ? 'true' : 'false' ?>"
                class="w-full pl-11 pr-11 py-3 rounded-xl border <?= isset($fieldErrors['password']) ? 'border-red-400 bg-red-50/20 focus:border-red-500 focus:ring-red-500/20' : 'border-gray-300 bg-white focus:border-brand-yellow focus:ring-brand-yellow/30' ?> text-gray-900 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 transition shadow-sm" />
              <button type="button" onclick="toggleVis('password', this)" aria-label="Tampilkan atau sembunyikan kata sandi"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition cursor-pointer p-1">
                <i class="ph ph-eye text-lg"></i>
              </button>
            </div>
            <p id="password-error" class="<?= isset($fieldErrors['password']) ? 'flex' : 'hidden' ?> items-center gap-1.5 text-xs text-red-600 font-medium mt-1.5" role="alert">
              <i class="ph ph-warning-circle text-base flex-shrink-0 text-red-500"></i>
              <span class="error-text"><?= htmlspecialchars($fieldErrors['password'] ?? '') ?></span>
            </p>
          </div>

          <!-- Tombol Masuk -->
          <button type="submit" id="btn-submit"
            class="w-full py-3.5 px-4 rounded-xl font-bold text-white bg-brand-yellow hover:bg-yellow-500 active:scale-[0.99] transition shadow-md hover:shadow-lg flex items-center justify-center gap-2 cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed">
            <span class="btn-text">Masuk</span>
            <i class="ph ph-arrow-right font-bold text-lg btn-icon"></i>
            <i class="ph ph-spinner animate-spin text-xl hidden btn-loading"></i>
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
          <a href="landingpage.php" class="hover:text-gray-600 transition">Beranda</a>
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
      btn.innerHTML = isPassword ?
        '<i class="ph ph-eye-slash text-lg"></i>' :
        '<i class="ph ph-eye text-lg"></i>';
    }

    (() => {
      const form = document.getElementById('form-login');
      if (!form) return;

      const email = document.getElementById('email');
      const password = document.getElementById('password');
      const submitBtn = document.getElementById('btn-submit');
      const btnText = submitBtn.querySelector('.btn-text');
      const btnIcon = submitBtn.querySelector('.btn-icon');
      const btnLoading = submitBtn.querySelector('.btn-loading');

      const touched = { email: false, password: false };

      function setFieldError(input, errorId, message) {
        const errEl = document.getElementById(errorId);
        if (errEl) {
          const textSpan = errEl.querySelector('.error-text');
          if (textSpan) textSpan.textContent = message;
          errEl.classList.remove('hidden');
          errEl.classList.add('flex');
        }
        input.setAttribute('aria-invalid', 'true');
        input.classList.remove('border-gray-300', 'bg-white', 'focus:border-brand-yellow', 'focus:ring-brand-yellow/30');
        input.classList.add('border-red-400', 'bg-red-50/20', 'focus:border-red-500', 'focus:ring-red-500/20');
      }

      function clearFieldError(input, errorId) {
        const errEl = document.getElementById(errorId);
        if (errEl) {
          errEl.classList.add('hidden');
          errEl.classList.remove('flex');
        }
        input.setAttribute('aria-invalid', 'false');
        input.classList.remove('border-red-400', 'bg-red-50/20', 'focus:border-red-500', 'focus:ring-red-500/20');
        input.classList.add('border-gray-300', 'bg-white', 'focus:border-brand-yellow', 'focus:ring-brand-yellow/30');
      }

      function validateEmailField() {
        const val = email.value.trim();
        if (!val) {
          setFieldError(email, 'email-error', 'Alamat email wajib diisi.');
          return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(val)) {
          setFieldError(email, 'email-error', 'Format alamat email tidak valid (contoh: nama@contoh.com).');
          return false;
        }
        clearFieldError(email, 'email-error');
        return true;
      }

      function validatePasswordField() {
        const val = password.value;
        if (!val) {
          setFieldError(password, 'password-error', 'Kata sandi wajib diisi.');
          return false;
        }
        clearFieldError(password, 'password-error');
        return true;
      }

      // Validasi saat blur (user selesai mengisi input)
      email.addEventListener('blur', () => {
        touched.email = true;
        validateEmailField();
      });

      password.addEventListener('blur', () => {
        touched.password = true;
        validatePasswordField();
      });

      // Real-time validasi saat mengetik (hanya jika sudah disentuh / ada error)
      email.addEventListener('input', () => {
        if (touched.email || email.getAttribute('aria-invalid') === 'true') {
          validateEmailField();
        }
      });

      password.addEventListener('input', () => {
        if (touched.password || password.getAttribute('aria-invalid') === 'true') {
          validatePasswordField();
        }
      });

      form.addEventListener('submit', (e) => {
        touched.email = true;
        touched.password = true;

        const isEmailValid = validateEmailField();
        const isPasswordValid = validatePasswordField();

        if (!isEmailValid || !isPasswordValid) {
          e.preventDefault();
          if (!isEmailValid) {
            email.focus();
          } else {
            password.focus();
          }
          return;
        }

        submitBtn.disabled = true;
        if (btnText) btnText.textContent = 'Memproses...';
        if (btnIcon) btnIcon.classList.add('hidden');
        if (btnLoading) btnLoading.classList.remove('hidden');
      });
    })();
  </script>
</body>

</html>
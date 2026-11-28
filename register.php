<?php
// register.php - Pendaftaran Pengguna Baru Loot-E
require_once "auth.php";

$redirect = $_GET["redirect"] ?? $_POST["redirect"] ?? "index.php";
$allowedRedirects = ["index.php", "categories.php", "search.php", "detail_produk.php", "cart.php", "profile.php", "pesanan.php", "dashboard_seller.php", "katalog.php", "upload_produk.php", "verifikasi_akun.php", "alamat_user.php"];
$redirectBase = parse_url($redirect, PHP_URL_PATH);
if (!in_array($redirectBase, $allowedRedirects, true)) {
  $redirect = "index.php";
}

// Jika sudah login, langsung arahkan ke tujuan
if (isLoggedIn()) {
  header("Location: " . $redirect);
  exit;
}

$errors = [];
$fieldErrors = [];

// Proses saat form disubmit via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nama            = trim($_POST['nama_lengkap'] ?? '');
  $rawEmail        = trim($_POST['email'] ?? '');
  $email           = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
  $password        = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';
  $terms           = isset($_POST['terms']);

  // Validasi Nama Lengkap
  if (empty($nama)) {
    $fieldErrors['nama_lengkap'] = "Nama lengkap wajib diisi.";
  } elseif (mb_strlen($nama) < 3) {
    $fieldErrors['nama_lengkap'] = "Nama lengkap minimal 3 karakter.";
  } elseif (mb_strlen($nama) > 100) {
    $fieldErrors['nama_lengkap'] = "Nama lengkap maksimal 100 karakter.";
  }

  // Validasi Email
  if (empty($rawEmail)) {
    $fieldErrors['email'] = "Alamat email wajib diisi.";
  } elseif (!$email || mb_strlen($rawEmail) > 100) {
    $fieldErrors['email'] = "Format alamat email tidak valid (contoh: nama@contoh.com).";
  }

  // Validasi Kata Sandi
  if (empty($password)) {
    $fieldErrors['password'] = "Kata sandi wajib diisi.";
  } elseif (strlen($password) < 8) {
    $fieldErrors['password'] = "Kata sandi minimal harus 8 karakter.";
  }

  // Validasi Konfirmasi Kata Sandi
  if (empty($confirmPassword)) {
    $fieldErrors['confirm_password'] = "Konfirmasi kata sandi wajib diisi.";
  } elseif ($password !== $confirmPassword) {
    $fieldErrors['confirm_password'] = "Konfirmasi kata sandi tidak cocok.";
  }

  // Validasi Syarat & Ketentuan
  if (!$terms) {
    $fieldErrors['terms'] = "Anda harus menyetujui Syarat & Ketentuan untuk melanjutkan.";
  }

  if (empty($fieldErrors)) {
    try {
      $db = getDbConnection();

      $stmtCheck = $db->prepare("SELECT id FROM pengguna WHERE email = ?");
      $stmtCheck->execute([$email]);
      if ($stmtCheck->fetch()) {
        $fieldErrors['email'] = "Email tersebut sudah terdaftar. Silakan gunakan email lain atau langsung masuk.";
      } else {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmtInsert = $db->prepare("INSERT INTO pengguna (nama, email, password, peran, is_verified) VALUES (?, ?, ?, 'user', 0)");
        $stmtInsert->execute([$nama, $email, $hashedPassword]);

        $userId = (int)$db->lastInsertId();

        // Login otomatis setelah register
        session_regenerate_id(true);
        $_SESSION['user_id']     = $userId;
        $_SESSION['nama']        = $nama;
        $_SESSION['email']       = $email;
        $_SESSION['peran']       = 'user';
        $_SESSION['is_verified'] = 0;

        header("Location: " . $redirect);
        exit;
      }
    } catch (PDOException $e) {
      $errors[] = "Terjadi gangguan database. Silakan coba lagi.";
    }
  }
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Daftar Akun - Loot-E</title>
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
    <!-- Bagian Kiri: Formulir Pendaftaran -->
    <div class="w-full lg:w-1/2 flex flex-col justify-between p-6 sm:p-10 md:p-14 lg:p-16 bg-white shadow-sm z-10 overflow-y-auto">
      <!-- Header Brand -->
      <div>
        <a href="landingpage.php" class="inline-flex items-center gap-3 group">
          <img src="assets/logo.png" alt="Logo Loot - E"
            class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm" />
          <span class="font-bold text-xl tracking-tight text-gray-900">Loot - <span class="text-brand-yellow">E</span></span>
        </a>
      </div>

      <!-- Konten Utama Pendaftaran -->
      <div class="w-full max-w-md mx-auto my-6">
        <div class="mb-6">
          <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">
            Daftar Akun
          </h1>
          <p class="text-gray-500 text-sm sm:text-base mt-2">
            Buat akun baru untuk mulai menggunakan layanan Loot-E.
          </p>
        </div>

        <!-- Alert Error -->
        <?php if (!empty($errors)): ?>
          <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-3">
            <i class="ph ph-warning-circle text-xl flex-shrink-0 text-red-600 mt-0.5"></i>
            <div class="flex-1 font-medium">
              <ul class="list-disc ml-4 space-y-1">
                <?php foreach ($errors as $err): ?>
                  <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>

        <form id="form-register" action="register.php<?= $redirect !== 'index.php' ? '?redirect=' . urlencode($redirect) : '' ?>" method="POST" class="space-y-4" novalidate>
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>" />
          <!-- Input Nama Lengkap -->
          <div>
            <label for="nama_lengkap" class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Lengkap</label>
            <div class="relative">
              <i class="ph ph-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none"></i>
              <input type="text" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap Anda" required
                value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>"
                aria-describedby="nama_lengkap-error"
                aria-invalid="<?= isset($fieldErrors['nama_lengkap']) ? 'true' : 'false' ?>"
                class="w-full pl-11 pr-4 py-3 rounded-xl border <?= isset($fieldErrors['nama_lengkap']) ? 'border-red-400 bg-red-50/20 focus:border-red-500 focus:ring-red-500/20' : 'border-gray-300 bg-white focus:border-brand-yellow focus:ring-brand-yellow/30' ?> text-gray-900 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 transition shadow-sm" />
            </div>
            <p id="nama_lengkap-error" class="<?= isset($fieldErrors['nama_lengkap']) ? 'flex' : 'hidden' ?> items-center gap-1.5 text-xs text-red-600 font-medium mt-1.5" role="alert">
              <i class="ph ph-warning-circle text-base flex-shrink-0 text-red-500"></i>
              <span class="error-text"><?= htmlspecialchars($fieldErrors['nama_lengkap'] ?? '') ?></span>
            </p>
          </div>

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
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-1.5">Kata Sandi</label>
            <div class="relative">
              <i class="ph ph-lock-key absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none"></i>
              <input type="password" id="password" name="password" placeholder="Minimal 8 karakter" required minlength="8"
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

            <!-- Requirement Hint: Minimal 8 karakter -->
            <div id="req-len" class="flex items-center gap-1.5 text-xs text-gray-500 mt-2 transition-colors">
              <i class="ph ph-circle text-xs req-icon text-gray-400"></i>
              <span>Kata sandi minimal 8 karakter</span>
            </div>
          </div>

          <!-- Input Konfirmasi Kata Sandi -->
          <div>
            <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-1.5">Konfirmasi Kata Sandi</label>
            <div class="relative">
              <i class="ph ph-shield-check absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none"></i>
              <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi kata sandi Anda" required
                aria-describedby="confirm_password-error"
                aria-invalid="<?= isset($fieldErrors['confirm_password']) ? 'true' : 'false' ?>"
                class="w-full pl-11 pr-11 py-3 rounded-xl border <?= isset($fieldErrors['confirm_password']) ? 'border-red-400 bg-red-50/20 focus:border-red-500 focus:ring-red-500/20' : 'border-gray-300 bg-white focus:border-brand-yellow focus:ring-brand-yellow/30' ?> text-gray-900 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 transition shadow-sm" />
              <button type="button" onclick="toggleVis('confirm_password', this)" aria-label="Tampilkan atau sembunyikan konfirmasi kata sandi"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition cursor-pointer p-1">
                <i class="ph ph-eye text-lg"></i>
              </button>
            </div>
            <p id="confirm_password-error" class="<?= isset($fieldErrors['confirm_password']) ? 'flex' : 'hidden' ?> items-center gap-1.5 text-xs text-red-600 font-medium mt-1.5" role="alert">
              <i class="ph ph-warning-circle text-base flex-shrink-0 text-red-500"></i>
              <span class="error-text"><?= htmlspecialchars($fieldErrors['confirm_password'] ?? '') ?></span>
            </p>
            <p id="confirm_password-match" class="hidden items-center gap-1.5 text-xs text-emerald-600 font-medium mt-1.5">
              <i class="ph ph-check-circle text-base flex-shrink-0 text-emerald-500"></i>
              <span>Kata sandi cocok</span>
            </p>
          </div>

          <!-- Syarat & Ketentuan Checkbox -->
          <div class="pt-1">
            <label class="flex items-start gap-2.5 cursor-pointer text-sm text-gray-600 select-none">
              <input type="checkbox" id="terms" name="terms" required checked
                class="w-4 h-4 mt-0.5 rounded border-gray-300 text-brand-yellow focus:ring-brand-yellow/40 cursor-pointer" />
              <span>Saya menyetujui
                <a href="#" class="font-semibold text-brand-yellow hover:underline">Syarat &amp; Ketentuan</a>
                Loot-E</span>
            </label>
            <p id="terms-error" class="<?= isset($fieldErrors['terms']) ? 'flex' : 'hidden' ?> items-center gap-1.5 text-xs text-red-600 font-medium mt-1.5" role="alert">
              <i class="ph ph-warning-circle text-base flex-shrink-0 text-red-500"></i>
              <span class="error-text"><?= htmlspecialchars($fieldErrors['terms'] ?? '') ?></span>
            </p>
          </div>

          <!-- Tombol Daftar -->
          <button type="submit" id="btn-submit"
            class="w-full py-3.5 px-4 rounded-xl font-bold text-white bg-brand-yellow hover:bg-yellow-500 active:scale-[0.99] transition shadow-md hover:shadow-lg flex items-center justify-center gap-2 cursor-pointer mt-4 disabled:opacity-75 disabled:cursor-not-allowed">
            <span class="btn-text">Daftar Akun</span>
            <i class="ph ph-arrow-right font-bold text-lg btn-icon"></i>
            <i class="ph ph-spinner animate-spin text-xl hidden btn-loading"></i>
          </button>
        </form>

        <!-- Tautan Login -->
        <p class="text-center text-sm text-gray-500 mt-6">
          Sudah punya akun?
          <a href="login.php<?= $redirect !== 'index.php' ? '?redirect=' . urlencode($redirect) : '' ?>" class="font-bold text-brand-yellow hover:text-yellow-600 hover:underline transition ml-1">Masuk</a>
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
      const form = document.getElementById('form-register');
      if (!form) return;

      const nama = document.getElementById('nama_lengkap');
      const email = document.getElementById('email');
      const password = document.getElementById('password');
      const confirmPassword = document.getElementById('confirm_password');
      const terms = document.getElementById('terms');
      const submitBtn = document.getElementById('btn-submit');
      const btnText = submitBtn.querySelector('.btn-text');
      const btnIcon = submitBtn.querySelector('.btn-icon');
      const btnLoading = submitBtn.querySelector('.btn-loading');

      const reqLen = document.getElementById('req-len');
      const confirmMatch = document.getElementById('confirm_password-match');

      const touched = {
        nama_lengkap: false,
        email: false,
        password: false,
        confirm_password: false,
        terms: false
      };

      function setFieldError(input, errorId, message) {
        const errEl = document.getElementById(errorId);
        if (errEl) {
          const textSpan = errEl.querySelector('.error-text');
          if (textSpan) textSpan.textContent = message;
          errEl.classList.remove('hidden');
          errEl.classList.add('flex');
        }
        if (input) {
          input.setAttribute('aria-invalid', 'true');
          input.classList.remove('border-gray-300', 'bg-white', 'focus:border-brand-yellow', 'focus:ring-brand-yellow/30', 'border-emerald-500');
          input.classList.add('border-red-400', 'bg-red-50/20', 'focus:border-red-500', 'focus:ring-red-500/20');
        }
      }

      function clearFieldError(input, errorId) {
        const errEl = document.getElementById(errorId);
        if (errEl) {
          errEl.classList.add('hidden');
          errEl.classList.remove('flex');
        }
        if (input) {
          input.setAttribute('aria-invalid', 'false');
          input.classList.remove('border-red-400', 'bg-red-50/20', 'focus:border-red-500', 'focus:ring-red-500/20');
          input.classList.add('border-gray-300', 'bg-white', 'focus:border-brand-yellow', 'focus:ring-brand-yellow/30');
        }
      }

      function validateNama() {
        const val = nama.value.trim();
        if (!val) {
          setFieldError(nama, 'nama_lengkap-error', 'Nama lengkap wajib diisi.');
          return false;
        }
        if (val.length < 3) {
          setFieldError(nama, 'nama_lengkap-error', 'Nama lengkap minimal 3 karakter.');
          return false;
        }
        if (val.length > 100) {
          setFieldError(nama, 'nama_lengkap-error', 'Nama lengkap maksimal 100 karakter.');
          return false;
        }
        const nameRegex = /^[a-zA-Z\s'’.\-]+$/;
        if (!nameRegex.test(val)) {
          setFieldError(nama, 'nama_lengkap-error', 'Nama lengkap hanya boleh berisi huruf dan spasi.');
          return false;
        }
        clearFieldError(nama, 'nama_lengkap-error');
        return true;
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
        if (val.length > 100) {
          setFieldError(email, 'email-error', 'Alamat email terlalu panjang (maks. 100 karakter).');
          return false;
        }
        clearFieldError(email, 'email-error');
        return true;
      }

      function updatePasswordRequirement() {
        const val = password.value;
        const hasLen = val.length >= 8;

        if (hasLen) {
          reqLen.className = 'flex items-center gap-1.5 text-xs text-emerald-600 font-medium mt-2 transition-colors';
          reqLen.querySelector('.req-icon').className = 'ph ph-check-circle text-xs req-icon text-emerald-600';
          reqLen.querySelector('span').textContent = 'karakter terpenuhi';
        } else {
          reqLen.className = 'flex items-center gap-1.5 text-xs text-gray-500 mt-2 transition-colors';
          reqLen.querySelector('.req-icon').className = 'ph ph-circle text-xs req-icon text-gray-400';
          reqLen.querySelector('span').textContent = 'Minimal 8 karakter';
        }
      }

      function validatePasswordField() {
        const val = password.value;
        if (!val) {
          setFieldError(password, 'password-error', 'Kata sandi wajib diisi.');
          return false;
        }
        if (val.length < 8) {
          setFieldError(password, 'password-error', 'Kata sandi minimal harus 8 karakter.');
          return false;
        }
        clearFieldError(password, 'password-error');
        return true;
      }

      function validateConfirmPasswordField() {
        const pwdVal = password.value;
        const confirmVal = confirmPassword.value;

        if (!confirmVal) {
          confirmMatch.classList.add('hidden');
          confirmMatch.classList.remove('flex');
          setFieldError(confirmPassword, 'confirm_password-error', 'Konfirmasi kata sandi wajib diisi.');
          return false;
        }

        if (pwdVal !== confirmVal) {
          confirmMatch.classList.add('hidden');
          confirmMatch.classList.remove('flex');
          setFieldError(confirmPassword, 'confirm_password-error', 'Konfirmasi kata sandi tidak cocok.');
          return false;
        }

        // Cocok
        clearFieldError(confirmPassword, 'confirm_password-error');
        if (pwdVal.length >= 8) {
          confirmMatch.classList.remove('hidden');
          confirmMatch.classList.add('flex');
        }
        return true;
      }

      function validateTerms() {
        if (!terms.checked) {
          setFieldError(null, 'terms-error', 'Anda harus menyetujui Syarat & Ketentuan untuk melanjutkan.');
          return false;
        }
        clearFieldError(null, 'terms-error');
        return true;
      }

      // Event Listeners: Blur
      nama.addEventListener('blur', () => {
        touched.nama_lengkap = true;
        validateNama();
      });

      email.addEventListener('blur', () => {
        touched.email = true;
        validateEmailField();
      });

      password.addEventListener('blur', () => {
        touched.password = true;
        validatePasswordField();
      });

      confirmPassword.addEventListener('blur', () => {
        touched.confirm_password = true;
        validateConfirmPasswordField();
      });

      // Event Listeners: Input
      nama.addEventListener('input', () => {
        if (touched.nama_lengkap || nama.getAttribute('aria-invalid') === 'true') {
          validateNama();
        }
      });

      email.addEventListener('input', () => {
        if (touched.email || email.getAttribute('aria-invalid') === 'true') {
          validateEmailField();
        }
      });

      password.addEventListener('input', () => {
        updatePasswordRequirement();
        if (touched.password || password.getAttribute('aria-invalid') === 'true') {
          validatePasswordField();
        }
        if (touched.confirm_password || confirmPassword.value.length > 0) {
          validateConfirmPasswordField();
        }
      });

      confirmPassword.addEventListener('input', () => {
        if (touched.confirm_password || confirmPassword.getAttribute('aria-invalid') === 'true' || confirmPassword.value.length >= password.value.length) {
          validateConfirmPasswordField();
        }
      });

      terms.addEventListener('change', () => {
        if (touched.terms || !terms.checked) {
          validateTerms();
        }
      });

      // Initial check in case values are pre-filled (e.g. from browser autofill or PHP post)
      if (password.value) {
        updatePasswordRequirement();
      }

      // Form Submit
      form.addEventListener('submit', (e) => {
        touched.nama_lengkap = true;
        touched.email = true;
        touched.password = true;
        touched.confirm_password = true;
        touched.terms = true;

        const isNamaValid = validateNama();
        const isEmailValid = validateEmailField();
        const isPasswordValid = validatePasswordField();
        const isConfirmValid = validateConfirmPasswordField();
        const isTermsValid = validateTerms();

        if (!isNamaValid || !isEmailValid || !isPasswordValid || !isConfirmValid || !isTermsValid) {
          e.preventDefault();
          // Fokus ke elemen error pertama
          if (!isNamaValid) {
            nama.focus();
          } else if (!isEmailValid) {
            email.focus();
          } else if (!isPasswordValid) {
            password.focus();
          } else if (!isConfirmValid) {
            confirmPassword.focus();
          } else if (!isTermsValid) {
            terms.focus();
          }
          return;
        }

        submitBtn.disabled = true;
        if (btnText) btnText.textContent = 'Mendaftarkan Akun...';
        if (btnIcon) btnIcon.classList.add('hidden');
        if (btnLoading) btnLoading.classList.remove('hidden');
      });
    })();
  </script>
</body>

</html>
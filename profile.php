<?php
require_once "auth.php";

if (!isLoggedIn()) {
    header("Location: login.php?redirect=profile.php");
    exit;
}

$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

$stmtUser = $db->prepare("
    SELECT id, nama, email, peran, no_hp, alamat, is_verified
    FROM pengguna
    WHERE id = ?
    LIMIT 1
");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();

if (!$userRow) {
    header("Location: auth.php?action=logout");
    exit;
}

// Sinkronkan session dengan data terbaru dari database (termasuk is_verified / BR-04)
$_SESSION['nama']        = $userRow['nama'];
$_SESSION['email']       = $userRow['email'];
$_SESSION['peran']       = $userRow['peran'];
$_SESSION['is_verified'] = (int)$userRow['is_verified'];

$lokasiOptions = [
    'Bandung, Jawa Barat',
    'Jakarta Selatan, DKI Jakarta',
    'Jakarta Pusat, DKI Jakarta',
    'Surabaya, Jawa Timur',
    'Yogyakarta, DI Yogyakarta',
    'Semarang, Jawa Tengah',
    'Tangerang, Banten',
    'Bekasi, Jawa Barat',
    'Depok, Jawa Barat',
    'Medan, Sumatera Utara',
    'Denpasar, Bali',
];

$existingAlamat = trim((string)($userRow['alamat'] ?? ''));
if ($existingAlamat !== '' && !in_array($existingAlamat, $lokasiOptions, true)) {
    array_unshift($lokasiOptions, $existingAlamat);
}

// CSRF token untuk form edit profil
if (empty($_SESSION['csrf_profile'])) {
    $_SESSION['csrf_profile'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_profile'];

$flash = null;
if (!empty($_SESSION['profile_flash']) && is_array($_SESSION['profile_flash'])) {
    $flash = $_SESSION['profile_flash'];
    unset($_SESSION['profile_flash']);
}

// Nilai form default dari database
$formNama   = (string)$userRow['nama'];
$formEmail  = (string)$userRow['email'];
$formLokasi = $existingAlamat;

// Proses update profil (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $namaBaru    = trim((string)($_POST['nama_lengkap'] ?? ''));
    $emailRaw    = trim((string)($_POST['email'] ?? ''));
    $lokasiBaru  = trim((string)($_POST['lokasi'] ?? ''));

    $formNama   = $namaBaru;
    $formEmail  = $emailRaw;
    $formLokasi = $lokasiBaru;

    if (!hash_equals($csrfToken, $postedToken)) {
        $flash = ['type' => 'error', 'message' => 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.'];
    } elseif ($namaBaru === '') {
        $flash = ['type' => 'error', 'message' => 'Nama lengkap wajib diisi.'];
    } elseif (mb_strlen($namaBaru) > 100) {
        $flash = ['type' => 'error', 'message' => 'Nama lengkap maksimal 100 karakter.'];
    } elseif ($emailRaw === '' || !filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
        $flash = ['type' => 'error', 'message' => 'Format alamat email tidak valid.'];
    } elseif (mb_strlen($emailRaw) > 100) {
        $flash = ['type' => 'error', 'message' => 'Email maksimal 100 karakter.'];
    } elseif ($lokasiBaru !== '' && !in_array($lokasiBaru, $lokasiOptions, true)) {
        $flash = ['type' => 'error', 'message' => 'Pilihan lokasi tidak valid.'];
    } else {
        try {
            $stmtCekEmail = $db->prepare("SELECT id FROM pengguna WHERE email = ? AND id != ? LIMIT 1");
            $stmtCekEmail->execute([$emailRaw, $userId]);

            if ($stmtCekEmail->fetch()) {
                $flash = ['type' => 'error', 'message' => 'Email tersebut sudah digunakan oleh akun lain.'];
            } else {
                $alamatSimpan = $lokasiBaru !== '' ? $lokasiBaru : null;

                $stmtUpdate = $db->prepare("
                    UPDATE pengguna
                    SET nama = ?, email = ?, alamat = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$namaBaru, $emailRaw, $alamatSimpan, $userId]);

                $_SESSION['nama']  = $namaBaru;
                $_SESSION['email'] = $emailRaw;
                $_SESSION['profile_flash'] = [
                    'type'    => 'success',
                    'message' => 'Perubahan profil berhasil disimpan.',
                ];

                header("Location: profile.php");
                exit;
            }
        } catch (PDOException $e) {
            $flash = ['type' => 'error', 'message' => 'Gagal menyimpan perubahan profil. Silakan coba lagi.'];
        }
    }
}

$currentUser = getCurrentUser();
$navActivePage = 'profile';

$isVerified = (int)$userRow['is_verified'] === 1;
$userNoHp   = trim((string)($userRow['no_hp'] ?? ''));

// Tabel `pengguna` belum memiliki kolom `username`; turunkan handle tampilan dari email pengguna asli
$emailLocalPart = strstr((string)$userRow['email'], '@', true);
$displayUsername = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', $emailLocalPart !== false ? $emailLocalPart : (string)$userRow['nama']));
if ($displayUsername === '') {
    $displayUsername = 'user' . $userId;
}

$accountActiveMenu = 'profil';
$accountSidebarUser = [
    'nama'  => (string)$userRow['nama'],
    'email' => (string)$userRow['email'],
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profile Saya | Loot-E</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ye: '#FFC914',
                        ch: '#272727',
                        brand: {
                            yellow: '#FFC914',
                            dark: '#272727',
                            footer: '#272727',
                            bg: '#F4F4F4'
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        hd: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif']
                    }
                }
            }
        };
    </script>

    <style>
        :root {
            --brand-yellow: #FFC914;
            --brand-dark: #272727;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 2px solid var(--brand-yellow);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="bg-[#F4F4F4] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Header & Navigasi Utama -->
    <?php require_once 'components/navbar.php'; ?>

    <!-- Konten Utama -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 md:px-12 py-8 md:py-10">
        <div class="flex flex-col lg:flex-row items-start gap-6">

            <?php require_once 'components/account_sidebar.php'; ?>

            <!-- Area Konten Utama Profil -->
            <section class="flex-1 w-full min-w-0">
                <!-- Judul Halaman -->
                <header>
                    <h1 class="text-2xl sm:text-[26px] font-extrabold text-[#272727] tracking-tight leading-tight">
                        Profile Saya
                    </h1>
                    <p class="text-xs sm:text-[13px] text-gray-500 mt-1">
                        Kelola informasi akun dan pengaturanmu.
                    </p>
                </header>

                <!-- Banner Peringatan Belum Terverifikasi -->
                <?php if (!$isVerified): ?>
                    <div id="banner-verifikasi"
                        class="mt-5 bg-[#F5EFD9] px-5 py-4 sm:px-6 sm:py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-11 h-11 rounded-full bg-brand-yellow flex items-center justify-center shrink-0">
                                <i class="ph-bold ph-shield-check text-[22px] text-[#854D0E]" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm font-bold text-[#272727] leading-snug">
                                    Belum Terverifikasi
                                </h2>
                                <p class="text-xs text-gray-600 mt-0.5 leading-relaxed">
                                    Selesaikan verifikasi akun terlebih dahulu untuk mulai menjual produk di Loot-E.
                                </p>
                            </div>
                        </div>
                        <button type="button"
                            data-demo-toast="Halaman Verifikasi Akun belum tersedia saat ini (mode demo)."
                            class="inline-flex items-center justify-center gap-2 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition shrink-0 cursor-pointer self-start sm:self-center">
                            <span>Verifikasi Sekarang</span>
                            <i class="ph ph-arrow-right text-sm font-bold" aria-hidden="true"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Kartu Informasi Profil -->
                <div id="informasi-profil"
                    class="mt-5 bg-white border border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.03)] p-6 sm:p-7">
                    <h2 class="text-sm sm:text-[15px] font-bold text-[#272727] pb-3.5 border-b border-gray-200">
                        Informasi Profil
                    </h2>

                    <form id="profile-edit-form" action="profile.php" method="POST"
                        class="pt-6 flex flex-col-reverse md:flex-row items-start gap-8 md:gap-0">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />

                        <!-- Kolom Kiri: Input Form -->
                        <div class="w-full md:flex-1 md:pr-8 lg:pr-10 space-y-4">
                            <!-- Nama Lengkap -->
                            <div class="grid grid-cols-1 sm:grid-cols-[135px_1fr] items-center gap-1.5 sm:gap-4">
                                <label for="nama_lengkap" class="text-xs sm:text-[13px] font-medium text-[#272727]">
                                    Nama Lengkap
                                </label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" required maxlength="100"
                                    value="<?= htmlspecialchars($formNama, ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-full h-10 px-3.5 bg-white border border-gray-300 rounded-[2px] text-sm text-[#272727] placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition" />
                            </div>

                            <!-- Username -->
                            <div class="grid grid-cols-1 sm:grid-cols-[135px_1fr] items-center gap-1.5 sm:gap-4">
                                <label for="username" class="text-xs sm:text-[13px] font-medium text-[#272727]">
                                    Username
                                </label>
                                <input type="text" id="username" name="username" readonly
                                    value="<?= htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8') ?>"
                                    title="Username otomatis mengikuti alamat email akun"
                                    class="w-full h-10 px-3.5 bg-gray-50 border border-gray-300 rounded-[2px] text-sm text-gray-500 cursor-not-allowed focus:outline-none" />
                            </div>

                            <!-- Email -->
                            <div class="grid grid-cols-1 sm:grid-cols-[135px_1fr] items-center gap-1.5 sm:gap-4">
                                <label for="email" class="text-xs sm:text-[13px] font-medium text-[#272727]">
                                    Email
                                </label>
                                <input type="email" id="email" name="email" required maxlength="100"
                                    value="<?= htmlspecialchars($formEmail, ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-full h-10 px-3.5 bg-white border border-gray-300 rounded-[2px] text-sm text-[#272727] placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition" />
                            </div>

                            <!-- Nomor HP -->
                            <div class="grid grid-cols-1 sm:grid-cols-[135px_1fr] items-start gap-1.5 sm:gap-4">
                                <label for="no_hp" class="text-xs sm:text-[13px] font-medium text-[#272727] sm:pt-2.5">
                                    Nomor HP
                                </label>
                                <div>
                                    <input type="tel" id="no_hp" name="no_hp"
                                        value="<?= htmlspecialchars($userNoHp, ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="Belum diisi / belum diverifikasi"
                                        readonly
                                        class="w-full h-10 px-3.5 bg-gray-50 border border-gray-300 rounded-[2px] text-sm <?= $userNoHp !== '' ? 'text-[#272727]' : 'text-gray-500' ?> placeholder-gray-400 cursor-not-allowed focus:outline-none" />
                                    <?php if (!$isVerified || $userNoHp === ''): ?>
                                        <p class="mt-1.5 text-[11px] text-gray-500 leading-snug">
                                            Nomor HP belum diisi atau belum diverifikasi. Lengkapi melalui <a href="#banner-verifikasi" data-demo-toast="Halaman Verifikasi Akun belum tersedia saat ini (mode demo)." class="font-semibold text-[#854D0E] hover:underline">Verifikasi Akun</a>.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Lokasi -->
                            <div class="grid grid-cols-1 sm:grid-cols-[135px_1fr] items-center gap-1.5 sm:gap-4">
                                <label for="lokasi" class="text-xs sm:text-[13px] font-medium text-[#272727]">
                                    Lokasi
                                </label>
                                <div class="relative">
                                    <select id="lokasi" name="lokasi"
                                        class="w-full h-10 pl-3.5 pr-10 bg-white border border-gray-300 rounded-md text-sm text-[#272727] appearance-none cursor-pointer focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition">
                                        <option value="" <?= $formLokasi === '' ? 'selected' : '' ?>>Pilih lokasi...</option>
                                        <?php foreach ($lokasiOptions as $kota): ?>
                                            <option value="<?= htmlspecialchars($kota, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $kota === $formLokasi ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($kota, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="ph ph-caret-down text-sm text-[#272727] pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2" aria-hidden="true"></i>
                                </div>
                            </div>

                            <!-- Tombol Simpan Perubahan -->
                            <div class="grid grid-cols-1 sm:grid-cols-[135px_1fr] gap-1.5 sm:gap-4 pt-2">
                                <div class="hidden sm:block" aria-hidden="true"></div>
                                <div>
                                    <button type="submit"
                                        class="bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition cursor-pointer">
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Foto Profil -->
                        <div class="w-full md:w-[230px] lg:w-[250px] shrink-0 md:border-l md:border-gray-200 md:pl-8 lg:pl-10 flex flex-col items-center justify-center pt-1 pb-2">
                            <div class="w-[108px] h-[108px] rounded-full bg-[#373737] flex items-center justify-center overflow-hidden">
                                <i class="ph-fill ph-user text-[56px] text-[#CFCFCF]" aria-hidden="true"></i>
                            </div>

                            <button type="button" id="btn-ubah-foto"
                                data-demo-toast="Fitur Ubah Foto belum tersedia saat ini (mode demo)."
                                class="mt-4 inline-flex items-center justify-center gap-1.5 bg-[#F4F5F7] hover:bg-gray-200/80 border border-gray-300/90 text-[#272727] text-xs font-medium px-3.5 py-1.5 rounded-[2px] transition cursor-pointer">
                                <i class="ph ph-camera text-sm text-[#272727]" aria-hidden="true"></i>
                                <span>Ubah Foto</span>
                            </button>

                            <p class="mt-3 text-center text-[11px] text-gray-400 leading-relaxed">
                                Ukuran maks. 1 MB<br />
                                Format: .JPG, .JPEG, .PNG
                            </p>
                        </div>

                    </form>
                </div>
            </section>

        </div>
    </main>

    <!-- Toast Notifikasi -->
    <div id="profile-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg">
    </div>

    <!-- Footer & Help FAB -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function () {
            const toast = document.getElementById('profile-toast');
            const initialFlash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            let toastTimer = null;

            function showToast(message, type) {
                if (!toast || !message) return;
                toast.textContent = message;
                toast.classList.remove('hidden', 'border-brand-yellow', 'border-red-500');
                toast.classList.add(type === 'error' ? 'border-red-500' : 'border-brand-yellow');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function () {
                    toast.classList.add('hidden');
                }, 3500);
            }

            document.querySelectorAll('[data-demo-toast]').forEach(function (el) {
                el.addEventListener('click', function () {
                    const msg = el.getAttribute('data-demo-toast');
                    if (msg) showToast(msg, 'info');
                });
            });

            if (initialFlash && initialFlash.message) {
                showToast(initialFlash.message, initialFlash.type || 'success');
            }
        })();
    </script>
</body>

</html>

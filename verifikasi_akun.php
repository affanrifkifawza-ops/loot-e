<?php
// verifikasi_akun.php - Halaman Gate & Informasi Verifikasi Akun Seller Loot-E
require_once "auth.php";

if (!isLoggedIn()) {
    header("Location: login.php?redirect=verifikasi_akun.php");
    exit;
}

$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

// Sinkronkan data terbaru pengguna dari database (BR-04)
$stmtUser = $db->prepare("
    SELECT id, nama, email, peran, nik, no_hp, alamat, info_rekening, is_verified
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

$_SESSION['nama']        = $userRow['nama'];
$_SESSION['email']       = $userRow['email'];
$_SESSION['peran']       = $userRow['peran'];
$_SESSION['is_verified'] = (int)$userRow['is_verified'];

$currentUser = getCurrentUser();
$isVerified  = (int)$userRow['is_verified'] === 1;

$navActivePage = 'verifikasi';
$accountActiveMenu = 'verifikasi';
$accountSidebarUser = [
    'nama'        => (string)$userRow['nama'],
    'email'       => (string)$userRow['email'],
    'is_verified' => (int)$userRow['is_verified'],
];

// Ambil flash message dari redirect halaman seller (dashboard_seller.php / katalog.php / upload_produk.php)
$flash = null;
if (!empty($_SESSION['verifikasi_flash']) && is_array($_SESSION['verifikasi_flash'])) {
    $flash = $_SESSION['verifikasi_flash'];
    unset($_SESSION['verifikasi_flash']);
}

$bankOptions = [
    'BCA',
    'BNI',
    'BRI',
    'Mandiri',
    'BSI',
    'CIMB Niaga',
    'Bank Permata',
    'Bank Danamon',
    'Bank Jago',
    'SeaBank',
];

if (empty($_SESSION['csrf_verifikasi'])) {
    $_SESSION['csrf_verifikasi'] = bin2hex(random_bytes(32));
}
$csrfVerifikasi = $_SESSION['csrf_verifikasi'];

$existingRekening = parseRekeningPengguna($userRow['info_rekening'] ?? null);

$formValues = [
    'nama_ktp'       => '',
    'nik'            => trim((string)($userRow['nik'] ?? '')),
    'tanggal_lahir'  => '',
    'jenis_kelamin'  => '',
    'nama_bank'      => (string)($existingRekening['nama_bank'] ?? ''),
    'nama_pemilik'   => (string)($existingRekening['nama_pemilik'] ?? ''),
    'nomor_rekening' => (string)($existingRekening['nomor_rekening'] ?? ''),
    'persetujuan'    => [],
];
$fieldErrors = [];
$forcedState = null;

// Proses submit verifikasi akun (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isVerified) {
        header("Location: verifikasi_akun.php");
        exit;
    }

    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $formValues['nama_ktp']       = trim((string)($_POST['nama_ktp'] ?? ''));
    $formValues['nik']            = trim((string)($_POST['nik'] ?? ''));
    $formValues['tanggal_lahir']  = trim((string)($_POST['tanggal_lahir'] ?? ''));
    $formValues['jenis_kelamin']  = trim((string)($_POST['jenis_kelamin'] ?? ''));
    $formValues['nama_bank']      = trim((string)($_POST['nama_bank'] ?? ''));
    $formValues['nama_pemilik']   = trim((string)($_POST['nama_pemilik'] ?? ''));
    $formValues['nomor_rekening'] = trim((string)($_POST['nomor_rekening'] ?? ''));

    $rawPersetujuan = $_POST['persetujuan'] ?? [];
    if (is_array($rawPersetujuan)) {
        $formValues['persetujuan'] = array_values(array_map(static fn($v) => (string)$v, $rawPersetujuan));
    } else {
        $formValues['persetujuan'] = [];
    }

    if (!hash_equals($csrfVerifikasi, $postedToken)) {
        $flash = [
            'type'    => 'error',
            'message' => 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.',
        ];
        $forcedState = 'form';
    } else {
        if (!preg_match('/^\d{16}$/', $formValues['nik'])) {
            $fieldErrors['nik'] = 'NIK harus terdiri dari tepat 16 digit angka.';
        }

        if (!in_array($formValues['nama_bank'], $bankOptions, true)) {
            $fieldErrors['nama_bank'] = 'Pilih nama bank yang tersedia.';
        }

        if ($formValues['nama_pemilik'] === '' || mb_strlen($formValues['nama_pemilik']) > 100) {
            $fieldErrors['nama_pemilik'] = 'Nama pemilik rekening wajib diisi (maksimal 100 karakter).';
        }

        if (!preg_match('/^\d{5,25}$/', $formValues['nomor_rekening'])) {
            $fieldErrors['nomor_rekening'] = 'Nomor rekening harus terdiri dari 5 sampai 25 digit angka.';
        }

        if (count(array_intersect(['1', '2', '3'], $formValues['persetujuan'])) !== 3) {
            $fieldErrors['persetujuan'] = 'Harap centang seluruh poin persetujuan sebelum mengirim verifikasi.';
        }

        if (!empty($fieldErrors)) {
            $flash = [
                'type'    => 'error',
                'message' => 'Periksa kembali kolom wajib verifikasi yang ditandai merah.',
            ];
            $forcedState = 'form';
        } else {
            try {
                $stmtCekNik = $db->prepare("SELECT id FROM pengguna WHERE nik = ? AND id != ? LIMIT 1");
                $stmtCekNik->execute([$formValues['nik'], $userId]);

                if ($stmtCekNik->fetch()) {
                    $fieldErrors['nik'] = 'NIK tersebut sudah terdaftar pada akun lain.';
                    $flash = [
                        'type'    => 'error',
                        'message' => 'NIK tersebut sudah terdaftar pada akun lain.',
                    ];
                    $forcedState = 'form';
                } else {
                    $infoRekeningJson = json_encode([
                        'nama_bank'      => $formValues['nama_bank'],
                        'nama_pemilik'   => $formValues['nama_pemilik'],
                        'nomor_rekening' => $formValues['nomor_rekening'],
                    ], JSON_UNESCAPED_UNICODE);

                    if ($infoRekeningJson === false) {
                        throw new RuntimeException('Gagal mengodekan informasi rekening.');
                    }

                    $stmtUpdate = $db->prepare("
                        UPDATE pengguna
                        SET nik = ?, info_rekening = ?, is_verified = 1
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$formValues['nik'], $infoRekeningJson, $userId]);

                    $_SESSION['is_verified'] = 1;
                    $_SESSION['verifikasi_flash'] = [
                        'type'    => 'success',
                        'message' => 'Verifikasi akun berhasil. Akses fitur penjual Loot-E kini sudah aktif.',
                    ];

                    header("Location: verifikasi_akun.php");
                    exit;
                }
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    $fieldErrors['nik'] = 'NIK tersebut sudah terdaftar pada akun lain.';
                    $flash = [
                        'type'    => 'error',
                        'message' => 'NIK tersebut sudah terdaftar pada akun lain.',
                    ];
                } else {
                    $flash = [
                        'type'    => 'error',
                        'message' => 'Gagal menyimpan data verifikasi akun. Silakan coba lagi.',
                    ];
                }
                $forcedState = 'form';
            } catch (Throwable $e) {
                $flash = [
                    'type'    => 'error',
                    'message' => 'Gagal memproses data verifikasi akun. Silakan coba lagi.',
                ];
                $forcedState = 'form';
            }
        }
    }
}

$initialUnverifiedState = (!$isVerified && ($forcedState === 'form' || (($_GET['state'] ?? '') === 'form'))) ? 'form' : 'unverified';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verifikasi Akun | Loot-E</title>

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

        a[href],
        button:not(:disabled),
        summary,
        select:not(:disabled),
        label[for],
        input[type="radio"]:not(:disabled),
        input[type="checkbox"]:not(:disabled) {
            cursor: pointer;
        }

        button:disabled,
        input:disabled,
        select:disabled,
        textarea:disabled {
            cursor: not-allowed;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            outline: 2px solid var(--brand-yellow);
            outline-offset: 2px;
        }

        .field-error {
            border-color: #DC2626 !important;
            background-color: #FEF2F2 !important;
        }

        .dropzone-active {
            border-color: #272727 !important;
            background-color: #EFEFEF !important;
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            position: absolute;
            right: 0;
            width: 40px;
            height: 100%;
            cursor: pointer;
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

            <!-- Sidebar Navigasi Akun -->
            <?php require_once 'components/account_sidebar.php'; ?>

            <!-- Area Konten Utama Verifikasi Akun -->
            <section class="flex-1 w-full min-w-0">

                <!-- Header Judul Halaman -->
                <header>
                    <h1 class="text-2xl sm:text-[26px] font-extrabold text-[#272727] tracking-tight leading-tight">
                        Verifikasi Akun
                    </h1>
                    <p class="text-xs sm:text-[13px] text-gray-600 mt-1">
                        Verifikasi identitas akunmu untuk membuka akses berjualan dan pengelolaan toko di Loot-E
                    </p>
                </header>

                <!-- Notifikasi Flash (Success / Error / Redirect Seller) -->
                <?php if ($flash && !empty($flash['message'])): ?>
                    <?php
                    $flashType = (string)($flash['type'] ?? 'warning');
                    if ($flashType === 'success') {
                        $flashBoxClass = 'bg-emerald-50 border-emerald-200 text-emerald-900';
                        $flashIconClass = 'ph-bold ph-check-circle text-emerald-700';
                    } elseif ($flashType === 'error') {
                        $flashBoxClass = 'bg-red-50 border-red-200 text-red-800';
                        $flashIconClass = 'ph-bold ph-warning-circle text-red-600';
                    } else {
                        $flashBoxClass = 'bg-[#FFFDF5] border-amber-300/80 text-[#272727]';
                        $flashIconClass = 'ph-bold ph-warning-circle text-amber-700';
                    }
                    ?>
                    <div role="alert"
                        class="mt-4 border px-4 py-3 rounded-[2px] flex items-start gap-2.5 text-xs <?= $flashBoxClass ?>">
                        <i class="<?= $flashIconClass ?> text-base shrink-0 mt-0.5" aria-hidden="true"></i>
                        <p class="leading-relaxed">
                            <?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!$isVerified): ?>
                    <!-- ============================================================== -->
                    <!-- STATE A1: AKUN BELUM TERVERIFIKASI                             -->
                    <!-- ============================================================== -->
                    <div id="section-unverified" class="mt-5<?= $initialUnverifiedState === 'form' ? ' hidden' : '' ?>">
                        <!-- Kartu Utama Status & CTA Verifikasi (Selaras dengan State Kosong alamat_user.php) -->
                        <div class="bg-white border border-gray-200/80 p-8 sm:p-12 lg:p-14 text-center rounded-[2px] shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                            <!-- Ilustrasi Kartu Identitas & Perisai Verifikasi Loot-E -->
                            <div class="w-28 h-24 mx-auto flex items-center justify-center">
                                <svg width="112" height="96" viewBox="0 0 112 96" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <ellipse cx="56" cy="84" rx="44" ry="8" fill="#EAEAEA" />
                                    <rect x="18" y="22" width="64" height="46" rx="4" fill="#E8EDF2" stroke="#CBD5E1" stroke-width="1.5" />
                                    <rect x="24" y="28" width="52" height="34" rx="2" fill="#F8FAFC" />
                                    <rect x="29" y="34" width="14" height="16" rx="2" fill="#CBD5E1" />
                                    <circle cx="36" cy="40" r="3" fill="#94A3B8" />
                                    <path d="M31 48C31 45.8 33.2 44.5 36 44.5C38.8 44.5 41 45.8 41 48" fill="#94A3B8" />
                                    <rect x="48" y="35" width="22" height="3.5" rx="1.5" fill="#CBD5E1" />
                                    <rect x="48" y="42" width="16" height="3" rx="1.5" fill="#E2E8F0" />
                                    <rect x="29" y="54" width="32" height="3" rx="1.5" fill="#E2E8F0" />
                                    <path d="M80 28L96 34V47C96 57.5 88.8 65.8 80 69C71.2 65.8 64 57.5 64 47V34L80 28Z" fill="#FFC914" stroke="#272727" stroke-width="1.75" stroke-linejoin="round" />
                                    <path d="M74.5 48.5L78.3 52.3L86 44.5" stroke="#272727" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>

                            <span class="inline-flex items-center gap-1.5 mt-3 bg-[#FFF8E1] border border-amber-200/80 text-[#854D0E] text-[11px] font-bold px-2.5 py-1 rounded-[2px]">
                                Status: Belum Terverifikasi
                            </span>

                            <h2 class="text-base sm:text-lg font-bold text-[#272727] mt-3">
                                Akun Belum Terverifikasi
                            </h2>
                            <p class="text-xs sm:text-[13px] text-gray-600 mt-2 max-w-md mx-auto leading-relaxed">
                                Akun kamu saat ini belum terverifikasi, sehingga belum bisa mengunggah atau menjual produk di Loot-E. Lengkapi verifikasi identitas satu kali untuk mulai membuka toko dan berjualan dengan aman.
                            </p>

                            <button type="button" id="btn-mulai-verifikasi"
                                class="inline-flex items-center justify-center gap-2 min-h-[44px] bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-6 py-2.5 rounded-[2px] transition mt-6 cursor-pointer shadow-xs">
                                <i class="ph-bold ph-shield-check text-base" aria-hidden="true"></i>
                                <span>Verifikasi Akun Sekarang</span>
                            </button>
                        </div>
                    </div>

                    <!-- ============================================================== -->
                    <!-- STATE A2: FORMULIR VERIFIKASI AKUN                             -->
                    <!-- ============================================================== -->
                    <div id="section-form" class="mt-5<?= $initialUnverifiedState === 'form' ? '' : ' hidden' ?>">
                        <form id="form-verifikasi-akun" method="POST" action="verifikasi_akun.php" novalidate class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfVerifikasi, ENT_QUOTES, 'UTF-8') ?>" />

                            <!-- 1. Informasi Identitas -->
                            <section class="bg-white border border-gray-200/80 rounded-[2px] p-5 sm:p-6 shadow-[0_1px_2px_rgba(0,0,0,0.02)]" aria-labelledby="heading-identitas">
                                <h2 id="heading-identitas" class="flex items-center gap-2 text-sm sm:text-[15px] font-bold text-[#272727]">
                                    <i class="ph ph-identification-badge text-lg text-[#272727]" aria-hidden="true"></i>
                                    <span>Informasi Identitas</span>
                                </h2>

                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="input-nama-ktp" class="block text-xs font-semibold text-[#272727] mb-1.5">
                                            Nama Lengkap sesuai KTP <span class="text-red-600">*</span>
                                        </label>
                                        <input type="text" id="input-nama-ktp" name="nama_ktp" maxlength="100"
                                            value="<?= htmlspecialchars($formValues['nama_ktp'], ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Masukkan nama lengkap"
                                            class="w-full min-h-[44px] bg-white border border-gray-400/80 rounded-lg px-3.5 py-2 text-xs sm:text-[13px] text-[#272727] placeholder:text-gray-500 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition" />
                                        <p id="err-nama-ktp" class="hidden mt-1 text-xs font-medium text-red-600"></p>
                                    </div>

                                    <div>
                                        <label for="input-nik" class="block text-xs font-semibold text-[#272727] mb-1.5">
                                            NIK <span class="text-red-600">*</span>
                                        </label>
                                        <input type="text" id="input-nik" name="nik" inputmode="numeric" maxlength="16" required
                                            value="<?= htmlspecialchars($formValues['nik'], ENT_QUOTES, 'UTF-8') ?>"
                                            <?= isset($fieldErrors['nik']) ? 'aria-invalid="true" ' : '' ?>placeholder="Masukkan 16 digit NIK"
                                            class="w-full min-h-[44px] bg-white border border-gray-400/80 rounded-lg px-3.5 py-2 text-xs sm:text-[13px] text-[#272727] placeholder:text-gray-500 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition tabular-nums<?= isset($fieldErrors['nik']) ? ' field-error' : '' ?>" />
                                        <p id="err-nik" class="<?= isset($fieldErrors['nik']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['nik'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="input-tanggal-lahir" class="block text-xs font-semibold text-[#272727] mb-1.5">
                                            Tanggal Lahir <span class="text-red-600">*</span>
                                        </label>
                                        <div class="relative">
                                            <input type="<?= $formValues['tanggal_lahir'] !== '' ? 'date' : 'text' ?>" id="input-tanggal-lahir" name="tanggal_lahir"
                                                value="<?= htmlspecialchars($formValues['tanggal_lahir'], ENT_QUOTES, 'UTF-8') ?>"
                                                placeholder="Pilih tanggal lahir"
                                                class="w-full min-h-[44px] bg-white border border-gray-400/80 rounded-lg pl-3.5 pr-11 py-2 text-xs sm:text-[13px] text-[#272727] placeholder:text-gray-500 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition" />
                                            <button type="button" id="btn-picker-tanggal" aria-label="Pilih tanggal lahir"
                                                class="absolute right-0 top-0 h-full w-11 flex items-center justify-center rounded-r-lg text-gray-600 hover:text-[#272727] hover:bg-gray-100 transition">
                                                <i class="ph ph-calendar-blank text-base" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <p id="err-tanggal-lahir" class="hidden mt-1 text-xs font-medium text-red-600"></p>
                                    </div>

                                    <fieldset>
                                        <legend class="block text-xs font-semibold text-[#272727] mb-1.5">
                                            Jenis Kelamin <span class="text-red-600">*</span>
                                        </legend>
                                        <div class="flex flex-wrap items-center gap-5 min-h-[44px]">
                                            <label class="inline-flex items-center gap-2 min-h-[44px] text-xs sm:text-[13px] text-[#272727] cursor-pointer">
                                                <input type="radio" name="jenis_kelamin" value="Laki-laki" class="w-4 h-4 accent-[#272727]"<?= $formValues['jenis_kelamin'] === 'Laki-laki' ? ' checked' : '' ?> />
                                                <span>Laki-laki</span>
                                            </label>
                                            <label class="inline-flex items-center gap-2 min-h-[44px] text-xs sm:text-[13px] text-[#272727] cursor-pointer">
                                                <input type="radio" name="jenis_kelamin" value="Perempuan" class="w-4 h-4 accent-[#272727]"<?= $formValues['jenis_kelamin'] === 'Perempuan' ? ' checked' : '' ?> />
                                                <span>Perempuan</span>
                                            </label>
                                        </div>
                                        <p id="err-jenis-kelamin" class="hidden mt-1 text-xs font-medium text-red-600"></p>
                                    </fieldset>
                                </div>

                                <div class="mt-4 bg-[#F5F5F5] rounded-md px-3.5 py-2.5 flex items-center gap-2 text-xs text-gray-600">
                                    <i class="ph ph-info text-sm text-gray-600 shrink-0" aria-hidden="true"></i>
                                    <p>Pastikan data identitas sesuai dengan dokumen resmi yang akan kamu unggah.</p>
                                </div>
                            </section>

                            <!-- 2. Dokumen Identitas -->
                            <section class="bg-white border border-gray-200/80 rounded-[2px] p-5 sm:p-6 shadow-[0_1px_2px_rgba(0,0,0,0.02)]" aria-labelledby="heading-dokumen">
                                <h2 id="heading-dokumen" class="flex items-center gap-2 text-sm sm:text-[15px] font-bold text-[#272727]">
                                    <i class="ph ph-identification-card text-lg text-[#272727]" aria-hidden="true"></i>
                                    <span>Dokumen Identitas</span>
                                </h2>

                                <div class="mt-4">
                                    <span class="block text-xs font-semibold text-[#272727] mb-2">
                                        Foto KTP <span class="text-red-600">*</span>
                                    </span>

                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 md:gap-6 items-center">
                                        <div class="md:col-span-7">
                                            <div id="dropzone-ktp"
                                                class="relative bg-[#F5F5F5] border border-dashed border-gray-400 rounded-lg p-4 hover:border-[#272727] hover:bg-[#EFEFEF] transition focus-within:outline focus-within:outline-2 focus-within:outline-brand-yellow focus-within:outline-offset-2">
                                                <input type="file" id="input-foto-ktp" name="foto_ktp"
                                                    accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="sr-only" />
                                                <label for="input-foto-ktp" class="flex items-center gap-3.5 pr-11 cursor-pointer">
                                                    <div class="w-14 h-11 rounded bg-white border border-gray-300 flex items-center justify-center shrink-0 overflow-hidden">
                                                        <i id="icon-ktp-default" class="ph-fill ph-image text-xl text-gray-500" aria-hidden="true"></i>
                                                        <img id="preview-ktp" alt="Pratinjau Foto KTP" width="56" height="44"
                                                            class="hidden w-full h-full object-cover" />
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <span id="title-ktp" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#272727]">
                                                            <i class="ph-bold ph-upload-simple text-sm shrink-0" aria-hidden="true"></i>
                                                            <span>Unggah Foto KTP</span>
                                                        </span>
                                                        <span id="meta-ktp" class="block text-[11px] text-gray-600 mt-0.5 truncate">
                                                            Format JPG, JPEG, PNG • Maks. 2 MB
                                                        </span>
                                                    </div>
                                                </label>
                                                <button type="button" id="btn-hapus-ktp" aria-label="Hapus foto KTP"
                                                    class="hidden absolute top-1 right-1 w-11 h-11 rounded-md bg-white border border-gray-300 text-gray-600 hover:text-red-600 hover:border-red-300 flex items-center justify-center transition">
                                                    <i class="ph-bold ph-x text-sm" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                            <p id="err-foto-ktp" class="hidden mt-1 text-xs font-medium text-red-600"></p>
                                        </div>

                                        <ul class="md:col-span-5 space-y-1.5 text-xs text-gray-600">
                                            <li class="flex items-start gap-2">
                                                <i class="ph-bold ph-check text-xs text-gray-600 mt-0.5 shrink-0" aria-hidden="true"></i>
                                                <span>Pastikan KTP terlihat utuh</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <i class="ph-bold ph-check text-xs text-gray-600 mt-0.5 shrink-0" aria-hidden="true"></i>
                                                <span>Tulisan terbaca jelas</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <i class="ph-bold ph-check text-xs text-gray-600 mt-0.5 shrink-0" aria-hidden="true"></i>
                                                <span>Foto tidak blur atau gelap</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </section>

                            <!-- 3. Verifikasi Wajah -->
                            <section class="bg-white border border-gray-200/80 rounded-[2px] p-5 sm:p-6 shadow-[0_1px_2px_rgba(0,0,0,0.02)]" aria-labelledby="heading-wajah">
                                <h2 id="heading-wajah" class="flex items-center gap-2 text-sm sm:text-[15px] font-bold text-[#272727]">
                                    <i class="ph ph-smiley text-lg text-[#272727]" aria-hidden="true"></i>
                                    <span>Verifikasi Wajah</span>
                                </h2>

                                <div class="mt-4">
                                    <span class="block text-xs font-semibold text-[#272727] mb-2">
                                        Selfie Wajah <span class="text-red-600">*</span>
                                    </span>

                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 md:gap-6 items-center">
                                        <div class="md:col-span-7">
                                            <div id="dropzone-selfie"
                                                class="relative bg-[#F5F5F5] border border-dashed border-gray-400 rounded-lg p-4 hover:border-[#272727] hover:bg-[#EFEFEF] transition focus-within:outline focus-within:outline-2 focus-within:outline-brand-yellow focus-within:outline-offset-2">
                                                <input type="file" id="input-selfie" name="foto_selfie"
                                                    accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="sr-only" />
                                                <label for="input-selfie" class="flex items-center gap-3.5 pr-11 cursor-pointer">
                                                    <div class="w-14 h-11 rounded bg-white border border-gray-300 flex items-center justify-center shrink-0 overflow-hidden">
                                                        <i id="icon-selfie-default" class="ph ph-user-focus text-xl text-gray-600" aria-hidden="true"></i>
                                                        <img id="preview-selfie" alt="Pratinjau Selfie Wajah" width="56" height="44"
                                                            class="hidden w-full h-full object-cover" />
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <span id="title-selfie" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#272727]">
                                                            <i class="ph-bold ph-upload-simple text-sm shrink-0" aria-hidden="true"></i>
                                                            <span>Unggah Selfie Wajah</span>
                                                        </span>
                                                        <span id="meta-selfie" class="block text-[11px] text-gray-600 mt-0.5 leading-snug">
                                                            Wajah harus terlihat jelas tanpa masker atau kacamata gelap
                                                        </span>
                                                    </div>
                                                </label>
                                                <button type="button" id="btn-hapus-selfie" aria-label="Hapus foto selfie wajah"
                                                    class="hidden absolute top-1 right-1 w-11 h-11 rounded-md bg-white border border-gray-300 text-gray-600 hover:text-red-600 hover:border-red-300 flex items-center justify-center transition">
                                                    <i class="ph-bold ph-x text-sm" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                            <p id="err-selfie" class="hidden mt-1 text-xs font-medium text-red-600"></p>
                                        </div>

                                        <ul class="md:col-span-5 space-y-1.5 text-xs text-gray-600">
                                            <li class="flex items-start gap-2">
                                                <i class="ph-bold ph-check text-xs text-gray-600 mt-0.5 shrink-0" aria-hidden="true"></i>
                                                <span>Selfie digunakan untuk memastikan identitas pemilik akun sesuai dengan dokumen.</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </section>

                            <!-- 4. Informasi Rekening -->
                            <section class="bg-white border border-gray-200/80 rounded-[2px] p-5 sm:p-6 shadow-[0_1px_2px_rgba(0,0,0,0.02)]" aria-labelledby="heading-rekening">
                                <h2 id="heading-rekening" class="flex items-center gap-2 text-sm sm:text-[15px] font-bold text-[#272727]">
                                    <i class="ph ph-bank text-lg text-[#272727]" aria-hidden="true"></i>
                                    <span>Informasi Rekening</span>
                                </h2>

                                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="select-nama-bank" class="block text-xs font-semibold text-[#272727] mb-1.5">
                                            Nama Bank <span class="text-red-600">*</span>
                                        </label>
                                        <div class="relative">
                                            <select id="select-nama-bank" name="nama_bank" required
                                                <?= isset($fieldErrors['nama_bank']) ? 'aria-invalid="true" ' : '' ?>class="w-full min-h-[44px] bg-white border border-gray-400/80 rounded-lg pl-3.5 pr-9 py-2 text-xs sm:text-[13px] text-gray-500 appearance-none focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition<?= isset($fieldErrors['nama_bank']) ? ' field-error' : '' ?>">
                                                <option value="" disabled<?= $formValues['nama_bank'] === '' ? ' selected' : '' ?>>Pilih nama bank</option>
                                                <?php foreach ($bankOptions as $bankName): ?>
                                                    <option value="<?= htmlspecialchars($bankName, ENT_QUOTES, 'UTF-8') ?>" class="text-[#272727]"<?= $formValues['nama_bank'] === $bankName ? ' selected' : '' ?>>
                                                        <?= htmlspecialchars($bankName, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="ph ph-caret-down text-xs text-gray-600 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
                                        </div>
                                        <p id="err-nama-bank" class="<?= isset($fieldErrors['nama_bank']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['nama_bank'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>

                                    <div>
                                        <label for="input-nama-pemilik" class="block text-xs font-semibold text-[#272727] mb-1.5">
                                            Nama Pemilik Rekening <span class="text-red-600">*</span>
                                        </label>
                                        <input type="text" id="input-nama-pemilik" name="nama_pemilik" required maxlength="100"
                                            value="<?= htmlspecialchars($formValues['nama_pemilik'], ENT_QUOTES, 'UTF-8') ?>"
                                            <?= isset($fieldErrors['nama_pemilik']) ? 'aria-invalid="true" ' : '' ?>placeholder="Masukkan nama pemilik"
                                            class="w-full min-h-[44px] bg-white border border-gray-400/80 rounded-lg px-3.5 py-2 text-xs sm:text-[13px] text-[#272727] placeholder:text-gray-500 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition<?= isset($fieldErrors['nama_pemilik']) ? ' field-error' : '' ?>" />
                                        <p id="err-nama-pemilik" class="<?= isset($fieldErrors['nama_pemilik']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['nama_pemilik'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>

                                    <div>
                                        <label for="input-nomor-rekening" class="block text-xs font-semibold text-[#272727] mb-1.5">
                                            Nomor Rekening <span class="text-red-600">*</span>
                                        </label>
                                        <input type="text" id="input-nomor-rekening" name="nomor_rekening" inputmode="numeric" maxlength="25" required
                                            value="<?= htmlspecialchars($formValues['nomor_rekening'], ENT_QUOTES, 'UTF-8') ?>"
                                            <?= isset($fieldErrors['nomor_rekening']) ? 'aria-invalid="true" ' : '' ?>placeholder="Masukkan nomor rekening"
                                            class="w-full min-h-[44px] bg-white border border-gray-400/80 rounded-lg px-3.5 py-2 text-xs sm:text-[13px] text-[#272727] placeholder:text-gray-500 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition tabular-nums<?= isset($fieldErrors['nomor_rekening']) ? ' field-error' : '' ?>" />
                                        <p id="err-nomor-rekening" class="<?= isset($fieldErrors['nomor_rekening']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['nomor_rekening'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>

                                <div class="mt-4 bg-[#F5F5F5] rounded-md px-3.5 py-2.5 flex items-center gap-2 text-xs text-gray-600">
                                    <i class="ph ph-info text-sm text-gray-600 shrink-0" aria-hidden="true"></i>
                                    <p>Rekening ini akan digunakan untuk pencairan hasil penjualan.</p>
                                </div>
                            </section>

                            <!-- 5. Persetujuan -->
                            <section class="bg-white border border-gray-200/80 rounded-[2px] p-5 sm:p-6 shadow-[0_1px_2px_rgba(0,0,0,0.02)]" aria-labelledby="heading-persetujuan">
                                <h2 id="heading-persetujuan" class="flex items-center gap-2 text-sm sm:text-[15px] font-bold text-[#272727]">
                                    <i class="ph ph-file-text text-lg text-[#272727]" aria-hidden="true"></i>
                                    <span>Persetujuan</span>
                                </h2>

                                <div class="mt-4 space-y-3">
                                    <label class="flex items-start gap-2.5 text-xs text-[#272727] leading-relaxed cursor-pointer">
                                        <input type="checkbox" id="chk-agree-1" name="persetujuan[]" value="1"
                                            class="mt-0.5 w-4 h-4 rounded border-gray-400 accent-[#272727] shrink-0"<?= in_array('1', $formValues['persetujuan'], true) ? ' checked' : '' ?> />
                                        <span>Saya memastikan data dan dokumen yang saya kirim adalah benar dan milik saya.</span>
                                    </label>
                                    <label class="flex items-start gap-2.5 text-xs text-[#272727] leading-relaxed cursor-pointer">
                                        <input type="checkbox" id="chk-agree-2" name="persetujuan[]" value="2"
                                            class="mt-0.5 w-4 h-4 rounded border-gray-400 accent-[#272727] shrink-0"<?= in_array('2', $formValues['persetujuan'], true) ? ' checked' : '' ?> />
                                        <span>Saya memahami bahwa akun harus diverifikasi sebelum dapat menjual produk di Loot-E.</span>
                                    </label>
                                    <label class="flex items-start gap-2.5 text-xs text-[#272727] leading-relaxed cursor-pointer">
                                        <input type="checkbox" id="chk-agree-3" name="persetujuan[]" value="3"
                                            class="mt-0.5 w-4 h-4 rounded border-gray-400 accent-[#272727] shrink-0"<?= in_array('3', $formValues['persetujuan'], true) ? ' checked' : '' ?> />
                                        <span>Saya menyetujui proses verifikasi identitas sesuai kebijakan Loot-E.</span>
                                    </label>
                                </div>
                                <p id="err-persetujuan" class="<?= isset($fieldErrors['persetujuan']) ? '' : 'hidden ' ?>mt-2.5 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['persetujuan'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </section>

                            <!-- Tombol Aksi & Catatan Peninjauan -->
                            <div class="pt-1 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                                <button type="button" id="btn-batal-verifikasi"
                                    class="inline-flex items-center justify-center min-h-[44px] px-6 py-2.5 bg-white hover:bg-gray-50 active:scale-[0.99] border border-gray-300 text-[#272727] font-semibold text-xs sm:text-[13px] rounded-[2px] transition">
                                    Batal
                                </button>
                                <button type="submit" id="btn-kirim-verifikasi"
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] px-6 py-2.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] disabled:opacity-60 text-[#272727] font-bold text-xs sm:text-[13px] rounded-[2px] transition shadow-xs">
                                    <i class="ph-fill ph-paper-plane-tilt text-sm" aria-hidden="true"></i>
                                    <span id="btn-kirim-label">Kirim Verifikasi</span>
                                </button>
                            </div>

                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                <i class="ph ph-info text-sm text-gray-600 shrink-0" aria-hidden="true"></i>
                                <p>Data akan ditinjau oleh tim Loot-E sebelum akun dapat digunakan untuk berjualan.</p>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- ============================================================== -->
                    <!-- STATE B: AKUN SUDAH TERVERIFIKASI                              -->
                    <!-- ============================================================== -->
                    <div id="section-verified" class="mt-5">
                        <div class="bg-white border border-gray-200/80 p-8 sm:p-10 rounded-[2px] shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-gray-100">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-11 h-11 rounded-[2px] bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-700 shrink-0">
                                        <i class="ph-bold ph-seal-check text-2xl" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center gap-1 bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-bold px-2 py-0.5 rounded-[2px]">
                                            Status: Terverifikasi
                                        </span>
                                        <h2 class="text-base sm:text-lg font-bold text-[#272727] mt-1">
                                            Akun Sudah Terverifikasi
                                        </h2>
                                    </div>
                                </div>
                            </div>

                            <p class="text-xs sm:text-[13px] text-gray-600 mt-4 leading-relaxed">
                                Identitas akunmu sudah terverifikasi. Seluruh fitur penjual seperti Dashboard Toko, pengelolaan Katalog Produk, serta penerimaan tawaran Nego dan Barter sudah aktif digunakan.
                            </p>

                            <div class="mt-6 flex flex-wrap items-center gap-3">
                                <a href="dashboard_seller.php"
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition">
                                    <i class="ph ph-storefront text-base" aria-hidden="true"></i>
                                    <span>Buka Dashboard Toko</span>
                                </a>
                                <a href="katalog.php"
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] bg-[#272727] hover:bg-neutral-800 active:scale-[0.99] text-white font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition">
                                    <i class="ph ph-package text-base" aria-hidden="true"></i>
                                    <span>Kelola Katalog Produk</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </section>
        </div>
    </main>

    <!-- Toast Notifikasi Standar Loot-E -->
    <div id="verifikasi-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg max-w-sm">
    </div>

    <!-- Footer Standar Loot-E -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function () {
            const toast = document.getElementById('verifikasi-toast');
            const sectionUnverified = document.getElementById('section-unverified');
            const sectionForm = document.getElementById('section-form');
            const btnVerifikasi = document.getElementById('btn-mulai-verifikasi');
            const btnBatal = document.getElementById('btn-batal-verifikasi');
            const formVerifikasi = document.getElementById('form-verifikasi-akun');
            const mainContainer = document.querySelector('main');
            const initialFlash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            let toastTimer = null;

            function showToast(message, type) {
                if (!toast || !message) return;
                toast.textContent = message;
                toast.classList.remove('hidden', 'border-brand-yellow', 'border-red-500', 'border-emerald-500');
                if (type === 'error') {
                    toast.classList.add('border-red-500');
                } else if (type === 'success') {
                    toast.classList.add('border-emerald-500');
                } else {
                    toast.classList.add('border-brand-yellow');
                }
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function () {
                    toast.classList.add('hidden');
                }, 4200);
            }

            function formatFileSize(bytes) {
                if (bytes >= 1024 * 1024) {
                    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
                }
                return Math.max(1, Math.round(bytes / 1024)) + ' KB';
            }

            function setFieldError(targetEl, errorEl, message) {
                if (errorEl) {
                    if (message) {
                        errorEl.textContent = message;
                        errorEl.classList.remove('hidden');
                    } else {
                        errorEl.textContent = '';
                        errorEl.classList.add('hidden');
                    }
                }
                if (targetEl) {
                    if (message) {
                        targetEl.classList.add('field-error');
                        targetEl.setAttribute('aria-invalid', 'true');
                    } else {
                        targetEl.classList.remove('field-error');
                        targetEl.removeAttribute('aria-invalid');
                    }
                }
            }

            const inputNamaKtp = document.getElementById('input-nama-ktp');
            const inputNik = document.getElementById('input-nik');
            const inputTanggalLahir = document.getElementById('input-tanggal-lahir');
            const btnPickerTanggal = document.getElementById('btn-picker-tanggal');
            const selectNamaBank = document.getElementById('select-nama-bank');
            const inputNamaPemilik = document.getElementById('input-nama-pemilik');
            const inputNomorRekening = document.getElementById('input-nomor-rekening');
            const btnKirim = document.getElementById('btn-kirim-verifikasi');
            const btnKirimLabel = document.getElementById('btn-kirim-label');
            const genderRadios = Array.from(document.querySelectorAll('input[name="jenis_kelamin"]'));
            const agreementCheckboxes = [
                document.getElementById('chk-agree-1'),
                document.getElementById('chk-agree-2'),
                document.getElementById('chk-agree-3'),
            ].filter(Boolean);

            const errMap = {
                namaKtp: document.getElementById('err-nama-ktp'),
                nik: document.getElementById('err-nik'),
                tanggalLahir: document.getElementById('err-tanggal-lahir'),
                jenisKelamin: document.getElementById('err-jenis-kelamin'),
                fotoKtp: document.getElementById('err-foto-ktp'),
                selfie: document.getElementById('err-selfie'),
                namaBank: document.getElementById('err-nama-bank'),
                namaPemilik: document.getElementById('err-nama-pemilik'),
                nomorRekening: document.getElementById('err-nomor-rekening'),
                persetujuan: document.getElementById('err-persetujuan'),
            };

            function clearAllErrors() {
                setFieldError(inputNamaKtp, errMap.namaKtp, '');
                setFieldError(inputNik, errMap.nik, '');
                setFieldError(inputTanggalLahir, errMap.tanggalLahir, '');
                setFieldError(null, errMap.jenisKelamin, '');
                setFieldError(document.getElementById('dropzone-ktp'), errMap.fotoKtp, '');
                setFieldError(document.getElementById('dropzone-selfie'), errMap.selfie, '');
                setFieldError(selectNamaBank, errMap.namaBank, '');
                setFieldError(inputNamaPemilik, errMap.namaPemilik, '');
                setFieldError(inputNomorRekening, errMap.nomorRekening, '');
                setFieldError(null, errMap.persetujuan, '');
            }

            if (btnVerifikasi && sectionUnverified && sectionForm) {
                btnVerifikasi.addEventListener('click', function () {
                    sectionUnverified.classList.add('hidden');
                    sectionForm.classList.remove('hidden');
                    if (mainContainer) {
                        mainContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    if (inputNamaKtp) {
                        setTimeout(function () {
                            inputNamaKtp.focus();
                        }, 120);
                    }
                });
            }

            if (btnBatal && sectionUnverified && sectionForm) {
                btnBatal.addEventListener('click', function () {
                    clearAllErrors();
                    sectionForm.classList.add('hidden');
                    sectionUnverified.classList.remove('hidden');
                    if (mainContainer) {
                        mainContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    if (btnVerifikasi) {
                        setTimeout(function () {
                            btnVerifikasi.focus();
                        }, 120);
                    }
                });
            }

            // Input Tanggal Lahir: tampilkan placeholder teks saat kosong, ganti ke date picker saat fokus/klik
            if (inputTanggalLahir) {
                const todayIso = new Date().toISOString().split('T')[0];
                inputTanggalLahir.setAttribute('max', todayIso);

                function activateDateInput(openPicker) {
                    if (inputTanggalLahir.type !== 'date') {
                        inputTanggalLahir.type = 'date';
                    }
                    if (openPicker && typeof inputTanggalLahir.showPicker === 'function') {
                        try {
                            inputTanggalLahir.showPicker();
                        } catch (err) {
                            // Abaikan jika browser menolak pemanggilan showPicker terprogram
                        }
                    }
                }

                inputTanggalLahir.addEventListener('focus', function () {
                    activateDateInput(false);
                });

                inputTanggalLahir.addEventListener('click', function () {
                    activateDateInput(true);
                });

                inputTanggalLahir.addEventListener('blur', function () {
                    if (!inputTanggalLahir.value) {
                        inputTanggalLahir.type = 'text';
                    }
                });

                inputTanggalLahir.addEventListener('change', function () {
                    if (inputTanggalLahir.value) {
                        setFieldError(inputTanggalLahir, errMap.tanggalLahir, '');
                    }
                });

                if (btnPickerTanggal) {
                    btnPickerTanggal.addEventListener('click', function () {
                        inputTanggalLahir.focus();
                        activateDateInput(true);
                    });
                }
            }

            // Filter input numerik untuk NIK dan Nomor Rekening
            function bindDigitsOnly(inputEl, maxLen, errorEl) {
                if (!inputEl) return;
                inputEl.addEventListener('input', function () {
                    const digits = inputEl.value.replace(/\D/g, '').slice(0, maxLen);
                    if (inputEl.value !== digits) {
                        inputEl.value = digits;
                    }
                    setFieldError(inputEl, errorEl, '');
                });
            }

            bindDigitsOnly(inputNik, 16, errMap.nik);
            bindDigitsOnly(inputNomorRekening, 25, errMap.nomorRekening);

            if (inputNamaKtp) {
                inputNamaKtp.addEventListener('input', function () {
                    if (inputNamaKtp.value.trim() !== '') {
                        setFieldError(inputNamaKtp, errMap.namaKtp, '');
                    }
                });
            }

            if (inputNamaPemilik) {
                inputNamaPemilik.addEventListener('input', function () {
                    if (inputNamaPemilik.value.trim() !== '') {
                        setFieldError(inputNamaPemilik, errMap.namaPemilik, '');
                    }
                });
            }

            if (selectNamaBank) {
                function syncBankSelectColor() {
                    if (selectNamaBank.value === '') {
                        selectNamaBank.classList.add('text-gray-500');
                        selectNamaBank.classList.remove('text-[#272727]');
                    } else {
                        selectNamaBank.classList.remove('text-gray-500');
                        selectNamaBank.classList.add('text-[#272727]');
                    }
                }
                syncBankSelectColor();
                selectNamaBank.addEventListener('change', function () {
                    syncBankSelectColor();
                    if (selectNamaBank.value !== '') {
                        setFieldError(selectNamaBank, errMap.namaBank, '');
                    }
                });
            }

            genderRadios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    setFieldError(null, errMap.jenisKelamin, '');
                });
            });

            agreementCheckboxes.forEach(function (chk) {
                chk.addEventListener('change', function () {
                    if (agreementCheckboxes.every(function (box) { return box.checked; })) {
                        setFieldError(null, errMap.persetujuan, '');
                    }
                });
            });

            function setupDropzone(config) {
                const zone = document.getElementById(config.zoneId);
                const input = document.getElementById(config.inputId);
                const iconDefault = document.getElementById(config.iconId);
                const previewImg = document.getElementById(config.previewId);
                const titleEl = document.getElementById(config.titleId);
                const metaEl = document.getElementById(config.metaId);
                const btnRemove = document.getElementById(config.removeId);
                const errorEl = config.errorEl;

                if (!zone || !input || !previewImg || !titleEl || !metaEl || !btnRemove) {
                    return { getFile: function () { return null; } };
                }

                let selectedFile = null;
                let previewUrl = null;
                const defaultTitleHtml = titleEl.innerHTML;
                const defaultMetaText = metaEl.textContent;

                function revokePreview() {
                    if (previewUrl) {
                        URL.revokeObjectURL(previewUrl);
                        previewUrl = null;
                    }
                }

                function resetZone() {
                    revokePreview();
                    selectedFile = null;
                    input.value = '';
                    previewImg.removeAttribute('src');
                    previewImg.classList.add('hidden');
                    if (iconDefault) iconDefault.classList.remove('hidden');
                    titleEl.innerHTML = defaultTitleHtml;
                    metaEl.textContent = defaultMetaText;
                    btnRemove.classList.add('hidden');
                }

                function handleSelectedFile(file) {
                    if (!file) return;
                    const validTypes = ['image/jpeg', 'image/png'];
                    const validExt = /\.(jpe?g|png)$/i.test(file.name);
                    if (!validTypes.includes(file.type) && !validExt) {
                        resetZone();
                        setFieldError(zone, errorEl, 'Format file harus JPG, JPEG, atau PNG.');
                        showToast('Format file harus JPG, JPEG, atau PNG.', 'error');
                        return;
                    }
                    if (file.size > 2 * 1024 * 1024) {
                        resetZone();
                        setFieldError(zone, errorEl, 'Ukuran file maksimal 2 MB.');
                        showToast('Ukuran file maksimal 2 MB.', 'error');
                        return;
                    }

                    revokePreview();
                    selectedFile = file;
                    previewUrl = URL.createObjectURL(file);
                    previewImg.src = previewUrl;
                    previewImg.classList.remove('hidden');
                    if (iconDefault) iconDefault.classList.add('hidden');
                    titleEl.textContent = file.name;
                    metaEl.textContent = formatFileSize(file.size) + ' • Klik untuk ganti foto';
                    btnRemove.classList.remove('hidden');
                    setFieldError(zone, errorEl, '');
                }

                input.addEventListener('change', function () {
                    if (input.files && input.files[0]) {
                        handleSelectedFile(input.files[0]);
                    }
                });

                btnRemove.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    resetZone();
                });

                ['dragenter', 'dragover'].forEach(function (evtName) {
                    zone.addEventListener(evtName, function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        zone.classList.add('dropzone-active');
                    });
                });

                ['dragleave', 'drop'].forEach(function (evtName) {
                    zone.addEventListener(evtName, function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        zone.classList.remove('dropzone-active');
                    });
                });

                zone.addEventListener('drop', function (e) {
                    const files = e.dataTransfer && e.dataTransfer.files;
                    if (files && files[0]) {
                        handleSelectedFile(files[0]);
                    }
                });

                return {
                    getFile: function () {
                        return selectedFile;
                    },
                    zoneEl: zone,
                    inputEl: input,
                };
            }

            const ktpDropzone = setupDropzone({
                zoneId: 'dropzone-ktp',
                inputId: 'input-foto-ktp',
                iconId: 'icon-ktp-default',
                previewId: 'preview-ktp',
                titleId: 'title-ktp',
                metaId: 'meta-ktp',
                removeId: 'btn-hapus-ktp',
                errorEl: errMap.fotoKtp,
            });

            const selfieDropzone = setupDropzone({
                zoneId: 'dropzone-selfie',
                inputId: 'input-selfie',
                iconId: 'icon-selfie-default',
                previewId: 'preview-selfie',
                titleId: 'title-selfie',
                metaId: 'meta-selfie',
                removeId: 'btn-hapus-selfie',
                errorEl: errMap.selfie,
            });

            if (formVerifikasi) {
                formVerifikasi.addEventListener('submit', function (e) {
                    clearAllErrors();

                    let firstInvalidTarget = null;
                    function registerInvalid(scrollEl, focusEl) {
                        if (!firstInvalidTarget && (scrollEl || focusEl)) {
                            firstInvalidTarget = {
                                scrollEl: scrollEl || focusEl,
                                focusEl: focusEl || scrollEl,
                            };
                        }
                    }

                    const nikVal = inputNik ? inputNik.value.trim() : '';
                    if (!/^\d{16}$/.test(nikVal)) {
                        setFieldError(inputNik, errMap.nik, 'NIK harus terdiri dari tepat 16 digit angka.');
                        registerInvalid(inputNik);
                    }

                    const bankVal = selectNamaBank ? selectNamaBank.value.trim() : '';
                    if (!bankVal) {
                        setFieldError(selectNamaBank, errMap.namaBank, 'Nama bank wajib dipilih.');
                        registerInvalid(selectNamaBank);
                    }

                    const namaPemilik = inputNamaPemilik ? inputNamaPemilik.value.trim() : '';
                    if (!namaPemilik) {
                        setFieldError(inputNamaPemilik, errMap.namaPemilik, 'Nama pemilik rekening wajib diisi.');
                        registerInvalid(inputNamaPemilik);
                    }

                    const noRek = inputNomorRekening ? inputNomorRekening.value.trim() : '';
                    if (!/^\d{5,25}$/.test(noRek)) {
                        setFieldError(inputNomorRekening, errMap.nomorRekening, 'Nomor rekening minimal 5 digit angka.');
                        registerInvalid(inputNomorRekening);
                    }

                    const allAgreed = agreementCheckboxes.every(function (chk) { return chk.checked; });
                    if (!allAgreed) {
                        setFieldError(null, errMap.persetujuan, 'Harap centang seluruh poin persetujuan sebelum mengirim verifikasi.');
                        registerInvalid(agreementCheckboxes.find(function (chk) { return !chk.checked; }));
                    }

                    if (firstInvalidTarget) {
                        e.preventDefault();
                        if (firstInvalidTarget.scrollEl && typeof firstInvalidTarget.scrollEl.scrollIntoView === 'function') {
                            firstInvalidTarget.scrollEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        if (firstInvalidTarget.focusEl && typeof firstInvalidTarget.focusEl.focus === 'function') {
                            firstInvalidTarget.focusEl.focus({ preventScroll: true });
                        }
                        showToast('Lengkapi semua kolom wajib verifikasi (NIK, Rekening, dan Persetujuan).', 'error');
                        return;
                    }

                    if (btnKirim && btnKirimLabel) {
                        btnKirim.disabled = true;
                        btnKirimLabel.textContent = 'Menyimpan...';
                    }
                });
            }

            document.querySelectorAll('[data-demo-toast]').forEach(function (el) {
                el.addEventListener('click', function () {
                    const msg = el.getAttribute('data-demo-toast');
                    if (msg) showToast(msg, 'info');
                });
            });

            if (initialFlash && initialFlash.message) {
                showToast(initialFlash.message, initialFlash.type || 'error');
            }
        })();
    </script>
</body>

</html>

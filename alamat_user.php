<?php
require_once "auth.php";

// Pastikan pengguna sudah login
if (!isLoggedIn()) {
    header("Location: login.php?redirect=alamat_user.php");
    exit;
}

$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

// Sinkronkan data user terbaru dari database
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

$_SESSION['nama']        = $userRow['nama'];
$_SESSION['email']       = $userRow['email'];
$_SESSION['peran']       = $userRow['peran'];
$_SESSION['is_verified'] = (int)$userRow['is_verified'];

$currentUser = [
    'id'          => (int)$userRow['id'],
    'nama'        => (string)$userRow['nama'],
    'email'       => (string)$userRow['email'],
    'peran'       => (string)$userRow['peran'],
    'is_verified' => (int)$userRow['is_verified'],
];
$userNoHp = trim((string)($userRow['no_hp'] ?? ''));

$accountActiveMenu = 'alamat';
$accountSidebarUser = [
    'nama'  => $currentUser['nama'],
    'email' => $currentUser['email'],
];

// CSRF token untuk form alamat
if (empty($_SESSION['csrf_address'])) {
    $_SESSION['csrf_address'] = bin2hex(random_bytes(32));
}
$csrfAddress = $_SESSION['csrf_address'];

// Ambil flash message dari redirect sebelumnya jika ada
$flash = null;
if (!empty($_SESSION['address_flash']) && is_array($_SESSION['address_flash'])) {
    $flash = $_SESSION['address_flash'];
    unset($_SESSION['address_flash']);
}

// Opsi dropdown wilayah
$provinsiOptions  = ['Jawa Barat', 'DKI Jakarta', 'Banten', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Bali'];
$kotaOptions      = ['Kota Bekasi', 'Kabupaten Bekasi', 'Kota Bandung', 'Kota Depok', 'Kota Bogor', 'Jakarta Selatan', 'Jakarta Pusat', 'Jakarta Timur'];
$kecamatanOptions = ['Pondok Gede', 'Jatiasih', 'Bekasi Barat', 'Bekasi Selatan', 'Pondok Melati'];
$kelurahanOptions = ['Jatibening Baru', 'Jatibening', 'Jatimakmur', 'Jatiwaringin', 'Jatimurni'];

// Helper format nomor telepon untuk tampilan
function formatTeleponDisplay(string $telepon): string
{
    $clean = preg_replace('/\s+/', '', $telepon);
    if ($clean !== '' && str_starts_with($clean, '0') && strlen($clean) >= 9) {
        $rest = substr($clean, 1);
        return '+62 ' . substr($rest, 0, 3) . '-' . substr($rest, 3, 4) . '-' . substr($rest, 7);
    }
    return $telepon;
}

$formMode = 'tambah';
$formValues = null;
$forcedState = null;
$fieldErrors = [];

// Proses simpan / ubah alamat (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken    = (string)($_POST['csrf_token'] ?? '');
    $mode           = (string)($_POST['mode'] ?? 'tambah');
    $addressId      = (int)($_POST['address_id'] ?? 0);
    $namaPenerima   = trim((string)($_POST['nama_penerima'] ?? ''));
    $provinsi       = trim((string)($_POST['provinsi'] ?? ''));
    $kota           = trim((string)($_POST['kota'] ?? ''));
    $kecamatan      = trim((string)($_POST['kecamatan'] ?? ''));
    $kelurahan      = trim((string)($_POST['kelurahan'] ?? ''));
    $kodePos        = trim((string)($_POST['kode_pos'] ?? ''));
    $alamatLengkap  = trim((string)($_POST['alamat_lengkap'] ?? ''));
    $detailTambahan = trim((string)($_POST['detail_tambahan'] ?? ''));

    $formMode = ($mode === 'edit') ? 'edit' : 'tambah';
    $formValues = [
        'id'              => $addressId,
        'nama_penerima'   => $namaPenerima,
        'provinsi'        => $provinsi,
        'kota'            => $kota,
        'kecamatan'       => $kecamatan,
        'kelurahan'       => $kelurahan,
        'kode_pos'        => $kodePos,
        'alamat_lengkap'  => $alamatLengkap,
        'detail_tambahan' => $detailTambahan,
    ];

    if (!hash_equals($csrfAddress, $postedToken)) {
        $flash = ['type' => 'error', 'message' => 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.'];
        $forcedState = $formMode;
    } else {
        if ($namaPenerima === '') {
            $fieldErrors['nama_penerima'] = 'Nama penerima wajib diisi.';
        } elseif (mb_strlen($namaPenerima) > 100) {
            $fieldErrors['nama_penerima'] = 'Nama penerima maksimal 100 karakter.';
        }

        if ($provinsi === '') {
            $fieldErrors['provinsi'] = 'Provinsi wajib dipilih.';
        }

        if ($kota === '') {
            $fieldErrors['kota'] = 'Kota / Kabupaten wajib dipilih.';
        }

        if ($kecamatan === '') {
            $fieldErrors['kecamatan'] = 'Kecamatan wajib dipilih.';
        }

        if ($kelurahan === '') {
            $fieldErrors['kelurahan'] = 'Kelurahan wajib dipilih.';
        }

        if ($kodePos === '') {
            $fieldErrors['kode_pos'] = 'Kode pos wajib diisi.';
        } elseif (mb_strlen($kodePos) > 10) {
            $fieldErrors['kode_pos'] = 'Kode pos maksimal 10 karakter.';
        }

        if ($alamatLengkap === '') {
            $fieldErrors['alamat_lengkap'] = 'Alamat lengkap wajib diisi.';
        }

        if (!empty($fieldErrors)) {
            $flash = ['type' => 'error', 'message' => 'Periksa kembali kolom wajib alamat yang ditandai merah.'];
            $forcedState = $formMode;
        } else {
            try {
                $alamatPayload = [
                    'nama_penerima'   => $namaPenerima,
                    'provinsi'        => $provinsi,
                    'kota'            => $kota,
                    'kecamatan'       => $kecamatan,
                    'kelurahan'       => $kelurahan,
                    'kode_pos'        => $kodePos,
                    'alamat_lengkap'  => $alamatLengkap,
                    'detail_tambahan' => $detailTambahan,
                ];
                $alamatJson = json_encode($alamatPayload, JSON_UNESCAPED_UNICODE);
                if ($alamatJson === false) {
                    throw new RuntimeException('Gagal mengodekan data alamat.');
                }

                $stmtUpdate = $db->prepare("UPDATE pengguna SET alamat = ? WHERE id = ?");
                $stmtUpdate->execute([$alamatJson, $userId]);

                $_SESSION['address_flash'] = [
                    'type'    => 'success',
                    'message' => $formMode === 'edit'
                        ? 'Perubahan alamat berhasil disimpan.'
                        : 'Alamat baru berhasil ditambahkan.',
                ];
                header("Location: alamat_user.php");
                exit;
            } catch (Throwable $e) {
                $flash = ['type' => 'error', 'message' => 'Gagal menyimpan data alamat. Silakan coba lagi.'];
                $forcedState = $formMode;
            }
        }
    }
}

// Ambil alamat user dari kolom `pengguna.alamat`
$address = parseAlamatPengguna($userRow['alamat'] ?? null, [
    'nama'  => $userRow['nama'] ?? '',
    'no_hp' => $userRow['no_hp'] ?? '',
]);

$hasAddress = $address !== null;

// Pastikan opsi wilayah yang sudah tersimpan tetap ada di dropdown jika di luar daftar bawaan
if ($address) {
    if ($address['provinsi'] !== '' && !in_array($address['provinsi'], $provinsiOptions, true)) {
        $provinsiOptions[] = $address['provinsi'];
    }
    if ($address['kota'] !== '' && !in_array($address['kota'], $kotaOptions, true)) {
        $kotaOptions[] = $address['kota'];
    }
    if ($address['kecamatan'] !== '' && !in_array($address['kecamatan'], $kecamatanOptions, true)) {
        $kecamatanOptions[] = $address['kecamatan'];
    }
    if ($address['kelurahan'] !== '' && !in_array($address['kelurahan'], $kelurahanOptions, true)) {
        $kelurahanOptions[] = $address['kelurahan'];
    }
}

// Tentukan state awal tampilan
if ($forcedState !== null) {
    $initialState = $forcedState;
} elseif (isset($_GET['state']) && in_array($_GET['state'], ['empty', 'view', 'tambah', 'edit'], true)) {
    $requestedState = $_GET['state'];
    if (!$hasAddress && ($requestedState === 'view' || $requestedState === 'edit')) {
        $initialState = 'empty';
    } else {
        $initialState = $requestedState;
    }
} else {
    $initialState = $hasAddress ? 'view' : 'empty';
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Alamat Saya | Loot-E</title>

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
        select:focus-visible,
        textarea:focus-visible {
            outline: 2px solid var(--brand-yellow);
            outline-offset: 2px;
        }

        .field-error {
            border-color: #DC2626 !important;
            background-color: #FEF2F2 !important;
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

            <!-- Area Konten Utama Alamat -->
            <section class="flex-1 w-full min-w-0">

                <!-- Header Judul Halaman -->
                <div>
                    <h1 class="text-2xl sm:text-[26px] font-extrabold text-[#272727] tracking-tight leading-tight">
                        Alamat
                    </h1>
                    <p class="text-xs sm:text-[13px] text-gray-500 mt-1">
                        Kelola alamat yang digunakan untuk pembelian dan pengiriman Loot-E
                    </p>
                </div>

                <!-- ============================================================== -->
                <!-- STATE 1: BELUM ADA ALAMAT (Sesuai PDF Page 1)                   -->
                <!-- ============================================================== -->
                <div id="section-empty" class="mt-5 hidden">
                    <div class="bg-white border border-gray-200/80 p-8 sm:p-14 lg:p-16 text-center rounded-[2px] shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                        <!-- Ilustrasi Map Pin Lokasi -->
                        <div class="w-32 h-24 mx-auto flex items-center justify-center relative">
                            <svg class="w-28 h-24 drop-shadow-xs" viewBox="0 0 140 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <!-- Bayangan Tanah / Peta -->
                                <ellipse cx="70" cy="85" rx="50" ry="10" fill="#EAEAEA" />
                                <!-- Kertas Peta Terlipat Tiga -->
                                <polygon points="30,30 55,20 55,75 30,85" fill="#E8EDF2" />
                                <polygon points="55,20 85,32 85,87 55,75" fill="#F8FAFC" />
                                <polygon points="85,32 110,22 110,77 85,87" fill="#E2E8F0" />
                                <!-- Garis Rute Halus -->
                                <path d="M40,55 Q55,45 70,60 T100,50" stroke="#CBD5E1" stroke-width="2.5" stroke-dasharray="4 3" stroke-linecap="round" fill="none" />
                                <!-- Bayangan Pin -->
                                <ellipse cx="70" cy="74" rx="8" ry="3.5" fill="#CBD5E1" />
                                <!-- Pin Lokasi Kuning Khas Loot-E -->
                                <path d="M70,30 C60,30 52,38 52,48 C52,61 70,74 70,74 C70,74 88,61 88,48 C88,38 80,30 70,30 Z" fill="#FFC914" />
                                <!-- Titik Putih Tengah Pin -->
                                <circle cx="70" cy="47" r="6" fill="#FFFFFF" />
                            </svg>
                        </div>

                        <h2 class="text-base sm:text-lg font-bold text-[#272727] mt-3">
                            Belum Ada Alamat
                        </h2>
                        <p class="text-xs text-gray-500 mt-2 max-w-md mx-auto leading-relaxed">
                            Kamu belum menambahkan alamat ke akun Loot-E. Tambahkan alamat untuk mempermudah proses checkout dan pengiriman.
                        </p>

                        <button type="button" id="btn-empty-tambah"
                            class="inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-6 py-2.5 rounded-[2px] transition mt-6 cursor-pointer shadow-xs">
                            <i class="ph ph-plus font-bold text-xs" aria-hidden="true"></i>
                            <span>Tambah Alamat</span>
                        </button>
                    </div>

                    <!-- Kotak Informasi Tentang Alamat -->
                    <div class="mt-6 bg-[#FFFDF5] border border-amber-200/70 p-4 sm:p-5 rounded-[2px] flex items-start gap-3.5">
                        <div class="w-6 h-6 rounded-full border border-amber-400/80 flex items-center justify-center shrink-0 mt-0.5 text-amber-600">
                            <i class="ph ph-info text-sm font-bold" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="text-xs sm:text-[13px] font-bold text-[#272727]">
                                Tentang Alamat
                            </h3>
                            <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                                Alamat akan digunakan sebagai alamat penerima saat kamu membeli barang dan sebagai alamat asal ketika kamu menjual barang.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- STATE 2: ADA ALAMAT (Sesuai PDF Page 2)                         -->
                <!-- ============================================================== -->
                <div id="section-view" class="mt-5">
                    <div class="bg-white border border-gray-200/80 p-6 sm:p-8 rounded-[2px] shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                        <!-- Baris Judul Alamat Utama & Tombol Edit -->
                        <div class="flex items-center justify-between gap-4 pb-4 border-b border-gray-100">
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-sm sm:text-base font-bold text-[#272727]">
                                    Alamat Utama
                                </h2>
                                <span class="bg-brand-yellow text-[#272727] text-[10px] font-bold px-2 py-0.5 rounded-[2px] tracking-wide">
                                    Utama
                                </span>
                            </div>

                            <button type="button" id="btn-view-edit"
                                class="inline-flex items-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs px-3.5 py-1.5 sm:px-4 sm:py-2 rounded-[2px] transition cursor-pointer shadow-xs">
                                <i class="ph ph-pencil-simple text-xs" aria-hidden="true"></i>
                                <span>Edit Alamat</span>
                            </button>
                        </div>

                        <!-- Rincian Alamat & Ikon Rumah -->
                        <div class="pt-5 flex items-start gap-4 sm:gap-5">
                            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-[4px] bg-[#FFF8E1] border border-amber-200/60 flex items-center justify-center shrink-0">
                                <i class="ph ph-house text-xl text-[#B88A00]" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 id="display-nama" class="text-sm font-bold text-[#272727] leading-snug">
                                    <?= htmlspecialchars((string)($address['nama_penerima'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                                <p id="display-telepon" class="text-xs text-gray-500 mt-1">
                                    <?php if ($userNoHp !== ''): ?>
                                        <?= htmlspecialchars(formatTeleponDisplay($userNoHp), ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        Nomor HP belum diatur. <a href="profile.php" class="text-[#272727] font-semibold underline hover:text-amber-600 transition">Lengkapi di Profil</a>
                                    <?php endif; ?>
                                </p>
                                <div id="display-alamat-full" class="text-xs text-gray-600 leading-relaxed mt-2.5 space-y-0.5">
                                    <p><?= htmlspecialchars((string)($address['alamat_lengkap'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if (!empty($address['kelurahan']) || !empty($address['kecamatan'])): ?>
                                        <p>Kel. <?= htmlspecialchars((string)($address['kelurahan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, Kec. <?= htmlspecialchars((string)($address['kecamatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($address['kota']) || !empty($address['provinsi']) || !empty($address['kode_pos'])): ?>
                                        <p><?= htmlspecialchars((string)($address['kota'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars((string)($address['provinsi'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string)($address['kode_pos'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Baris Keterangan Penggunaan Alamat -->
                        <div class="mt-6 pt-5 border-t border-gray-100 flex items-start gap-2.5">
                            <i class="ph ph-stack text-sm text-gray-500 mt-0.5 shrink-0" aria-hidden="true"></i>
                            <div>
                                <p class="text-xs font-bold text-[#272727]">
                                    Digunakan untuk:
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
                                    Alamat penerima saat checkout dan alamat asal pengiriman toko.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Kotak Informasi Tentang Alamat -->
                    <div class="mt-6 bg-[#FFFDF5] border border-amber-200/70 p-4 sm:p-5 rounded-[2px] flex items-start gap-3.5">
                        <div class="w-6 h-6 rounded-full border border-amber-400/80 flex items-center justify-center shrink-0 mt-0.5 text-amber-600">
                            <i class="ph ph-info text-sm font-bold" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="text-xs sm:text-[13px] font-bold text-[#272727]">
                                Tentang Alamat
                            </h3>
                            <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                                Alamat akan digunakan sebagai alamat penerima saat kamu membeli barang dan sebagai alamat asal ketika kamu menjual barang.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- STATE 3: FORM TAMBAH & EDIT ALAMAT (Sesuai PDF Page 3)           -->
                <!-- ============================================================== -->
                <div id="section-form" class="mt-5 hidden">
                    <div class="bg-white border border-gray-200/80 p-6 sm:p-8 rounded-[2px] shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                        <!-- Judul Form (Dinamis: Tambah Alamat / Edit Alamat) -->
                        <div class="pb-5 border-b border-gray-100">
                            <h2 id="form-heading-title" class="text-base sm:text-lg font-bold text-[#272727]">
                                Tambah Alamat
                            </h2>
                            <p id="form-heading-desc" class="text-xs text-gray-500 mt-1">
                                Lengkapi informasi alamat baru yang akan digunakan.
                            </p>
                        </div>

                        <!-- Form Isian Alamat -->
                        <form id="form-alamat" method="POST" action="alamat_user.php" novalidate class="mt-6 space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfAddress, ENT_QUOTES, 'UTF-8') ?>" />
                            <input type="hidden" id="form-mode" name="mode" value="<?= htmlspecialchars($formMode, ENT_QUOTES, 'UTF-8') ?>" />
                            <input type="hidden" id="form-address-id" name="address_id" value="<?= (int)($address['id'] ?? 0) ?>" />

                            <!-- 1. Nama Penerima -->
                            <div>
                                <label for="input-nama-penerima" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                    Nama Penerima
                                </label>
                                <input type="text" id="input-nama-penerima" name="nama_penerima" required
                                    <?= isset($fieldErrors['nama_penerima']) ? 'aria-invalid="true" ' : '' ?>placeholder="Masukkan nama penerima"
                                    class="w-full bg-white border border-gray-300 rounded-[2px] px-3.5 py-2.5 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow transition<?= isset($fieldErrors['nama_penerima']) ? ' field-error' : '' ?>" />
                                <p id="err-nama-penerima" class="<?= isset($fieldErrors['nama_penerima']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['nama_penerima'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>

                            <!-- 2. Provinsi & Kota / Kabupaten (2 Kolom) -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="select-provinsi" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                        Provinsi
                                    </label>
                                    <div class="relative">
                                        <select id="select-provinsi" name="provinsi" required
                                            <?= isset($fieldErrors['provinsi']) ? 'aria-invalid="true" ' : '' ?>class="w-full bg-white border border-gray-300 rounded-[2px] px-3.5 py-2.5 text-xs text-gray-800 appearance-none focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow cursor-pointer transition<?= isset($fieldErrors['provinsi']) ? ' field-error' : '' ?>">
                                            <option value="" disabled selected>Pilih provinsi</option>
                                            <?php foreach ($provinsiOptions as $prov): ?>
                                                <option value="<?= htmlspecialchars($prov, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($prov, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="ph ph-caret-down text-gray-400 text-xs absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
                                    </div>
                                    <p id="err-provinsi" class="<?= isset($fieldErrors['provinsi']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['provinsi'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>

                                <div>
                                    <label for="select-kota" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                        Kota / Kabupaten
                                    </label>
                                    <div class="relative">
                                        <select id="select-kota" name="kota" required
                                            <?= isset($fieldErrors['kota']) ? 'aria-invalid="true" ' : '' ?>class="w-full bg-white border border-gray-300 rounded-[2px] px-3.5 py-2.5 text-xs text-gray-800 appearance-none focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow cursor-pointer transition<?= isset($fieldErrors['kota']) ? ' field-error' : '' ?>">
                                            <option value="" disabled selected>Pilih kota / kabupaten</option>
                                            <?php foreach ($kotaOptions as $kt): ?>
                                                <option value="<?= htmlspecialchars($kt, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($kt, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="ph ph-caret-down text-gray-400 text-xs absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
                                    </div>
                                    <p id="err-kota" class="<?= isset($fieldErrors['kota']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['kota'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>

                            <!-- 4. Kecamatan & Kelurahan (2 Kolom) -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="select-kecamatan" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                        Kecamatan
                                    </label>
                                    <div class="relative">
                                        <select id="select-kecamatan" name="kecamatan" required
                                            <?= isset($fieldErrors['kecamatan']) ? 'aria-invalid="true" ' : '' ?>class="w-full bg-white border border-gray-300 rounded-[2px] px-3.5 py-2.5 text-xs text-gray-800 appearance-none focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow cursor-pointer transition<?= isset($fieldErrors['kecamatan']) ? ' field-error' : '' ?>">
                                            <option value="" disabled selected>Pilih kecamatan</option>
                                            <?php foreach ($kecamatanOptions as $kec): ?>
                                                <option value="<?= htmlspecialchars($kec, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($kec, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="ph ph-caret-down text-gray-400 text-xs absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
                                    </div>
                                    <p id="err-kecamatan" class="<?= isset($fieldErrors['kecamatan']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['kecamatan'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>

                                <div>
                                    <label for="select-kelurahan" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                        Kelurahan
                                    </label>
                                    <div class="relative">
                                        <select id="select-kelurahan" name="kelurahan" required
                                            <?= isset($fieldErrors['kelurahan']) ? 'aria-invalid="true" ' : '' ?>class="w-full bg-white border border-gray-300 rounded-[2px] px-3.5 py-2.5 text-xs text-gray-800 appearance-none focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow cursor-pointer transition<?= isset($fieldErrors['kelurahan']) ? ' field-error' : '' ?>">
                                            <option value="" disabled selected>Pilih kelurahan</option>
                                            <?php foreach ($kelurahanOptions as $kel): ?>
                                                <option value="<?= htmlspecialchars($kel, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($kel, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="ph ph-caret-down text-gray-400 text-xs absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>
                                    </div>
                                    <p id="err-kelurahan" class="<?= isset($fieldErrors['kelurahan']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['kelurahan'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>

                            <!-- 5. Kode Pos -->
                            <div>
                                <label for="input-kode-pos" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                    Kode Pos
                                </label>
                                <input type="text" id="input-kode-pos" name="kode_pos" required maxlength="10"
                                    <?= isset($fieldErrors['kode_pos']) ? 'aria-invalid="true" ' : '' ?>placeholder="Contoh: 40123"
                                    class="w-full bg-white border border-gray-300 rounded-[2px] px-3.5 py-2.5 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow transition<?= isset($fieldErrors['kode_pos']) ? ' field-error' : '' ?>" />
                                <p id="err-kode-pos" class="<?= isset($fieldErrors['kode_pos']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['kode_pos'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>

                            <!-- 6. Alamat Lengkap -->
                            <div>
                                <label for="input-alamat-lengkap" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                    Alamat Lengkap
                                </label>
                                <textarea id="input-alamat-lengkap" name="alamat_lengkap" rows="2" required
                                    <?= isset($fieldErrors['alamat_lengkap']) ? 'aria-invalid="true" ' : '' ?>placeholder="Masukkan alamat lengkap (nama jalan, nomor rumah, RT/RW, dll)"
                                    class="w-full bg-white border border-gray-300 rounded-[2px] px-3.5 py-2.5 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow transition resize-y<?= isset($fieldErrors['alamat_lengkap']) ? ' field-error' : '' ?>"></textarea>
                                <p id="err-alamat-lengkap" class="<?= isset($fieldErrors['alamat_lengkap']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600"><?= htmlspecialchars($fieldErrors['alamat_lengkap'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>

                            <!-- 7. Detail Tambahan / Patokan (Opsional) -->
                            <div>
                                <label for="input-detail-tambahan" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                    Detail Tambahan / Patokan <span class="text-gray-400 font-normal">(Opsional)</span>
                                </label>
                                <input type="text" id="input-detail-tambahan" name="detail_tambahan"
                                    placeholder="Contoh: Dekat Indomaret, samping masjid, rumah pagar hitam, dll"
                                    class="w-full bg-white border border-gray-300 rounded-[2px] px-3.5 py-2.5 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow transition" />
                            </div>

                            <!-- Tombol Aksi: Batal & Simpan Alamat / Simpan Perubahan -->
                            <div class="pt-4 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 sm:gap-3">
                                <button type="button" id="btn-form-batal"
                                    class="px-5 py-2.5 rounded-[2px] border border-gray-300 text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer text-center">
                                    Batal
                                </button>
                                <button type="submit" id="btn-form-submit"
                                    class="px-6 py-2.5 rounded-[2px] bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold transition cursor-pointer text-center shadow-xs">
                                    <span id="btn-submit-label">Simpan Alamat</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Informasi Catatan Tambahan (Sesuai PDF Page 3) -->
                    <div class="mt-6 bg-[#FFFDF5] border border-amber-200/70 px-4 py-3 rounded-[2px] flex items-center gap-2.5 text-xs text-gray-600">
                        <div class="w-4 h-4 rounded-full border border-amber-400 flex items-center justify-center shrink-0 text-amber-600 text-[10px] font-bold">
                            i
                        </div>
                        <p class="leading-relaxed">
                            Alamat ini akan digunakan untuk keperluan pembelian dan pengiriman barang di Loot-E.
                        </p>
                    </div>
                </div>

            </section>
        </div>
    </main>

    <!-- Toast Notifikasi (Standar Loot-E: fixed bottom-6 left-6 dengan aksen border-l-4) -->
    <div id="alamat-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg max-w-sm">
    </div>

    <!-- Footer Standar Loot-E -->
    <?php require_once 'components/footer.php'; ?>

    <!-- Script Interaktivitas UI & Manajemen State (Empty, View, Tambah/Edit) -->
    <script>
        (function() {
            // Kontainer 3 State
            const sectionEmpty = document.getElementById('section-empty');
            const sectionView = document.getElementById('section-view');
            const sectionForm = document.getElementById('section-form');

            // Tombol Aksi di dalam Halaman
            const btnEmptyTambah = document.getElementById('btn-empty-tambah');
            const btnViewEdit = document.getElementById('btn-view-edit');
            const btnFormBatal = document.getElementById('btn-form-batal');
            const formAlamat = document.getElementById('form-alamat');
            const formModeInput = document.getElementById('form-mode');
            const formAddressId = document.getElementById('form-address-id');
            const formHeadingTitle = document.getElementById('form-heading-title');
            const formHeadingDesc = document.getElementById('form-heading-desc');
            const btnSubmitLabel = document.getElementById('btn-submit-label');

            // Elemen Input Form
            const inputNama = document.getElementById('input-nama-penerima');
            const selectProv = document.getElementById('select-provinsi');
            const selectKota = document.getElementById('select-kota');
            const selectKec = document.getElementById('select-kecamatan');
            const selectKel = document.getElementById('select-kelurahan');
            const inputKodePos = document.getElementById('input-kode-pos');
            const inputAlamat = document.getElementById('input-alamat-lengkap');
            const inputDetail = document.getElementById('input-detail-tambahan');

            // Data Alamat dari Database
            const currentAddress = <?= json_encode($address, JSON_UNESCAPED_UNICODE) ?>;
            const postedValues = <?= json_encode($formValues, JSON_UNESCAPED_UNICODE) ?>;
            const hasAddress = <?= $hasAddress ? 'true' : 'false' ?>;
            const initialFlash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;

            const errMap = {
                namaPenerima: document.getElementById('err-nama-penerima'),
                provinsi: document.getElementById('err-provinsi'),
                kota: document.getElementById('err-kota'),
                kecamatan: document.getElementById('err-kecamatan'),
                kelurahan: document.getElementById('err-kelurahan'),
                kodePos: document.getElementById('err-kode-pos'),
                alamatLengkap: document.getElementById('err-alamat-lengkap'),
            };

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

            function clearAllErrors() {
                setFieldError(inputNama, errMap.namaPenerima, '');
                setFieldError(selectProv, errMap.provinsi, '');
                setFieldError(selectKota, errMap.kota, '');
                setFieldError(selectKec, errMap.kecamatan, '');
                setFieldError(selectKel, errMap.kelurahan, '');
                setFieldError(inputKodePos, errMap.kodePos, '');
                setFieldError(inputAlamat, errMap.alamatLengkap, '');
            }

            // Toast Notifikasi
            const toast = document.getElementById('alamat-toast');
            let toastTimer = null;

            function showToast(message, type) {
                if (!toast || !message) return;
                toast.textContent = message;
                toast.classList.remove('hidden', 'border-brand-yellow', 'border-red-500');
                toast.classList.add(type === 'error' ? 'border-red-500' : 'border-brand-yellow');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function() {
                    toast.classList.add('hidden');
                }, 3500);
            }

            // Fungsi Transisi State Tampilan
            function switchState(state, usePosted) {
                if (!usePosted) {
                    clearAllErrors();
                }

                sectionEmpty.classList.add('hidden');
                sectionView.classList.add('hidden');
                sectionForm.classList.add('hidden');

                if (state === 'empty') {
                    sectionEmpty.classList.remove('hidden');
                } else if (state === 'view') {
                    if (!hasAddress) {
                        sectionEmpty.classList.remove('hidden');
                    } else {
                        sectionView.classList.remove('hidden');
                    }
                } else if (state === 'tambah') {
                    formModeInput.value = 'tambah';
                    if (formAddressId) formAddressId.value = '0';
                    formHeadingTitle.textContent = 'Tambah Alamat';
                    formHeadingDesc.textContent = 'Lengkapi informasi alamat baru yang akan digunakan.';
                    btnSubmitLabel.textContent = 'Simpan Alamat';

                    const src = usePosted && postedValues ? postedValues : null;
                    if (src) {
                        inputNama.value = src.nama_penerima || '';
                        selectProv.value = src.provinsi || '';
                        selectKota.value = src.kota || '';
                        selectKec.value = src.kecamatan || '';
                        selectKel.value = src.kelurahan || '';
                        inputKodePos.value = src.kode_pos || '';
                        inputAlamat.value = src.alamat_lengkap || '';
                        inputDetail.value = src.detail_tambahan || '';
                    } else {
                        formAlamat.reset();
                        selectProv.value = '';
                        selectKota.value = '';
                        selectKec.value = '';
                        selectKel.value = '';
                    }

                    sectionForm.classList.remove('hidden');
                    const firstErrorEl = usePosted ? sectionForm.querySelector('.field-error') : null;
                    if (firstErrorEl && typeof firstErrorEl.focus === 'function') {
                        firstErrorEl.focus();
                    } else {
                        inputNama.focus();
                    }
                } else if (state === 'edit') {
                    if (!hasAddress && !(usePosted && postedValues)) {
                        switchState('tambah', false);
                        return;
                    }

                    formModeInput.value = 'edit';
                    formHeadingTitle.textContent = 'Edit Alamat';
                    formHeadingDesc.textContent = 'Perbarui informasi alamat yang akan digunakan.';
                    btnSubmitLabel.textContent = 'Simpan Perubahan';

                    const isUsingPosted = Boolean(usePosted && postedValues);
                    const src = isUsingPosted ? postedValues : currentAddress;
                    if (src) {
                        if (formAddressId) formAddressId.value = src.id || (currentAddress ? currentAddress.id : 0);
                        inputNama.value = src.nama_penerima || '';
                        selectProv.value = src.provinsi || '';
                        selectKota.value = src.kota || '';
                        selectKec.value = src.kecamatan || '';
                        selectKel.value = src.kelurahan || '';
                        inputKodePos.value = src.kode_pos || '';
                        inputAlamat.value = src.alamat_lengkap || '';
                        inputDetail.value = src.detail_tambahan || '';
                    }

                    sectionForm.classList.remove('hidden');
                    const firstErrorEl = usePosted ? sectionForm.querySelector('.field-error') : null;
                    if (firstErrorEl && typeof firstErrorEl.focus === 'function') {
                        firstErrorEl.focus();
                    } else {
                        inputNama.focus();
                    }
                }
            }

            // Event Listener Aksi dalam Halaman
            if (btnEmptyTambah) btnEmptyTambah.addEventListener('click', () => switchState('tambah', false));
            if (btnViewEdit) btnViewEdit.addEventListener('click', () => switchState('edit', false));
            if (btnFormBatal) btnFormBatal.addEventListener('click', () => {
                switchState(hasAddress ? 'view' : 'empty', false);
            });

            // Hapus error inline saat user mulai memperbaiki input
            [
                { el: inputNama, err: errMap.namaPenerima, evt: 'input' },
                { el: selectProv, err: errMap.provinsi, evt: 'change' },
                { el: selectKota, err: errMap.kota, evt: 'change' },
                { el: selectKec, err: errMap.kecamatan, evt: 'change' },
                { el: selectKel, err: errMap.kelurahan, evt: 'change' },
                { el: inputKodePos, err: errMap.kodePos, evt: 'input' },
                { el: inputAlamat, err: errMap.alamatLengkap, evt: 'input' },
            ].forEach(function(item) {
                if (!item.el) return;
                item.el.addEventListener(item.evt, function() {
                    if (item.el.value.trim() !== '') {
                        setFieldError(item.el, item.err, '');
                    }
                });
            });

            // Validasi Client-side sebelum Submit ke Backend
            if (formAlamat) {
                formAlamat.addEventListener('submit', function(e) {
                    clearAllErrors();

                    let firstInvalidTarget = null;
                    function registerInvalid(el) {
                        if (!firstInvalidTarget && el) {
                            firstInvalidTarget = el;
                        }
                    }

                    const namaVal = inputNama.value.trim();
                    if (!namaVal) {
                        setFieldError(inputNama, errMap.namaPenerima, 'Nama penerima wajib diisi.');
                        registerInvalid(inputNama);
                    } else if (namaVal.length > 100) {
                        setFieldError(inputNama, errMap.namaPenerima, 'Nama penerima maksimal 100 karakter.');
                        registerInvalid(inputNama);
                    }

                    if (!selectProv.value) {
                        setFieldError(selectProv, errMap.provinsi, 'Provinsi wajib dipilih.');
                        registerInvalid(selectProv);
                    }

                    if (!selectKota.value) {
                        setFieldError(selectKota, errMap.kota, 'Kota / Kabupaten wajib dipilih.');
                        registerInvalid(selectKota);
                    }

                    if (!selectKec.value) {
                        setFieldError(selectKec, errMap.kecamatan, 'Kecamatan wajib dipilih.');
                        registerInvalid(selectKec);
                    }

                    if (!selectKel.value) {
                        setFieldError(selectKel, errMap.kelurahan, 'Kelurahan wajib dipilih.');
                        registerInvalid(selectKel);
                    }

                    const kodePosVal = inputKodePos.value.trim();
                    if (!kodePosVal) {
                        setFieldError(inputKodePos, errMap.kodePos, 'Kode pos wajib diisi.');
                        registerInvalid(inputKodePos);
                    } else if (kodePosVal.length > 10) {
                        setFieldError(inputKodePos, errMap.kodePos, 'Kode pos maksimal 10 karakter.');
                        registerInvalid(inputKodePos);
                    }

                    const alamatVal = inputAlamat.value.trim();
                    if (!alamatVal) {
                        setFieldError(inputAlamat, errMap.alamatLengkap, 'Alamat lengkap wajib diisi.');
                        registerInvalid(inputAlamat);
                    }

                    if (firstInvalidTarget) {
                        e.preventDefault();
                        if (typeof firstInvalidTarget.scrollIntoView === 'function') {
                            firstInvalidTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        if (typeof firstInvalidTarget.focus === 'function') {
                            firstInvalidTarget.focus({ preventScroll: true });
                        }
                        showToast('Periksa kembali kolom wajib alamat yang ditandai merah.', 'error');
                    }
                });
            }

            // Inisialisasi state awal & flash message
            const initialParamState = '<?= htmlspecialchars($initialState, ENT_QUOTES, 'UTF-8') ?>';
            switchState(initialParamState, Boolean(postedValues));

            if (initialFlash && initialFlash.message) {
                showToast(initialFlash.message, initialFlash.type || 'info');
            }
        })();
    </script>
</body>

</html>
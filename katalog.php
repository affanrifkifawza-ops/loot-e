<?php
// katalog.php - Manajemen Katalog Produk Seller (Terintegrasi Database MySQL PDO)
require_once "auth.php";

if (!isLoggedIn()) {
    header("Location: login.php?redirect=katalog.php");
    exit;
}

$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

$stmtUser = $db->prepare("
    SELECT id, nama, email, peran, is_verified
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

// Sinkronkan session dengan data terbaru dari database (BR-04)
$_SESSION['nama']        = $userRow['nama'];
$_SESSION['email']       = $userRow['email'];
$_SESSION['peran']       = $userRow['peran'];
$_SESSION['is_verified'] = (int)$userRow['is_verified'];

// Guard BR-04: hanya seller terverifikasi yang boleh mengakses & mengelola katalog toko
if ((int)$userRow['is_verified'] !== 1) {
    $_SESSION['verifikasi_flash'] = [
        'type'    => 'error',
        'message' => 'Selesaikan verifikasi akun terlebih dahulu untuk mengakses Katalog Toko.',
    ];
    header("Location: verifikasi_akun.php");
    exit;
}

// CSRF token untuk aksi POST (Nonaktifkan / Aktifkan / Hapus)
if (empty($_SESSION['csrf_katalog'])) {
    $_SESSION['csrf_katalog'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_katalog'];

$flash = null;
if (!empty($_SESSION['katalog_flash']) && is_array($_SESSION['katalog_flash'])) {
    $flash = $_SESSION['katalog_flash'];
    unset($_SESSION['katalog_flash']);
}

$validTabs = ['semua', 'tersedia', 'draft', 'habis', 'nonaktif'];
$initialTab = in_array($_GET['tab'] ?? '', $validTabs, true) ? (string)$_GET['tab'] : 'semua';

function formatTanggalKatalog(?string $datetime): string
{
    if ($datetime === null || trim($datetime) === '') {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '-';
    }
    static $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];
    return date('j', $ts) . ' ' . ($bulan[(int)date('n', $ts)] ?? date('M', $ts)) . ' ' . date('Y', $ts);
}

// Proses aksi POST: Nonaktifkan, Aktifkan, Hapus Produk
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $action      = (string)($_POST['action'] ?? '');
    $produkId    = (int)($_POST['produk_id'] ?? 0);
    $returnTab   = in_array($_POST['return_tab'] ?? '', $validTabs, true) ? (string)$_POST['return_tab'] : 'semua';
    $redirectUrl = $returnTab !== 'semua' ? 'katalog.php?tab=' . urlencode($returnTab) : 'katalog.php';

    if (!hash_equals($csrfToken, $postedToken)) {
        $_SESSION['katalog_flash'] = [
            'type'    => 'error',
            'message' => 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.',
        ];
        header("Location: " . $redirectUrl);
        exit;
    }

    if ($produkId <= 0 || !in_array($action, ['nonaktifkan', 'aktifkan', 'hapus'], true)) {
        $_SESSION['katalog_flash'] = [
            'type'    => 'error',
            'message' => 'Permintaan aksi produk tidak valid.',
        ];
        header("Location: " . $redirectUrl);
        exit;
    }

    try {
        $db->beginTransaction();

        // Kunci baris produk milik seller (ownership guard)
        $stmtProd = $db->prepare("
            SELECT id, nama_produk, foto, stok, status_produk
            FROM produk
            WHERE id = ? AND id_penjual = ?
            FOR UPDATE
        ");
        $stmtProd->execute([$produkId, $userId]);
        $prod = $stmtProd->fetch();

        if (!$prod) {
            $db->rollBack();
            $_SESSION['katalog_flash'] = [
                'type'    => 'error',
                'message' => 'Produk tidak ditemukan atau bukan milik akun Anda.',
            ];
            header("Location: " . $redirectUrl);
            exit;
        }

        $namaProduk = (string)$prod['nama_produk'];

        if ($action === 'nonaktifkan') {
            if ($prod['status_produk'] !== 'tersedia') {
                $db->rollBack();
                $_SESSION['katalog_flash'] = [
                    'type'    => 'error',
                    'message' => 'Hanya produk yang sedang tayang yang dapat dinonaktifkan.',
                ];
            } else {
                $stmtUpd = $db->prepare("
                    UPDATE produk
                    SET status_produk = 'nonaktif'
                    WHERE id = ? AND id_penjual = ?
                ");
                $stmtUpd->execute([$produkId, $userId]);

                // BR-03 (ADR-0002): tutup otomatis semua negosiasi & barter aktif pada produk ini
                $stmtFailNego = $db->prepare("
                    UPDATE negosiasi
                    SET status = 'gagal'
                    WHERE id_produk = ? AND status IN ('pending', 'counter_offer')
                ");
                $stmtFailNego->execute([$produkId]);
                $closedNego = $stmtFailNego->rowCount();

                $stmtFailBarter = $db->prepare("
                    UPDATE barter
                    SET status = 'gagal'
                    WHERE (id_produk_diincar = ? OR id_produk_ditawarkan = ?)
                      AND status IN ('pending', 'counter_offer')
                ");
                $stmtFailBarter->execute([$produkId, $produkId]);
                $closedBarter = $stmtFailBarter->rowCount();

                $db->commit();

                $closedTotal = $closedNego + $closedBarter;
                $extraInfo = $closedTotal > 0 ? " ({$closedTotal} penawaran aktif otomatis ditutup)" : '';
                $_SESSION['katalog_flash'] = [
                    'type'    => 'success',
                    'message' => "Produk \"{$namaProduk}\" berhasil dinonaktifkan dan tidak tampil di etalase{$extraInfo}.",
                ];
            }
        } elseif ($action === 'aktifkan') {
            if ($prod['status_produk'] !== 'nonaktif') {
                $db->rollBack();
                $_SESSION['katalog_flash'] = [
                    'type'    => 'error',
                    'message' => 'Hanya produk berstatus nonaktif yang dapat diaktifkan kembali.',
                ];
            } elseif ((int)$prod['stok'] < 1) {
                $db->rollBack();
                $_SESSION['katalog_flash'] = [
                    'type'    => 'error',
                    'message' => "Produk \"{$namaProduk}\" tidak bisa diaktifkan karena stok 0. Tambahkan stok terlebih dahulu.",
                ];
            } else {
                $stmtUpd = $db->prepare("
                    UPDATE produk
                    SET status_produk = 'tersedia'
                    WHERE id = ? AND id_penjual = ?
                ");
                $stmtUpd->execute([$produkId, $userId]);
                $db->commit();

                $_SESSION['katalog_flash'] = [
                    'type'    => 'success',
                    'message' => "Produk \"{$namaProduk}\" berhasil diaktifkan dan kembali tayang di etalase.",
                ];
            }
        } elseif ($action === 'hapus') {
            // Cek apakah ada negosiasi atau barter yang sedang berlangsung
            $stmtActiveNego = $db->prepare("
                SELECT COUNT(*)
                FROM negosiasi
                WHERE id_produk = ? AND status IN ('pending', 'counter_offer')
            ");
            $stmtActiveNego->execute([$produkId]);
            $activeNegoCount = (int)$stmtActiveNego->fetchColumn();

            $stmtActiveBarter = $db->prepare("
                SELECT COUNT(*)
                FROM barter
                WHERE (id_produk_diincar = ? OR id_produk_ditawarkan = ?)
                  AND status IN ('pending', 'counter_offer')
            ");
            $stmtActiveBarter->execute([$produkId, $produkId]);
            $activeBarterCount = (int)$stmtActiveBarter->fetchColumn();

            if ($activeNegoCount > 0 || $activeBarterCount > 0) {
                $db->rollBack();
                $_SESSION['katalog_flash'] = [
                    'type'    => 'error',
                    'message' => "Produk \"{$namaProduk}\" tidak dapat dihapus karena masih ada negosiasi atau barter yang sedang berlangsung. Selesaikan proses tersebut terlebih dahulu.",
                ];
            } else {
                // Cek riwayat transaksi permanen (pesanan, ulasan, atau kesepakatan diterima) agar tidak melanggar integritas data
                $stmtOrderRef = $db->prepare("SELECT COUNT(*) FROM detail_pesanan WHERE id_produk = ?");
                $stmtOrderRef->execute([$produkId]);
                $hasOrders = (int)$stmtOrderRef->fetchColumn() > 0;

                $stmtReviewRef = $db->prepare("SELECT COUNT(*) FROM ulasan WHERE id_produk = ?");
                $stmtReviewRef->execute([$produkId]);
                $hasReviews = (int)$stmtReviewRef->fetchColumn() > 0;

                $stmtDealNego = $db->prepare("SELECT COUNT(*) FROM negosiasi WHERE id_produk = ? AND status = 'diterima'");
                $stmtDealNego->execute([$produkId]);
                $hasDealNego = (int)$stmtDealNego->fetchColumn() > 0;

                $stmtDealBarter = $db->prepare("SELECT COUNT(*) FROM barter WHERE (id_produk_diincar = ? OR id_produk_ditawarkan = ?) AND status = 'diterima'");
                $stmtDealBarter->execute([$produkId, $produkId]);
                $hasDealBarter = (int)$stmtDealBarter->fetchColumn() > 0;

                if ($hasOrders || $hasReviews || $hasDealNego || $hasDealBarter) {
                    $db->rollBack();
                    $_SESSION['katalog_flash'] = [
                        'type'    => 'error',
                        'message' => "Produk \"{$namaProduk}\" tidak dapat dihapus karena sudah memiliki riwayat pesanan atau transaksi. Gunakan tombol Nonaktifkan untuk menyembunyikannya.",
                    ];
                } else {
                    // Bersihkan riwayat tawaran non-aktif (ditolak/expired/gagal) agar FK ON DELETE RESTRICT tidak menghalangi hapus
                    $stmtCleanNego = $db->prepare("
                        DELETE FROM negosiasi
                        WHERE id_produk = ? AND status IN ('ditolak', 'expired', 'gagal')
                    ");
                    $stmtCleanNego->execute([$produkId]);

                    $stmtCleanBarter = $db->prepare("
                        DELETE FROM barter
                        WHERE (id_produk_diincar = ? OR id_produk_ditawarkan = ?)
                          AND status IN ('ditolak', 'expired', 'gagal')
                    ");
                    $stmtCleanBarter->execute([$produkId, $produkId]);

                    $stmtPhotoPaths = $db->prepare("SELECT path_foto FROM produk_foto WHERE id_produk = ?");
                    $stmtPhotoPaths->execute([$produkId]);
                    $galleryPaths = $stmtPhotoPaths->fetchAll(PDO::FETCH_COLUMN);

                    $stmtDel = $db->prepare("DELETE FROM produk WHERE id = ? AND id_penjual = ?");
                    $stmtDel->execute([$produkId, $userId]);

                    $db->commit();

                    // Hapus seluruh file foto lokal produk jika tersimpan di uploads/produk/
                    $allFotoPaths = array_unique(array_filter(array_merge(
                        [(string)($prod['foto'] ?? '')],
                        is_array($galleryPaths) ? array_map('strval', $galleryPaths) : []
                    )));
                    $uploadsDir = realpath(__DIR__ . '/uploads/produk');
                    if ($uploadsDir !== false) {
                        foreach ($allFotoPaths as $fotoPath) {
                            $cleanPath = trim($fotoPath);
                            if ($cleanPath === '') continue;
                            $targetFile = realpath(__DIR__ . '/' . ltrim($cleanPath, '/\\'));
                            if ($targetFile !== false && str_starts_with($targetFile, $uploadsDir . DIRECTORY_SEPARATOR) && is_file($targetFile)) {
                                @unlink($targetFile);
                            }
                        }
                    }

                    $_SESSION['katalog_flash'] = [
                        'type'    => 'success',
                        'message' => "Produk \"{$namaProduk}\" berhasil dihapus dari katalog.",
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['katalog_flash'] = [
            'type'    => 'error',
            'message' => 'Gagal memproses aksi produk. Silakan coba lagi.',
        ];
    }

    header("Location: " . $redirectUrl);
    exit;
}

// Ambil seluruh produk milik seller yang sedang login
$products = [];
try {
    $stmtProducts = $db->prepare("
        SELECT
            p.id,
            p.nama_produk,
            p.foto,
            p.harga,
            p.stok,
            p.kondisi,
            p.status_produk,
            p.created_at,
            k.nama_kategori,
            (
                SELECT MAX(ps.created_at)
                FROM detail_pesanan dp
                JOIN pesanan ps ON ps.id = dp.id_pesanan
                WHERE dp.id_produk = p.id
                  AND ps.status_pesanan != 'dibatalkan'
            ) AS terjual_pada
        FROM produk p
        JOIN kategori k ON k.id = p.id_kategori
        WHERE p.id_penjual = ?
        ORDER BY p.created_at DESC, p.id DESC
    ");
    $stmtProducts->execute([$userId]);
    $products = $stmtProducts->fetchAll();
} catch (PDOException $e) {
    $products = [];
    if ($flash === null) {
        $flash = ['type' => 'error', 'message' => 'Gagal memuat daftar produk katalog. Silakan muat ulang halaman.'];
    }
}

$currentUser = getCurrentUser();
$navActivePage = 'katalog';

$accountActiveMenu = 'toko_katalog';
$accountSidebarUser = [
    'nama'        => (string)$userRow['nama'],
    'email'       => (string)$userRow['email'],
    'is_verified' => 1,
];

$statusBadgeMap = [
    'tersedia' => [
        'label'   => 'Sedang Tayang',
        'pillCls' => 'bg-[#E6F7EE] text-[#15803D]',
        'dotCls'  => 'bg-[#22C55E]',
    ],
    'draft' => [
        'label'   => 'Draft',
        'pillCls' => 'bg-[#DCDEE2] text-[#4B5563]',
        'dotCls'  => 'bg-[#6B7280]',
    ],
    'habis' => [
        'label'   => 'Terjual',
        'pillCls' => 'bg-[#DCDEE2] text-[#4B5563]',
        'dotCls'  => 'bg-[#6B7280]',
    ],
    'nonaktif' => [
        'label'   => 'Nonaktif',
        'pillCls' => 'bg-[#FCE7F3] text-[#BE123C]',
        'dotCls'  => 'bg-[#F43F5E]',
    ],
];

$filterTabs = [
    ['key' => 'semua',    'label' => 'Semua'],
    ['key' => 'tersedia', 'label' => 'Sedang Tayang'],
    ['key' => 'draft',    'label' => 'Draft'],
    ['key' => 'habis',    'label' => 'Terjual'],
    ['key' => 'nonaktif', 'label' => 'Nonaktif'],
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Katalog Toko | Loot-E</title>

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

            <!-- Area Konten Utama Katalog -->
            <section class="flex-1 w-full min-w-0">
                <!-- Header Halaman & Tombol Tambah Produk -->
                <header class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-[26px] font-extrabold text-[#272727] tracking-tight leading-tight">
                            Katalog
                        </h1>
                        <p class="text-xs sm:text-[13px] text-gray-500 mt-1">
                            Kelola produk yang kamu jual di Loot-E
                        </p>
                    </div>

                    <a href="upload_produk.php" id="btn-tambah-produk"
                        class="inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition shrink-0 self-start">
                        <i class="ph-bold ph-plus text-xs" aria-hidden="true"></i>
                        <span>Tambah Produk</span>
                    </a>
                </header>

                <!-- Filter Status Produk -->
                <div class="mt-5 flex flex-wrap items-center gap-2" role="tablist" aria-label="Filter status produk katalog">
                    <?php foreach ($filterTabs as $tab): ?>
                        <?php $isTabActive = $tab['key'] === $initialTab; ?>
                        <button type="button"
                            role="tab"
                            data-katalog-tab="<?= htmlspecialchars($tab['key'], ENT_QUOTES, 'UTF-8') ?>"
                            aria-selected="<?= $isTabActive ? 'true' : 'false' ?>"
                            class="katalog-tab px-4 py-1.5 text-xs sm:text-[13px] rounded-[2px] border transition cursor-pointer <?= $isTabActive ? 'bg-brand-yellow text-[#272727] font-semibold border-brand-yellow' : 'bg-white text-[#272727] font-medium border-gray-200/90 hover:bg-gray-50' ?>">
                            <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Kontainer Grid Katalog Produk -->
                <div class="mt-4 bg-[#EAEAEA] p-4 sm:p-5 md:p-6">
                    <?php if (!empty($products)): ?>
                        <!-- Grid Kartu Produk (3 Kolom Desktop) -->
                        <div id="katalog-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
                            <?php foreach ($products as $item): ?>
                                <?php
                                $statusKey   = (string)$item['status_produk'];
                                $badge       = $statusBadgeMap[$statusKey] ?? $statusBadgeMap['tersedia'];
                                $catIcon     = kategoriIcon((string)$item['nama_kategori']);
                                $namaRaw     = (string)$item['nama_produk'];
                                $namaEsc     = htmlspecialchars($namaRaw, ENT_QUOTES, 'UTF-8');
                                $prodId      = (int)$item['id'];
                                $detailHref  = 'detail_produk.php?id=' . $prodId;
                                $terjualDate = formatTanggalKatalog($item['terjual_pada'] ?: $item['created_at']);
                                $msgHapus    = htmlspecialchars("Produk \"{$namaRaw}\" akan dihapus permanen dari katalog. Tindakan ini tidak dapat dibatalkan.", ENT_QUOTES, 'UTF-8');
                                $msgNonaktif = htmlspecialchars("Produk \"{$namaRaw}\" akan disembunyikan dari etalase dan semua penawaran aktif (jika ada) akan otomatis ditutup.", ENT_QUOTES, 'UTF-8');
                                ?>
                                <article data-katalog-card data-status="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"
                                    class="bg-white p-3.5 sm:p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-full">

                                    <!-- Baris Atas Kartu: Badge Status & Tombol Hapus -->
                                    <div class="flex items-center justify-between gap-2 mb-3">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold leading-none <?= $badge['pillCls'] ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $badge['dotCls'] ?>" aria-hidden="true"></span>
                                            <span><?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>

                                        <form method="POST" action="katalog.php" class="inline-block shrink-0 katalog-action-form"
                                            data-confirm-title="Hapus Produk"
                                            data-confirm-message="<?= $msgHapus ?>"
                                            data-confirm-btn="Ya, Hapus"
                                            data-confirm-variant="danger">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                                            <input type="hidden" name="action" value="hapus" />
                                            <input type="hidden" name="produk_id" value="<?= $prodId ?>" />
                                            <input type="hidden" name="return_tab" value="<?= htmlspecialchars($initialTab, ENT_QUOTES, 'UTF-8') ?>" class="katalog-return-tab" />
                                            <button type="submit"
                                                aria-label="Hapus produk <?= $namaEsc ?>"
                                                class="w-7 h-7 bg-[#FEE2E2] hover:bg-[#FECACA] text-[#EF4444] rounded-[2px] flex items-center justify-center transition cursor-pointer">
                                                <i class="ph ph-trash text-sm" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Gambar Produk (Fallback Ikon Kategori Loot-E) -->
                                    <div class="aspect-[16/10] w-full bg-[#F4F4F4] border border-gray-100 overflow-hidden mb-3.5 flex flex-col items-center justify-center">
                                        <?php if (!empty($item['foto'])): ?>
                                            <img src="<?= htmlspecialchars((string)$item['foto'], ENT_QUOTES, 'UTF-8') ?>"
                                                alt="<?= $namaEsc ?>"
                                                width="400" height="250"
                                                class="w-full h-full object-cover" />
                                        <?php else: ?>
                                            <i class="ph <?= htmlspecialchars($catIcon, ENT_QUOTES, 'UTF-8') ?> text-4xl text-gray-500" aria-hidden="true"></i>
                                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mt-1">
                                                <?= htmlspecialchars((string)$item['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info Produk -->
                                    <div class="flex-1 flex flex-col">
                                        <h2 class="text-[13px] sm:text-sm font-bold text-[#272727] leading-snug line-clamp-2">
                                            <?= $namaEsc ?>
                                        </h2>

                                        <p class="mt-1 text-[15px] font-extrabold text-[#272727] tabular-nums">
                                            <?= htmlspecialchars(formatRupiah((int)$item['harga']), ENT_QUOTES, 'UTF-8') ?>
                                        </p>

                                        <?php if ($statusKey === 'habis'): ?>
                                            <p class="mt-1 text-[11px] text-gray-500">
                                                Terjual pada: <?= htmlspecialchars($terjualDate, ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="mt-1 text-[11px] text-gray-500">
                                                Stok: <?= (int)$item['stok'] ?> unit &bull; Kondisi: <?= htmlspecialchars((string)$item['kondisi'], ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Tombol Aksi Bawah (Sesuai Status Produk) -->
                                    <div class="mt-4 space-y-2">
                                        <?php if ($statusKey === 'tersedia'): ?>
                                            <div class="grid grid-cols-2 gap-2">
                                                <a href="upload_produk.php?id=<?= $prodId ?>"
                                                    class="block text-center w-full bg-[#272727] hover:bg-black active:scale-[0.99] text-white text-xs font-semibold py-2 rounded-[2px] transition">
                                                    Edit
                                                </a>
                                                <form method="POST" action="katalog.php" class="katalog-action-form"
                                                    data-confirm-title="Nonaktifkan Produk"
                                                    data-confirm-message="<?= $msgNonaktif ?>"
                                                    data-confirm-btn="Ya, Nonaktifkan"
                                                    data-confirm-variant="warning">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                                                    <input type="hidden" name="action" value="nonaktifkan" />
                                                    <input type="hidden" name="produk_id" value="<?= $prodId ?>" />
                                                    <input type="hidden" name="return_tab" value="<?= htmlspecialchars($initialTab, ENT_QUOTES, 'UTF-8') ?>" class="katalog-return-tab" />
                                                    <button type="submit"
                                                        class="w-full bg-[#D1D5DB] hover:bg-[#C4C9D0] active:scale-[0.99] text-[#272727] text-xs font-semibold py-2 rounded-[2px] transition cursor-pointer">
                                                        Nonaktifkan
                                                    </button>
                                                </form>
                                            </div>
                                            <a href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>"
                                                class="block text-center w-full bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold py-2 rounded-[2px] transition">
                                                Lihat Produk
                                            </a>

                                        <?php elseif ($statusKey === 'nonaktif'): ?>
                                            <div class="grid grid-cols-2 gap-2">
                                                <a href="upload_produk.php?id=<?= $prodId ?>"
                                                    class="block text-center w-full bg-[#272727] hover:bg-black active:scale-[0.99] text-white text-xs font-semibold py-2 rounded-[2px] transition">
                                                    Edit
                                                </a>
                                                <form method="POST" action="katalog.php" class="katalog-action-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                                                    <input type="hidden" name="action" value="aktifkan" />
                                                    <input type="hidden" name="produk_id" value="<?= $prodId ?>" />
                                                    <input type="hidden" name="return_tab" value="<?= htmlspecialchars($initialTab, ENT_QUOTES, 'UTF-8') ?>" class="katalog-return-tab" />
                                                    <button type="submit"
                                                        class="w-full bg-[#D1D5DB] hover:bg-[#C4C9D0] active:scale-[0.99] text-[#272727] text-xs font-semibold py-2 rounded-[2px] transition cursor-pointer">
                                                        Aktifkan
                                                    </button>
                                                </form>
                                            </div>
                                            <a href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>"
                                                class="block text-center w-full bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold py-2 rounded-[2px] transition">
                                                Lihat Produk
                                            </a>

                                        <?php elseif ($statusKey === 'draft'): ?>
                                            <a href="upload_produk.php?id=<?= $prodId ?>"
                                                class="block text-center w-full bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold py-2 rounded-[2px] transition">
                                                Lanjutkan Draft
                                            </a>

                                        <?php else: ?>
                                            <button type="button"
                                                data-demo-toast="Aksi Lihat Detail untuk <?= $namaEsc ?> belum tersedia saat ini (mode demo)."
                                                class="w-full bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold py-2 rounded-[2px] transition cursor-pointer">
                                                Lihat Detail
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <!-- Empty State Filter -->
                        <div id="katalog-empty-state" class="hidden bg-white px-6 py-12 text-center shadow-[0_1px_3px_rgba(0,0,0,0.06)]">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[#F4F4F4] flex items-center justify-center text-gray-500">
                                <i class="ph ph-storefront text-2xl" aria-hidden="true"></i>
                            </div>
                            <h2 class="mt-3 text-sm font-bold text-[#272727]">
                                Belum ada produk pada status ini
                            </h2>
                            <p class="mt-1 text-xs text-gray-500 max-w-sm mx-auto leading-relaxed">
                                Produk dengan status yang dipilih belum tersedia di katalog tokomu.
                            </p>
                            <button type="button" id="katalog-reset-filter"
                                class="mt-4 inline-flex items-center justify-center bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold px-4 py-2 rounded-[2px] transition cursor-pointer">
                                Tampilkan Semua Produk
                            </button>
                        </div>

                        <!-- Pagination Kanan Bawah -->
                        <nav id="katalog-pagination" aria-label="Navigasi halaman katalog"
                            class="mt-5 flex items-center justify-end gap-1.5">
                        </nav>
                    <?php else: ?>
                        <!-- Empty State Awal (Seller belum memiliki produk sama sekali) -->
                        <div class="bg-white px-6 py-14 text-center shadow-[0_1px_3px_rgba(0,0,0,0.06)]">
                            <div class="w-14 h-14 mx-auto rounded-full bg-[#F4F4F4] flex items-center justify-center text-gray-500">
                                <i class="ph ph-storefront text-3xl" aria-hidden="true"></i>
                            </div>
                            <h2 class="mt-3.5 text-sm sm:text-base font-bold text-[#272727]">
                                Belum ada produk di katalog
                            </h2>
                            <p class="mt-1 text-xs sm:text-[13px] text-gray-500 max-w-sm mx-auto leading-relaxed">
                                Mulai unggah produk pertamamu agar dapat ditemukan dan dibeli pengguna Loot-E.
                            </p>
                            <a href="upload_produk.php"
                                class="mt-5 inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold px-5 py-2.5 rounded-[2px] transition">
                                <i class="ph-bold ph-plus text-xs" aria-hidden="true"></i>
                                <span>Tambah Produk</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </main>

    <!-- Toast Notifikasi -->
    <div id="katalog-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg max-w-sm">
    </div>

    <!-- Modal Konfirmasi Reusable -->
    <?php require_once 'components/confirm_modal.php'; ?>

    <!-- Footer & Help FAB -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function () {
            const PER_PAGE = 6;
            const cards = Array.from(document.querySelectorAll('[data-katalog-card]'));
            const tabs = Array.from(document.querySelectorAll('[data-katalog-tab]'));
            const returnTabInputs = Array.from(document.querySelectorAll('.katalog-return-tab'));
            const emptyState = document.getElementById('katalog-empty-state');
            const resetFilterBtn = document.getElementById('katalog-reset-filter');
            const paginationNav = document.getElementById('katalog-pagination');
            const toast = document.getElementById('katalog-toast');
            const initialFlash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            let activeStatus = <?= json_encode($initialTab, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            let currentPage = 1;
            let toastTimer = null;

            function showToast(message, type) {
                if (!toast || !message) return;
                toast.textContent = message;
                toast.classList.remove('hidden', 'border-brand-yellow', 'border-red-500');
                toast.classList.add(type === 'error' ? 'border-red-500' : 'border-brand-yellow');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function () {
                    toast.classList.add('hidden');
                }, 4000);
            }

            function getFilteredCards() {
                if (activeStatus === 'semua') return cards;
                return cards.filter(function (card) {
                    return card.getAttribute('data-status') === activeStatus;
                });
            }

            function renderPagination(totalItems) {
                if (!paginationNav) return;
                const totalPages = Math.max(1, Math.ceil(totalItems / PER_PAGE));
                if (totalItems === 0) {
                    paginationNav.innerHTML = '';
                    paginationNav.classList.add('hidden');
                    return;
                }
                paginationNav.classList.remove('hidden');

                const parts = [];

                const prevDisabled = currentPage <= 1;
                parts.push(
                    '<button type="button" data-page="' + (currentPage - 1) + '"' +
                    (prevDisabled ? ' disabled' : '') +
                    ' aria-label="Halaman sebelumnya"' +
                    ' class="w-8 h-8 rounded-[6px] flex items-center justify-center text-xs transition ' +
                    (prevDisabled
                        ? 'bg-white/50 text-gray-400 cursor-not-allowed'
                        : 'bg-white/80 hover:bg-white text-gray-700 cursor-pointer') +
                    '"><i class="ph ph-caret-left text-xs" aria-hidden="true"></i></button>'
                );

                for (let p = 1; p <= totalPages; p++) {
                    const isCurrent = p === currentPage;
                    parts.push(
                        '<button type="button" data-page="' + p + '"' +
                        (isCurrent ? ' aria-current="page"' : '') +
                        ' class="w-8 h-8 rounded-[6px] flex items-center justify-center text-xs transition cursor-pointer ' +
                        (isCurrent
                            ? 'bg-[#272727] text-white font-bold'
                            : 'text-[#272727] hover:bg-white/80 font-medium') +
                        '">' + p + '</button>'
                    );
                }

                const nextDisabled = currentPage >= totalPages;
                parts.push(
                    '<button type="button" data-page="' + (currentPage + 1) + '"' +
                    (nextDisabled ? ' disabled' : '') +
                    ' aria-label="Halaman berikutnya"' +
                    ' class="w-8 h-8 rounded-[6px] flex items-center justify-center text-xs transition ' +
                    (nextDisabled
                        ? 'bg-white/50 text-gray-400 cursor-not-allowed'
                        : 'bg-white/80 hover:bg-white text-gray-700 cursor-pointer') +
                    '"><i class="ph ph-caret-right text-xs" aria-hidden="true"></i></button>'
                );

                paginationNav.innerHTML = parts.join('');
            }

            function render() {
                const matched = getFilteredCards();
                const totalPages = Math.max(1, Math.ceil(matched.length / PER_PAGE));
                if (currentPage > totalPages) currentPage = totalPages;

                const startIdx = (currentPage - 1) * PER_PAGE;
                const endIdx = startIdx + PER_PAGE;
                const visibleSet = new Set(matched.slice(startIdx, endIdx));

                cards.forEach(function (card) {
                    card.classList.toggle('hidden', !visibleSet.has(card));
                });

                if (emptyState) {
                    emptyState.classList.toggle('hidden', matched.length > 0);
                }

                renderPagination(matched.length);
            }

            function setActiveTab(statusKey) {
                activeStatus = statusKey;
                currentPage = 1;
                tabs.forEach(function (tab) {
                    const isSelected = tab.getAttribute('data-katalog-tab') === statusKey;
                    tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    tab.classList.toggle('bg-brand-yellow', isSelected);
                    tab.classList.toggle('border-brand-yellow', isSelected);
                    tab.classList.toggle('font-semibold', isSelected);
                    tab.classList.toggle('bg-white', !isSelected);
                    tab.classList.toggle('border-gray-200/90', !isSelected);
                    tab.classList.toggle('font-medium', !isSelected);
                    tab.classList.toggle('hover:bg-gray-50', !isSelected);
                });
                returnTabInputs.forEach(function (input) {
                    input.value = statusKey;
                });
                render();
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    setActiveTab(tab.getAttribute('data-katalog-tab') || 'semua');
                });
            });

            if (resetFilterBtn) {
                resetFilterBtn.addEventListener('click', function () {
                    setActiveTab('semua');
                });
            }

            if (paginationNav) {
                paginationNav.addEventListener('click', function (e) {
                    const btn = e.target.closest('button[data-page]');
                    if (!btn || btn.disabled) return;
                    const targetPage = parseInt(btn.getAttribute('data-page') || '1', 10);
                    if (!isNaN(targetPage) && targetPage >= 1) {
                        currentPage = targetPage;
                        render();
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
                showToast(initialFlash.message, initialFlash.type || 'success');
            }

            if (cards.length > 0) {
                render();
            }
        })();
    </script>
</body>

</html>

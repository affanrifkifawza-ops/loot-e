<?php
require_once "auth.php";

if (!isLoggedIn()) {
    $redirectUrl = "detail_produk.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header("Location: login.php?redirect=" . urlencode($redirectUrl));
    exit;
}

$currentUser = getCurrentUser();
$navActivePage = 'categories';

$productId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$produk = null;
$idProduk = 0;
$relatedProducts = [];
$reviews = [];
$sellerStats = [
    'produk_aktif'      => 0,
    'produk_terjual'    => 0,
    'rating_toko'       => null,
    'total_ulasan_toko' => 0,
];

try {
    $db = getDbConnection();
    $sqlBase = "
        SELECT p.*, k.nama_kategori, u.nama AS nama_penjual, u.alamat AS alamat_penjual, u.is_verified AS penjual_verified
        FROM produk p
        JOIN kategori k ON p.id_kategori = k.id
        JOIN pengguna u ON p.id_penjual = u.id
    ";
    if ($productId !== null) {
        $stmt = $db->prepare($sqlBase . " WHERE p.id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $produk = $stmt->fetch() ?: null;
    } else {
        $produk = $db->query($sqlBase . " WHERE p.status_produk = 'tersedia' ORDER BY p.id ASC LIMIT 1")->fetch() ?: null;
    }

    if ($produk) {
        $idProduk  = (int)$produk['id'];
        $idPenjual = (int)$produk['id_penjual'];

        $stmtRel = $db->prepare("
            SELECT p.id, p.nama_produk, p.foto, p.harga, p.kondisi, p.stok, k.nama_kategori, u.nama AS nama_penjual
            FROM produk p
            JOIN kategori k ON p.id_kategori = k.id
            JOIN pengguna u ON p.id_penjual = u.id
            WHERE p.status_produk = 'tersedia' AND p.id != ?
            ORDER BY (p.id_kategori = ?) DESC, p.id DESC
            LIMIT 3
        ");
        $stmtRel->execute([$idProduk, (int)$produk['id_kategori']]);
        $relatedProducts = $stmtRel->fetchAll();

        $stmtRev = $db->prepare("
            SELECT ul.id, ul.rating, ul.komentar, ul.created_at, pb.nama AS nama_pembeli
            FROM ulasan ul
            JOIN pesanan ps ON ul.id_pesanan = ps.id
            JOIN pengguna pb ON ps.id_pembeli = pb.id
            WHERE ul.id_produk = ?
            ORDER BY ul.created_at DESC, ul.id DESC
        ");
        $stmtRev->execute([$idProduk]);
        $reviews = $stmtRev->fetchAll();

        $stmtSeller = $db->prepare("
            SELECT
                (SELECT COUNT(*) FROM produk WHERE id_penjual = ? AND status_produk = 'tersedia') AS produk_aktif,
                (SELECT COALESCE(SUM(dp.kuantitas), 0)
                 FROM detail_pesanan dp
                 JOIN pesanan ps ON dp.id_pesanan = ps.id
                 JOIN produk pr ON dp.id_produk = pr.id
                 WHERE pr.id_penjual = ? AND ps.status_pesanan = 'selesai') AS produk_terjual,
                (SELECT AVG(ul.rating)
                 FROM ulasan ul
                 JOIN produk pr ON ul.id_produk = pr.id
                 WHERE pr.id_penjual = ?) AS rating_toko,
                (SELECT COUNT(ul.id)
                 FROM ulasan ul
                 JOIN produk pr ON ul.id_produk = pr.id
                 WHERE pr.id_penjual = ?) AS total_ulasan_toko
        ");
        $stmtSeller->execute([$idPenjual, $idPenjual, $idPenjual, $idPenjual]);
        $sellerStats = $stmtSeller->fetch() ?: $sellerStats;
    }
} catch (PDOException $e) {
    $produk = null;
    $relatedProducts = [];
    $reviews = [];
}

if (!$produk) {
    header("Location: search.php");
    exit;
}

$idProduk        = (int)$produk['id'];
$namaProduk      = $produk['nama_produk'];
$kategoriNama    = $produk['nama_kategori'];
$kondisiProduk   = $produk['kondisi'];
$hargaProduk     = (int)$produk['harga'];
$stokProduk      = (int)$produk['stok'];
$statusProduk    = $produk['status_produk'];
$bisaNego        = (int)$produk['bisa_nego'] === 1;
$bisaBarter      = (int)$produk['bisa_barter'] === 1;
$namaPenjual     = $produk['nama_penjual'];
$alamatPenjual   = $produk['alamat_penjual'] ?? '';
$penjualVerified = (int)($produk['penjual_verified'] ?? 0) === 1;
$isOwnProduct    = ($currentUser['id'] === (int)$produk['id_penjual']); // BR-01

$totalUlasan = count($reviews);
$avgRating   = $totalUlasan > 0 ? array_sum(array_column($reviews, 'rating')) / $totalUlasan : 0.0;
$ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$puasCount    = 0;
foreach ($reviews as $rev) {
    $star = (int)$rev['rating'];
    if (isset($ratingCounts[$star])) {
        $ratingCounts[$star]++;
    }
    if ($star >= 4) {
        $puasCount++;
    }
}
$puasPct = $totalUlasan > 0 ? (int)round(($puasCount / $totalUlasan) * 100) : 0;

$parsedDeskripsi = parseDeskripsiProduk($produk['deskripsi'] ?? '');
$labelRiwayatMap = [
    'fisik'       => 'Kondisi Fisik',
    'fungsi'      => 'Fungsi',
    'lama_pakai'  => 'Lama Pemakaian',
    'servis'      => 'Riwayat Servis',
    'minus'       => 'Minus / Defect',
    'kelengkapan' => 'Kelengkapan',
];

$inisialPenjual = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $namaPenjual), 0, 2) ?: 'LE');
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($namaProduk) ?> | Loot-E</title>

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
                            bg: '#F4F5F7'
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif']
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

        /* Custom Styling Range Slider Harga Pasar (Indikator Statis) */
        #price-range-slider {
            -webkit-appearance: none;
            appearance: none;
            background: transparent;
            pointer-events: none;
        }

        #price-range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 9999px;
            background-color: #FFC914;
            border: 2px solid #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
            cursor: default;
            pointer-events: none;
        }

        #price-range-slider::-moz-range-thumb {
            width: 16px;
            height: 16px;
            border-radius: 9999px;
            background-color: #FFC914;
            border: 2px solid #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
            cursor: default;
            pointer-events: none;
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

<body class="bg-[#F4F5F7] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Header & Navigasi Utama dari components/navbar.php -->
    <?php require_once 'components/navbar.php'; ?>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <nav aria-label="Breadcrumb">
            <ol class="flex items-center gap-1.5 text-xs text-gray-500">
                <li>
                    <a href="categories.php" class="hover:text-gray-900 transition font-medium">Kategori</a>
                </li>
                <li aria-hidden="true" class="text-gray-400">/</li>
                <li class="font-semibold text-gray-900" aria-current="page">Detail Produk</li>
            </ol>
        </nav>

        <!-- Baris Utama Produk: Galeri (Kiri) + Info & Harga (Tengah) + Info Penjual (Kanan) -->
        <section class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start" aria-label="Informasi Utama Produk">

            <!-- Kolom 1: Foto Produk -->
            <div class="md:col-span-12 lg:col-span-5 bg-white border border-gray-200/80 p-4 shadow-2xs">
                <div class="relative w-full aspect-square bg-[#FAFAFA] border border-gray-200/80 flex items-center justify-center p-6 overflow-hidden">
                    <!-- Badge Kondisi -->
                    <span class="absolute top-3 left-3 z-10 bg-[#FEF3C7] text-[#92400E] text-xs font-semibold px-2.5 py-1 rounded-md tracking-wide">
                        <?= htmlspecialchars($kondisiProduk) ?>
                    </span>

                    <?php if (!empty($produk['foto'])): ?>
                        <img src="<?= htmlspecialchars($produk['foto']) ?>" alt="<?= htmlspecialchars($namaProduk) ?>" class="w-full h-full object-contain" />
                    <?php else: ?>
                        <div class="w-full h-full flex flex-col items-center justify-center text-center p-4">
                            <i class="ph <?= htmlspecialchars(kategoriIcon($kategoriNama)) ?> text-7xl text-gray-700 mb-2" aria-hidden="true"></i>
                            <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($namaProduk) ?></p>
                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($kategoriNama) ?> &bull; <?= htmlspecialchars($kondisiProduk) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Kolom 2: Detail Produk, Harga, Market Insight, dan Aksi -->
            <div class="md:col-span-12 lg:col-span-4 bg-white border border-gray-200/80 p-5 sm:p-6 space-y-4 shadow-2xs">
                <!-- Judul Produk -->
                <div>
                    <h1 id="produk-title" class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight">
                        <?= htmlspecialchars($namaProduk) ?>
                    </h1>

                    <!-- Rating & Meta -->
                    <div class="flex flex-wrap items-center gap-1 mt-2 text-xs text-gray-500">
                        <?php if ($totalUlasan > 0): ?>
                            <div class="flex items-center gap-1 text-gray-900 font-semibold">
                                <i class="ph-fill ph-star text-amber-500 text-sm" aria-hidden="true"></i>
                                <span><?= number_format($avgRating, 1) ?></span>
                                <span class="text-gray-500 font-normal">(<?= $totalUlasan ?> ulasan)</span>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center gap-1 text-gray-400 font-medium">
                                <i class="ph ph-star text-sm" aria-hidden="true"></i>
                                <span>Belum ada ulasan</span>
                            </div>
                        <?php endif; ?>
                        <span aria-hidden="true" class="text-gray-300">•</span>
                        <span aria-hidden="true" class="text-gray-300"></span>
                        <span><?= htmlspecialchars($kategoriNama) ?></span>
                        <span class="bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2 py-0.5 rounded-full">
                            <?= htmlspecialchars($kondisiProduk) ?>
                        </span>
                    </div>
                </div>

                <!-- Bagian Harga & Stok -->
                <div class="flex items-baseline justify-between gap-3 pt-2 border-t border-gray-100">
                    <span class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-tight">
                        <?= formatRupiah($hargaProduk) ?>
                    </span>
                    <span class="text-xs text-gray-500 font-medium shrink-0">
                        Stok: <span class="font-semibold text-gray-700"><?= $stokProduk ?></span>
                    </span>
                </div>

                <!-- Box Analisis Harga Pasar -->
                <div class="bg-[#FFFDF5] rounded-lg p-3.5 space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 font-semibold text-amber-900">
                            <i class="ph-fill ph-tag text-amber-600" aria-hidden="true"></i>
                            <span>Harga Pasar</span>
                        </div>
                        <span class="text-gray-600 text-xs">Harga listing <?= formatRupiah($hargaProduk) ?></span>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm sm:text-base font-bold text-gray-900">
                            Rp 600.000 - Rp 750.000
                        </span>
                        <span id="market-status-badge" class="bg-emerald-100 text-emerald-800 text-[11px] font-semibold px-2 py-0.5 rounded-full transition">
                            Dalam kisaran harga pasar
                        </span>
                    </div>

                    <!-- Slider / Gauge Bar Representatif (Indikator Statis) -->
                    <div class="pt-1">
                        <div class="relative w-full flex items-center py-1">
                            <!-- Track Bar Gradient -->
                            <div class="absolute inset-x-0 h-2 rounded-full bg-gradient-to-r from-emerald-200 via-amber-200 to-rose-200 border border-gray-200 pointer-events-none"></div>

                            <!-- Native Accessible Range Input (Statis / Tidak Dapat Digeser) -->
                            <input
                                type="range"
                                id="price-range-slider"
                                min="600000"
                                max="850000"
                                step="5000"
                                value="680000"
                                disabled
                                readonly
                                tabindex="-1"
                                aria-label="Indikator posisi harga pasar"
                                class="relative w-full h-2 z-10 cursor-default pointer-events-none focus:outline-none" />
                        </div>
                        <div class="flex justify-between text-[11px] text-gray-500 mt-1 font-medium select-none">
                            <span>Rp 600K</span>
                            <span id="slider-current-label" class="font-bold text-gray-900">Rp 680K</span>
                            <span>Rp 850K</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-gray-500 leading-relaxed border-t border-amber-100 pt-2">
                        Refrensi harga membantu kamu menilai harga produk. Harga ditentukan oleh seller
                    </p>
                </div>

                <!-- Badges Fasilitas Transaksi -->
                <div class="flex flex-wrap gap-1.5 text-xs">
                    <?php if ($bisaNego): ?>
                        <span class="bg-[#FEF3C7] text-[#92400E] px-3 py-1 rounded-full font-medium">
                            Bisa Nego
                        </span>
                    <?php endif; ?>
                    <?php if ($bisaBarter): ?>
                        <span class="bg-[#FEF3C7] text-[#92400E] px-3 py-1 rounded-full font-medium">
                            Bisa Barter
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Grid Tombol Aksi / Guard BR-01 -->
                <?php if ($isOwnProduct): ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3.5 text-xs text-amber-900 leading-relaxed">
                        <p class="font-bold flex items-center gap-1.5 mb-1">
                            <i class="ph-fill ph-storefront text-amber-600 text-sm" aria-hidden="true"></i>
                            <span>Produk Milik Anda Sendiri</span>
                        </p>
                        <p class="text-amber-800">
                            Anda tidak dapat membeli, menawar, atau mengajukan barter pada produk Anda sendiri.
                        </p>
                    </div>
                <?php elseif ($statusProduk !== 'tersedia' || $stokProduk <= 0): ?>
                    <div class="bg-gray-100 border border-gray-200 rounded-lg p-3.5 text-xs text-gray-700 leading-relaxed">
                        <p class="font-bold mb-0.5">Produk Tidak Tersedia</p>
                        <p>Produk ini sedang terkunci dalam transaksi lain atau stok sudah habis.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 gap-2.5 pt-2">
                        <?php if ($bisaNego): ?>
                            <button
                                type="button"
                                id="btn-nego"
                                class="min-h-[44px] px-3 py-2.5 bg-brand-yellow hover:bg-yellow-400 text-brand-dark font-bold text-xs uppercase tracking-wide transition shadow-2xs cursor-pointer">
                                Ajukan Harga
                            </button>
                        <?php endif; ?>

                        <?php if ($bisaBarter): ?>
                            <button
                                type="button"
                                id="btn-barter"
                                class="min-h-[44px] px-3 py-2.5 bg-white hover:bg-amber-50 text-amber-800 font-bold text-xs uppercase tracking-wide transition border border-amber-400 cursor-pointer">
                                Ajukan Barter
                            </button>
                        <?php endif; ?>

                        <button
                            type="button"
                            id="btn-cart"
                            data-id="<?= (int)$idProduk ?>"
                            class="min-h-[44px] px-3 py-2.5 bg-white hover:bg-gray-100 text-gray-800 font-bold text-xs uppercase tracking-wide transition border border-gray-300 flex items-center justify-center gap-1.5 cursor-pointer">
                            <i class="ph ph-shopping-cart text-sm font-bold" aria-hidden="true"></i>
                            <span>Tambah ke Keranjang</span>
                        </button>

                        <button
                            type="button"
                            id="btn-buy"
                            data-id="<?= (int)$idProduk ?>"
                            class="min-h-[44px] px-3 py-2.5 bg-[#272727] hover:bg-black text-white font-bold text-xs uppercase tracking-wide transition cursor-pointer">
                            Beli Sekarang
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kolom 3: Informasi Penjual -->
            <aside class="md:col-span-12 lg:col-span-3 bg-white border border-gray-200/80 p-5 space-y-4 shadow-2xs" aria-label="Informasi Penjual">
                <h2 class="font-bold text-sm text-gray-900 border-b border-gray-100 pb-2.5 uppercase tracking-wide">
                    Informasi Penjual
                </h2>

                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-brand-dark text-brand-yellow flex items-center justify-center font-bold text-base shadow-xs shrink-0">
                        <?= htmlspecialchars($inisialPenjual) ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-gray-900 leading-tight"><?= htmlspecialchars($namaPenjual) ?></h3>
                        <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($alamatPenjual ?: 'Lokasi belum diisi') ?></p>
                        <?php if ($penjualVerified): ?>
                            <span class="inline-block mt-1 text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full font-medium">
                                Verified Seller
                            </span>
                        <?php else: ?>
                            <span class="inline-block mt-1 text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">
                                Belum Verifikasi
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs bg-gray-50 p-3">
                    <div>
                        <span class="text-gray-500 block text-[11px]">Rating Toko</span>
                        <span class="font-bold text-gray-900 flex items-center gap-1 mt-0.5">
                            <?php if ((int)$sellerStats['total_ulasan_toko'] > 0): ?>
                                <i class="ph-fill ph-star text-amber-500" aria-hidden="true"></i>
                                <?= number_format((float)$sellerStats['rating_toko'], 1) ?> / 5.0
                            <?php else: ?>
                                <span class="text-gray-500 font-semibold">Belum ada</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-[11px]">Produk Aktif</span>
                        <span class="font-bold text-gray-900 block mt-0.5"><?= (int)$sellerStats['produk_aktif'] ?> Listing</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-[11px]">Produk Terjual</span>
                        <span class="font-bold text-gray-900 block mt-0.5"><?= (int)$sellerStats['produk_terjual'] ?> Unit</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-[11px]">Status Akun</span>
                        <span class="font-bold <?= $penjualVerified ? 'text-emerald-700' : 'text-gray-600' ?> block mt-0.5">
                            <?= $penjualVerified ? 'Terverifikasi' : 'Standar' ?>
                        </span>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-3 text-[11px] text-gray-500 space-y-1.5">
                    <div class="flex items-center gap-2 text-gray-700 font-medium">
                        <i class="ph ph-shield-check text-emerald-600 text-sm" aria-hidden="true"></i>
                        <span>Rekening Bersama Loot-E</span>
                    </div>
                    <p class="leading-relaxed">
                        Dana diteruskan ke penjual setelah kamu menerima barang dan memeriksa kesesuaian unit.
                    </p>
                </div>
            </aside>
        </section>

        <!-- Tab Navigasi Konten Detail -->
        <section class="bg-white border border-gray-200/80 overflow-hidden shadow-2xs" aria-label="Tab Detail Produk">
            <div class="border-b border-gray-200 px-4 sm:px-6 overflow-x-auto" role="tablist">
                <nav class="flex gap-2 sm:gap-6 min-w-max" aria-label="Bagian Detail">
                    <button
                        type="button"
                        class="tab-link py-3.5 px-3 text-xs sm:text-sm font-bold border-b-2 border-brand-yellow text-gray-900 cursor-pointer focus:outline-none"
                        data-target="panel-deskripsi"
                        role="tab"
                        aria-selected="true"
                        aria-controls="panel-deskripsi">
                        Deskripsi
                    </button>
                    <button
                        type="button"
                        class="tab-link py-3.5 px-3 text-xs sm:text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-900 cursor-pointer focus:outline-none transition"
                        data-target="panel-spesifikasi"
                        role="tab"
                        aria-selected="false"
                        aria-controls="panel-spesifikasi">
                        Spesifikasi
                    </button>
                    <button
                        type="button"
                        class="tab-link py-3.5 px-3 text-xs sm:text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-900 cursor-pointer focus:outline-none transition"
                        data-target="panel-riwayat"
                        role="tab"
                        aria-selected="false"
                        aria-controls="panel-riwayat">
                        Riwayat &amp; Kondisi
                    </button>
                    <button
                        type="button"
                        class="tab-link py-3.5 px-3 text-xs sm:text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-900 cursor-pointer focus:outline-none transition"
                        data-target="panel-ulasan"
                        role="tab"
                        aria-selected="false"
                        aria-controls="panel-ulasan">
                        Ulasan (<?= $totalUlasan ?>)
                    </button>
                </nav>
            </div>

            <!-- Konten Tab + Ulasan Rating -->
            <div class="p-5 sm:p-6 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                <!-- Area Kiri: Deskripsi & Spesifikasi Produk -->
                <div class="lg:col-span-7 space-y-6">
                    <!-- Panel Deskripsi -->
                    <div id="panel-deskripsi" class="tab-panel space-y-4" role="tabpanel">
                        <div>
                            <h2 class="text-base font-bold text-gray-900 mb-2">Deskripsi Produk</h2>
                            <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                                <?php if ($parsedDeskripsi['ringkasan'] !== ''): ?>
                                    <?= nl2br(htmlspecialchars($parsedDeskripsi['ringkasan'])) ?>
                                <?php else: ?>
                                    Belum ada deskripsi tambahan untuk produk ini.
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="border-t border-gray-100 pt-4">
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-y-2.5 gap-x-6 text-xs sm:text-sm">
                                <div class="flex items-center justify-between border-b border-gray-100 pb-1.5">
                                    <dt class="text-gray-500 font-medium">Kondisi:</dt>
                                    <dd class="text-gray-900 font-semibold"><?= htmlspecialchars($kondisiProduk) ?></dd>
                                </div>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-1.5">
                                    <dt class="text-gray-500 font-medium">Kategori:</dt>
                                    <dd class="text-gray-900 font-semibold"><?= htmlspecialchars($kategoriNama) ?></dd>
                                </div>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-1.5">
                                    <dt class="text-gray-500 font-medium">Stok Tersedia:</dt>
                                    <dd class="text-gray-900 font-semibold"><?= $stokProduk ?> Unit</dd>
                                </div>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-1.5">
                                    <dt class="text-gray-500 font-medium">Opsi Transaksi:</dt>
                                    <dd class="text-gray-900 font-semibold">
                                        <?php
                                        $opsi = ['Beli Langsung'];
                                        if ($bisaNego) $opsi[] = 'Nego';
                                        if ($bisaBarter) $opsi[] = 'Barter';
                                        echo htmlspecialchars(implode(' - ', $opsi));
                                        ?>
                                    </dd>
                                </div>
                                <?php if (!empty($parsedDeskripsi['riwayat']['kelengkapan'])): ?>
                                    <div class="flex items-center justify-between border-b border-gray-100 pb-1.5">
                                        <dt class="text-gray-500 font-medium">Kelengkapan:</dt>
                                        <dd class="text-gray-900 font-semibold text-right"><?= htmlspecialchars($parsedDeskripsi['riwayat']['kelengkapan']) ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <!-- Panel Spesifikasi Lengkap -->
                    <div id="panel-spesifikasi" class="tab-panel hidden space-y-4" role="tabpanel">
                        <h2 class="text-base font-bold text-gray-900 mb-2">Spesifikasi Lengkap</h2>
                        <?php if (!empty($parsedDeskripsi['spesifikasi'])): ?>
                            <table class="w-full text-xs sm:text-sm border border-gray-200 overflow-hidden">
                                <tbody>
                                    <?php foreach ($parsedDeskripsi['spesifikasi'] as $idx => $spec): ?>
                                        <tr class="border-b border-gray-200 <?= $idx % 2 === 0 ? 'bg-gray-50' : '' ?>">
                                            <th class="p-2.5 text-left font-medium text-gray-600 w-1/3"><?= htmlspecialchars($spec['label']) ?></th>
                                            <td class="p-2.5 text-gray-900 font-semibold"><?= htmlspecialchars($spec['nilai']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-xs sm:text-sm text-gray-500">
                                Penjual belum menambahkan detail spesifikasi teknis untuk produk ini.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Panel Riwayat & Kondisi -->
                    <div id="panel-riwayat" class="tab-panel hidden space-y-4" role="tabpanel">
                        <h2 class="text-base font-bold text-gray-900 mb-2">Riwayat Pemakaian &amp; Kondisi Fisik</h2>
                        <?php if (!empty($parsedDeskripsi['riwayat'])): ?>
                            <ul class="space-y-2.5 text-xs sm:text-sm text-gray-700">
                                <?php foreach ($parsedDeskripsi['riwayat'] as $key => $val): ?>
                                    <?php $label = $labelRiwayatMap[$key] ?? ucwords(str_replace('_', ' ', $key)); ?>
                                    <li class="flex items-start gap-2">
                                        <i class="ph ph-check-circle text-emerald-600 text-base shrink-0 mt-0.5" aria-hidden="true"></i>
                                        <span>
                                            <strong class="text-gray-900"><?= htmlspecialchars($label) ?>:</strong>
                                            <?= htmlspecialchars($val) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-xs sm:text-sm text-gray-500">
                                Kondisi umum produk tercatat sebagai <strong><?= htmlspecialchars($kondisiProduk) ?></strong>.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Panel Ulasan Full -->
                    <div id="panel-ulasan" class="tab-panel hidden space-y-4" role="tabpanel">
                        <h2 class="text-base font-bold text-gray-900 mb-2">Seluruh Ulasan Pembeli (<?= $totalUlasan ?>)</h2>
                        <?php if ($totalUlasan > 0): ?>
                            <div class="space-y-4">
                                <?php foreach ($reviews as $rev): ?>
                                    <?php
                                    $inisialPembeli = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $rev['nama_pembeli']), 0, 1) ?: 'P');
                                    $bintang = (int)$rev['rating'];
                                    ?>
                                    <article class="border-t border-gray-100 pt-3.5 space-y-1.5">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center font-bold text-xs shrink-0">
                                                    <?= htmlspecialchars($inisialPembeli) ?>
                                                </div>
                                                <div>
                                                    <span class="font-bold text-xs text-gray-900 block leading-tight"><?= htmlspecialchars($rev['nama_pembeli']) ?></span>
                                                    <time datetime="<?= htmlspecialchars(date('Y-m-d', strtotime($rev['created_at']))) ?>" class="text-[10px] text-gray-400 block">
                                                        <?= htmlspecialchars(date('d M Y', strtotime($rev['created_at']))) ?>
                                                    </time>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-0.5 text-amber-500 text-xs" aria-label="Bintang <?= $bintang ?>">
                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                    <i class="<?= $s <= $bintang ? 'ph-fill ph-star' : 'ph ph-star text-gray-300' ?>" aria-hidden="true"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($rev['komentar'])): ?>
                                            <p class="text-xs sm:text-sm text-gray-700 leading-relaxed">
                                                <?= nl2br(htmlspecialchars($rev['komentar'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-xs sm:text-sm text-gray-500">
                                Belum ada ulasan untuk produk ini. Ulasan hanya dapat ditulis oleh pembeli setelah pesanan berstatus selesai.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Area Kanan: Ringkasan Rating & Kartu Ulasan Singkat -->
                <div class="lg:col-span-5 bg-white border border-gray-200/80 p-5 space-y-5 shadow-2xs">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2.5">
                        <h2 class="font-bold text-sm text-gray-900">Ulasan (<?= $totalUlasan ?>)</h2>
                        <?php if ($totalUlasan > 0): ?>
                            <button type="button" id="btn-lihat-ulasan" class="text-xs text-amber-700 hover:text-amber-900 font-semibold transition cursor-pointer">
                                Lihat semua &gt;
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Bintang Ringkasan -->
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1 <?= $totalUlasan > 0 ? 'text-amber-500' : 'text-gray-300' ?> text-base" aria-label="Rating <?= $totalUlasan > 0 ? number_format($avgRating, 1) : '0' ?> dari 5 bintang">
                            <?php $roundedStar = (int)round($avgRating); ?>
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="<?= ($totalUlasan > 0 && $s <= $roundedStar) ? 'ph-fill ph-star' : 'ph ph-star' ?>" aria-hidden="true"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-xs font-bold text-gray-800">
                            <?= $totalUlasan > 0 ? number_format($avgRating, 1) . ' / 5.0' : 'Belum ada rating' ?>
                        </span>
                    </div>

                    <!-- Distribusi Rating 5 Bintang s/d 1 Bintang -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-xs">
                        <?php foreach ([5, 2, 4, 1, 3] as $star): ?>
                            <?php $pct = $totalUlasan > 0 ? (int)round(($ratingCounts[$star] / $totalUlasan) * 100) : 0; ?>
                            <div class="flex items-center gap-2">
                                <span class="w-7 font-semibold text-gray-700 flex items-center gap-0.5 shrink-0">
                                    <?= $star ?> <i class="ph-fill ph-star text-amber-500 text-[11px]" aria-hidden="true"></i>
                                </span>
                                <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full bg-amber-400 rounded-full" style="width: <?= $pct ?>%;"></div>
                                </div>
                                <span class="w-8 text-right text-[11px] font-medium <?= $pct > 0 ? 'text-gray-500' : 'text-gray-400' ?> shrink-0"><?= $pct ?>%</span>
                            </div>
                        <?php endforeach; ?>

                        <div class="flex items-center justify-between text-[11px] <?= $totalUlasan > 0 ? 'text-emerald-800 bg-emerald-50/80' : 'text-gray-500 bg-gray-50' ?> px-2.5 py-1 rounded-md font-medium">
                            <span class="flex items-center gap-1">
                                <i class="ph ph-check-circle <?= $totalUlasan > 0 ? 'text-emerald-600' : 'text-gray-400' ?> text-xs" aria-hidden="true"></i>
                                <span><?= $totalUlasan > 0 ? $puasPct . '% Pembeli Puas' : 'Belum ada ulasan' ?></span>
                            </span>
                            <?php if ($totalUlasan > 0): ?>
                                <span class="text-emerald-700 font-bold"><?= number_format($avgRating, 1) ?>/5.0</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($totalUlasan > 0): ?>
                        <?php foreach (array_slice($reviews, 0, 2) as $rev): ?>
                            <?php
                            $inisialPembeli = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $rev['nama_pembeli']), 0, 1) ?: 'P');
                            $bintang = (int)$rev['rating'];
                            ?>
                            <article class="border-t border-gray-100 pt-3.5 space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center font-bold text-xs shrink-0">
                                        <?= htmlspecialchars($inisialPembeli) ?>
                                    </div>
                                    <div>
                                        <span class="font-bold text-xs text-gray-900 block leading-tight"><?= htmlspecialchars($rev['nama_pembeli']) ?></span>
                                        <time datetime="<?= htmlspecialchars(date('Y-m-d', strtotime($rev['created_at']))) ?>" class="text-[10px] text-gray-400 block">
                                            <?= htmlspecialchars(date('d M Y', strtotime($rev['created_at']))) ?>
                                        </time>
                                    </div>
                                </div>

                                <div class="flex items-center gap-0.5 text-amber-500 text-xs" aria-label="Bintang <?= $bintang ?>">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="<?= $s <= $bintang ? 'ph-fill ph-star' : 'ph ph-star text-gray-300' ?>" aria-hidden="true"></i>
                                    <?php endfor; ?>
                                </div>

                                <?php if (!empty($rev['komentar'])): ?>
                                    <p class="text-xs text-gray-700 leading-normal">
                                        <?= nl2br(htmlspecialchars($rev['komentar'])) ?>
                                    </p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="border-t border-gray-100 pt-3.5 text-xs text-gray-500">
                            Belum ada ulasan pembeli untuk produk ini.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Bagian Produk Terkait -->
        <section class="space-y-4 pt-2" aria-label="Produk Terkait">
            <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-lg font-bold text-gray-900">Produk Terkait</h2>
                <a href="index.php" class="text-xs sm:text-sm font-semibold text-gray-600 hover:text-gray-900 transition flex items-center gap-1">
                    <span>Lihat Lainnya</span>
                    <i class="ph ph-arrow-right text-xs" aria-hidden="true"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php if (!empty($relatedProducts)): ?>
                    <?php foreach ($relatedProducts as $rel): ?>
                        <a href="detail_produk.php?id=<?= (int)$rel['id'] ?>" class="bg-white border border-gray-200/80 p-4 space-y-3 hover:shadow-md hover:border-gray-300 transition flex flex-col justify-between shadow-2xs">
                            <div class="space-y-3">
                                <div class="relative w-full aspect-video bg-gray-100 border border-gray-200 flex items-center justify-center overflow-hidden">
                                    <span class="absolute top-2 left-2 z-10 bg-[#FEF3C7] text-[#92400E] text-[10px] font-semibold px-2 py-0.5 rounded">
                                        <?= htmlspecialchars($rel['kondisi']) ?>
                                    </span>
                                    <?php if (!empty($rel['foto'])): ?>
                                        <img src="<?= htmlspecialchars($rel['foto']) ?>" alt="<?= htmlspecialchars($rel['nama_produk']) ?>" class="w-full h-full object-contain p-2" />
                                    <?php else: ?>
                                        <div class="text-center text-gray-400">
                                            <i class="ph <?= htmlspecialchars(kategoriIcon($rel['nama_kategori'])) ?> text-4xl mb-1 text-gray-500" aria-hidden="true"></i>
                                            <span class="block text-[11px]"><?= htmlspecialchars($rel['nama_kategori']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm text-gray-900 leading-snug line-clamp-2">
                                        <?= htmlspecialchars($rel['nama_produk']) ?>
                                    </h3>
                                    <p class="text-base font-extrabold text-gray-900 mt-1">
                                        <?= formatRupiah((int)$rel['harga']) ?>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs text-gray-500 border-t border-gray-100 pt-2.5">
                                <span class="truncate"><?= htmlspecialchars($rel['nama_penjual']) ?></span>
                                <span class="shrink-0">Stok <?= (int)$rel['stok'] ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="col-span-full text-xs text-gray-500 bg-white border border-gray-200/80 p-6 text-center">
                        Belum ada produk terkait lainnya.
                    </p>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <!-- Dialog Modal: Ajukan Penawaran Harga (Nego) -->
    <dialog id="modal-nego" class="p-6 bg-white rounded-xl border border-gray-300 shadow-2xl w-full max-w-md backdrop:bg-black/40">
        <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                <h3 class="text-base font-bold text-gray-900">Ajukan Penawaran Harga</h3>
                <button type="button" class="close-dialog text-gray-500 hover:text-gray-900 p-1 font-bold text-lg cursor-pointer" aria-label="Tutup dialog">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </div>
            <p class="text-xs text-gray-600">
                Masukkan harga penawaranmu untuk unit <strong><?= htmlspecialchars($namaProduk) ?></strong>. Harga asli: <?= formatRupiah($hargaProduk) ?>.
            </p>
            <div>
                <label for="input-tawaran" class="block text-xs font-semibold text-gray-700 mb-1">Nominal Tawaran (Rp)</label>
                <input
                    type="number"
                    id="input-tawaran"
                    placeholder="Contoh: <?= max(1000, (int)(round(($hargaProduk * 0.9) / 1000) * 1000)) ?>"
                    min="1000"
                    max="<?= $hargaProduk ?>"
                    class="w-full border border-gray-300 rounded-md p-2.5 text-sm text-gray-900 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow" />
            </div>
            <p class="text-[11px] bg-amber-50 border border-amber-200 text-amber-800 rounded-md p-2.5 leading-relaxed">
                <strong>Catatan Priority Queue:</strong> Pengajuan negosiasi tidak mengunci stok barang. Pembeli lain tetap dapat melakukan Beli Langsung selama transaksi belum masuk tahap pembayaran.
            </p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="close-dialog px-4 py-2 border border-gray-300 rounded-md text-xs font-bold text-gray-700 hover:bg-gray-100 cursor-pointer">
                    Batal
                </button>
                <button type="button" id="submit-tawaran" class="px-4 py-2 bg-brand-yellow rounded-md text-brand-dark text-xs font-bold hover:bg-yellow-400 cursor-pointer">
                    Kirim Tawaran
                </button>
            </div>
        </div>
    </dialog>

    <!-- Dialog Modal: Ajukan Barter -->
    <dialog id="modal-barter" class="p-6 bg-white rounded-xl border border-gray-300 shadow-2xl w-full max-w-md backdrop:bg-black/40">
        <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                <h3 class="text-base font-bold text-gray-900">Ajukan Barter Barang</h3>
                <button type="button" class="close-dialog text-gray-500 hover:text-gray-900 p-1 font-bold text-lg cursor-pointer" aria-label="Tutup dialog">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </div>
            <p class="text-xs text-gray-600">
                Tuliskan deskripsi barang elektronik milikmu yang ingin ditukar tambah dengan <strong><?= htmlspecialchars($namaProduk) ?></strong>.
            </p>
            <div>
                <label for="input-barter-item" class="block text-xs font-semibold text-gray-700 mb-1">Barang yang Ditawarkan</label>
                <textarea
                    id="input-barter-item"
                    rows="3"
                    placeholder="Contoh: RAM Corsair Vengeance 2x8GB DDR4 + Tambah dana Rp 200rb"
                    class="w-full border border-gray-300 rounded-md p-2.5 text-sm text-gray-900 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow"></textarea>
            </div>
            <p class="text-[11px] bg-amber-50 border border-amber-200 text-amber-800 rounded-md p-2.5 leading-relaxed">
                <strong>Catatan Priority Queue:</strong> Pengajuan barter tidak mengunci stok barang hingga tawaran disetujui dan masuk tahap pesanan.
            </p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="close-dialog px-4 py-2 border border-gray-300 rounded-md text-xs font-bold text-gray-700 hover:bg-gray-100 cursor-pointer">
                    Batal
                </button>
                <button type="button" id="submit-barter" class="px-4 py-2 bg-brand-yellow rounded-md text-brand-dark text-xs font-bold hover:bg-yellow-400 cursor-pointer">
                    Kirim Pengajuan
                </button>
            </div>
        </div>
    </dialog>

    <!-- Notifikasi Toast Interaktif -->
    <div id="toast-msg" class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg max-w-sm" role="status" aria-live="polite">
        <div class="flex items-center gap-2">
            <i class="ph ph-check-circle text-brand-yellow text-base" aria-hidden="true"></i>
            <span id="toast-text">Berhasil ditambahkan</span>
        </div>
    </div>

    <!-- Footer Standar Loot-E dari components/footer.php -->
    <?php require_once 'components/footer.php'; ?>

    <!-- Skrip Interaktivitas Halaman -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const produkTitleEl = document.getElementById('produk-title');
            const namaProduk = produkTitleEl ? produkTitleEl.textContent.trim() : 'Produk';

            // Logika Tab Navigasi
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabPanels = document.querySelectorAll('.tab-panel');

            function activateTab(targetId) {
                tabLinks.forEach(function(t) {
                    const isActive = t.getAttribute('data-target') === targetId;
                    t.classList.toggle('border-brand-yellow', isActive);
                    t.classList.toggle('text-gray-900', isActive);
                    t.classList.toggle('border-transparent', !isActive);
                    t.classList.toggle('text-gray-500', !isActive);
                    t.setAttribute('aria-selected', String(isActive));
                });

                tabPanels.forEach(function(panel) {
                    panel.classList.toggle('hidden', panel.id !== targetId);
                });
            }

            tabLinks.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    activateTab(this.getAttribute('data-target'));
                });
            });

            const btnLihatUlasan = document.getElementById('btn-lihat-ulasan');
            if (btnLihatUlasan) {
                btnLihatUlasan.addEventListener('click', function() {
                    activateTab('panel-ulasan');
                    const panelUlasan = document.getElementById('panel-ulasan');
                    if (panelUlasan) {
                        panelUlasan.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            }

            // Indikator Statis Rentang Harga Pasar
            const inputTawaran = document.getElementById('input-tawaran');
            if (inputTawaran && !inputTawaran.dataset.userEdited) {
                inputTawaran.value = 680000;
                inputTawaran.addEventListener('input', function() {
                    this.dataset.userEdited = 'true';
                });
            }

            // Logika Dialog Modal dan Tombol Nego / Barter
            const modalNego = document.getElementById('modal-nego');
            const modalBarter = document.getElementById('modal-barter');
            const btnNego = document.getElementById('btn-nego');
            const btnBarter = document.getElementById('btn-barter');
            const closeDialogButtons = document.querySelectorAll('.close-dialog');
            const toast = document.getElementById('toast-msg');
            const toastText = document.getElementById('toast-text');

            function showToast(message) {
                if (!toast || !toastText) return;
                toastText.textContent = message;
                toast.classList.remove('hidden');
                setTimeout(function() {
                    toast.classList.add('hidden');
                }, 3500);
            }

            if (btnNego && modalNego) {
                btnNego.addEventListener('click', function() {
                    modalNego.showModal();
                });
            }

            if (btnBarter && modalBarter) {
                btnBarter.addEventListener('click', function() {
                    modalBarter.showModal();
                });
            }

            // Auto-open modal bila diakses melalui hash #nego atau #barter dari search.php
            if (window.location.hash === '#nego' && btnNego && modalNego) {
                modalNego.showModal();
            } else if (window.location.hash === '#barter' && btnBarter && modalBarter) {
                modalBarter.showModal();
            }

            closeDialogButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (modalNego && modalNego.open) modalNego.close();
                    if (modalBarter && modalBarter.open) modalBarter.close();
                });
            });

            const submitTawaran = document.getElementById('submit-tawaran');
            if (submitTawaran) {
                submitTawaran.addEventListener('click', function() {
                    if (!inputTawaran) return;
                    const nominal = parseInt(inputTawaran.value, 10);
                    const maxHarga = parseInt(inputTawaran.getAttribute('max'), 10);
                    if (!nominal || nominal < 1000 || (maxHarga && nominal > maxHarga)) {
                        alert('Masukkan nominal tawaran yang valid (maksimal Rp ' + maxHarga.toLocaleString('id-ID') + ').');
                        return;
                    }
                    modalNego.close();
                    inputTawaran.value = '';
                    window.location.href = 'status_negosiasi.php?produk_id=<?= (int)$idProduk ?>&tawaran=' + encodeURIComponent(nominal);
                });
            }

            const submitBarter = document.getElementById('submit-barter');
            if (submitBarter) {
                submitBarter.addEventListener('click', function() {
                    const inputBarter = document.getElementById('input-barter-item');
                    if (inputBarter && inputBarter.value.trim()) {
                        modalBarter.close();
                        showToast('Pengajuan barter untuk ' + namaProduk + ' siap diproses.');
                        inputBarter.value = '';
                    } else {
                        alert('Silakan jelaskan barang yang kamu tawarkan.');
                    }
                });
            }

            // Keranjang & Beli Sekarang
            const btnCart = document.getElementById('btn-cart');
            if (btnCart) {
                btnCart.addEventListener('click', function() {
                    const idProduk = this.getAttribute('data-id') || '<?= (int)$idProduk ?>';
                    const formData = new FormData();
                    formData.append('action', 'add_to_cart');
                    formData.append('produk_id', idProduk);
                    formData.append('qty', '1');

                    btnCart.disabled = true;
                    fetch('cart.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            btnCart.disabled = false;
                            if (data.success) {
                                showToast(data.message || (namaProduk + ' berhasil ditambahkan ke keranjang!'));
                            } else {
                                showToast(data.message || 'Gagal menambahkan ke keranjang.');
                            }
                        })
                        .catch(() => {
                            btnCart.disabled = false;
                            showToast('Terjadi kesalahan saat menambahkan ke keranjang.');
                        });
                });
            }

            const btnBuy = document.getElementById('btn-buy');
            if (btnBuy) {
                btnBuy.addEventListener('click', function() {
                    const idProduk = this.getAttribute('data-id') || '<?= (int)$idProduk ?>';
                    const formData = new FormData();
                    formData.append('action', 'add_to_cart');
                    formData.append('produk_id', idProduk);
                    formData.append('qty', '1');

                    btnBuy.disabled = true;
                    fetch('cart.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            btnBuy.disabled = false;
                            if (data.success) {
                                window.location.href = 'cart.php';
                            } else {
                                showToast(data.message || 'Gagal memproses pembelian.');
                            }
                        })
                        .catch(() => {
                            btnBuy.disabled = false;
                            showToast('Terjadi kesalahan saat memproses pembelian.');
                        });
                });
            }
        });
    </script>
</body>

</html>
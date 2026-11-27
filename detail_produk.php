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
$productPhotos = [];
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
        $produk = $db->query($sqlBase . " WHERE p.status_produk = 'tersedia' AND p.stok > 0 ORDER BY p.id ASC LIMIT 1")->fetch() ?: null;
    }

    if ($produk) {
        $idProduk  = (int)$produk['id'];
        $idPenjual = (int)$produk['id_penjual'];

        $stmtPhotos = $db->prepare("
            SELECT path_foto
            FROM produk_foto
            WHERE id_produk = ?
            ORDER BY urutan ASC, id ASC
        ");
        $stmtPhotos->execute([$idProduk]);
        foreach ($stmtPhotos->fetchAll(PDO::FETCH_COLUMN) as $photoPath) {
            $cleanPath = trim((string)$photoPath);
            if ($cleanPath !== '') {
                $productPhotos[] = $cleanPath;
            }
        }
        // Fallback untuk produk lama yang hanya menyimpan foto di kolom produk.foto
        if (empty($productPhotos) && !empty($produk['foto'])) {
            $legacyPath = trim((string)$produk['foto']);
            if ($legacyPath !== '') {
                $productPhotos[] = $legacyPath;
            }
        }

        $stmtRel = $db->prepare("
            SELECT p.id, p.nama_produk, p.foto, p.harga, p.kondisi, p.stok, k.nama_kategori, u.nama AS nama_penjual
            FROM produk p
            JOIN kategori k ON p.id_kategori = k.id
            JOIN pengguna u ON p.id_penjual = u.id
            WHERE p.status_produk = 'tersedia' AND p.stok > 0 AND p.id != ?
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
                (SELECT COUNT(*) FROM produk WHERE id_penjual = ? AND status_produk = 'tersedia' AND stok > 0) AS produk_aktif,
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
    $productPhotos = [];
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
$alamatPenjual   = formatAlamatRingkas($produk['alamat_penjual'] ?? '');
$penjualVerified = (int)($produk['penjual_verified'] ?? 0) === 1;
$isOwnProduct    = ($currentUser['id'] === (int)$produk['id_penjual']); // BR-01

// Produk draft belum dipublikasikan sehingga hanya dapat dilihat pemilik atau admin
if ($statusProduk === 'draft' && !$isOwnProduct && ($currentUser['peran'] ?? 'user') !== 'admin') {
    header("Location: search.php");
    exit;
}

// shortcut: estimasi rentang referensi pasar berbasis ±12% harga produk karena belum ada tabel harga pasar, ganti dengan query referensi saat tersedia
$refStep     = $hargaProduk >= 2000000 ? 50000 : ($hargaProduk >= 500000 ? 25000 : ($hargaProduk >= 100000 ? 10000 : 1000));
$refHargaMin = max($refStep, (int)(round(($hargaProduk * 0.88) / $refStep) * $refStep));
$refHargaMax = max($refHargaMin + $refStep, (int)(round(($hargaProduk * 1.12) / $refStep) * $refStep));
if ($hargaProduk < $refHargaMin) {
    $marketStatusLabel = 'Di bawah pasar';
    $marketStatusClass = 'bg-emerald-100 text-emerald-800';
} elseif ($hargaProduk > $refHargaMax) {
    $marketStatusLabel = 'Di atas pasar';
    $marketStatusClass = 'bg-amber-100 text-amber-900';
} else {
    $marketStatusLabel = 'Sesuai pasar';
    $marketStatusClass = 'bg-emerald-100 text-emerald-800';
}

$defaultTawaran = min($hargaProduk, max(1000, (int)(round(($hargaProduk * 0.9) / 1000) * 1000)));

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

// Susun maks 4 poin ringkasan unit dari data riwayat/spesifikasi asli untuk mengisi ruang setelah banner harga dipersingkat
$quickHighlights = [];
$riwayatOrder = ['kelengkapan', 'fisik', 'lama_pakai', 'minus', 'fungsi', 'servis'];
foreach ($riwayatOrder as $key) {
    if (!empty($parsedDeskripsi['riwayat'][$key])) {
        $quickHighlights[] = [
            'label' => $labelRiwayatMap[$key] ?? ucwords(str_replace('_', ' ', $key)),
            'value' => $parsedDeskripsi['riwayat'][$key],
        ];
    }
    if (count($quickHighlights) >= 4) {
        break;
    }
}
if (count($quickHighlights) < 4 && !empty($parsedDeskripsi['spesifikasi'])) {
    foreach ($parsedDeskripsi['spesifikasi'] as $spec) {
        $quickHighlights[] = [
            'label' => $spec['label'],
            'value' => $spec['nilai'],
        ];
        if (count($quickHighlights) >= 4) {
            break;
        }
    }
}
if (empty($quickHighlights)) {
    $opsiList = ['Beli Langsung'];
    if ($bisaNego) $opsiList[] = 'Nego';
    if ($bisaBarter) $opsiList[] = 'Barter';
    $quickHighlights = [
        ['label' => 'Kondisi', 'value' => $kondisiProduk],
        ['label' => 'Kategori', 'value' => $kategoriNama],
        ['label' => 'Stok Tersedia', 'value' => $stokProduk . ' Unit'],
        ['label' => 'Opsi Transaksi', 'value' => implode(', ', $opsiList)],
    ];
}
$detailTargetTab = !empty($parsedDeskripsi['riwayat'])
    ? 'panel-riwayat'
    : (!empty($parsedDeskripsi['spesifikasi']) ? 'panel-spesifikasi' : 'panel-deskripsi');

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

    <script src="https://unpkg.com/@phosphor-icons/web" defer></script>
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

        button:disabled {
            cursor: not-allowed;
        }

        .gallery-thumbs-scroll {
            scrollbar-width: thin;
            scrollbar-color: #D1D5DB transparent;
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
            <?php
            $totalPhotos = count($productPhotos);
            $hasMultiplePhotos = $totalPhotos > 1;
            ?>
            <div class="md:col-span-12 lg:col-span-5 bg-white border border-gray-200/80 p-4 shadow-2xs">
                <div id="product-gallery" class="<?= $hasMultiplePhotos ? 'flex flex-col-reverse sm:flex-row gap-3' : '' ?>">
                    <?php if ($hasMultiplePhotos): ?>
                        <!-- Rail Pilihan Foto (Kiri di desktop, bawah di mobile) -->
                        <div class="sm:w-[72px] shrink-0 sm:relative">
                            <div
                                id="gallery-thumbs"
                                role="group"
                                aria-label="Pilihan foto produk"
                                class="flex flex-row sm:flex-col gap-2 overflow-x-auto sm:overflow-x-hidden sm:overflow-y-auto sm:absolute sm:inset-0 p-1 gallery-thumbs-scroll">
                                <?php foreach ($productPhotos as $idx => $photoPath): ?>
                                    <?php $isFirst = ($idx === 0); ?>
                                    <button
                                        type="button"
                                        class="gallery-thumb relative w-14 h-14 sm:w-16 sm:h-16 shrink-0 rounded-lg bg-[#FAFAFA] overflow-hidden p-1 transition cursor-pointer flex items-center justify-center <?= $isFirst ? 'border-2 border-brand-dark ring-2 ring-brand-yellow/70 opacity-100' : 'border border-gray-200 opacity-75 hover:opacity-100 hover:border-gray-400' ?>"
                                        data-index="<?= $idx ?>"
                                        data-src="<?= htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8') ?>"
                                        aria-label="Tampilkan foto <?= $idx + 1 ?> dari <?= $totalPhotos ?>"
                                        aria-pressed="<?= $isFirst ? 'true' : 'false' ?>">
                                        <img
                                            src="<?= htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars($namaProduk . ' - foto ' . ($idx + 1), ENT_QUOTES, 'UTF-8') ?>"
                                            width="64"
                                            height="64"
                                            loading="lazy"
                                            class="w-full h-full object-cover rounded-md pointer-events-none" />
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Area Foto Utama -->
                    <div class="relative flex-1 min-w-0 aspect-square bg-[#FAFAFA] border border-gray-200/80 rounded-lg flex items-center justify-center p-5 sm:p-6 overflow-hidden">
                        <!-- Badge Kondisi -->
                        <span class="absolute top-3 left-3 z-10 bg-[#FEF3C7] text-[#92400E] text-xs font-semibold px-2.5 py-1 rounded-md tracking-wide">
                            <?= htmlspecialchars($kondisiProduk) ?>
                        </span>

                        <?php if ($totalPhotos > 0): ?>
                            <img
                                id="gallery-main-img"
                                src="<?= htmlspecialchars($productPhotos[0], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($namaProduk, ENT_QUOTES, 'UTF-8') ?>"
                                width="600"
                                height="600"
                                fetchpriority="high"
                                class="w-full h-full object-contain transition-opacity duration-150" />

                            <!-- Status Memuat & Gagal Muat Foto -->
                            <div id="gallery-loading" class="hidden absolute inset-0 bg-[#FAFAFA]/80 flex items-center justify-center text-xs font-semibold text-gray-700" role="status" aria-live="polite">
                                Memuat foto...
                            </div>
                            <div id="gallery-error" class="hidden absolute inset-0 bg-[#FAFAFA] flex flex-col items-center justify-center text-center p-6" role="alert">
                                <i class="ph ph-image-broken text-4xl text-gray-400 mb-2" aria-hidden="true"></i>
                                <p class="text-xs font-semibold text-gray-800">Foto tidak dapat dimuat</p>
                                <p class="text-[11px] text-gray-500 mt-1">Pilih foto lain atau muat ulang halaman.</p>
                            </div>

                            <?php if ($hasMultiplePhotos): ?>
                                <!-- Navigasi Foto Sebelumnya & Berikutnya -->
                                <button
                                    type="button"
                                    id="btn-gallery-prev"
                                    disabled
                                    aria-label="Foto sebelumnya"
                                    class="absolute left-2.5 top-1/2 -translate-y-1/2 z-10 w-11 h-11 rounded-full bg-white/95 hover:bg-white border border-gray-200 text-gray-800 shadow-xs flex items-center justify-center transition cursor-pointer disabled:opacity-35 disabled:cursor-not-allowed">
                                    <i class="ph-bold ph-caret-left text-base" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    id="btn-gallery-next"
                                    aria-label="Foto berikutnya"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 z-10 w-11 h-11 rounded-full bg-white/95 hover:bg-white border border-gray-200 text-gray-800 shadow-xs flex items-center justify-center transition cursor-pointer disabled:opacity-35 disabled:cursor-not-allowed">
                                    <i class="ph-bold ph-caret-right text-base" aria-hidden="true"></i>
                                </button>

                                <!-- Indikator Urutan Foto -->
                                <span
                                    id="gallery-counter"
                                    aria-live="polite"
                                    class="absolute bottom-3 right-3 z-10 bg-white/95 border border-gray-200 text-gray-700 text-xs font-semibold px-2.5 py-1 rounded-full shadow-xs tabular-nums">
                                    1/<?= $totalPhotos ?>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="w-full h-full flex flex-col items-center justify-center text-center p-4">
                                <i class="ph <?= htmlspecialchars(kategoriIcon($kategoriNama)) ?> text-7xl text-gray-700 mb-2" aria-hidden="true"></i>
                                <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($namaProduk) ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($kategoriNama) ?> &bull; <?= htmlspecialchars($kondisiProduk) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Kolom 2: Detail Produk, Harga, Banner Referensi Harga, Ringkasan Unit, dan Aksi -->
            <div class="md:col-span-12 lg:col-span-4 bg-white border border-gray-200/80 p-5 sm:p-6 space-y-4 shadow-2xs">
                <!-- Judul Produk -->
                <div>
                    <h1 id="produk-title" class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight">
                        <?= htmlspecialchars($namaProduk) ?>
                    </h1>

                    <!-- Rating & Meta -->
                    <div class="flex flex-wrap items-center gap-1.5 mt-2 text-xs text-gray-500">
                        <?php if ($totalUlasan > 0): ?>
                            <div class="flex items-center gap-1 text-gray-900 font-semibold">
                                <i class="ph-fill ph-star text-amber-500 text-sm" aria-hidden="true"></i>
                                <span><?= number_format($avgRating, 1) ?></span>
                                <span class="text-gray-500 font-normal">(<?= $totalUlasan ?> ulasan)</span>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center gap-1 text-gray-500 font-medium">
                                <i class="ph ph-star text-sm" aria-hidden="true"></i>
                                <span>Belum ada ulasan</span>
                            </div>
                        <?php endif; ?>
                        <span aria-hidden="true" class="text-gray-300">&bull;</span>
                        <span><?= htmlspecialchars($kategoriNama) ?></span>
                        <span class="bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2 py-0.5 rounded-full">
                            <?= htmlspecialchars($kondisiProduk) ?>
                        </span>
                    </div>
                </div>

                <!-- Bagian Harga & Stok -->
                <div class="flex items-baseline justify-between gap-3 pt-2 border-t border-gray-100">
                    <span class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-tight tabular-nums">
                        <?= formatRupiah($hargaProduk) ?>
                    </span>
                    <span class="text-xs text-gray-500 font-medium shrink-0">
                        Stok: <span class="font-semibold text-gray-700"><?= $stokProduk ?></span>
                    </span>
                </div>

                <!-- Banner Referensi Harga (Minimalis, 2 baris agar angka tidak terpotong) -->
                <div class="bg-[#FFFDF5] border border-amber-200/80 rounded-lg px-3.5 py-2.5 space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 text-[11px] font-medium text-amber-900">
                            <i class="ph-fill ph-tag text-amber-600 text-sm shrink-0" aria-hidden="true"></i>
                            <span>Referensi Harga Pasar</span>
                        </div>
                        <button
                            type="button"
                            id="btn-info-harga"
                            aria-expanded="false"
                            aria-controls="price-info-note"
                            aria-label="Penjelasan referensi harga pasar"
                            class="w-7 h-7 -mr-1 flex items-center justify-center rounded-md text-amber-900 hover:bg-amber-100 transition cursor-pointer shrink-0">
                            <i class="ph ph-info text-base" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-bold text-gray-900 tabular-nums">
                            <?= formatRupiah($refHargaMin) ?> - <?= formatRupiah($refHargaMax) ?>
                        </span>
                        <span id="market-status-badge" class="<?= htmlspecialchars($marketStatusClass) ?> text-[11px] font-semibold px-2 py-0.5 rounded-md shrink-0">
                            <?= htmlspecialchars($marketStatusLabel) ?>
                        </span>
                    </div>
                    <p id="price-info-note" class="hidden text-[11px] text-gray-600 leading-relaxed border-t border-amber-200/70 pt-2">
                        Estimasi kisaran harga pasar membantu kamu menilai kewajaran harga produk. Harga akhir tetap ditentukan oleh penjual.
                    </p>
                </div>

                <!-- Ringkasan Unit -->
                <div class="bg-gray-50/80 border border-gray-200/80 rounded-lg p-3.5 space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-xs font-bold text-gray-900">Ringkasan Unit</h2>
                        <button
                            type="button"
                            id="btn-lihat-detail-unit"
                            data-target="<?= htmlspecialchars($detailTargetTab) ?>"
                            class="text-[11px] font-semibold text-amber-800 hover:text-amber-950 transition cursor-pointer">
                            Lihat lengkap
                        </button>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs">
                        <?php foreach ($quickHighlights as $item): ?>
                            <div class="min-w-0">
                                <dt class="text-[11px] text-gray-500"><?= htmlspecialchars($item['label']) ?></dt>
                                <dd class="font-semibold text-gray-900 leading-snug line-clamp-2" title="<?= htmlspecialchars($item['value']) ?>">
                                    <?= htmlspecialchars($item['value']) ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>

                <!-- Badges Fasilitas Transaksi -->
                <?php if ($bisaNego || $bisaBarter): ?>
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
                <?php endif; ?>

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
                        <?php if ($statusProduk === 'nonaktif'): ?>
                            <p class="font-bold mb-0.5">Produk Dinonaktifkan</p>
                            <p>Penjual sedang menonaktifkan produk ini untuk sementara.</p>
                        <?php else: ?>
                            <p class="font-bold mb-0.5">Produk Habis Terjual</p>
                            <p>Stok produk ini sudah habis terjual.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if ($stokProduk > 1): ?>
                        <!-- Pemilih Jumlah Beli (hanya tampil jika stok > 1 pcs) -->
                        <div class="flex items-center justify-between gap-3 pt-2 border-t border-gray-100">
                            <div class="flex items-center gap-2.5">
                                <label for="input-qty" class="text-xs font-semibold text-gray-700">Jumlah</label>
                                <div class="inline-flex items-center border border-gray-300 rounded-md overflow-hidden bg-white">
                                    <button
                                        type="button"
                                        id="btn-qty-minus"
                                        aria-label="Kurangi jumlah"
                                        disabled
                                        class="w-9 h-9 flex items-center justify-center text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                                        <i class="ph ph-minus text-xs font-bold" aria-hidden="true"></i>
                                    </button>
                                    <input
                                        type="number"
                                        id="input-qty"
                                        value="1"
                                        min="1"
                                        max="<?= $stokProduk ?>"
                                        inputmode="numeric"
                                        aria-label="Jumlah pembelian"
                                        class="w-11 h-9 text-center text-xs font-bold text-gray-900 border-x border-gray-200 focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                    <button
                                        type="button"
                                        id="btn-qty-plus"
                                        aria-label="Tambah jumlah"
                                        class="w-9 h-9 flex items-center justify-center text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                                        <i class="ph ph-plus text-xs font-bold" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-right min-w-0">
                                <span class="block text-[11px] text-gray-500">Subtotal</span>
                                <span id="subtotal-display" class="block text-sm font-extrabold text-gray-900 tabular-nums">
                                    <?= formatRupiah($hargaProduk) ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 gap-2.5 pt-1">
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
        <section id="section-tab-detail" class="bg-white border border-gray-200/80 overflow-hidden shadow-2xs" aria-label="Tab Detail Produk">
            <div class="border-b border-gray-200 px-4 sm:px-6 overflow-x-auto" role="tablist">
                <nav class="flex gap-2 sm:gap-6 min-w-max" aria-label="Bagian Detail">
                    <button
                        type="button"
                        class="tab-link py-3.5 px-3 text-xs sm:text-sm font-bold border-b-2 border-brand-yellow text-gray-900 cursor-pointer"
                        data-target="panel-deskripsi"
                        role="tab"
                        aria-selected="true"
                        aria-controls="panel-deskripsi">
                        Deskripsi
                    </button>
                    <button
                        type="button"
                        class="tab-link py-3.5 px-3 text-xs sm:text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-900 cursor-pointer transition"
                        data-target="panel-spesifikasi"
                        role="tab"
                        aria-selected="false"
                        aria-controls="panel-spesifikasi">
                        Spesifikasi
                    </button>
                    <button
                        type="button"
                        class="tab-link py-3.5 px-3 text-xs sm:text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-900 cursor-pointer transition"
                        data-target="panel-riwayat"
                        role="tab"
                        aria-selected="false"
                        aria-controls="panel-riwayat">
                        Riwayat &amp; Kondisi
                    </button>
                    <button
                        type="button"
                        class="tab-link py-3.5 px-3 text-xs sm:text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-900 cursor-pointer transition"
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
                                        <img src="<?= htmlspecialchars($rel['foto']) ?>" alt="<?= htmlspecialchars($rel['nama_produk']) ?>" width="400" height="225" loading="lazy" class="w-full h-full object-contain p-2" />
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
                    placeholder="Contoh: <?= $defaultTawaran ?>"
                    min="1000"
                    max="<?= $hargaProduk ?>"
                    class="w-full border border-gray-300 rounded-md p-2.5 text-sm text-gray-900 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow" />
            </div>
            <p class="text-[11px] bg-amber-50 border border-amber-200 text-amber-800 rounded-md p-2.5 leading-relaxed">
                <strong>Catatan Penawaran:</strong> Pengajuan negosiasi tidak memotong stok barang. Pembeli lain tetap dapat membeli langsung atau mengajukan tawaran lain; tawaranmu otomatis gagal jika stok habis lebih dulu.
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
                <strong>Catatan Penawaran:</strong> Pengajuan barter tidak memotong stok barang hingga kesepakatan disetujui dan pesanan dibuat. Tawaran otomatis gagal jika salah satu produk habis terjual lebih dulu.
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

            // Galeri Multi-Foto Produk
            const galleryMainImg = document.getElementById('gallery-main-img');
            const galleryLoading = document.getElementById('gallery-loading');
            const galleryError = document.getElementById('gallery-error');
            const galleryThumbs = Array.from(document.querySelectorAll('.gallery-thumb'));
            const galleryCounter = document.getElementById('gallery-counter');
            const btnGalleryPrev = document.getElementById('btn-gallery-prev');
            const btnGalleryNext = document.getElementById('btn-gallery-next');
            const galleryRoot = document.getElementById('product-gallery');

            if (galleryMainImg) {
                galleryMainImg.addEventListener('load', function() {
                    if (galleryLoading) galleryLoading.classList.add('hidden');
                    if (galleryError) galleryError.classList.add('hidden');
                    galleryMainImg.classList.remove('opacity-0');
                });
                galleryMainImg.addEventListener('error', function() {
                    if (galleryLoading) galleryLoading.classList.add('hidden');
                    if (galleryError) galleryError.classList.remove('hidden');
                    galleryMainImg.classList.add('opacity-0');
                });
                if (galleryMainImg.complete && galleryMainImg.naturalWidth === 0) {
                    if (galleryError) galleryError.classList.remove('hidden');
                    galleryMainImg.classList.add('opacity-0');
                }
            }

            if (galleryMainImg && galleryThumbs.length > 1) {
                let currentPhotoIndex = 0;
                const totalPhotos = galleryThumbs.length;

                function setActivePhoto(nextIndex) {
                    const clamped = Math.max(0, Math.min(nextIndex, totalPhotos - 1));
                    const targetBtn = galleryThumbs[clamped];
                    if (!targetBtn) return;

                    currentPhotoIndex = clamped;
                    const nextSrc = targetBtn.getAttribute('data-src') || '';

                    if (nextSrc && galleryMainImg.getAttribute('src') !== nextSrc) {
                        if (galleryError) galleryError.classList.add('hidden');
                        if (galleryLoading) galleryLoading.classList.remove('hidden');
                        galleryMainImg.classList.add('opacity-0');
                        galleryMainImg.setAttribute('src', nextSrc);
                        galleryMainImg.setAttribute('alt', namaProduk + ' - foto ' + (clamped + 1) + ' dari ' + totalPhotos);
                        if (galleryMainImg.complete && galleryMainImg.naturalWidth > 0) {
                            if (galleryLoading) galleryLoading.classList.add('hidden');
                            galleryMainImg.classList.remove('opacity-0');
                        }
                    }

                    galleryThumbs.forEach(function(btn, idx) {
                        const isActive = idx === clamped;
                        btn.setAttribute('aria-pressed', String(isActive));
                        btn.classList.toggle('border-2', isActive);
                        btn.classList.toggle('border-brand-dark', isActive);
                        btn.classList.toggle('ring-2', isActive);
                        btn.classList.toggle('ring-brand-yellow/70', isActive);
                        btn.classList.toggle('opacity-100', isActive);
                        btn.classList.toggle('border', !isActive);
                        btn.classList.toggle('border-gray-200', !isActive);
                        btn.classList.toggle('opacity-75', !isActive);
                        btn.classList.toggle('hover:opacity-100', !isActive);
                        btn.classList.toggle('hover:border-gray-400', !isActive);
                    });

                    targetBtn.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest',
                        inline: 'nearest'
                    });

                    if (galleryCounter) {
                        galleryCounter.textContent = (clamped + 1) + '/' + totalPhotos;
                    }
                    if (btnGalleryPrev) btnGalleryPrev.disabled = clamped <= 0;
                    if (btnGalleryNext) btnGalleryNext.disabled = clamped >= totalPhotos - 1;
                }

                galleryThumbs.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        const idx = parseInt(this.getAttribute('data-index') || '0', 10);
                        setActivePhoto(idx);
                    });
                });

                if (btnGalleryPrev) {
                    btnGalleryPrev.addEventListener('click', function() {
                        setActivePhoto(currentPhotoIndex - 1);
                    });
                }

                if (btnGalleryNext) {
                    btnGalleryNext.addEventListener('click', function() {
                        setActivePhoto(currentPhotoIndex + 1);
                    });
                }

                if (galleryRoot) {
                    galleryRoot.addEventListener('keydown', function(e) {
                        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                            if (currentPhotoIndex < totalPhotos - 1) {
                                e.preventDefault();
                                setActivePhoto(currentPhotoIndex + 1);
                            }
                        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                            if (currentPhotoIndex > 0) {
                                e.preventDefault();
                                setActivePhoto(currentPhotoIndex - 1);
                            }
                        }
                    });
                }
            }

            // Toggle keterangan singkat pada banner referensi harga
            const btnInfoHarga = document.getElementById('btn-info-harga');
            const priceInfoNote = document.getElementById('price-info-note');
            if (btnInfoHarga && priceInfoNote) {
                btnInfoHarga.addEventListener('click', function() {
                    const isOpen = !priceInfoNote.classList.contains('hidden');
                    priceInfoNote.classList.toggle('hidden', isOpen);
                    this.setAttribute('aria-expanded', String(!isOpen));
                });
            }

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

            // Tombol "Lihat lengkap" di Ringkasan Unit mengarah langsung ke tab detail yang relevan
            const btnLihatDetailUnit = document.getElementById('btn-lihat-detail-unit');
            if (btnLihatDetailUnit) {
                btnLihatDetailUnit.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-target') || 'panel-deskripsi';
                    activateTab(targetTab);
                    const tabSection = document.getElementById('section-tab-detail');
                    if (tabSection) {
                        tabSection.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            }

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

            // Default nominal tawaran mengikuti 90% harga listing produk
            const inputTawaran = document.getElementById('input-tawaran');
            const defaultTawaran = <?= (int)$defaultTawaran ?>;
            if (inputTawaran && !inputTawaran.dataset.userEdited) {
                inputTawaran.value = defaultTawaran;
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

            // Pemilih Jumlah Beli (Quantity Stepper) & Subtotal
            const hargaSatuan = <?= (int)$hargaProduk ?>;
            const maxStok = <?= max(1, (int)$stokProduk) ?>;
            const inputQty = document.getElementById('input-qty');
            const btnQtyMinus = document.getElementById('btn-qty-minus');
            const btnQtyPlus = document.getElementById('btn-qty-plus');
            const subtotalDisplay = document.getElementById('subtotal-display');

            function getSelectedQty() {
                if (!inputQty) return 1;
                const parsed = parseInt(inputQty.value, 10);
                if (isNaN(parsed) || parsed < 1) return 1;
                return Math.min(parsed, maxStok);
            }

            function syncQtyUI(nextQty) {
                const clamped = Math.max(1, Math.min(nextQty, maxStok));
                if (inputQty) inputQty.value = String(clamped);
                if (btnQtyMinus) btnQtyMinus.disabled = clamped <= 1;
                if (btnQtyPlus) btnQtyPlus.disabled = clamped >= maxStok;
                if (subtotalDisplay) {
                    subtotalDisplay.textContent = 'Rp ' + (hargaSatuan * clamped).toLocaleString('id-ID');
                }
            }

            if (inputQty) {
                if (btnQtyMinus) {
                    btnQtyMinus.addEventListener('click', function() {
                        syncQtyUI(getSelectedQty() - 1);
                    });
                }
                if (btnQtyPlus) {
                    btnQtyPlus.addEventListener('click', function() {
                        syncQtyUI(getSelectedQty() + 1);
                    });
                }
                inputQty.addEventListener('change', function() {
                    syncQtyUI(getSelectedQty());
                });
                syncQtyUI(1);
            }

            // Keranjang & Beli Sekarang
            const btnCart = document.getElementById('btn-cart');
            if (btnCart) {
                btnCart.addEventListener('click', function() {
                    const idProduk = this.getAttribute('data-id') || '<?= (int)$idProduk ?>';
                    const qty = getSelectedQty();
                    const formData = new FormData();
                    formData.append('action', 'add_to_cart');
                    formData.append('produk_id', idProduk);
                    formData.append('qty', String(qty));

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
                                showToast(data.message || (namaProduk + ' (' + qty + ' pcs) berhasil ditambahkan ke keranjang!'));
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
                    const qty = getSelectedQty();
                    const formData = new FormData();
                    formData.append('action', 'add_to_cart');
                    formData.append('produk_id', idProduk);
                    formData.append('qty', String(qty));

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

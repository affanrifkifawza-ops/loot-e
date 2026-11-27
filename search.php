<?php
require_once "auth.php";

if (!isLoggedIn()) {
    $redirectUrl = "search.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header("Location: login.php?redirect=" . urlencode($redirectUrl));
    exit;
}

$currentUser = getCurrentUser();
$navActivePage = '';

$searchQuery    = trim($_GET['q'] ?? '');
$kategoriFilter = isset($_GET['kategori']) && is_numeric($_GET['kategori']) ? (int)$_GET['kategori'] : null;
$kondisiFilter  = in_array($_GET['kondisi'] ?? '', ['Baru', 'Bekas'], true) ? $_GET['kondisi'] : '';
$minPrice       = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? max(0, (int)$_GET['min_price']) : null;
$maxPrice       = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? max(0, (int)$_GET['max_price']) : null;
if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
}
$filterNego     = !empty($_GET['nego']);
$filterBarter   = !empty($_GET['barter']);
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 6;

$products = [];
$totalResults = 0;
$totalPages = 1;
$allCategories = [];
$activeCategoryName = '';

try {
    $db = getDbConnection();

    // Daftar kategori beserta jumlah produk yang tersedia (menyesuaikan kata kunci pencarian jika ada)
    if ($searchQuery !== '') {
        $catTerm = "%" . $searchQuery . "%";
        $stmtCat = $db->prepare("
            SELECT k.id, k.nama_kategori, COUNT(p.id) AS total_produk
            FROM kategori k
            LEFT JOIN produk p
                ON k.id = p.id_kategori
               AND p.status_produk = 'tersedia'
               AND p.stok > 0
               AND (p.nama_produk LIKE ? OR k.nama_kategori LIKE ?)
            GROUP BY k.id, k.nama_kategori
            ORDER BY k.id ASC
        ");
        $stmtCat->execute([$catTerm, $catTerm]);
        $allCategories = $stmtCat->fetchAll();
    } else {
        $allCategories = $db->query("
            SELECT k.id, k.nama_kategori, COUNT(p.id) AS total_produk
            FROM kategori k
            LEFT JOIN produk p ON k.id = p.id_kategori AND p.status_produk = 'tersedia' AND p.stok > 0
            GROUP BY k.id, k.nama_kategori
            ORDER BY k.id ASC
        ")->fetchAll();
    }

    if ($kategoriFilter !== null) {
        foreach ($allCategories as $cat) {
            if ((int)$cat['id'] === $kategoriFilter) {
                $activeCategoryName = $cat['nama_kategori'];
                break;
            }
        }
    }

    // Kondisi filter disusun dinamis, semua nilai lewat prepared statement
    $where  = ["p.status_produk = 'tersedia'", "p.stok > 0"];
    $params = [];

    if ($searchQuery !== '') {
        $where[] = "(p.nama_produk LIKE ? OR k.nama_kategori LIKE ?)";
        $term = "%" . $searchQuery . "%";
        array_push($params, $term, $term);
    }
    if ($kategoriFilter) {
        $where[]  = "p.id_kategori = ?";
        $params[] = $kategoriFilter;
    }
    if ($kondisiFilter === 'Baru') {
        $where[] = "p.kondisi = 'Baru'";
    } elseif ($kondisiFilter === 'Bekas') {
        $where[] = "p.kondisi LIKE 'Bekas%'";
    }
    if ($minPrice !== null) {
        $where[]  = "p.harga >= ?";
        $params[] = $minPrice;
    }
    if ($maxPrice !== null) {
        $where[]  = "p.harga <= ?";
        $params[] = $maxPrice;
    }
    if ($filterNego && $filterBarter) {
        $where[] = "(p.bisa_nego = 1 OR p.bisa_barter = 1)";
    } elseif ($filterNego) {
        $where[] = "p.bisa_nego = 1";
    } elseif ($filterBarter) {
        $where[] = "p.bisa_barter = 1";
    }

    $whereSql = implode(' AND ', $where);

    $stmtCount = $db->prepare("
        SELECT COUNT(*) FROM produk p
        JOIN kategori k ON p.id_kategori = k.id
        WHERE $whereSql
    ");
    $stmtCount->execute($params);
    $totalResults = (int)$stmtCount->fetchColumn();
    $totalPages   = max(1, (int)ceil($totalResults / $perPage));
    $page         = min($page, $totalPages);
    $offset       = ($page - 1) * $perPage;

    // $perPage dan $offset sudah berupa integer
    $stmt = $db->prepare("
        SELECT p.*, k.nama_kategori, u.nama AS nama_penjual,
               (SELECT AVG(rating) FROM ulasan WHERE id_produk = p.id) AS avg_rating,
               (SELECT COUNT(*) FROM ulasan WHERE id_produk = p.id) AS total_reviews
        FROM produk p
        JOIN kategori k ON p.id_kategori = k.id
        JOIN pengguna u ON p.id_penjual = u.id
        WHERE $whereSql
        ORDER BY p.harga ASC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

if ($activeCategoryName !== '' && $searchQuery !== '') {
    $pageHeading = strcasecmp($searchQuery, $activeCategoryName) === 0
        ? $activeCategoryName
        : $searchQuery . ' — ' . $activeCategoryName;
} elseif ($activeCategoryName !== '') {
    $pageHeading = $activeCategoryName;
} elseif ($searchQuery !== '') {
    $pageHeading = $searchQuery;
} else {
    $pageHeading = 'Semua Produk';
}
$pageTitle = $pageHeading === 'Semua Produk' ? 'Pencarian' : $pageHeading;

// Query string filter untuk link pagination agar filter tidak hilang
$filterQuery = http_build_query(array_filter([
    'q'         => $searchQuery,
    'kategori'  => $kategoriFilter,
    'kondisi'   => $kondisiFilter,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'nego'      => $filterNego ? 1 : null,
    'barter'    => $filterBarter ? 1 : null,
], fn($v) => $v !== null && $v !== ''));
$pageUrl = fn(int $n) => 'search.php?' . $filterQuery . ($filterQuery !== '' ? '&' : '') . 'page=' . $n;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle) ?> | Loot-E</title>
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
        input:focus-visible {
            outline: 2px solid #FFC914;
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="bg-[#F8F9FA] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Navbar Utama -->
    <?php
    $navSearchQuery = $searchQuery;
    require_once 'components/navbar.php';
    ?>

    <main class="flex-1 max-w-7xl w-full mx-auto px-6 md:px-12 py-8">
        <nav aria-label="Breadcrumb" class="text-xs font-semibold text-gray-500 mb-4">
            <a href="categories.php" class="hover:text-brand-dark transition">Kategori</a>
            <span class="mx-1 text-gray-400">/</span>
            <span class="text-gray-800">Pencarian</span>
        </nav>
        <!-- Header Judul Pencarian -->
        <div class="mb-6">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-[#272727] tracking-tight">
                &ldquo;<?= htmlspecialchars($pageHeading) ?>&rdquo;
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 font-medium mt-1">
                <?= htmlspecialchars((string)$totalResults) ?> Hasil ditemukan
            </p>
        </div>

        <!-- Tombol Toggle Filter untuk Layar Mobile -->
        <div class="md:hidden mb-4">
            <button type="button" id="toggleFilterBtn"
                class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-white border border-gray-200 rounded-lg text-xs font-bold text-[#272727] shadow-xs active:bg-gray-50">
                <i class="ph ph-sliders text-base text-brand-yellow"></i>
                <span>Tampilkan Filter Pencarian</span>
            </button>
        </div>

        <div class="flex flex-col md:flex-row items-start gap-8">
            <!-- Sidebar Filter Kiri -->
            <aside id="filterSidebar" class="hidden md:block w-full md:w-60 lg:w-64 shrink-0 bg-transparent">
                <form action="search.php" method="GET" class="bg-white border border-gray-100 p-5 shadow-2xs">
                    <!-- Menyimpan query pencarian saat filter diubah -->
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>" />

                    <div class="flex items-center justify-between pb-3 border-b border-gray-200">
                        <h2 class="text-base font-bold text-[#272727]">Filter</h2>
                        <?php if ($searchQuery !== '' || $kategoriFilter || $kondisiFilter || $minPrice !== null || $maxPrice !== null || $filterNego || $filterBarter): ?>
                            <a href="search.php" class="text-[11px] font-semibold text-red-500 hover:underline">Reset</a>
                        <?php endif; ?>
                    </div>

                    <!-- Filter Kategori Dinamis dari Database -->
                    <div class="py-4 border-b border-gray-200">
                        <h3 class="text-xs font-bold text-[#272727] mb-2.5">Kategori</h3>
                        <div class="space-y-2 text-xs text-gray-700">
                            <label class="flex items-center gap-2 cursor-pointer hover:text-black">
                                <input type="radio" name="kategori" value="" <?= $kategoriFilter === null ? 'checked' : '' ?> onchange="this.form.submit()" class="w-3.5 h-3.5 text-[#272727] focus:ring-brand-yellow border-gray-300" />
                                <span>Semua Kategori</span>
                            </label>
                            <?php foreach ($allCategories as $cat): ?>
                                <label class="flex items-center gap-2 cursor-pointer hover:text-black">
                                    <input type="radio" name="kategori" value="<?= htmlspecialchars((string)$cat['id']) ?>"
                                        <?= $kategoriFilter === (int)$cat['id'] ? 'checked' : '' ?>
                                        onchange="this.form.submit()"
                                        class="w-3.5 h-3.5 text-[#272727] focus:ring-brand-yellow border-gray-300" />
                                    <span><?= htmlspecialchars($cat['nama_kategori']) ?> (<?= htmlspecialchars((string)$cat['total_produk']) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Filter Kondisi -->
                    <div class="py-4 border-b border-gray-200">
                        <h3 class="text-xs font-bold text-[#272727] mb-2.5">Kondisi</h3>
                        <div class="space-y-2 text-xs text-gray-700">
                            <label class="flex items-center gap-2 cursor-pointer hover:text-black">
                                <input type="radio" name="kondisi" value="" <?= $kondisiFilter === '' ? 'checked' : '' ?> onchange="this.form.submit()" class="w-3.5 h-3.5 text-[#272727] focus:ring-brand-yellow border-gray-300" />
                                <span>Semua Kondisi</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer hover:text-black">
                                <input type="radio" name="kondisi" value="Baru" <?= $kondisiFilter === 'Baru' ? 'checked' : '' ?> onchange="this.form.submit()" class="w-3.5 h-3.5 text-[#272727] focus:ring-brand-yellow border-gray-300" />
                                <span>Baru</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer hover:text-black">
                                <input type="radio" name="kondisi" value="Bekas" <?= $kondisiFilter === 'Bekas' ? 'checked' : '' ?> onchange="this.form.submit()" class="w-3.5 h-3.5 text-[#272727] focus:ring-brand-yellow border-gray-300" />
                                <span>Bekas</span>
                            </label>
                        </div>
                    </div>

                    <!-- Filter Harga -->
                    <div class="py-4 border-b border-gray-200">
                        <h3 class="text-xs font-bold text-[#272727] mb-2.5">Harga</h3>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <input type="number" name="min_price" min="0" value="<?= $minPrice !== null ? htmlspecialchars((string)$minPrice) : '' ?>" placeholder="Min" aria-label="Harga Minimum"
                                class="w-full border border-gray-300 rounded px-2.5 py-1 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow" />
                            <input type="number" name="max_price" min="0" value="<?= $maxPrice !== null ? htmlspecialchars((string)$maxPrice) : '' ?>" placeholder="Maks" aria-label="Harga Maksimum"
                                class="w-full border border-gray-300 rounded px-2.5 py-1 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow" />
                        </div>
                        <button type="submit"
                            class="w-full bg-[#272727] text-white py-1.5 text-xs font-semibold hover:bg-neutral-800 active:scale-[0.99] transition shadow-2xs">
                            Terapkan
                        </button>
                    </div>

                    <!-- Filter Opsi Transaksi -->
                    <div class="pt-4">
                        <h3 class="text-xs font-bold text-[#272727] mb-2.5">Opsi Transaksi</h3>
                        <div class="space-y-2 text-xs text-gray-700">
                            <label class="flex items-center gap-2 cursor-pointer hover:text-black">
                                <input type="checkbox" name="nego" value="1" <?= $filterNego ? 'checked' : '' ?> onchange="this.form.submit()" class="w-3.5 h-3.5 rounded text-[#272727] focus:ring-brand-yellow border-gray-300" />
                                <span>Bisa Nego</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer hover:text-black">
                                <input type="checkbox" name="barter" value="1" <?= $filterBarter ? 'checked' : '' ?> onchange="this.form.submit()" class="w-3.5 h-3.5 rounded text-[#272727] focus:ring-brand-yellow border-gray-300" />
                                <span>Bisa Barter</span>
                            </label>
                        </div>
                    </div>
                </form>
            </aside>

            <!-- Kolom Kanan: Hasil Pencarian Real dari Database -->
            <section aria-label="Hasil Produk" class="flex-1 w-full">
                <?php if (!empty($products)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php foreach ($products as $item): ?>
                            <?php
                            $catIcon = kategoriIcon($item['nama_kategori']);
                            $isBaru = $item['kondisi'] === 'Baru';
                            $badgeLabel = $isBaru ? 'Baru' : 'Bekas';
                            $reviewVal = (int)$item['total_reviews'];
                            $ratingVal = number_format((float)$item['avg_rating'], 1);
                            ?>
                            <article class="bg-white border border-gray-200/80 p-4 shadow-2xs hover:shadow-md hover:border-gray-300/80 transition flex flex-col group">
                                <!-- Baris Atas: Badge Kondisi & Kategori -->
                                <div class="flex items-center justify-between gap-2 mb-2.5">
                                    <span class="<?= $isBaru ? 'bg-emerald-50 text-emerald-700' : 'bg-[#FFF8E1] text-[#B88A00]' ?> text-[10px] font-bold px-2 py-0.5 rounded-md shrink-0">
                                        <?= htmlspecialchars($badgeLabel) ?>
                                    </span>
                                    <span class="text-[11px] font-semibold text-gray-500 truncate">
                                        <?= htmlspecialchars($item['nama_kategori'] ?? 'Elektronik') ?>
                                    </span>
                                </div>

                                <!-- Gambar, Judul, Harga & Tagline Opsi Transaksi -->
                                <a href="detail_produk.php?id=<?= (int)$item['id'] ?>" class="flex-1 flex flex-col">
                                    <div class="w-full h-44 overflow-hidden flex items-center justify-center p-3 relative mb-3 bg-[#FAFAFA] border border-gray-100">
                                        <?php if (!empty($item['foto'])): ?>
                                            <img src="<?= htmlspecialchars($item['foto']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition" />
                                        <?php else: ?>
                                            <div class="w-full h-full flex flex-col items-center justify-center bg-neutral-900 text-white p-3 relative shadow-inner">
                                                <span class="text-[9px] font-extrabold tracking-widest text-brand-yellow uppercase mb-1">
                                                    <?= htmlspecialchars($item['nama_kategori'] ?? 'Loot-E') ?>
                                                </span>
                                                <i class="ph <?= $catIcon ?> text-4xl text-gray-200 my-1 group-hover:scale-105 transition"></i>
                                                <span class="text-[10px] font-bold text-gray-300 text-center line-clamp-1">
                                                    <?= htmlspecialchars($item['nama_produk']) ?>
                                                </span>
                                                <span class="absolute bottom-1 right-2 text-[8px] text-brand-yellow font-bold">VERIFIED</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <h3 class="text-sm font-bold text-[#272727] leading-snug group-hover:text-black line-clamp-2 min-h-[2.5rem]">
                                        <?= htmlspecialchars($item['nama_produk']) ?>
                                    </h3>

                                    <p class="text-base font-extrabold text-[#272727] mt-1.5 tracking-tight">
                                        <?= formatRupiah((int)$item['harga']) ?>
                                    </p>

                                    <!-- Tagline Opsi Transaksi -->
                                    <div class="flex flex-wrap items-center gap-1.5 mt-2.5 min-h-[24px]">
                                        <?php if ($item['bisa_nego']): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 text-[10px] font-bold">
                                                <i class="ph-bold ph-tag text-[11px]"></i> Bisa Nego
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($item['bisa_barter']): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200/80 text-[10px] font-bold">
                                                <i class="ph-bold ph-arrows-left-right text-[11px]"></i> Bisa Barter
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!$item['bisa_nego'] && !$item['bisa_barter']): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-50 text-gray-500 border border-gray-200/80 text-[10px] font-semibold">
                                                <i class="ph ph-check-circle text-[11px]"></i> Harga Pas
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </a>

                                <!-- Footer Card: Rating, Akses Detail & Info Penjual -->
                                <div class="mt-auto pt-3">
                                    <div class="flex items-center justify-between gap-2 text-xs text-gray-600 pb-2.5 border-b border-gray-100">
                                        <?php if ($reviewVal > 0): ?>
                                            <span class="flex items-center gap-1 font-semibold text-gray-800">
                                                <i class="ph-fill ph-star text-amber-400 text-xs"></i>
                                                <?= htmlspecialchars($ratingVal) ?>
                                                <span class="text-gray-400 font-normal">(<?= $reviewVal ?>)</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-[11px]">Belum ada ulasan</span>
                                        <?php endif; ?>
                                        <a href="detail_produk.php?id=<?= (int)$item['id'] ?>" class="text-[11px] font-semibold text-gray-500 group-hover:text-[#272727] inline-flex items-center gap-0.5 transition shrink-0">
                                        </a>
                                    </div>

                                    <div class="flex items-center justify-between gap-2 pt-2.5 text-xs">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-6 h-6 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center font-bold text-[10px] shrink-0">
                                                <?= htmlspecialchars(strtoupper(substr($item['nama_penjual'] ?? 'Seller', 0, 1))) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-bold text-[#272727] text-xs leading-none truncate"><?= htmlspecialchars($item['nama_penjual'] ?? 'Loot-E Store') ?></p>
                                                <p class="text-[9px] text-gray-400 leading-tight mt-0.5">Fast Response</p>
                                            </div>
                                        </div>
                                        <span class="text-[10px] text-emerald-600 font-bold flex items-center gap-1 shrink-0">
                                            <i class="ph-fill ph-shield-check text-xs"></i> Trusted Seller
                                        </span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination Dinamis Sesuai pagination.png -->
                    <?php if ($totalPages > 1): ?>
                        <?php
                        $pagesToShow = [];
                        if ($totalPages <= 7) {
                            $pagesToShow = range(1, $totalPages);
                        } else {
                            $pagesToShow[] = 1;
                            $start = max(2, $page - 1);
                            $end   = min($totalPages - 1, $page + 1);

                            if ($page <= 3) {
                                $start = 2;
                                $end   = 4;
                            } elseif ($page >= $totalPages - 2) {
                                $start = $totalPages - 3;
                                $end   = $totalPages - 1;
                            }

                            if ($start > 2) {
                                $pagesToShow[] = '...';
                            }
                            for ($i = $start; $i <= $end; $i++) {
                                $pagesToShow[] = $i;
                            }
                            if ($end < $totalPages - 1) {
                                $pagesToShow[] = '...';
                            }
                            $pagesToShow[] = $totalPages;
                        }
                        ?>
                        <nav aria-label="Navigasi Halaman" class="flex items-center justify-center gap-1.5 sm:gap-3 mt-8 sm:mt-10 mb-4 sm:mb-6 select-none">
                            <?php if ($page > 1): ?>
                                <a href="<?= $pageUrl($page - 1) ?>"
                                    class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl sm:rounded-xl border-2 border-[#373737] bg-[#F5FAFE] flex items-center justify-center text-[#373737] hover:bg-white active:scale-95 transition shadow-2xs shrink-0"
                                    aria-label="Halaman Sebelumnya">
                                    <i class="ph-bold ph-caret-left text-sm sm:text-base"></i>
                                </a>
                            <?php else: ?>
                                <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl sm:rounded-xl border-2 border-[#373737] bg-[#F5FAFE] flex items-center justify-center text-[#373737] opacity-40 cursor-not-allowed shrink-0"
                                    aria-disabled="true" aria-label="Halaman Sebelumnya">
                                    <i class="ph-bold ph-caret-left text-sm sm:text-base"></i>
                                </span>
                            <?php endif; ?>

                            <div class="flex items-center gap-1 sm:gap-2">
                                <?php foreach ($pagesToShow as $p): ?>
                                    <?php if ($p === '...'): ?>
                                        <span class="w-6 sm:w-8 h-9 sm:h-10 text-[#373737] text-xs sm:text-sm font-bold flex items-center justify-center shrink-0">
                                            &hellip;
                                        </span>
                                    <?php elseif ($p === $page): ?>
                                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-[#373737] text-[#F5FAFE] text-xs sm:text-sm font-bold flex items-center justify-center shadow-xs shrink-0"
                                            aria-current="page">
                                            <?= $p ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="<?= $pageUrl($p) ?>"
                                            class="w-7 h-7 sm:w-8 sm:h-8 text-[#373737] hover:text-black hover:bg-gray-100/60 rounded-full text-xs sm:text-sm font-bold flex items-center justify-center transition shrink-0">
                                            <?= $p ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= $pageUrl($page + 1) ?>"
                                    class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl sm:rounded-xl border-2 border-[#373737] bg-[#F5FAFE] flex items-center justify-center text-[#373737] hover:bg-white active:scale-95 transition shadow-2xs shrink-0"
                                    aria-label="Halaman Berikutnya">
                                    <i class="ph-bold ph-caret-right text-sm sm:text-base"></i>
                                </a>
                            <?php else: ?>
                                <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl sm:rounded-xl border-2 border-[#373737] bg-[#F5FAFE] flex items-center justify-center text-[#373737] opacity-40 cursor-not-allowed shrink-0"
                                    aria-disabled="true" aria-label="Halaman Berikutnya">
                                    <i class="ph-bold ph-caret-right text-sm sm:text-base"></i>
                                </span>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- State Kosong Jika Tidak Ada Produk Yang Sesuai -->
                    <div class="bg-white border border-gray-200/80 rounded-2xl p-10 md:p-14 text-center max-w-xl mx-auto my-6 shadow-xs">
                        <div class="w-16 h-16 rounded-full bg-brand-yellow/15 text-brand-dark flex items-center justify-center mx-auto mb-4">
                            <i class="ph ph-magnifying-glass text-3xl text-brand-dark"></i>
                        </div>
                        <h2 class="text-lg font-bold text-[#272727] mb-2">
                            Tidak ada produk ditemukan
                        </h2>
                        <p class="text-xs sm:text-sm text-gray-500 max-w-md mx-auto leading-relaxed mb-6">
                            <?php if ($searchQuery !== ''): ?>
                                Tidak ditemukan produk yang sesuai dengan kata kunci <span class="font-bold text-[#272727]">&ldquo;<?= htmlspecialchars($searchQuery) ?>&rdquo;</span>.
                            <?php else: ?>
                                Belum ada produk yang tersedia dengan filter yang dipilih.
                            <?php endif; ?>
                            Coba periksa ejaan kata kunci atau telusuri katalog kategori yang tersedia.
                        </p>
                        <div class="flex flex-wrap items-center justify-center gap-3">
                            <a href="categories.php" class="bg-brand-yellow text-brand-dark px-5 py-2.5 rounded-lg text-xs font-bold hover:brightness-95 transition shadow-xs">
                                Jelajahi Kategori
                            </a>
                            <a href="index.php" class="bg-gray-100 text-[#272727] px-5 py-2.5 rounded-lg text-xs font-bold hover:bg-gray-200 transition">
                                Kembali ke Beranda
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function() {
            var toggleBtn = document.getElementById('toggleFilterBtn');
            var filterSidebar = document.getElementById('filterSidebar');
            if (toggleBtn && filterSidebar) {
                toggleBtn.addEventListener('click', function() {
                    filterSidebar.classList.toggle('hidden');
                });
            }
        })();
    </script>
</body>

</html>
<?php
require_once "auth.php";

if (!isLoggedIn()) {
    $redirectUrl = "search.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header("Location: login.php?redirect=" . urlencode($redirectUrl));
    exit;
}

$currentUser = getCurrentUser();
$navActivePage = 'categories';

$searchQuery    = trim($_GET['q'] ?? '');
$kategoriFilter = isset($_GET['kategori']) && is_numeric($_GET['kategori']) ? (int)$_GET['kategori'] : null;
$kondisiFilter  = isset($_GET['kondisi']) ? trim($_GET['kondisi']) : '';
$minPrice       = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (int)$_GET['min_price'] : null;
$maxPrice       = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (int)$_GET['max_price'] : null;
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 9;

$products = [];
$totalResults = 0;
$allCategories = [];

try {
    $db = getDbConnection();
    if ($db) {
        // Ambil daftar kategori beserta jumlah produk yang tersedia
        $stmtCat = $db->query("
            SELECT k.id, k.nama_kategori, COUNT(p.id) AS total_produk
            FROM kategori k
            LEFT JOIN produk p ON k.id = p.id_kategori AND p.status_produk = 'tersedia'
            GROUP BY k.id, k.nama_kategori
            ORDER BY k.id ASC
        ");
        $allCategories = $stmtCat->fetchAll();

        // Bangun query dinamis berdasarkan input pencarian dan filter
        $sql = "
            SELECT p.*, k.nama_kategori, u.nama AS nama_penjual,
                   COALESCE(AVG(ul.rating), 4.8) AS avg_rating,
                   COUNT(ul.id) AS total_reviews
            FROM produk p
            LEFT JOIN kategori k ON p.id_kategori = k.id
            LEFT JOIN pengguna u ON p.id_penjual = u.id
            LEFT JOIN ulasan ul ON p.id = ul.id_produk
            WHERE p.status_produk = 'tersedia'
        ";
        $params = [];

        if ($searchQuery !== '') {
            $sql .= " AND (p.nama_produk LIKE ? OR p.deskripsi LIKE ? OR k.nama_kategori LIKE ?)";
            $term = "%" . $searchQuery . "%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if ($kategoriFilter !== null && $kategoriFilter > 0) {
            $sql .= " AND p.id_kategori = ?";
            $params[] = $kategoriFilter;
        }

        if ($kondisiFilter !== '') {
            $sql .= " AND p.kondisi LIKE ?";
            $params[] = "%" . $kondisiFilter . "%";
        }

        if ($minPrice !== null && $minPrice > 0) {
            $sql .= " AND p.harga >= ?";
            $params[] = $minPrice;
        }

        if ($maxPrice !== null && $maxPrice > 0) {
            $sql .= " AND p.harga <= ?";
            $params[] = $maxPrice;
        }

        $sql .= " GROUP BY p.id ORDER BY p.harga ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $allResults = $stmt->fetchAll();

        $totalResults = count($allResults);
        $totalPages   = max(1, (int)ceil($totalResults / $perPage));
        $offset       = ($page - 1) * $perPage;

        $products = array_slice($allResults, $offset, $perPage);
    }
} catch (Exception $e) {
    $products = [];
    $totalResults = 0;
    $totalPages = 1;
}

// Ikon representatif per kategori produk
$categoryIconMap = [
    'gpu'        => 'ph-graphics-card',
    'cpu'        => 'ph-cpu',
    'ram'        => 'ph-memory',
    'laptop'     => 'ph-laptop',
    'phone'      => 'ph-device-mobile',
    'monitor'    => 'ph-monitor',
    'headphones' => 'ph-headphones',
    'aksesoris'  => 'ph-mouse',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($searchQuery !== '' ? $searchQuery : 'Pencarian') ?> | Loot-E</title>
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
    <?php require_once 'components/navbar.php'; ?>

    <!-- Sub-Navbar: Breadcrumb & Search Bar -->
    <header class="bg-white border-b border-gray-200/80 px-6 md:px-12 py-2.5">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <nav aria-label="Breadcrumb" class="text-xs font-semibold text-gray-500 shrink-0">
                <a href="categories.php" class="hover:text-brand-dark transition">Categories</a>
                <span class="mx-1 text-gray-400">/</span>
                <span class="text-gray-800">Pencarian</span>
            </nav>
            <form action="search.php" method="GET" class="relative max-w-sm sm:max-w-md w-full sm:mx-auto">
                <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Cari barang atau kategori..."
                    class="w-full bg-white border border-gray-300 rounded-full pl-4 pr-9 py-1.5 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow transition" />
                <button type="submit" class="absolute right-1 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-brand-yellow text-brand-dark flex items-center justify-center hover:brightness-95 transition shadow-xs" aria-label="Cari produk">
                    <i class="ph ph-arrow-right text-[11px] font-bold"></i>
                </button>
            </form>
            <div class="w-8 hidden sm:block shrink-0"></div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-6 md:px-12 py-8">
        <!-- Header Judul Pencarian -->
        <div class="mb-6">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-[#272727] tracking-tight">
                &ldquo;<?= htmlspecialchars($searchQuery !== '' ? $searchQuery : 'Semua Produk') ?>&rdquo;
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
                <form action="search.php" method="GET" class="bg-white border border-gray-100 rounded-xl p-5 shadow-2xs">
                    <!-- Menyimpan query pencarian saat filter diubah -->
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>" />

                    <div class="flex items-center justify-between pb-3 border-b border-gray-200">
                        <h2 class="text-base font-bold text-[#272727]">Filter</h2>
                        <?php if ($kategoriFilter || $kondisiFilter || $minPrice || $maxPrice): ?>
                            <a href="search.php?q=<?= urlencode($searchQuery) ?>" class="text-[11px] font-semibold text-red-500 hover:underline">Reset</a>
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
                            <input type="number" name="min_price" value="<?= $minPrice ? htmlspecialchars((string)$minPrice) : '' ?>" placeholder="Min" aria-label="Harga Minimum"
                                class="w-full border border-gray-300 rounded px-2.5 py-1 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow" />
                            <input type="number" name="max_price" value="<?= $maxPrice ? htmlspecialchars((string)$maxPrice) : '' ?>" placeholder="Maks" aria-label="Harga Maksimum"
                                class="w-full border border-gray-300 rounded px-2.5 py-1 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow" />
                        </div>
                        <button type="submit"
                            class="w-full bg-[#272727] text-white py-1.5 rounded text-xs font-semibold hover:bg-neutral-800 active:scale-[0.99] transition shadow-2xs">
                            Terapkan
                        </button>
                    </div>

                    <!-- Filter Penjual Terpercaya -->
                    <div class="pt-4">
                        <h3 class="text-xs font-bold text-[#272727] mb-2.5">Trusted Seller</h3>
                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:text-black">
                            <input type="checkbox" checked class="w-3.5 h-3.5 rounded text-[#272727] focus:ring-brand-yellow border-gray-300" />
                            <span>Hanya penjual terpercaya</span>
                        </label>
                    </div>
                </form>
            </aside>

            <!-- Kolom Kanan: Hasil Pencarian Real dari Database -->
            <section aria-label="Hasil Produk" class="flex-1 w-full">
                <?php if (!empty($products)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php foreach ($products as $item): ?>
                            <?php
                            $catKey = strtolower($item['nama_kategori'] ?? 'hardware');
                            $catIcon = $categoryIconMap[$catKey] ?? 'ph-package';
                            $isBaru = str_contains(strtolower($item['kondisi'] ?? ''), 'baru');
                            $badgeLabel = $isBaru ? 'Baru' : 'Bekas';
                            $ratingVal = number_format((float)($item['avg_rating'] ?? 4.8), 1);
                            $reviewVal = (int)($item['total_reviews'] ?? 0);
                            if ($reviewVal === 0) {
                                $reviewVal = 24;
                            }
                            ?>
                            <article class="bg-white border border-gray-200/80 rounded-xl p-4 shadow-2xs hover:shadow-md transition flex flex-col justify-between group">
                                <!-- Tag Kondisi & Tombol Wishlist -->
                                <div class="flex items-center justify-between mb-2">
                                    <span class="<?= $isBaru ? 'bg-emerald-50 text-emerald-700' : 'bg-[#FFF8E1] text-[#B88A00]' ?> text-[10px] font-bold px-2 py-0.5 rounded">
                                        <?= htmlspecialchars($badgeLabel) ?>
                                    </span>
                                    <button type="button" class="text-gray-400 hover:text-red-500 transition p-1" title="Tambah ke wishlist" aria-label="Simpan ke wishlist">
                                        <i class="ph ph-heart text-base"></i>
                                    </button>
                                </div>

                                <!-- Gambar Hardware / Kotak Produk -->
                                <div class="w-full h-40 bg-[#FAFAFA] rounded-lg overflow-hidden flex items-center justify-center p-3 relative mb-2">
                                    <div class="w-full h-full flex flex-col items-center justify-center bg-neutral-900 text-white rounded p-3 relative shadow-inner">
                                        <span class="text-[9px] font-extrabold tracking-widest text-brand-yellow uppercase mb-1">
                                            <?= htmlspecialchars($item['nama_kategori'] ?? 'Loot-E') ?>
                                        </span>
                                        <i class="ph <?= $catIcon ?> text-4xl text-gray-200 my-1 group-hover:scale-105 transition"></i>
                                        <span class="text-[10px] font-bold text-gray-300 text-center line-clamp-1">
                                            <?= htmlspecialchars($item['nama_produk']) ?>
                                        </span>
                                        <span class="absolute bottom-1 right-2 text-[8px] text-brand-yellow font-bold">VERIFIED</span>
                                    </div>
                                </div>

                                <!-- Detail Spesifikasi & Harga -->
                                <div>
                                    <span class="text-[10px] text-gray-400 font-medium block">
                                        <?= htmlspecialchars($item['kondisi'] ?? 'Kondisi Normal') ?>
                                    </span>
                                    <h3 class="text-sm font-bold text-[#272727] leading-snug mt-0.5 group-hover:text-black line-clamp-2 min-h-[38px]">
                                        <?= htmlspecialchars($item['nama_produk']) ?>
                                    </h3>
                                    <span class="text-xs font-bold text-brand-yellow block mt-0.5">
                                        <?= htmlspecialchars($item['nama_kategori'] ?? 'Elektronik') ?>
                                    </span>
                                    <p class="text-base font-extrabold text-[#272727] mt-1">
                                        Rp <?= number_format((int)$item['harga'], 0, ',', '.') ?>
                                    </p>

                                    <!-- Badge Nego & Barter -->
                                    <div class="flex items-center gap-3 text-[11px] text-brand-yellow font-medium mt-1.5">
                                        <span class="flex items-center gap-1">
                                            <i class="ph ph-tag text-xs font-bold"></i> Bisa Nego
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <i class="ph ph-arrows-left-right text-xs font-bold"></i> Bisa Barter
                                        </span>
                                    </div>

                                    <!-- Rating & Lokasi -->
                                    <div class="flex items-center justify-between text-xs text-gray-600 mt-2 pb-2.5 border-b border-gray-100">
                                        <span class="flex items-center gap-1 font-semibold text-gray-800">
                                            <i class="ph-fill ph-star text-amber-400 text-xs"></i>
                                            <?= htmlspecialchars($ratingVal) ?>
                                            <span class="text-gray-400 font-normal">(<?= htmlspecialchars((string)$reviewVal) ?>)</span>
                                        </span>
                                        <span class="flex items-center gap-1 text-gray-400 text-[11px]">
                                            <i class="ph ph-map-pin text-xs"></i> Jakarta
                                        </span>
                                    </div>

                                    <!-- Info Penjual -->
                                    <div class="flex items-center justify-between pt-2.5 text-xs">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center font-bold text-[10px]">
                                                <?= htmlspecialchars(strtoupper(substr($item['nama_penjual'] ?? 'Seller', 0, 1))) ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-[#272727] text-xs leading-none line-clamp-1"><?= htmlspecialchars($item['nama_penjual'] ?? 'Loot-E Store') ?></p>
                                                <p class="text-[9px] text-gray-400 leading-tight mt-0.5">Fast Response</p>
                                            </div>
                                        </div>
                                        <span class="text-[10px] text-emerald-600 font-bold flex items-center gap-1">
                                            <i class="ph-fill ph-shield-check text-xs"></i> Trusted Seller
                                        </span>
                                    </div>
                                </div>

                                <!-- Tombol Aksi: Ajukan Harga, Ajukan Barter, Chat Seller -->
                                <div class="mt-3.5 space-y-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="#nego-<?= htmlspecialchars($item['id']) ?>" class="bg-brand-yellow text-brand-dark py-1.5 px-2 rounded text-xs font-bold text-center hover:brightness-95 transition shadow-2xs">
                                            Ajukan Harga
                                        </a>
                                        <a href="#barter-<?= htmlspecialchars($item['id']) ?>" class="border border-brand-yellow text-brand-dark py-1.5 px-2 rounded text-xs font-bold text-center hover:bg-brand-yellow/15 transition">
                                            Ajukan Barter
                                        </a>
                                    </div>
                                    <button type="button" class="w-full bg-[#272727] text-white py-1.5 px-3 rounded text-xs font-semibold flex items-center justify-center gap-1.5 hover:bg-neutral-800 active:scale-[0.99] transition shadow-2xs">
                                        <i class="ph ph-chat-circle-dots text-sm"></i> Chat Seller
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination Dinamis -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Navigasi Halaman" class="flex items-center justify-center gap-2 mt-10">
                            <?php if ($page > 1): ?>
                                <a href="search.php?q=<?= urlencode($searchQuery) ?>&page=<?= $page - 1 ?>"
                                    class="w-8 h-8 rounded-md border border-gray-300 flex items-center justify-center text-xs text-gray-600 hover:bg-gray-100 transition" aria-label="Halaman Sebelumnya">
                                    <i class="ph ph-caret-left font-bold"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i === $page): ?>
                                    <span class="w-8 h-8 rounded-full bg-[#272727] text-white text-xs font-bold flex items-center justify-center shadow-xs" aria-current="page">
                                        <?= $i ?>
                                    </span>
                                <?php else: ?>
                                    <a href="search.php?q=<?= urlencode($searchQuery) ?>&page=<?= $i ?>"
                                        class="w-8 h-8 rounded-full text-gray-700 hover:bg-gray-100 text-xs font-semibold flex items-center justify-center transition">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="search.php?q=<?= urlencode($searchQuery) ?>&page=<?= $page + 1 ?>"
                                    class="w-8 h-8 rounded-md border border-gray-300 flex items-center justify-center text-xs text-gray-600 hover:bg-gray-100 transition" aria-label="Halaman Berikutnya">
                                    <i class="ph ph-caret-right font-bold"></i>
                                </a>
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
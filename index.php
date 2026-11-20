<?php
require_once "auth.php";

if (!isLoggedIn()) {
    header("Location: landingpage.php");
    exit;
}

$currentUser = getCurrentUser();
$navActivePage = 'home';

$db = getDbConnection();

$categories = $db->query("SELECT id, nama_kategori FROM kategori ORDER BY id LIMIT 7")->fetchAll();

function ambilProduk(PDO $db, string $where, string $order): array
{
    // $where dan $order hanya diisi string tetap dari file ini, bukan input pengguna
    return $db->query("
        SELECT p.id, p.nama_produk, p.foto, p.harga, p.kondisi, p.bisa_nego, p.bisa_barter, k.nama_kategori,
               (SELECT AVG(rating) FROM ulasan WHERE id_produk = p.id) AS rating,
               (SELECT COUNT(*) FROM ulasan WHERE id_produk = p.id) AS total_ulasan
        FROM produk p
        JOIN kategori k ON k.id = p.id_kategori
        WHERE p.status_produk = 'tersedia' $where
        ORDER BY $order
        LIMIT 5
    ")->fetchAll();
}

$sections = [
    [
        'id'    => 'products',
        'class' => 'pt-8 pb-4',
        'judul' => 'Penawaran Pilihan Hari Ini',
        'sub'   => 'Produk yang bisa dinego atau dibarter',
        'items' => ambilProduk($db, 'AND (p.bisa_nego = 1 OR p.bisa_barter = 1)', 'p.harga ASC'),
    ],
    [
        'id'    => 'trending',
        'class' => 'py-6',
        'judul' => 'Trending Minggu Ini',
        'sub'   => 'Produk dengan ulasan terbanyak',
        'items' => ambilProduk($db, '', 'total_ulasan DESC, p.created_at DESC'),
    ],
    [
        'id'    => 'new-arrivals',
        'class' => 'pt-2 pb-14',
        'judul' => 'New Arrivals',
        'sub'   => 'Produk terbaru yang baru tersedia di Loot-E.',
        'items' => ambilProduk($db, '', 'p.created_at DESC, p.id DESC'),
    ],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loot-E | Upgrade Your Setup</title>
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

<body class="bg-[#F4F5F7] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Navbar Utama -->
    <?php require_once 'components/navbar.php'; ?>

    <!-- Sub-Navbar: Breadcrumb & Search Bar -->
    <header class="bg-white border-b border-gray-200/80 px-6 md:px-12 py-2.5">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
            <span class="text-xs font-semibold text-gray-800 shrink-0">Home</span>
            <form action="search.php" method="GET" onsubmit="if(!this.q.value.trim()){ this.q.focus(); return false; }" class="relative max-w-sm sm:max-w-md w-full mx-auto">
                <input type="text" name="q" required placeholder="Cari barang atau kategori..."
                    class="w-full bg-white border border-gray-300 rounded-full pl-4 pr-9 py-1.5 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow transition" />
                <button type="submit" class="absolute right-1 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-brand-yellow text-white flex items-center justify-center hover:brightness-95 transition shadow-xs" aria-label="Cari produk">
                    <i class="ph ph-arrow-right text-[11px] font-bold text-white"></i>
                </button>
            </form>
            <div class="w-8 hidden sm:block shrink-0"></div>
        </div>
    </header>

    <main class="flex-1">
        <!-- Hero Banner: Upgrade Your Setup -->
        <section class="relative bg-brand-yellow overflow-hidden">
            <!-- Background Image Hero -->
            <div class="absolute inset-0 w-full h-full pointer-events-none select-none">
                <img src="assets/mainimage.png" alt="Setup Gaming Background"
                    class="w-full h-full object-cover object-right md:object-[85%_center] filter brightness-[0.98]"
                    fetchpriority="high" width="1440" height="420" />
                <div class="absolute inset-0 bg-gradient-to-r from-brand-yellow via-brand-yellow/85 to-transparent"></div>
            </div>

            <!-- Konten Teks Hero di Atas Background -->
            <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-12 py-14 sm:py-18 md:py-24 flex items-center min-h-[340px] md:min-h-[400px]">
                <div class="max-w-lg">
                    <h1 class="font-hd text-3xl sm:text-4xl md:text-5xl font-extrabold text-[#272727] leading-[1.1] tracking-tight">
                        Upgrade Your Setup
                    </h1>
                    <p class="mt-4 text-xs sm:text-sm md:text-base font-semibold text-[#272727]/90 leading-relaxed max-w-md">
                        Temukan mouse, keyboard, monitor, headset, dan perangkat elektronik lainnya untuk melengkapi setup-mu.
                    </p>
                </div>
            </div>
        </section>

        <!-- Kategori & Spotlight Container -->
        <div class="max-w-7xl mx-auto px-6 md:px-12 -mt-6 sm:-mt-8 relative z-20">
            <!-- 8 Kategori Berjajar -->
            <section aria-label="Kategori Produk" class="bg-white shadow-sm border border-gray-100 p-4 sm:p-6">
                <div class="grid grid-cols-4 sm:grid-cols-8 gap-3 sm:gap-4 text-center">
                    <?php foreach ($categories as $cat): ?>
                        <a href="search.php?kategori=<?= (int)$cat['id'] ?>" class="flex flex-col items-center gap-2 group cursor-pointer">
                            <div class="w-13 h-13 sm:w-20 sm:h-16 rounded-xl bg-[#F4F4F4] group-hover:bg-brand-yellow/20 flex items-center justify-center transition shadow-2xs">
                                <i class="ph <?= htmlspecialchars(kategoriIcon($cat['nama_kategori'])) ?> text-2xl sm:text-3xl text-gray-800 group-hover:text-brand-dark transition"></i>
                            </div>
                            <span class="text-xs font-semibold text-gray-700 group-hover:text-gray-900 transition"><?= htmlspecialchars($cat['nama_kategori']) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <a href="categories.php" class="flex flex-col items-center gap-2 group cursor-pointer">
                        <div class="w-13 h-13 sm:w-20 sm:h-16 rounded-xl bg-[#F4F4F4] group-hover:bg-brand-yellow/20 flex items-center justify-center transition shadow-2xs">
                            <i class="ph ph-dots-three text-2xl sm:text-3xl text-gray-800 group-hover:text-brand-dark transition"></i>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-gray-900 transition">Lainnya</span>
                    </a>
                </div>
            </section>

            <!-- Spotlight Card & Verifikasi Card -->
            <section aria-label="Spotlight dan Verifikasi" class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-6">
                <!-- Card Hot Spotlight -->
                <article class="lg:col-span-2 bg-[#272727] text-white p-6 sm:p-8 flex flex-col sm:flex-row items-center justify-between gap-6 shadow-sm border border-gray-700">
                    <div class="flex-1">
                        <span class="text-[10px] font-bold tracking-widest text-brand-dark uppercase bg-brand-yellow py-0.5 px-2 inline-block mb-3">
                            HOT SPOTLIGHT
                        </span>
                        <h2 class="font-hd text-lg sm:text-xl font-bold text-white leading-snug">
                            Mechanical Keyboards & Studio Audio Gear
                        </h2>
                        <p class="text-xs text-gray-300 mt-2.5 leading-relaxed max-w-sm">
                            Tunjang produktivitas dan setup desk-mu dengan ribuan switch mekanikal kustom, keycaps PBT, DAC amp, serta headphone studio teruji.
                        </p>
                        <a href="#trending" class="mt-5 inline-flex items-center gap-1.5 bg-brand-yellow text-brand-dark px-4 py-2.5 text-xs font-bold hover:brightness-95 transition shadow-sm">
                            Koleksi Terlengkap <i class="ph ph-arrow-right font-bold text-xs"></i>
                        </a>
                    </div>
                    <div class="w-full sm:w-56 h-36 shrink-0 flex items-center justify-center relative">
                        <img src="assets/keyboard.png" alt="keyboard">
                    </div>
                </article>

                <!-- Card Verifikasi 12 Titik -->
                <article class="lg:col-span-1 bg-white border border-gray-200/80 p-6 flex flex-col justify-between shadow-xs">
                    <div>
                        <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                            <i class="ph ph-shield-check text-xl text-gray-900"></i>
                        </div>
                        <h3 class="font-bold text-sm sm:text-base text-gray-900 leading-snug">
                            Verifikasi 12 Titik Loot-E
                        </h3>
                        <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                            Semua hardware bekas bisa direquest uji stres Furmark & MemTest oleh kurir partner. Bebas bekas mining dan 100% proteksi dana escrow.
                        </p>
                    </div>
                    <div class="flex items-center justify-between pt-4 mt-6 border-t border-gray-100 text-xs font-semibold text-gray-800">
                        <a href="help.php" class="hover:text-brand-yellow transition">Garansi Uang Kembali</a>
                        <a href="help.php" class="hover:text-brand-yellow transition">Detail Proteksi</a>
                    </div>
                </article>
            </section>
        </div>

        <!-- Section Produk: Pilihan, Trending, New Arrivals (data dari tabel produk) -->
        <?php foreach ($sections as $sec): ?>
            <section id="<?= $sec['id'] ?>" class="max-w-7xl mx-auto px-6 md:px-12 <?= $sec['class'] ?>">
                <div class="mb-4">
                    <h2 class="text-base sm:text-lg font-bold text-gray-900"><?= htmlspecialchars($sec['judul']) ?></h2>
                    <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($sec['sub']) ?></p>
                </div>
                <?php if (empty($sec['items'])): ?>
                    <p class="text-xs text-gray-500 bg-white border border-gray-200/70 p-6 text-center">Belum ada produk tersedia.</p>
                <?php else: ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3.5">
                        <?php foreach ($sec['items'] as $item): ?>
                            <a href="detail_produk.php?id=<?= (int)$item['id'] ?>" class="bg-white border border-gray-200/70 p-3 flex flex-col justify-between hover:shadow-md hover:border-gray-300 transition group cursor-pointer">
                                <div>
                                    <div class="w-full aspect-square bg-[#FBFBFB] border border-gray-100 overflow-hidden flex items-center justify-center p-3 mb-2.5 relative">
                                        <?php if (!empty($item['foto'])): ?>
                                            <img src="<?= htmlspecialchars($item['foto']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition" />
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-gray-400 group-hover:scale-105 transition">
                                                <i class="ph <?= htmlspecialchars(kategoriIcon($item['nama_kategori'])) ?> text-3xl text-gray-400"></i>
                                                <span class="text-[9px] mt-1 text-gray-400 font-medium"><?= htmlspecialchars($item['nama_kategori']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-xs font-semibold text-gray-800 line-clamp-2 leading-snug group-hover:text-brand-dark transition min-h-[32px]">
                                        <?= htmlspecialchars($item['nama_produk']) ?>
                                    </h3>
                                    <div class="mt-2">
                                        <span class="font-bold text-sm text-gray-900 block"><?= formatRupiah((int)$item['harga']) ?></span>
                                        <span class="text-[10px] text-gray-400"><?= htmlspecialchars($item['kondisi']) ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-gray-500 mt-2.5 pt-2 border-t border-gray-50">
                                    <?php if ((int)$item['total_ulasan'] > 0): ?>
                                        <span class="flex items-center gap-1 font-medium">
                                            <i class="ph-fill ph-star text-amber-400 text-xs"></i>
                                            <?= number_format((float)$item['rating'], 1) ?>
                                            <span class="text-gray-400 font-normal">(<?= (int)$item['total_ulasan'] ?>)</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-[10px]">Belum ada ulasan</span>
                                    <?php endif; ?>
                                    <span class="text-gray-400 text-[10px] truncate">
                                        <?= htmlspecialchars(implode(' · ', array_filter([$item['bisa_nego'] ? 'Nego' : null, $item['bisa_barter'] ? 'Barter' : null]))) ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </main>

    <!-- Footer -->
    <?php require_once 'components/footer.php'; ?>

</body>

</html>
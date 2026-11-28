<?php
require_once "auth.php";

if (!isLoggedIn()) {
    header("Location: landingpage.php");
    exit;
}

$currentUser    = getCurrentUser();
$navActivePage  = 'home';
$navOverlayHero = true;

function ambilProdukBeranda(PDO $db, string $where, string $order, int $limit): array
{
    // $where, $order, dan $limit hanya berasal dari parameter internal file ini, bukan input pengguna
    $limit = max(1, $limit);
    return $db->query("
        SELECT p.id, p.nama_produk, p.foto, p.harga, p.stok, p.kondisi,
               p.bisa_nego, p.bisa_barter, p.id_kategori, p.created_at,
               k.nama_kategori,
               u.nama AS nama_penjual, u.alamat AS alamat_penjual, u.is_verified AS penjual_verified,
               (SELECT AVG(rating) FROM ulasan WHERE id_produk = p.id) AS avg_rating,
               (SELECT COUNT(*) FROM ulasan WHERE id_produk = p.id) AS total_reviews
        FROM produk p
        JOIN kategori k ON k.id = p.id_kategori
        JOIN pengguna u ON u.id = p.id_penjual
        WHERE p.status_produk = 'tersedia' AND p.stok > 0 $where
        ORDER BY $order
        LIMIT $limit
    ")->fetchAll();
}

$dbError             = false;
$allCategories       = [];
$topCategories       = [];
$negoBarterProducts  = [];
$newArrivalsProducts = [];
$barterOnlyProducts  = [];
$popularProducts     = [];
$allProducts         = [];

try {
    $db = getDbConnection();

    $allCategories = $db->query("
        SELECT k.id, k.nama_kategori, COUNT(p.id) AS total_produk
        FROM kategori k
        LEFT JOIN produk p ON p.id_kategori = k.id AND p.status_produk = 'tersedia' AND p.stok > 0
        GROUP BY k.id, k.nama_kategori
        ORDER BY k.id ASC
    ")->fetchAll();
    $topCategories = array_slice($allCategories, 0, 7);

    $negoBarterProducts  = ambilProdukBeranda($db, 'AND (p.bisa_nego = 1 OR p.bisa_barter = 1)', 'p.harga ASC, p.id DESC', 8);
    $newArrivalsProducts = ambilProdukBeranda($db, '', 'p.created_at DESC, p.id DESC', 8);
    $barterOnlyProducts  = ambilProdukBeranda($db, 'AND p.bisa_barter = 1', 'p.created_at DESC, p.id DESC', 4);
    $popularProducts     = ambilProdukBeranda($db, '', 'total_reviews DESC, avg_rating DESC, p.created_at DESC', 4);
    $allProducts         = ambilProdukBeranda($db, '', 'p.created_at DESC, p.id DESC', 48);
} catch (PDOException $e) {
    $dbError = true;
}

$totalAvailableProducts = count($allProducts);
$initialExplorerLimit   = 8;
$isVerifiedUser         = isVerifiedSeller();
$sellerCtaHref          = $isVerifiedUser ? 'upload_produk.php' : 'verifikasi_akun.php';
$sellerCtaLabel         = $isVerifiedUser ? 'Pasang Produk Sekarang' : 'Verifikasi Akun Seller';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loot-E | Upgrade Your Setup</title>
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
        select:not(:disabled),
        label[for] {
            cursor: pointer;
        }

        button:disabled,
        select:disabled {
            cursor: not-allowed;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 2px solid #FFC914;
            outline-offset: 2px;
        }

        /* Hero slide transition */
        .hero-slide {
            transition: opacity 320ms ease-out, transform 320ms ease-out;
        }

        /* Tactile product card hover */
        .loote-card {
            position: relative;
            transition: transform 200ms cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 200ms ease-out,
                border-color 200ms ease-out;
        }

        .loote-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--brand-yellow);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
            z-index: 5;
        }

        .loote-card:hover {
            transform: translateY(-3px);
            border-color: #D1D5DB;
            box-shadow: 0 10px 22px -10px rgba(39, 39, 39, 0.12);
        }

        .loote-card:hover::before {
            transform: scaleX(1);
        }

        .loote-card .card-img-primary {
            transition: transform 240ms ease-out;
        }

        .loote-card:hover .card-img-primary {
            transform: scale(1.05);
        }

        .loote-card .card-hover-hint {
            opacity: 0;
            transform: translateY(6px);
            transition: opacity 180ms ease-out, transform 180ms ease-out;
        }

        .loote-card:hover .card-hover-hint {
            opacity: 1;
            transform: translateY(0);
        }

        /* Filter & reveal animation */
        @keyframes cardEnter {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-enter {
            animation: cardEnter 200ms ease-out both;
        }

        /* Parallax GPU layers */
        .parallax-bg {
            will-change: transform;
            transform: translate3d(0, 0, 0) scale(1.08);
        }

        .parallax-fg,
        .parallax-item {
            will-change: transform;
            transform: translate3d(0, 0, 0);
        }

        /* One-shot section scroll reveal */
        .reveal-section {
            opacity: 0;
            transform: translateY(18px);
            transition: opacity 380ms cubic-bezier(0.22, 1, 0.36, 1), transform 380ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        .reveal-section.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Horizontal rail */
        .rail-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
            cursor: grab;
        }

        .rail-scroll::-webkit-scrollbar {
            display: none;
        }

        .rail-scroll.is-dragging {
            cursor: grabbing;
            user-select: none;
            scroll-snap-type: none;
        }

        .no-scrollbar {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }

            .reveal-section {
                opacity: 1;
                transform: none;
            }

            .parallax-bg,
            .parallax-fg,
            .parallax-item {
                transform: none !important;
                opacity: 1 !important;
            }
        }
    </style>
</head>

<body class="bg-[#F4F5F7] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Navbar Utama (Floating Pill di Desktop, Sticky Penuh di Mobile) -->
    <?php require_once 'components/navbar.php'; ?>

    <main id="top" class="flex-1">
        <!-- 1. HERO CAROUSEL (3 Slide Terarah dengan Depth Parallax & Pause/Play) -->
        <section aria-label="Sorotan Utama Loot-E" aria-roledescription="carousel" class="relative overflow-hidden bg-brand-yellow">
            <div id="hero-carousel" class="relative min-h-[340px] md:min-h-[420px]">
                <!-- Slide 1: Upgrade Your Setup -->
                <article class="hero-slide relative z-10 min-h-[340px] md:min-h-[420px] flex items-center bg-brand-yellow overflow-hidden"
                    data-slide="0" aria-hidden="false">
                    <div class="absolute inset-0 pointer-events-none select-none overflow-hidden">
                        <img src="assets/mainimage.png" alt="Setup Gaming dan Hardware PC"
                            class="parallax-bg w-full h-full object-cover object-right md:object-[85%_center] filter brightness-[0.98]"
                            data-parallax-bg="0.32"
                            fetchpriority="high" width="1440" height="420" />
                        <div class="absolute inset-0 bg-gradient-to-r from-brand-yellow via-brand-yellow/90 to-transparent"></div>
                    </div>
                    <div class="parallax-fg relative z-10 max-w-7xl w-full mx-auto px-6 md:px-12 pt-10 pb-16 sm:pt-14 sm:pb-20 md:pt-32 md:pb-24" data-parallax-fg="-0.14">
                        <div class="max-w-xl">
                            <h1 class="font-hd text-3xl sm:text-4xl md:text-[44px] font-bold text-[#272727] leading-[1.15] tracking-tight">
                                Upgrade Your Setup
                            </h1>
                            <p class="mt-3.5 text-xs sm:text-sm md:text-base font-normal text-[#272727]/85 leading-relaxed max-w-md">
                                Temukan komponen PC, laptop, monitor, dan audio gear dengan kondisi transparan. Beli langsung, nego harga, atau barter aman via rekber.
                            </p>
                            <div class="mt-6 flex flex-wrap items-center gap-3">
                                <a href="#jelajah-produk"
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 bg-[#272727] text-white text-xs sm:text-sm font-semibold hover:bg-black active:scale-[0.99] transition shadow-sm">
                                    <span>Jelajahi Katalog</span>
                                    <i class="ph ph-arrow-down text-sm" aria-hidden="true"></i>
                                </a>
                                <a href="search.php?barter=1"
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 bg-white text-[#272727] border border-[#272727]/15 text-xs sm:text-sm font-semibold hover:bg-gray-50 active:scale-[0.99] transition shadow-2xs">
                                    <i class="ph ph-arrows-left-right text-sm text-sky-700" aria-hidden="true"></i>
                                    <span>Cari Bisa Barter</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Slide 2: Tukar Tambah / Barter -->
                <article class="hero-slide hidden opacity-0 absolute inset-0 z-10 min-h-[340px] md:min-h-[420px] flex items-center bg-[#272727] text-white overflow-hidden"
                    data-slide="1" aria-hidden="true">
                    <div class="absolute inset-0 pointer-events-none select-none overflow-hidden">
                        <img src="assets/categoriesimage.png" alt="Tukar Tambah Perangkat Elektronik"
                            class="parallax-bg w-full h-full object-cover object-right opacity-30"
                            data-parallax-bg="0.32"
                            loading="lazy" width="1440" height="420" />
                        <div class="absolute inset-0 bg-gradient-to-r from-[#272727] via-[#272727]/90 to-[#272727]/50"></div>
                    </div>
                    <div class="parallax-fg relative z-10 max-w-7xl w-full mx-auto px-6 md:px-12 pt-10 pb-16 sm:pt-14 sm:pb-20 md:pt-32 md:pb-24" data-parallax-fg="-0.14">
                        <div class="max-w-xl">
                            <p class="text-[11px] font-semibold tracking-wider text-brand-yellow uppercase mb-2">
                                Opsi Tukar Tambah Terstruktur
                            </p>
                            <h2 class="font-hd text-3xl sm:text-4xl md:text-[42px] font-bold text-white leading-[1.15] tracking-tight">
                                Tukar Hardware Lama ke Unit Incaran
                            </h2>
                            <p class="mt-3.5 text-xs sm:text-sm md:text-base font-normal text-gray-300 leading-relaxed max-w-md">
                                Ajukan barang dari katalogmu sebagai penawaran barter ditambah selisih dana yang disepakati, terlindungi Rekening Bersama dua arah.
                            </p>
                            <div class="mt-6 flex flex-wrap items-center gap-3">
                                <a href="search.php?barter=1"
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 bg-brand-yellow text-[#272727] text-xs sm:text-sm font-semibold hover:brightness-95 active:scale-[0.99] transition shadow-sm">
                                    <i class="ph ph-arrows-left-right text-sm" aria-hidden="true"></i>
                                    <span>Lihat Produk Bisa Barter</span>
                                </a>
                                <a href="#sorotan-ganda"
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 border border-gray-500 text-white text-xs sm:text-sm font-medium hover:border-brand-yellow hover:text-brand-yellow transition">
                                    <span>Sorotan Barter</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Slide 3: Fitur Unggulan Anti-Bottleneck -->
                <article class="hero-slide hidden opacity-0 absolute inset-0 z-10 min-h-[340px] md:min-h-[420px] flex items-center bg-[#1E1E1E] text-white overflow-hidden"
                    data-slide="2" aria-hidden="true">
                    <div class="absolute inset-0 pointer-events-none select-none flex items-center justify-end pr-8 md:pr-24 opacity-20 md:opacity-80 overflow-hidden">
                        <img src="assets/keyboard.png" alt="Simulasi Kecocokan Hardware PC"
                            class="parallax-bg w-64 md:w-[400px] h-auto object-contain"
                            data-parallax-bg="0.28"
                            loading="lazy" width="400" height="285" />
                    </div>
                    <div class="parallax-fg relative z-10 max-w-7xl w-full mx-auto px-6 md:px-12 pt-10 pb-16 sm:pt-14 sm:pb-20 md:pt-32 md:pb-24" data-parallax-fg="-0.14">
                        <div class="max-w-xl">
                            <p class="text-[11px] font-semibold tracking-wider text-brand-yellow uppercase mb-2">
                                Fitur Unggulan Anti-Bottleneck
                            </p>
                            <h2 class="font-hd text-3xl sm:text-4xl md:text-[42px] font-bold text-white leading-[1.15] tracking-tight">
                                Pastikan Komponen Pilihanmu Saling Seimbang
                            </h2>
                            <p class="mt-3.5 text-xs sm:text-sm md:text-base font-normal text-gray-300 leading-relaxed max-w-md">
                                Hindari salah beli atau performa tertahan. Cek keseimbangan komponen perangkat dan target penggunaanmu sebelum checkout atau barter.
                            </p>
                            <div class="mt-6 flex flex-wrap items-center gap-3">
                                <button type="button" data-open-bottleneck
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 bg-brand-yellow text-[#272727] text-xs sm:text-sm font-semibold hover:brightness-95 active:scale-[0.99] transition shadow-sm">
                                    <i class="ph ph-gauge text-base" aria-hidden="true"></i>
                                    <span>Cek Bottleneck Sekarang</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Kontrol Carousel -->
                <div class="absolute bottom-10 sm:bottom-12 right-4 sm:right-6 md:right-12 z-20 flex items-center gap-2">
                    <div class="flex items-center gap-1.5 bg-[#272727]/85 px-2.5 py-1.5 rounded-md border border-white/15" role="tablist" aria-label="Pilih slide sorotan">
                        <button type="button" class="hero-dot h-2 w-6 rounded-sm bg-brand-yellow transition-all" data-go="0" role="tab" aria-selected="true" aria-label="Slide 1: Upgrade Your Setup"></button>
                        <button type="button" class="hero-dot h-2 w-2.5 rounded-sm bg-white/40 hover:bg-white/70 transition-all" data-go="1" role="tab" aria-selected="false" aria-label="Slide 2: Tukar Hardware"></button>
                        <button type="button" class="hero-dot h-2 w-2.5 rounded-sm bg-white/40 hover:bg-white/70 transition-all" data-go="2" role="tab" aria-selected="false" aria-label="Slide 3: Fitur Anti-Bottleneck"></button>
                    </div>
                    <button type="button" id="hero-pause-btn" aria-label="Jeda pergantian slide otomatis" aria-pressed="false"
                        class="w-10 h-10 rounded-md bg-[#272727]/90 text-white border border-white/15 hover:border-brand-yellow hover:text-brand-yellow flex items-center justify-center transition">
                        <i id="hero-pause-icon" class="ph-bold ph-pause text-sm" aria-hidden="true"></i>
                    </button>
                    <button type="button" id="hero-prev" aria-label="Slide sebelumnya"
                        class="w-10 h-10 rounded-md bg-[#272727]/90 text-white border border-white/15 hover:border-brand-yellow hover:text-brand-yellow flex items-center justify-center transition">
                        <i class="ph-bold ph-caret-left text-sm" aria-hidden="true"></i>
                    </button>
                    <button type="button" id="hero-next" aria-label="Slide berikutnya"
                        class="w-10 h-10 rounded-md bg-[#272727]/90 text-white border border-white/15 hover:border-brand-yellow hover:text-brand-yellow flex items-center justify-center transition">
                        <i class="ph-bold ph-caret-right text-sm" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </section>

        <!-- 2. KATEGORI & FLAGSHIP CONTAINER -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 -mt-6 sm:-mt-8 relative z-20">
            <!-- 8 Kategori Berjajar dengan Jumlah Stok Aktif -->
            <section aria-label="Kategori Produk" class="bg-white shadow-sm border border-gray-200/80 p-3 sm:p-6">
                <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 sm:gap-4 text-center">
                    <?php foreach ($topCategories as $cat): ?>
                        <a href="search.php?kategori=<?= (int)$cat['id'] ?>" class="flex flex-col items-center gap-1.5 sm:gap-2 group min-h-[44px]">
                            <div class="w-12 h-12 sm:w-20 sm:h-16 rounded-xl bg-[#F4F4F4] border border-transparent group-hover:bg-brand-yellow/25 group-hover:border-brand-yellow group-hover:-translate-y-0.5 flex items-center justify-center transition-all duration-200 shadow-2xs">
                                <i class="ph <?= htmlspecialchars(kategoriIcon($cat['nama_kategori']), ENT_QUOTES, 'UTF-8') ?> text-xl sm:text-3xl text-gray-700 group-hover:text-brand-dark group-hover:scale-105 transition-transform" aria-hidden="true"></i>
                            </div>
                            <span class="text-[10px] sm:text-xs font-medium text-gray-700 group-hover:text-gray-900 transition leading-tight">
                                <?= htmlspecialchars($cat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="text-[10px] text-gray-500 font-normal leading-none">
                                <?= (int)$cat['total_produk'] ?> unit
                            </span>
                        </a>
                    <?php endforeach; ?>
                    <a href="categories.php" class="flex flex-col items-center gap-1.5 sm:gap-2 group min-h-[44px]">
                        <div class="w-12 h-12 sm:w-20 sm:h-16 rounded-xl bg-[#F4F4F4] border border-transparent group-hover:bg-brand-yellow/25 group-hover:border-brand-yellow group-hover:-translate-y-0.5 flex items-center justify-center transition-all duration-200 shadow-2xs">
                            <i class="ph ph-dots-three text-xl sm:text-3xl text-gray-700 group-hover:text-brand-dark group-hover:scale-105 transition-transform" aria-hidden="true"></i>
                        </div>
                        <span class="text-[10px] sm:text-xs font-medium text-gray-700 group-hover:text-gray-900 transition leading-tight">Lainnya</span>
                        <span class="text-[10px] text-gray-500 font-normal leading-none">Semua</span>
                    </a>
                </div>
            </section>

            <!-- Flagship Anti-Bottleneck Card & 3 Jalur Transaksi -->
            <section id="fitur-bottleneck" aria-label="Fitur Anti-Bottleneck dan Jalur Transaksi" class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-5 mt-4 sm:mt-6 reveal-section">
                <!-- Card Utama: Smart Anti-Bottleneck Checker -->
                <article class="lg:col-span-2 bg-[#272727] text-white p-6 sm:p-8 shadow-sm border border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-6 overflow-hidden">
                    <div class="max-w-xl">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-[10px] font-bold tracking-wider text-brand-dark uppercase bg-brand-yellow py-0.5 px-2 inline-block">
                                FITUR UNGGULAN
                            </span>
                            <span class="text-[11px] font-normal text-gray-300">Segera Hadir</span>
                        </div>
                        <h2 class="font-hd text-lg sm:text-[22px] font-bold text-white leading-snug">
                            Anti-Bottleneck Checker: Cek Keseimbangan Setup
                        </h2>
                        <p class="text-xs sm:text-sm font-normal text-gray-300 mt-2 leading-relaxed">
                            Pastikan komponen yang kamu incar cocok dengan spesifikasi perangkatmu dan tidak saling menahan performa sebelum membeli, menawar, atau tukar tambah.
                        </p>
                        <div class="mt-5">
                            <button type="button" data-open-bottleneck
                                class="inline-flex items-center justify-center gap-2 min-h-[44px] bg-brand-yellow text-[#272727] px-5 py-2.5 rounded-md text-xs sm:text-sm font-semibold hover:brightness-95 active:scale-[0.99] transition shadow-sm">
                                <i class="ph ph-gauge text-base" aria-hidden="true"></i>
                                <span>Hitung Bottleneck</span>
                            </button>
                        </div>
                    </div>
                    <div class="parallax-item hidden sm:flex w-28 h-28 rounded-2xl bg-[#1D1D1D] border border-gray-700 flex-col items-center justify-center gap-1.5 shrink-0 text-center px-3" data-parallax-range="14">
                        <i class="ph ph-gauge text-4xl text-brand-yellow" aria-hidden="true"></i>
                        <span class="text-[10px] font-medium text-gray-300 leading-tight">Cek Instan Sebelum Beli</span>
                    </div>
                </article>

                <!-- Card Informasi Kanan: 3 Jalur Transaksi & Proteksi Rekber -->
                <article class="lg:col-span-1 bg-white border border-gray-200/80 p-6 flex flex-col justify-between shadow-xs hover:border-gray-300 transition">
                    <div>
                        <div class="flex items-center gap-2.5 mb-3">
                            <div class="w-9 h-9 rounded-lg bg-brand-yellow/20 text-[#272727] flex items-center justify-center shrink-0">
                                <i class="ph ph-shield-check text-lg" aria-hidden="true"></i>
                            </div>
                            <h2 class="font-semibold text-sm sm:text-base text-gray-900 leading-snug">
                                3 Jalur Transaksi &amp; Rekber
                            </h2>
                        </div>
                        <ul class="space-y-2.5 text-xs text-gray-600 mt-3 leading-relaxed">
                            <li class="flex items-start gap-2">
                                <i class="ph ph-tag text-amber-700 text-sm mt-0.5 shrink-0" aria-hidden="true"></i>
                                <span><strong class="font-semibold text-gray-800">Bisa Nego:</strong> Ajukan harga wajar tanpa spam chat.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="ph ph-arrows-left-right text-sky-700 text-sm mt-0.5 shrink-0" aria-hidden="true"></i>
                                <span><strong class="font-semibold text-gray-800">Bisa Barter:</strong> Tukar tambah unit langsung dari katalogmu.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="ph ph-lock-key text-emerald-700 text-sm mt-0.5 shrink-0" aria-hidden="true"></i>
                                <span><strong class="font-semibold text-gray-800">Escrow Aman:</strong> Dana diteruskan setelah barang kamu cek.</span>
                            </li>
                        </ul>
                    </div>
                    <div class="flex items-center justify-between pt-4 mt-5 border-t border-gray-100 text-xs font-medium text-gray-700">
                        <a href="help.php" class="hover:text-[#92400E] transition">Cara Kerja Rekber</a>
                        <a href="help.php" class="hover:text-[#92400E] transition">Pusat Bantuan &gt;</a>
                    </div>
                </article>
            </section>
        </div>

        <?php if ($dbError): ?>
            <!-- Error State Koneksi / Query Database -->
            <section class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 py-10" role="alert">
                <div class="bg-red-50 border border-red-200 rounded-xl p-6 sm:p-8 text-center">
                    <div class="w-12 h-12 rounded-full bg-red-100 text-red-700 flex items-center justify-center mx-auto mb-3">
                        <i class="ph ph-warning-circle text-2xl" aria-hidden="true"></i>
                    </div>
                    <h2 class="text-base font-bold text-red-900">Gagal Memuat Daftar Produk</h2>
                    <p class="text-xs sm:text-sm text-red-800 mt-1 max-w-md mx-auto">
                        Terjadi kendala saat mengambil data produk dari server. Silakan muat ulang halaman untuk mencoba kembali.
                    </p>
                    <button type="button" onclick="window.location.reload()"
                        class="mt-4 inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 bg-[#272727] text-white text-xs font-semibold hover:bg-black transition">
                        <i class="ph ph-arrow-clockwise text-sm" aria-hidden="true"></i>
                        <span>Muat Ulang Halaman</span>
                    </button>
                </div>
            </section>
        <?php else: ?>

            <!-- 3. SECTION 1: Pilihan Bisa Nego & Barter (4 Kolom) -->
            <section id="pilihan-transaksi" class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 pt-10 pb-8 reveal-section">
                <div class="flex items-end justify-between gap-4 mb-5">
                    <div>
                        <div class="w-8 h-1 bg-brand-yellow mb-2"></div>
                        <h2 class="text-lg sm:text-xl font-bold text-gray-900">
                            Pilihan Bisa Nego &amp; Barter
                        </h2>
                        <p class="text-xs sm:text-sm font-normal text-gray-500 mt-0.5">
                            Barang siap tawar harga atau tukar tambah dengan unit milikmu.
                        </p>
                    </div>
                    <a href="search.php?nego=1&amp;barter=1"
                        class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-gray-700 hover:text-black border-b-2 border-brand-yellow pb-0.5 shrink-0 transition">
                        <span>Lihat Semua</span>
                        <i class="ph ph-arrow-right text-xs" aria-hidden="true"></i>
                    </a>
                </div>

                <?php if (empty($negoBarterProducts)): ?>
                    <div class="bg-white border border-gray-200/80 p-8 text-center">
                        <p class="text-xs sm:text-sm font-medium text-gray-600">Belum ada produk dengan opsi nego atau barter saat ini.</p>
                        <a href="search.php" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-gray-800 underline">Lihat semua produk tersedia</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <?php foreach ($negoBarterProducts as $item): ?>
                            <?php require 'components/product_card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- 4. SECTION 2: Pita Gelap Horizontal Carousel Rail (Drag-to-Scroll + Progress Bar + Parallax Backdrop) -->
            <section id="new-arrivals" class="relative overflow-hidden bg-[#272727] text-white py-10 sm:py-12 my-2 reveal-section">
                <div class="pointer-events-none select-none absolute inset-0 overflow-hidden" aria-hidden="true">
                    <div class="parallax-item absolute -right-12 -top-16 w-72 h-72 rounded-full border border-brand-yellow/15" data-parallax-range="28"></div>
                    <div class="parallax-item absolute -left-16 -bottom-20 w-80 h-80 rounded-full border border-white/10" data-parallax-range="-24"></div>
                </div>
                <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 md:px-12">
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
                        <div>
                            <div class="w-8 h-1 bg-brand-yellow mb-2"></div>
                            <h2 class="text-lg sm:text-xl font-bold text-white">
                                Baru Masuk di Loot-E
                            </h2>
                            <p class="text-xs sm:text-sm font-normal text-gray-300 mt-0.5">
                                Listing terbaru dari seller terverifikasi. Geser tombol panah atau tarik langsung dengan mouse.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" id="rail-prev" aria-label="Geser produk baru ke kiri"
                                class="w-11 h-11 rounded-md border border-gray-600 bg-[#272727] text-white hover:border-brand-yellow hover:text-brand-yellow disabled:opacity-35 flex items-center justify-center transition">
                                <i class="ph ph-caret-left text-base" aria-hidden="true"></i>
                            </button>
                            <button type="button" id="rail-next" aria-label="Geser produk baru ke kanan"
                                class="w-11 h-11 rounded-md border border-gray-600 bg-[#272727] text-white hover:border-brand-yellow hover:text-brand-yellow disabled:opacity-35 flex items-center justify-center transition">
                                <i class="ph ph-caret-right text-base" aria-hidden="true"></i>
                            </button>
                            <a href="search.php"
                                class="ml-1 inline-flex items-center gap-1.5 min-h-[44px] px-4 py-2 bg-brand-yellow text-[#272727] text-xs font-semibold hover:brightness-95 transition">
                                <span>Semua Terbaru</span>
                                <i class="ph ph-arrow-right text-xs" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>

                    <?php if (empty($newArrivalsProducts)): ?>
                        <div class="bg-neutral-800 border border-gray-700 p-8 text-center text-gray-300 text-xs sm:text-sm">
                            Belum ada produk terbaru yang tersedia saat ini.
                        </div>
                    <?php else: ?>
                        <div class="relative">
                            <div id="new-arrivals-rail" class="rail-scroll flex gap-4 overflow-x-auto snap-x snap-mandatory pb-2">
                                <?php foreach ($newArrivalsProducts as $item): ?>
                                    <?php
                                    $cardExtraClass = 'w-[272px] sm:w-[285px] shrink-0 snap-start';
                                    require 'components/product_card.php';
                                    ?>
                                <?php endforeach; ?>
                            </div>
                            <div id="rail-fade-right" class="hidden sm:block pointer-events-none absolute top-0 right-0 bottom-2 w-12 bg-gradient-to-l from-[#272727] to-transparent transition-opacity"></div>
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <div class="flex-1 h-1 bg-white/15 rounded-full overflow-hidden">
                                <div id="rail-progress-bar" class="h-full bg-brand-yellow transition-all duration-150" style="width: 35%;"></div>
                            </div>
                            <span class="text-[11px] font-normal text-gray-300 shrink-0">Geser untuk melihat lebih banyak</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 5. SECTION 3: Sorotan Ganda (Siap Barter & Populer Banyak Diulas) -->
            <section id="sorotan-ganda" class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 py-8 reveal-section">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Panel Kiri: Siap Tukar Tambah (Barter) -->
                    <div class="bg-white border border-gray-200/80 p-5 sm:p-6 shadow-2xs flex flex-col">
                        <div class="flex items-center justify-between gap-3 pb-4 mb-5 border-b border-gray-100">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg bg-sky-50 border border-sky-200/80 text-sky-700 flex items-center justify-center shrink-0">
                                    <i class="ph ph-arrows-left-right text-lg" aria-hidden="true"></i>
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-base sm:text-lg font-bold text-gray-900 truncate">
                                        Siap Tukar Tambah (Barter)
                                    </h2>
                                    <p class="text-xs font-normal text-gray-500 truncate">Tawarkan barangmu untuk tukar unit</p>
                                </div>
                            </div>
                            <a href="search.php?barter=1" class="text-xs font-semibold text-sky-700 hover:text-sky-900 shrink-0 transition">
                                Semua Barter &gt;
                            </a>
                        </div>

                        <?php if (empty($barterOnlyProducts)): ?>
                            <div class="flex-1 flex items-center justify-center p-8 text-center text-xs text-gray-600 bg-gray-50 border border-gray-100">
                                Belum ada produk dengan opsi barter saat ini.
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($barterOnlyProducts as $item): ?>
                                    <?php require 'components/product_card.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel Kanan: Populer & Banyak Diulas -->
                    <div class="bg-white border border-gray-200/80 p-5 sm:p-6 shadow-2xs flex flex-col">
                        <div class="flex items-center justify-between gap-3 pb-4 mb-5 border-b border-gray-100">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg bg-amber-50 border border-amber-200/80 text-amber-800 flex items-center justify-center shrink-0">
                                    <i class="ph-fill ph-star text-lg text-amber-500" aria-hidden="true"></i>
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-base sm:text-lg font-bold text-gray-900 truncate">
                                        Populer &amp; Banyak Diulas
                                    </h2>
                                    <p class="text-xs font-normal text-gray-500 truncate">Produk dengan ulasan pembeli tertinggi</p>
                                </div>
                            </div>
                            <a href="search.php" class="text-xs font-semibold text-gray-700 hover:text-black shrink-0 transition">
                                Cari Produk &gt;
                            </a>
                        </div>

                        <?php if (empty($popularProducts)): ?>
                            <div class="flex-1 flex items-center justify-center p-8 text-center text-xs text-gray-600 bg-gray-50 border border-gray-100">
                                Belum ada produk populer yang tersedia.
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($popularProducts as $item): ?>
                                    <?php require 'components/product_card.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- 6. SECTION 4: Jelajahi Semua Produk (Filter Statis + Budget Chips + Sort + Load More) -->
            <section id="jelajah-produk" class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 pt-4 pb-10 reveal-section">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-5">
                    <div>
                        <div class="w-8 h-1 bg-brand-yellow mb-2"></div>
                        <h2 class="text-lg sm:text-2xl font-bold text-gray-900">
                            Jelajahi Semua Produk
                        </h2>
                        <p id="explorer-count" aria-live="polite" class="text-xs sm:text-sm font-normal text-gray-500 mt-0.5">
                            Menampilkan <?= min($initialExplorerLimit, $totalAvailableProducts) ?> dari <?= $totalAvailableProducts ?> produk tersedia
                        </p>
                    </div>
                    <a href="search.php"
                        class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-gray-700 hover:text-black border-b-2 border-brand-yellow pb-0.5 shrink-0 self-start sm:self-auto transition">
                        <span>Filter Lanjutan di Pencarian</span>
                        <i class="ph ph-sliders text-xs" aria-hidden="true"></i>
                    </a>
                </div>

                <?php if ($totalAvailableProducts === 0): ?>
                    <div class="bg-white border border-gray-200/80 p-10 text-center shadow-2xs">
                        <div class="w-14 h-14 rounded-full bg-brand-yellow/20 text-[#272727] flex items-center justify-center mx-auto mb-3">
                            <i class="ph ph-package text-3xl" aria-hidden="true"></i>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900">Belum ada produk tayang</h3>
                        <p class="text-xs sm:text-sm text-gray-500 mt-1 max-w-md mx-auto">
                            Saat ini belum ada produk yang tersedia. Jadilah penjual pertama yang menayangkan hardware atau perangkat elektronikmu.
                        </p>
                        <a href="<?= htmlspecialchars($sellerCtaHref, ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-4 inline-flex items-center gap-2 min-h-[44px] px-5 py-2.5 bg-brand-yellow text-[#272727] text-xs font-semibold hover:brightness-95 transition">
                            <?= htmlspecialchars($sellerCtaLabel, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Filter Bar Statis (tidak sticky saat scroll) -->
                    <div class="bg-white border border-gray-200/80 p-3.5 sm:p-4 mb-5 space-y-3 shadow-2xs">
                        <!-- Baris 1: Tab Filter Kategori -->
                        <div class="no-scrollbar flex items-center gap-2 overflow-x-auto pb-0.5" role="group" aria-label="Filter berdasarkan kategori">
                            <button type="button"
                                class="cat-filter-btn shrink-0 min-h-[38px] px-3.5 py-1.5 rounded-md border text-xs font-semibold transition bg-[#272727] text-white border-[#272727]"
                                data-cat="all"
                                aria-pressed="true">
                                Semua (<?= $totalAvailableProducts ?>)
                            </button>
                            <?php foreach ($allCategories as $cat): ?>
                                <?php if ((int)$cat['total_produk'] > 0): ?>
                                    <button type="button"
                                        class="cat-filter-btn shrink-0 min-h-[38px] px-3.5 py-1.5 rounded-md border text-xs font-medium transition bg-white text-gray-600 border-gray-200 hover:border-gray-400"
                                        data-cat="<?= (int)$cat['id'] ?>"
                                        aria-pressed="false">
                                        <?= htmlspecialchars($cat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$cat['total_produk'] ?>)
                                    </button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <!-- Baris 2: Shortcut Rentang Budget -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-2.5 border-t border-gray-100" role="group" aria-label="Filter cepat sesuai budget">
                            <span class="text-[11px] font-medium text-gray-500 mr-1">Budget:</span>
                            <button type="button" class="budget-filter-btn min-h-[32px] px-2.5 py-1 rounded-md border text-[11px] font-semibold transition bg-[#272727] text-white border-[#272727]" data-budget="all" aria-pressed="true">Semua Harga</button>
                            <button type="button" class="budget-filter-btn min-h-[32px] px-2.5 py-1 rounded-md border text-[11px] font-medium transition bg-white text-gray-600 border-gray-200 hover:border-gray-400" data-budget="under1" aria-pressed="false">&lt; Rp 1 Jt</button>
                            <button type="button" class="budget-filter-btn min-h-[32px] px-2.5 py-1 rounded-md border text-[11px] font-medium transition bg-white text-gray-600 border-gray-200 hover:border-gray-400" data-budget="1to3" aria-pressed="false">Rp 1 - 3 Jt</button>
                            <button type="button" class="budget-filter-btn min-h-[32px] px-2.5 py-1 rounded-md border text-[11px] font-medium transition bg-white text-gray-600 border-gray-200 hover:border-gray-400" data-budget="3to5" aria-pressed="false">Rp 3 - 5 Jt</button>
                            <button type="button" class="budget-filter-btn min-h-[32px] px-2.5 py-1 rounded-md border text-[11px] font-medium transition bg-white text-gray-600 border-gray-200 hover:border-gray-400" data-budget="over5" aria-pressed="false">&gt; Rp 5 Jt</button>
                        </div>

                        <!-- Baris 3: Opsi Transaksi / Kondisi + Urutkan -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pt-2.5 border-t border-gray-100">
                            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter opsi transaksi dan kondisi">
                                <button type="button"
                                    class="flag-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border border-gray-200 bg-white text-gray-600 hover:border-amber-300 text-xs font-medium transition"
                                    data-filter="nego" aria-pressed="false">
                                    <i class="ph ph-tag text-xs text-amber-700" aria-hidden="true"></i>
                                    <span>Bisa Nego</span>
                                </button>
                                <button type="button"
                                    class="flag-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border border-gray-200 bg-white text-gray-600 hover:border-sky-300 text-xs font-medium transition"
                                    data-filter="barter" aria-pressed="false">
                                    <i class="ph ph-arrows-left-right text-xs text-sky-700" aria-hidden="true"></i>
                                    <span>Bisa Barter</span>
                                </button>
                                <button type="button"
                                    class="cond-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border border-gray-200 bg-white text-gray-600 hover:border-emerald-300 text-xs font-medium transition"
                                    data-cond="baru" aria-pressed="false">
                                    <i class="ph ph-package text-xs text-emerald-700" aria-hidden="true"></i>
                                    <span>Baru</span>
                                </button>
                                <button type="button"
                                    class="cond-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border border-gray-200 bg-white text-gray-600 hover:border-amber-300 text-xs font-medium transition"
                                    data-cond="bekas" aria-pressed="false">
                                    <i class="ph ph-clock-counter-clockwise text-xs text-[#92400E]" aria-hidden="true"></i>
                                    <span>Bekas</span>
                                </button>
                                <button type="button" id="btn-reset-explorer"
                                    class="hidden inline-flex items-center gap-1 min-h-[36px] px-3 py-1.5 text-xs font-semibold text-red-700 hover:text-red-900 hover:bg-red-50 rounded-md transition">
                                    <i class="ph ph-x text-xs" aria-hidden="true"></i>
                                    <span>Reset Filter</span>
                                </button>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <label for="home-sort" class="text-xs font-medium text-gray-500">Urutkan:</label>
                                <select id="home-sort"
                                    class="min-h-[36px] border border-gray-300 rounded-md px-3 py-1.5 text-xs font-medium text-gray-700 bg-white focus:border-brand-yellow">
                                    <option value="newest">Terbaru</option>
                                    <option value="price-asc">Harga Terendah</option>
                                    <option value="price-desc">Harga Tertinggi</option>
                                    <option value="reviews">Ulasan Terbanyak</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Grid Produk Eksplorasi -->
                    <div id="explorer-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <?php foreach ($allProducts as $idx => $item): ?>
                            <?php
                            $condGroup  = strtolower(trim((string)$item['kondisi'])) === 'baru' ? 'baru' : 'bekas';
                            $isOverInit = $idx >= $initialExplorerLimit ? ' hidden' : '';
                            $cardExtraClass = 'explorer-card' . $isOverInit;
                            $cardDataAttrs  = sprintf(
                                ' data-kategori="%d" data-kondisi-group="%s" data-nego="%d" data-barter="%d" data-harga="%d" data-reviews="%d" data-order="%d"',
                                (int)$item['id_kategori'],
                                $condGroup,
                                !empty($item['bisa_nego']) ? 1 : 0,
                                !empty($item['bisa_barter']) ? 1 : 0,
                                (int)$item['harga'],
                                (int)$item['total_reviews'],
                                $idx
                            );
                            require 'components/product_card.php';
                            ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Empty State Filter Eksplorasi -->
                    <div id="explorer-empty" class="hidden bg-white border border-gray-200/80 p-10 text-center mt-4 shadow-2xs">
                        <div class="w-12 h-12 rounded-full bg-amber-50 text-[#92400E] flex items-center justify-center mx-auto mb-3">
                            <i class="ph ph-funnel-x text-2xl" aria-hidden="true"></i>
                        </div>
                        <h3 class="text-sm sm:text-base font-semibold text-gray-900">
                            Tidak ada produk yang cocok dengan filter yang dipilih
                        </h3>
                        <p class="text-xs sm:text-sm text-gray-500 mt-1 max-w-md mx-auto">
                            Coba ubah kategori, rentang budget, atau opsi transaksi untuk melihat produk lain yang tersedia.
                        </p>
                        <button type="button" id="btn-empty-reset"
                            class="mt-4 inline-flex items-center gap-1.5 min-h-[44px] px-5 py-2.5 bg-[#272727] text-white text-xs font-semibold hover:bg-black transition">
                            Reset Semua Filter
                        </button>
                    </div>

                    <!-- Tombol Muat Lebih Banyak -->
                    <div id="explorer-load-more-wrap" class="mt-7 text-center <?= $totalAvailableProducts <= $initialExplorerLimit ? 'hidden' : '' ?>">
                        <button type="button" id="btn-load-more"
                            class="inline-flex items-center justify-center gap-2 min-h-[44px] px-6 py-2.5 bg-white border border-gray-300 text-gray-800 text-xs sm:text-sm font-semibold hover:border-gray-500 hover:bg-gray-50 transition shadow-2xs">
                            <span>Muat Lebih Banyak</span>
                            <i class="ph ph-plus text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </section>

        <?php endif; ?>

        <!-- 7. Compact Bottom Seller CTA -->
        <section aria-label="Aksi Penjual dan Pencarian Lanjutan" class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 pb-14 reveal-section">
            <div class="bg-[#272727] text-white p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 border border-gray-700 shadow-sm">
                <div class="max-w-xl">
                    <h2 class="text-lg sm:text-xl font-bold text-white leading-snug">
                        Punya Hardware Menganggur? Jual atau Tukar Tambah di Loot-<span class="text-brand-yellow">E</span>
                    </h2>
                    <p class="text-xs sm:text-sm font-normal text-gray-300 mt-1.5 leading-relaxed">
                        Pasang unit dengan detail kondisi terstruktur, aktifkan opsi nego atau barter, dan terima pencairan dana aman melalui Rekening Bersama.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <a href="<?= htmlspecialchars($sellerCtaHref, ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 bg-brand-yellow text-[#272727] text-xs font-semibold hover:brightness-95 transition">
                        <span><?= htmlspecialchars($sellerCtaLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <i class="ph ph-arrow-right text-xs" aria-hidden="true"></i>
                    </a>
                    <a href="search.php"
                        class="inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 border border-gray-500 text-white text-xs font-medium hover:border-brand-yellow hover:text-brand-yellow transition">
                        <span>Pencarian Lanjutan</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Back to Top Button (muncul setelah scroll > 500px, di atas FAB Help) -->
    <button type="button" id="btn-back-to-top" aria-label="Kembali ke atas halaman"
        class="fixed bottom-24 right-6 z-40 w-11 h-11 rounded-full bg-[#272727] text-white border border-gray-600 shadow-lg hover:border-brand-yellow hover:text-brand-yellow flex items-center justify-center transition-all duration-200 opacity-0 pointer-events-none translate-y-2">
        <i class="ph-bold ph-arrow-up text-base" aria-hidden="true"></i>
    </button>

    <!-- Toast Demo Fitur Anti-Bottleneck -->
    <div id="loote-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-[#272727] text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg max-w-sm">
        <div class="flex items-center gap-2">
            <i class="ph ph-info text-brand-yellow text-base shrink-0" aria-hidden="true"></i>
            <span id="loote-toast-text">Fitur Anti-Bottleneck Checker segera hadir.</span>
        </div>
    </div>

    <!-- Footer -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function() {
            const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // 1. HERO CAROUSEL
            const heroRoot = document.getElementById('hero-carousel');
            const slides = Array.from(document.querySelectorAll('.hero-slide'));
            const dots = Array.from(document.querySelectorAll('.hero-dot'));
            const heroPrev = document.getElementById('hero-prev');
            const heroNext = document.getElementById('hero-next');
            const heroPauseBtn = document.getElementById('hero-pause-btn');
            const heroPauseIcon = document.getElementById('hero-pause-icon');

            let activeSlide = 0;
            let userPaused = reducedMotion;
            let hoverPaused = false;
            let slideTimer = null;

            function showSlide(index) {
                if (!slides.length) return;
                activeSlide = (index + slides.length) % slides.length;
                slides.forEach(function(s, i) {
                    const isActive = i === activeSlide;
                    s.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                    if (isActive) {
                        s.classList.remove('hidden');
                        requestAnimationFrame(function() {
                            s.classList.remove('opacity-0');
                            s.classList.add('opacity-100');
                        });
                    } else {
                        s.classList.remove('opacity-100');
                        s.classList.add('opacity-0', 'hidden');
                    }
                });

                dots.forEach(function(d, i) {
                    const isActive = i === activeSlide;
                    d.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    d.className = isActive
                        ? 'hero-dot h-2 w-6 rounded-sm bg-brand-yellow transition-all'
                        : 'hero-dot h-2 w-2.5 rounded-sm bg-white/40 hover:bg-white/70 transition-all';
                });
            }

            function syncPauseUi() {
                if (!heroPauseBtn || !heroPauseIcon) return;
                heroPauseBtn.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
                heroPauseBtn.setAttribute('aria-label', userPaused ? 'Lanjutkan slide otomatis' : 'Jeda pergantian slide otomatis');
                heroPauseIcon.className = userPaused ? 'ph-bold ph-play text-sm' : 'ph-bold ph-pause text-sm';
            }

            function scheduleAutoSlide() {
                clearInterval(slideTimer);
                if (userPaused) return;
                slideTimer = setInterval(function() {
                    if (!hoverPaused && !document.hidden) {
                        showSlide(activeSlide + 1);
                    }
                }, 6000);
            }

            if (heroPrev) heroPrev.addEventListener('click', function() { showSlide(activeSlide - 1); scheduleAutoSlide(); });
            if (heroNext) heroNext.addEventListener('click', function() { showSlide(activeSlide + 1); scheduleAutoSlide(); });
            dots.forEach(function(d) {
                d.addEventListener('click', function() {
                    showSlide(Number(d.getAttribute('data-go') || 0));
                    scheduleAutoSlide();
                });
            });
            if (heroPauseBtn) {
                heroPauseBtn.addEventListener('click', function() {
                    userPaused = !userPaused;
                    syncPauseUi();
                    scheduleAutoSlide();
                });
            }
            if (heroRoot) {
                heroRoot.addEventListener('mouseenter', function() { hoverPaused = true; });
                heroRoot.addEventListener('mouseleave', function() { hoverPaused = false; });
                heroRoot.addEventListener('focusin', function() { hoverPaused = true; });
                heroRoot.addEventListener('focusout', function() { hoverPaused = false; });
            }
            syncPauseUi();
            scheduleAutoSlide();

            // 2. HORIZONTAL RAIL: Arrows + Drag-to-Scroll + Progress Bar
            const rail = document.getElementById('new-arrivals-rail');
            const railPrev = document.getElementById('rail-prev');
            const railNext = document.getElementById('rail-next');
            const railProgress = document.getElementById('rail-progress-bar');
            const railFadeRight = document.getElementById('rail-fade-right');

            if (rail && railPrev && railNext) {
                function updateRailState() {
                    const maxScroll = Math.max(0, rail.scrollWidth - rail.clientWidth);
                    railPrev.disabled = rail.scrollLeft <= 4;
                    railNext.disabled = maxScroll <= 4 || rail.scrollLeft >= maxScroll - 4;
                    if (railProgress) {
                        const pct = maxScroll <= 4 ? 100 : Math.min(100, Math.max(15, ((rail.scrollLeft + rail.clientWidth) / rail.scrollWidth) * 100));
                        railProgress.style.width = pct.toFixed(1) + '%';
                    }
                    if (railFadeRight) {
                        railFadeRight.style.opacity = rail.scrollLeft >= maxScroll - 8 ? '0' : '1';
                    }
                }

                railPrev.addEventListener('click', function() {
                    rail.scrollBy({ left: -310, behavior: reducedMotion ? 'auto' : 'smooth' });
                });
                railNext.addEventListener('click', function() {
                    rail.scrollBy({ left: 310, behavior: reducedMotion ? 'auto' : 'smooth' });
                });

                let isDown = false;
                let startX = 0;
                let scrollStart = 0;
                let draggedDistance = 0;

                rail.addEventListener('mousedown', function(e) {
                    if (e.button !== 0) return;
                    isDown = true;
                    draggedDistance = 0;
                    startX = e.pageX - rail.offsetLeft;
                    scrollStart = rail.scrollLeft;
                    rail.classList.add('is-dragging');
                });

                window.addEventListener('mouseup', function() {
                    if (!isDown) return;
                    isDown = false;
                    rail.classList.remove('is-dragging');
                });

                rail.addEventListener('mousemove', function(e) {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - rail.offsetLeft;
                    const walk = x - startX;
                    draggedDistance = Math.abs(walk);
                    rail.scrollLeft = scrollStart - walk;
                });

                rail.addEventListener('click', function(e) {
                    if (draggedDistance > 8) {
                        e.preventDefault();
                        draggedDistance = 0;
                    }
                }, true);

                rail.addEventListener('scroll', updateRailState, { passive: true });
                window.addEventListener('resize', updateRailState);
                updateRailState();
            }

            // 3. EXPLORER FILTER, BUDGET, SORT & LOAD MORE (#jelajah-produk)
            const grid = document.getElementById('explorer-grid');
            const cards = grid ? Array.from(grid.querySelectorAll('.explorer-card')) : [];
            const catBtns = Array.from(document.querySelectorAll('.cat-filter-btn'));
            const budgetBtns = Array.from(document.querySelectorAll('.budget-filter-btn'));
            const flagBtns = Array.from(document.querySelectorAll('.flag-filter-btn'));
            const condBtns = Array.from(document.querySelectorAll('.cond-filter-btn'));
            const sortSelect = document.getElementById('home-sort');
            const countEl = document.getElementById('explorer-count');
            const emptyEl = document.getElementById('explorer-empty');
            const resetBtn = document.getElementById('btn-reset-explorer');
            const emptyResetBtn = document.getElementById('btn-empty-reset');
            const loadMoreWrap = document.getElementById('explorer-load-more-wrap');
            const loadMoreBtn = document.getElementById('btn-load-more');

            const STEP = <?= (int)$initialExplorerLimit ?>;
            const state = {
                cat: 'all',
                budget: 'all',
                nego: false,
                barter: false,
                cond: '',
                sort: 'newest',
                limit: STEP
            };

            function matchBudget(price, key) {
                if (key === 'under1') return price < 1000000;
                if (key === '1to3') return price >= 1000000 && price <= 3000000;
                if (key === '3to5') return price > 3000000 && price <= 5000000;
                if (key === 'over5') return price > 5000000;
                return true;
            }

            function syncButtonStyles() {
                catBtns.forEach(function(btn) {
                    const active = btn.getAttribute('data-cat') === state.cat;
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    btn.className = active
                        ? 'cat-filter-btn shrink-0 min-h-[38px] px-3.5 py-1.5 rounded-md border text-xs font-semibold transition bg-[#272727] text-white border-[#272727]'
                        : 'cat-filter-btn shrink-0 min-h-[38px] px-3.5 py-1.5 rounded-md border text-xs font-medium transition bg-white text-gray-600 border-gray-200 hover:border-gray-400';
                });

                budgetBtns.forEach(function(btn) {
                    const active = btn.getAttribute('data-budget') === state.budget;
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    btn.className = active
                        ? 'budget-filter-btn min-h-[32px] px-2.5 py-1 rounded-md border text-[11px] font-semibold transition bg-[#272727] text-white border-[#272727]'
                        : 'budget-filter-btn min-h-[32px] px-2.5 py-1 rounded-md border text-[11px] font-medium transition bg-white text-gray-600 border-gray-200 hover:border-gray-400';
                });

                flagBtns.forEach(function(btn) {
                    const key = btn.getAttribute('data-filter');
                    const active = Boolean(state[key]);
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    if (key === 'nego') {
                        btn.className = active
                            ? 'flag-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border text-xs font-semibold transition bg-amber-50 text-amber-800 border-amber-300'
                            : 'flag-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border border-gray-200 bg-white text-gray-600 hover:border-amber-300 text-xs font-medium transition';
                    } else {
                        btn.className = active
                            ? 'flag-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border text-xs font-semibold transition bg-sky-50 text-sky-700 border-sky-300'
                            : 'flag-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border border-gray-200 bg-white text-gray-600 hover:border-sky-300 text-xs font-medium transition';
                    }
                });

                condBtns.forEach(function(btn) {
                    const key = btn.getAttribute('data-cond');
                    const active = state.cond === key;
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    if (key === 'baru') {
                        btn.className = active
                            ? 'cond-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border text-xs font-semibold transition bg-emerald-50 text-emerald-700 border-emerald-300'
                            : 'cond-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border border-gray-200 bg-white text-gray-600 hover:border-emerald-300 text-xs font-medium transition';
                    } else {
                        btn.className = active
                            ? 'cond-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border text-xs font-semibold transition bg-[#FFF8E1] text-[#92400E] border-amber-300'
                            : 'cond-filter-btn inline-flex items-center gap-1.5 min-h-[36px] px-3 py-1.5 rounded-md border border-gray-200 bg-white text-gray-600 hover:border-amber-300 text-xs font-medium transition';
                    }
                });

                const hasActiveFilter = state.cat !== 'all' || state.budget !== 'all' || state.nego || state.barter || state.cond !== '';
                if (resetBtn) resetBtn.classList.toggle('hidden', !hasActiveFilter);
            }

            function renderExplorer(animate) {
                if (!grid) return;
                syncButtonStyles();

                const sorted = cards.slice().sort(function(a, b) {
                    const priceA = Number(a.getAttribute('data-harga') || 0);
                    const priceB = Number(b.getAttribute('data-harga') || 0);
                    const revA = Number(a.getAttribute('data-reviews') || 0);
                    const revB = Number(b.getAttribute('data-reviews') || 0);
                    const ordA = Number(a.getAttribute('data-order') || 0);
                    const ordB = Number(b.getAttribute('data-order') || 0);

                    if (state.sort === 'price-asc') return priceA - priceB || ordA - ordB;
                    if (state.sort === 'price-desc') return priceB - priceA || ordA - ordB;
                    if (state.sort === 'reviews') return revB - revA || ordA - ordB;
                    return ordA - ordB;
                });

                const frag = document.createDocumentFragment();
                const matching = [];

                sorted.forEach(function(card) {
                    frag.appendChild(card);
                    const price = Number(card.getAttribute('data-harga') || 0);
                    const matchCat = state.cat === 'all' || card.getAttribute('data-kategori') === state.cat;
                    const matchBud = matchBudget(price, state.budget);
                    const matchNego = !state.nego || card.getAttribute('data-nego') === '1';
                    const matchBarter = !state.barter || card.getAttribute('data-barter') === '1';
                    const matchCond = state.cond === '' || card.getAttribute('data-kondisi-group') === state.cond;

                    if (matchCat && matchBud && matchNego && matchBarter && matchCond) {
                        matching.push(card);
                    } else {
                        card.classList.add('hidden');
                    }
                });

                let shownCount = 0;
                matching.forEach(function(card, idx) {
                    if (idx < state.limit) {
                        card.classList.remove('hidden');
                        if (animate && !reducedMotion) {
                            card.classList.remove('card-enter');
                            card.style.animationDelay = Math.min(160, (idx % STEP) * 30) + 'ms';
                            void card.offsetWidth;
                            card.classList.add('card-enter');
                        }
                        shownCount++;
                    } else {
                        card.classList.add('hidden');
                    }
                });

                grid.appendChild(frag);

                const totalMatch = matching.length;
                if (countEl) {
                    countEl.textContent = totalMatch === 0
                        ? '0 produk cocok dengan filter aktif'
                        : 'Menampilkan ' + shownCount + ' dari ' + totalMatch + ' produk tersedia';
                }
                if (emptyEl) emptyEl.classList.toggle('hidden', totalMatch > 0);
                if (loadMoreWrap) loadMoreWrap.classList.toggle('hidden', shownCount >= totalMatch);
            }

            function resetAllFilters() {
                state.cat = 'all';
                state.budget = 'all';
                state.nego = false;
                state.barter = false;
                state.cond = '';
                state.limit = STEP;
                renderExplorer(true);
            }

            if (grid) {
                catBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        state.cat = btn.getAttribute('data-cat') || 'all';
                        state.limit = STEP;
                        renderExplorer(true);
                    });
                });

                budgetBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        state.budget = btn.getAttribute('data-budget') || 'all';
                        state.limit = STEP;
                        renderExplorer(true);
                    });
                });

                flagBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        const key = btn.getAttribute('data-filter');
                        if (key === 'nego' || key === 'barter') {
                            state[key] = !state[key];
                            state.limit = STEP;
                            renderExplorer(true);
                        }
                    });
                });

                condBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        const key = btn.getAttribute('data-cond') || '';
                        state.cond = state.cond === key ? '' : key;
                        state.limit = STEP;
                        renderExplorer(true);
                    });
                });

                if (sortSelect) {
                    sortSelect.addEventListener('change', function() {
                        state.sort = sortSelect.value || 'newest';
                        renderExplorer(true);
                    });
                }

                if (resetBtn) resetBtn.addEventListener('click', resetAllFilters);
                if (emptyResetBtn) emptyResetBtn.addEventListener('click', resetAllFilters);
                if (loadMoreBtn) {
                    loadMoreBtn.addEventListener('click', function() {
                        state.limit += STEP;
                        renderExplorer(true);
                    });
                }

                renderExplorer(false);
            }

            // 4. TOAST DEMO ANTI-BOTTLENECK CHECKER
            // TODO: hubungkan tombol [data-open-bottleneck] ke fitur Anti-Bottleneck Checker saat sudah tersedia
            const toastEl = document.getElementById('loote-toast');
            const toastTextEl = document.getElementById('loote-toast-text');
            let toastTimer = null;

            function showDemoToast(message) {
                if (!toastEl || !toastTextEl) return;
                toastTextEl.textContent = message;
                toastEl.classList.remove('hidden');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(function() {
                    toastEl.classList.add('hidden');
                }, 3200);
            }

            document.addEventListener('click', function(e) {
                if (e.target.closest('[data-open-bottleneck]')) {
                    showDemoToast('Fitur Anti-Bottleneck Checker sedang dalam tahap pengembangan (Segera hadir).');
                }
            });

            // 5. SCROLL PARALLAX ENGINE (GPU translate3d)
            const parallaxBgs = Array.from(document.querySelectorAll('[data-parallax-bg]'));
            const parallaxFgs = Array.from(document.querySelectorAll('[data-parallax-fg]'));
            const parallaxItems = Array.from(document.querySelectorAll('[data-parallax-range]'));

            if (!reducedMotion) {
                let parallaxTicking = false;

                function applyParallax() {
                    parallaxTicking = false;
                    const scrollY = window.scrollY || window.pageYOffset || 0;
                    const vh = window.innerHeight || 800;

                    if (scrollY <= 600) {
                        parallaxBgs.forEach(function(el) {
                            const speed = Number(el.getAttribute('data-parallax-bg') || 0.3);
                            const y = Math.min(120, scrollY * speed);
                            el.style.transform = 'translate3d(0, ' + y.toFixed(1) + 'px, 0) scale(1.08)';
                        });

                        const heroFade = Math.max(0.25, 1 - scrollY / 420);
                        parallaxFgs.forEach(function(el) {
                            const speed = Number(el.getAttribute('data-parallax-fg') || -0.14);
                            const y = Math.max(-70, scrollY * speed);
                            el.style.transform = 'translate3d(0, ' + y.toFixed(1) + 'px, 0)';
                            el.style.opacity = heroFade.toFixed(3);
                        });
                    }

                    parallaxItems.forEach(function(el) {
                        const rect = el.getBoundingClientRect();
                        if (rect.bottom < -60 || rect.top > vh + 60) return;
                        const range = Number(el.getAttribute('data-parallax-range') || 16);
                        const ratio = ((rect.top + rect.height / 2) / vh) - 0.5;
                        const offset = Math.max(-Math.abs(range), Math.min(Math.abs(range), ratio * range * 2));
                        el.style.transform = 'translate3d(0, ' + offset.toFixed(1) + 'px, 0)';
                    });
                }

                function requestParallaxUpdate() {
                    if (!parallaxTicking) {
                        parallaxTicking = true;
                        requestAnimationFrame(applyParallax);
                    }
                }

                window.addEventListener('scroll', requestParallaxUpdate, { passive: true });
                window.addEventListener('resize', requestParallaxUpdate, { passive: true });
                applyParallax();
            }

            // 6. ONE-SHOT SCROLL REVEAL (stagger halus pada card)
            const revealSections = Array.from(document.querySelectorAll('.reveal-section'));
            if (!reducedMotion && 'IntersectionObserver' in window) {
                const io = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            const cardsInSec = entry.target.querySelectorAll('.loote-card');
                            cardsInSec.forEach(function(card, idx) {
                                card.style.animationDelay = Math.min(180, idx * 40) + 'ms';
                                card.classList.add('card-enter');
                            });
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

                revealSections.forEach(function(sec) { io.observe(sec); });
            } else {
                revealSections.forEach(function(sec) { sec.classList.add('is-visible'); });
            }

            // 7. BACK TO TOP BUTTON
            const backTopBtn = document.getElementById('btn-back-to-top');
            if (backTopBtn) {
                window.addEventListener('scroll', function() {
                    const show = (window.scrollY || window.pageYOffset || 0) > 500;
                    backTopBtn.classList.toggle('opacity-0', !show);
                    backTopBtn.classList.toggle('pointer-events-none', !show);
                    backTopBtn.classList.toggle('translate-y-2', !show);
                }, { passive: true });

                backTopBtn.addEventListener('click', function() {
                    window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
                });
            }
        })();
    </script>
</body>

</html>

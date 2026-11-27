<?php
// katalog.php - UI Katalog Produk Seller (Phase 2: Frontend UI Only / Mock Data)
require_once "auth.php";

$sessionUser = getCurrentUser();
$currentUser = $sessionUser;
$navActivePage = 'katalog';

$accountActiveMenu = 'toko_katalog';
$accountSidebarUser = [
    'nama'        => $sessionUser['nama'] ?? 'Loot-E Official Store',
    'email'       => $sessionUser['email'] ?? 'seller@loote.com',
    'is_verified' => $sessionUser ? (int)($sessionUser['is_verified'] ?? 0) : 1,
];

// shortcut: mock array statis untuk tahap UI-First; akan diganti query PDO produk milik seller@loote.com setelah UI disetujui
$mockProducts = [
    [
        'id'           => 1,
        'nama'         => 'GTX 750 Ti 2GB GDDR5',
        'kategori'     => 'GPU',
        'harga'        => 650000,
        'stok'         => 2,
        'kondisi'      => 'Bekas (Normal)',
        'status'       => 'tersedia',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 2,
        'nama'         => 'SSD Kingston A400 240GB',
        'kategori'     => 'Storage',
        'harga'        => 275000,
        'stok'         => 1,
        'kondisi'      => 'Baru',
        'status'       => 'draft',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 3,
        'nama'         => 'RAM Corsair Vengeance 16GB (2x8GB)',
        'kategori'     => 'RAM',
        'harga'        => 550000,
        'stok'         => 3,
        'kondisi'      => 'Bekas (Normal)',
        'status'       => 'tersedia',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 4,
        'nama'         => 'Motherboard ASUS H81M-K',
        'kategori'     => 'Motherboard',
        'harga'        => 450000,
        'stok'         => 0,
        'kondisi'      => 'Bekas (Normal)',
        'status'       => 'habis',
        'foto'         => null,
        'terjual_pada' => '20 Jan 2026',
    ],
    [
        'id'           => 5,
        'nama'         => 'Logitech G102 Lightsync',
        'kategori'     => 'Mouse',
        'harga'        => 200000,
        'stok'         => 0,
        'kondisi'      => 'Bekas (Normal)',
        'status'       => 'nonaktif',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 6,
        'nama'         => 'HyperX Cloud Stinger',
        'kategori'     => 'Headphones',
        'harga'        => 375000,
        'stok'         => 0,
        'kondisi'      => 'Bekas (Normal)',
        'status'       => 'habis',
        'foto'         => null,
        'terjual_pada' => '15 Jan 2026',
    ],
    // Halaman 2 & 3 (mock simulasi pagination)
    [
        'id'           => 7,
        'nama'         => 'GTX 1660 Super 6GB GDDR6',
        'kategori'     => 'GPU',
        'harga'        => 2400000,
        'stok'         => 5,
        'kondisi'      => 'Bekas - Seperti Baru',
        'status'       => 'tersedia',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 8,
        'nama'         => 'AMD Ryzen 5 5600X 6-Core 12-Thread',
        'kategori'     => 'CPU',
        'harga'        => 2100000,
        'stok'         => 7,
        'kondisi'      => 'Baru',
        'status'       => 'tersedia',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 9,
        'nama'         => 'Team T-Force Delta RGB 16GB (2x8GB) DDR4',
        'kategori'     => 'RAM',
        'harga'        => 850000,
        'stok'         => 10,
        'kondisi'      => 'Baru',
        'status'       => 'tersedia',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 10,
        'nama'         => 'Lenovo Ideapad S145-15AST',
        'kategori'     => 'Laptop',
        'harga'        => 1725000,
        'stok'         => 3,
        'kondisi'      => 'Bekas - Mulus',
        'status'       => 'draft',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 11,
        'nama'         => 'iPhone 11 128GB Black iBox',
        'kategori'     => 'Phone',
        'harga'        => 3150000,
        'stok'         => 4,
        'kondisi'      => 'Bekas - Mulus',
        'status'       => 'tersedia',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 12,
        'nama'         => 'ASUS ROG Strix XG27AQM 270Hz',
        'kategori'     => 'Monitor',
        'harga'        => 6441000,
        'stok'         => 0,
        'kondisi'      => 'Baru',
        'status'       => 'nonaktif',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 13,
        'nama'         => 'Sony WH-1000XM4 Wireless Noise Cancelling',
        'kategori'     => 'Headphones',
        'harga'        => 3200000,
        'stok'         => 3,
        'kondisi'      => 'Bekas - Seperti Baru',
        'status'       => 'tersedia',
        'foto'         => null,
        'terjual_pada' => null,
    ],
    [
        'id'           => 14,
        'nama'         => 'Logitech G Pro X Superlight Wireless',
        'kategori'     => 'Mouse',
        'harga'        => 1450000,
        'stok'         => 6,
        'kondisi'      => 'Bekas - Mulus',
        'status'       => 'tersedia',
        'foto'         => null,
        'terjual_pada' => null,
    ],
];

$statusBadgeMap = [
    'tersedia' => [
        'label'    => 'Sedang Tayang',
        'pillCls'  => 'bg-[#E6F7EE] text-[#15803D]',
        'dotCls'   => 'bg-[#22C55E]',
    ],
    'draft' => [
        'label'    => 'Draft',
        'pillCls'  => 'bg-[#DCDEE2] text-[#4B5563]',
        'dotCls'   => 'bg-[#6B7280]',
    ],
    'habis' => [
        'label'    => 'Terjual',
        'pillCls'  => 'bg-[#DCDEE2] text-[#4B5563]',
        'dotCls'   => 'bg-[#6B7280]',
    ],
    'nonaktif' => [
        'label'    => 'Nonaktif',
        'pillCls'  => 'bg-[#FCE7F3] text-[#BE123C]',
        'dotCls'   => 'bg-[#F43F5E]',
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

                    <button type="button" id="btn-tambah-produk"
                        data-demo-toast="Halaman Tambah Produk belum tersedia saat ini (mode demo)."
                        class="inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition shrink-0 cursor-pointer self-start">
                        <i class="ph-bold ph-plus text-xs" aria-hidden="true"></i>
                        <span>Tambah Produk</span>
                    </button>
                </header>

                <!-- Filter Status Produk -->
                <div class="mt-5 flex flex-wrap items-center gap-2" role="tablist" aria-label="Filter status produk katalog">
                    <?php foreach ($filterTabs as $idx => $tab): ?>
                        <?php $isDefaultActive = $idx === 0; ?>
                        <button type="button"
                            role="tab"
                            data-katalog-tab="<?= htmlspecialchars($tab['key'], ENT_QUOTES, 'UTF-8') ?>"
                            aria-selected="<?= $isDefaultActive ? 'true' : 'false' ?>"
                            class="katalog-tab px-4 py-1.5 text-xs sm:text-[13px] rounded-[2px] border transition cursor-pointer <?= $isDefaultActive ? 'bg-brand-yellow text-[#272727] font-semibold border-brand-yellow' : 'bg-white text-[#272727] font-medium border-gray-200/90 hover:bg-gray-50' ?>">
                            <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Kontainer Grid Katalog Produk -->
                <div class="mt-4 bg-[#EAEAEA] p-4 sm:p-5 md:p-6">
                    <!-- Grid Kartu Produk (3 Kolom Desktop) -->
                    <div id="katalog-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
                        <?php foreach ($mockProducts as $item): ?>
                            <?php
                            $statusKey = $item['status'];
                            $badge     = $statusBadgeMap[$statusKey] ?? $statusBadgeMap['tersedia'];
                            $catIcon   = kategoriIcon($item['kategori']);
                            $namaEsc   = htmlspecialchars($item['nama'], ENT_QUOTES, 'UTF-8');
                            ?>
                            <article data-katalog-card data-status="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"
                                class="bg-white p-3.5 sm:p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-full">

                                <!-- Baris Atas Kartu: Badge Status & Tombol Hapus -->
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold leading-none <?= $badge['pillCls'] ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $badge['dotCls'] ?>" aria-hidden="true"></span>
                                        <span><?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </span>

                                    <button type="button"
                                        aria-label="Hapus produk <?= $namaEsc ?>"
                                        data-demo-toast="Aksi Hapus untuk <?= $namaEsc ?> belum tersedia saat ini (mode demo)."
                                        class="w-7 h-7 bg-[#FEE2E2] hover:bg-[#FECACA] text-[#EF4444] rounded-[2px] flex items-center justify-center transition cursor-pointer shrink-0">
                                        <i class="ph ph-trash text-sm" aria-hidden="true"></i>
                                    </button>
                                </div>

                                <!-- Gambar Produk (Fallback Ikon Kategori Loot-E) -->
                                <div class="aspect-[16/10] w-full bg-[#F4F4F4] border border-gray-100 overflow-hidden mb-3.5 flex flex-col items-center justify-center">
                                    <?php if (!empty($item['foto'])): ?>
                                        <img src="<?= htmlspecialchars($item['foto'], ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= $namaEsc ?>"
                                            width="400" height="250"
                                            class="w-full h-full object-cover" />
                                    <?php else: ?>
                                        <i class="ph <?= htmlspecialchars($catIcon, ENT_QUOTES, 'UTF-8') ?> text-4xl text-gray-500" aria-hidden="true"></i>
                                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mt-1">
                                            <?= htmlspecialchars($item['kategori'], ENT_QUOTES, 'UTF-8') ?>
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
                                            Terjual pada: <?= htmlspecialchars((string)$item['terjual_pada'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="mt-1 text-[11px] text-gray-500">
                                            Stok: <?= (int)$item['stok'] ?> unit &bull; Kondisi: <?= htmlspecialchars($item['kondisi'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Tombol Aksi Bawah (Sesuai Status Produk) -->
                                <div class="mt-4 space-y-2">
                                    <?php if ($statusKey === 'tersedia'): ?>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button type="button"
                                                data-demo-toast="Aksi Edit untuk <?= $namaEsc ?> belum tersedia saat ini (mode demo)."
                                                class="w-full bg-[#272727] hover:bg-black active:scale-[0.99] text-white text-xs font-semibold py-2 rounded-[2px] transition cursor-pointer">
                                                Edit
                                            </button>
                                            <button type="button"
                                                data-demo-toast="Aksi Nonaktifkan untuk <?= $namaEsc ?> belum tersedia saat ini (mode demo)."
                                                class="w-full bg-[#D1D5DB] hover:bg-[#C4C9D0] active:scale-[0.99] text-[#272727] text-xs font-semibold py-2 rounded-[2px] transition cursor-pointer">
                                                Nonaktifkan
                                            </button>
                                        </div>
                                        <button type="button"
                                            data-demo-toast="Aksi Lihat Produk untuk <?= $namaEsc ?> belum tersedia saat ini (mode demo)."
                                            class="w-full bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold py-2 rounded-[2px] transition cursor-pointer">
                                            Lihat Produk
                                        </button>

                                    <?php elseif ($statusKey === 'nonaktif'): ?>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button type="button"
                                                data-demo-toast="Aksi Edit untuk <?= $namaEsc ?> belum tersedia saat ini (mode demo)."
                                                class="w-full bg-[#272727] hover:bg-black active:scale-[0.99] text-white text-xs font-semibold py-2 rounded-[2px] transition cursor-pointer">
                                                Edit
                                            </button>
                                            <button type="button"
                                                data-demo-toast="Aksi Aktifkan untuk <?= $namaEsc ?> belum tersedia saat ini (mode demo)."
                                                class="w-full bg-[#D1D5DB] hover:bg-[#C4C9D0] active:scale-[0.99] text-[#272727] text-xs font-semibold py-2 rounded-[2px] transition cursor-pointer">
                                                Aktifkan
                                            </button>
                                        </div>
                                        <button type="button"
                                            data-demo-toast="Aksi Lihat Produk untuk <?= $namaEsc ?> belum tersedia saat ini (mode demo)."
                                            class="w-full bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold py-2 rounded-[2px] transition cursor-pointer">
                                            Lihat Produk
                                        </button>

                                    <?php elseif ($statusKey === 'draft'): ?>
                                        <button type="button"
                                            data-demo-toast="Aksi Lanjutkan Draft untuk <?= $namaEsc ?> belum tersedia saat ini (mode demo)."
                                            class="w-full bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold py-2 rounded-[2px] transition cursor-pointer">
                                            Lanjutkan Draft
                                        </button>

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
                </div>
            </section>

        </div>
    </main>

    <!-- Toast Notifikasi -->
    <div id="katalog-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg max-w-sm">
    </div>

    <!-- Footer & Help FAB -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function () {
            const PER_PAGE = 6;
            const cards = Array.from(document.querySelectorAll('[data-katalog-card]'));
            const tabs = Array.from(document.querySelectorAll('[data-katalog-tab]'));
            const emptyState = document.getElementById('katalog-empty-state');
            const resetFilterBtn = document.getElementById('katalog-reset-filter');
            const paginationNav = document.getElementById('katalog-pagination');
            const toast = document.getElementById('katalog-toast');

            let activeStatus = 'semua';
            let currentPage = 1;
            let toastTimer = null;

            function showToast(message) {
                if (!toast || !message) return;
                toast.textContent = message;
                toast.classList.remove('hidden');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function () {
                    toast.classList.add('hidden');
                }, 3200);
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

                // Tombol Sebelumnya
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

                // Nomor Halaman
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

                // Tombol Berikutnya
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
                    if (msg) showToast(msg);
                });
            });

            render();
        })();
    </script>
</body>

</html>

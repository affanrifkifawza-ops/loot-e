<?php
// help.php - Pusat Bantuan Loot-E
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Ambil data user yang sedang login jika ada
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $currentUser = [
        'id'    => $_SESSION['user_id'] ?? '',
        'nama'  => $_SESSION['nama'] ?? 'Pengguna',
        'email' => $_SESSION['email'] ?? '',
        'peran' => $_SESSION['peran'] ?? 'buyer'
    ];
}

$navActivePage = 'help';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Pusat bantuan Loot-E untuk akun, belanja, pesanan, pembayaran, barter, dan penjualan." />
    <title>Pusat Bantuan | Loot-E</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- Tailwind CSS CDN & Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ["Plus Jakarta Sans", "Inter", "sans-serif"],
                    },
                    colors: {
                        brand: {
                            yellow: "#F5B700",
                            dark: "#303135",
                            footer: "#383838",
                            bg: "#F8F9FA",
                        },
                    },
                },
            },
        };
    </script>

    <style>
        :root {
            color-scheme: light;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: "Plus Jakarta Sans", "Inter", sans-serif;
        }

        button,
        a,
        summary,
        select,
        label[for],
        input[type="checkbox"],
        input[type="radio"] {
            cursor: pointer;
        }

        button:disabled {
            cursor: not-allowed;
        }

        :focus-visible {
            outline: 3px solid #f5b700;
            outline-offset: 3px;
        }

        .hero-wash {
            background: linear-gradient(105deg,
                    #ffffff 0%,
                    #ffffff 70%,
                    #fff9e4 100%);
        }

        .category-card[aria-pressed="true"] {
            background: #fff9e4;
            box-shadow: inset 0 0 0 2px #f5b700;
        }

        .faq-trigger[aria-expanded="true"] .faq-chevron {
            transform: rotate(180deg);
        }

        dialog::backdrop {
            background: rgba(38, 38, 40, 0.64);
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="bg-[#f5f5f6] text-[#363638] antialiased min-h-screen flex flex-col justify-between">

    <!-- Komponen Navigasi -->
    <?php require_once 'components/navbar.php'; ?>

    <!-- Konten Utama -->
    <main id="main-content" class="flex-grow">
        <!-- Hero Section & Pencarian -->
        <section class="px-5 pt-6 sm:px-8 sm:pt-8 lg:px-[76px]" aria-labelledby="help-title">
            <div class="hero-wash mx-auto flex min-h-[320px] max-w-[1288px] flex-col items-center justify-center border border-[#eeeeee] px-5 py-10 text-center sm:px-10 shadow-sm">
                <p class="mb-3 inline-flex items-center gap-1.5 bg-[#fff8dc] px-3.5 py-1 text-xs font-bold text-[#d49e00] rounded-full">
                    <i class="ph-fill ph-headphones text-sm" aria-hidden="true"></i>
                    Pusat Bantuan Resmi Loot-E
                </p>
                <h1 id="help-title" class="text-3xl font-extrabold tracking-[-0.04em] text-[#38383a] sm:text-[40px]">
                    How Can We Help You?
                </h1>
                <p class="mt-2 max-w-[535px] text-sm leading-6 text-[#7d7d80] sm:text-[15px]">
                    Temukan jawaban dan panduan untuk membantu pengalamanmu menggunakan Loot-E.
                </p>

                <form id="search-form" class="mt-6 w-full max-w-[672px]" novalidate>
                    <label for="help-search" class="sr-only">Cari pertanyaan bantuan</label>
                    <div class="flex min-h-[50px] items-center rounded-full border border-[#d5d5d6] bg-white p-1.5 shadow-sm focus-within:border-brand-yellow focus-within:ring-2 focus-within:ring-brand-yellow/30 transition">
                        <i class="ph ph-magnifying-glass ml-3 text-xl text-[#97979a]" aria-hidden="true"></i>
                        <input id="help-search" type="search" autocomplete="off" placeholder="Cari pertanyaan atau topik bantuan..." class="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm text-[#363638] placeholder:text-[#a1a1a4] focus:outline-none" />
                        <button type="submit" class="min-h-[38px] shrink-0 rounded-full bg-brand-yellow px-5 text-xs font-bold text-brand-dark transition hover:bg-yellow-500 active:translate-y-px shadow-sm">
                            Cari
                        </button>
                    </div>
                    <div class="mt-2.5 flex flex-wrap justify-center items-center gap-x-2 gap-y-1 text-xs text-[#8d8d90]">
                        <span class="font-semibold">Populer:</span>
                        <button type="button" data-search-term="barter" class="font-semibold underline decoration-[#b5b5b7] underline-offset-2 hover:text-[#363638]">
                            Panduan Barter
                        </button>
                        <span aria-hidden="true">•</span>
                        <button type="button" data-search-term="kondisi" class="font-semibold underline decoration-[#b5b5b7] underline-offset-2 hover:text-[#363638]">
                            Kondisi Produk
                        </button>
                        <span aria-hidden="true">•</span>
                        <button type="button" data-search-term="lacak" class="font-semibold underline decoration-[#b5b5b7] underline-offset-2 hover:text-[#363638]">
                            Lacak Paket
                        </button>
                        <span aria-hidden="true">•</span>
                        <button type="button" data-search-term="jual" class="font-semibold underline decoration-[#b5b5b7] underline-offset-2 hover:text-[#363638]">
                            Cara Jual
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Kategori Bantuan -->
        <section id="categories" class="mx-auto max-w-[1288px] px-5 pb-8 pt-8 sm:px-8 lg:px-[76px]" aria-labelledby="categories-title">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 id="categories-title" class="text-2xl font-extrabold tracking-[-0.04em] text-[#38383a]">
                        Browse by Category
                    </h2>
                    <p class="mt-0.5 text-xs text-[#98989b]">
                        Pilih topik bantuan yang relevan dengan kebutuhan transaksimu.
                    </p>
                </div>
                <p class="hidden pb-0.5 text-[11px] font-bold text-[#858588] sm:block">
                    6 Kategori Tersedia
                </p>
            </div>

            <div class="mt-6 grid overflow-hidden border border-[#e5e5e7] bg-[#eeeeee] gap-[1px] sm:grid-cols-2 lg:grid-cols-3 shadow-sm" role="group" aria-label="Kategori bantuan">
                <button type="button" data-category="account" aria-pressed="false" class="category-card min-h-[87px] bg-white px-5 py-4 text-left transition hover:bg-[#fffdf5]">
                    <span class="flex items-center gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-yellow/20 text-[23px] text-brand-dark">
                            <i class="ph ph-user-gear" aria-hidden="true"></i>
                        </span>
                        <span>
                            <span class="block text-[15px] font-bold tracking-[-0.02em]">Account &amp; Profile</span>
                            <span class="mt-0.5 block text-xs text-[#949497]">Pengaturan akun dan informasi profil.</span>
                        </span>
                    </span>
                </button>
                <button type="button" data-category="products" aria-pressed="false" class="category-card min-h-[87px] bg-white px-5 py-4 text-left transition hover:bg-[#fffdf5]">
                    <span class="flex items-center gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-yellow/20 text-[23px] text-brand-dark">
                            <i class="ph ph-shopping-bag" aria-hidden="true"></i>
                        </span>
                        <span>
                            <span class="block text-[15px] font-bold tracking-[-0.02em]">Products &amp; Shopping</span>
                            <span class="mt-0.5 block text-xs text-[#949497]">Pencarian produk dan informasi barang.</span>
                        </span>
                    </span>
                </button>
                <button type="button" data-category="orders" aria-pressed="false" class="category-card min-h-[87px] bg-white px-5 py-4 text-left transition hover:bg-[#fffdf5]">
                    <span class="flex items-center gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-yellow/20 text-[23px] text-brand-dark">
                            <i class="ph ph-package" aria-hidden="true"></i>
                        </span>
                        <span>
                            <span class="block text-[15px] font-bold tracking-[-0.02em]">Orders &amp; Shipping</span>
                            <span class="mt-0.5 block text-xs text-[#949497]">Status pesanan dan pelacakan pengiriman.</span>
                        </span>
                    </span>
                </button>
                <button type="button" data-category="payments" aria-pressed="false" class="category-card min-h-[87px] bg-white px-5 py-4 text-left transition hover:bg-[#fffdf5]">
                    <span class="flex items-center gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-yellow/20 text-[23px] text-brand-dark">
                            <i class="ph ph-credit-card" aria-hidden="true"></i>
                        </span>
                        <span>
                            <span class="block text-[15px] font-bold tracking-[-0.02em]">Payments</span>
                            <span class="mt-0.5 block text-xs text-[#949497]">Informasi pembayaran dan transaksi.</span>
                        </span>
                    </span>
                </button>
                <button type="button" data-category="barter" aria-pressed="false" class="category-card min-h-[87px] bg-white px-5 py-4 text-left transition hover:bg-[#fffdf5]">
                    <span class="flex items-center gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-yellow/20 text-[23px] text-brand-dark">
                            <i class="ph ph-arrows-left-right" aria-hidden="true"></i>
                        </span>
                        <span>
                            <span class="block text-[15px] font-bold tracking-[-0.02em]">Negotiation &amp; Barter</span>
                            <span class="mt-0.5 block text-xs text-[#949497]">Panduan penawaran harga dan sistem barter.</span>
                        </span>
                    </span>
                </button>
                <button type="button" data-category="selling" aria-pressed="false" class="category-card min-h-[87px] bg-white px-5 py-4 text-left transition hover:bg-[#fffdf5]">
                    <span class="flex items-center gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-yellow/20 text-[23px] text-brand-dark">
                            <i class="ph ph-storefront" aria-hidden="true"></i>
                        </span>
                        <span>
                            <span class="block text-[15px] font-bold tracking-[-0.02em]">Selling on Loot-E</span>
                            <span class="mt-0.5 block text-xs text-[#949497]">Informasi untuk menjual barang elektronik.</span>
                        </span>
                    </span>
                </button>
            </div>
        </section>

        <!-- Pertanyaan yang Sering Diajukan (FAQ) -->
        <section id="faq" class="mx-auto max-w-[1288px] px-5 pb-9 sm:px-8 lg:px-[76px]" aria-labelledby="faq-title">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h2 id="faq-title" class="text-2xl font-extrabold tracking-[-0.04em] text-[#38383a]">
                        Frequently Asked Questions
                    </h2>
                    <p class="mt-0.5 text-xs text-[#98989b]">
                        Temukan jawaban cepat untuk pertanyaan yang sering diajukan.
                    </p>
                </div>
                <div id="faq-filters" class="flex flex-wrap gap-1.5" role="group" aria-label="Filter pertanyaan">
                    <button type="button" data-filter="all" aria-pressed="true" class="faq-filter min-h-[30px] border border-brand-dark bg-brand-dark px-3.5 text-xs font-bold text-white transition hover:bg-gray-700">
                        All Questions
                    </button>
                    <button type="button" data-filter="account" aria-pressed="false" class="faq-filter min-h-[30px] border border-[#d3d3d5] bg-white px-3.5 text-xs font-bold transition hover:border-[#9c9c9f]">
                        Account
                    </button>
                    <button type="button" data-filter="products" aria-pressed="false" class="faq-filter min-h-[30px] border border-[#d3d3d5] bg-white px-3.5 text-xs font-bold transition hover:border-[#9c9c9f]">
                        Products
                    </button>
                    <button type="button" data-filter="orders" aria-pressed="false" class="faq-filter min-h-[30px] border border-[#d3d3d5] bg-white px-3.5 text-xs font-bold transition hover:border-[#9c9c9f]">
                        Orders
                    </button>
                    <button type="button" data-filter="payments" aria-pressed="false" class="faq-filter min-h-[30px] border border-[#d3d3d5] bg-white px-3.5 text-xs font-bold transition hover:border-[#9c9c9f]">
                        Payments
                    </button>
                    <button type="button" data-filter="barter" aria-pressed="false" class="faq-filter min-h-[30px] border border-[#d3d3d5] bg-white px-3.5 text-xs font-bold transition hover:border-[#9c9c9f]">
                        Barter
                    </button>
                    <button type="button" data-filter="selling" aria-pressed="false" class="faq-filter min-h-[30px] border border-[#d3d3d5] bg-white px-3.5 text-xs font-bold transition hover:border-[#9c9c9f]">
                        Selling
                    </button>
                </div>
            </div>

            <!-- FAQ List Items -->
            <div id="faq-list" class="mt-6 space-y-2.5">
                <!-- FAQ 1 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="account" data-search="create loot-e account register daftar akun buat">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="true" aria-controls="answer-1">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                How do I create a Loot-E account?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-1" class="faq-answer border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Pengguna dapat membuat akun baru melalui halaman <a href="register.php" class="text-brand-yellow font-semibold hover:underline">Register</a> dengan melengkapi nama, email, dan kata sandi. Setelah terdaftar, Anda dapat langsung melakukan transaksi beli maupun jual.
                    </div>
                </article>

                <!-- FAQ 2 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="account" data-search="update profile information informasi profil akun ubah ganti">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-2">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                Can I update my profile information?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-2" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Buka pengaturan akun untuk memperbarui nama, nomor kontak, alamat pengiriman, dan rincian profil lainnya sesuai kebutuhan transaksi Anda.
                    </div>
                </article>

                <!-- FAQ 3 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="products" data-search="find specific product cari produk barang search filter kategori">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-3">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                How do I find a specific product?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-3" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Gunakan kolom pencarian di bagian atas beranda atau telusuri lewat halaman <a href="categories.php" class="text-brand-yellow font-semibold hover:underline">Categories</a> untuk menyaring produk berdasarkan komponen (GPU, CPU, Laptop, Monitor, dll).
                    </div>
                </article>

                <!-- FAQ 4 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="products" data-search="product conditions condition kondisi barang sebelum membeli garansi minus">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-4">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                Can I see product conditions before purchasing?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-4" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Ya, setiap produk memiliki label kondisi (seperti Baru, Seperti Baru, Bekas Mulus) beserta foto asli, kelengkapan kotak, dan catatan dari penjual sebelum Anda melakukan pembayaran atau penawaran.
                    </div>
                </article>

                <!-- FAQ 5 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="orders" data-search="order status status pesanan pembelian cek order">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-5">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                Where can I check my order status?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-5" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Status pesanan dapat dipantau langsung dari menu riwayat pesanan akun Anda, mulai dari status menunggu konfirmasi penjual, diproses, hingga dikirim.
                    </div>
                </article>

                <!-- FAQ 6 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="orders" data-search="track shipment lacak paket pengiriman shipping kurir resi">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-6">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                How can I track my shipment?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-6" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Nomor resi otomatis dicantumkan pada rincian pesanan saat paket diserahkan ke kurir logistik. Anda dapat mengecek posisi paket secara real-time.
                    </div>
                </article>

                <!-- FAQ 7 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="payments" data-search="payment methods pembayaran metode transaksi transfer qris e-wallet">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-7">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                What payment methods are available?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-7" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Loot-E mendukung berbagai metode pembayaran aman termasuk Transfer Bank Virtual Account, QRIS, e-Wallet, serta rekening bersama (escrow) untuk menjamin keamanan dana pembeli.
                    </div>
                </article>

                <!-- FAQ 8 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="payments" data-search="payment information informasi pembayaran tagihan invoice">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-8">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                Where can I find my payment information?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-8" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Invoice resmi dan riwayat pembayaran tersimpan rapi pada detail transaksi pesanan yang bersangkutan di dashboard akun Anda.
                    </div>
                </article>

                <!-- FAQ 9 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="barter" data-search="negotiate product price negotiation harga tawar negosiasi diskon">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-9">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                Can I negotiate a product price?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-9" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Tentu! Pada produk yang menyalakan opsi negosiasi, Anda dapat menggunakan tombol "Nego Harga" untuk mengajukan nominal yang disepakati kepada penjual secara sopan.
                    </div>
                </article>

                <!-- FAQ 10 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="barter" data-search="barter feature panduan barter tukar tambah produk hardware">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-10">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                How does the barter feature work?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-10" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Fitur Barter memungkinkan pertukaran barang atau tukar tambah (dengan penyesuaian selisih nominal). Ajukan barang yang ingin Anda barterkan beserta deskripsinya, lalu penjual akan meninjau tawaran Anda.
                    </div>
                </article>

                <!-- FAQ 11 -->
                <article class="faq-item bg-white border border-[#e8e8ea] shadow-sm overflow-hidden" data-category="selling" data-search="sell products selling jual produk penjual pasang iklan">
                    <h3>
                        <button type="button" class="faq-trigger flex min-h-[48px] w-full items-center justify-between gap-4 px-5 text-left text-sm font-bold tracking-[-0.01em]" aria-expanded="false" aria-controls="answer-11">
                            <span class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-yellow shrink-0"></span>
                                How can I sell products on Loot-E?
                            </span>
                            <i class="faq-chevron ph ph-caret-down text-base text-[#8d8d90] transition-transform duration-200" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <div id="answer-11" class="faq-answer hidden border-t border-[#f0f0f2] px-5 pb-4 pt-3 text-[13px] leading-6 text-[#6d6d70]">
                        Masuk ke akun Anda, klik opsi "Jual Produk", unggah foto perangkat keras elektronik Anda, pilih kategori, isi deskripsi dan kondisi, lalu tetapkan harga jual atau izinkan barter.
                    </div>
                </article>
            </div>

            <!-- Empty State -->
            <div id="empty-state" class="hidden bg-white border border-[#e8e8ea] px-6 py-12 text-center mt-6 shadow-sm">
                <i class="ph ph-magnifying-glass mb-3 block text-4xl text-[#96969a] mx-auto" aria-hidden="true"></i>
                <h3 class="text-base font-bold text-gray-800">Tidak ada pertanyaan yang cocok</h3>
                <p class="mt-1 text-sm text-[#89898c]">
                    Coba gunakan kata kunci pencarian yang lain atau pilih kategori bantuan di atas.
                </p>
                <button type="button" onclick="setFilter('all'); document.getElementById('help-search').value=''; updateFaq();" class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-brand-yellow text-brand-dark text-xs font-bold rounded-lg hover:bg-yellow-500 transition shadow-sm">
                    Reset Pencarian
                </button>
            </div>
        </section>

        <!-- Kontak Dukungan -->
        <section id="contact-support" class="mx-auto max-w-[1288px] px-5 pb-16 sm:px-8 lg:px-[76px]" aria-labelledby="support-title">
            <div class="flex flex-col justify-between gap-6 bg-white border border-[#e8e8ea] px-7 py-8 sm:px-8 md:flex-row md:items-center md:py-9 shadow-sm">
                <div class="max-w-[570px]">
                    <p class="text-xs font-bold text-[#d49e00] flex items-center gap-1.5">
                        <i class="ph-fill ph-headphones text-sm" aria-hidden="true"></i>
                        LAYANAN PELANGGAN 24/7
                    </p>
                    <h2 id="support-title" class="mt-2 text-2xl font-extrabold tracking-[-0.04em] text-gray-900">
                        Still Need Help?
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-[#7d7d80]">
                        Belum menemukan jawaban yang kamu cari? Kirim pesan langsung kepada tim dukungan Loot-E untuk mendapatkan solusi cepat.
                    </p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row md:shrink-0">
                    <a href="#categories" class="inline-flex min-h-[44px] items-center justify-center bg-gray-800 px-5 text-xs font-bold text-white transition hover:bg-gray-700 shadow-sm">
                        Explore Help Topics
                    </a>
                    <button id="open-support-dialog" type="button" class="inline-flex min-h-[44px] items-center justify-center gap-2 bg-brand-yellow px-5 text-xs font-bold text-brand-dark transition hover:bg-yellow-500 active:translate-y-px shadow-sm">
                        <i class="ph ph-chat-text text-base" aria-hidden="true"></i> Contact Support
                    </button>
                </div>
            </div>
        </section>
    </main>

    <!-- Komponen Footer -->
    <?php require_once 'components/footer.php'; ?>

    <!-- Toast Notifikasi -->
    <div id="toast" class="pointer-events-none fixed bottom-5 left-1/2 z-50 hidden w-[min(90vw,420px)] -translate-x-1/2 bg-brand-dark px-4 py-3 text-center text-sm text-white rounded-xl shadow-2xl" role="status" aria-live="polite"></div>

    <!-- Modal Dialog Kontak Bantuan -->
    <dialog id="support-dialog" class="w-[min(92vw,540px)] border-0 bg-white p-0 text-[#363638] rounded-2xl shadow-2xl">
        <form id="support-form" class="p-6 sm:p-8" novalidate>
            <div class="flex items-start justify-between gap-5 border-b border-gray-100 pb-4">
                <div>
                    <p class="text-xs font-bold text-[#d49e00]">HUBUNGI BANTUAN</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] text-gray-900">
                        Ceritakan kendalamu
                    </h2>
                </div>
                <button id="close-support-dialog" type="button" class="grid h-9 w-9 place-items-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900" aria-label="Tutup formulir bantuan">
                    <i class="ph ph-x text-lg" aria-hidden="true"></i>
                </button>
            </div>
            <p class="mt-3 text-sm leading-5 text-[#858588]">
                Tim kami siap membantu menyelesaikan kendala transaksi, akun, pengiriman, maupun pembayaran Anda.
            </p>
            <div class="mt-6 space-y-4">
                <div>
                    <label for="support-subject" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-700">Topik Permasalahan</label>
                    <input id="support-subject" name="subject" required type="text" class="min-h-[44px] w-full rounded-xl border border-gray-300 px-3.5 text-sm focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none transition" placeholder="Contoh: Kendala pembayaran atau status pesanan" />
                </div>
                <div>
                    <label for="support-message" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-700">Pesan / Detail Kendala</label>
                    <textarea id="support-message" name="message" required rows="4" class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none transition" placeholder="Jelaskan kendala Anda secara rinci..."></textarea>
                </div>
            </div>
            <p id="support-feedback" class="mt-3 hidden text-sm font-medium" role="status" aria-live="polite"></p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end border-t border-gray-100 pt-4">
                <button type="button" id="cancel-support-dialog" class="min-h-[42px] rounded-xl border border-gray-300 px-5 text-sm font-bold text-gray-600 transition hover:bg-gray-100">
                    Batal
                </button>
                <button type="submit" class="min-h-[42px] rounded-xl bg-brand-yellow px-5 text-sm font-bold text-brand-dark transition hover:bg-yellow-500 active:translate-y-px shadow-sm">
                    Kirim Pesan Bantuan
                </button>
            </div>
        </form>
    </dialog>

    <!-- JavaScript Interaktif -->
    <script>
        const faqItems = [...document.querySelectorAll(".faq-item")];
        const searchInput = document.querySelector("#help-search");
        const emptyState = document.querySelector("#empty-state");
        const filterButtons = [...document.querySelectorAll(".faq-filter")];
        const categoryButtons = [...document.querySelectorAll(".category-card")];
        let activeFilter = "all";

        function updateFaq() {
            const query = (searchInput.value || "").trim().toLowerCase();
            let visibleCount = 0;

            faqItems.forEach((item) => {
                const matchesFilter =
                    activeFilter === "all" || item.dataset.category === activeFilter;
                const matchesSearch = !query ||
                    item.dataset.search.toLowerCase().includes(query) ||
                    item.textContent.toLowerCase().includes(query);
                const isVisible = matchesFilter && matchesSearch;
                item.classList.toggle("hidden", !isVisible);
                if (isVisible) visibleCount += 1;
            });

            if (emptyState) {
                emptyState.classList.toggle("hidden", visibleCount !== 0);
            }
        }

        function setFilter(filter) {
            activeFilter = filter;
            filterButtons.forEach((button) => {
                const isActive = button.dataset.filter === filter;
                button.setAttribute("aria-pressed", String(isActive));
                button.classList.toggle("bg-brand-dark", isActive);
                button.classList.toggle("text-white", isActive);
                button.classList.toggle("border-brand-dark", isActive);
                button.classList.toggle("bg-white", !isActive);
                button.classList.toggle("text-[#363638]", !isActive);
                button.classList.toggle("border-[#d3d3d5]", !isActive);
            });
            categoryButtons.forEach((button) =>
                button.setAttribute(
                    "aria-pressed",
                    String(button.dataset.category === filter),
                ),
            );
            updateFaq();
        }

        const searchForm = document.querySelector("#search-form");
        if (searchForm) {
            searchForm.addEventListener("submit", (event) => {
                event.preventDefault();
                updateFaq();
                document
                    .querySelector("#faq")
                    .scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });
            });
        }

        if (searchInput) {
            searchInput.addEventListener("input", updateFaq);
        }

        document.querySelectorAll("[data-search-term]").forEach((button) => {
            button.addEventListener("click", () => {
                if (searchInput) {
                    searchInput.value = button.dataset.searchTerm;
                }
                setFilter("all");
                document
                    .querySelector("#faq")
                    .scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });
            });
        });

        filterButtons.forEach((button) =>
            button.addEventListener("click", () =>
                setFilter(button.dataset.filter),
            ),
        );

        categoryButtons.forEach((button) => {
            button.addEventListener("click", () => {
                if (searchInput) {
                    searchInput.value = "";
                }
                setFilter(button.dataset.category);
                document
                    .querySelector("#faq")
                    .scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });
            });
        });

        document.querySelectorAll(".faq-trigger").forEach((trigger) => {
            trigger.addEventListener("click", () => {
                const answer = document.querySelector(
                    `#${trigger.getAttribute("aria-controls")}`,
                );
                const isOpen = trigger.getAttribute("aria-expanded") === "true";
                trigger.setAttribute("aria-expanded", String(!isOpen));
                if (answer) {
                    answer.classList.toggle("hidden", isOpen);
                }
            });
        });

        let toastTimer;
        const toast = document.querySelector("#toast");

        function showToast(message) {
            if (!toast) return;
            toast.textContent = message;
            toast.classList.remove("hidden");
            window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(
                () => toast.classList.add("hidden"),
                3500,
            );
        }

        document.querySelectorAll("[data-toast]").forEach((button) => {
            button.addEventListener("click", () => {
                showToast(button.dataset.toast);
            });
        });

        const supportDialog = document.querySelector("#support-dialog");
        const supportFeedback = document.querySelector("#support-feedback");
        const supportForm = document.querySelector("#support-form");

        const openSupportBtn = document.querySelector("#open-support-dialog");
        if (openSupportBtn && supportDialog) {
            openSupportBtn.addEventListener("click", () => {
                if (supportFeedback) supportFeedback.classList.add("hidden");
                if (supportForm) supportForm.reset();
                supportDialog.showModal();
                const subj = document.querySelector("#support-subject");
                if (subj) subj.focus();
            });
        }

        ["#close-support-dialog", "#cancel-support-dialog"].forEach((selector) => {
            const el = document.querySelector(selector);
            if (el && supportDialog) {
                el.addEventListener("click", () => supportDialog.close());
            }
        });

        if (supportForm && supportDialog) {
            supportForm.addEventListener("submit", (event) => {
                event.preventDefault();
                if (!supportForm.checkValidity()) {
                    if (supportFeedback) {
                        supportFeedback.textContent =
                            "Mohon lengkapi topik dan pesan sebelum mengirim.";
                        supportFeedback.className = "mt-3 text-sm text-red-600 font-medium";
                        supportFeedback.classList.remove("hidden");
                    }
                    supportForm.reportValidity();
                    return;
                }

                if (supportFeedback) {
                    supportFeedback.textContent =
                        "Terima kasih! Pesan bantuan Anda berhasil dikirim ke tim dukungan kami.";
                    supportFeedback.className = "mt-3 text-sm text-green-600 font-medium";
                    supportFeedback.classList.remove("hidden");
                }

                setTimeout(() => {
                    supportDialog.close();
                    showToast("Pesan bantuan Anda telah berhasil terkirim.");
                }, 1200);
            });
        }
    </script>
</body>

</html>
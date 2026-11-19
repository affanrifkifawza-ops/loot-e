<?php
require_once "auth.php";

$currentUser = getCurrentUser();

$navActivePage = 'categories';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ASUS GTX 750 Ti 2GB | Loot-E</title>

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

        /* Custom Styling Range Slider Harga Pasar */
        #price-range-slider {
            -webkit-appearance: none;
            appearance: none;
            background: transparent;
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
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        #price-range-slider::-webkit-slider-thumb:hover,
        #price-range-slider::-webkit-slider-thumb:active {
            transform: scale(1.25);
        }

        #price-range-slider::-moz-range-thumb {
            width: 16px;
            height: 16px;
            border-radius: 9999px;
            background-color: #FFC914;
            border: 2px solid #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        #price-range-slider::-moz-range-thumb:hover,
        #price-range-slider::-moz-range-thumb:active {
            transform: scale(1.25);
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

    <!-- Sub-Navbar: Breadcrumb & Search Bar -->
    <header class="bg-white border-b border-gray-200/80 px-4 sm:px-6 md:px-12 py-2.5">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4">
            <nav aria-label="Breadcrumb" class="w-full sm:w-auto shrink-0">
                <ol class="flex items-center gap-1.5 text-xs text-gray-500">
                    <li>
                        <a href="categories.php" class="hover:text-gray-900 transition font-medium">Kategori</a>
                    </li>
                    <li aria-hidden="true" class="text-gray-400">/</li>
                    <li class="font-semibold text-gray-900" aria-current="page">Detail Produk</li>
                </ol>
            </nav>

            <form action="search.php" method="GET" onsubmit="if(!this.q.value.trim()){ this.q.focus(); return false; }" class="relative max-w-sm sm:max-w-md w-full mx-auto">
                <label for="search-input" class="sr-only">Cari barang atau kategori</label>
                <input
                    id="search-input"
                    type="text"
                    name="q"
                    required
                    placeholder="Cari barang atau kategori..."
                    class="w-full bg-white border border-gray-300 rounded-full pl-4 pr-9 py-1.5 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow transition" />
                <button
                    type="submit"
                    class="absolute right-1 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-brand-yellow text-white flex items-center justify-center hover:brightness-95 transition shadow-xs"
                    aria-label="Cari produk">
                    <i class="ph ph-arrow-right text-[11px] font-bold text-white"></i>
                </button>
            </form>

            <div class="hidden sm:block shrink-0 w-[140px]"></div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- Baris Utama Produk: Galeri (Kiri) + Info & Harga (Tengah) + Info Penjual (Kanan) -->
        <section class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start" aria-label="Informasi Utama Produk">

            <!-- Kolom 1: Galeri Foto Produk -->
            <div class="md:col-span-12 lg:col-span-5 bg-white border border-gray-200/80 p-4 shadow-2xs">
                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Thumbnails list -->
                    <div class="flex sm:flex-col gap-2 order-2 sm:order-1 overflow-x-auto sm:overflow-visible shrink-0" role="tablist" aria-label="Daftar Foto Produk">
                        <button
                            type="button"
                            class="thumb-btn w-16 h-16 rounded-lg border-2 border-gray-900 p-1 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-gray-900 focus:outline-none transition"
                            data-index="1"
                            data-label="1/3"
                            aria-label="Tampilkan foto produk 1"
                            aria-selected="true"
                            role="tab">
                            <div class="w-full h-full rounded bg-gray-200 flex flex-col items-center justify-center text-[10px] font-semibold text-gray-600">
                                <i class="ph ph-graphics-card text-xl text-gray-700" aria-hidden="true"></i>
                                <span>Unit</span>
                            </div>
                        </button>

                        <button
                            type="button"
                            class="thumb-btn w-16 h-16 rounded-lg border border-gray-300 p-1 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-gray-900 focus:outline-none transition"
                            data-index="2"
                            data-label="2/3"
                            aria-label="Tampilkan foto produk 2"
                            aria-selected="false"
                            role="tab">
                            <div class="w-full h-full rounded bg-gray-200 flex flex-col items-center justify-center text-[10px] font-semibold text-gray-600">
                                <i class="ph ph-package text-xl text-gray-700" aria-hidden="true"></i>
                                <span>Box</span>
                            </div>
                        </button>

                        <button
                            type="button"
                            class="thumb-btn w-16 h-16 rounded-lg border border-gray-300 p-1 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-gray-900 focus:outline-none transition"
                            data-index="3"
                            data-label="3/3"
                            aria-label="Tampilkan foto produk 3"
                            aria-selected="false"
                            role="tab">
                            <div class="w-full h-full rounded bg-gray-200 flex flex-col items-center justify-center text-[10px] font-semibold text-gray-600">
                                <i class="ph ph-plugs text-xl text-gray-700" aria-hidden="true"></i>
                                <span>Port</span>
                            </div>
                        </button>
                    </div>

                    <!-- Main Image Frame -->
                    <div class="relative flex-1 aspect-square bg-[#FAFAFA] border border-gray-200/80 flex items-center justify-center p-6 order-1 sm:order-2 overflow-hidden">
                        <!-- Badge Bekas -->
                        <span class="absolute top-3 left-3 bg-[#FEF3C7] text-[#92400E] text-xs font-semibold px-2.5 py-1 rounded-md tracking-wide">
                            Bekas
                        </span>

                        <!-- Main visual placeholder/render -->
                        <div id="main-preview-container" class="w-full h-full flex flex-col items-center justify-center text-center p-4">
                            <i class="ph ph-graphics-card text-7xl text-gray-700 mb-2" aria-hidden="true"></i>
                            <p id="main-preview-title" class="text-sm font-bold text-gray-800">ASUS GeForce GTX 750 Ti 2GB GDDR5</p>
                            <p class="text-xs text-gray-500 mt-1">Dual Fan Edition • PCIe 3.0</p>
                        </div>

                        <!-- Mini indicator bottom left & counter bottom right -->
                        <div class="absolute bottom-3 left-3 w-7 h-7 rounded border border-gray-300 bg-white/90 flex items-center justify-center text-gray-600 shadow-2xs" title="Rotasi 360 derajat">
                            <i class="ph ph-arrows-clockwise text-xs" aria-hidden="true"></i>
                        </div>

                        <span id="gallery-counter" class="absolute bottom-3 right-3 bg-white/95 border border-gray-300 text-xs text-gray-700 px-2.5 py-0.5 rounded-full font-medium shadow-2xs">
                            1/3
                        </span>
                    </div>
                </div>
            </div>

            <!-- Kolom 2: Detail Produk, Harga, Market Insight, dan Aksi -->
            <div class="md:col-span-12 lg:col-span-4 bg-white border border-gray-200/80 p-5 sm:p-6 space-y-4 shadow-2xs">
                <!-- Judul Produk -->
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight">
                        ASUS GTX 750 Ti 2GB
                    </h1>

                    <!-- Rating & Meta -->
                    <div class="flex flex-wrap items-center gap-2 mt-2 text-xs text-gray-500">
                        <div class="flex items-center gap-1 text-gray-900 font-semibold">
                            <i class="ph-fill ph-star text-amber-500 text-sm" aria-hidden="true"></i>
                            <span>4.8</span>
                            <span class="text-gray-500 font-normal">(28 ulasan)</span>
                        </div>
                        <span aria-hidden="true" class="text-gray-300">•</span>
                        <span>Terjual 45+</span>
                        <span aria-hidden="true" class="text-gray-300">•</span>
                        <span>Jakarta</span>
                        <span class="bg-emerald-50 text-emerald-700 text-[11px] font-medium px-2 py-0.5 rounded-full">
                            Kondisi Normal
                        </span>
                    </div>
                </div>

                <!-- Bagian Harga -->
                <div class="flex flex-wrap items-baseline gap-2.5 pt-1 border-t border-gray-100">
                    <span class="text-2xl sm:text-3xl font-extrabold text-gray-900">
                        Rp 680.000
                    </span>
                    <span class="text-sm text-gray-400 line-through">
                        Rp 850.000
                    </span>
                    <span class="bg-brand-yellow text-white text-xs font-bold px-2 py-0.5 rounded">
                        20% OFF
                    </span>
                </div>

                <!-- Box Analisis Harga Pasar -->
                <div class="bg-[#FFFDF5] rounded-lg p-3.5 space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 font-semibold text-amber-900">
                            <i class="ph-fill ph-tag text-amber-600" aria-hidden="true"></i>
                            <span>Harga Pasar</span>
                        </div>
                        <span class="text-gray-600 text-xs">Harga listing Rp 680.000</span>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm sm:text-base font-bold text-gray-900">
                            Rp 600.000 - Rp 750.000
                        </span>
                        <span id="market-status-badge" class="bg-emerald-100 text-emerald-800 text-[11px] font-semibold px-2 py-0.5 rounded-full transition">
                            Dalam kisaran harga pasar
                        </span>
                    </div>

                    <!-- Slider / Gauge Bar Representatif (Bisa Digeser) -->
                    <div class="pt-1">
                        <div class="relative w-full flex items-center py-1">
                            <!-- Track Bar Gradient -->
                            <div class="absolute inset-x-0 h-2 rounded-full bg-gradient-to-r from-emerald-200 via-amber-200 to-rose-200 border border-gray-200 pointer-events-none"></div>

                            <!-- Native Accessible Range Input -->
                            <input
                                type="range"
                                id="price-range-slider"
                                min="600000"
                                max="850000"
                                step="5000"
                                value="680000"
                                aria-label="Slider simulasi harga pasar"
                                class="relative w-full h-2 z-10 cursor-pointer focus:outline-none" />
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
                    <span class="bg-[#FEF3C7] text-[#92400E] px-3 py-1 rounded-full font-medium">
                        Bisa Nego
                    </span>
                    <span class="bg-[#FEF3C7] text-[#92400E] px-3 py-1 rounded-full font-medium">
                        Bisa Barter
                    </span>
                    <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full font-medium">
                        Fast Response
                    </span>
                    <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full font-medium">
                        Trusted Seller
                    </span>
                </div>

                <!-- Grid Tombol Aksi 2x2 -->
                <div class="grid grid-cols-2 gap-2.5 pt-2">
                    <button
                        type="button"
                        id="btn-nego"
                        class="min-h-[44px] px-3 py-2.5 bg-brand-yellow hover:bg-yellow-400 text-brand-dark font-bold text-xs uppercase tracking-wide transition shadow-2xs cursor-pointer">
                        Ajukan Harga
                    </button>

                    <button
                        type="button"
                        id="btn-barter"
                        class="min-h-[44px] px-3 py-2.5 bg-white hover:bg-amber-50 text-amber-800 font-bold text-xs uppercase tracking-wide transition border border-amber-400 cursor-pointer">
                        Ajukan Barter
                    </button>

                    <button
                        type="button"
                        id="btn-cart"
                        class="min-h-[44px] px-3 py-2.5 bg-white hover:bg-gray-100 text-gray-800 font-bold text-xs uppercase tracking-wide transition border border-gray-300 flex items-center justify-center gap-1.5 cursor-pointer">
                        <i class="ph ph-shopping-cart text-sm font-bold" aria-hidden="true"></i>
                        <span>Tambah ke Keranjang</span>
                    </button>

                    <button
                        type="button"
                        id="btn-buy"
                        class="min-h-[44px] px-3 py-2.5 bg-[#272727] hover:bg-black text-white font-bold text-xs uppercase tracking-wide transition cursor-pointer">
                        Beli Sekarang
                    </button>
                </div>
            </div>

            <!-- Kolom 3: Informasi Penjual -->
            <aside class="md:col-span-12 lg:col-span-3 bg-white border border-gray-200/80 p-5 space-y-4 shadow-2xs" aria-label="Informasi Penjual">
                <h2 class="font-bold text-sm text-gray-900 border-b border-gray-100 pb-2.5 uppercase tracking-wide">
                    Informasi Penjual
                </h2>

                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-brand-dark text-brand-yellow flex items-center justify-center font-bold text-base shadow-xs shrink-0">
                        TB
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-gray-900 leading-tight">Tech Bandung</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Kota Bandung</p>
                        <span class="inline-block mt-1 text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full font-medium">
                            Online 15 Menit Lalu
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs bg-gray-50 p-3">
                    <div>
                        <span class="text-gray-500 block text-[11px]">Rating Toko</span>
                        <span class="font-bold text-gray-900 flex items-center gap-1 mt-0.5">
                            <i class="ph-fill ph-star text-amber-500" aria-hidden="true"></i> 4.9 / 5.0
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-[11px]">Kecepatan</span>
                        <span class="font-bold text-gray-900 block mt-0.5">± 2 Jam</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-[11px]">Produk Terjual</span>
                        <span class="font-bold text-gray-900 block mt-0.5">250+ Unit</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-[11px]">Transaksi Sukses</span>
                        <span class="font-bold text-emerald-700 block mt-0.5">99.2%</span>
                    </div>
                </div>

                <div class="space-y-2 pt-1">
                    <a
                        href="#"
                        class="w-full min-h-[44px] bg-brand-yellow hover:bg-yellow-400 text-brand-dark font-bold text-xs uppercase tracking-wide py-2.5 transition flex items-center justify-center gap-2 cursor-pointer shadow-2xs">
                        <i class="ph ph-chat-circle-dots text-base font-bold" aria-hidden="true"></i>
                        <span>Chat Penjual</span>
                    </a>
                    <a
                        href="#"
                        class="w-full min-h-[44px] bg-white hover:bg-gray-100 text-gray-800 font-bold text-xs uppercase tracking-wide border border-gray-300 py-2.5 transition flex items-center justify-center gap-2">
                        <i class="ph ph-storefront text-base" aria-hidden="true"></i>
                        <span>Kunjungi Toko</span>
                    </a>
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
                        Ulasan (28)
                    </button>
                </nav>
            </div>

            <!-- Konten Tab + Ulasan Rating (Grid 2 Kolom Sesuai detail_produk.png) -->
            <div class="p-5 sm:p-6 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                <!-- Area Kiri: Deskripsi & Spesifikasi Produk -->
                <div class="lg:col-span-7 space-y-6">
                    <!-- Panel Deskripsi -->
                    <div id="panel-deskripsi" class="tab-panel space-y-4" role="tabpanel">
                        <div>
                            <h2 class="text-base font-bold text-gray-900 mb-2">Deskripsi Produk</h2>
                            <p class="text-xs sm:text-sm text-gray-600 leading-relaxed">
                                ASUS GTX 750 Ti 2GB GDDR5, kondisi bekas pemakaian normal. Masih berfungsi dengan baik, tidak pernah mining. Cocok untuk gaming ringan dan kerja harian. Sudah dibersihkan, siap langsung pakai.
                            </p>
                        </div>

                        <div class="border-t border-gray-100 pt-4">
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-y-2.5 gap-x-6 text-xs sm:text-sm">
                                <div class="flex items-center justify-between border-b border-gray-100 pb-1.5">
                                    <dt class="text-gray-500 font-medium">Kondisi:</dt>
                                    <dd class="text-gray-900 font-semibold">Bekas (Normal)</dd>
                                </div>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-1.5">
                                    <dt class="text-gray-500 font-medium">Garansi:</dt>
                                    <dd class="text-gray-900 font-semibold">Tidak Ada Garansi</dd>
                                </div>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-1.5">
                                    <dt class="text-gray-500 font-medium">Min. PSU:</dt>
                                    <dd class="text-gray-900 font-semibold">400 W</dd>
                                </div>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-1.5">
                                    <dt class="text-gray-500 font-medium">Port:</dt>
                                    <dd class="text-gray-900 font-semibold">1x HDMI, 1x DVI, 1x DP</dd>
                                </div>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-1.5 sm:col-span-2">
                                    <dt class="text-gray-500 font-medium">Kelengkapan:</dt>
                                    <dd class="text-gray-900 font-semibold">Unit Saja (Tanpa Box)</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Panel Spesifikasi Lengkap -->
                    <div id="panel-spesifikasi" class="tab-panel hidden space-y-4" role="tabpanel">
                        <h2 class="text-base font-bold text-gray-900 mb-2">Spesifikasi Lengkap</h2>
                        <table class="w-full text-xs sm:text-sm border border-gray-200 overflow-hidden">
                            <tbody>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="p-2.5 text-left font-medium text-gray-600 w-1/3">Graphics Engine</th>
                                    <td class="p-2.5 text-gray-900 font-semibold">NVIDIA GeForce GTX 750 Ti</td>
                                </tr>
                                <tr class="border-b border-gray-200">
                                    <th class="p-2.5 text-left font-medium text-gray-600">Bus Standard</th>
                                    <td class="p-2.5 text-gray-900">PCI Express 3.0</td>
                                </tr>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="p-2.5 text-left font-medium text-gray-600">Video Memory</th>
                                    <td class="p-2.5 text-gray-900 font-semibold">2GB GDDR5 128-bit</td>
                                </tr>
                                <tr class="border-b border-gray-200">
                                    <th class="p-2.5 text-left font-medium text-gray-600">CUDA Cores</th>
                                    <td class="p-2.5 text-gray-900">640</td>
                                </tr>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="p-2.5 text-left font-medium text-gray-600">Power Consumption</th>
                                    <td class="p-2.5 text-gray-900">Maksimal 60W (Tanpa 6-pin tambahan)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Panel Riwayat & Kondisi -->
                    <div id="panel-riwayat" class="tab-panel hidden space-y-4" role="tabpanel">
                        <h2 class="text-base font-bold text-gray-900 mb-2">Riwayat Pemakaian &amp; Kondisi Fisik</h2>
                        <ul class="space-y-2 text-xs sm:text-sm text-gray-700">
                            <li class="flex items-start gap-2">
                                <i class="ph ph-check text-emerald-600 text-base shrink-0 mt-0.5" aria-hidden="true"></i>
                                <span>Thermal paste baru diganti menggunakan Arctic MX-4 bulan lalu.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="ph ph-check text-emerald-600 text-base shrink-0 mt-0.5" aria-hidden="true"></i>
                                <span>Kedua kipas berputar lancar tanpa bunyi bising atau kendala bearing.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="ph ph-check text-emerald-600 text-base shrink-0 mt-0.5" aria-hidden="true"></i>
                                <span>Suhu idle berada di kisaran 34 derajat dan full load Furmark stabil di 68 derajat.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Panel Ulasan Full -->
                    <div id="panel-ulasan" class="tab-panel hidden space-y-4" role="tabpanel">
                        <h2 class="text-base font-bold text-gray-900 mb-2">Seluruh Ulasan Pembeli (28)</h2>
                        <p class="text-xs text-gray-600">
                            Semua ulasan berasal dari pembeli terverifikasi yang telah menyelesaikan transaksi produk ini.
                        </p>
                    </div>
                </div>

                <!-- Area Kanan: Ringkasan Rating & Kartu Ulasan Singkat -->
                <div class="lg:col-span-5 bg-white border border-gray-200/80 p-5 space-y-5 shadow-2xs">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2.5">
                        <h2 class="font-bold text-sm text-gray-900">Ulasan (28)</h2>
                        <a href="#panel-ulasan" class="text-xs text-amber-700 hover:text-amber-900 font-semibold transition">
                            Lihat semua &gt;
                        </a>
                    </div>

                    <!-- 5 Bintang Emas -->
                    <div class="flex items-center gap-1 text-amber-500 text-base" aria-label="Rating 5 dari 5 bintang">
                        <i class="ph-fill ph-star" aria-hidden="true"></i>
                        <i class="ph-fill ph-star" aria-hidden="true"></i>
                        <i class="ph-fill ph-star" aria-hidden="true"></i>
                        <i class="ph-fill ph-star" aria-hidden="true"></i>
                        <i class="ph-fill ph-star" aria-hidden="true"></i>
                    </div>

                    <!-- Distribusi Rating 5 Bintang s/d 1 Bintang -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-7 font-semibold text-gray-700 flex items-center gap-0.5 shrink-0">
                                5 <i class="ph-fill ph-star text-amber-500 text-[11px]" aria-hidden="true"></i>
                            </span>
                            <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-amber-400 rounded-full" style="width: 86%;"></div>
                            </div>
                            <span class="w-8 text-right text-[11px] font-medium text-gray-500 shrink-0">86%</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="w-7 font-semibold text-gray-700 flex items-center gap-0.5 shrink-0">
                                2 <i class="ph-fill ph-star text-amber-500 text-[11px]" aria-hidden="true"></i>
                            </span>
                            <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-amber-400 rounded-full" style="width: 0%;"></div>
                            </div>
                            <span class="w-8 text-right text-[11px] font-medium text-gray-400 shrink-0">0%</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="w-7 font-semibold text-gray-700 flex items-center gap-0.5 shrink-0">
                                4 <i class="ph-fill ph-star text-amber-500 text-[11px]" aria-hidden="true"></i>
                            </span>
                            <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-amber-400 rounded-full" style="width: 11%;"></div>
                            </div>
                            <span class="w-8 text-right text-[11px] font-medium text-gray-500 shrink-0">11%</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="w-7 font-semibold text-gray-700 flex items-center gap-0.5 shrink-0">
                                1 <i class="ph-fill ph-star text-amber-500 text-[11px]" aria-hidden="true"></i>
                            </span>
                            <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-amber-400 rounded-full" style="width: 0%;"></div>
                            </div>
                            <span class="w-8 text-right text-[11px] font-medium text-gray-400 shrink-0">0%</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="w-7 font-semibold text-gray-700 flex items-center gap-0.5 shrink-0">
                                3 <i class="ph-fill ph-star text-amber-500 text-[11px]" aria-hidden="true"></i>
                            </span>
                            <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-amber-400 rounded-full" style="width: 3%;"></div>
                            </div>
                            <span class="w-8 text-right text-[11px] font-medium text-gray-500 shrink-0">3%</span>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-emerald-800 bg-emerald-50/80 px-2.5 py-1 rounded-md font-medium">
                            <span class="flex items-center gap-1">
                                <i class="ph ph-check-circle text-emerald-600 text-xs" aria-hidden="true"></i>
                                <span>97% Pembeli Puas</span>
                            </span>
                            <span class="text-emerald-700 font-bold">4.8/5.0</span>
                        </div>
                    </div>

                    <!-- Ulasan Pembeli Item 1 -->
                    <article class="border-t border-gray-100 pt-3.5 space-y-2">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-700 flex items-center justify-center font-bold text-xs shrink-0">
                                B
                            </div>
                            <div>
                                <span class="font-bold text-xs text-gray-900 block leading-tight">BudiSantoso</span>
                                <time datetime="2026-11-05" class="text-[10px] text-gray-400 block">2 minggu lalu</time>
                            </div>
                        </div>

                        <div class="flex items-center gap-0.5 text-amber-500 text-xs" aria-label="Bintang 5">
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                        </div>

                        <p class="text-xs text-gray-700 leading-normal">
                            Barang sesuai deskripsi, masih bagus. Pengiriman cepat
                        </p>

                        <div class="flex justify-end pt-1">
                            <button
                                type="button"
                                class="helpful-btn text-[11px] bg-[#FEF3C7] text-[#92400E] border border-[#FDE68A] hover:bg-amber-200 px-3 py-1 rounded-full font-medium transition cursor-pointer"
                                data-count="12">
                                Membantu (<span>12</span>)
                            </button>
                        </div>
                    </article>

                    <!-- Ulasan Pembeli Item 2 -->
                    <article class="border-t border-gray-100 pt-3.5 space-y-2">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-700 flex items-center justify-center font-bold text-xs shrink-0">
                                B
                            </div>
                            <div>
                                <span class="font-bold text-xs text-gray-900 block leading-tight">BudiSantoso</span>
                                <time datetime="2026-11-05" class="text-[10px] text-gray-400 block">2 minggu lalu</time>
                            </div>
                        </div>

                        <div class="flex items-center gap-0.5 text-amber-500 text-xs" aria-label="Bintang 5">
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                            <i class="ph-fill ph-star" aria-hidden="true"></i>
                        </div>

                        <p class="text-xs text-gray-700 leading-normal">
                            Barang sesuai deskripsi, masih bagus. Pengiriman cepat
                        </p>

                        <div class="flex justify-end pt-1">
                            <button
                                type="button"
                                class="helpful-btn text-[11px] bg-[#FEF3C7] text-[#92400E] border border-[#FDE68A] hover:bg-amber-200 px-3 py-1 rounded-full font-medium transition cursor-pointer"
                                data-count="12">
                                Membantu (<span>12</span>)
                            </button>
                        </div>
                    </article>
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
                <!-- Kartu Produk Terkait 1 -->
                <a href="detail_produk.php" class="bg-white border border-gray-200/80 p-4 space-y-3 hover:shadow-md hover:border-gray-300 transition flex flex-col justify-between shadow-2xs">
                    <div class="space-y-3">
                        <div class="relative w-full aspect-video bg-gray-100 border border-gray-200 flex items-center justify-center overflow-hidden">
                            <span class="absolute top-2 left-2 bg-[#FEF3C7] text-[#92400E] text-[10px] font-semibold px-2 py-0.5 rounded">
                                Bekas
                            </span>
                            <div class="text-center text-gray-400">
                                <i class="ph ph-graphics-card text-4xl mb-1 text-gray-500" aria-hidden="true"></i>
                                <span class="block text-[11px]">Preview Unit</span>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-gray-900 leading-snug">
                                <p>
                                    Gigabyte GTX 1050 Ti 4GB OC
                                </p>
                            </h3>
                            <p class="text-base font-extrabold text-gray-900 mt-1">
                                Rp 1.150.000
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-gray-500 border-t border-gray-100 pt-2.5">
                        <span>Jakarta Barat</span>
                        <span>Terjual 12</span>
                    </div>
                </a>
                <!-- Kartu Produk Terkait 2 -->
                <a href="detail_produk.php" class="bg-white border border-gray-200/80 p-4 space-y-3 hover:shadow-md hover:border-gray-300 transition flex flex-col justify-between shadow-2xs">
                    <div class="space-y-3">
                        <div class="relative w-full aspect-video bg-gray-100 border border-gray-200 flex items-center justify-center overflow-hidden">
                            <span class="absolute top-2 left-2 bg-[#FEF3C7] text-[#92400E] text-[10px] font-semibold px-2 py-0.5 rounded">
                                Bekas
                            </span>
                            <div class="text-center text-gray-400">
                                <i class="ph ph-graphics-card text-4xl mb-1 text-gray-500" aria-hidden="true"></i>
                                <span class="block text-[11px]">Preview Unit</span>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-gray-900 leading-snug">
                                <p>
                                    Sapphire RX 570 4GB Nitro+
                                </p>
                            </h3>
                            <p class="text-base font-extrabold text-gray-900 mt-1">
                                Rp 950.000
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-gray-500 border-t border-gray-100 pt-2.5">
                        <span>Bandung</span>
                        <span>Terjual 8</span>
                    </div>
                </a>

                <!-- Kartu Produk Terkait 3 -->
                <a href="detail_produk.php" class="bg-white border border-gray-200/80 p-4 space-y-3 hover:shadow-md hover:border-gray-300 transition flex flex-col justify-between shadow-2xs">
                    <div class="space-y-3">
                        <div class="relative w-full aspect-video bg-gray-100 border border-gray-200 flex items-center justify-center overflow-hidden">
                            <span class="absolute top-2 left-2 bg-[#FEF3C7] text-[#92400E] text-[10px] font-semibold px-2 py-0.5 rounded">
                                Bekas
                            </span>
                            <div class="text-center text-gray-400">
                                <i class="ph ph-lightning text-4xl mb-1 text-gray-500" aria-hidden="true"></i>
                                <span class="block text-[11px]">Preview Unit</span>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-gray-900 leading-snug">
                                <p>
                                    Power Supply Corsair CV450 450W 80+
                                </p>
                            </h3>
                            <p class="text-base font-extrabold text-gray-900 mt-1">
                                Rp 420.000
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-gray-500 border-t border-gray-100 pt-2.5">
                        <span>Surabaya</span>
                        <span>Terjual 30+</span>
                    </div>
                </a>
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
                Masukkan harga penawaranmu untuk unit <strong>ASUS GTX 750 Ti 2GB</strong>. Harga asli: Rp 680.000.
            </p>
            <div>
                <label for="input-tawaran" class="block text-xs font-semibold text-gray-700 mb-1">Nominal Tawaran (Rp)</label>
                <input
                    type="number"
                    id="input-tawaran"
                    placeholder="Contoh: 620000"
                    min="500000"
                    max="680000"
                    class="w-full border border-gray-300 rounded-md p-2.5 text-sm text-gray-900 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow" />
            </div>
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
                Tuliskan deskripsi komponen atau barang elektronik yang ingin kamu tukar tambah dengan produk ini.
            </p>
            <div>
                <label for="input-barter-item" class="block text-xs font-semibold text-gray-700 mb-1">Barang yang Ditawarkan</label>
                <textarea
                    id="input-barter-item"
                    rows="3"
                    placeholder="Contoh: RAM Corsair Vengeance 2x8GB DDR4 + Tambah dana Rp 200rb"
                    class="w-full border border-gray-300 rounded-md p-2.5 text-sm text-gray-900 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow"></textarea>
            </div>
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
            // Logika Tab Navigasi
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabPanels = document.querySelectorAll('.tab-panel');

            tabLinks.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');

                    tabLinks.forEach(function(t) {
                        t.classList.remove('border-brand-yellow', 'text-gray-900');
                        t.classList.add('border-transparent', 'text-gray-500');
                        t.setAttribute('aria-selected', 'false');
                    });

                    this.classList.remove('border-transparent', 'text-gray-500');
                    this.classList.add('border-brand-yellow', 'text-gray-900');
                    this.setAttribute('aria-selected', 'true');

                    tabPanels.forEach(function(panel) {
                        panel.classList.add('hidden');
                    });

                    const targetPanel = document.getElementById(targetId);
                    if (targetPanel) {
                        targetPanel.classList.remove('hidden');
                    }
                });
            });

            // Logika Galeri Thumbnail
            const thumbButtons = document.querySelectorAll('.thumb-btn');
            const galleryCounter = document.getElementById('gallery-counter');
            const mainTitle = document.getElementById('main-preview-title');

            const galleryData = {
                '1': {
                    title: 'ASUS GeForce GTX 750 Ti 2GB GDDR5',
                    label: '1/3'
                },
                '2': {
                    title: 'Box & Kartu Garansi Resmi ASUS',
                    label: '2/3'
                },
                '3': {
                    title: 'Tampak Belakang & Port I/O HDMI/DVI/DP',
                    label: '3/3'
                }
            };

            thumbButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = this.getAttribute('data-index');

                    thumbButtons.forEach(function(b) {
                        b.classList.remove('border-2', 'border-gray-900');
                        b.classList.add('border', 'border-gray-300');
                        b.setAttribute('aria-selected', 'false');
                    });

                    this.classList.remove('border', 'border-gray-300');
                    this.classList.add('border-2', 'border-gray-900');
                    this.setAttribute('aria-selected', 'true');

                    if (galleryData[idx]) {
                        if (galleryCounter) galleryCounter.textContent = galleryData[idx].label;
                        if (mainTitle) mainTitle.textContent = galleryData[idx].title;
                    }
                });
            });

            // Logika Slider Interaktif Harga Pasar
            const priceSlider = document.getElementById('price-range-slider');
            const sliderCurrentLabel = document.getElementById('slider-current-label');
            const marketStatusBadge = document.getElementById('market-status-badge');
            const inputTawaran = document.getElementById('input-tawaran');

            function updatePriceSlider(val) {
                const numericVal = parseInt(val, 10);

                // Format angka ringkas untuk teks indikator (contoh: Rp 680K)
                const inK = Math.round(numericVal / 1000);
                if (sliderCurrentLabel) {
                    sliderCurrentLabel.textContent = 'Rp ' + inK + 'K';
                }

                // Perbarui status pasar: 600K s/d 750K wajar pasar, > 750K di atas pasar
                if (marketStatusBadge) {
                    if (numericVal <= 750000) {
                        marketStatusBadge.className = 'bg-emerald-100 text-emerald-800 border border-emerald-300 text-[11px] font-semibold px-2 py-0.5 rounded-full transition';
                        marketStatusBadge.textContent = 'Dalam kisaran harga pasar';
                    } else {
                        marketStatusBadge.className = 'bg-rose-100 text-rose-800 border border-rose-300 text-[11px] font-semibold px-2 py-0.5 rounded-full transition';
                        marketStatusBadge.textContent = 'Di atas harga pasar';
                    }
                }

                // Sinkronkan nilai ke input dialog tawar harga jika belum diubah manual
                if (inputTawaran && !inputTawaran.dataset.userEdited) {
                    inputTawaran.value = numericVal;
                }
            }

            if (priceSlider) {
                priceSlider.addEventListener('input', function() {
                    updatePriceSlider(this.value);
                });

                if (inputTawaran) {
                    inputTawaran.addEventListener('input', function() {
                        this.dataset.userEdited = 'true';
                    });
                }
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

            closeDialogButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (modalNego && modalNego.open) modalNego.close();
                    if (modalBarter && modalBarter.open) modalBarter.close();
                });
            });

            const submitTawaran = document.getElementById('submit-tawaran');
            if (submitTawaran) {
                submitTawaran.addEventListener('click', function() {
                    const inputTawaran = document.getElementById('input-tawaran');
                    if (inputTawaran && inputTawaran.value) {
                        modalNego.close();
                        showToast('Tawaran harga Rp ' + Number(inputTawaran.value).toLocaleString('id-ID') + ' terkirim ke penjual.');
                        inputTawaran.value = '';
                    } else {
                        alert('Silakan masukkan nominal tawaran.');
                    }
                });
            }

            const submitBarter = document.getElementById('submit-barter');
            if (submitBarter) {
                submitBarter.addEventListener('click', function() {
                    const inputBarter = document.getElementById('input-barter-item');
                    if (inputBarter && inputBarter.value.trim()) {
                        modalBarter.close();
                        showToast('Pengajuan barter berhasil dikirimkan ke penjual.');
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
                    showToast('ASUS GTX 750 Ti berhasil dimasukkan ke keranjang!');
                });
            }

            const btnBuy = document.getElementById('btn-buy');
            if (btnBuy) {
                btnBuy.addEventListener('click', function() {
                    showToast('Mengarahkan ke halaman checkout transaksi...');
                });
            }

            // Tombol Membantu Ulasan
            const helpfulButtons = document.querySelectorAll('.helpful-btn');
            helpfulButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const countSpan = this.querySelector('span');
                    let count = parseInt(this.getAttribute('data-count'), 10) || 0;
                    if (!this.classList.contains('voted')) {
                        count += 1;
                        this.classList.add('voted', 'bg-amber-300');
                        this.setAttribute('data-count', count);
                        if (countSpan) countSpan.textContent = count;
                    }
                });
            });
        });
    </script>
</body>

</html>
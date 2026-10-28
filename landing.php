<?php
// landing.php - Halaman Landing Publik Loot-E (tanpa wajib login)
require_once "auth.php";

$db = getDbConnection();

// Ambil Kategori dari Database
$categories = [];
try {
    $stmtKat    = $db->query("SELECT * FROM kategori ORDER BY id ASC");
    $categories = $stmtKat->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Icon mapping untuk kategori
$categoryIcons = [
    'GPU'        => 'ph-graphics-card',
    'CPU'        => 'ph-cpu',
    'RAM'        => 'ph-memory',
    'Laptop'     => 'ph-laptop',
    'Phone'      => 'ph-device-mobile',
    'Smartphone' => 'ph-device-mobile',
    'Monitor'    => 'ph-monitor',
    'Headphones' => 'ph-headphones',
    'Audio'      => 'ph-speaker-high',
    'Aksesoris'  => 'ph-mouse',
    'More'       => 'ph-dots-three'
];

// Ambil Produk dari Database
$products = [];
try {
    $stmtProd = $db->query("
        SELECT p.*, k.nama_kategori, u.nama AS nama_penjual
        FROM produk p
        LEFT JOIN kategori k ON p.id_kategori = k.id
        LEFT JOIN pengguna u ON p.id_penjual = u.id
        ORDER BY p.id_kategori ASC
    ");
    $products = $stmtProd->fetchAll();
} catch (PDOException $e) {
    $products = [];
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loot-E | Find Better, Trade Smarter</title>
    <meta name="description" content="Marketplace elektronik terpercaya untuk mencari, membeli, menegosiasikan, dan menukar perangkat elektronik dengan mudah dan aman." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ["Inter", "sans-serif"],
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
    <link rel="stylesheet" href="style.css" />
</head>

<body class="bg-[#F4F5F7] font-sans text-gray-800">
    <!-- Navbar (Publik: selalu tampil tombol Login & Register) -->
    <nav class="bg-brand-dark text-white py-3 px-6 md:px-12 flex justify-between items-center sticky top-0 z-50 shadow-md">
        <div class="flex items-center gap-2">
            <a href="landing.php" class="flex items-center gap-2">
                <img src="assets/logo.png" alt="Logo Loot-E" class="h-8 w-8 rounded-full object-cover shadow-sm" />
                <span class="font-bold text-xl tracking-wide">Loot-<span class="text-brand-yellow">E</span></span>
            </a>
        </div>
        <div class="hidden md:flex gap-8 text-sm font-medium text-gray-300">
            <a href="landing.php" class="hover:text-brand-yellow transition">Home</a>
            <a href="categories.html" class="hover:text-brand-yellow transition">Categories</a>
            <a href="landing.php" class="hover:text-brand-yellow transition">About</a>
            <a href="landing.php" class="hover:text-brand-yellow transition">Help</a>
        </div>
        <!-- Tombol Login & Register (selalu tampil di landing page) -->
        <div class="flex items-center gap-3">
            <a href="login.php"
                id="btn-login"
                class="text-xs bg-brand-yellow text-brand-dark px-4 py-2 rounded-lg font-bold hover:bg-yellow-500 transition shadow-sm">
                Login
            </a>
            <a href="register.php"
                id="btn-register"
                class="text-xs border border-gray-500 text-gray-300 hover:border-brand-yellow hover:text-brand-yellow px-4 py-2 rounded-lg font-medium transition hidden sm:inline-block">
                Register
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section
        class="relative max-w-7xl mx-auto px-6 md:px-12 py-12 sm:py-16 md:py-20 grid md:grid-cols-2 gap-8 md:gap-10 items-center overflow-hidden">
        <!-- Background Image khusus tampilan Mobile -->
        <div class="absolute inset-0 md:hidden pointer-events-none -z-0 overflow-hidden">
            <img src="./assets/image.png" alt="Electronics Background"
                class="w-full h-full object-cover object-center opacity-25 filter blur-[0.5px]" />
            <div class="absolute inset-0 bg-gradient-to-b from-[#F4F5F7]/70 via-[#F4F5F7]/85 to-[#F4F5F7]"></div>
        </div>

        <div class="relative z-10">
            <p class="text-brand-yellow font-bold text-xs mb-2 md:mb-3 uppercase tracking-wider">
                Electronic Marketplace
            </p>
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold text-gray-900 leading-tight mb-4 md:mb-5">
                Find Better.<br />
                <span class="text-brand-yellow">Trade Smarter.</span>
            </h1>
            <p class="text-gray-600 mb-6 md:mb-8 max-w-md leading-relaxed text-sm md:text-base">
                Marketplace elektronik untuk mencari, membeli, menegosiasikan, dan
                menukar perangkat elektronik dengan lebih mudah, terpercaya, dan aman.
            </p>
            <div class="relative max-w-md shadow-lg rounded-full">
                <i class="ph ph-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
                <input type="text" placeholder="Cari CPU, GPU, Laptop, monitor, dll ..."
                    class="w-full pl-12 pr-14 py-3.5 rounded-full border border-transparent focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/50 bg-white text-sm" />
                <button
                    class="absolute right-2 top-1/2 -translate-y-1/2 bg-brand-yellow text-white h-9 w-9 rounded-full flex items-center justify-center hover:bg-yellow-500 transition shadow-md">
                    <i class="ph ph-arrow-right font-bold"></i>
                </button>
            </div>
        </div>

        <!-- Kolom Gambar khusus Desktop -->
        <div class="relative hidden md:flex justify-center md:justify-end z-10">
            <img src="./assets/image.png" alt="Electronics" class="w-full max-w-4xl" />
        </div>
    </section>

    <!-- Explore Categories -->
    <section id="categories" class="max-w-7xl mx-auto px-6 md:px-12 py-10">
        <div class="flex justify-between items-end mb-6">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900">
                Explore Categories
            </h2>
            <a href="#categories"
                class="text-sm text-gray-500 hover:text-brand-yellow flex items-center gap-1 transition font-medium">
                <?= count($categories) ?> Kategori Tersedia <i class="ph ph-arrow-right"></i>
            </a>
        </div>
        <div class="grid grid-cols-4 md:grid-cols-8 gap-3 md:gap-4">
            <?php foreach ($categories as $cat): ?>
                <?php
                $catTitle = $cat['nama_kategori'];
                $icon = $categoryIcons[$catTitle] ?? 'ph-tag';
                ?>
                <a href="#products"
                    class="bg-white p-3 rounded-2xl shadow-sm hover:shadow-md hover:border-brand-yellow/50 transition flex flex-col items-center justify-center gap-2 group border border-gray-100">
                    <div
                        class="h-14 w-14 bg-gray-50 rounded-xl flex items-center justify-center group-hover:bg-brand-yellow/15 transition">
                        <i class="ph <?= $icon ?> text-2xl text-gray-700 group-hover:text-brand-yellow transition"></i>
                    </div>
                    <span class="text-xs font-semibold text-gray-700"><?= htmlspecialchars($catTitle) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Popular Products (Dinamis dari Database MySQL) -->
    <section id="products" class="max-w-7xl mx-auto px-6 md:px-12 py-10">
        <div class="flex justify-between items-end mb-6">
            <div>
                <h2 class="text-xl md:text-2xl font-bold text-gray-900">
                    Popular Products
                </h2>
                <p class="text-xs text-gray-500 mt-1">Daftar produk elektronik terbaru terhubung langsung ke database</p>
            </div>
            <a href="#products"
                class="text-sm text-gray-500 hover:text-brand-yellow flex items-center gap-1 transition font-medium">
                Total: <?= count($products) ?> Produk <i class="ph ph-arrow-right"></i>
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $prod): ?>
                    <?php
                    $kat  = $prod['nama_kategori'] ?? 'Elektronik';
                    $icon = $categoryIcons[$kat] ?? 'ph-device-mobile';
                    ?>
                    <div
                        class="bg-white p-4 shadow-sm hover:shadow-md transition flex flex-col group cursor-pointer border border-gray-100">
                        <div class="h-36 mb-4 bg-gray-50 flex items-center justify-center overflow-hidden relative border border-gray-100">
                            <div class="w-16 h-16 rounded-2xl bg-brand-yellow/10 flex items-center justify-center group-hover:scale-110 transition duration-300">
                                <i class="ph <?= $icon ?> text-4xl text-brand-dark group-hover:text-brand-yellow transition"></i>
                            </div>
                            <span class="absolute top-2.5 right-2.5 text-[10px] font-semibold bg-white/90 backdrop-blur-sm px-2 py-0.5 rounded-full shadow-sm text-gray-600 border border-gray-200">
                                <?= htmlspecialchars($prod['kondisi'] ?? 'Tersedia') ?>
                            </span>
                        </div>
                        <h3 class="font-bold text-sm text-gray-900 mb-1 line-clamp-1 group-hover:text-brand-yellow transition">
                            <?= htmlspecialchars($prod['nama_produk']) ?>
                        </h3>
                        <p class="text-xs text-gray-400 mb-2">
                            <?= htmlspecialchars($kat) ?> &bull; Stok: <?= htmlspecialchars($prod['stok']) ?> unit
                        </p>
                        <p class="font-extrabold text-base text-gray-900 mb-2">
                            Rp <?= number_format($prod['harga'], 0, ',', '.') ?>
                        </p>
                        <div class="flex items-center gap-1 mb-3">
                            <i class="ph-fill ph-star text-brand-yellow text-xs"></i>
                            <span class="text-xs font-semibold text-gray-700">4.9</span>
                            <span class="text-xs text-gray-400">(Ulasan)</span>
                        </div>
                        <!-- Tombol Beli diarahkan ke login.php untuk pengunjung yang belum login -->
                        <div
                            class="flex items-center justify-between text-[11px] font-medium text-gray-600 bg-gray-50 w-full px-2.5 py-1.5 rounded-lg mt-auto border border-gray-100">
                            <div class="flex items-center gap-1">
                                <i class="ph-fill ph-shield-check text-green-500 text-sm"></i>
                                <span class="truncate max-w-[130px]"><?= htmlspecialchars($prod['nama_penjual'] ?? 'Trusted Seller') ?></span>
                            </div>
                            <a href="login.php" class="text-brand-yellow font-bold text-xs group-hover:translate-x-0.5 transition" title="Login untuk membeli">Beli &rarr;</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full py-12 text-center text-gray-500">
                    <p>Belum ada produk yang tersedia di database saat ini.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Banner CTA login untuk pengunjung yang belum login -->
        <div class="mt-10 bg-brand-dark px-6 py-5 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-lg">
            <div class="flex items-center gap-3">
                <i class="ph-fill ph-lock text-brand-yellow text-2xl"></i>
                <p class="text-white text-sm font-medium">
                    Masuk atau daftar untuk menambahkan produk ke keranjang dan melakukan transaksi.
                </p>
            </div>
            <div class="flex gap-3 shrink-0">
                <a href="login.php"
                    id="cta-login"
                    class="bg-brand-yellow text-brand-dark text-sm font-bold px-5 py-2.5 hover:bg-yellow-500 transition shadow">
                    Login
                </a>
                <a href="register.php"
                    id="cta-register"
                    class="border border-gray-500 text-gray-300 hover:border-brand-yellow hover:text-brand-yellow text-sm font-medium px-5 py-2.5 transition">
                    Register
                </a>
            </div>
        </div>
    </section>

    <!-- Why Loot-E -->
    <section id="why-loote" class="max-w-7xl mx-auto px-6 md:px-12 py-10">
        <div class="bg-white p-8 md:p-12 shadow-sm border border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Why Loot-<span class="text-brand-yellow">E</span>?
            </h2>
            <p class="text-gray-500 text-sm mb-10 max-w-md">
                Kami hadir dengan fitur lengkap yang dirancang untuk memberikan
                pengalaman transaksi elektronik terbaik
            </p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div>
                    <i class="ph-fill ph-chart-bar text-4xl text-brand-yellow mb-4"></i>
                    <h4 class="font-bold text-sm text-gray-900 mb-2">
                        Market Price Reference
                    </h4>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Ketahui harga pasar terbaru sebelum membeli.
                    </p>
                </div>
                <div>
                    <i class="ph-fill ph-check-circle text-4xl text-brand-yellow mb-4"></i>
                    <h4 class="font-bold text-sm text-gray-900 mb-2">
                        Product Condition &amp; History
                    </h4>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Lihat kondisi detail dan riwayat produk.
                    </p>
                </div>
                <div>
                    <i class="ph-fill ph-user-circle text-4xl text-brand-yellow mb-4"></i>
                    <h4 class="font-bold text-sm text-gray-900 mb-2">
                        Seller Performance
                    </h4>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Cek rating dan aktivitas seller sebelum transaksi.
                    </p>
                </div>
                <div>
                    <i class="ph-fill ph-lightning text-4xl text-brand-yellow mb-4"></i>
                    <h4 class="font-bold text-sm text-gray-900 mb-2">Buy Now</h4>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Transaksi cepat dengan harga tetap.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="max-w-7xl mx-auto px-6 md:px-12 py-10 mb-10">
        <div
            class="bg-[#303135] p-8 md:p-12 flex flex-col md:flex-row items-center justify-between shadow-2xl relative overflow-hidden">
            <div class="relative z-10 w-full md:w-2/3">
                <p class="text-brand-yellow font-bold text-sm mb-2">
                    Ready to Upgrade?
                </p>
                <h2 class="text-2xl md:text-3xl font-bold text-white mb-4 leading-snug">
                    Temukan Perangkat Elektronik<br />Impianmu di Loot-<span class="text-brand-yellow">E</span>
                </h2>
                <p class="text-gray-400 text-sm max-w-md">
                    Jelajahi ribuan produk elektronik dari berbagai seller terpercaya
                    dengan harga terbaik.
                </p>
            </div>
            <div class="mt-8 md:mt-0 relative z-10 w-full md:w-auto flex flex-col sm:flex-row gap-3">
                <a href="register.php"
                    class="bg-brand-yellow hover:bg-yellow-500 text-[#303135] font-bold py-3.5 px-6 transition inline-flex items-center justify-center gap-2 w-full md:w-auto shadow-lg">
                    Daftar Sekarang <i class="ph ph-arrow-right font-bold"></i>
                </a>
                <a href="login.php"
                    class="border border-gray-500 text-gray-300 hover:border-brand-yellow hover:text-brand-yellow font-bold py-3.5 px-6 transition inline-flex items-center justify-center gap-2 w-full md:w-auto">
                    Login
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-[#262628] text-gray-400 py-12 px-6 md:px-12">
        <div
            class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-8 mb-8 border-b border-gray-700 pb-8">
            <div class="text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-2 mb-2">
                    <span class="font-bold text-2xl text-white tracking-wide">LOOT-<span
                            class="text-brand-yellow">E</span></span>
                </div>
                <p class="text-xs">Find Better, Trade Smarter.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-6 text-sm">
                <a href="landing.php" class="hover:text-brand-yellow transition">Home</a>
                <a href="categories.html" class="hover:text-brand-yellow transition">Categories</a>
                <a href="#products" class="hover:text-brand-yellow transition">Products</a>
                <a href="#why-loote" class="hover:text-brand-yellow transition">About</a>
            </div>
            <div class="flex gap-4">
                <a href="#" class="hover:text-white transition"><i class="ph-fill ph-youtube-logo text-2xl"></i></a>
                <a href="#" class="hover:text-white transition"><i class="ph-fill ph-facebook-logo text-2xl"></i></a>
                <a href="#" class="hover:text-white transition"><i class="ph-fill ph-instagram-logo text-2xl"></i></a>
                <a href="#" class="hover:text-white transition"><i class="ph-fill ph-discord-logo text-2xl"></i></a>
            </div>
        </div>
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between text-xs">
            <p>&copy; <?= date('Y') ?> Loot-E. All rights reserved.</p>
            <div class="flex gap-4 mt-4 md:mt-0">
                <a href="#" class="hover:text-white transition">Privacy</a>
                <a href="#" class="hover:text-white transition">Terms</a>
            </div>
        </div>
    </footer>

</body>

</html>
<?php
// categories.php - Halaman Explore Categories Loot-E
require_once "auth.php";

$currentUser = getCurrentUser();

// ===== Search Filter PHP =====
$searchQuery = trim($_GET['q'] ?? '');

// ===== Data Kategori =====
// PC Components
$allPcComponents = [
    ['name' => 'GPU',          'icon' => 'ph-graphics-card', 'slug' => 'gpu'],
    ['name' => 'CPU',          'icon' => 'ph-cpu',           'slug' => 'cpu'],
    ['name' => 'RAM',          'icon' => 'ph-memory',        'slug' => 'ram'],
    ['name' => 'Motherboard',  'icon' => 'ph-circuit-board', 'slug' => 'motherboard'],
    ['name' => 'Storage',      'icon' => 'ph-hard-drives',   'slug' => 'storage'],
    ['name' => 'Power Supply', 'icon' => 'ph-lightning',     'slug' => 'power-supply'],
    ['name' => 'PC Case',      'icon' => 'ph-desktop-tower', 'slug' => 'pc-case'],
    ['name' => 'CPU Cooler',   'icon' => 'ph-fan',           'slug' => 'cpu-cooler'],
];

// Elektronik Lainnya
$allOtherElectronics = [
    ['name' => 'Laptop',     'icon' => 'ph-laptop',        'slug' => 'laptop'],
    ['name' => 'Smartphone', 'icon' => 'ph-device-mobile', 'slug' => 'smartphone'],
    ['name' => 'Monitor',    'icon' => 'ph-monitor',       'slug' => 'monitor'],
    ['name' => 'Headset',    'icon' => 'ph-headphones',    'slug' => 'headset'],
    ['name' => 'Keyboard',   'icon' => 'ph-keyboard',      'slug' => 'keyboard'],
    ['name' => 'Mouse',      'icon' => 'ph-mouse',         'slug' => 'mouse'],
    ['name' => 'Tablet',     'icon' => 'ph-device-tablet', 'slug' => 'tablet'],
    ['name' => 'Aksesoris',  'icon' => 'ph-bag-simple',    'slug' => 'aksesoris'],
];

// Fungsi filter berdasarkan query pencarian
function filterCategories(array $categories, string $query): array
{
    if ($query === '') return $categories;
    return array_values(array_filter($categories, function ($cat) use ($query) {
        return stripos($cat['name'], $query) !== false ||
            stripos($cat['slug'], $query) !== false;
    }));
}

$pcComponents    = filterCategories($allPcComponents, $searchQuery);
$otherElectronics = filterCategories($allOtherElectronics, $searchQuery);
?>
<!doctype html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loot-E | Explore Categories</title>
    <meta name="description"
        content="Jelajahi kategori elektronik di Loot-E — PC Components, Laptop, Smartphone, Monitor, dan lainnya." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
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
                    keyframes: {
                        fadeInUp: {
                            '0%': {
                                opacity: '0',
                                transform: 'translateY(20px)'
                            },
                            '100%': {
                                opacity: '1',
                                transform: 'translateY(0)'
                            },
                        },
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.6s ease forwards',
                    },
                },
            },
        };
    </script>
</head>

<body class="bg-[#F4F5F7] font-sans text-gray-800">
    <!-- Navbar -->
    <nav class="bg-brand-dark text-white py-3 px-6 md:px-12 flex justify-between items-center sticky top-0 z-50 shadow-md">
        <div class="flex items-center gap-2">
            <a href="index.php" class="flex items-center gap-2">
                <img src="assets/logo.png" alt="Logo Loot-E" class="h-8 w-8 rounded-full object-cover shadow-sm" />
                <span class="font-bold text-xl tracking-wide">Loot-<span class="text-brand-yellow">E</span></span>
            </a>
        </div>
        <div class="hidden md:flex gap-8 text-sm font-medium text-gray-300">
            <a href="index.php" class="hover:text-brand-yellow transition">Home</a>
            <a href="categories.php" class="text-brand-yellow transition font-semibold">Categories</a>
            <a href="landing.php#why-loote" class="hover:text-brand-yellow transition">About</a>
            <a href="#" class="hover:text-brand-yellow transition">Help</a>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($currentUser): ?>
                <div class="flex items-center gap-3 border-l border-gray-600 pl-4">
                    <div class="w-8 h-8 rounded-full bg-brand-yellow text-brand-dark flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                        <?= htmlspecialchars(strtoupper(substr($currentUser['nama'], 0, 2))) ?>
                    </div>
                    <div class="hidden sm:flex flex-col text-left">
                        <span class="text-xs font-bold text-white leading-tight">Halo, <?= htmlspecialchars($currentUser['nama']) ?>!</span>
                    </div>
                    <a href="auth.php?action=logout"
                        class="text-xs bg-red-500/20 text-red-300 hover:bg-red-500 hover:text-white px-2.5 py-1.5 rounded-lg font-medium transition ml-1"
                        title="Keluar dari akun">
                        Keluar
                    </a>
                </div>
            <?php else: ?>
                <a href="login.php" id="btn-login"
                    class="text-xs bg-brand-yellow text-brand-dark px-4 py-2 rounded-lg font-bold hover:bg-yellow-500 transition shadow-sm">
                    Login
                </a>
                <a href="register.php" id="btn-register"
                    class="text-xs border border-gray-500 text-gray-300 hover:border-brand-yellow hover:text-brand-yellow px-4 py-2 rounded-lg font-medium transition hidden sm:inline-block">
                    Register
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden">
        <!-- Background Image khusus tampilan Mobile -->
        <div class="absolute inset-0 md:hidden pointer-events-none -z-0 overflow-hidden">
            <img src="./assets/image.png" alt="Electronics Background"
                class="w-full h-full object-cover object-center opacity-25 filter blur-[0.5px]" />
            <div class="absolute inset-0 bg-gradient-to-b from-[#F4F5F7]/70 via-[#F4F5F7]/85 to-[#F4F5F7]"></div>
        </div>

        <div class="max-w-7xl mx-auto px-6 md:px-12 <?= $searchQuery !== '' ? 'pt-10 pb-6 md:pt-14 md:pb-8' : 'py-12 md:py-16' ?> grid md:grid-cols-2 gap-8 items-center relative z-10">
            <div>
                <h1 class="text-4xl sm:text-5xl md:text-[3.25rem] font-black text-gray-900 leading-tight mb-6">
                    Explore <span class="text-brand-yellow">Categories</span>
                </h1>
                <form action="categories.php" method="GET" class="relative max-w-lg shadow-lg rounded-full">
                    <i class="ph ph-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none"></i>
                    <input type="text" name="q" id="categorySearch"
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        placeholder="Cari GPU, CPU, Laptop, monitor, dll..."
                        class="w-full pl-12 pr-14 py-3.5 rounded-full border border-transparent focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/50 bg-white text-sm text-gray-800" />
                    <button type="submit"
                        class="absolute right-2 top-1/2 -translate-y-1/2 bg-brand-yellow text-white h-9 w-9 rounded-full flex items-center justify-center hover:bg-yellow-500 transition shadow-md">
                        <i class="ph ph-arrow-right font-bold"></i>
                    </button>
                </form>

                <?php if ($searchQuery !== ''): ?>
                    <div class="mt-3.5 flex items-center gap-3 flex-wrap pl-2">
                        <p class="text-xs sm:text-sm text-gray-600">
                            Hasil pencarian untuk <span class="font-semibold text-gray-900">"<?= htmlspecialchars($searchQuery) ?>"</span>
                            <span class="text-gray-400">&bull;</span> <?= count($pcComponents) + count($otherElectronics) ?> kategori ditemukan
                        </p>
                        <a href="categories.php" class="text-xs text-brand-dark bg-brand-yellow/20 hover:bg-brand-yellow px-2.5 py-0.5 rounded-full font-medium transition flex items-center gap-1">
                            <i class="ph ph-x text-xs"></i> Hapus pencarian
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Hero Illustration -->
            <div class="relative hidden md:flex justify-center md:justify-end">
                <img src="./assets/image.png" alt="Electronics" class="w-full max-w-[600px] drop-shadow-xl" />
            </div>
        </div>
    </section>

    <!-- PC Components Section -->
    <section id="pc-components" class="max-w-7xl mx-auto px-6 md:px-12 <?= $searchQuery !== '' ? 'pt-4 md:pt-6' : 'pt-12' ?> pb-6">
        <div class="mb-8">
            <div class="w-10 h-1 rounded-sm bg-brand-yellow mb-2.5"></div>
            <h2 class="text-2xl md:text-[1.7rem] font-extrabold text-gray-900 mb-1">PC Components</h2>
            <p class="text-sm text-gray-500">Build or upgrade your PC with the right components.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5" id="pcComponentsGrid">
            <?php foreach ($pcComponents as $cat): ?>
                <a href="index.php?kategori=<?= urlencode($cat['slug']) ?>"
                    class="group relative overflow-hidden bg-white border border-gray-100 rounded-2xl p-5 flex items-center gap-4 transition-all duration-300 hover:border-brand-yellow hover:shadow-[0_8px_30px_rgba(245,183,0,0.12)] hover:-translate-y-1 text-gray-800">
                    <div class="w-[72px] h-[72px] min-w-[72px] rounded-xl bg-gray-50 flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                        <i class="ph <?= htmlspecialchars($cat['icon']) ?> text-3xl text-gray-700 group-hover:text-brand-yellow transition-colors"></i>
                    </div>
                    <div class="flex-1 min-w-0 relative z-10">
                        <h3 class="font-bold text-sm text-gray-900 mb-0.5 group-hover:text-brand-yellow transition-colors">
                            <?= htmlspecialchars($cat['name']) ?>
                        </h3>
                        <p class="text-xs text-gray-400">Browse Products</p>
                    </div>
                    <div class="w-9 h-9 min-w-[36px] rounded-full bg-brand-yellow text-white flex items-center justify-center transition-transform duration-300 ml-auto group-hover:translate-x-1 shadow-sm">
                        <i class="ph ph-arrow-right text-sm font-bold"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($searchQuery !== '' && empty($pcComponents)): ?>
            <div class="text-center py-10 px-5 text-gray-400">
                <i class="ph ph-magnifying-glass text-4xl text-gray-300 mb-3"></i>
                <p class="text-sm">Tidak ada kategori yang cocok dengan pencarian.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Elektronik Lainnya Section -->
    <section id="other-electronics" class="max-w-7xl mx-auto px-6 md:px-12 pt-8 pb-12">
        <div class="mb-8">
            <div class="w-10 h-1 rounded-sm bg-brand-yellow mb-2.5"></div>
            <h2 class="text-2xl md:text-[1.7rem] font-extrabold text-gray-900 mb-1">Elektronik Lainnya</h2>
            <p class="text-sm text-gray-500">Eksplor elektronik lain untuk penggunaan sehari-hari.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5" id="otherElectronicsGrid">
            <?php foreach ($otherElectronics as $cat): ?>
                <a href="index.php?kategori=<?= urlencode($cat['slug']) ?>"
                    class="group relative overflow-hidden bg-white border border-gray-100 rounded-2xl p-5 flex items-center gap-4 transition-all duration-300 hover:border-brand-yellow hover:shadow-[0_8px_30px_rgba(245,183,0,0.12)] hover:-translate-y-1 text-gray-800">
                    <div class="w-[72px] h-[72px] min-w-[72px] rounded-xl bg-gray-50 flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                        <i class="ph <?= htmlspecialchars($cat['icon']) ?> text-3xl text-gray-700 group-hover:text-brand-yellow transition-colors"></i>
                    </div>
                    <div class="flex-1 min-w-0 relative z-10">
                        <h3 class="font-bold text-sm text-gray-900 mb-0.5 group-hover:text-brand-yellow transition-colors">
                            <?= htmlspecialchars($cat['name']) ?>
                        </h3>
                        <p class="text-xs text-gray-400">Browse Products</p>
                    </div>
                    <div class="w-9 h-9 min-w-[36px] rounded-full bg-brand-yellow text-white flex items-center justify-center transition-transform duration-300 ml-auto group-hover:translate-x-1 shadow-sm">
                        <i class="ph ph-arrow-right text-sm font-bold"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($searchQuery !== '' && empty($otherElectronics)): ?>
            <div class="text-center py-10 px-5 text-gray-400">
                <i class="ph ph-magnifying-glass text-4xl text-gray-300 mb-3"></i>
                <p class="text-sm">Tidak ada kategori yang cocok dengan pencarian.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- CTA Search Banner -->
    <section class="max-w-7xl mx-auto px-6 md:px-12 pb-16">
        <div class="relative overflow-hidden bg-gradient-to-br from-[#303135] via-[#404045] to-[#303135] px-8 md:px-12 py-10 flex flex-col lg:flex-row items-center justify-between gap-8">
            <!-- Decorative Glow Elements -->
            <div class="absolute -top-1/2 -left-1/4 w-96 h-96 rounded-full bg-[radial-gradient(circle,rgba(245,183,0,0.08)_0%,transparent_70%)] pointer-events-none"></div>
            <div class="absolute -bottom-1/2 -right-1/4 w-80 h-80 rounded-full bg-[radial-gradient(circle,rgba(245,183,0,0.06)_0%,transparent_70%)] pointer-events-none"></div>

            <div class="relative z-10">
                <h2 class="text-xl md:text-2xl font-bold text-white leading-snug">
                    Looking for <span class="text-brand-yellow font-extrabold">something specific?</span>
                </h2>
                <p class="text-gray-400 text-sm mt-1">Search directly to find the electronics you need.</p>
            </div>
            <div class="relative z-10 flex items-center gap-3 w-full lg:w-auto">
                <form action="index.php" method="GET" class="flex items-center gap-3 w-full lg:w-auto">
                    <div class="relative flex-1 sm:w-[360px] lg:w-[400px]">
                        <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-base"></i>
                        <input type="text" name="q" placeholder="Cari produk yang ingin kamu cari..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-full bg-white text-gray-800 text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-yellow transition" />
                    </div>
                    <button type="submit"
                        class="bg-brand-yellow hover:bg-yellow-500 text-brand-dark font-bold py-2.5 px-6 rounded-full text-sm transition shadow-lg flex items-center gap-2 whitespace-nowrap">
                        Cari Produk <i class="ph ph-arrow-right font-bold"></i>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-[#262628] text-gray-400 py-12 px-6 md:px-12">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-8 mb-8 border-b border-gray-700 pb-8">
            <div class="text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-2 mb-2">
                    <span class="font-bold text-2xl text-white tracking-wide">LOOT-<span class="text-brand-yellow">E</span></span>
                </div>
                <p class="text-xs">Find Better, Trade Smarter.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-6 text-sm">
                <a href="landing.php" class="hover:text-brand-yellow transition">Home</a>
                <a href="categories.php" class="text-brand-yellow transition">Categories</a>
                <a href="landing.php#why-loote" class="hover:text-brand-yellow transition">About</a>
                <a href="#" class="hover:text-brand-yellow transition">Help</a>
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

    <!-- JavaScript: auto-submit form saat user mengetik (debounce 400ms) -->
    <script>
        (function() {
            const input = document.getElementById('categorySearch');
            if (!input) return;
            let timer;
            input.addEventListener('input', function() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    input.closest('form').submit();
                }, 400);
            });
        })();
    </script>
</body>

</html>
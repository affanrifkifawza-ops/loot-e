<?php
// categories.php - Halaman Explore Categories Loot-E
require_once "auth.php";

// Wajib login untuk mengakses halaman ini
if (!isLoggedIn()) {
    header("Location: login.php?redirect=categories.php");
    exit;
}

$currentUser = getCurrentUser();

// ===== Search Filter PHP =====
$searchQuery = trim($_GET['q'] ?? '');

// ===== Data Kategori (dari tabel kategori) =====
// Kategori yang masuk grup "PC Components"; kategori lain (termasuk yang baru ditambah) masuk "Elektronik Lainnya"
$pcComponentNames = ['gpu', 'cpu', 'ram', 'motherboard', 'storage', 'power supply', 'pc case', 'cpu cooler'];

$stmt = getDbConnection()->prepare("SELECT id, nama_kategori FROM kategori WHERE nama_kategori LIKE ? ORDER BY id");
$stmt->execute(['%' . $searchQuery . '%']);

$pcComponents = $otherElectronics = [];
foreach ($stmt->fetchAll() as $cat) {
    if (in_array(strtolower($cat['nama_kategori']), $pcComponentNames, true)) {
        $pcComponents[] = $cat;
    } else {
        $otherElectronics[] = $cat;
    }
}
?>
<!doctype html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loot-E | Explore Categories</title>
    <meta name="description"
        content="Jelajahi kategori elektronik di Loot-E: PC Components, Laptop, Smartphone, Monitor, dan lainnya." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        hd: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        ye: '#FFC914',
                        ch: '#272727',
                        brand: {
                            yellow: '#FFC914',
                            dark: '#272727',
                            footer: '#272727',
                            bg: '#F4F5F7',
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
    <?php $navActivePage = 'categories';
    require_once 'components/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="relative bg-brand-yellow overflow-hidden">
        <!-- Background Image Hero -->
        <div class="absolute inset-0 w-full h-full pointer-events-none select-none overflow-hidden">
            <img src="assets/categoriesimage.png" alt="Explore Categories Background"
                class="w-full h-full object-cover object-right md:object-[85%_center] filter brightness-[0.98]"
                fetchpriority="high" />
            <div class="absolute inset-0 bg-gradient-to-r from-brand-yellow via-brand-yellow/85 sm:via-brand-yellow/60 to-transparent"></div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-12 <?= $searchQuery !== '' ? 'pt-10 pb-6 md:pt-14 md:pb-8' : 'py-12 md:py-16' ?> flex items-center min-h-[260px] sm:min-h-[300px] md:min-h-[340px]">
            <div class="max-w-xl w-full">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-[#272727] leading-tight mb-5 tracking-tight">
                    Explore Categories
                </h1>
                <form action="categories.php" method="GET" onsubmit="if(!this.q.value.trim()){ this.q.focus(); return false; }" class="relative max-w-lg shadow-lg rounded-full">
                    <i class="ph ph-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none"></i>
                    <input type="text" name="q" id="categorySearch" required
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        placeholder="Cari GPU, CPU, Laptop, monitor, dll..."
                        class="w-full pl-12 pr-14 py-3.5 rounded-full border border-transparent focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/50 bg-white text-sm text-gray-800" />
                    <button type="submit"
                        class="absolute right-2 top-1/2 -translate-y-1/2 bg-[#FFC914] text-white h-9 w-9 rounded-full flex items-center justify-center hover:brightness-95 transition shadow-xs" aria-label="Cari kategori">
                        <i class="ph ph-arrow-right font-bold"></i>
                    </button>
                </form>

                <?php if ($searchQuery !== ''): ?>
                    <div class="mt-3.5 flex items-center gap-3 flex-wrap pl-2">
                        <p class="text-xs sm:text-sm text-[#272727] font-medium">
                            Hasil pencarian untuk <span class="font-bold text-[#272727]">&ldquo;<?= htmlspecialchars($searchQuery) ?>&rdquo;</span>
                            <span class="text-[#272727]/60">&bull;</span> <?= count($pcComponents) + count($otherElectronics) ?> kategori ditemukan
                        </p>
                        <a href="categories.php" class="text-xs text-white bg-[#272727] hover:bg-neutral-800 px-3 py-1 rounded-full font-semibold transition flex items-center gap-1 shadow-xs">
                            <i class="ph ph-x text-xs"></i> Hapus pencarian
                        </a>
                    </div>
                <?php endif; ?>
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
                <a href="search.php?kategori=<?= (int)$cat['id'] ?>"
                    class="group relative overflow-hidden bg-white border border-gray-100 p-5 flex items-center gap-4 transition-all duration-300 hover:border-brand-yellow hover:shadow-[0_8px_30px_rgba(245,183,0,0.12)] hover:-translate-y-1 text-gray-800">
                    <div class="w-[72px] h-[72px] min-w-[72px] rounded-xl bg-gray-50 flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                        <i class="ph <?= htmlspecialchars(kategoriIcon($cat['nama_kategori'])) ?> text-3xl text-gray-700 group-hover:text-brand-yellow transition-colors"></i>
                    </div>
                    <div class="flex-1 min-w-0 relative z-10">
                        <h3 class="font-bold text-sm text-gray-900 mb-0.5 group-hover:text-brand-yellow transition-colors">
                            <?= htmlspecialchars($cat['nama_kategori']) ?>
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
                <a href="search.php?kategori=<?= (int)$cat['id'] ?>"
                    class="group relative overflow-hidden bg-white border border-gray-100 p-5 flex items-center gap-4 transition-all duration-300 hover:border-brand-yellow hover:shadow-[0_8px_30px_rgba(245,183,0,0.12)] hover:-translate-y-1 text-gray-800">
                    <div class="w-[72px] h-[72px] min-w-[72px] rounded-xl bg-gray-50 flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                        <i class="ph <?= htmlspecialchars(kategoriIcon($cat['nama_kategori'])) ?> text-3xl text-gray-700 group-hover:text-brand-yellow transition-colors"></i>
                    </div>
                    <div class="flex-1 min-w-0 relative z-10">
                        <h3 class="font-bold text-sm text-gray-900 mb-0.5 group-hover:text-brand-yellow transition-colors">
                            <?= htmlspecialchars($cat['nama_kategori']) ?>
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
        <div class="relative overflow-hidden bg-[#272727] px-8 md:px-12 py-10 flex flex-col lg:flex-row items-center justify-between gap-8 shadow-sm">
            <!-- Decorative Glow Elements -->
            <div class="absolute -top-1/2 -left-1/4 w-96 h-96 rounded-full bg-[radial-gradient(circle,rgba(255,201,20,0.08)_0%,transparent_70%)] pointer-events-none"></div>
            <div class="absolute -bottom-1/2 -right-1/4 w-80 h-80 rounded-full bg-[radial-gradient(circle,rgba(255,201,20,0.06)_0%,transparent_70%)] pointer-events-none"></div>

            <div class="relative z-10">
                <h2 class="text-xl md:text-2xl font-bold text-white leading-snug">
                    Looking for <span class="text-brand-yellow font-extrabold">something specific?</span>
                </h2>
                <p class="text-gray-400 text-sm mt-1">Search directly to find the electronics you need.</p>
            </div>
            <div class="relative z-10 flex items-center gap-3 w-full lg:w-auto">
                <form action="search.php" method="GET" class="flex items-center gap-3 w-full lg:w-auto">
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
    <?php require_once 'components/footer.php'; ?>

</body>

</html>
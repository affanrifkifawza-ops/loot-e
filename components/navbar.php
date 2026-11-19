<?php
// Ambil variabel dari scope pemanggil
$_navUser    = $currentUser ?? null;
$_activePage = $navActivePage ?? '';
$_baseHome   = $_navUser ? 'index.php' : 'landingpage.php';
$_onMainPage = ($_activePage === 'landing' || $_activePage === 'home');
$_hrefHome   = $_onMainPage ? '#' : $_baseHome;
$_hrefProd   = $_onMainPage ? '#products' : $_baseHome . '#products';
$_hrefAbout  = $_activePage === 'landing' ? '#why-loote' : 'landingpage.php#why-loote';

/**
 * Helper: tentukan class link navbar
 * Link aktif → kuning & semibold | tidak aktif → abu hover kuning
 */
if (!function_exists('_navLinkClass')) {
    function _navLinkClass(string $page, string $active): string
    {
        return $page === $active
            ? 'text-brand-yellow font-semibold transition'
            : 'hover:text-brand-yellow transition';
    }
}
?>
<nav class="bg-brand-dark text-white py-4 px-6 md:px-12 flex justify-between items-center sticky top-0 z-50 shadow-md">

    <!-- Logo -->
    <div class="flex items-center gap-2">
        <a href="<?= $_navUser ? 'index.php' : 'landingpage.php' ?>" class="flex items-center gap-2">
            <img src="assets/logo.png" alt="Logo Loot-E" class="h-8 w-8 rounded-full object-cover shadow-sm" />
            <span class="font-bold text-xl tracking-wide">Loot-<span class="text-brand-yellow">E</span></span>
        </a>
    </div>

    <!-- Menu Navigasi (Desktop) -->
    <div class="hidden md:flex gap-8 text-sm font-medium text-gray-300">
        <a href="<?= $_hrefHome ?>"
            class="<?= _navLinkClass('home', $_activePage) ?>">Home</a>
        <a href="<?= $_hrefProd ?>"
            class="<?= _navLinkClass('categories', $_activePage) ?>">Products</a>
        <a href="<?= $_hrefAbout ?>"
            class="<?= _navLinkClass('about', $_activePage) ?>">About</a>
        <a href="help.php"
            class="<?= _navLinkClass('help', $_activePage) ?>">Help</a>
    </div>

    <!-- Aksi Kanan: Ikon + User / Login + Toggle Mobile -->
    <div class="flex items-center gap-2 sm:gap-3 md:gap-4">
        <?php if ($_navUser): ?>
            <!-- Ikon Notifikasi & Keranjang (Hanya Desktop) -->
            <button type="button" class="hidden md:flex items-center justify-center w-9 h-9 rounded-lg hover:bg-white/10 text-gray-300 hover:text-brand-yellow transition" title="Notifikasi" aria-label="Notifikasi">
                <i class="ph ph-bell text-xl"></i>
            </button>
            <button type="button" class="hidden md:flex items-center justify-center w-9 h-9 rounded-lg hover:bg-white/10 text-gray-300 hover:text-brand-yellow transition" title="Keranjang" aria-label="Keranjang belanja">
                <i class="ph ph-shopping-cart text-xl"></i>
            </button>

            <!-- Profil User (Avatar di Mobile & Desktop, Nama & Logout di Desktop) -->
            <div class="flex items-center gap-3 md:border-l md:border-gray-600 md:pl-4">
                <!-- Avatar inisial / Profile Picture -->
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-brand-yellow text-brand-dark flex items-center justify-center font-bold text-xs uppercase shadow-sm shrink-0" title="<?= htmlspecialchars($_navUser['nama']) ?>">
                    <?= htmlspecialchars(strtoupper(substr($_navUser['nama'], 0, 2))) ?>
                </div>
                <!-- Nama user (Hanya Desktop) -->
                <div class="hidden md:flex flex-col text-left">
                    <span class="text-xs font-bold text-white leading-tight">
                        Halo, <?= htmlspecialchars($_navUser['nama']) ?>!
                    </span>
                </div>
                <!-- Tombol Keluar (Hanya Desktop) -->
                <a href="auth.php?action=logout"
                    class="hidden md:inline-flex text-xs bg-red-500/20 text-red-300 hover:bg-red-500 hover:text-white px-2.5 py-1.5 rounded-lg font-medium transition ml-1"
                    title="Keluar dari akun">
                    Keluar
                </a>
            </div>
        <?php else: ?>
            <!-- Tombol Login & Register (Hanya Desktop) -->
            <a href="login.php"
                class="hidden md:inline-flex text-xs bg-brand-yellow text-brand-dark px-4 py-2 rounded-lg font-bold hover:bg-yellow-500 transition shadow-sm">
                Login
            </a>
            <a href="register.php"
                class="hidden md:inline-flex text-xs border border-gray-500 text-gray-300 hover:border-brand-yellow hover:text-brand-yellow px-4 py-2 rounded-lg font-medium transition">
                Register
            </a>
        <?php endif; ?>

        <!-- Tombol Hamburger (Hanya Mobile) -->
        <button type="button" id="navbar-mobile-toggle"
            class="md:hidden flex items-center justify-center w-10 h-10 rounded-lg text-2xl text-gray-300 hover:text-white hover:bg-white/10 active:scale-95 transition focus:outline-none"
            aria-label="Buka menu navigasi" aria-expanded="false">
            <i class="ph ph-list" id="navbar-mobile-icon"></i>
        </button>
    </div>

</nav>

<!-- Menu Navigasi Mobile Dropdown -->
<div id="navbar-mobile-menu" class="hidden md:hidden bg-brand-dark text-white px-6 pb-5 pt-3 border-t border-gray-700/60 shadow-lg">
    <?php if ($_navUser): ?>
        <!-- Info Profil User di Mobile Menu -->
        <div class="flex items-center justify-between pb-3 mb-2 border-b border-gray-700/60">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-brand-yellow text-brand-dark flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                    <?= htmlspecialchars(strtoupper(substr($_navUser['nama'], 0, 2))) ?>
                </div>
                <div>
                    <p class="text-xs font-bold text-white leading-tight"><?= htmlspecialchars($_navUser['nama']) ?></p>
                    <p class="text-[11px] text-gray-400 capitalize"><?= $_navUser['peran'] === 'admin' ? 'Admin' : ($_navUser['is_verified'] ? 'Verified Seller' : 'Pengguna') ?></p>
                </div>
            </div>
            <a href="auth.php?action=logout" class="text-xs bg-red-500/20 text-red-300 hover:bg-red-500 hover:text-white px-3 py-1.5 rounded-lg font-medium transition" title="Keluar">
                Keluar
            </a>
        </div>
    <?php endif; ?>

    <div class="flex flex-col gap-2 text-sm font-medium">
        <a href="<?= $_hrefHome ?>" class="<?= _navLinkClass('home', $_activePage) ?> py-2 px-2 rounded-lg hover:bg-white/5">Home</a>
        <a href="<?= $_hrefProd ?>" class="<?= _navLinkClass('categories', $_activePage) ?> py-2 px-2 rounded-lg hover:bg-white/5">Products</a>
        <a href="<?= $_hrefAbout ?>" class="<?= _navLinkClass('about', $_activePage) ?> py-2 px-2 rounded-lg hover:bg-white/5">About</a>
        <a href="help.php" class="<?= _navLinkClass('help', $_activePage) ?> py-2 px-2 rounded-lg hover:bg-white/5">Help</a>
        <?php if (!$_navUser): ?>
            <div class="pt-3 mt-1 border-t border-gray-700/50 flex gap-2">
                <a href="login.php" class="text-xs text-center flex-1 bg-brand-yellow text-brand-dark py-2.5 rounded-lg font-bold hover:brightness-95 transition">Login</a>
                <a href="register.php" class="text-xs text-center flex-1 border border-gray-500 py-2.5 rounded-lg text-gray-300 hover:text-white transition">Register</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    (function() {
        const toggleBtn = document.getElementById('navbar-mobile-toggle');
        const mobileMenu = document.getElementById('navbar-mobile-menu');
        const toggleIcon = document.getElementById('navbar-mobile-icon');
        if (toggleBtn && mobileMenu) {
            toggleBtn.addEventListener('click', function() {
                const isHidden = mobileMenu.classList.contains('hidden');
                if (isHidden) {
                    mobileMenu.classList.remove('hidden');
                    toggleBtn.setAttribute('aria-expanded', 'true');
                    if (toggleIcon) toggleIcon.className = 'ph ph-x';
                } else {
                    mobileMenu.classList.add('hidden');
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    if (toggleIcon) toggleIcon.className = 'ph ph-list';
                }
            });
        }
    })();
</script>
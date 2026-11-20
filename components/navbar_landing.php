<?php
// Navbar khusus Landing Page (dan halaman publik saat belum login)
$_activePage = $navActivePage ?? '';
$_hrefHome   = $_activePage === 'landing' ? '#' : 'landingpage.php';
$_hrefProd   = $_activePage === 'landing' ? '#products' : 'landingpage.php#products';
$_hrefAbout  = $_activePage === 'landing' ? '#why-loote' : 'landingpage.php#why-loote';

if (!function_exists('_navLandingClass')) {
    function _navLandingClass(string $page, string $active): string
    {
        return $page === $active
            ? 'text-brand-yellow font-semibold transition'
            : 'hover:text-brand-yellow transition';
    }
}
?>
<nav class="bg-brand-dark text-white py-4 px-6 md:px-12 flex justify-between items-center sticky top-0 z-50 shadow-md">
    <!-- Logo -->
    <a href="landingpage.php" class="flex items-center gap-2">
        <img src="assets/logo.png" alt="Logo Loot-E" class="h-8 w-8 rounded-full object-cover shadow-sm" />
        <span class="font-bold text-xl tracking-wide">Loot-<span class="text-brand-yellow">E</span></span>
    </a>

    <!-- Menu Navigasi (Desktop) -->
    <div class="hidden md:flex gap-8 text-sm font-medium text-gray-300">
        <a href="<?= $_hrefHome ?>" class="<?= _navLandingClass('home', $_activePage) ?>">Home</a>
        <a href="<?= $_hrefProd ?>" class="<?= _navLandingClass('categories', $_activePage) ?>">Products</a>
        <a href="<?= $_hrefAbout ?>" class="<?= _navLandingClass('about', $_activePage) ?>">About</a>
        <a href="help.php" class="<?= _navLandingClass('help', $_activePage) ?>">Help</a>
    </div>

    <!-- Login / Register & Toggle Mobile -->
    <div class="flex items-center gap-2 sm:gap-3">
        <a href="login.php"
            class="hidden md:inline-flex text-xs bg-brand-yellow text-brand-dark px-4 py-2 rounded-lg font-bold hover:bg-yellow-500 transition shadow-sm">
            Login
        </a>
        <a href="register.php"
            class="hidden md:inline-flex text-xs border border-gray-500 text-gray-300 hover:border-brand-yellow hover:text-brand-yellow px-4 py-2 rounded-lg font-medium transition">
            Register
        </a>

        <button type="button" id="navbar-mobile-toggle"
            class="md:hidden flex items-center justify-center w-10 h-10 rounded-lg text-2xl text-gray-300 hover:text-white hover:bg-white/10 active:scale-95 transition focus:outline-none"
            aria-label="Buka menu navigasi" aria-expanded="false">
            <i class="ph ph-list" id="navbar-mobile-icon"></i>
        </button>
    </div>
</nav>

<!-- Menu Navigasi Mobile Dropdown -->
<div id="navbar-mobile-menu" class="hidden md:hidden bg-brand-dark text-white px-6 pb-5 pt-3 border-t border-gray-700/60 shadow-lg">
    <div class="flex flex-col gap-2 text-sm font-medium">
        <a href="<?= $_hrefHome ?>" class="<?= _navLandingClass('home', $_activePage) ?> py-2 px-2 rounded-lg hover:bg-white/5">Home</a>
        <a href="<?= $_hrefProd ?>" class="<?= _navLandingClass('categories', $_activePage) ?> py-2 px-2 rounded-lg hover:bg-white/5">Products</a>
        <a href="<?= $_hrefAbout ?>" class="<?= _navLandingClass('about', $_activePage) ?> py-2 px-2 rounded-lg hover:bg-white/5">About</a>
        <a href="help.php" class="<?= _navLandingClass('help', $_activePage) ?> py-2 px-2 rounded-lg hover:bg-white/5">Help</a>
        <div class="pt-3 mt-1 border-t border-gray-700/50 flex gap-2">
            <a href="login.php" class="text-xs text-center flex-1 bg-brand-yellow text-brand-dark py-2.5 rounded-lg font-bold hover:brightness-95 transition">Login</a>
            <a href="register.php" class="text-xs text-center flex-1 border border-gray-500 py-2.5 rounded-lg text-gray-300 hover:text-white transition">Register</a>
        </div>
    </div>
</div>

<script>
    (function() {
        const toggleBtn = document.getElementById('navbar-mobile-toggle');
        const mobileMenu = document.getElementById('navbar-mobile-menu');
        const toggleIcon = document.getElementById('navbar-mobile-icon');
        if (toggleBtn && mobileMenu) {
            toggleBtn.addEventListener('click', function() {
                const isHidden = mobileMenu.classList.toggle('hidden');
                toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                if (toggleIcon) toggleIcon.className = isHidden ? 'ph ph-list' : 'ph ph-x';
            });
        }
    })();
</script>

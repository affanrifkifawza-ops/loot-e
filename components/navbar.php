<?php

/**
 * components/navbar.php - Komponen Navbar Loot-E
 *
 * Cara pakai:
 *   $navActivePage = 'home' | 'categories' | 'landing'; // opsional, default ''
 *   require_once 'components/navbar.php';
 *
 * Variabel yang digunakan (diambil dari scope pemanggil):
 *   $currentUser  - array user (dari getCurrentUser()) atau null
 *   $navActivePage - string halaman aktif untuk highlight nav link
 */

// Ambil variabel dari scope pemanggil
$_navUser       = $currentUser ?? null;
$_activePage    = $navActivePage ?? '';

/**
 * Helper: tentukan class link navbar
 * Link aktif → kuning & semibold | tidak aktif → abu hover kuning
 */
function _navLinkClass(string $page, string $active): string
{
    return $page === $active
        ? 'text-brand-yellow font-semibold transition'
        : 'hover:text-brand-yellow transition';
}
?>
<nav class="bg-brand-dark text-white py-3 px-6 md:px-12 flex justify-between items-center sticky top-0 z-50 shadow-md">

    <!-- Logo -->
    <div class="flex items-center gap-2">
        <a href="<?= $_navUser ? 'index.php' : 'landing.php' ?>" class="flex items-center gap-2">
            <img src="assets/logo.png" alt="Logo Loot-E" class="h-8 w-8 rounded-full object-cover shadow-sm" />
            <span class="font-bold text-xl tracking-wide">Loot-<span class="text-brand-yellow">E</span></span>
        </a>
    </div>

    <!-- Menu Navigasi -->
    <div class="hidden md:flex gap-8 text-sm font-medium text-gray-300">
        <a href="<?= $_navUser ? 'index.php' : 'landing.php' ?>"
            class="<?= _navLinkClass('home', $_activePage) ?>">Home</a>
        <a href="categories.php"
            class="<?= _navLinkClass('categories', $_activePage) ?>">Categories</a>
        <a href="landing.php"
            class="<?= _navLinkClass('about', $_activePage) ?>">About</a>
        <a href="#" class="hover:text-brand-yellow transition">Help</a>
    </div>

    <!-- Aksi Kanan: Ikon + User / Login -->
    <div class="flex items-center gap-4">
        <?php if ($_navUser): ?>
            <!-- Ikon Notifikasi & Keranjang (hanya saat login) -->
            <i class="ph ph-bell text-xl cursor-pointer hover:text-brand-yellow transition" title="Notifikasi"></i>
            <i class="ph ph-shopping-cart text-xl cursor-pointer hover:text-brand-yellow transition" title="Keranjang"></i>

            <div class="flex items-center gap-3 border-l border-gray-600 pl-4">
                <!-- Avatar inisial -->
                <div class="w-8 h-8 rounded-full bg-brand-yellow text-brand-dark flex items-center justify-center font-bold text-xs uppercase shadow-sm">
                    <?= htmlspecialchars(strtoupper(substr($_navUser['nama'], 0, 2))) ?>
                </div>
                <!-- Nama user -->
                <div class="hidden sm:flex flex-col text-left">
                    <span class="text-xs font-bold text-white leading-tight">
                        Halo, <?= htmlspecialchars($_navUser['nama']) ?>!
                    </span>
                </div>
                <!-- Tombol Keluar -->
                <a href="auth.php?action=logout"
                    class="text-xs bg-red-500/20 text-red-300 hover:bg-red-500 hover:text-white px-2.5 py-1.5 rounded-lg font-medium transition ml-1"
                    title="Keluar dari akun">
                    Keluar
                </a>
            </div>
        <?php else: ?>
            <a href="login.php"
                class="text-xs bg-brand-yellow text-brand-dark px-4 py-2 rounded-lg font-bold hover:bg-yellow-500 transition shadow-sm">
                Login
            </a>
            <a href="register.php"
                class="text-xs border border-gray-500 text-gray-300 hover:border-brand-yellow hover:text-brand-yellow px-4 py-2 rounded-lg font-medium transition hidden sm:inline-block">
                Register
            </a>
        <?php endif; ?>
    </div>

</nav>
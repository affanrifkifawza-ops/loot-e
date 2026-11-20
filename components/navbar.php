<?php
// Navbar utama untuk halaman aplikasi (setelah login)
$_navUser        = $currentUser ?? null;
$_navSearchValue = htmlspecialchars($navSearchQuery ?? '');
$_navInitials    = $_navUser ? htmlspecialchars(strtoupper(substr($_navUser['nama'], 0, 2))) : '';
$_navName        = $_navUser ? htmlspecialchars($_navUser['nama']) : '';
?>
<nav class="bg-brand-dark text-white sticky top-0 z-50 shadow-md">
    <div class="py-3 px-4 sm:px-6 md:px-12 flex justify-between items-center gap-4 lg:grid lg:grid-cols-[1fr_minmax(0,36rem)_1fr]">

        <!-- Logo -->
        <a href="<?= $_navUser ? 'index.php' : 'landingpage.php' ?>" class="flex items-center gap-2 shrink-0 justify-self-start">
            <img src="assets/logo.png" alt="Logo Loot-E" class="h-8 w-8 rounded-full object-cover shadow-sm" />
            <span class="font-bold text-xl tracking-wide">Loot-<span class="text-brand-yellow">E</span></span>
        </a>

        <!-- Search Bar (Desktop) -->
        <form action="search.php" method="GET"
            onsubmit="if(!this.q.value.trim()){ this.q.focus(); return false; }"
            class="hidden md:block relative w-full max-w-xl mx-auto">
            <input type="text" name="q" required value="<?= $_navSearchValue ?>"
                placeholder="Cari barang atau kategori..."
                class="w-full bg-white border border-gray-300 rounded-full pl-5 pr-11 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/40 transition" />
            <button type="submit"
                class="absolute right-1.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-brand-yellow text-white flex items-center justify-center hover:brightness-95 transition shadow-xs"
                aria-label="Cari produk">
                <i class="ph ph-arrow-right text-xs font-bold text-white"></i>
            </button>
        </form>

        <!-- Aksi Kanan: Ikon + Dropdown Profil -->
        <div class="flex items-center gap-1.5 sm:gap-3 shrink-0 justify-self-end">
            <?php if ($_navUser): ?>
                <button type="button"
                    class="flex items-center justify-center w-9 h-9 rounded-lg hover:bg-white/10 text-gray-300 hover:text-brand-yellow transition"
                    title="Notifikasi" aria-label="Notifikasi">
                    <i class="ph ph-bell text-xl"></i>
                </button>
                <a href="cart.php"
                    class="flex items-center justify-center w-9 h-9 rounded-lg hover:bg-white/10 <?= ($navActivePage ?? '') === 'cart' ? 'text-brand-yellow' : 'text-gray-300 hover:text-brand-yellow' ?> transition"
                    title="Keranjang" aria-label="Keranjang belanja">
                    <i class="ph ph-shopping-cart text-xl"></i>
                </a>

                <!-- Dropdown Profil User (Desktop & Mobile) -->
                <div class="relative md:border-l md:border-gray-600 md:pl-4" id="navbar-profile-wrapper">
                    <button type="button" id="navbar-profile-toggle"
                        class="flex items-center gap-2.5 rounded-full md:rounded-lg p-0.5 md:px-2 md:py-1 hover:bg-white/10 transition focus:outline-none"
                        aria-expanded="false" aria-haspopup="true">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-brand-yellow text-brand-dark flex items-center justify-center font-bold text-xs uppercase shadow-sm shrink-0">
                            <?= $_navInitials ?>
                        </div>
                        <span class="hidden md:inline text-xs font-bold text-white leading-tight">
                            Halo, <?= $_navName ?>!
                        </span>
                        <i id="navbar-profile-caret" class="ph ph-caret-down hidden md:inline text-xs text-gray-400 transition-transform duration-150"></i>
                    </button>

                    <!-- Menu Dropdown -->
                    <div id="navbar-profile-menu"
                        class="hidden absolute right-0 mt-2 w-56 bg-white text-gray-800 rounded-xl shadow-xl border border-gray-100 p-1.5 z-50"
                        role="menu" aria-labelledby="navbar-profile-toggle">
                        <!-- Header Profil (Placeholder menuju halaman profil) -->
                        <button type="button" role="menuitem"
                            class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-gray-50 transition text-left group">
                            <div class="w-8 h-8 rounded-full bg-brand-yellow text-brand-dark flex items-center justify-center font-bold text-xs uppercase shrink-0">
                                <?= $_navInitials ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-gray-900 truncate"><?= $_navName ?></p>
                                <p class="text-[11px] text-gray-500 group-hover:text-gray-700 transition">Lihat profil</p>
                            </div>
                            <i class="ph ph-caret-right text-xs text-gray-400 group-hover:text-gray-700 group-hover:translate-x-0.5 transition shrink-0"></i>
                        </button>

                        <div class="border-t border-gray-100 my-1.5"></div>

                        <button type="button" role="menuitem"
                            class="w-full text-left px-2.5 py-2 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2.5 transition">
                            <i class="ph ph-storefront text-base text-gray-500"></i>
                            Toko Saya
                        </button>
                        <button type="button" role="menuitem"
                            class="w-full text-left px-2.5 py-2 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2.5 transition">
                            <i class="ph ph-gear text-base text-gray-500"></i>
                            Settings
                        </button>

                        <div class="border-t border-gray-100 my-1.5"></div>

                        <a href="auth.php?action=logout" role="menuitem"
                            class="w-full px-2.5 py-2 rounded-lg text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2.5 transition">
                            <i class="ph ph-sign-out text-base"></i>
                            Keluar
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php"
                    class="text-xs bg-brand-yellow text-brand-dark px-3.5 py-2 rounded-lg font-bold hover:bg-yellow-500 transition shadow-sm">
                    Login
                </a>
                <a href="register.php"
                    class="text-xs border border-gray-500 text-gray-300 hover:border-brand-yellow hover:text-brand-yellow px-3.5 py-2 rounded-lg font-medium transition">
                    Register
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Bar Baris Kedua (Mobile) -->
    <div class="md:hidden px-4 pb-3">
        <form action="search.php" method="GET"
            onsubmit="if(!this.q.value.trim()){ this.q.focus(); return false; }"
            class="relative w-full">
            <input type="text" name="q" required value="<?= $_navSearchValue ?>"
                placeholder="Cari barang atau kategori..."
                class="w-full bg-white border border-gray-300 rounded-full pl-4 pr-11 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/40 transition" />
            <button type="submit"
                class="absolute right-1.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-brand-yellow text-white flex items-center justify-center hover:brightness-95 transition shadow-xs"
                aria-label="Cari produk">
                <i class="ph ph-arrow-right text-xs font-bold text-white"></i>
            </button>
        </form>
    </div>
</nav>

<script>
    (function() {
        const wrapper = document.getElementById('navbar-profile-wrapper');
        const toggleBtn = document.getElementById('navbar-profile-toggle');
        const menu = document.getElementById('navbar-profile-menu');
        const caret = document.getElementById('navbar-profile-caret');
        if (!wrapper || !toggleBtn || !menu) return;

        function closeMenu() {
            menu.classList.add('hidden');
            toggleBtn.setAttribute('aria-expanded', 'false');
            if (caret) caret.classList.remove('rotate-180');
        }

        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = menu.classList.toggle('hidden');
            toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            if (caret) caret.classList.toggle('rotate-180', !isHidden);
        });

        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) closeMenu();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeMenu();
        });
    })();
</script>
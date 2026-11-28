<?php
// Navbar utama untuk halaman aplikasi (setelah login)
// Desktop (>= 768px): Floating Pill melayang di tengah, mengecil jadi kapsul logo saat scroll, melebar saat hover/fokus.
// Mobile (< 768px): Sticky penuh agar nyaman tanpa hover.
// Halaman dengan hero full-bleed (index.php, categories.php) dapat mengatur $navOverlayHero = true sebelum require_once.
$_navUser        = $currentUser ?? null;
$_navSearchValue = htmlspecialchars($navSearchQuery ?? '', ENT_QUOTES, 'UTF-8');
$_navInitials    = $_navUser ? htmlspecialchars(strtoupper(substr($_navUser['nama'], 0, 2)), ENT_QUOTES, 'UTF-8') : '';
$_navName        = $_navUser ? htmlspecialchars($_navUser['nama'], ENT_QUOTES, 'UTF-8') : '';
$_navOverlayHero = !empty($navOverlayHero);
?>
<style>
    .loote-scroll-progress {
        transform-origin: left center;
        transform: scaleX(0);
        will-change: transform;
    }

    @media (min-width: 768px) {
        .loote-floating-pill {
            width: min(calc(100vw - 2rem), 820px);
            padding: 6px 14px;
            transition: width 360ms cubic-bezier(0.22, 1, 0.36, 1),
                padding 320ms cubic-bezier(0.22, 1, 0.36, 1),
                border-color 260ms ease,
                box-shadow 260ms ease;
        }

        .loote-nav-collapsible {
            max-width: 700px;
            opacity: 1;
            margin-left: 18px;
            overflow: hidden;
            transition: max-width 340ms cubic-bezier(0.22, 1, 0.36, 1),
                opacity 220ms ease 90ms,
                margin-left 300ms ease;
        }

        .loote-floating-pill.is-compact {
            width: 146px;
            padding: 6px 14px;
            border-color: rgba(255, 201, 20, 0.38);
            box-shadow: 0 12px 28px -8px rgba(0, 0, 0, 0.5);
        }

        .loote-floating-pill.is-compact .loote-nav-collapsible {
            max-width: 0;
            opacity: 0;
            margin-left: 0;
            pointer-events: none;
            transition: max-width 300ms cubic-bezier(0.22, 1, 0.36, 1),
                opacity 110ms ease,
                margin-left 260ms ease;
        }

        .loote-floating-pill.is-compact:hover,
        .loote-floating-pill.is-compact:focus-within,
        .loote-floating-pill.is-compact.is-menu-open {
            width: min(calc(100vw - 2rem), 820px);
            padding: 6px 14px;
            border-color: rgba(255, 255, 255, 0.22);
        }

        .loote-floating-pill.is-compact:hover .loote-nav-collapsible,
        .loote-floating-pill.is-compact:focus-within .loote-nav-collapsible,
        .loote-floating-pill.is-compact.is-menu-open .loote-nav-collapsible {
            max-width: 700px;
            opacity: 1;
            margin-left: 18px;
            pointer-events: auto;
            transition: max-width 360ms cubic-bezier(0.22, 1, 0.36, 1),
                opacity 220ms ease 100ms,
                margin-left 300ms ease;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .loote-floating-pill,
        .loote-nav-collapsible,
        .loote-scroll-progress {
            transition-duration: 0.01ms !important;
        }
    }
</style>

<header class="sticky top-0 z-50 md:fixed md:top-3.5 md:left-0 md:right-0 md:flex md:justify-center md:px-4 md:pointer-events-none">
    <nav id="loote-floating-nav" aria-label="Navigasi Utama"
        class="loote-floating-pill relative bg-brand-dark text-white shadow-md md:pointer-events-auto md:bg-[#272727]/95 md:backdrop-blur-md md:rounded-full md:border md:border-white/15 md:shadow-[0_14px_34px_-10px_rgba(0,0,0,0.45)]">
        <div class="py-2.5 px-4 sm:px-6 md:p-0 flex items-center justify-between md:min-h-[40px]">
            <!-- Logo (selalu tampil, menjadi isi utama kapsul saat scroll di desktop) -->
            <a href="<?= $_navUser ? 'index.php' : 'landingpage.php' ?>" class="flex items-center gap-2 shrink-0 py-0.5">
                <img src="assets/logo.png" alt="Logo Loot-E" width="32" height="32" class="h-8 w-8 rounded-full object-cover shadow-sm shrink-0" />
                <span class="font-bold text-lg sm:text-xl md:text-lg tracking-wide whitespace-nowrap">Loot-<span class="text-brand-yellow">E</span></span>
            </a>

            <!-- Konten Tengah & Kanan (menyusut saat scroll di desktop, melebar saat hover/fokus) -->
            <div class="loote-nav-collapsible flex items-center justify-end md:gap-3 flex-1 min-w-0">
                <!-- Search Bar (Desktop) -->
                <form action="search.php" method="GET"
                    onsubmit="if(!this.q.value.trim()){ this.q.focus(); return false; }"
                    class="hidden md:block relative flex-1 max-w-[390px] md:mx-auto">
                    <label for="navbar-search-desktop" class="sr-only">Cari barang atau kategori</label>
                    <input type="text" id="navbar-search-desktop" name="q" required value="<?= $_navSearchValue ?>"
                        placeholder="Cari barang atau kategori..."
                        class="w-full bg-white/95 border border-gray-300 rounded-full pl-4 pr-10 py-1.5 text-xs sm:text-sm text-gray-800 placeholder-gray-500 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/40 transition" />
                    <button type="submit"
                        class="absolute right-1 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-brand-yellow text-[#272727] flex items-center justify-center hover:brightness-95 transition shadow-xs cursor-pointer"
                        aria-label="Cari produk">
                        <i class="ph-bold ph-arrow-right text-xs" aria-hidden="true"></i>
                    </button>
                </form>

                <!-- Aksi Kanan: Ikon + Trigger Profil -->
                <div class="flex items-center gap-1 sm:gap-1.5 md:gap-1 shrink-0">
                    <?php if ($_navUser): ?>
                        <button type="button"
                            class="flex items-center justify-center w-10 h-10 md:w-9 md:h-9 rounded-full hover:bg-white/10 text-gray-300 hover:text-brand-yellow transition cursor-pointer shrink-0"
                            title="Notifikasi" aria-label="Notifikasi">
                            <i class="ph ph-bell text-xl md:text-[19px]" aria-hidden="true"></i>
                        </button>
                        <a href="cart.php"
                            class="flex items-center justify-center w-10 h-10 md:w-9 md:h-9 rounded-full hover:bg-white/10 <?= ($navActivePage ?? '') === 'cart' ? 'text-brand-yellow' : 'text-gray-300 hover:text-brand-yellow' ?> transition shrink-0"
                            title="Keranjang" aria-label="Keranjang belanja">
                            <i class="ph ph-shopping-cart text-xl md:text-[19px]" aria-hidden="true"></i>
                        </a>

                        <!-- Trigger Dropdown Profil User -->
                        <div class="md:border-l md:border-gray-600/80 md:pl-2 md:ml-1 shrink-0" id="navbar-profile-wrapper">
                            <button type="button" id="navbar-profile-toggle"
                                class="flex items-center gap-2 rounded-full p-0.5 md:px-1.5 md:py-1 hover:bg-white/10 transition focus:outline-none cursor-pointer"
                                aria-expanded="false" aria-haspopup="true" aria-controls="navbar-profile-menu">
                                <div class="w-8 h-8 rounded-full bg-brand-yellow text-brand-dark flex items-center justify-center font-semibold text-xs uppercase shadow-sm shrink-0">
                                    <?= $_navInitials ?>
                                </div>
                                <span class="hidden lg:inline text-xs font-medium text-white leading-tight whitespace-nowrap max-w-[125px] truncate">
                                    Halo, <?= $_navName ?>!
                                </span>
                                <i id="navbar-profile-caret" class="ph ph-caret-down hidden lg:inline text-xs text-gray-400 transition-transform duration-150" aria-hidden="true"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <a href="login.php"
                            class="text-xs bg-brand-yellow text-brand-dark px-3.5 py-2 rounded-full font-semibold hover:brightness-95 transition shadow-sm whitespace-nowrap">
                            Login
                        </a>
                        <a href="register.php"
                            class="text-xs border border-gray-500 text-gray-200 hover:border-brand-yellow hover:text-brand-yellow px-3.5 py-2 rounded-full font-medium transition whitespace-nowrap">
                            Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Search Bar Baris Kedua (Mobile < 768px) -->
        <div class="md:hidden px-4 pb-3">
            <form action="search.php" method="GET"
                onsubmit="if(!this.q.value.trim()){ this.q.focus(); return false; }"
                class="relative w-full">
                <label for="navbar-search-mobile" class="sr-only">Cari barang atau kategori</label>
                <input type="text" id="navbar-search-mobile" name="q" required value="<?= $_navSearchValue ?>"
                    placeholder="Cari barang atau kategori..."
                    class="w-full bg-white border border-gray-300 rounded-full pl-4 pr-12 py-2 text-sm text-gray-800 placeholder-gray-500 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/40 transition" />
                <button type="submit"
                    class="absolute right-1 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-brand-yellow text-[#272727] flex items-center justify-center hover:brightness-95 transition shadow-xs cursor-pointer"
                    aria-label="Cari produk">
                    <i class="ph-bold ph-arrow-right text-xs" aria-hidden="true"></i>
                </button>
            </form>
        </div>

        <?php if ($_navUser): ?>
            <!-- Menu Dropdown Profil (berada di luar .loote-nav-collapsible agar tidak terpotong overflow) -->
            <div id="navbar-profile-menu"
                class="hidden absolute right-4 md:right-3 top-full mt-2 w-56 bg-white text-gray-800 rounded-xl shadow-xl border border-gray-100 p-1.5 z-50"
                role="menu" aria-labelledby="navbar-profile-toggle">
                <a href="profile.php" role="menuitem"
                    class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-gray-50 transition text-left group">
                    <div class="w-8 h-8 rounded-full bg-brand-yellow text-brand-dark flex items-center justify-center font-semibold text-xs uppercase shrink-0">
                        <?= $_navInitials ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-gray-900 truncate"><?= $_navName ?></p>
                        <p class="text-[11px] text-gray-500 group-hover:text-gray-700 transition">Lihat profil</p>
                    </div>
                    <i class="ph ph-caret-right text-xs text-gray-400 group-hover:text-gray-700 group-hover:translate-x-0.5 transition shrink-0" aria-hidden="true"></i>
                </a>

                <div class="border-t border-gray-100 my-1.5"></div>

                <a href="dashboard_seller.php" role="menuitem"
                    class="w-full text-left px-2.5 py-2 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2.5 transition">
                    <i class="ph ph-storefront text-base text-gray-500" aria-hidden="true"></i>
                    Toko Saya
                </a>
                <a href="pesanan.php" role="menuitem"
                    class="w-full text-left px-2.5 py-2 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2.5 transition">
                    <i class="ph ph-receipt text-base text-gray-500" aria-hidden="true"></i>
                    Pesanan Saya
                </a>

                <div class="border-t border-gray-100 my-1.5"></div>

                <a href="auth.php?action=logout" role="menuitem"
                    class="w-full px-2.5 py-2 rounded-lg text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2.5 transition">
                    <i class="ph ph-sign-out text-base" aria-hidden="true"></i>
                    Keluar
                </a>
            </div>
        <?php endif; ?>

        <!-- Garis Progress Scroll Tipis -->
        <div class="pointer-events-none absolute bottom-0 left-0 right-0 md:left-6 md:right-6 h-[2px] bg-white/10 overflow-hidden md:rounded-full" aria-hidden="true">
            <div id="loote-scroll-progress-bar" class="loote-scroll-progress h-full w-full bg-brand-yellow"></div>
        </div>
    </nav>
</header>

<?php if (!$_navOverlayHero): ?>
    <!-- Spacer otomatis pada desktop agar konten halaman non-hero tidak tertutup floating pill -->
    <div class="hidden md:block h-[76px] shrink-0" aria-hidden="true"></div>
<?php endif; ?>

<script>
    (function() {
        const nav = document.getElementById('loote-floating-nav');
        const progressBar = document.getElementById('loote-scroll-progress-bar');
        const toggleBtn = document.getElementById('navbar-profile-toggle');
        const menu = document.getElementById('navbar-profile-menu');
        const caret = document.getElementById('navbar-profile-caret');
        const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Dropdown profil
        if (toggleBtn && menu) {
            function closeMenu() {
                menu.classList.add('hidden');
                toggleBtn.setAttribute('aria-expanded', 'false');
                if (caret) caret.classList.remove('rotate-180');
                if (nav) nav.classList.remove('is-menu-open');
            }

            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isHidden = menu.classList.toggle('hidden');
                toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                if (caret) caret.classList.toggle('rotate-180', !isHidden);
                if (nav) nav.classList.toggle('is-menu-open', !isHidden);
            });

            document.addEventListener('click', function(e) {
                if (!menu.contains(e.target) && !toggleBtn.contains(e.target)) {
                    closeMenu();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeMenu();
            });
        }

        // State compact floating pill (desktop) + scroll progress hairline
        let scrollTicking = false;
        function syncNavOnScroll() {
            scrollTicking = false;
            const y = window.scrollY || window.pageYOffset || 0;
            if (nav) {
                nav.classList.toggle('is-compact', y > 80);
            }
            if (progressBar && !reducedMotion) {
                const maxScroll = Math.max(1, document.documentElement.scrollHeight - (window.innerHeight || 800));
                const progress = Math.min(1, Math.max(0, y / maxScroll));
                progressBar.style.transform = 'scaleX(' + progress.toFixed(4) + ')';
            }
        }

        function requestNavSync() {
            if (!scrollTicking) {
                scrollTicking = true;
                requestAnimationFrame(syncNavOnScroll);
            }
        }

        window.addEventListener('scroll', requestNavSync, { passive: true });
        window.addEventListener('resize', requestNavSync, { passive: true });
        syncNavOnScroll();
    })();
</script>

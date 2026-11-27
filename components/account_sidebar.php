<?php
// Sidebar navigasi akun bersama untuk halaman area profil pengguna (DRY)
$_sidebarActive = $accountActiveMenu ?? 'profil';
$_sidebarUser   = $accountSidebarUser ?? [
    'nama'        => 'Pengguna',
    'email'       => '',
    'is_verified' => 0,
];
$_editProfileHref = $_sidebarActive === 'profil' ? '#informasi-profil' : 'profile.php#informasi-profil';
$_isVerifiedUser  = isset($_sidebarUser['is_verified'])
    ? !empty($_sidebarUser['is_verified'])
    : (function_exists('isVerifiedSeller') && isVerifiedSeller());

$_tokoSubmenuItems = [
    ['id' => 'toko_dashboard',  'label' => 'Dashboard',        'href' => 'dashboard_seller.php'],
    ['id' => 'toko_katalog',    'label' => 'Katalog',          'href' => 'katalog.php'],
    ['id' => 'toko_nego',       'label' => 'Pengajuan Nego',   'href' => null],
    ['id' => 'toko_barter',     'label' => 'Pengajuan Barter', 'href' => null],
    ['id' => 'toko_pengiriman', 'label' => 'Pengiriman Saya',  'href' => null],
];

$_tokoSubmenuIds = array_column($_tokoSubmenuItems, 'id');
$_isTokoActive   = $_sidebarActive === 'toko' || in_array($_sidebarActive, $_tokoSubmenuIds, true);

$_sidebarMenuItems = [
    ['id' => 'profil',     'label' => 'Profil Saya',     'icon' => 'ph-user',              'href' => 'profile.php'],
    ['id' => 'pesanan',    'label' => 'Pesanan Saya',    'icon' => 'ph-package',           'href' => 'pesanan.php'],
<<<<<<< HEAD
    ['id' => 'negosiasi',  'label' => 'Negosiasi Saya',  'icon' => 'ph-chat-circle-text',  'href' => null],
    ['id' => 'barter',     'label' => 'Barter Saya',     'icon' => 'ph-arrows-left-right', 'href' => null],
    ['id' => 'toko',       'label' => 'Toko Saya',       'icon' => 'ph-storefront',        'href' => null, 'is_dropdown' => true],
=======
    ['id' => 'negosiasi',  'label' => 'Negosiasi Saya',  'icon' => 'ph-chat-circle-text',  'href' => 'nego_buyer.php'],
    ['id' => 'barter',     'label' => 'Barter Saya',     'icon' => 'ph-arrows-left-right', 'href' => 'barter_buyer.php'],
    ['id' => 'toko',       'label' => 'Toko Saya',       'icon' => 'ph-storefront',        'href' => null],
>>>>>>> origin/develop
    ['id' => 'notifikasi', 'label' => 'Notifikasi',      'icon' => 'ph-bell',              'href' => null],
    ['id' => 'alamat',     'label' => 'Alamat',          'icon' => 'ph-map-pin',           'href' => 'alamat_buyer.php'],
    ['id' => 'keamanan',   'label' => 'Keamanan Akun',   'icon' => 'ph-shield-star',       'href' => null],
];

if (!$_isVerifiedUser) {
    $_sidebarMenuItems[] = ['id' => 'verifikasi', 'label' => 'Verifikasi Akun', 'icon' => 'ph-shield-check', 'href' => null];
}
?>
<style>
    .sidebar-submenu {
        display: grid;
        grid-template-rows: 0fr;
        transition: grid-template-rows 280ms cubic-bezier(0.16, 1, 0.3, 1);
    }

    .sidebar-submenu[data-open="true"] {
        grid-template-rows: 1fr;
    }

    .sidebar-submenu-inner {
        overflow: hidden;
    }

    .sidebar-submenu-item {
        opacity: 0;
        transform: translateY(-6px);
        transition: opacity 150ms ease, transform 150ms ease, color 150ms ease, background-color 150ms ease;
    }

    .sidebar-submenu[data-open="true"] .sidebar-submenu-item {
        opacity: 1;
        transform: translateY(0);
        transition:
            opacity 240ms cubic-bezier(0.16, 1, 0.3, 1) var(--stagger, 0ms),
            transform 260ms cubic-bezier(0.16, 1, 0.3, 1) var(--stagger, 0ms),
            color 150ms ease,
            background-color 150ms ease;
    }

    .sidebar-toko-caret {
        transition: transform 260ms cubic-bezier(0.16, 1, 0.3, 1);
    }

    #sidebar-toko-toggle[aria-expanded="true"] .sidebar-toko-caret {
        transform: rotate(180deg);
    }

    @media (prefers-reduced-motion: reduce) {
        .sidebar-submenu,
        .sidebar-submenu-item,
        .sidebar-toko-caret {
            transition: none !important;
            transform: none !important;
        }
    }
</style>
<!-- Sidebar Navigasi Akun -->
<aside class="w-full lg:w-[252px] shrink-0 bg-white border border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
    <!-- Ringkasan Profil Pengguna -->
    <div class="px-5 pt-5 pb-4 flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-full bg-[#373737] flex items-center justify-center shrink-0 overflow-hidden">
            <i class="ph-fill ph-user text-[26px] text-[#CFCFCF]" aria-hidden="true"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p id="sidebar-profile-name" class="text-sm font-bold text-[#272727] leading-snug truncate">
                <?= htmlspecialchars((string)($_sidebarUser['nama'] ?? 'Pengguna'), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p id="sidebar-profile-email" class="text-[11px] text-gray-500 truncate mt-0.5">
                <?= htmlspecialchars((string)($_sidebarUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <a href="<?= htmlspecialchars($_editProfileHref, ENT_QUOTES, 'UTF-8') ?>"
                class="inline-flex items-center gap-1 text-[11px] text-gray-500 hover:text-[#272727] mt-1 transition">
                <i class="ph ph-pencil-simple text-xs" aria-hidden="true"></i>
                <span>Edit Profile</span>
            </a>
        </div>
    </div>

    <div class="border-t border-gray-200/90 mx-4"></div>

    <!-- Daftar Menu Sidebar -->
    <nav aria-label="Menu Profil Pengguna" class="px-3 py-3 space-y-0.5">
        <?php foreach ($_sidebarMenuItems as $item): ?>
            <?php if (!empty($item['is_dropdown'])): ?>
                <!-- Menu Dropdown Toko Saya -->
                <div>
                    <?php if ($_isTokoActive): ?>
                        <button type="button" id="sidebar-toko-toggle"
                            aria-expanded="true"
                            aria-controls="sidebar-toko-submenu"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 text-[13px] text-left transition cursor-pointer bg-brand-yellow text-[#272727] font-semibold">
                            <i class="ph <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> text-[17px] text-[#272727] shrink-0" aria-hidden="true"></i>
                            <span class="flex-1"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <i class="ph ph-caret-down sidebar-toko-caret text-xs text-[#272727] shrink-0" aria-hidden="true"></i>
                        </button>
                    <?php else: ?>
                        <a href="dashboard_seller.php"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 text-[13px] text-left transition text-[#374151] hover:bg-gray-50 hover:text-[#272727] font-medium">
                            <i class="ph <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> text-[17px] text-[#374151] shrink-0" aria-hidden="true"></i>
                            <span class="flex-1"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <i class="ph ph-caret-down sidebar-toko-caret text-xs text-gray-400 shrink-0" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>

                    <div id="sidebar-toko-submenu" class="sidebar-submenu" data-open="<?= $_isTokoActive ? 'true' : 'false' ?>" data-toko-active="<?= $_isTokoActive ? 'true' : 'false' ?>">
                        <div class="sidebar-submenu-inner">
                            <div class="my-1 ml-[21px] border-l border-gray-200/90 space-y-0.5 py-0.5">
                                <?php foreach ($_tokoSubmenuItems as $subIdx => $sub): ?>
                                    <?php
                                    $isSubActive = $sub['id'] === $_sidebarActive;
                                    $staggerMs   = $subIdx * 35;
                                    ?>
                                    <?php if ($isSubActive): ?>
                                        <a href="<?= htmlspecialchars((string)($sub['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" aria-current="page"
                                            style="--stagger: <?= $staggerMs ?>ms;"
                                            class="sidebar-submenu-item relative block pl-5 pr-3 py-2 text-[13px] font-bold text-[#C99700] bg-[#FFFBEB]/80 border-l-2 border-brand-yellow -ml-[1px]">
                                            <?= htmlspecialchars($sub['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php elseif (!empty($sub['href'])): ?>
                                        <a href="<?= htmlspecialchars((string)$sub['href'], ENT_QUOTES, 'UTF-8') ?>"
                                            style="--stagger: <?= $staggerMs ?>ms;"
                                            class="sidebar-submenu-item block pl-5 pr-3 py-2 text-[13px] font-medium text-[#4B5563] hover:text-[#272727] hover:bg-gray-50">
                                            <?= htmlspecialchars($sub['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    <?php else: ?>
                                        <button type="button"
                                            style="--stagger: <?= $staggerMs ?>ms;"
                                            data-demo-toast="Fitur <?= htmlspecialchars($sub['label'], ENT_QUOTES, 'UTF-8') ?> belum tersedia saat ini (mode demo)."
                                            class="sidebar-submenu-item w-full text-left pl-5 pr-3 py-2 text-[13px] font-medium text-[#4B5563] hover:text-[#272727] hover:bg-gray-50 cursor-pointer">
                                            <?= htmlspecialchars($sub['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php $isActive = $item['id'] === $_sidebarActive; ?>
                <?php if ($isActive): ?>
                    <a href="<?= htmlspecialchars((string)($item['href'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" aria-current="page"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 bg-brand-yellow text-[#272727] text-[13px] font-semibold transition">
                        <i class="ph <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> text-[17px] shrink-0" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php elseif (!empty($item['href'])): ?>
                    <a href="<?= htmlspecialchars((string)$item['href'], ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 text-[#374151] hover:bg-gray-50 hover:text-[#272727] text-[13px] font-medium text-left transition">
                        <i class="ph <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> text-[17px] text-[#374151] shrink-0" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php else: ?>
                    <button type="button"
                        data-demo-toast="Fitur <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?> belum tersedia saat ini (mode demo)."
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 text-[#374151] hover:bg-gray-50 hover:text-[#272727] text-[13px] font-medium text-left transition cursor-pointer">
                        <i class="ph <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> text-[17px] text-[#374151] shrink-0" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="border-t border-gray-200/90 mx-4"></div>

    <!-- Logout -->
    <div class="px-3 py-3">
        <a href="auth.php?action=logout"
            class="w-full flex items-center gap-3 px-3.5 py-2.5 text-red-600 hover:bg-red-50 hover:text-red-700 text-[13px] font-semibold text-left transition">
            <i class="ph ph-sign-out text-[17px] text-red-600 shrink-0" aria-hidden="true"></i>
            <span>Logout</span>
        </a>
    </div>
<<<<<<< HEAD
</aside>

<script>
    (function () {
        const toggleBtn = document.getElementById('sidebar-toko-toggle');
        const submenu = document.getElementById('sidebar-toko-submenu');
        if (!toggleBtn || !submenu || toggleBtn.dataset.bound === '1') return;
        toggleBtn.dataset.bound = '1';

        // Mainkan animasi turun sekali saat baru masuk ke area Toko Saya dari halaman lain
        const tokoPages = ['dashboard_seller.php', 'katalog.php'];
        const cameFromToko = tokoPages.some(function (page) {
            return document.referrer.indexOf(page) !== -1;
        });
        const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (submenu.getAttribute('data-toko-active') === 'true' && !cameFromToko && !reducedMotion) {
            submenu.style.transition = 'none';
            submenu.setAttribute('data-open', 'false');
            toggleBtn.setAttribute('aria-expanded', 'false');
            void submenu.offsetHeight;
            submenu.style.transition = '';
            requestAnimationFrame(function () {
                submenu.setAttribute('data-open', 'true');
                toggleBtn.setAttribute('aria-expanded', 'true');
            });
        }

        toggleBtn.addEventListener('click', function () {
            const isOpen = submenu.getAttribute('data-open') === 'true';
            const nextOpen = !isOpen;
            submenu.setAttribute('data-open', nextOpen ? 'true' : 'false');
            toggleBtn.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
        });
    })();
</script>
=======
</aside>
>>>>>>> origin/develop

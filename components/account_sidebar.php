<?php
// Sidebar navigasi akun bersama untuk halaman area profil pengguna (DRY)
$_sidebarActive = $accountActiveMenu ?? 'profil';
$_sidebarUser   = $accountSidebarUser ?? [
    'nama'  => 'Pengguna',
    'email' => '',
];
$_editProfileHref = $_sidebarActive === 'profil' ? '#informasi-profil' : 'profile.php#informasi-profil';

$_sidebarMenuItems = [
    ['id' => 'profil',     'label' => 'Profil Saya',     'icon' => 'ph-user',              'href' => 'profile.php'],
    ['id' => 'pesanan',    'label' => 'Pesanan Saya',    'icon' => 'ph-package',           'href' => 'pesanan.php'],
    ['id' => 'negosiasi',  'label' => 'Negosiasi Saya',  'icon' => 'ph-chat-circle-text',  'href' => null],
    ['id' => 'barter',     'label' => 'Barter Saya',     'icon' => 'ph-arrows-left-right', 'href' => null],
    ['id' => 'toko',       'label' => 'Toko Saya',       'icon' => 'ph-storefront',        'href' => null],
    ['id' => 'notifikasi', 'label' => 'Notifikasi',      'icon' => 'ph-bell',              'href' => null],
    ['id' => 'alamat',     'label' => 'Alamat',          'icon' => 'ph-map-pin',           'href' => null],
    ['id' => 'keamanan',   'label' => 'Keamanan Akun',   'icon' => 'ph-shield-star',       'href' => null],
    ['id' => 'verifikasi', 'label' => 'Verifikasi Akun', 'icon' => 'ph-shield-check',      'href' => null],
];
?>
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
</aside>

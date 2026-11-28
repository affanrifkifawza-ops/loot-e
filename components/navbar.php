<?php
// Navbar utama untuk halaman aplikasi (setelah login)
// Desktop (>= 768px): Floating Pill melayang di tengah, mengecil jadi kapsul logo saat scroll, melebar saat hover/fokus.
// Mobile (< 768px): Sticky penuh agar nyaman tanpa hover.
// Halaman dengan hero full-bleed (index.php, categories.php) dapat mengatur $navOverlayHero = true sebelum require_once.
$_navUser        = $currentUser ?? null;
$_navSearchValue = htmlspecialchars($navSearchQuery ?? '');
$_navInitials    = $_navUser ? htmlspecialchars(strtoupper(substr($_navUser['nama'], 0, 2))) : '';
$_navName        = $_navUser ? htmlspecialchars($_navUser['nama']) : '';

// Data Pop-up Notifikasi (PDO dengan fallback mockup jika data kosong)
$_navNotifOrders  = [];
$_navNotifUpdates = [];

if ($_navUser && function_exists('getDbConnection')) {
    try {
        $_navNotifDb = getDbConnection();
        if ($_navNotifDb instanceof PDO) {
            $_stmtNotifOrders = $_navNotifDb->prepare("
                SELECT ps.id, ps.tipe_transaksi, ps.total_harga, ps.status_pesanan, ps.created_at,
                       dp.id_produk, dp.kuantitas, dp.harga_satuan,
                       pr.nama_produk, kt.nama_kategori
                FROM pesanan ps
                LEFT JOIN detail_pesanan dp ON dp.id = (
                    SELECT MIN(dp2.id) FROM detail_pesanan dp2 WHERE dp2.id_pesanan = ps.id
                )
                LEFT JOIN produk pr ON pr.id = dp.id_produk
                LEFT JOIN kategori kt ON kt.id = pr.id_kategori
                WHERE ps.id_pembeli = ?
                ORDER BY ps.created_at DESC, ps.id DESC
                LIMIT 5
            ");
            $_stmtNotifOrders->execute([(int)$_navUser['id']]);
            $_rowsNotifOrders = $_stmtNotifOrders->fetchAll();

            $_notifStatusMap = [
                'menunggu_pembayaran' => ['Menunggu Pembayaran', 'cart.php?status=belum_bayar'],
                'dibayar'             => ['Diproses', 'cart.php?status=diproses'],
                'dikirim'             => ['Dikirim', 'cart.php?status=dikirim'],
                'selesai'             => ['Selesai', 'cart.php?status=ulasan'],
                'dibatalkan'          => ['Dibatalkan', 'cart.php'],
            ];
            $_notifEmojiMap = [
                'gpu'        => '🖥️',
                'cpu'        => '⚙️',
                'ram'        => '⚙️',
                'laptop'     => '💻',
                'phone'      => '📱',
                'smartphone' => '📱',
                'monitor'    => '🖥️',
                'headphones' => '🎧',
            ];

            foreach ($_rowsNotifOrders as $_rowOrder) {
                $_prefix = match ($_rowOrder['tipe_transaksi'] ?? '') {
                    'hasil_nego'   => 'NGS-',
                    'hasil_barter' => 'BTR-',
                    default        => 'ORD-',
                };
                $_orderCode   = $_prefix . str_pad((string)$_rowOrder['id'], 4, '0', STR_PAD_LEFT);
                $_statusInfo  = $_notifStatusMap[$_rowOrder['status_pesanan'] ?? 'menunggu_pembayaran'] ?? ['Menunggu Pembayaran', 'cart.php?status=belum_bayar'];
                $_katKey      = strtolower(trim((string)($_rowOrder['nama_kategori'] ?? '')));
                $_qty         = max(1, (int)($_rowOrder['kuantitas'] ?? 1));
                $_harga       = (int)($_rowOrder['harga_satuan'] ?? $_rowOrder['total_harga'] ?? 0);
                $_produkLabel = (string)($_rowOrder['nama_produk'] ?? 'Komponen Elektronik');
                if (!empty($_rowOrder['nama_kategori'])) {
                    $_produkLabel .= ' • ' . $_rowOrder['nama_kategori'];
                }

                $_navNotifOrders[] = [
                    'title'      => 'Pesanan #' . $_orderCode,
                    'product'    => $_produkLabel,
                    'qty_price'  => $_qty . 'x Rp' . number_format($_harga, 0, ',', '.'),
                    'status'     => $_statusInfo[0],
                    'image_icon' => $_notifEmojiMap[$_katKey] ?? '📦',
                    'link'       => $_statusInfo[1],
                ];
            }

            $_stmtNotifNego = $_navNotifDb->prepare("
                SELECT n.id, n.id_pembeli, n.id_produk, n.harga_deal, n.status, n.created_at,
                       pr.nama_produk
                FROM negosiasi n
                JOIN produk pr ON pr.id = n.id_produk
                WHERE n.id_pembeli = ? OR pr.id_penjual = ?
                ORDER BY n.created_at DESC, n.id DESC
                LIMIT 5
            ");
            $_stmtNotifNego->execute([(int)$_navUser['id'], (int)$_navUser['id']]);
            $_rowsNotifNego = $_stmtNotifNego->fetchAll();

            $_negoDescMap = [
                'pending'       => 'Penawaran harga menunggu tinjauan penjual.',
                'counter_offer' => 'Penjual memberikan penawaran balik untuk produk ini.',
                'diterima'      => 'Penawaran harga disetujui! Segera lanjutkan ke checkout.',
                'ditolak'       => 'Penawaran harga belum disepakati.',
                'expired'       => 'Batas waktu negosiasi telah habis.',
            ];

            foreach ($_rowsNotifNego as $_rowNego) {
                $_desc = $_negoDescMap[$_rowNego['status'] ?? 'pending'] ?? 'Pembaruan status negosiasi.';
                if (!empty($_rowNego['harga_deal'])) {
                    $_desc .= ' Harga deal: Rp' . number_format((int)$_rowNego['harga_deal'], 0, ',', '.') . '.';
                }
                $_isBuyer = (int)$_rowNego['id_pembeli'] === (int)$_navUser['id'];
                $_navNotifUpdates[] = [
                    'title' => 'Negosiasi: ' . $_rowNego['nama_produk'],
                    'desc'  => $_desc,
                    'time'  => date('d M Y, H:i', strtotime($_rowNego['created_at'])) . ' WIB',
                    'link'  => $_isBuyer ? ('status_negosiasi.php?id=' . (int)$_rowNego['id']) : ('detail_produk.php?id=' . (int)$_rowNego['id_produk']),
                ];
            }
        }
    } catch (PDOException $e) {
    }
}

if ($_navUser && empty($_navNotifUpdates)) {
    $_navNotifUpdates = [
        [
            'title'       => 'LOOT-E Official Store',
            'is_official' => true,
            'desc'        => '',
            'time'        => '',
            'link'        => 'index.php',
        ],
    ];
}
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
                        <!-- Pop-up Notifikasi -->
                        <div class="relative" id="navbar-notif-wrapper">
                            <button type="button" id="navbar-notif-toggle"
                                class="flex items-center justify-center w-10 h-10 md:w-9 md:h-9 rounded-full hover:bg-white/10 text-gray-300 hover:text-brand-yellow transition cursor-pointer shrink-0"
                                title="Notifikasi" aria-label="Notifikasi" aria-expanded="false" aria-haspopup="true">
                                <i class="ph ph-bell text-xl md:text-[19px]" aria-hidden="true"></i>
                            </button>

                            <div id="navbar-notif-menu"
                                class="hidden fixed inset-x-3 top-16 sm:inset-auto sm:absolute sm:right-0 sm:top-full sm:mt-2 sm:w-[400px] bg-white text-gray-800 rounded-lg shadow-2xl border border-gray-200/80 overflow-hidden z-50"
                                role="dialog" aria-label="Daftar Notifikasi">
                                <!-- Header & Tab Control -->
                                <div class="pt-4 px-5 pb-0 bg-white">
                                    <h2 class="text-lg font-extrabold text-[#272727] tracking-tight mb-3">Notifikasi</h2>
                                    <div class="flex border-b border-gray-200 -mx-5 px-5">
                                        <button type="button" id="notif-popup-tab-status"
                                            class="flex-1 py-2.5 text-center text-sm font-bold border-b-2 border-brand-yellow text-brand-yellow focus:outline-none transition-all cursor-pointer">
                                            Status
                                        </button>
                                        <button type="button" id="notif-popup-tab-update"
                                            class="flex-1 py-2.5 text-center text-sm font-bold border-b-2 border-transparent text-gray-700 hover:text-[#272727] focus:outline-none transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                            Update
                                            <span id="notif-popup-update-badge" class="bg-gray-100 text-gray-700 text-[11px] font-bold w-5 h-5 inline-flex items-center justify-center rounded-full transition-colors">
                                                <?= count($_navNotifUpdates) ?>
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Isi Scrollable Pop-up -->
                                <div class="max-h-[75vh] overflow-y-auto bg-white">
                                    <!-- TAB 1: STATUS -->
                                    <div id="notif-popup-panel-status">
                                        <section class="bg-white px-5 pt-5 pb-6">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-base font-extrabold text-[#272727]">Pesanan Saya</h3>
                                                <a href="cart.php?status=belum_bayar" class="text-xs font-bold text-brand-yellow hover:underline cursor-pointer">Lihat Semua</a>
                                            </div>
                                            <?php if (empty($_navNotifOrders)): ?>
                                                <div class="text-center py-4">
                                                    <div class="w-14 h-14 rounded-full bg-[#F4F5F7] flex items-center justify-center mx-auto mb-3.5">
                                                        <i class="ph ph-shopping-bag text-2xl text-[#272727]" aria-hidden="true"></i>
                                                    </div>
                                                    <h4 class="text-sm font-bold text-[#272727] mb-1">Belum Ada Pesanan</h4>
                                                    <p class="text-[11px] text-gray-500 leading-relaxed max-w-[260px] mx-auto mb-4">
                                                        Kamu belum memiliki pesanan. Yuk, temukan produk elektronik yang kamu butuhkan!
                                                    </p>
                                                    <a href="search.php" class="inline-block bg-brand-yellow hover:brightness-95 text-[#272727] text-xs font-bold px-5 py-2.5 rounded-[2px] transition shadow-2xs">
                                                        Mulai Belanja
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div class="space-y-3">
                                                    <?php foreach ($_navNotifOrders as $_order): ?>
                                                        <a href="<?= htmlspecialchars($_order['link']) ?>" class="block group">
                                                            <div class="flex items-start justify-between gap-2.5 p-2.5 rounded-lg border border-gray-100 hover:border-brand-yellow hover:bg-amber-50/40 transition-all cursor-pointer">
                                                                <div class="w-11 h-11 bg-[#F4F5F7] rounded-lg flex-shrink-0 flex items-center justify-center text-xl border border-gray-200/70">
                                                                    <?= $_order['image_icon'] ?>
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <h4 class="text-xs font-bold text-[#272727] truncate"><?= htmlspecialchars($_order['title']) ?></h4>
                                                                    <p class="text-[11px] text-gray-500 truncate mt-0.5"><?= htmlspecialchars($_order['product']) ?></p>
                                                                    <p class="text-[11px] font-bold text-gray-800 mt-0.5"><?= htmlspecialchars($_order['qty_price']) ?></p>
                                                                </div>
                                                                <span class="text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 shrink-0">
                                                                    <?= htmlspecialchars($_order['status']) ?>
                                                                </span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </section>

                                        <!-- Pemisah Abu-abu -->
                                        <div class="h-2.5 bg-[#F4F5F7] border-y border-gray-100"></div>

                                        <!-- Aktivitas Toko -->
                                        <section class="bg-white px-5 py-5">
                                            <h3 class="text-base font-extrabold text-[#272727] mb-1.5">Aktivitas Toko</h3>
                                            <p class="text-xs text-gray-500 leading-relaxed mb-4">Cek pesanan yang masuk dan perkembangan tokomu secara rutin di satu tempat</p>
                                            <a href="index.php" class="block w-full bg-brand-yellow hover:brightness-95 text-[#272727] font-bold text-xs sm:text-sm py-3 px-4 rounded-[2px] transition text-center cursor-pointer shadow-2xs">
                                                Masuk ke Toko Saya
                                            </a>
                                        </section>
                                    </div>

                                    <!-- TAB 2: UPDATE -->
                                    <div id="notif-popup-panel-update" class="hidden bg-white">
                                        <section class="bg-white min-h-[300px]">
                                            <?php foreach ($_navNotifUpdates as $_update): ?>
                                                <a href="<?= htmlspecialchars($_update['link']) ?>" class="flex items-start gap-3.5 px-5 py-4 border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer">
                                                    <img src="assets/logo.png" alt="Loot-E" class="w-12 h-12 rounded-full object-cover shrink-0" />
                                                    <div class="flex-1 min-w-0 pt-0.5">
                                                        <h4 class="text-sm font-bold text-[#272727] leading-snug">
                                                            <?php if (!empty($_update['is_official'])): ?>
                                                                LOOT-<span class="text-brand-yellow">E</span> Official Store
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($_update['title']) ?>
                                                            <?php endif; ?>
                                                        </h4>
                                                        <?php if (!empty($_update['desc'])): ?>
                                                            <p class="text-xs text-gray-500 leading-relaxed mt-1"><?= htmlspecialchars($_update['desc']) ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($_update['time'])): ?>
                                                            <span class="block text-[10px] text-gray-400 font-medium mt-1"><?= htmlspecialchars($_update['time']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </section>
                                    </div>
                                </div>
                            </div>
                        </div>
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
        const profileWrapper = document.getElementById('navbar-profile-wrapper');
        const profileToggle  = document.getElementById('navbar-profile-toggle');
        const profileMenu    = document.getElementById('navbar-profile-menu');
        const profileCaret   = document.getElementById('navbar-profile-caret');

        const notifWrapper   = document.getElementById('navbar-notif-wrapper');
        const notifToggle    = document.getElementById('navbar-notif-toggle');
        const notifMenu      = document.getElementById('navbar-notif-menu');
        const tabStatusBtn   = document.getElementById('notif-popup-tab-status');
        const tabUpdateBtn   = document.getElementById('notif-popup-tab-update');
        const updateBadge    = document.getElementById('notif-popup-update-badge');
        const panelStatus    = document.getElementById('notif-popup-panel-status');
        const panelUpdate    = document.getElementById('notif-popup-panel-update');

        function closeProfileMenu() {
            if (!profileMenu || !profileToggle) return;
            profileMenu.classList.add('hidden');
            profileToggle.setAttribute('aria-expanded', 'false');
            if (profileCaret) profileCaret.classList.remove('rotate-180');
        }

        function closeNotifMenu() {
            if (!notifMenu || !notifToggle) return;
            notifMenu.classList.add('hidden');
            notifToggle.setAttribute('aria-expanded', 'false');
            notifToggle.classList.remove('text-brand-yellow');
            notifToggle.classList.add('text-gray-300');
        }

        if (profileWrapper && profileToggle && profileMenu) {
            profileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                closeNotifMenu();
                const isHidden = profileMenu.classList.toggle('hidden');
                profileToggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                if (profileCaret) profileCaret.classList.toggle('rotate-180', !isHidden);
            });
        }

        if (notifWrapper && notifToggle && notifMenu) {
            notifToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                closeProfileMenu();
                const isHidden = notifMenu.classList.toggle('hidden');
                notifToggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                notifToggle.classList.toggle('text-brand-yellow', !isHidden);
                notifToggle.classList.toggle('text-gray-300', isHidden);
            });
        }

        function switchPopupNotifTab(tabName) {
            if (!panelStatus || !panelUpdate || !tabStatusBtn || !tabUpdateBtn) return;
            const isStatus = tabName === 'status';
            panelStatus.classList.toggle('hidden', !isStatus);
            panelUpdate.classList.toggle('hidden', isStatus);

            tabStatusBtn.classList.toggle('border-brand-yellow', isStatus);
            tabStatusBtn.classList.toggle('text-brand-yellow', isStatus);
            tabStatusBtn.classList.toggle('border-transparent', !isStatus);
            tabStatusBtn.classList.toggle('text-gray-700', !isStatus);

            tabUpdateBtn.classList.toggle('border-brand-yellow', !isStatus);
            tabUpdateBtn.classList.toggle('text-brand-yellow', !isStatus);
            tabUpdateBtn.classList.toggle('border-transparent', isStatus);
            tabUpdateBtn.classList.toggle('text-gray-700', isStatus);

            if (updateBadge) {
                updateBadge.classList.toggle('bg-brand-yellow', !isStatus);
                updateBadge.classList.toggle('text-white', !isStatus);
                updateBadge.classList.toggle('bg-gray-100', isStatus);
                updateBadge.classList.toggle('text-gray-700', isStatus);
            }
        }

        if (tabStatusBtn) {
            tabStatusBtn.addEventListener('click', function() {
                switchPopupNotifTab('status');
            });
        }
        if (tabUpdateBtn) {
            tabUpdateBtn.addEventListener('click', function() {
                switchPopupNotifTab('update');
            });
        }

        document.addEventListener('click', function(e) {
            if (profileWrapper && !profileWrapper.contains(e.target)) closeProfileMenu();
            if (notifWrapper && !notifWrapper.contains(e.target)) closeNotifMenu();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfileMenu();
                closeNotifMenu();
            }
        });
    })();
</script>

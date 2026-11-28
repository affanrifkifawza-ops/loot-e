<?php
/**
 * Halaman Notifikasi - Project Loot-E
 * File: notifikasi.php
 * Tampilan dashboard notifikasi e-commerce (Full Clickable Links)
 */

// 1. Inisialisasi Autentikasi, Koneksi Database (PDO) & Helper
require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php?redirect=notifikasi.php');
    exit;
}

$currentUser = getCurrentUser();
$navActivePage = 'notifikasi';

$database_connected = false;
$orders = [];
$updates = [];

// Check URL Parameter tab
$current_tab = isset($_GET['tab']) && $_GET['tab'] === 'update' ? 'update' : 'status';

// Query dari database (PDO) jika koneksi tersedia
$db = function_exists('getDbConnection') ? getDbConnection() : ($koneksi ?? null);
if ($db instanceof PDO) {
    try {
        // Query daftar pesanan user
        $stmtOrders = $db->prepare("
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
        $stmtOrders->execute([(int)$currentUser['id']]);
        $rowsOrders = $stmtOrders->fetchAll();

        $statusMap = [
            'menunggu_pembayaran' => ['Menunggu Pembayaran', 'pesanan.php'],
            'dibayar'             => ['Diproses', 'pesanan.php'],
            'dikirim'             => ['Dikirim', 'pesanan.php'],
            'selesai'             => ['Selesai', 'pesanan.php'],
            'dibatalkan'          => ['Dibatalkan', 'pesanan.php'],
        ];
        $emojiMap = [
            'gpu'        => '🖥️',
            'cpu'        => '⚙️',
            'ram'        => '⚙️',
            'laptop'     => '💻',
            'phone'      => '📱',
            'smartphone' => '📱',
            'monitor'    => '🖥️',
            'headphones' => '🎧',
        ];

        foreach ($rowsOrders as $row) {
            $prefix = match ($row['tipe_transaksi'] ?? '') {
                'hasil_nego'   => 'NGS-',
                'hasil_barter' => 'BTR-',
                default        => 'ORD-',
            };
            $orderCode = $prefix . str_pad((string)$row['id'], 4, '0', STR_PAD_LEFT);
            $statusInfo = $statusMap[$row['status_pesanan'] ?? 'menunggu_pembayaran'] ?? ['Menunggu Pembayaran', 'pesanan.php'];
            $katKey = strtolower(trim((string)($row['nama_kategori'] ?? '')));
            $qty = max(1, (int)($row['kuantitas'] ?? 1));
            $harga = (int)($row['harga_satuan'] ?? $row['total_harga'] ?? 0);
            $produkLabel = (string)($row['nama_produk'] ?? 'Komponen Elektronik');
            if (!empty($row['nama_kategori'])) {
                $produkLabel .= ' • ' . $row['nama_kategori'];
            }

            $orders[] = [
                'id'          => $orderCode,
                'product_id'  => (int)($row['id_produk'] ?? 0),
                'title'       => 'Pesanan #' . $orderCode,
                'product'     => $produkLabel,
                'qty_price'   => $qty . 'x Rp' . number_format($harga, 0, ',', '.'),
                'status'      => $statusInfo[0],
                'image_icon'  => $emojiMap[$katKey] ?? '📦',
                'link'        => $statusInfo[1],
            ];
        }

        // Query daftar notifikasi negosiasi user (sebagai pembeli maupun penjual)
        $stmtNego = $db->prepare("
            SELECT n.id, n.id_pembeli, n.id_produk, n.harga_deal, n.status, n.created_at,
                   pr.nama_produk
            FROM negosiasi n
            JOIN produk pr ON pr.id = n.id_produk
            WHERE n.id_pembeli = ? OR pr.id_penjual = ?
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT 5
        ");
        $stmtNego->execute([(int)$currentUser['id'], (int)$currentUser['id']]);
        $rowsNego = $stmtNego->fetchAll();

        $negoStatusDesc = [
            'pending'       => 'Penawaran harga menunggu tinjauan penjual.',
            'counter_offer' => 'Penjual memberikan penawaran balik untuk produk ini.',
            'diterima'      => 'Penawaran harga disetujui! Segera lanjutkan ke checkout.',
            'ditolak'       => 'Penawaran harga belum disepakati.',
            'expired'       => 'Batas waktu negosiasi telah habis.',
            'gagal'         => 'Negosiasi ditutup otomatis karena stok tidak tersedia.',
        ];

        foreach ($rowsNego as $row) {
            $desc = $negoStatusDesc[$row['status'] ?? 'pending'] ?? 'Pembaruan status negosiasi.';
            if (!empty($row['harga_deal'])) {
                $desc .= ' Harga deal: Rp' . number_format((int)$row['harga_deal'], 0, ',', '.') . '.';
            }
            $isBuyer = (int)$row['id_pembeli'] === (int)$currentUser['id'];
            $updates[] = [
                'title' => 'Negosiasi: ' . $row['nama_produk'],
                'desc'  => $desc,
                'time'  => date('d M Y, H:i', strtotime($row['created_at'])) . ' WIB',
                'link'  => $isBuyer ? ('status_negosiasi.php?id=' . (int)$row['id']) : ('detail_produk.php?id=' . (int)$row['id_produk']),
            ];
        }

        $database_connected = true;
    } catch (PDOException $e) {
        $database_connected = false;
    }
}

// Data Tab Update Notification (Fallback mockup jika belum ada notifikasi dari database)
if (empty($updates)) {
    $updates = [
        [
            'title'       => 'LOOT-E Official Store',
            'is_official' => true,
            'desc'        => '',
            'time'        => '',
            'link'        => 'index.php',
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Loot-E Store</title>
    
    <!-- CSS External jika ada -->
    <?php if (file_exists(__DIR__ . '/assets/css/style.css')): ?>
        <link rel="stylesheet" href="assets/css/style.css">
    <?php endif; ?>

    <!-- Phosphor Icons & Tailwind CSS via CDN -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fffbeb', 100: '#fef3c7', 400: '#fbbf24',
                            500: '#FFC400', 600: '#d97706', 700: '#b45309',
                            yellow: '#FFC914', dark: '#272727'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f1f5f9; }
        .bg-brand-yellow { background-color: #FFC400; }
        .hover-bg-brand-yellow:hover { background-color: #e6b000; }
        .border-brand-yellow { border-color: #FFC400; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col justify-between">

    <!-- Navbar Global dari components/ -->
    <?php 
    if (file_exists(__DIR__ . '/components/navbar.php')) {
        include __DIR__ . '/components/navbar.php';
    }
    ?>

    <!-- Main Content Container -->
    <div class="container mx-auto max-w-md my-0 sm:my-6 px-0 sm:px-4 flex-1">
        
        <div class="bg-white min-h-screen sm:min-h-0 sm:rounded-2xl sm:shadow-xl overflow-hidden border border-gray-100 flex flex-col">
            
            <!-- Header Notifikasi & Tab Control -->
            <header class="pt-5 px-5 pb-0 bg-white sticky top-0 z-20">
                <h1 class="text-xl font-extrabold text-[#272727] tracking-tight mb-4">Notifikasi</h1>
                
                <!-- Navigasi Tab Status vs Update -->
                <div class="flex border-b border-gray-200 -mx-5 px-5">
                    <button type="button" onclick="switchNotificationTab('status')" id="btn-tab-status" 
                            class="flex-1 py-3 text-center text-sm font-bold border-b-2 <?= $current_tab === 'status' ? 'border-brand-yellow text-brand-yellow' : 'border-transparent text-gray-700' ?> focus:outline-none transition-all cursor-pointer">
                        Status
                    </button>
                    <button type="button" onclick="switchNotificationTab('update')" id="btn-tab-update" 
                            class="flex-1 py-3 text-center text-sm font-bold border-b-2 <?= $current_tab === 'update' ? 'border-brand-yellow text-brand-yellow' : 'border-transparent text-gray-700' ?> focus:outline-none transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        Update
                        <span id="badge-tab-update" class="<?= $current_tab === 'update' ? 'bg-brand-yellow text-white' : 'bg-gray-100 text-gray-700' ?> text-xs font-bold w-5 h-5 inline-flex items-center justify-center rounded-full transition-colors">
                            <?= count($updates) ?>
                        </span>
                    </button>
                </div>
            </header>

            <!-- ================= TAB CONTENT 1: STATUS ================= -->
            <div id="panel-status" class="flex-1 bg-white <?= $current_tab === 'update' ? 'hidden' : '' ?>">
                
                <!-- SEKSI PESANAN SAYA -->
                <section class="bg-white px-5 pt-5 pb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-base font-extrabold text-[#272727]">Pesanan Saya</h2>
                        <a href="pesanan.php" class="text-xs font-bold text-brand-yellow hover:underline cursor-pointer">
                            Lihat Semua
                        </a>
                    </div>

                    <?php if (empty($orders)): ?>
                        <div class="text-center py-4">
                            <div class="w-14 h-14 rounded-full bg-[#F4F5F7] flex items-center justify-center mx-auto mb-3.5">
                                <i class="ph ph-shopping-bag text-2xl text-[#272727]" aria-hidden="true"></i>
                            </div>
                            <h3 class="text-sm font-bold text-[#272727] mb-1">Belum Ada Pesanan</h3>
                            <p class="text-[11px] text-gray-500 leading-relaxed max-w-[260px] mx-auto mb-4">
                                Kamu belum memiliki pesanan. Yuk, temukan produk elektronik yang kamu butuhkan!
                            </p>
                            <a href="search.php" class="inline-block bg-brand-yellow hover:brightness-95 text-[#272727] text-xs font-bold px-5 py-2.5 rounded-[2px] transition shadow-2xs">
                                Mulai Belanja
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($orders as $order): ?>
                                <a href="<?= htmlspecialchars($order['link']) ?>" class="block group">
                                    <div class="flex items-start justify-between gap-3 p-2.5 rounded-lg border border-gray-100 hover:border-brand-yellow hover:bg-amber-50/40 transition-all cursor-pointer">
                                        <div class="w-12 h-12 bg-[#F4F5F7] rounded-lg flex-shrink-0 flex items-center justify-center text-2xl border border-gray-200/70">
                                            <?= $order['image_icon'] ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-xs font-bold text-[#272727] truncate"><?= htmlspecialchars($order['title']) ?></h3>
                                            <p class="text-[11px] text-gray-500 truncate mt-0.5"><?= htmlspecialchars($order['product']) ?></p>
                                            <p class="text-[11px] font-bold text-gray-800 mt-0.5"><?= htmlspecialchars($order['qty_price']) ?></p>
                                        </div>
                                        <span class="text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 shrink-0">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Pemisah Abu-abu -->
                <div class="h-2.5 bg-[#F4F5F7] border-y border-gray-100"></div>

                <!-- SEKSI AKTIVITAS TOKO -->
                <section class="bg-white px-5 py-5">
                    <h2 class="text-base font-extrabold text-[#272727] mb-1.5">Aktivitas Toko</h2>
                    <p class="text-xs text-gray-500 leading-relaxed mb-4">
                        Cek pesanan yang masuk dan perkembangan tokomu secara rutin di satu tempat
                    </p>
                    <a href="dashboard_seller.php" class="block w-full bg-brand-yellow hover:brightness-95 text-[#272727] font-bold text-sm py-3 px-4 rounded-[2px] shadow-2xs transition-all text-center cursor-pointer">
                        Masuk ke Toko Saya
                    </a>
                </section>

            </div>

            <!-- ================= TAB CONTENT 2: UPDATE ================= -->
            <div id="panel-update" class="flex-1 bg-white <?= $current_tab === 'update' ? '' : 'hidden' ?>">
                <section class="bg-white min-h-[320px]">
                    <?php foreach ($updates as $update): ?>
                        <a href="<?= htmlspecialchars($update['link']) ?>" class="flex items-start gap-3.5 px-5 py-4 border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer">
                            <img src="assets/logo.png" alt="Loot-E" class="w-12 h-12 rounded-full object-cover shrink-0" />
                            <div class="flex-1 min-w-0 pt-0.5">
                                <h2 class="text-sm font-bold text-[#272727] leading-snug">
                                    <?php if (!empty($update['is_official'])): ?>
                                        LOOT-<span class="text-brand-yellow">E</span> Official Store
                                    <?php else: ?>
                                        <?= htmlspecialchars($update['title']) ?>
                                    <?php endif; ?>
                                </h2>
                                <?php if (!empty($update['desc'])): ?>
                                    <p class="text-xs text-gray-500 leading-relaxed mt-1"><?= htmlspecialchars($update['desc']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($update['time'])): ?>
                                    <span class="block text-[10px] text-gray-400 font-medium mt-1"><?= htmlspecialchars($update['time']) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </section>
            </div>

        </div>

    </div>

    <!-- Footer Global dari components/ -->
    <?php 
    if (file_exists(__DIR__ . '/components/footer.php')) {
        include __DIR__ . '/components/footer.php';
    }
    ?>

    <!-- JavaScript Interaktif Switch Tab -->
    <script>
        function switchNotificationTab(tabName) {
            const panelStatus = document.getElementById('panel-status');
            const panelUpdate = document.getElementById('panel-update');
            const btnStatus = document.getElementById('btn-tab-status');
            const btnUpdate = document.getElementById('btn-tab-update');
            const badgeUpdate = document.getElementById('badge-tab-update');

            if (tabName === 'status') {
                panelStatus.classList.remove('hidden');
                panelUpdate.classList.add('hidden');

                btnStatus.classList.add('border-brand-yellow', 'text-brand-yellow', 'font-bold');
                btnStatus.classList.remove('border-transparent', 'text-gray-700');

                btnUpdate.classList.remove('border-brand-yellow', 'text-brand-yellow', 'font-bold');
                btnUpdate.classList.add('border-transparent', 'text-gray-700');

                if (badgeUpdate) {
                    badgeUpdate.classList.remove('bg-brand-yellow', 'text-white');
                    badgeUpdate.classList.add('bg-gray-100', 'text-gray-700');
                }
            } else {
                panelStatus.classList.add('hidden');
                panelUpdate.classList.remove('hidden');

                btnUpdate.classList.add('border-brand-yellow', 'text-brand-yellow', 'font-bold');
                btnUpdate.classList.remove('border-transparent', 'text-gray-700');

                btnStatus.classList.remove('border-brand-yellow', 'text-brand-yellow', 'font-bold');
                btnStatus.classList.add('border-transparent', 'text-gray-700');

                if (badgeUpdate) {
                    badgeUpdate.classList.add('bg-brand-yellow', 'text-white');
                    badgeUpdate.classList.remove('bg-gray-100', 'text-gray-700');
                }
            }
        }
    </script>
</body>
</html>
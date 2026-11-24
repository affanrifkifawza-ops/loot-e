<?php
require_once "auth.php";

if (!isLoggedIn()) {
    header("Location: login.php?redirect=pesanan.php");
    exit;
}

$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

$stmtUser = $db->prepare("
    SELECT id, nama, email, peran, is_verified
    FROM pengguna
    WHERE id = ?
    LIMIT 1
");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();

if (!$userRow) {
    header("Location: auth.php?action=logout");
    exit;
}

// Sinkronkan session dengan data terbaru dari database (termasuk is_verified / BR-04)
$_SESSION['nama']        = $userRow['nama'];
$_SESSION['email']       = $userRow['email'];
$_SESSION['peran']       = $userRow['peran'];
$_SESSION['is_verified'] = (int)$userRow['is_verified'];

// CSRF token untuk aksi pesanan (Batalkan Pesanan / Beli Lagi)
if (empty($_SESSION['csrf_pesanan'])) {
    $_SESSION['csrf_pesanan'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_pesanan'];

$flash = null;
if (!empty($_SESSION['pesanan_flash']) && is_array($_SESSION['pesanan_flash'])) {
    $flash = $_SESSION['pesanan_flash'];
    unset($_SESSION['pesanan_flash']);
}

// Proses aksi POST (Batalkan Pesanan & Beli Lagi)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $action      = (string)($_POST['action'] ?? '');
    $orderId     = (int)($_POST['order_id'] ?? 0);

    if (!hash_equals($csrfToken, $postedToken)) {
        $_SESSION['pesanan_flash'] = [
            'type'    => 'error',
            'message' => 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.',
        ];
        header("Location: pesanan.php");
        exit;
    }

    if ($action === 'batalkan_pesanan' && $orderId > 0) {
        try {
            $db->beginTransaction();

            $stmtOrder = $db->prepare("
                SELECT id, status_pesanan
                FROM pesanan
                WHERE id = ? AND id_pembeli = ?
                FOR UPDATE
            ");
            $stmtOrder->execute([$orderId, $userId]);
            $targetOrder = $stmtOrder->fetch();

            if (!$targetOrder) {
                $db->rollBack();
                $_SESSION['pesanan_flash'] = [
                    'type'    => 'error',
                    'message' => 'Pesanan tidak ditemukan.',
                ];
            } elseif ($targetOrder['status_pesanan'] !== 'menunggu_pembayaran') {
                $db->rollBack();
                $_SESSION['pesanan_flash'] = [
                    'type'    => 'error',
                    'message' => 'Pesanan hanya dapat dibatalkan saat berstatus Menunggu Pembayaran.',
                ];
            } else {
                $stmtCancel = $db->prepare("
                    UPDATE pesanan
                    SET status_pesanan = 'dibatalkan'
                    WHERE id = ? AND id_pembeli = ?
                ");
                $stmtCancel->execute([$orderId, $userId]);

                // Kembalikan stok & buka kunci produk jika sebelumnya terkunci/habis (BR-02)
                $stmtItems = $db->prepare("
                    SELECT id_produk, kuantitas
                    FROM detail_pesanan
                    WHERE id_pesanan = ?
                ");
                $stmtItems->execute([$orderId]);
                $detailRows = $stmtItems->fetchAll();

                $stmtRestore = $db->prepare("
                    UPDATE produk
                    SET stok = stok + ?,
                        status_produk = CASE
                            WHEN status_produk IN ('terkunci', 'habis') THEN 'tersedia'
                            ELSE status_produk
                        END
                    WHERE id = ?
                ");
                foreach ($detailRows as $dRow) {
                    $stmtRestore->execute([(int)$dRow['kuantitas'], (int)$dRow['id_produk']]);
                }

                $db->commit();
                $_SESSION['pesanan_flash'] = [
                    'type'    => 'success',
                    'message' => 'Pesanan berhasil dibatalkan.',
                ];
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['pesanan_flash'] = [
                'type'    => 'error',
                'message' => 'Gagal membatalkan pesanan. Silakan coba lagi.',
            ];
        }

        header("Location: pesanan.php");
        exit;
    }

    if ($action === 'beli_lagi' && $orderId > 0) {
        try {
            $stmtItems = $db->prepare("
                SELECT dp.id_produk, dp.kuantitas, p.id_penjual, p.stok, p.status_produk
                FROM detail_pesanan dp
                JOIN pesanan ps ON dp.id_pesanan = ps.id
                JOIN produk p ON dp.id_produk = p.id
                WHERE ps.id = ? AND ps.id_pembeli = ?
            ");
            $stmtItems->execute([$orderId, $userId]);
            $orderProducts = $stmtItems->fetchAll();

            $addedCount = 0;
            $stmtCart = $db->prepare("
                INSERT INTO keranjang (id_pembeli, id_produk, kuantitas)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE kuantitas = LEAST(kuantitas + VALUES(kuantitas), ?)
            ");

            foreach ($orderProducts as $prod) {
                $stok = (int)$prod['stok'];
                // BR-01: cegah pembelian produk milik sendiri; pastikan stok tersedia
                if (
                    $prod['status_produk'] === 'tersedia'
                    && $stok >= 1
                    && (int)$prod['id_penjual'] !== $userId
                ) {
                    $qtyAdd = min(max(1, (int)$prod['kuantitas']), $stok);
                    $stmtCart->execute([$userId, (int)$prod['id_produk'], $qtyAdd, $stok]);
                    $addedCount++;
                }
            }

            if ($addedCount > 0) {
                header("Location: cart.php");
                exit;
            }

            $_SESSION['pesanan_flash'] = [
                'type'    => 'error',
                'message' => 'Produk pada pesanan ini sudah tidak tersedia atau stok habis.',
            ];
        } catch (PDOException $e) {
            $_SESSION['pesanan_flash'] = [
                'type'    => 'error',
                'message' => 'Gagal menambahkan produk ke keranjang. Silakan coba lagi.',
            ];
        }

        header("Location: pesanan.php");
        exit;
    }
}

$currentUser = getCurrentUser();
$navActivePage = 'pesanan';

$accountActiveMenu = 'pesanan';
$accountSidebarUser = [
    'nama'  => (string)$userRow['nama'],
    'email' => (string)$userRow['email'],
];
$isVerified = (int)$userRow['is_verified'] === 1;

// Tab status pesanan
$statusTabs = [
    ['id' => 'semua',               'label' => 'Semua'],
    ['id' => 'menunggu_pembayaran', 'label' => 'Menunggu Pembayaran'],
    ['id' => 'diproses',            'label' => 'Diproses'],
    ['id' => 'dikirim',             'label' => 'Dikirim'],
    ['id' => 'selesai',             'label' => 'Selesai'],
    ['id' => 'dibatalkan',          'label' => 'Dibatalkan'],
];

function formatTanggalPesanan(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    static $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y, H:i', $ts);
}

$statusMetaMap = [
    'menunggu_pembayaran' => [
        'tab_key' => 'menunggu_pembayaran',
        'label'   => 'Menunggu Pembayaran',
        'icon'    => 'ph-clock',
        'class'   => 'text-[#C86A2B]',
    ],
    'dibayar' => [
        'tab_key' => 'diproses',
        'label'   => 'Sedang Diproses',
        'icon'    => 'ph-package',
        'class'   => 'text-[#B45309]',
    ],
    'dikirim' => [
        'tab_key' => 'dikirim',
        'label'   => 'Sedang Dikirim',
        'icon'    => 'ph-truck',
        'class'   => 'text-[#C86A2B]',
    ],
    'selesai' => [
        'tab_key' => 'selesai',
        'label'   => 'Selesai',
        'icon'    => '',
        'class'   => 'text-[#16A34A]',
    ],
    'dibatalkan' => [
        'tab_key' => 'dibatalkan',
        'label'   => 'Dibatalkan',
        'icon'    => 'ph-x-circle',
        'class'   => 'text-red-600',
    ],
];

// Ambil daftar pesanan user dari database (tanpa N+1 query)
$orders = [];
try {
    $stmtOrders = $db->prepare("
        SELECT id, tipe_transaksi, biaya_layanan, total_harga, metode_bayar,
               alamat_kirim, nomor_resi, status_pesanan, created_at
        FROM pesanan
        WHERE id_pembeli = ?
        ORDER BY created_at DESC, id DESC
    ");
    $stmtOrders->execute([$userId]);
    $orderRows = $stmtOrders->fetchAll();

    if (!empty($orderRows)) {
        $orderIds = array_map(static fn(array $r): int => (int)$r['id'], $orderRows);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $stmtDetails = $db->prepare("
            SELECT dp.id_pesanan, dp.id_produk, dp.kuantitas, dp.harga_satuan,
                   p.nama_produk, p.foto, p.kondisi,
                   k.nama_kategori,
                   u.nama AS nama_penjual
            FROM detail_pesanan dp
            JOIN produk p ON dp.id_produk = p.id
            JOIN kategori k ON p.id_kategori = k.id
            JOIN pengguna u ON p.id_penjual = u.id
            WHERE dp.id_pesanan IN ($placeholders)
            ORDER BY dp.id ASC
        ");
        $stmtDetails->execute($orderIds);
        $detailRows = $stmtDetails->fetchAll();

        $itemsByOrder = [];
        foreach ($detailRows as $d) {
            $itemsByOrder[(int)$d['id_pesanan']][] = $d;
        }

        foreach ($orderRows as $row) {
            $oid = (int)$row['id'];
            $rawItems = $itemsByOrder[$oid] ?? [];
            $ts = strtotime((string)$row['created_at']) ?: time();
            $orderCode = 'LE-' . date('Ymd', $ts) . '-' . str_pad((string)$oid, 3, '0', STR_PAD_LEFT);

            $sellerNames = [];
            $formattedItems = [];
            foreach ($rawItems as $it) {
                $sellerName = trim((string)($it['nama_penjual'] ?? ''));
                if ($sellerName !== '' && !in_array($sellerName, $sellerNames, true)) {
                    $sellerNames[] = $sellerName;
                }
                $formattedItems[] = [
                    'id_produk' => (int)$it['id_produk'],
                    'nama'      => (string)$it['nama_produk'],
                    'kategori'  => (string)($it['nama_kategori'] ?? 'Elektronik'),
                    'kondisi'   => (string)($it['kondisi'] ?? 'Bekas'),
                    'qty'       => (int)$it['kuantitas'],
                    'harga'     => (int)$it['harga_satuan'],
                    'foto'      => $it['foto'] !== null && trim((string)$it['foto']) !== '' ? (string)$it['foto'] : null,
                ];
            }

            $tokoLabel = 'Penjual Loot-E';
            if (count($sellerNames) === 1) {
                $tokoLabel = $sellerNames[0];
            } elseif (count($sellerNames) > 1) {
                $tokoLabel = $sellerNames[0] . ' (+' . (count($sellerNames) - 1) . ' toko)';
            }

            $dbStatus = (string)$row['status_pesanan'];
            $meta = $statusMetaMap[$dbStatus] ?? $statusMetaMap['menunggu_pembayaran'];

            $orders[] = [
                'id'           => $oid,
                'order_code'   => $orderCode,
                'tanggal'      => formatTanggalPesanan((string)$row['created_at']),
                'timestamp'    => $ts,
                'toko'         => $tokoLabel,
                'db_status'    => $dbStatus,
                'status_key'   => $meta['tab_key'],
                'status_label' => $meta['label'],
                'status_icon'  => $meta['icon'],
                'status_class' => $meta['class'],
                'total'        => (int)$row['total_harga'],
                'nomor_resi'   => trim((string)($row['nomor_resi'] ?? '')),
                'items'        => $formattedItems,
            ];
        }
    }
} catch (PDOException $e) {
    $orders = [];
    if ($flash === null) {
        $flash = ['type' => 'error', 'message' => 'Gagal memuat daftar pesanan. Silakan muat ulang halaman.'];
    }
}

$hasAnyOrders = !empty($orders);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pesanan Saya | Loot-E</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ye: '#FFC914',
                        ch: '#272727',
                        brand: {
                            yellow: '#FFC914',
                            dark: '#272727',
                            footer: '#272727',
                            bg: '#F4F4F4'
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        hd: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif']
                    }
                }
            }
        };
    </script>

    <style>
        :root {
            --brand-yellow: #FFC914;
            --brand-dark: #272727;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 2px solid var(--brand-yellow);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="bg-[#F4F4F4] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Header & Navigasi Utama -->
    <?php require_once 'components/navbar.php'; ?>

    <!-- Konten Utama -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 md:px-12 py-8 md:py-10">
        <div class="flex flex-col lg:flex-row items-start gap-6">

            <?php require_once 'components/account_sidebar.php'; ?>

            <!-- Area Konten Utama Pesanan -->
            <section class="flex-1 w-full min-w-0">
                <!-- Judul Halaman -->
                <header>
                    <h1 class="text-2xl sm:text-[26px] font-extrabold text-[#272727] tracking-tight leading-tight">
                        Pesanan Saya
                    </h1>
                    <p class="text-xs sm:text-[13px] text-gray-500 mt-1">
                        Kelola dan pantau semua pesananmu di Loot-E
                    </p>
                </header>

                <!-- Banner Peringatan Belum Terverifikasi -->
                <?php if (!$isVerified): ?>
                    <div id="banner-verifikasi"
                        class="mt-5 bg-[#F5EFD9] px-5 py-4 sm:px-6 sm:py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-11 h-11 rounded-full bg-brand-yellow flex items-center justify-center shrink-0">
                                <i class="ph-bold ph-shield-check text-[22px] text-[#854D0E]" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm font-bold text-[#272727] leading-snug">
                                    Belum Terverifikasi
                                </h2>
                                <p class="text-xs text-gray-600 mt-0.5 leading-relaxed">
                                    Selesaikan verifikasi akun terlebih dahulu untuk mulai menjual produk di Loot-E.
                                </p>
                            </div>
                        </div>
                        <button type="button"
                            data-demo-toast="Halaman Verifikasi Akun belum tersedia saat ini (mode demo)."
                            class="inline-flex items-center justify-center gap-2 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition shrink-0 cursor-pointer self-start sm:self-center">
                            <span>Verifikasi Sekarang</span>
                            <i class="ph ph-arrow-right text-sm font-bold" aria-hidden="true"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Filter Tab Status Pesanan -->
                <div role="tablist" aria-label="Filter status pesanan"
                    class="mt-5 flex flex-wrap items-center gap-2">
                    <?php foreach ($statusTabs as $index => $tab): ?>
                        <?php $isDefaultActive = $index === 0; ?>
                        <button type="button"
                            role="tab"
                            aria-selected="<?= $isDefaultActive ? 'true' : 'false' ?>"
                            data-status-tab="<?= htmlspecialchars($tab['id'], ENT_QUOTES, 'UTF-8') ?>"
                            class="order-status-tab px-4 py-2 text-xs sm:text-[13px] rounded-[2px] border transition cursor-pointer <?= $isDefaultActive ? 'bg-brand-yellow border-brand-yellow text-[#272727] font-semibold' : 'bg-white border-gray-200 text-[#374151] hover:border-gray-300 hover:bg-gray-50 font-medium' ?>">
                            <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Bar Pencarian & Urutkan -->
                <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                    <div class="relative flex-1">
                        <i class="ph ph-magnifying-glass text-base text-gray-400 pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true"></i>
                        <label for="order-search-input" class="sr-only">Cari produk dalam pesanan</label>
                        <input type="search" id="order-search-input"
                            placeholder="Cari produk dalam pesanan..."
                            class="w-full h-10 pl-10 pr-4 bg-white border border-gray-200/90 rounded-[2px] text-xs sm:text-[13px] text-[#272727] placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition" />
                    </div>

                    <div class="relative w-full sm:w-[152px] shrink-0">
                        <label for="order-sort-select" class="sr-only">Urutkan pesanan</label>
                        <select id="order-sort-select"
                            class="w-full h-10 pl-3.5 pr-9 bg-white border border-gray-200/90 rounded-[2px] text-xs sm:text-[13px] font-medium text-[#272727] appearance-none cursor-pointer focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition">
                            <option value="terbaru" selected>Terbaru</option>
                            <option value="terlama">Terlama</option>
                            <option value="total_desc">Total Tertinggi</option>
                            <option value="total_asc">Total Terendah</option>
                        </select>
                        <i class="ph ph-caret-down text-xs text-gray-600 pointer-events-none absolute right-3 top-1/2 -translate-y-1/2" aria-hidden="true"></i>
                    </div>
                </div>

                <?php if ($hasAnyOrders): ?>
                    <!-- Daftar Kartu Pesanan -->
                    <div id="orders-list" class="mt-4 space-y-4">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $searchParts = [$order['order_code'], $order['toko'], $order['status_label']];
                            foreach ($order['items'] as $orderItem) {
                                $searchParts[] = $orderItem['nama'];
                                $searchParts[] = $orderItem['kategori'];
                                $searchParts[] = $orderItem['kondisi'];
                            }
                            $searchIndex = strtolower(implode(' ', $searchParts));
                            ?>
                            <article class="order-card bg-white border border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.03)]"
                                data-status="<?= htmlspecialchars($order['status_key'], ENT_QUOTES, 'UTF-8') ?>"
                                data-timestamp="<?= (int)$order['timestamp'] ?>"
                                data-total="<?= (int)$order['total'] ?>"
                                data-search="<?= htmlspecialchars($searchIndex, ENT_QUOTES, 'UTF-8') ?>">

                                <!-- Header Kartu Pesanan -->
                                <div class="px-5 py-3.5 border-b border-gray-200/80 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="font-bold text-[#272727]">
                                                Order ID: <?= htmlspecialchars($order['order_code'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <button type="button"
                                                data-copy-order="<?= htmlspecialchars($order['order_code'], ENT_QUOTES, 'UTF-8') ?>"
                                                aria-label="Salin Order ID <?= htmlspecialchars($order['order_code'], ENT_QUOTES, 'UTF-8') ?>"
                                                title="Salin Order ID"
                                                class="text-gray-400 hover:text-[#272727] transition cursor-pointer inline-flex items-center">
                                                <i class="ph ph-copy text-sm" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <span aria-hidden="true" class="text-gray-300">|</span>
                                        <span class="text-gray-500">
                                            <?= htmlspecialchars($order['tanggal'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span aria-hidden="true" class="text-gray-300">|</span>
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-[#272727]">
                                            <i class="ph ph-storefront text-sm text-gray-500" aria-hidden="true"></i>
                                            <span><?= htmlspecialchars($order['toko'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                    </div>

                                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold <?= htmlspecialchars($order['status_class'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($order['status_icon'] !== ''): ?>
                                            <i class="ph <?= htmlspecialchars($order['status_icon'], ENT_QUOTES, 'UTF-8') ?> text-sm" aria-hidden="true"></i>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($order['status_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>

                                <!-- Daftar Produk dalam Pesanan -->
                                <div class="px-5 divide-y divide-gray-200/70">
                                    <?php foreach ($order['items'] as $item): ?>
                                        <?php
                                        $isBaru = strtolower(trim($item['kondisi'])) === 'baru';
                                        // Selaras dengan warna tag kondisi di display (search.php & detail_produk.php), lembut dan mudah dibaca
                                        $kondisiClasses = $isBaru
                                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/70'
                                            : 'bg-[#FFF8E1] text-[#92400E] border border-amber-200/70';
                                        $itemSubtotal = (int)$item['harga'] * (int)$item['qty'];
                                        $catIcon = kategoriIcon($item['kategori']);
                                        $detailHref = 'detail_produk.php?id=' . (int)$item['id_produk'];
                                        ?>
                                        <div class="py-4 flex items-start sm:items-center justify-between gap-3 sm:gap-4">
                                            <div class="flex items-center gap-3.5 min-w-0">
                                                <!-- Thumbnail Produk (Fallback Ikon Kategori) -->
                                                <a href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>"
                                                    aria-label="Lihat detail <?= htmlspecialchars($item['nama'], ENT_QUOTES, 'UTF-8') ?>"
                                                    class="w-16 h-16 shrink-0 bg-[#F7F7F8] border border-gray-200/80 rounded-[2px] flex flex-col items-center justify-center p-1.5 overflow-hidden hover:border-gray-300 transition">
                                                    <?php if (!empty($item['foto'])): ?>
                                                        <img src="<?= htmlspecialchars($item['foto'], ENT_QUOTES, 'UTF-8') ?>"
                                                            alt="<?= htmlspecialchars($item['nama'], ENT_QUOTES, 'UTF-8') ?>"
                                                            width="64" height="64"
                                                            class="w-full h-full object-contain" />
                                                    <?php else: ?>
                                                        <i class="ph <?= htmlspecialchars($catIcon, ENT_QUOTES, 'UTF-8') ?> text-2xl text-gray-600" aria-hidden="true"></i>
                                                        <span class="text-[9px] font-semibold text-gray-500 uppercase tracking-wide mt-0.5 leading-none truncate max-w-full">
                                                            <?= htmlspecialchars($item['kategori'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </a>

                                                <!-- Detail Produk -->
                                                <div class="min-w-0">
                                                    <span class="inline-block text-[10px] font-semibold px-2 py-0.5 rounded-[3px] leading-tight <?= $kondisiClasses ?>">
                                                        <?= htmlspecialchars($item['kondisi'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <h3 class="mt-1 text-sm font-bold text-[#272727] leading-snug">
                                                        <a href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>" class="hover:underline">
                                                            <?= htmlspecialchars($item['nama'], ENT_QUOTES, 'UTF-8') ?>
                                                        </a>
                                                    </h3>
                                                    <p class="mt-0.5 text-xs text-gray-500 tabular-nums">
                                                        <?= (int)$item['qty'] ?> barang x <?= htmlspecialchars(formatRupiah((int)$item['harga']), ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Harga Item -->
                                            <div class="text-right shrink-0">
                                                <span class="text-sm font-bold text-[#272727] tabular-nums">
                                                    <?= htmlspecialchars(formatRupiah($itemSubtotal), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Footer Kartu: Total Pembayaran & Tombol Aksi -->
                                <div class="px-5 py-3.5 border-t border-gray-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-baseline gap-2.5">
                                        <span class="text-xs text-gray-500">Total Pembayaran</span>
                                        <span class="text-base font-extrabold text-[#272727] tabular-nums">
                                            <?= htmlspecialchars(formatRupiah((int)$order['total']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <?php if ($order['db_status'] === 'dikirim'): ?>
                                            <button type="button"
                                                data-demo-toast="<?= htmlspecialchars($order['nomor_resi'] !== '' ? 'Nomor Resi ' . $order['order_code'] . ': ' . $order['nomor_resi'] : 'Nomor resi untuk ' . $order['order_code'] . ' belum diinput penjual.', ENT_QUOTES, 'UTF-8') ?>"
                                                class="inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold px-4 py-2 rounded-[2px] transition cursor-pointer">
                                                <i class="ph ph-truck text-sm" aria-hidden="true"></i>
                                                <span>Lacak Pengiriman</span>
                                            </button>
                                            <button type="button"
                                                data-demo-toast="Halaman Detail Pesanan <?= htmlspecialchars($order['order_code'], ENT_QUOTES, 'UTF-8') ?> belum tersedia saat ini."
                                                class="inline-flex items-center justify-center gap-1.5 bg-white hover:bg-gray-50 active:scale-[0.99] text-[#272727] border border-gray-300 text-xs font-semibold px-4 py-2 rounded-[2px] transition cursor-pointer">
                                                <i class="ph ph-receipt text-sm" aria-hidden="true"></i>
                                                <span>Lihat Detail</span>
                                            </button>
                                        <?php elseif ($order['db_status'] === 'menunggu_pembayaran'): ?>
                                            <button type="button"
                                                data-demo-toast="Halaman pembayaran untuk <?= htmlspecialchars($order['order_code'], ENT_QUOTES, 'UTF-8') ?> belum tersedia saat ini."
                                                class="inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold px-4 py-2 rounded-[2px] transition cursor-pointer">
                                                <i class="ph ph-credit-card text-sm" aria-hidden="true"></i>
                                                <span>Bayar Sekarang</span>
                                            </button>
                                            <form method="POST" action="pesanan.php" class="inline-block"
                                                onsubmit="return confirm('Batalkan pesanan <?= htmlspecialchars($order['order_code'], ENT_QUOTES, 'UTF-8') ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                                                <input type="hidden" name="action" value="batalkan_pesanan" />
                                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>" />
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center gap-1.5 bg-white hover:bg-gray-50 active:scale-[0.99] text-[#272727] border border-gray-300 text-xs font-semibold px-4 py-2 rounded-[2px] transition cursor-pointer">
                                                    <i class="ph ph-x-circle text-sm" aria-hidden="true"></i>
                                                    <span>Batalkan Pesanan</span>
                                                </button>
                                            </form>
                                        <?php elseif ($order['db_status'] === 'dibayar'): ?>
                                            <button type="button"
                                                data-demo-toast="Halaman Detail Pesanan <?= htmlspecialchars($order['order_code'], ENT_QUOTES, 'UTF-8') ?> belum tersedia saat ini."
                                                class="inline-flex items-center justify-center gap-1.5 bg-white hover:bg-gray-50 active:scale-[0.99] text-[#272727] border border-gray-300 text-xs font-semibold px-4 py-2 rounded-[2px] transition cursor-pointer">
                                                <i class="ph ph-receipt text-sm" aria-hidden="true"></i>
                                                <span>Lihat Detail</span>
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" action="pesanan.php" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                                                <input type="hidden" name="action" value="beli_lagi" />
                                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>" />
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold px-4 py-2 rounded-[2px] transition cursor-pointer">
                                                    <i class="ph ph-shopping-cart-simple text-sm" aria-hidden="true"></i>
                                                    <span>Beli Lagi</span>
                                                </button>
                                            </form>
                                            <button type="button"
                                                data-demo-toast="Halaman Detail Pesanan <?= htmlspecialchars($order['order_code'], ENT_QUOTES, 'UTF-8') ?> belum tersedia saat ini."
                                                class="inline-flex items-center justify-center gap-1.5 bg-white hover:bg-gray-50 active:scale-[0.99] text-[#272727] border border-gray-300 text-xs font-semibold px-4 py-2 rounded-[2px] transition cursor-pointer">
                                                <i class="ph ph-receipt text-sm" aria-hidden="true"></i>
                                                <span>Lihat Detail</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Empty State Filter / Pencarian -->
                    <div id="orders-empty-state"
                        class="hidden mt-4 bg-white border border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.03)] px-6 py-12 text-center">
                        <div class="w-12 h-12 mx-auto rounded-full bg-[#F4F4F4] flex items-center justify-center text-gray-500">
                            <i class="ph ph-package text-2xl" aria-hidden="true"></i>
                        </div>
                        <h2 id="orders-empty-title" class="mt-3 text-sm font-bold text-[#272727]">
                            Belum ada pesanan ditemukan
                        </h2>
                        <p id="orders-empty-desc" class="mt-1 text-xs text-gray-500 max-w-sm mx-auto leading-relaxed">
                            Tidak ada pesanan yang cocok dengan filter atau kata kunci pencarian saat ini.
                        </p>
                        <button type="button" id="orders-reset-filter"
                            class="mt-4 inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold px-4 py-2 rounded-[2px] transition cursor-pointer">
                            <span>Tampilkan Semua Pesanan</span>
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Empty State Awal (User belum memiliki pesanan sama sekali) -->
                    <div id="orders-empty-initial"
                        class="mt-4 bg-white border border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.03)] px-6 py-14 text-center">
                        <div class="w-14 h-14 mx-auto rounded-full bg-[#F4F4F4] flex items-center justify-center text-gray-500">
                            <i class="ph ph-package text-3xl" aria-hidden="true"></i>
                        </div>
                        <h2 class="mt-3.5 text-sm sm:text-base font-bold text-[#272727]">
                            Belum ada pesanan
                        </h2>
                        <p class="mt-1 text-xs sm:text-[13px] text-gray-500 max-w-sm mx-auto leading-relaxed">
                            Pesanan dari produk yang kamu beli atau checkout akan muncul di sini.
                        </p>
                        <a href="search.php"
                            class="mt-5 inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold px-5 py-2.5 rounded-[2px] transition">
                            <i class="ph ph-magnifying-glass text-sm font-bold" aria-hidden="true"></i>
                            <span>Cari Produk Sekarang</span>
                        </a>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <!-- Toast Notifikasi -->
    <div id="order-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg">
    </div>

    <!-- Footer & Help FAB -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function () {
            const tabs = Array.from(document.querySelectorAll('.order-status-tab'));
            const searchInput = document.getElementById('order-search-input');
            const sortSelect = document.getElementById('order-sort-select');
            const ordersList = document.getElementById('orders-list');
            const emptyState = document.getElementById('orders-empty-state');
            const emptyTitle = document.getElementById('orders-empty-title');
            const emptyDesc = document.getElementById('orders-empty-desc');
            const resetBtn = document.getElementById('orders-reset-filter');
            const toast = document.getElementById('order-toast');
            const initialFlash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            const ACTIVE_CLASSES = ['bg-brand-yellow', 'border-brand-yellow', 'text-[#272727]', 'font-semibold'];
            const INACTIVE_CLASSES = ['bg-white', 'border-gray-200', 'text-[#374151]', 'hover:border-gray-300', 'hover:bg-gray-50', 'font-medium'];

            let activeStatus = 'semua';
            let toastTimer = null;

            function showToast(message, type) {
                if (!toast || !message) return;
                toast.textContent = message;
                toast.classList.remove('hidden', 'border-brand-yellow', 'border-red-500');
                toast.classList.add(type === 'error' ? 'border-red-500' : 'border-brand-yellow');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function () {
                    toast.classList.add('hidden');
                }, 3500);
            }

            function getOrderCards() {
                return ordersList ? Array.from(ordersList.querySelectorAll('.order-card')) : [];
            }

            function sortCards(cards, mode) {
                if (!ordersList) return;
                cards.sort(function (a, b) {
                    const timeA = Number(a.dataset.timestamp || 0);
                    const timeB = Number(b.dataset.timestamp || 0);
                    const totalA = Number(a.dataset.total || 0);
                    const totalB = Number(b.dataset.total || 0);

                    if (mode === 'terlama') return timeA - timeB;
                    if (mode === 'total_desc') return totalB - totalA;
                    if (mode === 'total_asc') return totalA - totalB;
                    return timeB - timeA;
                });

                cards.forEach(function (card) {
                    ordersList.appendChild(card);
                });
            }

            function applyFilters() {
                const cards = getOrderCards();
                if (cards.length === 0) return;

                const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
                const sortMode = sortSelect ? sortSelect.value : 'terbaru';

                sortCards(cards, sortMode);

                let visibleCount = 0;
                cards.forEach(function (card) {
                    const statusMatch = activeStatus === 'semua' || card.dataset.status === activeStatus;
                    const searchMatch = query === '' || (card.dataset.search || '').includes(query);
                    const shouldShow = statusMatch && searchMatch;

                    card.classList.toggle('hidden', !shouldShow);
                    if (shouldShow) visibleCount++;
                });

                if (emptyState) {
                    const isEmpty = visibleCount === 0;
                    emptyState.classList.toggle('hidden', !isEmpty);
                    if (isEmpty && emptyTitle && emptyDesc) {
                        if (query !== '') {
                            emptyTitle.textContent = 'Pesanan tidak ditemukan';
                            emptyDesc.textContent = 'Tidak ada pesanan yang cocok dengan kata kunci "' + searchInput.value.trim() + '".';
                        } else {
                            emptyTitle.textContent = 'Belum ada pesanan pada status ini';
                            emptyDesc.textContent = 'Pesanan yang sesuai dengan status terpilih akan muncul di sini.';
                        }
                    }
                }
            }

            function setActiveTab(statusId) {
                activeStatus = statusId;
                tabs.forEach(function (tab) {
                    const isSelected = tab.dataset.statusTab === statusId;
                    tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    if (isSelected) {
                        tab.classList.remove.apply(tab.classList, INACTIVE_CLASSES);
                        tab.classList.add.apply(tab.classList, ACTIVE_CLASSES);
                    } else {
                        tab.classList.remove.apply(tab.classList, ACTIVE_CLASSES);
                        tab.classList.add.apply(tab.classList, INACTIVE_CLASSES);
                    }
                });
                applyFilters();
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    setActiveTab(tab.dataset.statusTab || 'semua');
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }

            if (sortSelect) {
                sortSelect.addEventListener('change', applyFilters);
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    if (searchInput) searchInput.value = '';
                    if (sortSelect) sortSelect.value = 'terbaru';
                    setActiveTab('semua');
                });
            }

            document.querySelectorAll('[data-copy-order]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const code = btn.getAttribute('data-copy-order') || '';
                    if (!code) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(code).then(function () {
                            showToast('Order ID ' + code + ' berhasil disalin.', 'info');
                        }).catch(function () {
                            showToast('Order ID: ' + code, 'info');
                        });
                    } else {
                        showToast('Order ID: ' + code, 'info');
                    }
                });
            });

            document.querySelectorAll('[data-demo-toast]').forEach(function (el) {
                el.addEventListener('click', function () {
                    const msg = el.getAttribute('data-demo-toast');
                    if (msg) showToast(msg, 'info');
                });
            });

            if (initialFlash && initialFlash.message) {
                showToast(initialFlash.message, initialFlash.type || 'success');
            }
        })();
    </script>
</body>

</html>

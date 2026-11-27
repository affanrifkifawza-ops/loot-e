<?php
require_once "auth.php";
require_once "config/helpers.php";

if (!isLoggedIn()) {
    header("Location: login.php?redirect=cart.php");
    exit;
}

$currentUser = getCurrentUser();

$navActivePage = 'cart';

// Handle penambahan via tautan GET langsung
if (isset($_GET['action']) && $_GET['action'] === 'add' && !empty($_GET['id'])) {
    $produkId = (int)$_GET['id'];
    $qty = max(1, (int)($_GET['qty'] ?? 1));

    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT id, id_penjual, stok, status_produk FROM produk WHERE id = ?");
        $stmt->execute([$produkId]);
        $prod = $stmt->fetch();

        if ($prod && $prod['status_produk'] === 'tersedia' && (int)$prod['stok'] >= 1 && (int)$prod['id_penjual'] !== $currentUser['id']) {
            $stmtCart = $db->prepare("
                INSERT INTO keranjang (id_pembeli, id_produk, kuantitas)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE kuantitas = LEAST(kuantitas + VALUES(kuantitas), ?)
            ");
            $stmtCart->execute([$currentUser['id'], $produkId, $qty, (int)$prod['stok']]);
        }
    } catch (PDOException $e) {
    }

    header('Location: cart.php');
    exit;
}

// Handle POST API untuk manipulasi keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $response = ['success' => false, 'message' => ''];

    try {
        $db = getDbConnection();

        if ($action === 'add_to_cart') {
            $produkId = (int)($_POST['produk_id'] ?? 0);
            $qty = max(1, (int)($_POST['qty'] ?? 1));

            $stmt = $db->prepare("SELECT id, id_penjual, stok, status_produk, nama_produk FROM produk WHERE id = ?");
            $stmt->execute([$produkId]);
            $prod = $stmt->fetch();

            if (!$prod || $prod['status_produk'] !== 'tersedia' || (int)$prod['stok'] < 1) {
                $response = ['success' => false, 'message' => 'Produk tidak tersedia atau stok habis'];
            } elseif ((int)$prod['id_penjual'] === $currentUser['id']) {
                $response = ['success' => false, 'message' => 'Anda tidak dapat membeli produk milik sendiri (BR-01)'];
            } else {
                $stmtCart = $db->prepare("
                    INSERT INTO keranjang (id_pembeli, id_produk, kuantitas)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE kuantitas = LEAST(kuantitas + VALUES(kuantitas), ?)
                ");
                $stmtCart->execute([$currentUser['id'], $produkId, $qty, (int)$prod['stok']]);
                $response = [
                    'success' => true,
                    'message' => htmlspecialchars($prod['nama_produk']) . ' berhasil ditambahkan ke keranjang!',
                    'produk_id' => $produkId
                ];
            }
        } elseif ($action === 'update_qty') {
            $keranjangId = (int)($_POST['id'] ?? 0);
            $qty = max(1, (int)($_POST['qty'] ?? 1));

            // Periksa stok produk
            $stmt = $db->prepare("
                SELECT p.stok 
                FROM keranjang k 
                JOIN produk p ON k.id_produk = p.id 
                WHERE k.id = ? AND k.id_pembeli = ?
            ");
            $stmt->execute([$keranjangId, $currentUser['id']]);
            $stok = $stmt->fetchColumn();

            if ($stok !== false) {
                $qty = min($qty, (int)$stok);
                $stmtUpdate = $db->prepare("UPDATE keranjang SET kuantitas = ? WHERE id = ? AND id_pembeli = ?");
                $stmtUpdate->execute([$qty, $keranjangId, $currentUser['id']]);
                $response = ['success' => true, 'message' => 'Kuantitas berhasil diperbarui', 'qty' => $qty];
            } else {
                $response = ['success' => false, 'message' => 'Produk tidak ditemukan di keranjang'];
            }
        } elseif ($action === 'delete_item') {
            $keranjangId = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM keranjang WHERE id = ? AND id_pembeli = ?");
            $stmt->execute([$keranjangId, $currentUser['id']]);
            $response = ['success' => true, 'message' => 'Produk berhasil dihapus dari keranjang'];
        } elseif ($action === 'delete_selected') {
            $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $db->prepare("DELETE FROM keranjang WHERE id IN ($placeholders) AND id_pembeli = ?");
                $stmt->execute([...$ids, $currentUser['id']]);
            }
            $response = ['success' => true, 'message' => 'Produk terpilih berhasil dihapus'];
        }
    } catch (PDOException $e) {
        $response = ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    }

    header('Location: cart.php');
    exit;
}

// Ambil data keranjang dari database
$cartItems = [];
try {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT k.id AS keranjang_id, k.kuantitas,
               p.id AS produk_id, p.nama_produk, p.foto, p.harga, p.kondisi,
               p.stok, p.status_produk, p.deskripsi, p.id_penjual, p.bisa_nego, p.bisa_barter,
               k2.nama_kategori,
               u.nama AS nama_penjual, u.alamat AS alamat_penjual
        FROM keranjang k
        JOIN produk p ON k.id_produk = p.id
        JOIN kategori k2 ON p.id_kategori = k2.id
        JOIN pengguna u ON p.id_penjual = u.id
        WHERE k.id_pembeli = ?
        ORDER BY k.id DESC
    ");
    $stmt->execute([$currentUser['id']]);
    $cartItems = $stmt->fetchAll();
} catch (PDOException $e) {
    $cartItems = [];
}

$totalProdukItem = 0;
$subtotalAwal = 0;
foreach ($cartItems as $item) {
    $totalProdukItem += (int)$item['kuantitas'];
    $subtotalAwal += (int)$item['harga'] * (int)$item['kuantitas'];
}

function formatHargaCart(int $nominal): string
{
    return 'Rp' . number_format($nominal, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Keranjang Belanja | Loot-E</title>

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
                            bg: '#F8F9FA'
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif']
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
        input:focus-visible {
            outline: 2px solid var(--brand-yellow);
            outline-offset: 2px;
        }

        .cart-checkbox {
            accent-color: #FFC914;
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

<body class="bg-[#F8F9FA] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Header & Navigasi Utama -->
    <?php require_once 'components/navbar.php'; ?>

    <!-- Konten Utama -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 md:px-12 py-5 sm:py-8">

        <!-- Header Halaman -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5 sm:mb-6">
            <div>
                <h1 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-[#272727] tracking-tight">
                    Keranjang Belanja
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 font-normal mt-1">
                    Periksa kembali produk pilihanmu sebelum melanjutkan ke pembayaran.
                </p>
            </div>
            <div id="cart-counter-badge" class="self-start sm:self-auto inline-flex items-center bg-white border border-gray-200 px-3 py-1.5 text-xs sm:text-sm font-semibold text-gray-800 shadow-2xs">
                <?= count($cartItems) ?> Produk dalam Keranjang
            </div>
        </div>

        <?php if (empty($cartItems)): ?>
            <!-- State Kosong -->
            <div id="empty-cart-view" class="bg-white border border-gray-200/80 p-6 sm:p-14 text-center max-w-xl mx-auto shadow-2xs my-4 sm:my-8">
                <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 text-yellow-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-shopping-cart text-3xl sm:text-4xl"></i>
                </div>
                <h2 class="text-base sm:text-lg font-bold text-gray-900 mb-1">Keranjang Belanja Masih Kosong</h2>
                <p class="text-xs sm:text-sm text-gray-500 max-w-md mx-auto mb-6 leading-relaxed">
                    Belum ada produk di keranjangmu. Temukan berbagai hardware PC, laptop, dan aksesoris terbaik sekarang!
                </p>
                <a href="search.php" class="inline-flex items-center justify-center gap-2 bg-brand-yellow hover:bg-yellow-400 active:bg-yellow-500 text-brand-dark font-bold px-5 py-3 text-xs sm:text-sm transition shadow-xs min-h-[44px]">
                    <i class="ph ph-magnifying-glass font-bold text-base"></i>
                    <span>Cari Produk Sekarang</span>
                </a>
            </div>
        <?php else: ?>
            <div id="active-cart-view" class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-8 items-start">

                <!-- Kolom Kiri: Daftar Produk Keranjang -->
                <section class="lg:col-span-8 space-y-4" aria-label="Daftar Produk di Keranjang">

                    <!-- Kartu Induk Keranjang -->
                    <div class="bg-white border border-gray-200/80 shadow-2xs overflow-hidden">

                        <!-- Baris Pilih Semua & Hapus -->
                        <div class="p-3.5 sm:p-4 md:px-6 bg-white border-b border-gray-100 flex items-center justify-between gap-3">
                            <label class="flex items-center gap-2.5 sm:gap-3 cursor-pointer select-none py-1 min-h-[44px]">
                                <input type="checkbox" id="check-all" checked
                                    class="cart-checkbox w-4 h-4 rounded text-brand-yellow focus:ring-brand-yellow border-gray-300 cursor-pointer" />
                                <span class="text-xs sm:text-sm font-bold text-gray-900">Pilih Semua</span>
                                <span id="label-terpilih" class="text-xs text-gray-500 font-normal">
                                    (<?= count($cartItems) ?> produk terpilih)
                                </span>
                            </label>

                            <button type="button" id="btn-delete-selected"
                                class="inline-flex items-center gap-1.5 text-[8px] sm:text-sm text-red-500 hover:text-red-600 transition font-medium focus:outline-none min-h-[44px] px-2"
                                aria-label="Hapus yang dipilih">
                                <i class="ph ph-trash text-base"></i>
                                <span>Hapus yang Dipilih</span>
                            </button>
                        </div>

                        <!-- Daftar Item Produk -->
                        <div class="divide-y divide-gray-100" id="cart-items-container">
                            <?php foreach ($cartItems as $index => $item):
                                $kondisiText = $item['kondisi'] ?? 'Bekas (Normal)';
                                $isBaru = (stripos($kondisiText, 'Baru') !== false && stripos($kondisiText, 'Seperti') === false);
                                $isOwnProduct = ($currentUser['id'] === (int)($item['id_penjual'] ?? 0));
                                $alamatPenjualRingkas = formatAlamatRingkas($item['alamat_penjual'] ?? '');
                                $catatanItem = '';
                                if (!empty($item['deskripsi'])) {
                                    $parsed = parseDeskripsiProduk($item['deskripsi']);
                                    $rw = $parsed['riwayat'] ?? [];
                                    $parts = array_filter([
                                        $rw['servis'] ?? '',
                                        $rw['kelengkapan'] ?? '',
                                        $alamatPenjualRingkas !== '' ? 'Siap kirim dari ' . $alamatPenjualRingkas : ''
                                    ]);
                                    $catatanItem = implode(' • ', array_slice($parts, 0, 2));
                                }
                                if (empty($catatanItem) && $alamatPenjualRingkas !== '') {
                                    $catatanItem = 'Siap kirim dari ' . $alamatPenjualRingkas;
                                }
                            ?>
                                <article class="cart-item-row p-3.5 sm:p-5 md:p-6 transition hover:bg-gray-50/50"
                                    data-id="<?= (int)$item['keranjang_id'] ?>"
                                    data-price="<?= (int)$item['harga'] ?>"
                                    data-max-stock="<?= (int)$item['stok'] ?>"
                                    data-own="<?= $isOwnProduct ? '1' : '0' ?>">

                                    <div class="flex flex-col sm:flex-row items-start gap-3 sm:gap-4">
                                        <!-- Checkbox & Thumbnail & Header Info (Mobile Friendly) -->
                                        <div class="flex items-start gap-2.5 sm:gap-4 w-full sm:w-auto">
                                            <label class="pt-1.5 sm:pt-1.5 p-1 -m-1 inline-flex items-center justify-center cursor-pointer select-none">
                                                <input type="checkbox" checked
                                                    class="item-checkbox cart-checkbox w-4 h-4 rounded text-brand-yellow focus:ring-brand-yellow border-gray-300 cursor-pointer"
                                                    data-id="<?= (int)$item['keranjang_id'] ?>"
                                                    aria-label="Pilih <?= htmlspecialchars($item['nama_produk']) ?>" />
                                            </label>

                                            <!-- Thumbnail Produk (Dapat Diklik ke Detail) -->
                                            <a href="detail_produk.php?id=<?= (int)$item['produk_id'] ?>"
                                                class="w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24 bg-[#FAFAFA] border border-gray-200/80 p-1.5 sm:p-2 flex items-center justify-center shrink-0 overflow-hidden hover:opacity-90 hover:border-gray-300 transition block cursor-pointer"
                                                aria-label="Lihat detail <?= htmlspecialchars($item['nama_produk']) ?>">
                                                <?php if (!empty($item['foto'])): ?>
                                                    <img src="<?= htmlspecialchars($item['foto']) ?>"
                                                        alt="<?= htmlspecialchars($item['nama_produk']) ?>"
                                                        class="w-full h-full object-contain" />
                                                <?php else: ?>
                                                    <i class="ph <?= htmlspecialchars(kategoriIcon($item['nama_kategori'] ?? '')) ?> text-2xl sm:text-3xl text-gray-400"></i>
                                                <?php endif; ?>
                                            </a>

                                            <!-- Pada Layar Mobile (< sm), info produk tampil langsung di sebelah thumbnail -->
                                            <a href="detail_produk.php?id=<?= (int)$item['produk_id'] ?>" class="flex-1 min-w-0 sm:hidden block group">
                                                <div class="flex items-center gap-1.5 flex-wrap text-[11px] text-gray-600 mb-1">
                                                    <span class="font-medium text-gray-800 truncate max-w-[120px]"><?= htmlspecialchars($item['nama_penjual'] ?? 'Toko') ?></span>
                                                    <span class="text-gray-300">•</span>
                                                    <?php if ($isBaru): ?>
                                                        <span class="bg-[#272727] text-white text-[10px] font-semibold px-1.5 py-0.5 rounded">
                                                            <?= htmlspecialchars($kondisiText) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="bg-[#FEF3C7] text-[#92400E] text-[10px] font-medium px-1.5 py-0.5 rounded">
                                                            <?= htmlspecialchars($kondisiText) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <h2 class="font-bold text-xs text-gray-900 leading-snug line-clamp-2 group-hover:text-amber-600 transition">
                                                    <?= htmlspecialchars($item['nama_produk']) ?>
                                                </h2>
                                                <p class="text-[11px] font-extrabold text-[#272727] mt-1">
                                                    <?= formatHargaCart((int)$item['harga']) ?>
                                                </p>
                                            </a>
                                        </div>

                                        <!-- Informasi Produk (Tampilan Tablet & Desktop: sm ke atas) -->
                                        <a href="detail_produk.php?id=<?= (int)$item['produk_id'] ?>" class="hidden sm:block flex-1 min-w-0 group">
                                            <!-- Penjual & Badge Kondisi -->
                                            <div class="flex items-center gap-1.5 flex-wrap text-xs text-gray-600 mb-1.5">
                                                <i class="ph ph-storefront text-gray-400 text-sm"></i>
                                                <span class="font-medium text-gray-800"><?= htmlspecialchars($item['nama_penjual'] ?? 'Toko') ?></span>
                                                <span class="text-gray-300">•</span>
                                                <?php if ($isBaru): ?>
                                                    <span class="bg-[#272727] text-white text-[11px] font-semibold px-2 py-0.5 rounded">
                                                        <?= htmlspecialchars($kondisiText) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-[#FEF3C7] text-[#92400E] text-[11px] font-medium px-2 py-0.5 rounded">
                                                        <?= htmlspecialchars($kondisiText) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Judul Produk -->
                                            <h2 class="font-bold text-sm sm:text-base text-gray-900 leading-snug line-clamp-2 group-hover:text-amber-600 transition">
                                                <?= htmlspecialchars($item['nama_produk']) ?>
                                            </h2>

                                            <!-- Kategori -->
                                            <p class="text-xs text-gray-500 mt-1">
                                                Kategori: <?= htmlspecialchars($item['nama_kategori'] ?? 'Komponen') ?>
                                            </p>

                                            <!-- Catatan / Garansi -->
                                            <?php if (!empty($catatanItem)): ?>
                                                <p class="text-xs text-gray-400 mt-1 leading-relaxed">
                                                    <?= htmlspecialchars($catatanItem) ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if ($isOwnProduct): ?>
                                                <p class="text-xs text-amber-700 font-medium mt-1">
                                                    * Produk milik Anda sendiri (BR-01)
                                                </p>
                                            <?php endif; ?>
                                        </a>

                                        <!-- Harga & Kontrol Kuantitas (Responsif: Bawah di Mobile, Kanan di Desktop) -->
                                        <div class="w-full sm:w-auto flex items-center sm:flex-col sm:items-end justify-between sm:justify-start gap-2 pt-2.5 sm:pt-0 self-stretch shrink-0 border-t sm:border-t-0 border-gray-100">
                                            <!-- Harga Satuan (Hanya desktop sm ke atas karena di mobile sudah di atas) -->
                                            <div class="hidden sm:block text-right">
                                                <span class="text-[11px] text-gray-400 block font-normal">Harga Satuan</span>
                                                <span class="text-base sm:text-lg font-extrabold text-[#272727] block">
                                                    <?= formatHargaCart((int)$item['harga']) ?>
                                                </span>
                                            </div>

                                            <!-- Label Kuantitas di Layar Kecil -->
                                            <span class="sm:hidden text-xs text-gray-500 font-medium">Jumlah:</span>

                                            <!-- Stepper Kuantitas & Hapus -->
                                            <div class="flex items-center gap-2 sm:mt-4">
                                                <div class="inline-flex items-center border border-gray-200 bg-white overflow-hidden shadow-2xs">
                                                    <button type="button"
                                                        class="btn-decrement w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center text-gray-600 hover:bg-gray-100 active:bg-gray-200 transition font-bold text-base sm:text-sm select-none"
                                                        aria-label="Kurangi kuantitas">
                                                        -
                                                    </button>
                                                    <input type="number"
                                                        class="qty-field w-9 sm:w-10 text-center text-xs sm:text-sm font-bold text-gray-900 border-x border-gray-200 py-1.5 sm:py-1 focus:outline-none [-moz-appearance:_textfield] [&::-webkit-inner-spin-button]:m-0 [&::-webkit-inner-spin-button]:appearance-none"
                                                        value="<?= (int)$item['kuantitas'] ?>"
                                                        min="1"
                                                        max="<?= (int)$item['stok'] ?>"
                                                        readonly />
                                                    <button type="button"
                                                        class="btn-increment w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center text-gray-600 hover:bg-gray-100 active:bg-gray-200 transition font-bold text-base sm:text-sm select-none"
                                                        aria-label="Tambah kuantitas">
                                                        +
                                                    </button>
                                                </div>

                                                <button type="button"
                                                    class="btn-delete-row w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center text-gray-400 hover:text-red-600 hover:bg-red-50 transition"
                                                    title="Hapus produk"
                                                    aria-label="Hapus produk">
                                                    <i class="ph ph-trash text-lg sm:text-base"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Banner Keamanan Rekber Loot-E -->
                    <div class="flex items-center gap-2.5 text-xs text-gray-500 px-1 py-1">
                        <i class="ph-fill ph-shield-check text-[#FFC914] text-lg shrink-0"></i>
                        <span class="leading-relaxed">Transaksi aman dan terlindungi dengan sistem Rekber Loot-E Protection. Dana diteruskan ke penjual setelah barang diterima dan diverifikasi.</span>
                    </div>
                </section>

                <!-- Kolom Kanan: Ringkasan Belanja -->
                <aside class="lg:col-span-4 lg:sticky lg:top-24 space-y-4" aria-label="Ringkasan Belanja">
                    <div class="bg-white border border-gray-200/80 p-4 sm:p-6 shadow-2xs">
                        <h2 class="text-base sm:text-lg font-bold text-[#272727] mb-4 sm:mb-5">
                            Ringkasan Belanja
                        </h2>

                        <!-- Rincian Produk & Ongkir -->
                        <div class="space-y-3 text-xs sm:text-sm">
                            <div class="flex justify-between items-center text-gray-600">
                                <span>Total Produk</span>
                                <span id="summary-total-item" class="font-medium text-gray-900">
                                    <?= $totalProdukItem ?> item
                                </span>
                            </div>

                            <div class="flex justify-between items-center text-gray-600">
                                <span>Subtotal</span>
                                <span id="summary-subtotal" class="font-bold text-gray-900">
                                    <?= formatHargaCart($subtotalAwal) ?>
                                </span>
                            </div>

                            <div class="flex justify-between items-start text-gray-600">
                                <div>
                                    <span>Biaya Pengiriman</span>
                                    <p class="text-[11px] text-gray-400 mt-0.5">Dihitung saat checkout</p>
                                </div>
                                <span class="font-medium text-gray-400">-</span>
                            </div>
                        </div>

                        <hr class="border-gray-200 my-4" />

                        <!-- Total Belanja -->
                        <div class="flex justify-between items-baseline mb-5 sm:mb-6">
                            <div>
                                <span class="font-bold text-sm sm:text-base text-[#272727] block">Total Belanja</span>
                                <span class="text-[11px] text-gray-400 block">Belum termasuk ongkir</span>
                            </div>
                            <span id="summary-grandtotal" class="text-xl sm:text-2xl font-extrabold text-[#272727]">
                                <?= formatHargaCart($subtotalAwal) ?>
                            </span>
                        </div>

                        <!-- Tombol Aksi -->
                        <a href="#"
                            class="w-full bg-brand-yellow hover:bg-yellow-400 active:bg-yellow-500 text-brand-dark font-bold py-3.5 px-4 flex items-center justify-center gap-2 transition shadow-xs disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px]">
                            <span>Lanjut ke Checkout</span>
                            <i class="ph ph-arrow-right font-bold text-base"></i>
                        </a>

                        <a href="search.php"
                            class="w-full mt-3 border border-gray-300 hover:bg-gray-50 active:bg-gray-100 text-gray-800 font-semibold py-3 px-4 flex items-center justify-center transition text-sm min-h-[44px]">
                            Belanja Lagi
                        </a>

                        <hr class="border-gray-200 my-4 sm:my-5" />

                        <!-- Dukungan Pelanggan -->
                        <p class="text-xs text-gray-500 text-center">
                            Punya kendala transaksi?
                            <a href="help.php" class="font-semibold text-gray-800 hover:underline">
                                Hubungi Loot-E Support
                            </a>
                        </p>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal Konfirmasi Hapus -->
    <dialog id="delete-modal" class="rounded-lg backdrop:bg-black/50 p-6 max-w-sm w-full shadow-2xl border border-gray-100 m-auto">
        <div class="text-center">
            <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-3">
                <i class="ph ph-trash text-2xl"></i>
            </div>
            <h3 class="font-bold text-base text-gray-900 mb-1" id="delete-modal-title">Hapus Produk?</h3>
            <p class="text-xs text-gray-500 mb-6" id="delete-modal-desc">Produk ini akan dihapus dari keranjang belanja Anda.</p>
            <div class="flex gap-2">
                <button type="button" id="btn-cancel-delete"
                    class="flex-1 py-2.5 px-4 rounded-xl border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                    Batal
                </button>
                <button type="button" id="btn-confirm-delete"
                    class="flex-1 py-2.5 px-4 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition shadow-xs">
                    Hapus
                </button>
            </div>
        </div>
    </dialog>

    <!-- Notifikasi Toast -->
    <div id="cart-toast"
        class="fixed bottom-6 left-6 z-50 hidden bg-[#272727] text-white border-l-4 border-brand-yellow px-4 py-3 rounded-lg text-xs font-medium shadow-xl transition-all duration-200 flex items-center gap-2"
        role="status" aria-live="polite">
        <i class="ph ph-info text-brand-yellow text-base"></i>
        <span id="toast-message">Pemberitahuan</span>
    </div>

    <!-- Footer Utama -->
    <?php require_once 'components/footer.php'; ?>

    <!-- Skrip Interaktivitas Keranjang -->
    <script>
        (function() {
            const checkAll = document.getElementById('check-all');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            const labelTerpilih = document.getElementById('label-terpilih');
            const summaryTotalItem = document.getElementById('summary-total-item');
            const summarySubtotal = document.getElementById('summary-subtotal');
            const summaryGrandtotal = document.getElementById('summary-grandtotal');
            const btnCheckout = document.getElementById('btn-checkout');
            const btnDeleteSelected = document.getElementById('btn-delete-selected');
            const cartCounterBadge = document.getElementById('cart-counter-badge');
            const deleteModal = document.getElementById('delete-modal');
            const btnCancelDelete = document.getElementById('btn-cancel-delete');
            const btnConfirmDelete = document.getElementById('btn-confirm-delete');
            const deleteModalTitle = document.getElementById('delete-modal-title');
            const deleteModalDesc = document.getElementById('delete-modal-desc');
            const toast = document.getElementById('cart-toast');
            const toastMessage = document.getElementById('toast-message');

            let toastTimer = null;

            function showToast(msg) {
                if (!toast) return;
                toastMessage.textContent = msg;
                toast.classList.remove('hidden');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => {
                    toast.classList.add('hidden');
                }, 3000);
            }

            function formatRupiah(amount) {
                return 'Rp' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            // Hitung ulang ringkasan belanja berdasarkan item yang dicentang
            function recalculateSummary() {
                let totalItemsCount = 0;
                let subtotal = 0;
                let selectedRowsCount = 0;
                let hasOwnProduct = false;

                const rows = document.querySelectorAll('.cart-item-row');
                rows.forEach(row => {
                    const cb = row.querySelector('.item-checkbox');
                    const qtyField = row.querySelector('.qty-field');
                    const price = parseInt(row.getAttribute('data-price') || '0', 10);
                    const qty = parseInt(qtyField ? qtyField.value : '1', 10);
                    const isOwn = row.getAttribute('data-own') === '1';

                    if (cb && cb.checked) {
                        selectedRowsCount++;
                        totalItemsCount += qty;
                        subtotal += (price * qty);
                        if (isOwn) hasOwnProduct = true;
                    }
                });

                if (labelTerpilih) {
                    labelTerpilih.textContent = `(${selectedRowsCount} produk terpilih)`;
                }
                if (summaryTotalItem) {
                    summaryTotalItem.textContent = `${totalItemsCount} item`;
                }
                if (summarySubtotal) {
                    summarySubtotal.textContent = formatRupiah(subtotal);
                }
                if (summaryGrandtotal) {
                    summaryGrandtotal.textContent = formatRupiah(subtotal);
                }

                if (btnCheckout) {
                    btnCheckout.disabled = (selectedRowsCount === 0 || hasOwnProduct);
                    if (hasOwnProduct) {
                        btnCheckout.title = 'Ada produk milik Anda sendiri di daftar terpilih (BR-01)';
                    } else {
                        btnCheckout.title = '';
                    }
                }

                if (btnDeleteSelected) {
                    btnDeleteSelected.disabled = (selectedRowsCount === 0);
                    btnDeleteSelected.classList.toggle('opacity-40', selectedRowsCount === 0);
                    btnDeleteSelected.classList.toggle('pointer-events-none', selectedRowsCount === 0);
                }

                if (checkAll) {
                    const allCbs = document.querySelectorAll('.item-checkbox');
                    checkAll.checked = (allCbs.length > 0 && selectedRowsCount === allCbs.length);
                }
            }

            // Sync kuantitas ke backend via POST
            function syncQty(keranjangId, qty) {
                const formData = new FormData();
                formData.append('action', 'update_qty');
                formData.append('id', keranjangId);
                formData.append('qty', qty);

                fetch('cart.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                }).catch(() => {});
            }

            // Check all toggle
            if (checkAll) {
                checkAll.addEventListener('change', function() {
                    const cbs = document.querySelectorAll('.item-checkbox');
                    cbs.forEach(cb => cb.checked = checkAll.checked);
                    recalculateSummary();
                });
            }

            // Item checkbox toggle
            itemCheckboxes.forEach(cb => {
                cb.addEventListener('change', recalculateSummary);
            });

            // Tombol stepper kuantitas
            document.querySelectorAll('.cart-item-row').forEach(row => {
                const keranjangId = row.getAttribute('data-id');
                const maxStock = parseInt(row.getAttribute('data-max-stock') || '99', 10);
                const qtyInput = row.querySelector('.qty-field');
                const btnInc = row.querySelector('.btn-increment');
                const btnDec = row.querySelector('.btn-decrement');

                if (btnInc && qtyInput) {
                    btnInc.addEventListener('click', function() {
                        let current = parseInt(qtyInput.value || '1', 10);
                        if (current < maxStock) {
                            current++;
                            qtyInput.value = current;
                            recalculateSummary();
                            syncQty(keranjangId, current);
                        } else {
                            showToast(`Stok maksimal hanya ${maxStock} unit`);
                        }
                    });
                }

                if (btnDec && qtyInput) {
                    btnDec.addEventListener('click', function() {
                        let current = parseInt(qtyInput.value || '1', 10);
                        if (current > 1) {
                            current--;
                            qtyInput.value = current;
                            recalculateSummary();
                            syncQty(keranjangId, current);
                        }
                    });
                }
            });

            // Penanganan Modal Hapus
            let pendingDeleteType = null;
            let pendingDeleteId = null;

            function openDeleteModal(type, id = null, count = 1) {
                pendingDeleteType = type;
                pendingDeleteId = id;

                if (type === 'single') {
                    deleteModalTitle.textContent = 'Hapus Produk?';
                    deleteModalDesc.textContent = 'Produk ini akan dihapus dari keranjang belanja Anda.';
                } else {
                    deleteModalTitle.textContent = 'Hapus Produk Terpilih?';
                    deleteModalDesc.textContent = `${count} produk terpilih akan dihapus dari keranjang belanja Anda.`;
                }

                if (deleteModal && typeof deleteModal.showModal === 'function') {
                    deleteModal.showModal();
                } else {
                    if (confirm(deleteModalDesc.textContent)) {
                        executeDelete();
                    }
                }
            }

            if (btnCancelDelete) {
                btnCancelDelete.addEventListener('click', () => {
                    if (deleteModal) deleteModal.close();
                });
            }

            function executeDelete() {
                if (pendingDeleteType === 'single' && pendingDeleteId) {
                    const row = document.querySelector(`.cart-item-row[data-id="${pendingDeleteId}"]`);
                    if (row) {
                        row.remove();
                    }
                    const formData = new FormData();
                    formData.append('action', 'delete_item');
                    formData.append('id', pendingDeleteId);
                    fetch('cart.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    }).catch(() => {});
                    showToast('Produk dihapus dari keranjang');
                } else if (pendingDeleteType === 'selected') {
                    const selectedCbs = document.querySelectorAll('.item-checkbox:checked');
                    const ids = [];
                    selectedCbs.forEach(cb => {
                        const id = cb.getAttribute('data-id');
                        if (id) ids.push(id);
                        const row = cb.closest('.cart-item-row');
                        if (row) row.remove();
                    });

                    const formData = new FormData();
                    formData.append('action', 'delete_selected');
                    formData.append('ids', ids.join(','));
                    fetch('cart.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    }).catch(() => {});
                    showToast(`${ids.length} produk berhasil dihapus`);
                }

                if (deleteModal && deleteModal.open) {
                    deleteModal.close();
                }

                // Update counter produk di header
                const remainingRows = document.querySelectorAll('.cart-item-row');
                if (cartCounterBadge) {
                    cartCounterBadge.textContent = `${remainingRows.length} Produk dalam Keranjang`;
                }

                if (remainingRows.length === 0) {
                    location.reload();
                } else {
                    recalculateSummary();
                }
            }

            if (btnConfirmDelete) {
                btnConfirmDelete.addEventListener('click', executeDelete);
            }

            // Hapus per item
            document.querySelectorAll('.btn-delete-row').forEach(btn => {
                btn.addEventListener('click', function() {
                    const row = this.closest('.cart-item-row');
                    const id = row ? row.getAttribute('data-id') : null;
                    if (id) {
                        openDeleteModal('single', id);
                    }
                });
            });

            // Hapus yang dipilih
            if (btnDeleteSelected) {
                btnDeleteSelected.addEventListener('click', function() {
                    const selectedCbs = document.querySelectorAll('.item-checkbox:checked');
                    if (selectedCbs.length > 0) {
                        openDeleteModal('selected', null, selectedCbs.length);
                    }
                });
            }

            // Lanjut ke Checkout
            if (btnCheckout) {
                btnCheckout.addEventListener('click', function() {
                    const selectedCbs = document.querySelectorAll('.item-checkbox:checked');
                    if (selectedCbs.length === 0) {
                        showToast('Silakan pilih minimal satu produk untuk checkout');
                        return;
                    }
                    showToast('Melanjutkan ke halaman pembayaran...');
                    setTimeout(() => {
                        window.location.href = 'checkout.php';
                    }, 500);
                });
            }

            // Inisialisasi awal
            recalculateSummary();
        })();
    </script>
</body>

</html>
<?php
require_once "auth.php";

// Pastikan pengguna sudah login (BR-04 & Session Guard)
if (!isLoggedIn()) {
    header("Location: login.php?redirect=nego_buyer.php");
    exit;
}

$currentUser = getCurrentUser();
$userId = (int)($currentUser['id'] ?? 0);
$isVerified = (int)($currentUser['is_verified'] ?? 0) === 1;

// Helper format nominal Rupiah sesuai format desain (Rp620.000)
function formatRupiahNego(int $angka): string
{
    return 'Rp' . number_format($angka, 0, ',', '.');
}

// Helper format tanggal negosiasi (contoh: 14 Okt 2025, 14:30 WIB)
function formatTanggalNego(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    static $bulan = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y, H:i', $ts) . ' WIB';
}

// Ambil data user terbaru dari database
$db = null;
try {
    $db = getDbConnection();
    if ($db instanceof PDO) {
        $stmtUser = $db->prepare("
            SELECT id, nama, email, peran, is_verified
            FROM pengguna
            WHERE id = ?
            LIMIT 1
        ");
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch();
        if ($userRow) {
            $_SESSION['nama']        = $userRow['nama'];
            $_SESSION['email']       = $userRow['email'];
            $_SESSION['peran']       = $userRow['peran'];
            $_SESSION['is_verified'] = (int)$userRow['is_verified'];

            $currentUser['nama']     = (string)$userRow['nama'];
            $currentUser['email']    = (string)$userRow['email'];
            $isVerified              = (int)$userRow['is_verified'] === 1;
        }
    }
} catch (PDOException $e) {
    // Database fallback
}

$accountActiveMenu = 'negosiasi';
$accountSidebarUser = [
    'nama'  => $currentUser['nama'] ?? 'Pengguna',
    'email' => $currentUser['email'] ?? '',
];

// Query daftar negosiasi buyer dari database
$negotiations = [];
$dbHasRows = false;

if ($db instanceof PDO) {
    try {
        $stmtNego = $db->prepare("
            SELECT n.id, n.id_pembeli, n.id_produk, n.kuantitas, n.harga_deal, n.status, n.created_at,
                   p.nama_produk, p.foto, p.kondisi, p.harga AS harga_asli, p.id_penjual,
                   k.nama_kategori,
                   penjual.nama AS nama_penjual,
                   (
                       SELECT rn.harga
                       FROM riwayat_negosiasi rn
                       WHERE rn.id_negosiasi = n.id
                       ORDER BY rn.waktu DESC, rn.id DESC
                       LIMIT 1
                   ) AS tawaran_terakhir_harga,
                   (
                       SELECT rn.pengirim_tawaran
                       FROM riwayat_negosiasi rn
                       WHERE rn.id_negosiasi = n.id
                       ORDER BY rn.waktu DESC, rn.id DESC
                       LIMIT 1
                   ) AS pengirim_terakhir
            FROM negosiasi n
            JOIN produk p ON n.id_produk = p.id
            JOIN kategori k ON p.id_kategori = k.id
            JOIN pengguna penjual ON p.id_penjual = penjual.id
            WHERE n.id_pembeli = ?
            ORDER BY n.created_at DESC, n.id DESC
        ");
        $stmtNego->execute([$userId]);
        $rawNegoRows = $stmtNego->fetchAll();

        if (!empty($rawNegoRows)) {
            $dbHasRows = true;
            foreach ($rawNegoRows as $row) {
                $statusDb = (string)$row['status'];
                $statusKey = 'menunggu_respons';
                $statusLabel = 'Menunggu Respons';
                $statusColor = 'text-[#C86A2B]';
                $statusIcon = 'ph-clock';

                if ($statusDb === 'counter_offer') {
                    $statusKey = 'penawaran_balik';
                    $statusLabel = 'Penawaran Balik';
                    $statusColor = 'text-[#C86A2B]';
                    $statusIcon = 'ph-arrow-u-up-left';
                } elseif ($statusDb === 'diterima') {
                    $statusKey = 'diterima';
                    $statusLabel = 'Diterima';
                    $statusColor = 'text-[#16A34A]';
                    $statusIcon = null;
                } elseif ($statusDb === 'ditolak') {
                    $statusKey = 'ditolak';
                    $statusLabel = 'Ditolak';
                    $statusColor = 'text-gray-500';
                    $statusIcon = 'ph-prohibit';
                } elseif ($statusDb === 'expired') {
                    $statusKey = 'ditolak';
                    $statusLabel = 'Kedaluwarsa';
                    $statusColor = 'text-gray-500';
                    $statusIcon = 'ph-clock';
                }

                $catSlug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string)$row['nama_kategori']), 0, 5) ?: 'ITEM');
                $code = '#NEG-' . $catSlug . '-' . str_pad((string)$row['id'], 4, '0', STR_PAD_LEFT);
                $kondisi = (string)($row['kondisi'] ?? 'Bekas');
                $isBaru = stripos($kondisi, 'Baru') !== false;

                $hargaAsliNominal = (int)$row['harga_asli'];
                $lastOfferNominal = (int)($row['tawaran_terakhir_harga'] ?? $row['harga_deal'] ?? $row['harga_asli']);
                $ts = strtotime((string)$row['created_at']) ?: time();

                $negotiations[] = [
                    'id'                 => (int)$row['id'],
                    'code'               => $code,
                    'timestamp'          => $ts,
                    'nominal_harga'      => $lastOfferNominal,
                    'waktu'              => formatTanggalNego((string)$row['created_at']),
                    'toko'               => (string)$row['nama_penjual'],
                    'status_key'         => $statusKey,
                    'status_label'       => $statusLabel,
                    'status_color'       => $statusColor,
                    'status_icon'        => $statusIcon,
                    'nama_produk'        => (string)$row['nama_produk'],
                    'kondisi'            => $kondisi,
                    'kondisi_badge_bg'   => $isBaru ? 'bg-brand-yellow text-[#272727]' : 'bg-[#272727] text-white',
                    'tipe_arah'          => 'keluar',
                    'subteks'            => 'Kamu mengajukan penawaran',
                    'subteks_icon'       => 'ph-user',
                    'harga_asli'         => formatRupiahNego($hargaAsliNominal),
                    'penawaran_terakhir' => formatRupiahNego($lastOfferNominal),
                    'foto'               => !empty($row['foto']) ? (string)$row['foto'] : null,
                    'detail_url'         => 'status_negosiasi.php?id=' . (int)$row['id'],
                ];
            }
        }
    } catch (PDOException $e) {
        $negotiations = [];
    }
}

// Tabs filter navigasi
$filterTabs = [
    ['key' => 'semua',            'label' => 'Semua'],
    ['key' => 'menunggu_respons', 'label' => 'Menunggu Respons'],
    ['key' => 'penawaran_balik',  'label' => 'Penawaran Balik'],
    ['key' => 'diterima',         'label' => 'Diterima'],
    ['key' => 'ditolak',          'label' => 'Ditolak'],
    ['key' => 'selesai',          'label' => 'Selesai'],
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Negosiasi Saya | Loot-E</title>

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

            <!-- Sidebar Navigasi Akun -->
            <?php require_once 'components/account_sidebar.php'; ?>

            <!-- Area Konten Utama Negosiasi -->
            <section class="flex-1 w-full min-w-0">

                <!-- Judul Halaman -->
                <header>
                    <h1 class="text-2xl sm:text-[26px] font-extrabold text-[#272727] tracking-tight leading-tight">
                        Negosiasi Saya
                    </h1>
                    <p class="text-xs sm:text-[13px] text-gray-500 mt-1">
                        Kelola semua penawaran harga yang melibatkan kamu di Loot-E
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

                <!-- Baris Tab Status Filter -->
                <div role="tablist" aria-label="Filter status negosiasi"
                    class="mt-5 flex flex-wrap items-center gap-2">
                    <?php foreach ($filterTabs as $index => $tab): ?>
                        <?php $isDefaultActive = $index === 0; ?>
                        <button type="button"
                            role="tab"
                            aria-selected="<?= $isDefaultActive ? 'true' : 'false' ?>"
                            data-filter="<?= htmlspecialchars($tab['key'], ENT_QUOTES, 'UTF-8') ?>"
                            class="tab-filter-btn px-4 py-2 text-xs sm:text-[13px] rounded-[2px] border transition cursor-pointer <?= $isDefaultActive ? 'bg-brand-yellow border-brand-yellow text-[#272727] font-semibold' : 'bg-white border-gray-200 text-[#374151] hover:border-gray-300 hover:bg-gray-50 font-medium' ?>">
                            <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Bar Pencarian & Urutkan -->
                <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                    <div class="relative flex-1">
                        <i class="ph ph-magnifying-glass text-base text-gray-400 pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2" aria-hidden="true"></i>
                        <label for="search-negotiation" class="sr-only">Cari produk dalam negosiasi</label>
                        <input type="search" id="search-negotiation"
                            placeholder="Cari produk dalam negosiasi..."
                            class="w-full h-10 pl-10 pr-4 bg-white border border-gray-200/90 rounded-[2px] text-xs sm:text-[13px] text-[#272727] placeholder-gray-400 focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition" />
                    </div>

                    <div class="relative w-full sm:w-[152px] shrink-0">
                        <label for="sort-negotiation" class="sr-only">Urutkan negosiasi</label>
                        <select id="sort-negotiation"
                            class="w-full h-10 pl-3.5 pr-9 bg-white border border-gray-200/90 rounded-[2px] text-xs sm:text-[13px] font-medium text-[#272727] appearance-none cursor-pointer focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 transition">
                            <option value="terbaru" selected>Terbaru</option>
                            <option value="terlama">Terlama</option>
                            <option value="harga_tertinggi">Harga Tertinggi</option>
                            <option value="harga_terendah">Harga Terendah</option>
                        </select>
                        <i class="ph ph-caret-down text-xs text-gray-600 pointer-events-none absolute right-3 top-1/2 -translate-y-1/2" aria-hidden="true"></i>
                    </div>
                </div>

                <!-- Daftar Kartu Negosiasi -->
                <div id="negotiations-container" class="mt-4 space-y-4">
                    <?php if (empty($negotiations)): ?>
                        <div class="bg-white border border-gray-200/80 p-12 text-center rounded-[2px]">
                            <div class="w-16 h-16 mx-auto rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-4">
                                <i class="ph ph-chat-circle-dots text-3xl" aria-hidden="true"></i>
                            </div>
                            <h3 class="text-base font-bold text-[#272727]">Belum Ada Negosiasi</h3>
                            <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto leading-relaxed">
                                Kamu belum memiliki riwayat negosiasi harga produk. Jelajahi katalog dan ajukan penawaran terbaikmu!
                            </p>
                            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                                <a href="search.php"
                                    class="inline-flex items-center gap-2 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition shadow-xs">
                                    <i class="ph ph-magnifying-glass text-sm font-bold" aria-hidden="true"></i>
                                    <span>Cari Produk Nego</span>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($negotiations as $item): ?>
                            <article class="negotiation-card bg-white border border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.03)]"
                                data-status="<?= htmlspecialchars($item['status_key'], ENT_QUOTES, 'UTF-8') ?>"
                                data-timestamp="<?= (int)$item['timestamp'] ?>"
                                data-price="<?= (int)$item['nominal_harga'] ?>"
                                data-title="<?= htmlspecialchars(strtolower($item['nama_produk']), ENT_QUOTES, 'UTF-8') ?>"
                                data-store="<?= htmlspecialchars(strtolower($item['toko']), ENT_QUOTES, 'UTF-8') ?>"
                                data-code="<?= htmlspecialchars(strtolower($item['code']), ENT_QUOTES, 'UTF-8') ?>"
                                data-search="<?= htmlspecialchars(strtolower($item['nama_produk'] . ' ' . $item['toko'] . ' ' . $item['code']), ENT_QUOTES, 'UTF-8') ?>">

                                <!-- Header Kartu: ID, Tanggal, Toko, Status -->
                                <div class="px-5 py-3.5 border-b border-gray-200/80 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="font-bold text-[#272727]">
                                                ID Negosiasi: <?= htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <button type="button"
                                                class="copy-code-btn text-gray-400 hover:text-[#272727] transition cursor-pointer inline-flex items-center"
                                                data-code="<?= htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8') ?>"
                                                title="Salin ID Negosiasi"
                                                aria-label="Salin ID <?= htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="ph ph-copy text-sm" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <span aria-hidden="true" class="text-gray-300">|</span>
                                        <span class="text-gray-500">
                                            <?= htmlspecialchars($item['waktu'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span aria-hidden="true" class="text-gray-300">|</span>
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-[#272727]">
                                            <i class="ph ph-storefront text-sm text-gray-500" aria-hidden="true"></i>
                                            <span><?= htmlspecialchars($item['toko'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                    </div>

                                    <!-- Status Badge Text -->
                                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold <?= htmlspecialchars($item['status_color'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (!empty($item['status_icon'])): ?>
                                            <i class="ph <?= htmlspecialchars($item['status_icon'], ENT_QUOTES, 'UTF-8') ?> text-sm" aria-hidden="true"></i>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($item['status_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>

                                <!-- Body Kartu: Gambar, Info Produk, Penawaran Terakhir, Tombol Aksi -->
                                <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div class="flex items-start gap-4 min-w-0 flex-1">
                                        <!-- Foto Produk / Placeholder Box -->
                                        <div class="w-16 h-16 sm:w-[72px] sm:h-[72px] bg-[#F4F4F4] border border-gray-200/60 rounded-[2px] shrink-0 overflow-hidden flex items-center justify-center">
                                            <?php if (!empty($item['foto']) && file_exists($item['foto'])): ?>
                                                <img src="<?= htmlspecialchars($item['foto'], ENT_QUOTES, 'UTF-8') ?>"
                                                    alt="<?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>"
                                                    class="w-full h-full object-cover" />
                                            <?php else: ?>
                                                <div class="w-full h-full bg-[#EFEFEF]"></div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Detail Produk & Status Pengajuan -->
                                        <div class="min-w-0 flex-1">
                                            <div>
                                                <span class="inline-block text-[10px] font-semibold px-2 py-0.5 rounded-[3px] leading-tight <?= htmlspecialchars($item['kondisi_badge_bg'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($item['kondisi'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                            <h3 class="mt-1 text-sm font-bold text-[#272727] leading-snug truncate">
                                                <?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>
                                            </h3>
                                            <div class="flex items-center gap-1.5 text-xs text-gray-500 mt-1">
                                                <i class="ph <?= htmlspecialchars($item['subteks_icon'], ENT_QUOTES, 'UTF-8') ?> text-gray-400 text-xs shrink-0" aria-hidden="true"></i>
                                                <span><?= htmlspecialchars($item['subteks'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                <span>Harga Produk: </span>
                                                <span class="line-through"><?= htmlspecialchars($item['harga_asli'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Kolom Harga Penawaran Terakhir & Tombol Lihat Detail -->
                                    <div class="flex items-center justify-between md:justify-end gap-6 shrink-0 pt-3 md:pt-0 border-t md:border-t-0 border-gray-100">
                                        <div class="text-left md:text-right">
                                            <p class="text-xs text-gray-500">Penawaran Terakhir</p>
                                            <p class="text-base font-extrabold text-[#272727] tabular-nums mt-0.5">
                                                <?= htmlspecialchars($item['penawaran_terakhir'], ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        </div>
                                        <a href="<?= htmlspecialchars($item['detail_url'], ENT_QUOTES, 'UTF-8') ?>"
                                            class="inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold px-4 py-2 rounded-[2px] transition shrink-0 cursor-pointer">
                                            <i class="ph ph-file-text text-sm font-bold" aria-hidden="true"></i>
                                            <span>Lihat Detail</span>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <!-- Empty State saat pencarian/filter tidak ditemukan -->
                        <div id="empty-state" class="hidden bg-white border border-gray-200/80 p-10 text-center rounded-[2px]">
                            <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
                                <i class="ph ph-chat-circle-dots text-2xl" aria-hidden="true"></i>
                            </div>
                            <h3 id="empty-state-title" class="text-sm font-bold text-[#272727]">Tidak Ada Negosiasi Ditemukan</h3>
                            <p id="empty-state-desc" class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                                Tidak ada data negosiasi yang sesuai dengan filter atau kata kunci pencarian kamu.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

            </section>
        </div>
    </main>

    <!-- Toast Notifikasi (Standar Loot-E: fixed bottom-6 left-6 dengan aksen border-l-4) -->
    <div id="nego-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg">
    </div>

    <!-- Footer Standar Loot-E -->
    <?php require_once 'components/footer.php'; ?>

    <!-- Script Interaktivitas UI Filter, Search, Sort & Copy -->
    <script>
        (function() {
            const tabButtons = document.querySelectorAll('.tab-filter-btn');
            const searchInput = document.getElementById('search-negotiation');
            const sortSelect = document.getElementById('sort-negotiation');
            const container = document.getElementById('negotiations-container');
            const emptyState = document.getElementById('empty-state');
            const emptyTitle = document.getElementById('empty-state-title');
            const emptyDesc = document.getElementById('empty-state-desc');
            const toast = document.getElementById('nego-toast');
            const copyButtons = document.querySelectorAll('.copy-code-btn');

            let activeFilter = 'semua';
            let toastTimer = null;

            const ACTIVE_CLASSES = ['bg-brand-yellow', 'border-brand-yellow', 'text-[#272727]', 'font-semibold'];
            const INACTIVE_CLASSES = ['bg-white', 'border-gray-200', 'text-[#374151]', 'hover:border-gray-300', 'hover:bg-gray-50', 'font-medium'];

            function showToast(message, type) {
                if (!toast || !message) return;
                toast.textContent = message;
                toast.classList.remove('hidden', 'border-brand-yellow', 'border-red-500');
                toast.classList.add(type === 'error' ? 'border-red-500' : 'border-brand-yellow');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function() {
                    toast.classList.add('hidden');
                }, 3500);
            }

            function getCards() {
                return container ? Array.from(container.querySelectorAll('.negotiation-card')) : [];
            }

            function sortCards(cards, mode) {
                if (!container) return;
                cards.sort(function(a, b) {
                    const timeA = Number(a.dataset.timestamp || 0);
                    const timeB = Number(b.dataset.timestamp || 0);
                    const priceA = Number(a.dataset.price || 0);
                    const priceB = Number(b.dataset.price || 0);

                    if (mode === 'terlama') return timeA - timeB;
                    if (mode === 'harga_tertinggi') return priceB - priceA;
                    if (mode === 'harga_terendah') return priceA - priceB;
                    return timeB - timeA; // 'terbaru'
                });

                cards.forEach(function(card) {
                    container.appendChild(card);
                });
            }

            // Filter dan Pencarian
            function applyFilters() {
                const cards = getCards();
                if (cards.length === 0) return;

                const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
                const sortMode = sortSelect ? sortSelect.value : 'terbaru';

                sortCards(cards, sortMode);

                let visibleCount = 0;
                cards.forEach(card => {
                    const status = card.getAttribute('data-status') || '';
                    const searchData = card.getAttribute('data-search') || '';

                    const matchesFilter = (activeFilter === 'semua') || (status === activeFilter);
                    const matchesSearch = !query || searchData.includes(query);

                    const shouldShow = matchesFilter && matchesSearch;
                    card.classList.toggle('hidden', !shouldShow);
                    if (shouldShow) visibleCount++;
                });

                if (emptyState) {
                    const isEmpty = visibleCount === 0;
                    emptyState.classList.toggle('hidden', !isEmpty);
                    if (isEmpty && emptyTitle && emptyDesc) {
                        if (query !== '') {
                            emptyTitle.textContent = 'Negosiasi tidak ditemukan';
                            emptyDesc.textContent = 'Tidak ada negosiasi yang cocok dengan kata kunci "' + searchInput.value.trim() + '".';
                        } else {
                            emptyTitle.textContent = 'Belum ada negosiasi pada status ini';
                            emptyDesc.textContent = 'Negosiasi yang sesuai dengan status terpilih akan muncul di sini.';
                        }
                    }
                }
            }

            function setActiveTab(key) {
                activeFilter = key;
                tabButtons.forEach(tab => {
                    const isSelected = tab.getAttribute('data-filter') === key;
                    tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    if (isSelected) {
                        tab.classList.remove(...INACTIVE_CLASSES);
                        tab.classList.add(...ACTIVE_CLASSES);
                    } else {
                        tab.classList.remove(...ACTIVE_CLASSES);
                        tab.classList.add(...INACTIVE_CLASSES);
                    }
                });
                applyFilters();
            }

            // Event Tab Click
            tabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    setActiveTab(this.getAttribute('data-filter') || 'semua');
                });
            });

            // Event Pencarian Live
            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }

            // Event Urutkan Change
            if (sortSelect) {
                sortSelect.addEventListener('change', applyFilters);
            }

            // Fitur Salin ID Negosiasi
            copyButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const code = this.getAttribute('data-code') || '';
                    if (!code) return;

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(code).then(() => {
                            showToast('ID Negosiasi ' + code + ' berhasil disalin.', 'info');
                        }).catch(() => {
                            fallbackCopy(code);
                        });
                    } else {
                        fallbackCopy(code);
                    }
                });
            });

            function fallbackCopy(text) {
                const tempInput = document.createElement('input');
                tempInput.value = text;
                document.body.appendChild(tempInput);
                tempInput.select();
                try {
                    document.execCommand('copy');
                    showToast('ID Negosiasi ' + text + ' berhasil disalin.', 'info');
                } catch (err) {
                    showToast('Gagal menyalin ID.', 'error');
                }
                document.body.removeChild(tempInput);
            }

            // Demo toast untuk verifikasi sekarang
            const demoButtons = document.querySelectorAll('[data-demo-toast]');
            demoButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const msg = this.getAttribute('data-demo-toast') || 'Fitur belum tersedia.';
                    showToast(msg);
                });
            });
        })();
    </script>
</body>

</html>
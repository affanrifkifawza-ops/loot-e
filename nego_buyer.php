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

                $negotiations[] = [
                    'id'                 => (int)$row['id'],
                    'code'               => $code,
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
                <div class="mt-6">
                    <div class="flex items-center gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Filter Status Negosiasi">
                        <?php foreach ($filterTabs as $tab): ?>
                            <?php $isActive = $tab['key'] === 'semua'; ?>
                            <button type="button"
                                data-filter="<?= htmlspecialchars($tab['key'], ENT_QUOTES, 'UTF-8') ?>"
                                role="tab"
                                aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                                class="tab-filter-btn px-4 py-2 text-xs sm:text-[13px] font-semibold transition shrink-0 rounded-[2px] border cursor-pointer <?= $isActive ? 'bg-brand-yellow text-[#272727] border-brand-yellow' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-200/90 font-medium' ?>">
                                <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Baris Pencarian dan Urutkan -->
                <div class="mt-4 flex items-center gap-3">
                    <div class="relative flex-1">
                        <i class="ph ph-magnifying-glass text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 text-sm" aria-hidden="true"></i>
                        <input type="text"
                            id="search-negotiation"
                            placeholder="Cari produk dalam negosiasi..."
                            class="w-full bg-white border border-gray-200/90 pl-9 pr-4 py-2 text-xs text-gray-800 placeholder-gray-400 rounded-[2px] focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow transition" />
                    </div>
                    <div class="shrink-0">
                        <div class="relative inline-block">
                            <select id="sort-negotiation"
                                aria-label="Urutkan Negosiasi"
                                class="appearance-none bg-white border border-gray-200/90 text-xs text-gray-700 px-3.5 py-2 pr-8 rounded-[2px] focus:outline-none focus:border-brand-yellow cursor-pointer font-medium">
                                <option value="terbaru">Terbaru</option>
                                <option value="terlama">Terlama</option>
                                <option value="harga_tertinggi">Harga Tertinggi</option>
                                <option value="harga_terendah">Harga Terendah</option>
                            </select>
                            <i class="ph ph-caret-down text-gray-500 absolute right-2.5 top-1/2 -translate-y-1/2 text-xs pointer-events-none" aria-hidden="true"></i>
                        </div>
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
                                    class="inline-flex items-center gap-2 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs px-5 py-2.5 rounded-[2px] transition shadow-xs">
                                    <i class="ph ph-magnifying-glass text-sm" aria-hidden="true"></i>
                                    <span>Cari Produk Nego</span>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($negotiations as $item): ?>
                            <article class="negotiation-card bg-white border border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.02)] transition hover:border-gray-300"
                                data-status="<?= htmlspecialchars($item['status_key'], ENT_QUOTES, 'UTF-8') ?>"
                                data-title="<?= htmlspecialchars(strtolower($item['nama_produk']), ENT_QUOTES, 'UTF-8') ?>"
                                data-store="<?= htmlspecialchars(strtolower($item['toko']), ENT_QUOTES, 'UTF-8') ?>"
                                data-code="<?= htmlspecialchars(strtolower($item['code']), ENT_QUOTES, 'UTF-8') ?>">

                                <!-- Header Kartu: ID, Tanggal, Toko, Status -->
                                <div class="px-5 py-3 border-b border-gray-200/80 flex flex-wrap items-center justify-between gap-y-2 gap-x-4 text-xs">
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-gray-500">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-gray-900 font-semibold">ID Negosiasi: <span class="font-bold text-[#272727]"><?= htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8') ?></span></span>
                                            <button type="button"
                                                class="copy-code-btn text-gray-400 hover:text-gray-700 transition cursor-pointer p-0.5"
                                                data-code="<?= htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8') ?>"
                                                title="Salin ID Negosiasi"
                                                aria-label="Salin ID <?= htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="ph ph-copy text-xs" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-gray-500">
                                            <i class="ph ph-calendar-blank text-gray-400 text-xs" aria-hidden="true"></i>
                                            <span><?= htmlspecialchars($item['waktu'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-gray-600 font-medium">
                                            <i class="ph ph-storefront text-gray-400 text-xs" aria-hidden="true"></i>
                                            <span><?= htmlspecialchars($item['toko'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>

                                    <!-- Status Badge Text -->
                                    <div class="flex items-center gap-1.5 <?= htmlspecialchars($item['status_color'], ENT_QUOTES, 'UTF-8') ?> font-semibold text-xs">
                                        <?php if (!empty($item['status_icon'])): ?>
                                            <i class="ph <?= htmlspecialchars($item['status_icon'], ENT_QUOTES, 'UTF-8') ?> text-xs" aria-hidden="true"></i>
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
                                                <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-[2px] <?= htmlspecialchars($item['kondisi_badge_bg'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($item['kondisi'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                            <h2 class="text-sm font-bold text-[#272727] leading-snug mt-1 truncate">
                                                <?= htmlspecialchars($item['nama_produk'], ENT_QUOTES, 'UTF-8') ?>
                                            </h2>
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
                                            <p class="text-[11px] text-gray-400 font-medium">Penawaran Terakhir</p>
                                            <p class="text-base sm:text-lg font-extrabold text-[#272727] tabular-nums mt-0.5">
                                                <?= htmlspecialchars($item['penawaran_terakhir'], ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        </div>
                                        <a href="<?= htmlspecialchars($item['detail_url'], ENT_QUOTES, 'UTF-8') ?>"
                                            class="inline-flex items-center justify-center gap-1.5 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs font-bold px-4 py-2.5 rounded-[2px] transition shrink-0 cursor-pointer">
                                            <i class="ph ph-file-text text-sm font-bold" aria-hidden="true"></i>
                                            <span>Lihat Detail</span>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Empty State saat pencarian/filter tidak ditemukan -->
                    <div id="empty-state" class="hidden bg-white border border-gray-200/80 p-10 text-center rounded-[2px]">
                        <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
                            <i class="ph ph-chat-circle-dots text-2xl" aria-hidden="true"></i>
                        </div>
                        <h3 class="text-sm font-bold text-[#272727]">Tidak Ada Negosiasi Ditemukan</h3>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                            Tidak ada data negosiasi yang sesuai dengan filter atau kata kunci pencarian kamu.
                        </p>
                    </div>
                </div>

            </section>
        </div>
    </main>

    <!-- Footer Standar Loot-E -->
    <?php require_once 'components/footer.php'; ?>

    <!-- Toast Notifikasi Ringkas -->
    <div id="toast-nego"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden bg-[#272727] text-white px-4 py-2.5 rounded-[2px] text-xs font-semibold shadow-lg transition-all flex items-center gap-2"
        role="status"
        aria-live="polite">
        <i class="ph-bold ph-check text-brand-yellow text-sm" aria-hidden="true"></i>
        <span id="toast-nego-text">ID Negosiasi berhasil disalin!</span>
    </div>

    <!-- Script Interaktivitas UI Filter, Search, Sort & Copy -->
    <script>
        (function() {
            const tabButtons = document.querySelectorAll('.tab-filter-btn');
            const searchInput = document.getElementById('search-negotiation');
            const sortSelect = document.getElementById('sort-negotiation');
            const cards = Array.from(document.querySelectorAll('.negotiation-card'));
            const emptyState = document.getElementById('empty-state');
            const toast = document.getElementById('toast-nego');
            const toastText = document.getElementById('toast-nego-text');
            const copyButtons = document.querySelectorAll('.copy-code-btn');

            let activeFilter = 'semua';
            let searchQuery = '';
            let toastTimer = null;

            function showToast(message) {
                if (!toast) return;
                if (toastText) toastText.textContent = message;
                toast.classList.remove('hidden');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => {
                    toast.classList.add('hidden');
                }, 2500);
            }

            // Fitur Salin ID Negosiasi
            copyButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const code = this.getAttribute('data-code') || '';
                    if (!code) return;

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(code).then(() => {
                            showToast('ID Negosiasi ' + code + ' disalin ke clipboard!');
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
                    showToast('ID Negosiasi ' + text + ' disalin ke clipboard!');
                } catch (err) {
                    showToast('Gagal menyalin ID.');
                }
                document.body.removeChild(tempInput);
            }

            // Filter dan Pencarian
            function applyFilters() {
                let visibleCount = 0;

                cards.forEach(card => {
                    const status = card.getAttribute('data-status') || '';
                    const title = card.getAttribute('data-title') || '';
                    const store = card.getAttribute('data-store') || '';
                    const code = card.getAttribute('data-code') || '';

                    const matchesFilter = (activeFilter === 'semua') || (status === activeFilter);
                    const matchesSearch = !searchQuery ||
                        title.includes(searchQuery) ||
                        store.includes(searchQuery) ||
                        code.includes(searchQuery);

                    if (matchesFilter && matchesSearch) {
                        card.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        card.classList.add('hidden');
                    }
                });

                if (emptyState) {
                    if (visibleCount === 0) {
                        emptyState.classList.remove('hidden');
                    } else {
                        emptyState.classList.add('hidden');
                    }
                }
            }

            // Event Tab Click
            tabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    activeFilter = this.getAttribute('data-filter') || 'semua';

                    tabButtons.forEach(b => {
                        b.setAttribute('aria-selected', 'false');
                        b.classList.remove('bg-brand-yellow', 'text-[#272727]', 'border-brand-yellow', 'font-semibold');
                        b.classList.add('bg-white', 'text-gray-700', 'border-gray-200/90', 'font-medium');
                    });

                    this.setAttribute('aria-selected', 'true');
                    this.classList.remove('bg-white', 'text-gray-700', 'border-gray-200/90', 'font-medium');
                    this.classList.add('bg-brand-yellow', 'text-[#272727]', 'border-brand-yellow', 'font-semibold');

                    applyFilters();
                });
            });

            // Event Pencarian Live
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    searchQuery = this.value.trim().toLowerCase();
                    applyFilters();
                });
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
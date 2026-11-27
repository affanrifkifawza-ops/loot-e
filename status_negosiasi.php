<?php
require_once "auth.php";

if (!isLoggedIn()) {
    $redirectUrl = "status_negosiasi.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header("Location: login.php?redirect=" . urlencode($redirectUrl));
    exit;
}

$currentUser = getCurrentUser();
$navActivePage = 'negosiasi';

$negoIdParam   = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$produkIdParam = isset($_GET['produk_id']) && is_numeric($_GET['produk_id']) ? (int)$_GET['produk_id'] : null;
$tawaranParam  = isset($_GET['tawaran']) && is_numeric($_GET['tawaran']) ? (int)$_GET['tawaran'] : null;

$produkRow    = null;
$negoRow      = null;
$riwayatRows  = [];
$sellerStats  = [
    'rating_toko'       => null,
    'total_ulasan_toko' => 0,
    'transaksi_selesai' => 0,
    'total_transaksi'   => 0,
];

function formatTanggalIndo(string $dateStr, bool $withTime = false, bool $shortMonth = false): string
{
    $ts = strtotime($dateStr);
    if ($ts === false) {
        return $dateStr;
    }
    $monthsFull = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $monthsShort = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];
    $m = (int)date('n', $ts);
    $monthLabel = $shortMonth ? $monthsShort[$m] : $monthsFull[$m];
    $formatted = date('j', $ts) . ' ' . $monthLabel . ' ' . date('Y', $ts);
    if ($withTime) {
        $formatted .= ', ' . date('H:i', $ts) . ' WIB';
    }
    return $formatted;
}

function formatRingkasWaktu(string $dateStr): string
{
    $ts = strtotime($dateStr);
    if ($ts === false) {
        return $dateStr;
    }
    $monthsShort = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];
    $m = (int)date('n', $ts);
    return date('j', $ts) . ' ' . $monthsShort[$m] . ', ' . date('H:i', $ts) . ' WIB';
}

function ekstrakKota(?string $alamat): string
{
    $raw = trim((string)$alamat);
    if ($raw === '') {
        return 'Indonesia';
    }
    $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));
    return !empty($parts) ? end($parts) : $raw;
}

try {
    $db = getDbConnection();
    $sqlProdukBase = "
        SELECT p.*, k.nama_kategori,
               u.nama AS nama_penjual,
               u.alamat AS alamat_penjual,
               u.is_verified AS penjual_verified
        FROM produk p
        JOIN kategori k ON p.id_kategori = k.id
        JOIN pengguna u ON p.id_penjual = u.id
    ";

    // 1. Jika akses dengan ?id=<negosiasi.id>, muat negosiasi beserta produknya
    if ($negoIdParam !== null) {
        $stmtNego = $db->prepare("
            SELECT n.*
            FROM negosiasi n
            WHERE n.id = ? AND (n.id_pembeli = ? OR ? = 'admin')
            LIMIT 1
        ");
        $stmtNego->execute([$negoIdParam, (int)$currentUser['id'], $currentUser['peran']]);
        $negoRow = $stmtNego->fetch() ?: null;

        if ($negoRow) {
            $stmtProd = $db->prepare($sqlProdukBase . " WHERE p.id = ? AND p.bisa_nego = 1 LIMIT 1");
            $stmtProd->execute([(int)$negoRow['id_produk']]);
            $produkRow = $stmtProd->fetch() ?: null;
        }
    }

    // 2. Jika akses dengan ?produk_id=<produk.id>, muat produk yang bisa dinego
    if (!$produkRow && $produkIdParam !== null) {
        $stmtProd = $db->prepare($sqlProdukBase . " WHERE p.id = ? AND p.bisa_nego = 1 LIMIT 1");
        $stmtProd->execute([$produkIdParam]);
        $produkRow = $stmtProd->fetch() ?: null;

        if ($produkRow) {
            $stmtNego = $db->prepare("
                SELECT n.*
                FROM negosiasi n
                WHERE n.id_produk = ? AND n.id_pembeli = ?
                ORDER BY n.created_at DESC, n.id DESC
                LIMIT 1
            ");
            $stmtNego->execute([(int)$produkRow['id'], (int)$currentUser['id']]);
            $negoRow = $stmtNego->fetch() ?: null;
        }
    }

    // 3. Fallback jika tanpa parameter: cari negosiasi terbaru user atau produk bisa_nego pertama
    if (!$produkRow && $negoIdParam === null && $produkIdParam === null) {
        $stmtLastNego = $db->prepare("
            SELECT n.*
            FROM negosiasi n
            JOIN produk p ON n.id_produk = p.id
            WHERE n.id_pembeli = ? AND p.bisa_nego = 1
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT 1
        ");
        $stmtLastNego->execute([(int)$currentUser['id']]);
        $negoRow = $stmtLastNego->fetch() ?: null;

        if ($negoRow) {
            $stmtProd = $db->prepare($sqlProdukBase . " WHERE p.id = ? LIMIT 1");
            $stmtProd->execute([(int)$negoRow['id_produk']]);
            $produkRow = $stmtProd->fetch() ?: null;
        } else {
            $produkRow = $db->query($sqlProdukBase . " WHERE p.bisa_nego = 1 AND p.status_produk = 'tersedia' AND p.stok > 0 ORDER BY p.id ASC LIMIT 1")->fetch() ?: null;
        }
    }

    // Muat riwayat negosiasi dari DB jika sesi negosiasi sudah ada
    if ($negoRow) {
        $stmtRiwayat = $db->prepare("
            SELECT id, pengirim_tawaran, harga, waktu
            FROM riwayat_negosiasi
            WHERE id_negosiasi = ?
            ORDER BY waktu DESC, id DESC
        ");
        $stmtRiwayat->execute([(int)$negoRow['id']]);
        $riwayatRows = $stmtRiwayat->fetchAll();
    }

    // Muat statistik penjual dari DB
    if ($produkRow) {
        $idPenjual = (int)$produkRow['id_penjual'];
        $stmtSeller = $db->prepare("
            SELECT
                (SELECT AVG(ul.rating)
                 FROM ulasan ul
                 JOIN produk pr ON ul.id_produk = pr.id
                 WHERE pr.id_penjual = ?) AS rating_toko,
                (SELECT COUNT(ul.id)
                 FROM ulasan ul
                 JOIN produk pr ON ul.id_produk = pr.id
                 WHERE pr.id_penjual = ?) AS total_ulasan_toko,
                (SELECT COUNT(DISTINCT ps.id)
                 FROM detail_pesanan dp
                 JOIN pesanan ps ON dp.id_pesanan = ps.id
                 JOIN produk pr ON dp.id_produk = pr.id
                 WHERE pr.id_penjual = ? AND ps.status_pesanan = 'selesai') AS transaksi_selesai,
                (SELECT COUNT(DISTINCT ps.id)
                 FROM detail_pesanan dp
                 JOIN pesanan ps ON dp.id_pesanan = ps.id
                 JOIN produk pr ON dp.id_produk = pr.id
                 WHERE pr.id_penjual = ?) AS total_transaksi
        ");
        $stmtSeller->execute([$idPenjual, $idPenjual, $idPenjual, $idPenjual]);
        $sellerStats = $stmtSeller->fetch() ?: $sellerStats;
    }
} catch (PDOException $e) {
    $produkRow = null;
}

if (!$produkRow) {
    header("Location: search.php");
    exit;
}

// Hitung nilai tampilan berdasarkan data produk DB (+ data negosiasi DB jika tersedia)
$hargaAsli = (int)$produkRow['harga'];
$kategoriKode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $produkRow['nama_kategori']), 0, 5) ?: 'ITEM');

$tawaranPembeli = null;
$tawaranPenjual = null;
$waktuPembeli   = null;
$waktuPenjual   = null;

if (!empty($riwayatRows)) {
    foreach ($riwayatRows as $rw) {
        if ($rw['pengirim_tawaran'] === 'pembeli' && $tawaranPembeli === null) {
            $tawaranPembeli = (int)$rw['harga'];
            $waktuPembeli   = $rw['waktu'];
        }
        if ($rw['pengirim_tawaran'] === 'penjual' && $tawaranPenjual === null) {
            $tawaranPenjual = (int)$rw['harga'];
            $waktuPenjual   = $rw['waktu'];
        }
    }
}

// Jika belum ada row di DB (preview langsung dari detail_produk.php), selaraskan nominal dengan harga produk DB
if ($tawaranPembeli === null) {
    if ($tawaranParam !== null && $tawaranParam >= 1000 && $tawaranParam < $hargaAsli) {
        $tawaranPembeli = $tawaranParam;
    } else {
        $tawaranPembeli = max(1000, (int)(round(($hargaAsli * 0.85) / 1000) * 1000));
    }
}

if ($tawaranPenjual === null) {
    if ($negoRow && !empty($negoRow['harga_deal'])) {
        $tawaranPenjual = (int)$negoRow['harga_deal'];
    } else {
        $mid = (int)(round((($tawaranPembeli + $hargaAsli) / 2) / 1000) * 1000);
        $tawaranPenjual = ($mid > $tawaranPembeli && $mid < $hargaAsli) ? $mid : min($hargaAsli, $tawaranPembeli + 50000);
    }
}

$hematAwal   = max(0, $hargaAsli - $tawaranPembeli);
$persenAwal  = $hargaAsli > 0 ? number_format(($hematAwal / $hargaAsli) * 100, 1) . '%' : '0.0%';
$hematBalik  = max(0, $hargaAsli - $tawaranPenjual);
$persenBalik = $hargaAsli > 0 ? number_format(($hematBalik / $hargaAsli) * 100, 1) . '%' : '0.0%';

// Status & metadata negosiasi
$statusKey     = $negoRow['status'] ?? 'counter_offer';
$stokProduk    = (int)($produkRow['stok'] ?? 0);
$statusProduk  = (string)($produkRow['status_produk'] ?? 'tersedia');
$qtyNego       = max(1, (int)($negoRow['kuantitas'] ?? 1));

// Bedakan feedback status 'gagal' sesuai kondisi aktual produk & sisa stok (ADR-0002 / BR-03)
if ($statusProduk === 'habis' || $stokProduk <= 0) {
    $gagalMeta = [
        'badge'     => 'Gagal',
        'label'     => 'Penawaran Gagal (Produk Habis)',
        'judul'     => 'Produk Sudah Habis Terjual',
        'deskripsi' => 'Tawaranmu kalah bersaing dan produk ini sudah habis terjual.',
        'step2'     => 'done',
        'step3'     => 'pending',
    ];
} elseif ($statusProduk === 'nonaktif') {
    $gagalMeta = [
        'badge'     => 'Gagal',
        'label'     => 'Penawaran Gagal (Produk Nonaktif)',
        'judul'     => 'Produk Dinonaktifkan Penjual',
        'deskripsi' => 'Penjual sedang menonaktifkan produk ini untuk sementara sehingga sesi penawaran ditutup.',
        'step2'     => 'done',
        'step3'     => 'pending',
    ];
} elseif ($statusProduk === 'tersedia' && $stokProduk < $qtyNego) {
    $gagalMeta = [
        'badge'     => 'Gagal',
        'label'     => 'Penawaran Gagal (Stok Tidak Cukup)',
        'judul'     => 'Sisa Stok Tidak Mencukupi',
        'deskripsi' => 'Sisa stok produk saat ini (' . $stokProduk . ' unit) tidak lagi mencukupi jumlah unit pada tawaranmu (' . $qtyNego . ' unit). Kamu dapat mengajukan penawaran baru sesuai sisa stok yang tersedia.',
        'step2'     => 'done',
        'step3'     => 'pending',
    ];
} else {
    $gagalMeta = [
        'badge'     => 'Gagal',
        'label'     => 'Penawaran Gagal (Stok Kembali Tersedia)',
        'judul'     => 'Sesi Sebelumnya Gagal, Produk Kembali Tersedia',
        'deskripsi' => 'Tawaran pada sesi ini sebelumnya gagal saat stok sempat habis, namun saat ini produk tersedia kembali. Kamu dapat mengajukan penawaran baru.',
        'step2'     => 'done',
        'step3'     => 'pending',
    ];
}

$statusMap = [
    'pending' => [
        'badge'     => 'Menunggu Penjual',
        'label'     => 'Penawaran Menunggu Tinjauan Penjual',
        'judul'     => 'Menunggu Respons dari Penjual',
        'deskripsi' => 'Penawaran harga kamu telah dikirim ke penjual. Pantau halaman ini untuk melihat persetujuan atau penawaran balik dari penjual.',
        'step2'     => 'active',
        'step3'     => 'pending',
    ],
    'counter_offer' => [
        'badge'     => 'Penawaran Balik',
        'label'     => 'Penawaran Balik dari Penjual',
        'judul'     => 'Penjual Memberikan Penawaran Balik',
        'deskripsi' => 'Penjual memberikan harga baru untuk barang ini. Kamu dapat mengajukan penawaran harga kembali atau menolak kesepakatan ini.',
        'step2'     => 'active',
        'step3'     => 'pending',
    ],
    'diterima' => [
        'badge'     => 'Disetujui',
        'label'     => 'Penawaran Disetujui Penjual',
        'judul'     => 'Kesepakatan Harga Tercapai',
        'deskripsi' => 'Penjual telah menyetujui harga negosiasi. Pesanan telah dibuat dan stok dialokasikan untuk kamu; segera selesaikan pembayaran sebelum batas waktu berakhir.',
        'step2'     => 'done',
        'step3'     => 'done',
    ],
    'ditolak' => [
        'badge'     => 'Ditolak',
        'label'     => 'Penawaran Ditolak',
        'judul'     => 'Penawaran Harga Belum Disepakati',
        'deskripsi' => 'Penawaran pada sesi ini ditolak. Kamu masih dapat mengajukan penawaran harga baru yang lebih tinggi setelah masa jeda 30 menit selama produk tersedia.',
        'step2'     => 'done',
        'step3'     => 'pending',
    ],
    'expired' => [
        'badge'     => 'Kedaluwarsa',
        'label'     => 'Batas Waktu Negosiasi Habis',
        'judul'     => 'Sesi Negosiasi Telah Berakhir',
        'deskripsi' => 'Batas waktu konfirmasi negosiasi telah habis. Silakan ajukan penawaran harga baru jika produk masih tersedia.',
        'step2'     => 'done',
        'step3'     => 'pending',
    ],
    'gagal' => $gagalMeta,
];
$statusMeta = $statusMap[$statusKey] ?? $statusMap['counter_offer'];

// Hitung countdown dari batas_waktu DB jika ada, atau default 30 menit (sesuai kolom batas_waktu)
$countdownStr = '00:30:00';
if ($negoRow && !empty($negoRow['batas_waktu'])) {
    $sisaDetik = max(0, strtotime($negoRow['batas_waktu']) - time());
    $h = str_pad((string)floor($sisaDetik / 3600), 2, '0', STR_PAD_LEFT);
    $m = str_pad((string)floor(($sisaDetik % 3600) / 60), 2, '0', STR_PAD_LEFT);
    $s = str_pad((string)($sisaDetik % 60), 2, '0', STR_PAD_LEFT);
    $countdownStr = "{$h}:{$m}:{$s}";
}

$createdBase   = $negoRow['created_at'] ?? date('Y-m-d H:i:s');
$tanggalMulai  = formatTanggalIndo($createdBase, false, false);
$waktuStep1    = $waktuPembeli ? formatRingkasWaktu($waktuPembeli) : formatRingkasWaktu($createdBase);
$waktuStep2    = $waktuPenjual ? formatRingkasWaktu($waktuPenjual) : ($statusKey === 'pending' ? 'Sedang ditinjau' : formatRingkasWaktu($createdBase));
$waktuStep3    = $statusKey === 'diterima' ? 'Kesepakatan disetujui' : 'Menunggu konfirmasi';

// Riwayat penawaran dari DB atau simulasi selaras produk DB
$riwayatTampil = [];
if (!empty($riwayatRows)) {
    foreach ($riwayatRows as $idx => $rw) {
        $isPenjual = $rw['pengirim_tawaran'] === 'penjual';
        $riwayatTampil[] = [
            'aktor'    => $isPenjual ? 'penjual' : 'pembeli',
            'judul'    => $isPenjual ? 'Penjual memberikan penawaran balik' : 'Kamu mengajukan penawaran',
            'status'   => $isPenjual ? 'Penawaran Balik' : 'Terkirim',
            'is_badge' => $isPenjual && $idx === 0,
            'nominal'  => (int)$rw['harga'],
            'waktu'    => formatTanggalIndo($rw['waktu'], true, true),
            'pesan'    => $isPenjual
                ? 'Penawaran balik sebesar ' . formatRupiah((int)$rw['harga']) . ' untuk unit ' . $produkRow['nama_produk'] . '.'
                : 'Pengajuan penawaran harga sebesar ' . formatRupiah((int)$rw['harga']) . ' untuk ' . $produkRow['nama_produk'] . '.',
        ];
    }
} else {
    $riwayatTampil = [
        [
            'aktor'    => 'penjual',
            'judul'    => 'Penjual memberikan penawaran balik',
            'status'   => 'Penawaran Balik',
            'is_badge' => true,
            'nominal'  => $tawaranPenjual,
            'waktu'    => formatTanggalIndo($createdBase, true, true),
            'pesan'    => 'Halo, harga pasnya ' . formatRupiah($tawaranPenjual) . ' ya untuk unit ' . $produkRow['nama_produk'] . ' (' . $produkRow['kondisi'] . ').',
        ],
        [
            'aktor'    => 'pembeli',
            'judul'    => 'Kamu mengajukan penawaran',
            'status'   => 'Terkirim',
            'is_badge' => false,
            'nominal'  => $tawaranPembeli,
            'waktu'    => formatTanggalIndo($createdBase, true, true),
            'pesan'    => 'Halo gan, izin tawar ' . formatRupiah($tawaranPembeli) . '. Siap checkout via Rekber Loot-E hari ini.',
        ],
    ];
}

// Statistik penjual dari DB
$totalUlasanToko  = (int)($sellerStats['total_ulasan_toko'] ?? 0);
$ratingTokoText   = $totalUlasanToko > 0
    ? number_format((float)$sellerStats['rating_toko'], 1) . ' / 5.0'
    : '0.0 / 5.0';
$ulasanTokoLabel  = $totalUlasanToko > 0
    ? $totalUlasanToko . ' Ulasan Toko'
    : 'Belum ada ulasan';

$totalTransaksi   = (int)($sellerStats['total_transaksi'] ?? 0);
$transaksiSelesai = (int)($sellerStats['transaksi_selesai'] ?? 0);
$persenSukses     = $totalTransaksi > 0
    ? (int)round(($transaksiSelesai / $totalTransaksi) * 100) . '%'
    : '100%';

$idKodeNegosiasi = $negoRow
    ? '#NEG-' . $kategoriKode . '-' . str_pad((string)$negoRow['id'], 4, '0', STR_PAD_LEFT)
    : '#NEG-' . $kategoriKode . '-' . str_pad((string)$produkRow['id'], 4, '0', STR_PAD_LEFT);

$namaPenjual    = $produkRow['nama_penjual'];
$inisialPenjual = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $namaPenjual), 0, 1) ?: 'L');
$kotaPenjual    = ekstrakKota($produkRow['alamat_penjual'] ?? '');

$negosiasi = [
    'id_kode'          => $idKodeNegosiasi,
    'status_badge'     => $statusMeta['badge'],
    'status_label'     => $statusMeta['label'],
    'status_judul'     => $statusMeta['judul'],
    'status_deskripsi' => $statusMeta['deskripsi'],
    'berakhir_dalam'   => $countdownStr,
    'tanggal_mulai'    => $tanggalMulai,
    'metode_transaksi' => 'Rekber Loot-E Escrow',
    'produk'           => [
        'id'            => (int)$produkRow['id'],
        'nama'          => $produkRow['nama_produk'],
        'kategori'      => $produkRow['nama_kategori'],
        'foto'          => (string)($produkRow['foto'] ?? ''),
        'kondisi_badge' => $produkRow['kondisi'],
        'garansi_badge' => 'Garansi Toko 7 Hari',
        'harga_asli'    => $hargaAsli,
        'proteksi'      => 'Rekber Proteksi Loot-E',
    ],
    'penawaran'        => [
        'awal_pembeli'    => $tawaranPembeli,
        'hemat_awal'      => $hematAwal,
        'persen_awal'     => $persenAwal,
        'balik_penjual'   => $tawaranPenjual,
        'hemat_balik'     => $hematBalik,
        'persen_balik'    => $persenBalik,
        'catatan_penjual' => $riwayatTampil[0]['pesan'],
    ],
    'penjual'          => [
        'nama'             => $namaPenjual,
        'inisial'          => $inisialPenjual,
        'kota'             => $kotaPenjual,
        'online_terakhir'  => 'Penjual Terverifikasi',
        'verified'         => (int)($produkRow['penjual_verified'] ?? 0) === 1,
        'rating'           => $ratingTokoText,
        'total_ulasan'     => $ulasanTokoLabel,
        'transaksi_sukses' => $persenSukses,
        'garansi_toko'     => '7 hari pergantian unit apabila barang cacat pabrik atau tidak sesuai deskripsi.',
        'loote_guard'      => 'Uang tertahan di Rekber sampai Anda menekan konfirmasi terima barang.',
    ],
    'tahapan'          => [
        [
            'label' => "Penawaran\nDikirim",
            'waktu' => $waktuStep1,
            'state' => 'done',
            'icon'  => 'ph-bold ph-check',
        ],
        [
            'label' => 'Ditinjau Penjual',
            'waktu' => $waktuStep2,
            'state' => $statusMeta['step2'],
            'icon'  => $statusMeta['step2'] === 'done' ? 'ph-bold ph-check' : 'ph-bold ph-arrows-clockwise',
        ],
        [
            'label' => "Keputusan\nPenjual",
            'waktu' => $waktuStep3,
            'state' => $statusMeta['step3'],
            'icon'  => $statusMeta['step3'] === 'done' ? 'ph-bold ph-check' : 'ph ph-scales',
        ],
    ],
    'riwayat'          => $riwayatTampil,
];

$produk    = $negosiasi['produk'];
$penawaran = $negosiasi['penawaran'];
$penjual   = $negosiasi['penjual'];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Status Negosiasi - <?= htmlspecialchars($produk['nama']) ?> | Loot-E</title>

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
                            bg: '#F4F5F7'
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
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            outline: 2px solid var(--brand-yellow);
            outline-offset: 2px;
        }

        dialog::backdrop {
            background: rgba(39, 39, 39, 0.55);
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

<body class="bg-[#F4F5F7] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Header & Navigasi Utama -->
    <?php require_once 'components/navbar.php'; ?>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-10 py-8 sm:py-10 space-y-5">

        <!-- Judul Halaman -->
        <header class="pb-1">
            <h1 class="text-2xl sm:text-[28px] font-extrabold text-[#272727] tracking-tight leading-tight">
                Status Negosiasi
            </h1>
            <p class="text-xs sm:text-[13px] text-gray-500 mt-1">
                Pantau perkembangan penawaran harga kamu dengan penjual.
            </p>
        </header>

        <!-- Kartu Ringkasan Produk -->
        <section class="bg-white border border-gray-200/90 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-4 sm:gap-5 min-w-0">
                <!-- Thumbnail Produk -->
                <a href="detail_produk.php?id=<?= (int)$produk['id'] ?>" class="w-20 h-20 sm:w-[88px] sm:h-[88px] shrink-0 rounded-[4px] bg-[#F3F4F6] border border-gray-400/70 flex items-center justify-center overflow-hidden hover:border-brand-yellow transition">
                    <?php if (!empty($produk['foto'])): ?>
                        <img src="<?= htmlspecialchars($produk['foto']) ?>" alt="<?= htmlspecialchars($produk['nama']) ?>" class="w-full h-full object-contain" />
                    <?php else: ?>
                        <i class="ph <?= htmlspecialchars(kategoriIcon($produk['kategori'])) ?> text-3xl text-gray-300" aria-hidden="true"></i>
                    <?php endif; ?>
                </a>

                <!-- Detail Produk -->
                <div class="min-w-0 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="bg-[#F3F4F6] text-gray-600 text-[11px] font-medium px-2.5 py-0.5 rounded-[2px]">
                            <?= htmlspecialchars($produk['kondisi_badge']) ?>
                        </span>
                        <span class="bg-[#272727] text-white text-[11px] font-semibold px-2.5 py-0.5 rounded-[2px] inline-flex items-center gap-1">
                            <i class="ph ph-shield-check text-brand-yellow text-xs" aria-hidden="true"></i>
                            <?= htmlspecialchars($produk['garansi_badge']) ?>
                        </span>
                    </div>

                    <h2 class="text-sm sm:text-[15px] font-bold text-[#272727] leading-snug">
                        <a href="detail_produk.php?id=<?= (int)$produk['id'] ?>" class="hover:underline">
                            <?= htmlspecialchars($produk['nama']) ?>
                        </a>
                    </h2>

                    <div class="flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-1 font-semibold text-[#272727]">
                            <i class="ph ph-storefront text-gray-500 text-xs" aria-hidden="true"></i>
                            <?= htmlspecialchars($penjual['nama']) ?>
                        </span>
                        <span class="text-gray-300">&bull;</span>
                        <span class="inline-flex items-center gap-1 text-gray-500">
                            <i class="ph ph-map-pin text-gray-400 text-xs" aria-hidden="true"></i>
                            <?= htmlspecialchars($penjual['kota']) ?>
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5 text-[11px] font-medium text-gray-700 pt-0.5">
                        <i class="ph-fill ph-shield-check text-brand-yellow text-sm" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($produk['proteksi']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Harga Asli Produk -->
            <div class="sm:text-right shrink-0 border-t sm:border-t-0 border-gray-100 pt-3 sm:pt-0">
                <p class="text-[11px] text-gray-400 font-medium">Harga Asli Produk</p>
                <p class="text-lg sm:text-[22px] font-extrabold text-[#272727] tracking-tight leading-tight mt-0.5">
                    <?= formatRupiah((int)$produk['harga_asli']) ?>
                </p>
                <p class="text-[10px] text-gray-400 mt-0.5">Belum termasuk ongkir</p>
            </div>
        </section>

        <!-- Konten Utama 2 Kolom -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

            <!-- Kolom Kiri (8 Kolom): Perkembangan Negosiasi & Riwayat Penawaran -->
            <div class="lg:col-span-8 space-y-5">

                <!-- Kartu 1: Perkembangan Negosiasi -->
                <section class="bg-white border border-gray-200/90 p-5 sm:p-6">
                    <!-- Header Kartu -->
                    <div class="flex flex-wrap items-center justify-between gap-2 pb-6">
                        <h2 class="text-sm sm:text-[15px] font-bold text-[#272727] inline-flex items-center gap-2">
                            <i class="ph ph-trend-up text-base text-[#272727]" aria-hidden="true"></i>
                            Perkembangan Negosiasi
                        </h2>
                        <p class="text-[11px] text-gray-400">
                            ID Negosiasi: <span class="font-bold text-[#272727]"><?= htmlspecialchars($negosiasi['id_kode']) ?></span>
                        </p>
                    </div>

                    <!-- Stepper 3 Tahap -->
                    <div class="px-2 sm:px-6 pt-1 pb-2">
                        <div class="relative">
                            <!-- Garis Penghubung Horizontal -->
                            <div class="grid grid-cols-2 absolute top-[17px] left-[16.666%] right-[16.666%] z-0" aria-hidden="true">
                                <div class="h-[2px] bg-brand-yellow"></div>
                                <div class="h-[2px] <?= $statusMeta['step3'] === 'done' ? 'bg-brand-yellow' : 'bg-gray-200' ?>"></div>
                            </div>

                            <ol class="relative z-10 grid grid-cols-3 gap-2 sm:gap-4 text-center">
                                <?php foreach ($negosiasi['tahapan'] as $step): ?>
                                    <?php
                                    $boxClass = 'bg-[#F3F4F6] text-gray-400';
                                    $titleClass = 'font-semibold text-gray-500';
                                    if ($step['state'] === 'done') {
                                        $boxClass = 'bg-[#272727] text-brand-yellow';
                                        $titleClass = 'font-bold text-[#272727]';
                                    } elseif ($step['state'] === 'active') {
                                        $boxClass = 'bg-brand-yellow text-[#272727]';
                                        $titleClass = 'font-bold text-[#272727]';
                                    }
                                    ?>
                                    <li class="flex flex-col items-center">
                                        <div class="w-9 h-9 rounded-lg flex items-center justify-center <?= $boxClass ?>">
                                            <i class="<?= htmlspecialchars($step['icon']) ?> text-sm" aria-hidden="true"></i>
                                        </div>
                                        <p class="text-[11px] sm:text-xs <?= $titleClass ?> mt-2.5 leading-snug whitespace-pre-line"><?= htmlspecialchars($step['label']) ?></p>
                                        <p class="text-[10px] text-gray-400 mt-1"><?= htmlspecialchars($step['waktu']) ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    </div>

                    <hr class="border-gray-100 my-5" />

                    <!-- Badge Status & Countdown -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
                        <span class="bg-brand-yellow text-[#272727] text-[11px] font-bold px-2.5 py-0.5 rounded-[2px] inline-flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#272727]" aria-hidden="true"></span>
                            <?= htmlspecialchars($negosiasi['status_label']) ?>
                        </span>
                        <p class="text-[11px] text-gray-500">
                            Berakhir dalam:
                            <span id="nego-countdown" class="font-bold text-red-600 tabular-nums"><?= htmlspecialchars($negosiasi['berakhir_dalam']) ?></span>
                        </p>
                    </div>

                    <!-- Judul & Deskripsi Status -->
                    <h3 class="text-base sm:text-[17px] font-bold text-[#272727] leading-snug">
                        <?= htmlspecialchars($negosiasi['status_judul']) ?>
                    </h3>
                    <p class="text-xs text-gray-500 leading-relaxed mt-1">
                        <?= htmlspecialchars($negosiasi['status_deskripsi']) ?>
                    </p>

                    <!-- Perbandingan Harga Penawaran -->
                    <div class="bg-[#F4F5F7] p-4 sm:p-5 mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <p class="text-[11px] text-gray-500 font-medium">Penawaran Awal Kamu:</p>
                            <p class="text-sm sm:text-base font-bold text-gray-400 line-through mt-1">
                                <?= formatRupiah((int)$penawaran['awal_pembeli']) ?>
                            </p>
                            <p class="text-[11px] text-gray-400 mt-0.5">
                                Hemat <?= formatRupiah((int)$penawaran['hemat_awal']) ?> (<?= htmlspecialchars($penawaran['persen_awal']) ?>)
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] text-[#272727] font-semibold">Harga Penawaran Balik Penjual:</p>
                            <p class="text-lg sm:text-[22px] font-extrabold text-[#272727] leading-tight mt-0.5">
                                <?= formatRupiah((int)$penawaran['balik_penjual']) ?>
                            </p>
                            <p class="text-[11px] text-gray-600 font-medium mt-0.5">
                                Hemat <?= formatRupiah((int)$penawaran['hemat_balik']) ?> (<?= htmlspecialchars($penawaran['persen_balik']) ?> dari harga asli)
                            </p>
                        </div>
                    </div>

                    <!-- Catatan Penjual -->
                    <blockquote class="border-l-2 border-brand-yellow pl-3.5 py-1 mt-4 text-xs italic text-gray-500 leading-relaxed">
                        &ldquo;<?= htmlspecialchars($penawaran['catatan_penjual']) ?>&rdquo;
                    </blockquote>

                    <!-- Tombol Aksi Utama -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-5">
                        <?php if ($statusKey === 'gagal'): ?>
                            <?php if ($statusProduk === 'tersedia' && $stokProduk > 0): ?>
                                <a href="detail_produk.php?id=<?= (int)$produk['id'] ?>"
                                    class="bg-brand-yellow hover:brightness-95 text-[#272727] text-xs font-bold px-5 py-2.5 flex-1 inline-flex items-center justify-center gap-2 transition active:scale-[0.99]">
                                    <i class="ph ph-tag text-sm" aria-hidden="true"></i>
                                    Ajukan Penawaran Baru (Sisa Stok: <?= $stokProduk ?>)
                                </a>
                            <?php endif; ?>
                            <a href="search.php"
                                class="bg-[#272727] hover:bg-black text-white text-xs font-bold px-5 py-2.5 inline-flex items-center justify-center gap-2 transition active:scale-[0.99]">
                                <i class="ph ph-magnifying-glass text-sm" aria-hidden="true"></i>
                                Cari Produk Serupa
                            </a>
                        <?php else: ?>
                            <button type="button" id="btn-ajukan-harga-baru"
                                class="bg-[#272727] hover:bg-black text-white text-xs font-bold px-4 py-2.5 inline-flex items-center justify-center gap-2 transition active:scale-[0.99] cursor-pointer">
                                <i class="ph ph-tag text-sm" aria-hidden="true"></i>
                                Ajukan Harga Baru
                            </button>
                            <button type="button" id="btn-terima-checkout"
                                class="bg-brand-yellow hover:brightness-95 text-[#272727] text-xs font-bold px-5 py-2.5 flex-1 inline-flex items-center justify-center gap-2 transition active:scale-[0.99] cursor-pointer">
                                <i class="ph ph-shopping-bag text-sm" aria-hidden="true"></i>
                                Terima &amp; Checkout (<?= formatRupiah((int)$penawaran['balik_penjual']) ?>)
                            </button>
                            <button type="button" id="btn-tolak-penawaran"
                                class="bg-[#F00000] hover:bg-red-700 text-white text-xs font-bold px-5 py-2.5 inline-flex items-center justify-center transition active:scale-[0.99] cursor-pointer">
                                Tolak Penawaran
                            </button>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Kartu 2: Riwayat Penawaran -->
                <section class="bg-white border border-gray-200/90 p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                        <h2 class="text-sm sm:text-[15px] font-bold text-[#272727] inline-flex items-center gap-2">
                            <i class="ph ph-clock-counter-clockwise text-base text-[#272727]" aria-hidden="true"></i>
                            Riwayat Penawaran
                        </h2>
                        <span class="text-[11px] text-gray-400">Catatan resmi negosiasi</span>
                    </div>

                    <div class="space-y-3.5">
                        <?php foreach ($negosiasi['riwayat'] as $item): ?>
                            <article class="bg-[#F4F5F7] border border-gray-200/70 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs font-bold text-[#272727] inline-flex items-center gap-2">
                                        <i class="ph <?= $item['aktor'] === 'penjual' ? 'ph-storefront' : 'ph-user' ?> text-sm text-[#272727]" aria-hidden="true"></i>
                                        <?= htmlspecialchars($item['judul']) ?>
                                    </p>
                                    <?php if ($item['is_badge']): ?>
                                        <span class="bg-brand-yellow text-[#272727] text-[10px] font-bold px-2.5 py-0.5 rounded-[2px]">
                                            <?= htmlspecialchars($item['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[11px] text-gray-400 font-medium">
                                            <?= htmlspecialchars($item['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-wrap items-baseline justify-between gap-2 mt-2.5">
                                    <p class="text-sm sm:text-[15px] font-extrabold text-[#272727]">
                                        <?= formatRupiah((int)$item['nominal']) ?>
                                    </p>
                                    <time class="text-[11px] text-gray-400">
                                        <?= htmlspecialchars($item['waktu']) ?>
                                    </time>
                                </div>

                                <div class="bg-white border border-gray-200/80 px-3 py-2.5 mt-2.5 text-xs text-gray-500 leading-relaxed">
                                    &ldquo;<?= htmlspecialchars($item['pesan']) ?>&rdquo;
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

            </div>

            <!-- Kolom Kanan (4 Kolom): Ringkasan, Informasi Penjual, Bantuan, Proteksi -->
            <aside class="lg:col-span-4 space-y-4">

                <!-- Kartu 1: Ringkasan Negosiasi -->
                <section class="bg-white border border-gray-200/90 p-5">
                    <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                        <h2 class="text-sm font-bold text-[#272727]">Ringkasan Negosiasi</h2>
                        <i class="ph ph-receipt text-gray-400 text-base" aria-hidden="true"></i>
                    </div>

                    <dl class="divide-y divide-gray-100 text-xs">
                        <div class="flex items-center justify-between gap-2 py-2.5">
                            <dt class="text-gray-500">Harga Asli Produk</dt>
                            <dd class="font-bold text-[#272727]"><?= formatRupiah((int)$produk['harga_asli']) ?></dd>
                        </div>
                        <div class="flex items-center justify-between gap-2 py-2.5">
                            <dt class="text-gray-500">Penawaran Kamu</dt>
                            <dd class="font-bold text-[#272727]"><?= formatRupiah((int)$penawaran['awal_pembeli']) ?></dd>
                        </div>
                        <div class="flex items-center justify-between gap-2 py-2.5">
                            <dt class="text-gray-500">Penawaran Penjual</dt>
                            <dd class="font-bold text-[#272727]"><?= formatRupiah((int)$penawaran['balik_penjual']) ?></dd>
                        </div>
                        <div class="flex items-center justify-between gap-2 py-2.5">
                            <dt class="text-gray-500">Status Negosiasi</dt>
                            <dd>
                                <span class="bg-brand-yellow text-[#272727] text-[10px] font-bold px-2.5 py-0.5 rounded-[2px]">
                                    <?= htmlspecialchars($negosiasi['status_badge']) ?>
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2 py-2.5">
                            <dt class="text-gray-500">Metode Transaksi</dt>
                            <dd class="font-bold text-[#272727] inline-flex items-center gap-1">
                                <i class="ph ph-lock-simple text-xs text-[#272727]" aria-hidden="true"></i>
                                <?= htmlspecialchars($negosiasi['metode_transaksi']) ?>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2 py-2.5">
                            <dt class="text-gray-500">Tanggal Mulai</dt>
                            <dd class="font-medium text-gray-600"><?= htmlspecialchars($negosiasi['tanggal_mulai']) ?></dd>
                        </div>
                    </dl>

                    <!-- Harga Penawaran Aktif -->
                    <div class="bg-[#F4F5F7] p-3.5 mt-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">
                            HARGA PENAWARAN AKTIF
                        </p>
                        <div class="flex items-baseline justify-between gap-2 mt-1">
                            <p class="text-base sm:text-lg font-extrabold text-[#272727]">
                                <?= formatRupiah((int)$penawaran['balik_penjual']) ?>
                            </p>
                            <span class="text-[11px] font-bold text-[#272727]">
                                Hemat <?= formatRupiah((int)$penawaran['hemat_balik']) ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-500 leading-relaxed mt-1.5">
                            Menunggu keputusan Anda untuk menerima atau menolak harga balik ini.
                        </p>
                    </div>
                </section>

                <!-- Kartu 2: Informasi Penjual -->
                <section class="bg-white border border-gray-200/90 p-5">
                    <div class="flex items-center justify-between pb-3.5 border-b border-gray-100">
                        <h2 class="text-sm font-bold text-[#272727]">Informasi Penjual</h2>
                        <i class="ph ph-storefront text-gray-400 text-base" aria-hidden="true"></i>
                    </div>

                    <div class="flex items-center gap-3 mt-4">
                        <div class="w-10 h-10 rounded-full bg-[#272727] text-white flex items-center justify-center font-bold text-sm shrink-0">
                            <?= htmlspecialchars($penjual['inisial']) ?>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-[#272727] inline-flex items-center gap-1.5">
                                <span class="truncate"><?= htmlspecialchars($penjual['nama']) ?></span>
                                <?php if ($penjual['verified']): ?>
                                    <i class="ph-fill ph-seal-check text-brand-yellow text-sm shrink-0" title="Verified Seller" aria-hidden="true"></i>
                                <?php endif; ?>
                            </p>
                            <p class="text-[11px] text-gray-400 mt-0.5">
                                <?= htmlspecialchars($penjual['kota']) ?> &bull; <?= htmlspecialchars($penjual['online_terakhir']) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Statistik Toko -->
                    <div class="grid grid-cols-2 gap-2.5 mt-4">
                        <div class="bg-[#F4F5F7] py-3 px-2 text-center">
                            <p class="text-xs sm:text-[13px] font-extrabold text-[#272727] inline-flex items-center justify-center gap-1">
                                <i class="ph-fill ph-star text-brand-yellow text-xs" aria-hidden="true"></i>
                                <?= htmlspecialchars($penjual['rating']) ?>
                            </p>
                            <p class="text-[10px] text-gray-400 mt-0.5"><?= htmlspecialchars($penjual['total_ulasan']) ?></p>
                        </div>
                        <div class="bg-[#F4F5F7] py-3 px-2 text-center">
                            <p class="text-xs sm:text-[13px] font-extrabold text-[#272727]">
                                <?= htmlspecialchars($penjual['transaksi_sukses']) ?>
                            </p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Transaksi Sukses</p>
                        </div>
                    </div>

                    <!-- Garansi & Loot-E Guard -->
                    <ul class="space-y-2.5 mt-4 pt-4 border-t border-gray-100 text-[11px] text-gray-500 leading-relaxed">
                        <li class="flex items-start gap-2">
                            <i class="ph ph-shield-check text-[#272727] text-sm shrink-0 mt-0.5" aria-hidden="true"></i>
                            <span>
                                <strong class="text-[#272727] font-semibold">Garansi Toko:</strong>
                                <?= htmlspecialchars($penjual['garansi_toko']) ?>
                            </span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="ph ph-lock-key text-[#272727] text-sm shrink-0 mt-0.5" aria-hidden="true"></i>
                            <span>
                                <strong class="text-[#272727] font-semibold">Loot-E Guard:</strong>
                                <?= htmlspecialchars($penjual['loote_guard']) ?>
                            </span>
                        </li>
                    </ul>
                </section>

                <!-- Kartu 3: Butuh Bantuan Negosiasi? -->
                <section class="bg-white border border-gray-200/90 p-5">
                    <div class="flex items-start gap-3">
                        <i class="ph ph-question text-gray-500 text-lg shrink-0 mt-0.5" aria-hidden="true"></i>
                        <div class="min-w-0">
                            <h2 class="text-xs font-bold text-[#272727]">Butuh Bantuan Negosiasi?</h2>
                            <p class="text-[11px] text-gray-500 leading-relaxed mt-1">
                                Punya pertanyaan seputar kesepakatan harga atau perlindungan garansi? Tim CS Loot-E siap melayani 24/7.
                            </p>
                            <a href="help.php" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#272727] hover:text-brand-yellow transition mt-2.5">
                                Hubungi Bantuan Loot-E
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>
                    </div>
                </section>

                <!-- Kartu 4: Rekber Loot-E Protection -->
                <section class="bg-gradient-to-br from-[#FFFDF2] via-[#FEF6D6] to-[#FDEEA8] border border-[#F5E08E] p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-xs font-bold text-[#272727] inline-flex items-center gap-1.5">
                            <i class="ph ph-shield-check text-[#272727] text-base" aria-hidden="true"></i>
                            Rekber Loot-E Protection
                        </h2>
                        <span class="bg-brand-yellow text-[#272727] text-[9px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-[2px]">
                            AMAN
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-600 leading-relaxed mt-2">
                        Transaksi menggunakan sistem perlindungan Loot-E untuk membantu menjaga keamanan proses jual beli.
                    </p>
                    <div class="grid grid-cols-2 gap-2 mt-3.5">
                        <div class="bg-white border border-[#F3E5AB] px-2.5 py-2 flex items-center gap-1.5">
                            <i class="ph ph-lock-simple text-[#272727] text-xs shrink-0" aria-hidden="true"></i>
                            <span class="text-[10px] font-bold text-[#272727] leading-tight">Garansi Uang Kembali</span>
                        </div>
                        <div class="bg-white border border-[#F3E5AB] px-2.5 py-2 flex items-center gap-1.5">
                            <i class="ph ph-clock-countdown text-[#272727] text-xs shrink-0" aria-hidden="true"></i>
                            <span class="text-[10px] font-bold text-[#272727] leading-tight">Cek Fisik 2x24 Jam</span>
                        </div>
                    </div>
                </section>

            </aside>
        </div>

    </main>

    <!-- Dialog Modal: Ajukan Harga Baru -->
    <dialog id="modal-harga-baru" class="p-6 bg-white rounded-xl border border-gray-300 shadow-2xl w-full max-w-md backdrop:bg-black/40">
        <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                <h3 class="text-base font-bold text-gray-900">Ajukan Harga Baru</h3>
                <button type="button" class="close-dialog text-gray-500 hover:text-gray-900 p-1 font-bold text-lg cursor-pointer" aria-label="Tutup dialog">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </div>
            <p class="text-xs text-gray-600">
                Ajukan penawaran harga baru untuk <strong><?= htmlspecialchars($produk['nama']) ?></strong>. Penawaran balik penjual saat ini: <?= formatRupiah((int)$penawaran['balik_penjual']) ?>.
            </p>
            <div>
                <label for="input-harga-baru" class="block text-xs font-semibold text-gray-700 mb-1">Nominal Tawaran Baru (Rp)</label>
                <input
                    type="number"
                    id="input-harga-baru"
                    placeholder="Contoh: <?= max(1000, (int)(round(($penawaran['balik_penjual'] * 0.95) / 1000) * 1000)) ?>"
                    min="1000"
                    max="<?= (int)$produk['harga_asli'] ?>"
                    class="w-full border border-gray-300 rounded-md p-2.5 text-sm text-gray-900 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow" />
            </div>
            <div>
                <label for="input-catatan-baru" class="block text-xs font-semibold text-gray-700 mb-1">Catatan untuk Penjual (Opsional)</label>
                <textarea
                    id="input-catatan-baru"
                    rows="2"
                    placeholder="Contoh: Bisa kurang sedikit lagi gan? Siap langsung checkout."
                    class="w-full border border-gray-300 rounded-md p-2.5 text-sm text-gray-900 focus:outline-none focus:border-brand-yellow focus:ring-1 focus:ring-brand-yellow"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="close-dialog px-4 py-2 border border-gray-300 rounded-md text-xs font-bold text-gray-700 hover:bg-gray-100 cursor-pointer">
                    Batal
                </button>
                <button type="button" id="submit-harga-baru" class="px-4 py-2 bg-brand-yellow rounded-md text-brand-dark text-xs font-bold hover:bg-yellow-400 cursor-pointer">
                    Kirim Tawaran Baru
                </button>
            </div>
        </div>
    </dialog>

    <!-- Notifikasi Toast Interaktif -->
    <div id="toast-msg" class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg max-w-sm" role="status" aria-live="polite">
        <div class="flex items-center gap-2">
            <i class="ph ph-check-circle text-brand-yellow text-base" aria-hidden="true"></i>
            <span id="toast-text">Status diperbarui</span>
        </div>
    </div>

    <!-- Footer Standar Loot-E -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function() {
            const countdownEl = document.getElementById('nego-countdown');
            if (countdownEl) {
                const parts = countdownEl.textContent.trim().split(':').map(Number);
                if (parts.length === 3 && parts.every(function(n) { return !isNaN(n); })) {
                    let remaining = parts[0] * 3600 + parts[1] * 60 + parts[2];
                    setInterval(function() {
                        if (remaining <= 0) return;
                        remaining -= 1;
                        const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
                        const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
                        const s = String(remaining % 60).padStart(2, '0');
                        countdownEl.textContent = h + ':' + m + ':' + s;
                    }, 1000);
                }
            }

            const modalHargaBaru = document.getElementById('modal-harga-baru');
            const btnAjukanBaru = document.getElementById('btn-ajukan-harga-baru');
            const btnTerima = document.getElementById('btn-terima-checkout');
            const btnTolak = document.getElementById('btn-tolak-penawaran');
            const submitHargaBaru = document.getElementById('submit-harga-baru');
            const inputHargaBaru = document.getElementById('input-harga-baru');
            const inputCatatanBaru = document.getElementById('input-catatan-baru');
            const closeButtons = document.querySelectorAll('.close-dialog');
            const toast = document.getElementById('toast-msg');
            const toastText = document.getElementById('toast-text');
            let toastTimer = null;

            function showToast(message) {
                if (!toast || !toastText) return;
                toastText.textContent = message;
                toast.classList.remove('hidden');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function() {
                    toast.classList.add('hidden');
                }, 3500);
            }

            if (btnAjukanBaru && modalHargaBaru) {
                btnAjukanBaru.addEventListener('click', function() {
                    modalHargaBaru.showModal();
                });
            }

            closeButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (modalHargaBaru && modalHargaBaru.open) modalHargaBaru.close();
                });
            });

            if (submitHargaBaru && inputHargaBaru && modalHargaBaru) {
                submitHargaBaru.addEventListener('click', function() {
                    const nominal = parseInt(inputHargaBaru.value, 10);
                    const maxHarga = parseInt(inputHargaBaru.getAttribute('max'), 10);
                    if (!nominal || nominal < 1000 || (maxHarga && nominal > maxHarga)) {
                        alert('Masukkan nominal tawaran yang valid (maksimal Rp ' + maxHarga.toLocaleString('id-ID') + ').');
                        return;
                    }
                    modalHargaBaru.close();
                    showToast('Simulasi UI: Tawaran baru Rp ' + nominal.toLocaleString('id-ID') + ' siap dikirim.');
                    inputHargaBaru.value = '';
                    if (inputCatatanBaru) inputCatatanBaru.value = '';
                });
            }

            if (btnTerima) {
                btnTerima.addEventListener('click', function() {
                    showToast('Simulasi UI: Penawaran diterima, mengarahkan ke halaman checkout.');
                });
            }

            if (btnTolak) {
                btnTolak.addEventListener('click', function() {
                    showToast('Simulasi UI: Penawaran balik ditolak.');
                });
            }
        })();
    </script>
</body>

</html>

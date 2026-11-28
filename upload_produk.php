<?php
// upload_produk.php - Create, Edit & Lanjutkan Draft Produk Seller (Terintegrasi MySQL PDO)
require_once "auth.php";

if (!isLoggedIn()) {
    $redirectTarget = "upload_produk.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header("Location: login.php?redirect=" . urlencode($redirectTarget));
    exit;
}

$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

// Sinkronkan session dengan data terbaru dari database & terapkan Guard BR-04
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

$_SESSION['nama']        = $userRow['nama'];
$_SESSION['email']       = $userRow['email'];
$_SESSION['peran']       = $userRow['peran'];
$_SESSION['is_verified'] = (int)$userRow['is_verified'];

if ((int)$userRow['is_verified'] !== 1) {
    $_SESSION['verifikasi_flash'] = [
        'type'    => 'error',
        'message' => 'Selesaikan verifikasi akun terlebih dahulu untuk menjual atau mengelola produk.',
    ];
    header("Location: verifikasi_akun.php");
    exit;
}

// Token CSRF khusus halaman upload produk
if (empty($_SESSION['csrf_upload_produk'])) {
    $_SESSION['csrf_upload_produk'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_upload_produk'];

// Ambil daftar kategori dinamis dari tabel kategori
$categories = $db->query("SELECT id, nama_kategori FROM kategori ORDER BY id ASC")->fetchAll();
$validKategoriIds = [];
foreach ($categories as $cat) {
    $validKategoriIds[(int)$cat['id']] = (string)$cat['nama_kategori'];
}

const ALLOWED_KONDISI_UPLOAD = [
    'Baru',
    'Bekas - Seperti Baru',
    'Bekas - Mulus',
    'Bekas - Ada Minus',
];
const MAX_UPLOAD_PRICE = 2000000000;
const MAX_UPLOAD_PHOTO_BYTES = 2 * 1024 * 1024;
const ALLOWED_UPLOAD_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

function safeDeleteLocalUpload(string $relativePath): void
{
    $clean = trim($relativePath);
    if ($clean === '') {
        return;
    }
    $uploadsDir = realpath(__DIR__ . '/uploads/produk');
    $targetFile = realpath(__DIR__ . '/' . ltrim($clean, '/\\'));
    if (
        $uploadsDir !== false
        && $targetFile !== false
        && str_starts_with($targetFile, $uploadsDir . DIRECTORY_SEPARATOR)
        && is_file($targetFile)
    ) {
        @unlink($targetFile);
    }
}

function detectImageMime(string $tmpPath): ?string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }
    $info = @getimagesize($tmpPath);
    return is_array($info) && !empty($info['mime']) ? (string)$info['mime'] : null;
}

// Deteksi mode Edit / Lanjutkan Draft jika ada ?id= atau POST produk_id
$rawEditId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['produk_id'] ?? $_GET['id'] ?? '')
    : ($_GET['id'] ?? '');
$editId = ctype_digit((string)$rawEditId) ? (int)$rawEditId : 0;

$existingProduct = null;
$existingPhotos = []; // [['id' => int, 'path_foto' => string]]
$existingPhotoMap = []; // key => path_foto

if ($editId > 0) {
    $stmtEdit = $db->prepare("
        SELECT id, id_kategori, nama_produk, foto, harga, stok, kondisi, deskripsi, bisa_nego, bisa_barter, status_produk
        FROM produk
        WHERE id = ? AND id_penjual = ?
        LIMIT 1
    ");
    $stmtEdit->execute([$editId, $userId]);
    $existingProduct = $stmtEdit->fetch() ?: null;

    if (!$existingProduct) {
        $_SESSION['katalog_flash'] = [
            'type'    => 'error',
            'message' => 'Produk tidak ditemukan atau bukan milik akun Anda.',
        ];
        header("Location: katalog.php");
        exit;
    }

    $stmtPhotos = $db->prepare("
        SELECT id, path_foto
        FROM produk_foto
        WHERE id_produk = ?
        ORDER BY urutan ASC, id ASC
    ");
    $stmtPhotos->execute([$editId]);
    $photoRows = $stmtPhotos->fetchAll();

    foreach ($photoRows as $row) {
        $pid = (int)$row['id'];
        $path = trim((string)$row['path_foto']);
        if ($path !== '') {
            $existingPhotos[] = ['id' => $pid, 'path' => $path];
            $existingPhotoMap['id_' . $pid] = $path;
        }
    }

    // Fallback untuk produk lama yang baru menyimpan path pada kolom produk.foto
    if (empty($existingPhotos) && !empty($existingProduct['foto'])) {
        $legacyPath = trim((string)$existingProduct['foto']);
        if ($legacyPath !== '') {
            $existingPhotos[] = ['id' => 0, 'path' => $legacyPath];
            $existingPhotoMap['id_0'] = $legacyPath;
        }
    }
}

$isEditMode  = $existingProduct !== null;
$isDraftEdit = $isEditMode && $existingProduct['status_produk'] === 'draft';
$isLiveEdit  = $isEditMode && !$isDraftEdit;

// Nilai awal form (dari DB saat Edit/Draft, atau kosong saat Tambah Baru)
$parsedExistingDesc = $isEditMode ? parseDeskripsiProduk($existingProduct['deskripsi'] ?? '') : [
    'ringkasan'   => '',
    'spesifikasi' => [],
    'riwayat'     => [],
];

$formValues = [
    'nama_produk' => $isEditMode ? (string)$existingProduct['nama_produk'] : '',
    'id_kategori' => $isEditMode ? (string)(int)$existingProduct['id_kategori'] : '',
    'kondisi'     => $isEditMode ? (string)$existingProduct['kondisi'] : '',
    'harga'       => $isEditMode ? (string)(int)$existingProduct['harga'] : '',
    'stok'        => $isEditMode ? (string)max(1, (int)$existingProduct['stok']) : '1',
    'bisa_nego'   => $isEditMode ? ((int)$existingProduct['bisa_nego'] === 1) : false,
    'bisa_barter' => $isEditMode ? ((int)$existingProduct['bisa_barter'] === 1) : false,
    'ringkasan'   => (string)($parsedExistingDesc['ringkasan'] ?? ''),
    'riwayat'     => [
        'fisik'       => (string)($parsedExistingDesc['riwayat']['fisik'] ?? ''),
        'fungsi'      => (string)($parsedExistingDesc['riwayat']['fungsi'] ?? ''),
        'lama_pakai'  => (string)($parsedExistingDesc['riwayat']['lama_pakai'] ?? ''),
        'servis'      => (string)($parsedExistingDesc['riwayat']['servis'] ?? ''),
        'minus'       => (string)($parsedExistingDesc['riwayat']['minus'] ?? ''),
        'kelengkapan' => (string)($parsedExistingDesc['riwayat']['kelengkapan'] ?? ''),
    ],
    'spesifikasi' => $parsedExistingDesc['spesifikasi'] ?? [],
];

$serverErrors = [];
$fieldErrors = [];

// Proses POST Create / Update Produk
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');

    // Kumpulkan input terbaru agar nilai tetap terjaga jika validasi gagal
    $formValues['nama_produk'] = trim((string)($_POST['nama_produk'] ?? ''));
    $formValues['id_kategori'] = trim((string)($_POST['id_kategori'] ?? ''));
    $formValues['kondisi']     = trim((string)($_POST['kondisi'] ?? ''));
    $formValues['harga']       = trim((string)($_POST['harga'] ?? ''));
    $formValues['stok']        = trim((string)($_POST['stok'] ?? ''));
    $formValues['bisa_nego']   = isset($_POST['bisa_nego']) && $_POST['bisa_nego'] === '1';
    $formValues['bisa_barter'] = isset($_POST['bisa_barter']) && $_POST['bisa_barter'] === '1';
    $formValues['ringkasan']   = trim((string)($_POST['ringkasan'] ?? ''));

    $rawRiwayat = is_array($_POST['riwayat'] ?? null) ? $_POST['riwayat'] : [];
    foreach (array_keys($formValues['riwayat']) as $rKey) {
        $formValues['riwayat'][$rKey] = trim((string)($rawRiwayat[$rKey] ?? ''));
    }

    $cleanedSpecs = [];
    $rawSpecs = is_array($_POST['spesifikasi'] ?? null) ? $_POST['spesifikasi'] : [];
    foreach ($rawSpecs as $row) {
        if (!is_array($row)) {
            continue;
        }
        $lbl = trim((string)($row['label'] ?? ''));
        $val = trim((string)($row['nilai'] ?? ''));
        if ($lbl !== '' && $val !== '') {
            $cleanedSpecs[] = ['label' => $lbl, 'nilai' => $val];
        }
    }
    $formValues['spesifikasi'] = $cleanedSpecs;

    // Tentukan status tujuan
    if ($isLiveEdit) {
        $targetStatus = $existingProduct['status_produk'] === 'nonaktif' ? 'nonaktif' : 'tersedia';
    } else {
        $postedStatus = (string)($_POST['status_produk'] ?? 'tersedia');
        $targetStatus = in_array($postedStatus, ['tersedia', 'draft'], true) ? $postedStatus : 'tersedia';
    }
    $isDraftSave = ($targetStatus === 'draft');

    if (!hash_equals($csrfToken, $postedToken)) {
        $serverErrors[] = 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {
        // Validasi 5 field inti (wajib baik untuk Tayang maupun Simpan Draft sesuai constraint DB NOT NULL)
        if ($formValues['nama_produk'] === '') {
            $fieldErrors['nama_produk'] = 'Nama produk wajib diisi.';
            $serverErrors[] = 'Nama produk wajib diisi.';
        } elseif (mb_strlen($formValues['nama_produk']) > 255) {
            $fieldErrors['nama_produk'] = 'Nama produk maksimal 255 karakter.';
            $serverErrors[] = 'Nama produk maksimal 255 karakter.';
        }

        $kategoriId = ctype_digit($formValues['id_kategori']) ? (int)$formValues['id_kategori'] : 0;
        if (!isset($validKategoriIds[$kategoriId])) {
            $fieldErrors['id_kategori'] = 'Pilih salah satu kategori produk yang tersedia.';
            $serverErrors[] = 'Kategori produk wajib dipilih.';
        }

        if (!in_array($formValues['kondisi'], ALLOWED_KONDISI_UPLOAD, true)) {
            $fieldErrors['kondisi'] = 'Pilih kondisi barang dari opsi yang tersedia.';
            $serverErrors[] = 'Kondisi barang wajib dipilih.';
        }

        $hargaInt = ctype_digit($formValues['harga']) ? (int)$formValues['harga'] : 0;
        if ($hargaInt <= 0) {
            $fieldErrors['harga'] = 'Harga wajib diisi dan lebih besar dari 0.';
            $serverErrors[] = 'Harga produk wajib lebih besar dari Rp 0.';
        } elseif ($hargaInt > MAX_UPLOAD_PRICE) {
            $fieldErrors['harga'] = 'Harga maksimal Rp 2.000.000.000.';
            $serverErrors[] = 'Harga melebihi batas maksimal Rp 2.000.000.000.';
        }

        $stokInt = ctype_digit($formValues['stok']) ? (int)$formValues['stok'] : 0;
        if ($stokInt < 1) {
            $fieldErrors['stok'] = 'Stok minimal 1 unit dan berupa angka bulat.';
            $serverErrors[] = 'Stok minimal 1 unit.';
        }

        // Validasi deskripsi lengkap (wajib untuk tayang & edit produk non-draft; opsional untuk draft)
        if (!$isDraftSave) {
            if ($formValues['ringkasan'] === '') {
                $fieldErrors['ringkasan'] = 'Ringkasan produk wajib diisi.';
                $serverErrors[] = 'Ringkasan produk wajib diisi.';
            }

            $riwayatLabelMap = [
                'fisik'       => 'Kondisi Fisik',
                'fungsi'      => 'Fungsi',
                'lama_pakai'  => 'Lama Pemakaian',
                'servis'      => 'Riwayat Servis',
                'minus'       => 'Minus / Defect',
                'kelengkapan' => 'Kelengkapan',
            ];
            foreach ($riwayatLabelMap as $rKey => $rLabel) {
                if ($formValues['riwayat'][$rKey] === '') {
                    $fieldErrors['riwayat_' . $rKey] = $rLabel . ' wajib diisi.';
                    $serverErrors[] = $rLabel . ' wajib diisi.';
                }
            }
        }

        // Normalisasi dan validasi file foto baru yang diunggah
        $uploadedFiles = [];
        if (isset($_FILES['foto_baru']) && is_array($_FILES['foto_baru']['name'])) {
            $fileCount = count($_FILES['foto_baru']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                $errCode = (int)($_FILES['foto_baru']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                $origName = (string)($_FILES['foto_baru']['name'][$i] ?? '');
                if ($errCode === UPLOAD_ERR_NO_FILE && $origName === '') {
                    continue;
                }
                if ($errCode !== UPLOAD_ERR_OK) {
                    $msg = ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE)
                        ? 'Salah satu foto melebihi batas ukuran server (maks 2 MB).'
                        : 'Gagal mengunggah foto "' . $origName . '". Silakan coba lagi.';
                    $fieldErrors['foto'] = $msg;
                    $serverErrors[] = $msg;
                    continue;
                }

                $tmpPath = (string)($_FILES['foto_baru']['tmp_name'][$i] ?? '');
                $size    = (int)($_FILES['foto_baru']['size'][$i] ?? 0);
                if ($size <= 0 || $size > MAX_UPLOAD_PHOTO_BYTES) {
                    $msg = 'File "' . $origName . '" melebihi batas 2 MB.';
                    $fieldErrors['foto'] = $msg;
                    $serverErrors[] = $msg;
                    continue;
                }

                $mime = detectImageMime($tmpPath);
                if ($mime === null || !isset(ALLOWED_UPLOAD_MIMES[$mime])) {
                    $msg = 'File "' . $origName . '" bukan gambar JPG, PNG, atau WEBP yang valid.';
                    $fieldErrors['foto'] = $msg;
                    $serverErrors[] = $msg;
                    continue;
                }

                $uploadedFiles[] = [
                    'tmp_name' => $tmpPath,
                    'ext'      => ALLOWED_UPLOAD_MIMES[$mime],
                ];
            }
        }

        // Susun urutan final foto dari input hidden foto_order (gabungan foto lama yang dipertahankan + foto baru)
        $orderRaw = (string)($_POST['foto_order'] ?? '');
        $orderDecoded = json_decode($orderRaw, true);
        $plannedSequence = [];
        $usedExistingKeys = [];
        $usedNewIndices = [];

        if (is_array($orderDecoded)) {
            foreach ($orderDecoded as $entry) {
                if (!is_array($entry) || empty($entry['type'])) {
                    continue;
                }
                if ($entry['type'] === 'existing' && isset($entry['id'])) {
                    $key = 'id_' . (int)$entry['id'];
                    if (isset($existingPhotoMap[$key]) && !isset($usedExistingKeys[$key])) {
                        $usedExistingKeys[$key] = true;
                        $plannedSequence[] = ['type' => 'existing', 'path' => $existingPhotoMap[$key]];
                    }
                } elseif ($entry['type'] === 'new' && isset($entry['idx'])) {
                    $idx = (int)$entry['idx'];
                    if (isset($uploadedFiles[$idx]) && !isset($usedNewIndices[$idx])) {
                        $usedNewIndices[$idx] = true;
                        $plannedSequence[] = ['type' => 'new', 'file' => $uploadedFiles[$idx]];
                    }
                }
            }
        } else {
            foreach ($existingPhotos as $ep) {
                $key = 'id_' . (int)$ep['id'];
                $usedExistingKeys[$key] = true;
                $plannedSequence[] = ['type' => 'existing', 'path' => $ep['path']];
            }
            foreach ($uploadedFiles as $idx => $uf) {
                $usedNewIndices[$idx] = true;
                $plannedSequence[] = ['type' => 'new', 'file' => $uf];
            }
        }

        // Minimal 1 foto wajib untuk tayang (draft boleh 0 foto)
        if (!$isDraftSave && count($plannedSequence) < 1 && !isset($fieldErrors['foto'])) {
            $fieldErrors['foto'] = 'Unggah minimal 1 foto produk agar dapat ditayangkan.';
            $serverErrors[] = 'Minimal 1 foto produk wajib diunggah.';
        }

        // Jika seluruh validasi lolos, pindahkan file baru lalu simpan ke DB dalam 1 transaksi
        if (empty($serverErrors)) {
            $uploadDirAbs = __DIR__ . '/uploads/produk';
            if (!is_dir($uploadDirAbs) && !mkdir($uploadDirAbs, 0755, true) && !is_dir($uploadDirAbs)) {
                $serverErrors[] = 'Gagal menyiapkan folder penyimpanan foto di server.';
            } else {
                $movedRelativePaths = [];
                $finalPhotoPaths = [];
                $moveFailed = false;

                foreach ($plannedSequence as $step) {
                    if ($step['type'] === 'existing') {
                        $finalPhotoPaths[] = $step['path'];
                    } else {
                        $newFileName = uniqid('prod_', true) . '.' . $step['file']['ext'];
                        $destAbs = $uploadDirAbs . DIRECTORY_SEPARATOR . $newFileName;
                        $destRel = 'uploads/produk/' . $newFileName;
                        if (!move_uploaded_file($step['file']['tmp_name'], $destAbs)) {
                            $moveFailed = true;
                            break;
                        }
                        $movedRelativePaths[] = $destRel;
                        $finalPhotoPaths[] = $destRel;
                    }
                }

                if ($moveFailed) {
                    foreach ($movedRelativePaths as $mp) {
                        safeDeleteLocalUpload($mp);
                    }
                    $serverErrors[] = 'Gagal menyimpan file foto ke server. Silakan coba lagi.';
                } else {
                    $riwayatPayload = [];
                    foreach ($formValues['riwayat'] as $rKey => $rVal) {
                        if ($rVal !== '') {
                            $riwayatPayload[$rKey] = $rVal;
                        }
                    }
                    $deskripsiJson = json_encode([
                        'ringkasan'   => $formValues['ringkasan'],
                        'spesifikasi' => $formValues['spesifikasi'],
                        'riwayat'     => $riwayatPayload,
                    ], JSON_UNESCAPED_UNICODE);

                    $coverPhoto = $finalPhotoPaths[0] ?? null;

                    try {
                        $db->beginTransaction();

                        if ($isEditMode) {
                            $stmtUpd = $db->prepare("
                                UPDATE produk
                                SET id_kategori = ?, nama_produk = ?, foto = ?, harga = ?, stok = ?,
                                    kondisi = ?, deskripsi = ?, bisa_nego = ?, bisa_barter = ?, status_produk = ?
                                WHERE id = ? AND id_penjual = ?
                            ");
                            $stmtUpd->execute([
                                $kategoriId,
                                $formValues['nama_produk'],
                                $coverPhoto,
                                $hargaInt,
                                $stokInt,
                                $formValues['kondisi'],
                                $deskripsiJson,
                                $formValues['bisa_nego'] ? 1 : 0,
                                $formValues['bisa_barter'] ? 1 : 0,
                                $targetStatus,
                                $editId,
                                $userId,
                            ]);
                            $savedProductId = $editId;
                        } else {
                            $stmtIns = $db->prepare("
                                INSERT INTO produk
                                    (id_penjual, id_kategori, nama_produk, foto, harga, stok, kondisi, deskripsi, bisa_nego, bisa_barter, status_produk)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmtIns->execute([
                                $userId,
                                $kategoriId,
                                $formValues['nama_produk'],
                                $coverPhoto,
                                $hargaInt,
                                $stokInt,
                                $formValues['kondisi'],
                                $deskripsiJson,
                                $formValues['bisa_nego'] ? 1 : 0,
                                $formValues['bisa_barter'] ? 1 : 0,
                                $targetStatus,
                            ]);
                            $savedProductId = (int)$db->lastInsertId();
                        }

                        // Sinkronkan tabel produk_foto sesuai urutan final
                        $stmtClearPhotos = $db->prepare("DELETE FROM produk_foto WHERE id_produk = ?");
                        $stmtClearPhotos->execute([$savedProductId]);

                        if (!empty($finalPhotoPaths)) {
                            $stmtInsPhoto = $db->prepare("
                                INSERT INTO produk_foto (id_produk, path_foto, urutan)
                                VALUES (?, ?, ?)
                            ");
                            foreach ($finalPhotoPaths as $idx => $photoPath) {
                                $stmtInsPhoto->execute([$savedProductId, $photoPath, $idx + 1]);
                            }
                        }

                        $db->commit();

                        // Hapus file lokal lama yang dibuang oleh seller saat edit
                        if ($isEditMode) {
                            $keptSet = array_flip($finalPhotoPaths);
                            foreach ($existingPhotoMap as $oldPath) {
                                if (!isset($keptSet[$oldPath])) {
                                    safeDeleteLocalUpload($oldPath);
                                }
                            }
                        }

                        $namaMsg = $formValues['nama_produk'];
                        if ($targetStatus === 'draft') {
                            $_SESSION['katalog_flash'] = [
                                'type'    => 'success',
                                'message' => "Draft produk \"{$namaMsg}\" berhasil disimpan.",
                            ];
                            header("Location: katalog.php?tab=draft");
                        } elseif ($isLiveEdit) {
                            $_SESSION['katalog_flash'] = [
                                'type'    => 'success',
                                'message' => "Perubahan pada produk \"{$namaMsg}\" berhasil disimpan.",
                            ];
                            header("Location: katalog.php?tab=" . urlencode($targetStatus));
                        } else {
                            $_SESSION['katalog_flash'] = [
                                'type'    => 'success',
                                'message' => "Produk \"{$namaMsg}\" berhasil ditayangkan ke etalase.",
                            ];
                            header("Location: katalog.php?tab=tersedia");
                        }
                        exit;
                    } catch (PDOException $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        foreach ($movedRelativePaths as $mp) {
                            safeDeleteLocalUpload($mp);
                        }
                        $serverErrors[] = 'Gagal menyimpan data produk ke database. Silakan coba lagi.';
                    }
                }
            }
        }
    }
}

$previewSellerName = (string)$userRow['nama'];
$previewSellerInitial = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $previewSellerName), 0, 1) ?: 'T');

$pageTitle = $isDraftEdit ? 'Lanjutkan Draft' : ($isLiveEdit ? 'Edit Produk' : 'Jual Barang');
$pageSubtitle = $isDraftEdit
    ? 'Lengkapi sisa detail produkmu lalu tayangkan ke etalase, atau simpan kembali sebagai draft.'
    : ($isLiveEdit
        ? 'Perbarui foto, harga, stok, atau rincian kondisi agar informasi produk selalu akurat bagi pembeli.'
        : 'Lengkapi informasi produk dengan jujur dan detail agar pembeli mudah menilai kondisi sebelum bertransaksi.');
$backHref = $isDraftEdit ? 'katalog.php?tab=draft' : 'katalog.php';
$primaryBtnLabel = $isDraftEdit ? 'Tayangkan Produk' : ($isLiveEdit ? 'Simpan Perubahan' : 'Submit Produk');
$primaryTargetStatus = $isLiveEdit
    ? ($existingProduct['status_produk'] === 'nonaktif' ? 'nonaktif' : 'tersedia')
    : 'tersedia';

$initialPhotosJs = array_map(static function (array $p): array {
    return [
        'kind' => 'existing',
        'id'   => (int)$p['id'],
        'path' => (string)$p['path'],
        'url'  => (string)$p['path'],
    ];
}, $existingPhotos);
$initialSpecsJs = $formValues['spesifikasi'];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Loot-E</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <script src="https://unpkg.com/@phosphor-icons/web" defer></script>
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

        a[href],
        button:not(:disabled),
        select:not(:disabled),
        label[for],
        input[type="checkbox"]:not(:disabled),
        input[type="radio"]:not(:disabled),
        summary {
            cursor: pointer;
        }

        button:disabled,
        input:disabled,
        select:disabled,
        textarea:disabled {
            cursor: not-allowed;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible,
        [tabindex]:focus-visible {
            outline: 2px solid var(--brand-yellow);
            outline-offset: 2px;
        }

        .field-error {
            border-color: #DC2626 !important;
            background-color: #FEF2F2;
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

<body class="bg-[#F4F5F7] font-sans text-[#272727] antialiased min-h-screen flex flex-col">

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">

        <!-- Tombol Kembali ke Katalog -->
        <div class="mb-3">
            <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" id="btn-back-katalog"
                class="inline-flex items-center gap-1.5 min-h-[44px] px-3 -ml-3 text-xs sm:text-sm font-semibold text-gray-600 hover:text-[#272727] rounded-md transition">
                <i class="ph-bold ph-arrow-left text-base" aria-hidden="true"></i>
                <span>Kembali ke Katalog</span>
            </a>
        </div>

        <!-- Page Heading -->
        <header class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 border-b border-gray-200 pb-5">
            <div>
                <h1 class="text-2xl sm:text-[28px] font-extrabold text-[#272727] tracking-tight leading-tight">
                    <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <p class="mt-1.5 text-sm text-gray-600 max-w-2xl">
                    <?= htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <p class="text-xs text-gray-600 shrink-0">
                <span class="text-red-600 font-bold" aria-hidden="true">*</span> Wajib diisi untuk tayang
            </p>
        </header>

        <!-- Error Summary Banner (Server & Client Validation) -->
        <div id="error-summary" class="<?= empty($serverErrors) ? 'hidden ' : '' ?>mb-6 bg-red-50 border border-red-200 text-red-800 p-4 rounded-md" role="alert" aria-live="assertive">
            <div class="flex items-start gap-2.5">
                <i class="ph-fill ph-warning-circle text-lg text-red-600 mt-0.5 shrink-0" aria-hidden="true"></i>
                <div class="flex-1">
                    <h2 class="text-sm font-bold text-red-900">Periksa kembali beberapa kolom berikut:</h2>
                    <ul id="error-summary-list" class="mt-1.5 list-disc list-inside text-xs space-y-1 text-red-800">
                        <?php foreach ($serverErrors as $errMsg): ?>
                            <li><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Workspace Grid: Form (8 cols) + Sticky Preview Card (4 cols) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- KOLOM KIRI: FORM UPLOAD / EDIT PRODUK -->
            <form id="product-upload-form"
                method="POST"
                action="<?= htmlspecialchars($isEditMode ? ('upload_produk.php?id=' . $editId) : 'upload_produk.php', ENT_QUOTES, 'UTF-8') ?>"
                enctype="multipart/form-data"
                class="lg:col-span-8 space-y-6"
                novalidate>

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                <?php if ($isEditMode): ?>
                    <input type="hidden" name="produk_id" value="<?= $editId ?>" />
                <?php endif; ?>
                <input type="hidden" id="status_produk" name="status_produk" value="<?= htmlspecialchars($primaryTargetStatus, ENT_QUOTES, 'UTF-8') ?>" />
                <input type="hidden" id="foto_order" name="foto_order" value="[]" />

                <!-- SECTION 1: Informasi Dasar Produk -->
                <section class="bg-white border border-gray-200/90 p-5 sm:p-6 shadow-2xs" aria-labelledby="sec-basic-title">
                    <div class="pb-4 mb-5 border-b border-gray-100">
                        <h2 id="sec-basic-title" class="text-base font-bold text-[#272727]">
                            1. Informasi Dasar Produk
                        </h2>
                        <p class="text-xs text-gray-600 mt-1">
                            Sertakan merek, seri, dan kapasitas utama agar produk mudah ditemukan saat dicari pembeli.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <!-- Nama Produk -->
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <label for="nama_produk" class="block text-xs font-semibold text-gray-800">
                                    Nama Produk <span class="text-red-600">*</span>
                                </label>
                                <span id="nama-counter" class="text-xs text-gray-500 tabular-nums">0 / 255</span>
                            </div>
                            <input type="text" id="nama_produk" name="nama_produk" maxlength="255" required
                                value="<?= htmlspecialchars($formValues['nama_produk'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Contoh: RTX 3060 12GB GDDR6 Dual Fan"
                                class="w-full min-h-[44px] px-3.5 py-2.5 text-sm bg-white border border-gray-300 rounded-md text-[#272727] placeholder:text-gray-400 focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none transition<?= isset($fieldErrors['nama_produk']) ? ' field-error' : '' ?>" />
                            <p id="err-nama_produk" class="<?= isset($fieldErrors['nama_produk']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600">
                                <?= htmlspecialchars($fieldErrors['nama_produk'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>

                        <!-- Kategori (Dinamis dari DB) + Kondisi Barang -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="id_kategori" class="block text-xs font-semibold text-gray-800 mb-1.5">
                                    Kategori <span class="text-red-600">*</span>
                                </label>
                                <select id="id_kategori" name="id_kategori" required
                                    class="w-full min-h-[44px] px-3.5 py-2.5 text-sm bg-white border border-gray-300 rounded-md text-[#272727] focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none transition<?= isset($fieldErrors['id_kategori']) ? ' field-error' : '' ?>">
                                    <option value="">Pilih kategori...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <?php
                                        $cId   = (int)$cat['id'];
                                        $cName = (string)$cat['nama_kategori'];
                                        $cIcon = kategoriIcon($cName);
                                        $selected = ((string)$cId === $formValues['id_kategori']) ? ' selected' : '';
                                        ?>
                                        <option value="<?= $cId ?>"
                                            data-name="<?= htmlspecialchars($cName, ENT_QUOTES, 'UTF-8') ?>"
                                            data-icon="<?= htmlspecialchars($cIcon, ENT_QUOTES, 'UTF-8') ?>"<?= $selected ?>>
                                            <?= htmlspecialchars($cName, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p id="err-id_kategori" class="<?= isset($fieldErrors['id_kategori']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600">
                                    <?= htmlspecialchars($fieldErrors['id_kategori'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>

                            <div>
                                <label for="kondisi" class="block text-xs font-semibold text-gray-800 mb-1.5">
                                    Kondisi Barang <span class="text-red-600">*</span>
                                </label>
                                <select id="kondisi" name="kondisi" required
                                    class="w-full min-h-[44px] px-3.5 py-2.5 text-sm bg-white border border-gray-300 rounded-md text-[#272727] focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none transition<?= isset($fieldErrors['kondisi']) ? ' field-error' : '' ?>">
                                    <option value="">Pilih kondisi barang</option>
                                    <?php foreach (ALLOWED_KONDISI_UPLOAD as $optKondisi): ?>
                                        <option value="<?= htmlspecialchars($optKondisi, ENT_QUOTES, 'UTF-8') ?>"<?= $formValues['kondisi'] === $optKondisi ? ' selected' : '' ?>>
                                            <?= htmlspecialchars($optKondisi, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p id="err-kondisi" class="<?= isset($fieldErrors['kondisi']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600">
                                    <?= htmlspecialchars($fieldErrors['kondisi'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SECTION 2: Foto Produk (Fleksibel 1..N foto, tersimpan di produk_foto + sampul di produk.foto) -->
                <section class="bg-white border border-gray-200/90 p-5 sm:p-6 shadow-2xs" aria-labelledby="sec-photo-title">
                    <div class="flex items-start justify-between gap-3 pb-4 mb-5 border-b border-gray-100">
                        <div>
                            <h2 id="sec-photo-title" class="text-base font-bold text-[#272727]">
                                2. Foto Produk <span class="text-red-600">*</span>
                            </h2>
                            <p class="text-xs text-gray-600 mt-1">
                                Bebas unggah sebanyak yang kamu butuhkan (minimal 1 foto untuk tayang, JPG/PNG/WEBP). Foto pertama otomatis menjadi sampul utama.
                            </p>
                        </div>
                        <span id="photo-count-badge" class="text-xs font-semibold text-gray-600 shrink-0 tabular-nums">
                            0 foto
                        </span>
                    </div>

                    <!-- Picker tidak punya name agar tidak terkirim ganda; foto_baru[] disinkronkan via DataTransfer saat submit -->
                    <input type="file" id="foto-picker" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" />
                    <input type="file" id="foto_baru" name="foto_baru[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" tabindex="-1" aria-hidden="true" />

                    <!-- Empty Dropzone (tampil saat 0 foto) -->
                    <label for="foto-picker" id="photo-empty-dropzone"
                        class="flex flex-col items-center justify-center w-full bg-[#FFFDF5] border-2 border-dashed border-brand-yellow rounded-lg px-5 py-9 text-center hover:bg-amber-50/70 transition">
                        <i class="ph-bold ph-plus text-3xl text-brand-yellow" aria-hidden="true"></i>
                        <span class="mt-2.5 text-sm font-bold text-[#272727]">
                            Unggah Foto Produk
                        </span>
                        <span class="mt-1 text-xs text-gray-600">
                            Tarik dan lepas di sini, atau klik untuk memilih satu atau beberapa foto sekaligus (maks 2 MB per foto).
                        </span>
                    </label>

                    <!-- Galeri Dinamis (tampil saat >= 1 foto + 1 tile Tambah Foto fleksibel di ujung) -->
                    <div id="photo-gallery-grid" class="hidden grid grid-cols-2 sm:grid-cols-4 gap-3" aria-label="Galeri foto produk"></div>

                    <p id="err-foto" class="<?= isset($fieldErrors['foto']) ? '' : 'hidden ' ?>mt-3 text-xs font-medium text-red-600" role="alert">
                        <?= htmlspecialchars($fieldErrors['foto'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p id="photo-tip" class="hidden mt-2.5 text-xs text-gray-600">
                        Klik foto lain untuk menjadikannya sampul utama di etalase. Maksimal 2 MB per foto.
                    </p>
                </section>

                <!-- SECTION 3: Harga, Stok & Opsi Transaksi -->
                <section class="bg-white border border-gray-200/90 p-5 sm:p-6 shadow-2xs" aria-labelledby="sec-pricing-title">
                    <div class="pb-4 mb-5 border-b border-gray-100">
                        <h2 id="sec-pricing-title" class="text-base font-bold text-[#272727]">
                            3. Harga, Stok &amp; Opsi Transaksi
                        </h2>
                        <p class="text-xs text-gray-600 mt-1">
                            Atur harga jual yang wajar, jumlah unit tersedia, serta jalur negosiasi atau barter jika dibutuhkan.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                        <!-- Harga Satuan -->
                        <div class="sm:col-span-8">
                            <label for="harga" class="block text-xs font-semibold text-gray-800 mb-1.5">
                                Harga Satuan (IDR) <span class="text-red-600">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-gray-600 pointer-events-none">Rp</span>
                                <input type="number" id="harga" name="harga" min="1" max="2000000000" step="1000" inputmode="numeric" required
                                    value="<?= htmlspecialchars($formValues['harga'], ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="0"
                                    class="w-full min-h-[44px] pl-11 pr-3.5 py-2.5 text-sm bg-white border border-gray-300 rounded-md text-[#272727] placeholder:text-gray-400 tabular-nums focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none transition<?= isset($fieldErrors['harga']) ? ' field-error' : '' ?>" />
                            </div>
                            <p id="err-harga" class="<?= isset($fieldErrors['harga']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600">
                                <?= htmlspecialchars($fieldErrors['harga'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>

                        <!-- Stok -->
                        <div class="sm:col-span-4">
                            <label for="stok" class="block text-xs font-semibold text-gray-800 mb-1.5">
                                Stok <span class="text-red-600">*</span>
                            </label>
                            <input type="number" id="stok" name="stok" min="1" step="1" inputmode="numeric" required
                                value="<?= htmlspecialchars($formValues['stok'], ENT_QUOTES, 'UTF-8') ?>"
                                class="w-full min-h-[44px] px-3.5 py-2.5 text-sm bg-white border border-gray-300 rounded-md text-[#272727] tabular-nums focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none transition<?= isset($fieldErrors['stok']) ? ' field-error' : '' ?>" />
                            <p id="err-stok" class="<?= isset($fieldErrors['stok']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600">
                                <?= htmlspecialchars($fieldErrors['stok'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">Minimal 1 unit.</p>
                        </div>
                    </div>

                    <!-- Opsi Transaksi Tambahan (Toggle Switches) -->
                    <fieldset class="mt-5 pt-5 border-t border-gray-100">
                        <legend class="text-xs font-bold text-[#272727] mb-1">
                            Opsi Transaksi Tambahan (Opsional)
                        </legend>
                        <p class="text-xs text-gray-600 mb-4">
                            Beli Langsung (Buy Now) selalu aktif. Aktifkan opsi di bawah jika ingin membuka jalur negosiasi atau tukar tambah.
                        </p>

                        <div class="divide-y divide-gray-100 max-w-md">
                            <!-- Toggle Bisa Nego -->
                            <div class="py-3 first:pt-0 flex items-center justify-between gap-4">
                                <div class="flex items-start gap-3 min-w-0">
                                    <i class="ph ph-handshake text-lg text-[#272727] mt-0.5 shrink-0" aria-hidden="true"></i>
                                    <div class="min-w-0">
                                        <label for="bisa_nego" class="block text-sm font-bold text-[#272727]">
                                            Tersedia untuk Nego
                                        </label>
                                        <p class="text-xs text-gray-600 mt-0.5">
                                            Izinkan produk ini digunakan dalam fitur Nego.
                                        </p>
                                    </div>
                                </div>
                                <label for="bisa_nego" class="relative inline-flex items-center shrink-0 min-h-[44px]">
                                    <input type="checkbox" id="bisa_nego" name="bisa_nego" value="1"<?= $formValues['bisa_nego'] ? ' checked' : '' ?> class="sr-only peer" />
                                    <span class="w-11 h-6 bg-gray-300 rounded-full transition-colors peer-checked:bg-brand-yellow peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-yellow after:content-[''] after:absolute after:top-1/2 after:-translate-y-1/2 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:shadow-xs after:transition-transform peer-checked:after:translate-x-5" aria-hidden="true"></span>
                                </label>
                            </div>

                            <!-- Toggle Bisa Barter -->
                            <div class="py-3 last:pb-0 flex items-center justify-between gap-4">
                                <div class="flex items-start gap-3 min-w-0">
                                    <i class="ph ph-arrows-left-right text-lg text-[#272727] mt-0.5 shrink-0" aria-hidden="true"></i>
                                    <div class="min-w-0">
                                        <label for="bisa_barter" class="block text-sm font-bold text-[#272727]">
                                            Tersedia untuk Barter
                                        </label>
                                        <p class="text-xs text-gray-600 mt-0.5">
                                            Izinkan produk ini digunakan dalam fitur barter.
                                        </p>
                                    </div>
                                </div>
                                <label for="bisa_barter" class="relative inline-flex items-center shrink-0 min-h-[44px]">
                                    <input type="checkbox" id="bisa_barter" name="bisa_barter" value="1"<?= $formValues['bisa_barter'] ? ' checked' : '' ?> class="sr-only peer" />
                                    <span class="w-11 h-6 bg-gray-300 rounded-full transition-colors peer-checked:bg-brand-yellow peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-yellow after:content-[''] after:absolute after:top-1/2 after:-translate-y-1/2 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:shadow-xs after:transition-transform peer-checked:after:translate-x-5" aria-hidden="true"></span>
                                </label>
                            </div>
                        </div>

                        <p id="transaksi-summary-note" class="mt-4 text-xs text-gray-600">
                            Mode aktif saat ini: <strong class="text-[#272727]">Harga Pas (Beli Langsung saja)</strong>
                        </p>
                    </fieldset>
                </section>

                <!-- SECTION 4: Deskripsi, Riwayat Kondisi & Spesifikasi -->
                <section class="bg-white border border-gray-200/90 p-5 sm:p-6 shadow-2xs" aria-labelledby="sec-desc-title">
                    <div class="pb-4 mb-5 border-b border-gray-100">
                        <h2 id="sec-desc-title" class="text-base font-bold text-[#272727]">
                            4. Deskripsi, Riwayat Kondisi &amp; Spesifikasi
                        </h2>
                        <p class="text-xs text-gray-600 mt-1">
                            Tuliskan kondisi fisik, riwayat pemakaian, dan detail teknis agar calon pembeli yakin dengan unit yang kamu jual.
                        </p>
                    </div>

                    <!-- 4A: Ringkasan Produk -->
                    <div class="mb-6">
                        <label for="ringkasan" class="block text-xs font-semibold text-gray-800 mb-1.5">
                            Ringkasan Produk <span class="text-red-600">*</span>
                        </label>
                        <textarea id="ringkasan" name="ringkasan" rows="3" required
                            placeholder="Jelaskan gambaran umum barang, alasan jual, atau catatan penting untuk pembeli..."
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-gray-300 rounded-md text-[#272727] placeholder:text-gray-400 focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none transition<?= isset($fieldErrors['ringkasan']) ? ' field-error' : '' ?>"><?= htmlspecialchars($formValues['ringkasan'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        <p id="err-ringkasan" class="<?= isset($fieldErrors['ringkasan']) ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600">
                            <?= htmlspecialchars($fieldErrors['ringkasan'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>

                    <!-- 4B: 6 Field Riwayat & Kondisi (ADR-0001) -->
                    <div class="mb-6 pt-5 border-t border-gray-100">
                        <h3 class="text-sm font-bold text-[#272727] mb-1">
                            Rincian Riwayat &amp; Kondisi
                        </h3>
                        <p class="text-xs text-gray-600 mb-4">
                            Keenam field ini wajib diisi agar tab <strong>Riwayat &amp; Kondisi</strong> dan ringkasan unit di halaman detail produk tampil lengkap.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php
                            $riwayatFields = [
                                ['key' => 'fisik',       'label' => 'Kondisi Fisik',  'placeholder' => 'Contoh: Mulus 95%, lecet halus di bezel'],
                                ['key' => 'fungsi',      'label' => 'Fungsi',         'placeholder' => 'Contoh: Normal 100%, suhu full load 68C'],
                                ['key' => 'lama_pakai',  'label' => 'Lama Pemakaian', 'placeholder' => 'Contoh: 1 tahun pemakaian harian'],
                                ['key' => 'servis',      'label' => 'Riwayat Servis', 'placeholder' => 'Contoh: Belum pernah servis / ganti thermal paste'],
                                ['key' => 'minus',       'label' => 'Minus / Defect', 'placeholder' => 'Contoh: Tidak ada / karet kaki belakang lepas'],
                                ['key' => 'kelengkapan', 'label' => 'Kelengkapan',    'placeholder' => 'Contoh: Fullset box original + buku manual'],
                            ];
                            foreach ($riwayatFields as $rf):
                                $fid = 'riwayat_' . $rf['key'];
                                $fval = $formValues['riwayat'][$rf['key']] ?? '';
                                $ferr = $fieldErrors[$fid] ?? '';
                            ?>
                                <div>
                                    <label for="<?= $fid ?>" class="block text-xs font-semibold text-gray-800 mb-1.5">
                                        <?= htmlspecialchars($rf['label'], ENT_QUOTES, 'UTF-8') ?> <span class="text-red-600">*</span>
                                    </label>
                                    <input type="text" id="<?= $fid ?>" name="riwayat[<?= $rf['key'] ?>]" required
                                        value="<?= htmlspecialchars($fval, ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="<?= htmlspecialchars($rf['placeholder'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="w-full min-h-[44px] px-3 py-2 text-sm bg-white border border-gray-300 rounded-md text-[#272727] placeholder:text-gray-400 focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none transition<?= $ferr !== '' ? ' field-error' : '' ?>" />
                                    <p id="err-<?= $fid ?>" class="<?= $ferr !== '' ? '' : 'hidden ' ?>mt-1 text-xs font-medium text-red-600">
                                        <?= htmlspecialchars($ferr, ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 4C: Spesifikasi Dinamis -->
                    <div class="pt-5 border-t border-gray-100">
                        <div class="space-y-2.5 mb-3">
                            <div>
                                <h3 class="text-sm font-bold text-[#272727]">
                                    Spesifikasi
                                </h3>
                                <p class="text-xs text-gray-600 mt-0.5">
                                    Opsional, isi pasangan label dan nilai sesuai kategori barang. Baris kosong otomatis diabaikan.
                                </p>
                            </div>
                            <button type="button" id="btn-add-spec"
                                class="min-h-[44px] px-3.5 py-2 bg-brand-dark text-white text-xs font-bold rounded-[4px] hover:bg-black active:scale-[0.99] transition inline-flex items-center gap-1.5">
                                <i class="ph-bold ph-plus text-xs" aria-hidden="true"></i>
                                <span>Tambah Baris</span>
                            </button>
                        </div>

                        <div id="spec-rows-container" class="space-y-2.5"></div>

                        <!-- Empty State Spesifikasi -->
                        <div id="spec-empty-state" class="border border-dashed border-gray-300 rounded-md p-5 text-center bg-[#FAFAFA]">
                            <p class="text-xs font-bold text-[#272727]">Belum ada baris spesifikasi ditambahkan.</p>
                            <p class="text-xs text-gray-600 mt-1">
                                Tambahkan detail teknis seperti VRAM, Socket, Kapasitas, atau Garansi agar tab Spesifikasi terisi.
                            </p>
                            <button type="button" id="btn-add-spec-empty"
                                class="mt-3.5 min-h-[44px] px-4 py-2 bg-white border border-gray-300 text-xs font-bold text-[#272727] rounded-[4px] hover:border-gray-500 hover:bg-gray-50 active:scale-[0.99] transition">
                                Tambah Spesifikasi Pertama
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Action Bar: Indikator Kelengkapan Wajib (Kiri) + Tombol Aksi (Kanan) -->
                <div class="bg-white border border-gray-200/90 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-2xs">
                    <!-- Kiri: Indikator Kesiapan Tayang -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <i id="readiness-icon" class="ph-fill ph-list-checks text-base text-[#92400E] shrink-0" aria-hidden="true"></i>
                            <p id="readiness-title" class="text-xs sm:text-sm font-bold text-[#272727] tabular-nums">
                                Kelengkapan wajib: 1 / 13 syarat
                            </p>
                        </div>
                        <div class="mt-2 h-1.5 w-full max-w-xs bg-gray-100 rounded-full overflow-hidden" aria-hidden="true">
                            <div id="readiness-bar" class="h-full bg-brand-yellow rounded-full transition-all duration-200" style="width: 8%;"></div>
                        </div>
                        <p id="readiness-hint" class="mt-1.5 text-xs text-gray-600">
                            Lengkapi sisa syarat wajib untuk tayang, atau simpan ke draft dulu.
                        </p>
                    </div>

                    <!-- Kanan: Tombol Aksi -->
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-2.5 shrink-0">
                        <?php if (!$isLiveEdit): ?>
                            <button type="submit" id="btn-save-draft" data-submit-status="draft"
                                class="min-h-[44px] px-5 py-2.5 bg-white border border-gray-400 text-[#272727] font-bold text-xs sm:text-sm rounded-[4px] hover:bg-gray-50 hover:border-[#272727] active:scale-[0.99] disabled:opacity-60 transition inline-flex items-center justify-center gap-2">
                                <i id="draft-icon" class="ph ph-floppy-disk text-base" aria-hidden="true"></i>
                                <span id="draft-label">Simpan Draft</span>
                            </button>
                        <?php endif; ?>

                        <button type="submit" id="btn-submit" data-submit-status="<?= htmlspecialchars($primaryTargetStatus, ENT_QUOTES, 'UTF-8') ?>"
                            class="min-h-[44px] px-6 py-2.5 bg-brand-yellow text-[#272727] font-extrabold text-xs sm:text-sm rounded-[4px] hover:brightness-95 active:scale-[0.99] disabled:opacity-60 transition inline-flex items-center justify-center gap-2">
                            <i id="submit-icon" class="ph ph-paper-plane-tilt text-base" aria-hidden="true"></i>
                            <span id="submit-label"><?= htmlspecialchars($primaryBtnLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    </div>
                </div>
            </form>

            <!-- KOLOM KANAN: PRATINJAU KARTU ETALASE (Menyesuaikan card search.php, sticky saat scroll) -->
            <aside class="lg:col-span-4 lg:sticky lg:top-6 self-start" aria-label="Pratinjau Kartu Etalase">
                <section class="bg-white border border-gray-200/90 p-5 shadow-2xs" aria-labelledby="preview-card-title">
                    <div class="pb-3 mb-4 border-b border-gray-100">
                        <h2 id="preview-card-title" class="text-sm font-bold text-[#272727]">
                            Pratinjau Kartu Etalase
                        </h2>
                    </div>

                    <article class="bg-white border border-gray-200/80 p-4 shadow-2xs flex flex-col">
                        <!-- Baris Atas: Badge Kondisi & Kategori -->
                        <div class="flex items-center justify-between gap-2 mb-2.5">
                            <span id="card-badge-kondisi" class="bg-gray-100 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded-md shrink-0">
                                Kondisi belum dipilih
                            </span>
                            <span id="card-kategori" class="text-[11px] font-semibold text-gray-600 truncate">
                                Kategori
                            </span>
                        </div>

                        <!-- Gambar / Placeholder Kategori (sesuai search.php) -->
                        <div class="w-full h-44 overflow-hidden flex items-center justify-center p-3 relative mb-3 bg-[#FAFAFA] border border-gray-100">
                            <img id="card-preview-img" src="" alt="Pratinjau foto utama produk" width="280" height="176"
                                class="hidden w-full h-full object-contain" />
                            <div id="card-preview-fallback" class="w-full h-full flex flex-col items-center justify-center bg-neutral-900 text-white p-3 relative shadow-inner">
                                <span id="card-preview-cat-label" class="text-[9px] font-extrabold tracking-widest text-brand-yellow uppercase mb-1">
                                    Loot-E
                                </span>
                                <i id="card-preview-icon" class="ph ph-tag text-4xl text-gray-200 my-1" aria-hidden="true"></i>
                                <span id="card-preview-fallback-name" class="text-[10px] font-bold text-gray-300 text-center line-clamp-1">
                                    Nama produk akan tampil di sini
                                </span>
                                <span class="absolute bottom-1 right-2 text-[8px] text-brand-yellow font-bold">VERIFIED</span>
                            </div>
                        </div>

                        <h3 id="card-nama" class="text-sm font-bold text-[#272727] leading-snug line-clamp-2 min-h-[2.5rem]">
                            Nama produk akan tampil di sini
                        </h3>

                        <p id="card-harga" class="text-base font-extrabold text-[#272727] mt-1.5 tracking-tight tabular-nums">
                            Rp 0
                        </p>

                        <!-- Badge Opsi Transaksi (sama persis dengan search.php) -->
                        <div id="card-opsi-badges" class="flex flex-wrap items-center gap-1.5 mt-2.5 min-h-[24px]">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-50 text-gray-600 border border-gray-200/80 text-[10px] font-semibold">
                                <i class="ph ph-check-circle text-[11px]" aria-hidden="true"></i> Harga Pas
                            </span>
                        </div>

                        <!-- Footer Card: Ulasan, Stok & Info Penjual (sesuai search.php) -->
                        <div class="mt-auto pt-3">
                            <div class="flex items-center justify-between gap-2 text-xs text-gray-600 pb-2.5 border-b border-gray-100">
                                <span class="text-gray-500 text-[11px]">Belum ada ulasan</span>
                                <span id="card-stok" class="text-[11px] font-semibold text-gray-600 tabular-nums shrink-0">Stok: 1</span>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-2.5 text-xs">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-6 h-6 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center font-bold text-[10px] shrink-0">
                                        <?= htmlspecialchars($previewSellerInitial, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-[#272727] text-xs leading-none truncate"><?= htmlspecialchars($previewSellerName, ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-[10px] text-gray-500 leading-tight mt-0.5">Fast Response</p>
                                    </div>
                                </div>
                                <span class="text-[10px] text-emerald-700 font-bold flex items-center gap-1 shrink-0">
                                    <i class="ph-fill ph-shield-check text-xs" aria-hidden="true"></i> Trusted Seller
                                </span>
                            </div>
                        </div>
                    </article>
                </section>
            </aside>
        </div>
    </main>

    <!-- Toast Notification -->
    <div id="toast-msg" role="status" aria-live="polite"
        class="hidden fixed bottom-5 left-5 z-50 bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-semibold shadow-lg max-w-sm">
    </div>

    <!-- Modal Konfirmasi Reusable -->
    <?php require_once 'components/confirm_modal.php'; ?>

    <script>
        (function () {
            'use strict';

            const MAX_PRICE = 2000000000;
            const MAX_PHOTO_BYTES = 2 * 1024 * 1024;
            const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
            const ALLOWED_KONDISI = [
                'Baru',
                'Bekas - Seperti Baru',
                'Bekas - Mulus',
                'Bekas - Ada Minus'
            ];
            const IS_LIVE_EDIT = <?= $isLiveEdit ? 'true' : 'false' ?>;
            const PRIMARY_STATUS = <?= json_encode($primaryTargetStatus, JSON_UNESCAPED_UNICODE) ?>;
            const PRIMARY_LABEL = <?= json_encode($primaryBtnLabel, JSON_UNESCAPED_UNICODE) ?>;

            const state = {
                photos: <?= json_encode($initialPhotosJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                specs: <?= json_encode($initialSpecsJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                pendingStatus: PRIMARY_STATUS
            };

            const els = {
                form: document.getElementById('product-upload-form'),
                btnBackKatalog: document.getElementById('btn-back-katalog'),
                statusInput: document.getElementById('status_produk'),
                fotoOrderInput: document.getElementById('foto_order'),
                errorSummary: document.getElementById('error-summary'),
                errorSummaryList: document.getElementById('error-summary-list'),

                nama: document.getElementById('nama_produk'),
                namaCounter: document.getElementById('nama-counter'),
                kategori: document.getElementById('id_kategori'),
                kondisi: document.getElementById('kondisi'),

                fotoPicker: document.getElementById('foto-picker'),
                fotoBaru: document.getElementById('foto_baru'),
                photoEmptyDropzone: document.getElementById('photo-empty-dropzone'),
                photoGalleryGrid: document.getElementById('photo-gallery-grid'),
                photoCountBadge: document.getElementById('photo-count-badge'),
                photoTip: document.getElementById('photo-tip'),

                harga: document.getElementById('harga'),
                stok: document.getElementById('stok'),
                bisaNego: document.getElementById('bisa_nego'),
                bisaBarter: document.getElementById('bisa_barter'),
                transaksiNote: document.getElementById('transaksi-summary-note'),

                ringkasan: document.getElementById('ringkasan'),
                riwayat: {
                    fisik: document.getElementById('riwayat_fisik'),
                    fungsi: document.getElementById('riwayat_fungsi'),
                    lama_pakai: document.getElementById('riwayat_lama_pakai'),
                    servis: document.getElementById('riwayat_servis'),
                    minus: document.getElementById('riwayat_minus'),
                    kelengkapan: document.getElementById('riwayat_kelengkapan')
                },

                specContainer: document.getElementById('spec-rows-container'),
                specEmptyState: document.getElementById('spec-empty-state'),
                btnAddSpec: document.getElementById('btn-add-spec'),
                btnAddSpecEmpty: document.getElementById('btn-add-spec-empty'),

                btnSaveDraft: document.getElementById('btn-save-draft'),
                draftIcon: document.getElementById('draft-icon'),
                draftLabel: document.getElementById('draft-label'),
                btnSubmit: document.getElementById('btn-submit'),
                submitIcon: document.getElementById('submit-icon'),
                submitLabel: document.getElementById('submit-label'),
                readinessIcon: document.getElementById('readiness-icon'),
                readinessTitle: document.getElementById('readiness-title'),
                readinessBar: document.getElementById('readiness-bar'),
                readinessHint: document.getElementById('readiness-hint'),

                cardBadgeKondisi: document.getElementById('card-badge-kondisi'),
                cardStok: document.getElementById('card-stok'),
                cardPreviewImg: document.getElementById('card-preview-img'),
                cardPreviewFallback: document.getElementById('card-preview-fallback'),
                cardPreviewIcon: document.getElementById('card-preview-icon'),
                cardPreviewCatLabel: document.getElementById('card-preview-cat-label'),
                cardPreviewFallbackName: document.getElementById('card-preview-fallback-name'),
                cardKategori: document.getElementById('card-kategori'),
                cardNama: document.getElementById('card-nama'),
                cardHarga: document.getElementById('card-harga'),
                cardOpsiBadges: document.getElementById('card-opsi-badges'),

                toast: document.getElementById('toast-msg')
            };

            let toastTimer = null;
            function showToast(message) {
                els.toast.textContent = message;
                els.toast.classList.remove('hidden');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function () {
                    els.toast.classList.add('hidden');
                }, 2800);
            }

            function formatRupiah(num) {
                const n = Number(num);
                if (!Number.isFinite(n) || n <= 0) return 'Rp 0';
                return 'Rp ' + Math.floor(n).toLocaleString('id-ID');
            }

            function setFieldError(fieldId, message) {
                const inputEl = document.getElementById(fieldId);
                const errEl = document.getElementById('err-' + fieldId);
                if (inputEl && inputEl.type !== 'file') {
                    inputEl.classList.toggle('field-error', Boolean(message));
                }
                if (errEl) {
                    errEl.textContent = message || '';
                    errEl.classList.toggle('hidden', !message);
                }
            }

            function clearAllFieldErrors() {
                [
                    'nama_produk', 'id_kategori', 'kondisi', 'foto', 'harga', 'stok', 'ringkasan',
                    'riwayat_fisik', 'riwayat_fungsi', 'riwayat_lama_pakai',
                    'riwayat_servis', 'riwayat_minus', 'riwayat_kelengkapan'
                ].forEach(function (id) {
                    setFieldError(id, '');
                });
                els.errorSummary.classList.add('hidden');
                els.errorSummaryList.innerHTML = '';
            }

            // Sinkronkan file baru ke input[name="foto_baru[]"] & urutan final ke hidden foto_order
            function syncPhotoPayload() {
                const dt = typeof DataTransfer !== 'undefined' ? new DataTransfer() : null;
                const order = [];
                let newIdx = 0;

                state.photos.forEach(function (item) {
                    if (item.kind === 'existing') {
                        order.push({ type: 'existing', id: item.id });
                    } else if (item.kind === 'new' && item.file) {
                        if (dt) dt.items.add(item.file);
                        order.push({ type: 'new', idx: newIdx });
                        newIdx++;
                    }
                });

                if (dt) {
                    els.fotoBaru.files = dt.files;
                }
                els.fotoOrderInput.value = JSON.stringify(order);
            }

            function addPhotoFiles(fileList) {
                setFieldError('foto', '');
                const incoming = Array.from(fileList || []);
                if (incoming.length === 0) return;

                let firstError = '';
                let addedCount = 0;

                incoming.forEach(function (file) {
                    if (!ALLOWED_TYPES.includes(file.type)) {
                        if (!firstError) firstError = 'File "' + file.name + '" bukan JPG, PNG, atau WEBP.';
                        return;
                    }
                    if (file.size > MAX_PHOTO_BYTES) {
                        if (!firstError) firstError = 'File "' + file.name + '" melebihi batas 2 MB.';
                        return;
                    }
                    state.photos.push({
                        kind: 'new',
                        file: file,
                        url: URL.createObjectURL(file)
                    });
                    addedCount++;
                });

                els.fotoPicker.value = '';
                if (firstError) {
                    setFieldError('foto', firstError);
                }

                if (addedCount > 0) {
                    syncPhotoPayload();
                    renderPhotoGallery();
                    updatePreview();
                }
            }

            function removePhotoAt(index) {
                const removed = state.photos.splice(index, 1)[0];
                if (removed && removed.kind === 'new' && removed.url) {
                    URL.revokeObjectURL(removed.url);
                }
                setFieldError('foto', '');
                syncPhotoPayload();
                renderPhotoGallery();
                updatePreview();
            }

            function promotePhotoToPrimary(index) {
                if (index <= 0 || index >= state.photos.length) return;
                const chosen = state.photos.splice(index, 1)[0];
                state.photos.unshift(chosen);
                syncPhotoPayload();
                renderPhotoGallery();
                updatePreview();
                showToast('Foto utama berhasil diperbarui.');
            }

            function bindDropEvents(zoneEl) {
                zoneEl.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    zoneEl.classList.add('bg-amber-50');
                });
                zoneEl.addEventListener('dragleave', function () {
                    zoneEl.classList.remove('bg-amber-50');
                });
                zoneEl.addEventListener('drop', function (e) {
                    e.preventDefault();
                    zoneEl.classList.remove('bg-amber-50');
                    if (e.dataTransfer && e.dataTransfer.files) {
                        addPhotoFiles(e.dataTransfer.files);
                    }
                });
            }

            function renderPhotoGallery() {
                const count = state.photos.length;
                els.photoCountBadge.textContent = count + ' foto';
                els.photoTip.classList.toggle('hidden', count < 1);

                if (count === 0) {
                    els.photoEmptyDropzone.classList.remove('hidden');
                    els.photoGalleryGrid.classList.add('hidden');
                    els.photoGalleryGrid.innerHTML = '';
                    return;
                }

                els.photoEmptyDropzone.classList.add('hidden');
                els.photoGalleryGrid.classList.remove('hidden');
                els.photoGalleryGrid.innerHTML = '';

                state.photos.forEach(function (photo, i) {
                    const card = document.createElement('div');
                    card.className = 'relative aspect-square rounded-lg border ' +
                        (i === 0 ? 'border-brand-yellow ring-1 ring-brand-yellow/40' : 'border-gray-200') +
                        ' bg-[#FAFAFA] overflow-hidden';

                    const selectBtn = document.createElement('button');
                    selectBtn.type = 'button';
                    selectBtn.className = 'w-full h-full block focus-visible:outline-none';
                    selectBtn.setAttribute(
                        'aria-label',
                        i === 0 ? 'Foto utama produk' : 'Jadikan foto ke-' + (i + 1) + ' sebagai foto utama'
                    );
                    if (i > 0) {
                        selectBtn.title = 'Klik untuk jadikan sampul utama';
                        selectBtn.addEventListener('click', function () {
                            promotePhotoToPrimary(i);
                        });
                    }

                    const img = document.createElement('img');
                    img.src = photo.url;
                    img.alt = 'Foto produk ' + (i + 1);
                    img.width = 160;
                    img.height = 160;
                    img.className = 'w-full h-full object-cover';
                    selectBtn.appendChild(img);
                    card.appendChild(selectBtn);

                    if (i === 0) {
                        const badge = document.createElement('span');
                        badge.className = 'absolute bottom-1.5 left-1.5 px-1.5 py-0.5 rounded bg-brand-dark/90 text-brand-yellow text-[10px] font-bold leading-none pointer-events-none';
                        badge.textContent = 'Utama';
                        card.appendChild(badge);
                    }

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.setAttribute('aria-label', 'Hapus foto ' + (i + 1));
                    removeBtn.className = 'absolute top-1.5 right-1.5 w-7 h-7 rounded-full bg-brand-dark/80 hover:bg-red-600 text-white flex items-center justify-center transition';
                    removeBtn.innerHTML = '<i class="ph-bold ph-x text-xs" aria-hidden="true"></i>';
                    removeBtn.addEventListener('click', function () {
                        removePhotoAt(i);
                    });
                    card.appendChild(removeBtn);

                    els.photoGalleryGrid.appendChild(card);
                });

                // 1 Tile fleksibel "+ Tambah Foto" selalu berada di ujung galeri
                const addTile = document.createElement('button');
                addTile.type = 'button';
                addTile.setAttribute('aria-label', 'Tambah foto produk lainnya');
                addTile.className = 'aspect-square rounded-lg border-2 border-dashed border-brand-yellow bg-[#FFFDF5] hover:bg-amber-50/70 flex flex-col items-center justify-center p-2 text-center transition';
                addTile.innerHTML =
                    '<i class="ph-bold ph-plus text-2xl text-brand-yellow" aria-hidden="true"></i>' +
                    '<span class="mt-1.5 text-xs font-bold text-[#272727]">Tambah Foto</span>';
                addTile.addEventListener('click', function () {
                    els.fotoPicker.click();
                });
                bindDropEvents(addTile);
                els.photoGalleryGrid.appendChild(addTile);
            }

            function renderSpecRows() {
                els.specContainer.innerHTML = '';
                if (state.specs.length === 0) {
                    els.specEmptyState.classList.remove('hidden');
                    return;
                }
                els.specEmptyState.classList.add('hidden');

                state.specs.forEach(function (row, index) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'grid grid-cols-1 sm:grid-cols-12 gap-2 items-center';

                    const labelWrap = document.createElement('div');
                    labelWrap.className = 'sm:col-span-5';
                    const labelInput = document.createElement('input');
                    labelInput.type = 'text';
                    labelInput.name = 'spesifikasi[' + index + '][label]';
                    labelInput.value = row.label;
                    labelInput.placeholder = 'Label (mis. Video Memory)';
                    labelInput.setAttribute('aria-label', 'Label spesifikasi baris ' + (index + 1));
                    labelInput.className = 'w-full min-h-[44px] px-3 py-2 text-sm bg-white border border-gray-300 rounded-md text-[#272727] placeholder:text-gray-400 focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none';
                    labelInput.addEventListener('input', function (e) {
                        state.specs[index].label = e.target.value;
                    });
                    labelWrap.appendChild(labelInput);

                    const valWrap = document.createElement('div');
                    valWrap.className = 'sm:col-span-6';
                    const valInput = document.createElement('input');
                    valInput.type = 'text';
                    valInput.name = 'spesifikasi[' + index + '][nilai]';
                    valInput.value = row.nilai;
                    valInput.placeholder = 'Nilai (mis. 12GB GDDR6)';
                    valInput.setAttribute('aria-label', 'Nilai spesifikasi baris ' + (index + 1));
                    valInput.className = 'w-full min-h-[44px] px-3 py-2 text-sm bg-white border border-gray-300 rounded-md text-[#272727] placeholder:text-gray-400 focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30 focus:outline-none';
                    valInput.addEventListener('input', function (e) {
                        state.specs[index].nilai = e.target.value;
                    });
                    valWrap.appendChild(valInput);

                    const delWrap = document.createElement('div');
                    delWrap.className = 'sm:col-span-1 flex justify-end';
                    const delBtn = document.createElement('button');
                    delBtn.type = 'button';
                    delBtn.setAttribute('aria-label', 'Hapus baris spesifikasi ' + (index + 1));
                    delBtn.className = 'min-w-[44px] min-h-[44px] flex items-center justify-center rounded-md border border-gray-200 text-gray-600 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition';
                    delBtn.innerHTML = '<i class="ph ph-trash text-base" aria-hidden="true"></i>';
                    delBtn.addEventListener('click', function () {
                        state.specs.splice(index, 1);
                        renderSpecRows();
                    });
                    delWrap.appendChild(delBtn);

                    wrapper.appendChild(labelWrap);
                    wrapper.appendChild(valWrap);
                    wrapper.appendChild(delWrap);
                    els.specContainer.appendChild(wrapper);
                });
            }

            function addSpecRow() {
                state.specs.push({ label: '', nilai: '' });
                renderSpecRows();
                const lastInput = els.specContainer.querySelector('input[name="spesifikasi[' + (state.specs.length - 1) + '][label]"]');
                if (lastInput) lastInput.focus();
            }

            function updateReadiness() {
                const hargaNum = Number(els.harga.value);
                const stokNum = Number(els.stok.value);
                const checks = [
                    els.nama.value.trim().length > 0 && els.nama.value.trim().length <= 255,
                    els.kategori.value !== '',
                    ALLOWED_KONDISI.includes(els.kondisi.value),
                    state.photos.length >= 1,
                    els.harga.value !== '' && Number.isFinite(hargaNum) && hargaNum > 0 && hargaNum <= MAX_PRICE,
                    els.stok.value !== '' && Number.isInteger(stokNum) && stokNum >= 1,
                    els.ringkasan.value.trim() !== '',
                    els.riwayat.fisik.value.trim() !== '',
                    els.riwayat.fungsi.value.trim() !== '',
                    els.riwayat.lama_pakai.value.trim() !== '',
                    els.riwayat.servis.value.trim() !== '',
                    els.riwayat.minus.value.trim() !== '',
                    els.riwayat.kelengkapan.value.trim() !== ''
                ];

                const total = checks.length;
                const filled = checks.filter(Boolean).length;
                const pct = Math.round((filled / total) * 100);
                const isReady = filled === total;

                els.readinessBar.style.width = pct + '%';
                els.readinessBar.className = 'h-full rounded-full transition-all duration-200 ' +
                    (isReady ? 'bg-emerald-500' : 'bg-brand-yellow');

                if (isReady) {
                    els.readinessIcon.className = 'ph-fill ph-check-circle text-base text-emerald-600 shrink-0';
                    els.readinessTitle.textContent = 'Semua syarat wajib lengkap (' + filled + ' / ' + total + ')';
                    els.readinessHint.textContent = IS_LIVE_EDIT
                        ? 'Perubahan produk siap disimpan.'
                        : 'Produk sudah siap ditayangkan ke etalase Loot-E.';
                } else {
                    els.readinessIcon.className = 'ph-fill ph-list-checks text-base text-[#92400E] shrink-0';
                    els.readinessTitle.textContent = 'Kelengkapan wajib: ' + filled + ' / ' + total + ' syarat';
                    els.readinessHint.textContent = IS_LIVE_EDIT
                        ? 'Lengkapi ' + (total - filled) + ' syarat wajib lagi sebelum menyimpan perubahan.'
                        : 'Lengkapi ' + (total - filled) + ' syarat wajib lagi untuk tayang, atau simpan ke draft dulu.';
                }
            }

            function updatePreview() {
                const namaVal = els.nama.value.trim();
                els.namaCounter.textContent = els.nama.value.length + ' / 255';

                const extraModes = [];
                if (els.bisaNego.checked) extraModes.push('Bisa Nego');
                if (els.bisaBarter.checked) extraModes.push('Bisa Barter');

                const modeLabel = extraModes.length === 0
                    ? 'Harga Pas (Beli Langsung saja)'
                    : extraModes.join(' - ');
                els.transaksiNote.innerHTML = 'Mode aktif saat ini: <strong class="text-[#272727]">' + modeLabel + '</strong>';

                const selectedOpt = els.kategori.options[els.kategori.selectedIndex];
                const catName = selectedOpt && selectedOpt.dataset.name ? selectedOpt.dataset.name : '';
                const catIcon = selectedOpt && selectedOpt.dataset.icon ? selectedOpt.dataset.icon : 'ph-tag';

                const kondisiVal = els.kondisi.value;
                if (kondisiVal === 'Baru') {
                    els.cardBadgeKondisi.className = 'bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-md shrink-0';
                    els.cardBadgeKondisi.textContent = 'Baru';
                } else if (kondisiVal) {
                    els.cardBadgeKondisi.className = 'bg-[#FFF8E1] text-[#92400E] text-[10px] font-bold px-2 py-0.5 rounded-md shrink-0';
                    els.cardBadgeKondisi.textContent = 'Bekas';
                } else {
                    els.cardBadgeKondisi.className = 'bg-gray-100 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded-md shrink-0';
                    els.cardBadgeKondisi.textContent = 'Kondisi belum dipilih';
                }

                const stokNum = Number(els.stok.value);
                els.cardStok.textContent = 'Stok: ' + (Number.isInteger(stokNum) && stokNum > 0 ? stokNum : 0);
                els.cardKategori.textContent = catName || 'Kategori';
                const displayNama = namaVal || 'Nama produk akan tampil di sini';
                els.cardNama.textContent = displayNama;
                els.cardHarga.textContent = formatRupiah(els.harga.value);

                if (state.photos.length > 0) {
                    els.cardPreviewImg.src = state.photos[0].url;
                    els.cardPreviewImg.classList.remove('hidden');
                    els.cardPreviewFallback.classList.add('hidden');
                } else {
                    els.cardPreviewImg.src = '';
                    els.cardPreviewImg.classList.add('hidden');
                    els.cardPreviewFallback.classList.remove('hidden');
                    els.cardPreviewIcon.className = 'ph ' + catIcon + ' text-4xl text-gray-200 my-1';
                    els.cardPreviewCatLabel.textContent = catName || 'Loot-E';
                    els.cardPreviewFallbackName.textContent = displayNama;
                }

                const badgeHtml = [];
                if (els.bisaNego.checked) {
                    badgeHtml.push(
                        '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 text-[10px] font-bold">' +
                        '<i class="ph-bold ph-tag text-[11px]" aria-hidden="true"></i> Bisa Nego</span>'
                    );
                }
                if (els.bisaBarter.checked) {
                    badgeHtml.push(
                        '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200/80 text-[10px] font-bold">' +
                        '<i class="ph-bold ph-arrows-left-right text-[11px]" aria-hidden="true"></i> Bisa Barter</span>'
                    );
                }
                if (badgeHtml.length === 0) {
                    badgeHtml.push(
                        '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-50 text-gray-600 border border-gray-200/80 text-[10px] font-semibold">' +
                        '<i class="ph ph-check-circle text-[11px]" aria-hidden="true"></i> Harga Pas</span>'
                    );
                }
                els.cardOpsiBadges.innerHTML = badgeHtml.join('');

                updateReadiness();
            }

            function validateForm(targetStatus) {
                clearAllFieldErrors();
                const isDraft = targetStatus === 'draft';
                const errors = [];

                const namaVal = els.nama.value.trim();
                if (!namaVal) {
                    setFieldError('nama_produk', 'Nama produk wajib diisi.');
                    errors.push({ id: 'nama_produk', msg: 'Nama produk wajib diisi.' });
                } else if (namaVal.length > 255) {
                    setFieldError('nama_produk', 'Nama produk maksimal 255 karakter.');
                    errors.push({ id: 'nama_produk', msg: 'Nama produk maksimal 255 karakter.' });
                }

                if (!els.kategori.value) {
                    setFieldError('id_kategori', 'Pilih salah satu kategori produk.');
                    errors.push({ id: 'id_kategori', msg: 'Kategori produk wajib dipilih.' });
                }

                if (!ALLOWED_KONDISI.includes(els.kondisi.value)) {
                    setFieldError('kondisi', 'Pilih kondisi barang dari opsi yang tersedia.');
                    errors.push({ id: 'kondisi', msg: 'Kondisi barang wajib dipilih.' });
                }

                if (!isDraft && state.photos.length < 1) {
                    setFieldError('foto', 'Unggah minimal 1 foto produk agar dapat ditayangkan.');
                    errors.push({ id: 'photo-empty-dropzone', msg: 'Minimal 1 foto produk wajib diunggah.' });
                }

                const hargaNum = Number(els.harga.value);
                if (!els.harga.value || !Number.isFinite(hargaNum) || hargaNum <= 0) {
                    setFieldError('harga', 'Harga wajib diisi dan lebih besar dari 0.');
                    errors.push({ id: 'harga', msg: 'Harga produk wajib lebih besar dari Rp 0.' });
                } else if (hargaNum > MAX_PRICE) {
                    setFieldError('harga', 'Harga maksimal Rp 2.000.000.000.');
                    errors.push({ id: 'harga', msg: 'Harga melebihi batas maksimal Rp 2.000.000.000.' });
                }

                const stokNum = Number(els.stok.value);
                if (!els.stok.value || !Number.isInteger(stokNum) || stokNum < 1) {
                    setFieldError('stok', 'Stok minimal 1 unit dan berupa angka bulat.');
                    errors.push({ id: 'stok', msg: 'Stok minimal 1 unit.' });
                }

                if (!isDraft) {
                    if (!els.ringkasan.value.trim()) {
                        setFieldError('ringkasan', 'Ringkasan produk wajib diisi.');
                        errors.push({ id: 'ringkasan', msg: 'Ringkasan produk wajib diisi.' });
                    }

                    const riwayatLabels = {
                        fisik: 'Kondisi Fisik',
                        fungsi: 'Fungsi',
                        lama_pakai: 'Lama Pemakaian',
                        servis: 'Riwayat Servis',
                        minus: 'Minus / Defect',
                        kelengkapan: 'Kelengkapan'
                    };

                    Object.keys(els.riwayat).forEach(function (key) {
                        if (!els.riwayat[key].value.trim()) {
                            const fieldId = 'riwayat_' + key;
                            setFieldError(fieldId, riwayatLabels[key] + ' wajib diisi.');
                            errors.push({ id: fieldId, msg: riwayatLabels[key] + ' wajib diisi.' });
                        }
                    });
                }

                if (errors.length > 0) {
                    els.errorSummaryList.innerHTML = '';
                    errors.forEach(function (err) {
                        const li = document.createElement('li');
                        li.textContent = err.msg;
                        els.errorSummaryList.appendChild(li);
                    });
                    els.errorSummary.classList.remove('hidden');
                    const firstEl = document.getElementById(errors[0].id);
                    if (firstEl) {
                        if (typeof firstEl.focus === 'function') firstEl.focus();
                        firstEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }

                return true;
            }

            function setSubmittingState(isLoading, targetStatus) {
                if (els.btnSaveDraft) els.btnSaveDraft.disabled = isLoading;
                els.btnSubmit.disabled = isLoading;

                if (els.draftIcon && els.draftLabel) {
                    if (isLoading && targetStatus === 'draft') {
                        els.draftIcon.className = 'ph ph-spinner-gap animate-spin text-base';
                        els.draftLabel.textContent = 'Menyimpan draft...';
                    } else {
                        els.draftIcon.className = 'ph ph-floppy-disk text-base';
                        els.draftLabel.textContent = 'Simpan Draft';
                    }
                }

                if (isLoading && targetStatus !== 'draft') {
                    els.submitIcon.className = 'ph ph-spinner-gap animate-spin text-base';
                    els.submitLabel.textContent = 'Menyimpan...';
                } else {
                    els.submitIcon.className = 'ph ph-paper-plane-tilt text-base';
                    els.submitLabel.textContent = PRIMARY_LABEL;
                }
            }

            function getFormSnapshot() {
                return JSON.stringify({
                    nama: els.nama.value.trim(),
                    kategori: els.kategori.value,
                    kondisi: els.kondisi.value,
                    harga: els.harga.value.trim(),
                    stok: els.stok.value.trim(),
                    nego: els.bisaNego.checked,
                    barter: els.bisaBarter.checked,
                    ringkasan: els.ringkasan.value.trim(),
                    riwayat: Object.keys(els.riwayat).map(function (k) { return els.riwayat[k].value.trim(); }),
                    specs: state.specs.map(function (s) { return [s.label.trim(), s.nilai.trim()]; }),
                    photos: state.photos.map(function (p) {
                        return p.kind === 'existing' ? ('e:' + p.id) : ('n:' + (p.file ? p.file.name + ':' + p.file.size : ''));
                    })
                });
            }

            // Sinkronkan pratinjau langsung & indikator kelengkapan wajib
            [
                els.nama, els.kategori, els.kondisi, els.harga, els.stok,
                els.bisaNego, els.bisaBarter, els.ringkasan,
                ...Object.values(els.riwayat)
            ].forEach(function (el) {
                el.addEventListener('input', updatePreview);
                el.addEventListener('change', updatePreview);
            });

            // Upload foto: klik & drag-and-drop
            els.fotoPicker.addEventListener('change', function (e) {
                addPhotoFiles(e.target.files);
            });
            bindDropEvents(els.photoEmptyDropzone);

            // Spesifikasi dinamis
            els.btnAddSpec.addEventListener('click', addSpecRow);
            els.btnAddSpecEmpty.addEventListener('click', addSpecRow);

            // Deteksi tombol submit yang ditekan
            [els.btnSaveDraft, els.btnSubmit].filter(Boolean).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    state.pendingStatus = btn.getAttribute('data-submit-status') || PRIMARY_STATUS;
                    els.statusInput.value = state.pendingStatus;
                });
            });

            // Submit form POST native ke backend PHP
            els.form.addEventListener('submit', function (e) {
                const submitter = e.submitter;
                const targetStatus = (submitter && submitter.getAttribute('data-submit-status')) || state.pendingStatus || PRIMARY_STATUS;
                els.statusInput.value = targetStatus;

                if (!validateForm(targetStatus)) {
                    e.preventDefault();
                    return;
                }

                syncPhotoPayload();
                setSubmittingState(true, targetStatus);
            });

            // Init UI terlebih dahulu lalu simpan snapshot awal untuk deteksi perubahan
            syncPhotoPayload();
            renderPhotoGallery();
            renderSpecRows();
            updatePreview();
            const initialSnapshot = getFormSnapshot();

            if (els.btnBackKatalog) {
                els.btnBackKatalog.addEventListener('click', function (e) {
                    if (getFormSnapshot() === initialSnapshot) return;
                    if (typeof window.looteConfirm !== 'function') return;

                    e.preventDefault();
                    const targetHref = els.btnBackKatalog.getAttribute('href') || 'katalog.php';
                    const primaryActionBtn = IS_LIVE_EDIT ? els.btnSubmit : els.btnSaveDraft;

                    window.looteConfirm({
                        title: 'Yakin ingin keluar dari halaman ini?',
                        message: IS_LIVE_EDIT
                            ? 'Perubahan pada produk belum disimpan. Simpan perubahan sekarang atau keluar dan buang perubahan?'
                            : 'Perubahan yang sudah kamu buat belum disimpan. Simpan progres ke draft atau keluar dan buang progres?',
                        variant: 'warning',
                        confirmText: IS_LIVE_EDIT ? 'Simpan Perubahan' : 'Simpan ke Draft',
                        cancelText: 'Keluar & Buang',
                        cancelClass: 'px-4 py-2 text-sm font-bold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-[2px] transition cursor-pointer',
                        onConfirm: function () {
                            state.pendingStatus = IS_LIVE_EDIT ? PRIMARY_STATUS : 'draft';
                            els.statusInput.value = state.pendingStatus;
                            if (primaryActionBtn && typeof els.form.requestSubmit === 'function') {
                                els.form.requestSubmit(primaryActionBtn);
                            } else if (primaryActionBtn) {
                                primaryActionBtn.click();
                            }
                        },
                        onCancel: function () {
                            window.location.href = targetHref;
                        }
                    });
                });
            }
        })();
    </script>
</body>

</html>

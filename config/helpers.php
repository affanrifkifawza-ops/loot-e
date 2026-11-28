<?php
// helpers.php - Fungsi bantu tampilan yang dipakai lebih dari satu halaman

/**
 * Ikon Phosphor per nama kategori. Kategori baru yang belum terdaftar memakai ikon umum.
 */
function kategoriIcon(string $nama): string
{
    static $map = [
        'gpu'          => 'ph-graphics-card',
        'cpu'          => 'ph-cpu',
        'ram'          => 'ph-memory',
        'motherboard'  => 'ph-circuit-board',
        'storage'      => 'ph-hard-drives',
        'power supply' => 'ph-lightning',
        'pc case'      => 'ph-desktop-tower',
        'cpu cooler'   => 'ph-fan',
        'laptop'       => 'ph-laptop',
        'phone'        => 'ph-device-mobile',
        'smartphone'   => 'ph-device-mobile',
        'monitor'      => 'ph-monitor',
        'headphones'   => 'ph-headphones',
        'headset'      => 'ph-headphones',
        'keyboard'     => 'ph-keyboard',
        'mouse'        => 'ph-mouse',
        'tablet'       => 'ph-device-tablet',
        'aksesoris'    => 'ph-bag-simple',
    ];
    return $map[strtolower(trim($nama))] ?? 'ph-tag';
}

function formatRupiah(int $angka): string
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Mem-parsing kolom `produk.deskripsi` (JSON terstruktur) menjadi 3 bagian untuk tab detail produk:
 * ringkasan, spesifikasi (array label-nilai dinamis), dan riwayat kondisi.
 * Tetap aman jika data lama masih berupa teks biasa (fallback ke ringkasan).
 */
function parseDeskripsiProduk(?string $rawDeskripsi): array
{
    $raw = trim((string)$rawDeskripsi);
    $default = [
        'is_structured' => false,
        'ringkasan'     => $raw,
        'spesifikasi'   => [],
        'riwayat'       => [],
    ];

    if ($raw === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        return $default;
    }

    $spesifikasi = [];
    if (!empty($decoded['spesifikasi']) && is_array($decoded['spesifikasi'])) {
        foreach ($decoded['spesifikasi'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string)($item['label'] ?? ''));
            $nilai = trim((string)($item['nilai'] ?? ''));
            if ($label !== '' && $nilai !== '') {
                $spesifikasi[] = ['label' => $label, 'nilai' => $nilai];
            }
        }
    }

    $riwayat = [];
    if (!empty($decoded['riwayat']) && is_array($decoded['riwayat'])) {
        foreach ($decoded['riwayat'] as $key => $val) {
            $valStr = trim((string)$val);
            if ($valStr !== '') {
                $riwayat[(string)$key] = $valStr;
            }
        }
    }

    return [
        'is_structured' => true,
        'ringkasan'     => trim((string)($decoded['ringkasan'] ?? '')),
        'spesifikasi'   => $spesifikasi,
        'riwayat'       => $riwayat,
    ];
}

/**
 * Mem-parsing kolom `pengguna.alamat` (JSON terstruktur dari alamat_user.php atau teks biasa lama).
 * Mengembalikan null jika alamat kosong.
 */
function parseAlamatPengguna(?string $rawAlamat, array $fallbackUser = []): ?array
{
    $raw = trim((string)$rawAlamat);
    if ($raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
        $parsed = [
            'is_structured'   => true,
            'nama_penerima'   => trim((string)($decoded['nama_penerima'] ?? '')),
            'telepon'         => trim((string)($decoded['telepon'] ?? '')),
            'provinsi'        => trim((string)($decoded['provinsi'] ?? '')),
            'kota'            => trim((string)($decoded['kota'] ?? '')),
            'kecamatan'       => trim((string)($decoded['kecamatan'] ?? '')),
            'kelurahan'       => trim((string)($decoded['kelurahan'] ?? '')),
            'kode_pos'        => trim((string)($decoded['kode_pos'] ?? '')),
            'alamat_lengkap'  => trim((string)($decoded['alamat_lengkap'] ?? '')),
            'detail_tambahan' => trim((string)($decoded['detail_tambahan'] ?? '')),
        ];

        if (
            $parsed['alamat_lengkap'] === '' &&
            $parsed['nama_penerima'] === '' &&
            $parsed['kota'] === '' &&
            $parsed['provinsi'] === ''
        ) {
            return null;
        }

        return $parsed;
    }

    return [
        'is_structured'   => false,
        'nama_penerima'   => trim((string)($fallbackUser['nama'] ?? '')),
        'telepon'         => trim((string)($fallbackUser['no_hp'] ?? '')),
        'provinsi'        => '',
        'kota'            => '',
        'kecamatan'       => '',
        'kelurahan'       => '',
        'kode_pos'        => '',
        'alamat_lengkap'  => $raw,
        'detail_tambahan' => '',
    ];
}

/**
 * Mengubah kolom `pengguna.alamat` (JSON maupun teks biasa) menjadi string lokasi yang rapi untuk ditampilkan.
 */
function formatAlamatRingkas(?string $rawAlamat, bool $kotaOnly = false): string
{
    $parsed = parseAlamatPengguna($rawAlamat);
    if ($parsed === null) {
        return '';
    }

    if ($parsed['is_structured']) {
        if ($kotaOnly) {
            return $parsed['kota'] !== ''
                ? $parsed['kota']
                : ($parsed['provinsi'] !== '' ? $parsed['provinsi'] : $parsed['alamat_lengkap']);
        }

        $parts = array_values(array_filter([
            $parsed['alamat_lengkap'],
            $parsed['kota'],
            $parsed['provinsi'],
        ], static fn($v) => $v !== ''));

        return implode(', ', $parts);
    }

    $raw = $parsed['alamat_lengkap'];
    if ($kotaOnly) {
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));
        return !empty($parts) ? end($parts) : $raw;
    }

    return $raw;
}

/**
 * Mem-parsing kolom `pengguna.info_rekening` (JSON terstruktur atau teks biasa lama seperti "BCA - 1234567890").
 * Mengembalikan null jika data rekening kosong.
 */
function parseRekeningPengguna(?string $rawRekening): ?array
{
    $raw = trim((string)$rawRekening);
    if ($raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
        $parsed = [
            'is_structured'  => true,
            'nama_bank'      => trim((string)($decoded['nama_bank'] ?? '')),
            'nama_pemilik'   => trim((string)($decoded['nama_pemilik'] ?? '')),
            'nomor_rekening' => trim((string)($decoded['nomor_rekening'] ?? '')),
        ];

        if (
            $parsed['nama_bank'] === '' &&
            $parsed['nama_pemilik'] === '' &&
            $parsed['nomor_rekening'] === ''
        ) {
            return null;
        }

        return $parsed;
    }

    $namaBank = '';
    $nomorRekening = $raw;
    if (str_contains($raw, ' - ')) {
        [$bankPart, $rekPart] = explode(' - ', $raw, 2);
        $namaBank = trim($bankPart);
        $nomorRekening = trim($rekPart);
    }

    return [
        'is_structured'  => false,
        'nama_bank'      => $namaBank,
        'nama_pemilik'   => '',
        'nomor_rekening' => $nomorRekening,
    ];
}

/**
 * Mengubah kolom `pengguna.info_rekening` menjadi string satu baris yang rapi untuk tampilan UI.
 */
function formatRekeningRingkas(?string $rawRekening): string
{
    $parsed = parseRekeningPengguna($rawRekening);
    if ($parsed === null) {
        return '';
    }

    $mainParts = array_values(array_filter([
        $parsed['nama_bank'],
        $parsed['nomor_rekening'],
    ], static fn($v) => $v !== ''));

    $summary = implode(' - ', $mainParts);
    if ($parsed['nama_pemilik'] !== '') {
        $summary .= ($summary !== '' ? ' ' : '') . '(a.n. ' . $parsed['nama_pemilik'] . ')';
    }

    return $summary;
}

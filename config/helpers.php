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

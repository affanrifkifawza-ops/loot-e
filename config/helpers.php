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

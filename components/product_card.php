<?php
/**
 * components/product_card.php - Kartu produk seragam Loot-E (mengikuti pola search.php).
 * Membutuhkan $item (array) dan helper dari config/helpers.php.
 * Opsional: $cardExtraClass (string), $cardDataAttrs (string, sudah aman/escaped oleh pemanggil).
 */
$extraClass = $cardExtraClass ?? '';
$dataAttrs  = $cardDataAttrs ?? '';
$cardExtraClass = '';
$cardDataAttrs  = '';

$prodId          = (int)($item['id'] ?? 0);
$namaProdukCard  = (string)($item['nama_produk'] ?? '');
$kategoriCard    = (string)($item['nama_kategori'] ?? 'Elektronik');
$catIcon         = kategoriIcon($kategoriCard);
$kondisiLabel    = trim((string)($item['kondisi'] ?? ''));
if ($kondisiLabel === '') {
    $kondisiLabel = 'Bekas';
}
$kondisiClass    = kondisiBadgeClasses($kondisiLabel);
$hargaCard       = (int)($item['harga'] ?? 0);
$stokCard        = (int)($item['stok'] ?? 1);
$bisaNegoCard    = !empty($item['bisa_nego']);
$bisaBarterCard  = !empty($item['bisa_barter']);
$reviewVal       = (int)($item['total_reviews'] ?? $item['total_ulasan'] ?? 0);
$ratingRaw       = $item['avg_rating'] ?? $item['rating'] ?? 0;
$ratingVal       = number_format((float)$ratingRaw, 1);
$namaPenjualCard = (string)($item['nama_penjual'] ?? 'Loot-E Store');
$penjualVerified = !empty($item['penjual_verified']);
$kotaPenjual     = formatAlamatRingkas($item['alamat_penjual'] ?? '', true);
$lokasiLabel     = $kotaPenjual !== '' ? $kotaPenjual : 'Penjual Terdaftar';
$inisialPenjual  = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $namaPenjualCard), 0, 1) ?: 'S');
?>
<article class="loote-card bg-white border border-gray-200/80 p-4 shadow-2xs flex flex-col group <?= htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') ?>"<?= $dataAttrs ?>>
    <!-- Baris Atas: Badge Kondisi Lengkap & Kategori -->
    <div class="flex items-center justify-between gap-2 mb-2.5">
        <span class="<?= $kondisiClass ?> text-[10px] font-semibold px-2 py-0.5 rounded-md shrink-0 max-w-[65%] truncate"
            title="<?= htmlspecialchars($kondisiLabel, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($kondisiLabel, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <span class="text-[11px] font-medium text-gray-500 truncate">
            <?= htmlspecialchars($kategoriCard, ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <!-- Gambar, Judul, Harga & Tagline Opsi Transaksi -->
    <a href="detail_produk.php?id=<?= $prodId ?>" class="flex-1 flex flex-col cursor-pointer">
        <div class="w-full h-44 overflow-hidden flex items-center justify-center p-3 relative mb-3 bg-[#FAFAFA] border border-gray-100">
            <?php if (!empty($item['foto'])): ?>
                <img src="<?= htmlspecialchars($item['foto'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($namaProdukCard, ENT_QUOTES, 'UTF-8') ?>"
                    width="300" height="176" loading="lazy"
                    class="card-img-primary w-full h-full object-contain" />
            <?php else: ?>
                <div class="w-full h-full flex flex-col items-center justify-center bg-neutral-900 text-white p-3 relative shadow-inner">
                    <span class="text-[9px] font-semibold tracking-wider text-brand-yellow uppercase mb-1">
                        <?= htmlspecialchars($kategoriCard, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <i class="ph <?= htmlspecialchars($catIcon, ENT_QUOTES, 'UTF-8') ?> text-4xl text-gray-200 my-1 group-hover:scale-110 transition-transform" aria-hidden="true"></i>
                    <span class="text-[10px] font-medium text-gray-300 text-center line-clamp-1">
                        <?= htmlspecialchars($namaProdukCard, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php if ($penjualVerified): ?>
                        <span class="absolute top-1.5 right-2 text-[8px] text-brand-yellow font-semibold">VERIFIED</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <span class="card-hover-hint absolute bottom-2 right-2 z-10 bg-brand-yellow text-[#272727] text-[10px] font-semibold px-2.5 py-1 rounded-sm shadow-xs inline-flex items-center gap-1">
                Lihat Detail <i class="ph ph-arrow-up-right text-[10px]" aria-hidden="true"></i>
            </span>
        </div>

        <h3 class="text-sm font-semibold text-gray-800 leading-snug group-hover:text-black line-clamp-2 min-h-[2.5rem]">
            <?= htmlspecialchars($namaProdukCard, ENT_QUOTES, 'UTF-8') ?>
        </h3>

        <p class="text-base font-bold text-[#272727] mt-1.5 tracking-tight tabular-nums">
            <?= formatRupiah($hargaCard) ?>
        </p>

        <!-- Tagline Opsi Transaksi -->
        <div class="flex flex-wrap items-center gap-1.5 mt-2.5 min-h-[24px]">
            <?php if ($bisaNegoCard): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200/80 text-[10px] font-semibold">
                    <i class="ph-bold ph-tag text-[11px]" aria-hidden="true"></i> Bisa Nego
                </span>
            <?php endif; ?>
            <?php if ($bisaBarterCard): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200/80 text-[10px] font-semibold">
                    <i class="ph-bold ph-arrows-left-right text-[11px]" aria-hidden="true"></i> Bisa Barter
                </span>
            <?php endif; ?>
            <?php if (!$bisaNegoCard && !$bisaBarterCard): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-50 text-gray-600 border border-gray-200/80 text-[10px] font-medium">
                    <i class="ph ph-check-circle text-[11px]" aria-hidden="true"></i> Harga Pas
                </span>
            <?php endif; ?>
        </div>
    </a>

    <!-- Footer Card: Rating, Stok & Info Penjual -->
    <div class="mt-auto pt-3">
        <div class="flex items-center justify-between gap-2 text-xs text-gray-600 pb-2.5 border-b border-gray-100">
            <?php if ($reviewVal > 0): ?>
                <span class="flex items-center gap-1 font-medium text-gray-700">
                    <i class="ph-fill ph-star text-amber-500 text-xs" aria-hidden="true"></i>
                    <?= htmlspecialchars($ratingVal, ENT_QUOTES, 'UTF-8') ?>
                    <span class="text-gray-500 font-normal">(<?= $reviewVal ?>)</span>
                </span>
            <?php else: ?>
                <span class="text-gray-500 text-[11px]">Belum ada ulasan</span>
            <?php endif; ?>
            <span class="text-[11px] font-normal text-gray-500 shrink-0 tabular-nums">
                Stok <?= $stokCard ?>
            </span>
        </div>

        <div class="flex items-center justify-between gap-2 pt-2.5 text-xs">
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-6 h-6 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center font-semibold text-[10px] shrink-0">
                    <?= htmlspecialchars($inisialPenjual, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-800 text-xs leading-none truncate">
                        <?= htmlspecialchars($namaPenjualCard, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="text-[10px] font-normal text-gray-500 leading-tight mt-0.5 truncate">
                        <?= htmlspecialchars($lokasiLabel, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            </div>
            <?php if ($penjualVerified): ?>
                <span class="text-[10px] text-emerald-700 font-semibold flex items-center gap-1 shrink-0">
                    <i class="ph-fill ph-shield-check text-xs" aria-hidden="true"></i> Trusted Seller
                </span>
            <?php endif; ?>
        </div>
    </div>
</article>

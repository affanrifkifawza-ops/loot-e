# ADR-0002: Redefinisi `produk.status_produk` & Alur Penawaran Paralel (Multi-Offer)

- **Status:** Accepted
- **Tanggal:** 2026-11-27
- **Konteks:**
  Skema awal ([db_loote.sql](../../db/db_loote.sql)) memiliki `produk.status_produk ENUM('tersedia', 'terkunci', 'habis')` serta tabel `antrean` (Priority Queue serial). Sementara itu, halaman manajemen toko ([katalog.php](../../katalog.php)) membutuhkan status `draft` dan `nonaktif`. Selain itu, status `terkunci` menghambat penjualan produk dengan `stok > 1` (karena mengunci seluruh listing hanya untuk 1 transaksi), dan antrean satu-per-satu tidak efisien untuk `stok = 1` dibanding membiarkan penjual memilih tawaran terbaik dari beberapa pembeli sekaligus.

## Keputusan
1. **Status Produk (`produk.status_produk`):**
   Menghapus `'terkunci'` dan menambahkan `'draft'` serta `'nonaktif'`:
   `ENUM('tersedia', 'draft', 'nonaktif', 'habis') DEFAULT 'tersedia'`
   Selaras 1:1 dengan 4 status di [katalog.php](../../katalog.php) (`Sedang Tayang`, `Draft`, `Nonaktif`, `Terjual`).
2. **Penghapusan Tabel `antrean` & Beralih ke Penawaran Paralel:**
   Tabel `antrean` dihapus (skema menjadi 11 tabel). Banyak pembeli dapat mengajukan `negosiasi` atau `barter` secara bersamaan pada produk yang sama tanpa saling mengunci; penjual bebas memilih penawaran terbaik.
3. **Reservasi Berbasis Stok (BR-02):**
   Stok dikurangi secara atomik (`stok = stok - kuantitas`) saat transaksi masuk tahap `pesanan` (`menunggu_pembayaran`), baik dari *Buy Now* maupun kesepakatan *Nego/Barter*. Produk tetap `tersedia` selama `stok > 0`, dan otomatis menjadi `habis` saat `stok = 0`. Pada barter yang disepakati, stok kedua produk dikurangi bersamaan. Jika pesanan dibatalkan sebelum dibayar, stok dikembalikan dan status `habis` kembali ke `tersedia`.
4. **Status Penutup `gagal` & Feedback Kontekstual (BR-03):**
   `negosiasi.status` dan `barter.status` ditambah `'gagal'` serta kolom `updated_at`. Saat stok berkurang atau produk dinonaktifkan, seluruh sesi aktif (`pending`, `counter_offer`) dengan kuantitas melebihi sisa stok (atau semua sesi aktif bila `stok = 0` / `nonaktif`) otomatis ditutup ke `'gagal'`. UI wajib membedakan pesan untuk kasus habis terjual, sisa stok tidak mencukupi, produk dinonaktifkan, maupun stok kembali tersedia (jika pemenang batal bayar).
5. **Aturan Anti-Spam Re-Offer Setelah `ditolak`:**
   - Maksimal **1 sesi aktif** per pembeli per produk pada waktu bersamaan.
   - Maksimal **3x percobaan per hari** per pembeli per produk (`gagal` tidak memotong kuota).
   - **Cooldown 30 menit** sejak penawaran ditolak (dihitung dari `updated_at`).
   - Penawaran baru **wajib lebih tinggi** dari tawaran terakhir yang ditolak (barter: naikkan `tambah_dana` atau ganti produk tukaran), dengan bantuan *suggested price* di UI dan batas minimum wajar 50% harga listing.

## Konsekuensi
- **Positif:** Skema lebih ringkas (11 tabel), mendukung penjualan multi-stok secara paralel, memisahkan penolakan manual (`ditolak`) dari kegagalan stok (`gagal`), serta mencegah *zombie offer* saat pesanan dibatalkan.
- **Perhatian ke depan:** Database lokal `db_loote` perlu di-import ulang; seluruh transaksi *checkout* / *accept nego* / *accept barter* wajib memakai transaksi DB (`SELECT ... FOR UPDATE`) agar pemotongan stok dan pembaruan status `gagal` tetap atomik.

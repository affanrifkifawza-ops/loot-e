# Handoff Document: Persiapan & Antisipasi Bug Fitur Upload Produk (Seller)

## Context & Current State
- **Workspace:** `c:\laragon\www\loot-e`
- **Tech Stack:** PHP Native (PDO MySQL), HTML5, Tailwind CSS (CDN), Vanilla JavaScript
- **Referensi Utama (jangan diduplikasi, baca langsung):**
  - Spesifikasi produk & *business rules*: `PRD.md` (fokus bagian 4.1, 4.2, dan 5 / BR-01 s.d. BR-05)
  - Skema database & seeder: `db/db_loote.sql`
  - Kontrak format JSON `produk.deskripsi`: `docs/adr/0001-structured-product-description.md`
  - Riwayat audit & refaktor tahap sebelumnya: `loote-audit-handoff.md`
- **Status Kesiapan Saat Ini:**
  - Skema tabel `produk`, `kategori`, dan `pengguna` (termasuk kolom verifikasi seller `no_hp`, `alamat`, `info_rekening`, `is_verified`) sudah siap.
  - Query etalase di `index.php`, `search.php`, `landingpage.php`, dan `detail_produk.php` aman menerima data produk baru (termasuk produk dengan 0 ulasan) tanpa error SQL/PHP.
  - Belum ada halaman verifikasi seller, halaman upload/kelola produk, maupun folder penyimpanan gambar `uploads/produk/`.

---

## Catatan Antisipasi Bug (Wajib Diperhatikan Saat Implementasi & Perbaikan)

### 1. Tampilan Foto Produk (`produk.foto`) & Folder Upload
- **Masalah saat ini:** Foto yang nanti diupload belum akan tampil di halaman manapun.
  - `index.php` (baris 20), `landingpage.php` (baris 8), dan query `$relatedProducts` di `detail_produk.php` (baris 30) **belum men-`SELECT p.foto`**.
  - `search.php` (baris 321–332) dan `detail_produk.php` (baris 223–288) sudah `SELECT p.*`, namun HTML-nya masih merender placeholder ikon kategori (belum ada `<img src="...">` dengan fallback ikon jika `foto` kosong/NULL).
  - JS galeri di `detail_produk.php` (baris 955–968) masih hardcoded ke data dummy `"ASUS GeForce GTX 750 Ti"`.
- **Antisipasi saat membuat upload:**
  - Buat folder `uploads/produk/` otomatis di PHP (`if (!is_dir(...)) mkdir(..., 0755, true)`) agar `move_uploaded_file()` tidak gagal.
  - Tambahkan `uploads/` ke `.gitignore` agar file upload lokal tidak ikut ter-commit.
  - Validasi upload di backend: cek `$_FILES['foto']['error'] === UPLOAD_ERR_OK`, batas ukuran (mis. maks 2 MB), validasi MIME asli (`finfo_file()` / `getimagesize()`, bukan hanya ekstensi), dan rename file dengan nama unik (mis. `uniqid('prod_', true) . '.' . $ext`).
  - Catatan tipe kolom: `produk.foto` adalah `VARCHAR(255)` (aman untuk 1 foto utama; jika nanti butuh multi-foto, ubah struktur kolom atau buat tabel terpisah).

### 2. Format Nilai Kolom `produk.kondisi` (Filter `search.php`)
- **Masalah potensial:** Filter di `search.php` (baris 53–57 & 304) menggunakan pencocokan ketat:
  - `Baru` → `p.kondisi = 'Baru'`
  - `Bekas` → `p.kondisi LIKE 'Bekas%'`
- **Antisipasi:** Jangan gunakan input teks bebas untuk `kondisi`. Wajib pakai `<select>` dengan opsi baku (misal: `Baru`, `Bekas - Seperti Baru`, `Bekas - Mulus`, `Bekas - Ada Minus`) dan validasi whitelist di sisi PHP sebelum `INSERT`/`UPDATE`.

### 3. Format JSON pada `produk.deskripsi` (ADR-0001)
- **Masalah potensial:** `parseDeskripsiProduk()` di `config/helpers.php` (baris 42–91) mem-parsing JSON dengan struktur `ringkasan`, `spesifikasi` (`[{label, nilai}]`), dan `riwayat` (`fisik`, `fungsi`, `lama_pakai`, `servis`, `minus`, `kelengkapan`). Jika form menyimpan teks biasa, tidak error (fallback ke `ringkasan`), tetapi tab **Spesifikasi** dan **Riwayat & Kondisi** di `detail_produk.php` akan kosong.
- **Antisipasi:** Susun payload array di PHP lalu encode dengan `json_encode($payload, JSON_UNESCAPED_UNICODE)` sebelum disimpan ke `produk.deskripsi`.

### 4. Sinkronisasi Session `is_verified` & Guard BR-04
- **Masalah potensial:** `isVerifiedSeller()` di `auth.php` (baris 50–52) hanya membaca `$_SESSION['is_verified']`.
- **Antisipasi:**
  - Saat user selesai verifikasi seller (`UPDATE pengguna SET no_hp=?, alamat=?, info_rekening=?, is_verified=1 WHERE id=?`), **wajib langsung set `$_SESSION['is_verified'] = 1`**.
  - Di halaman upload/edit/hapus produk, cek ulang `is_verified` ke DB (atau sinkronkan ke session) agar status tetap valid jika diubah admin (BR-04).
  - Validasi input angka di PHP: `harga > 0` dan `<= 2000000000` (batas aman `INT` signed MySQL), `stok >= 1`, `id_kategori` harus eksis di tabel `kategori`.

### 5. Whitelist Redirect di `login.php` & `register.php`
- **Masalah potensial:** `$allowedRedirects` di `login.php` (baris 6) dan `register.php` (baris 6) saat ini hanya `["index.php", "categories.php", "search.php"]`.
- **Antisipasi:** Tambahkan halaman baru (`verifikasi_seller.php`, `tambah_produk.php`, `produk_saya.php`, serta `detail_produk.php`) ke `$allowedRedirects` agar redirect pasca-login tidak dilempar balik ke `index.php`.

### 6. Pelanggaran BR-01 & Data Hardcoded di `detail_produk.php`
- **Masalah saat ini:**
  - `detail_produk.php` (baris 391–424) belum mengecek `$isOwnProduct`, sehingga penjual masih melihat tombol *Ajukan Harga / Barter / Keranjang / Beli Sekarang* di produknya sendiri (melanggar BR-01).
  - Saat menambahkan pengecekan `$isOwnProduct`, pastikan cek `$currentUser !== null` dulu (`$isOwnProduct = $currentUser && (int)$produk['id_penjual'] === $currentUser['id'];`) karena `detail_produk.php` saat ini bisa diakses guest.
  - Data masih hardcoded di `detail_produk.php`: rating `4.8 (28 ulasan)` & ulasan `BudiSantoso` (baris 305–306, 532, 636–788), harga pasar `Rp 600.000 - Rp 750.000` (baris 337, 354–364), teks modal/toast `"ASUS GTX 750 Ti"` (baris 853, 861–862, 1104), dan badge/statistik penjual (baris 440–464 — query baris 15 belum mengambil `u.is_verified`).

### 7. Entry Point Navigasi
- `components/navbar.php` belum memiliki link/tombol menuju halaman **Jual Barang** / **Produk Saya** (baik di desktop maupun mobile menu).

---

## Urutan Progres Berikutnya (Rekomendasi)
1. **Seller Onboarding & Navbar:** Buat `verifikasi_seller.php` (gate `is_verified = 0 -> 1`) + tambahkan tombol "Jual Barang" di `components/navbar.php` + update `$allowedRedirects` di `login.php` & `register.php`.
2. **CRUD Produk Seller:** Buat `tambah_produk.php` (form terstruktur JSON ADR-0001 + upload foto aman + dropdown kondisi baku) dan `produk_saya.php` (daftar, edit, hapus produk milik seller sendiri).
3. **Sinkronisasi Tampilan Foto & Cleanup `detail_produk.php`:** Tampilkan `p.foto` (fallback ikon kategori) di `index.php`, `search.php`, `landingpage.php`, dan `detail_produk.php`; terapkan BR-01 dan query ulasan asli di `detail_produk.php`.
4. **Jalur Transaksi MVP:** Keranjang & Buy Now/Checkout + Escrow (BR-02), Negosiasi & Barter dengan timer 30 menit (BR-03), Input Resi & Ulasan (BR-05), lalu Admin Panel (`admin/index.php`).

---

## Suggested Skills
Untuk sesi berikutnya, panggil skill berikut sesuai kebutuhan:
- `ponytail` — menjaga implementasi PHP Native tetap ringkas, aman, dan bebas *over-engineering*.
- `antislop` & `antislop-ui` — saat merancang form upload produk, verifikasi seller, dan pembaruan tampilan kartu/galeri produk.
- `ponytail-review` — untuk meninjau keamanan upload file, query PDO, dan kepatuhan BR-01 s.d. BR-05 setelah fitur selesai dibuat.

# Handoff Document: Persiapan & Antisipasi Bug Fitur Upload Produk (Seller)

## Context & Current State
- **Workspace:** `c:\laragon\www\loot-e`
- **Tech Stack:** PHP Native (PDO MySQL), HTML5, Tailwind CSS (CDN), Vanilla JavaScript
- **Referensi Utama (jangan diduplikasi, baca langsung):**
  - Spesifikasi produk & *business rules*: `PRD.md` (fokus bagian 4.1, 4.2, dan 5 / BR-01 s.d. BR-05)
  - Skema database & seeder: `db/db_loote.sql`
  - Kontrak format JSON `produk.deskripsi`: `docs/adr/0001-structured-product-description.md`
  - Riwayat audit & refaktor tahap sebelumnya: `docs/loote-audit-handoff.md`
- **Status Kesiapan Saat Ini:**
  - Skema tabel `produk`, `kategori`, dan `pengguna` (kolom verifikasi seller: `nik` `CHAR(16)`, `info_rekening` `TEXT` JSON, `is_verified`; kolom profil opsional: `no_hp`, `alamat` dengan default `NULL`) sudah siap.
  - Query etalase di `index.php`, `search.php`, `landingpage.php`, dan `detail_produk.php` aman menerima data produk baru dan sudah mendukung rendering kolom `p.foto` (fallback ikon kategori jika kosong).
  - Pembersihan data hardcoded, guard wajib login, serta aturan BR-01 pada `detail_produk.php` sudah diterapkan.
  - Whitelist redirect di `login.php` & `register.php` sudah mencakup `detail_produk.php`, `verifikasi_akun.php`, `alamat_user.php`, `dashboard_seller.php`, `katalog.php`, dan `upload_produk.php`.
  - Halaman gate `verifikasi_akun.php`, `alamat_user.php`, `dashboard_seller.php`, `katalog.php`, dan `upload_produk.php` sudah tersedia; UI formulir verifikasi seller akan ditambahkan sebagai state lanjutan di dalam `verifikasi_akun.php`.

---

## Catatan Antisipasi Bug (Wajib Diperhatikan Saat Implementasi & Perbaikan)

### 1. Tampilan Foto Produk (`produk.foto`) & Folder Upload
- **Status rendering saat ini (Sudah Siap):** `index.php`, `search.php`, `landingpage.php`, dan `detail_produk.php` sudah men-`SELECT p.foto` dan menampilkan `<img src="...">` jika kolom terisi (fallback ikon kategori jika `NULL`/kosong).
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
  - Saat user selesai verifikasi seller (`UPDATE pengguna SET nik=?, info_rekening=?, is_verified=1 WHERE id=?`), validasi backend cukup memastikan `nik` 16 digit angka (unik), payload JSON `info_rekening` (`nama_bank`, `nama_pemilik`, `nomor_rekening`) lengkap (gunakan helper `parseRekeningPengguna()` / `formatRekeningRingkas()` saat membaca), dan persetujuan tercentang, lalu **wajib langsung set `$_SESSION['is_verified'] = 1`**. Field demo lain pada form verifikasi (nama sesuai KTP, tanggal lahir, jenis kelamin, foto KTP, selfie wajah) bersifat opsional di frontend dan tidak disimpan ke database, sementara `no_hp` dan `alamat` bersifat opsional milik profil pengguna (`profile.php` & `alamat_user.php`).
  - **Catatan Sesi Berikutnya (UI Form Verifikasi & Approval Admin):** Form verifikasi ditempatkan sebagai state di dalam `verifikasi_akun.php` (pola state seperti `alamat_user.php`) dengan tombol `Batal` untuk kembali ke state awal (tanpa fitur draft). Pengaturan langsung `is_verified = 1` bersifat sementara selama fokus pengembangan masih pada user biasa; alur approval oleh admin akan dibahas kembali saat halaman admin dibuat.
  - Di halaman upload/edit/hapus produk, cek ulang `is_verified` ke DB (atau sinkronkan ke session) agar status tetap valid jika diubah admin (BR-04).
  - Validasi input angka di PHP: `harga > 0` dan `<= 2000000000` (batas aman `INT` signed MySQL), `stok >= 1` untuk produk `tersedia`, `id_kategori` harus eksis di tabel `kategori`, dan `status_produk` wajib whitelist (`tersedia`, `draft`, `nonaktif`, `habis` — lihat `docs/adr/0002-product-status-and-parallel-offers.md`).

### 5. Whitelist Redirect di `login.php` & `register.php`
- **Status saat ini:** `$allowedRedirects` sudah mencakup `["index.php", "categories.php", "search.php", "detail_produk.php"]`.
- **Antisipasi:** Pastikan halaman akun dan seller (`verifikasi_akun.php`, `alamat_user.php`, `dashboard_seller.php`, `katalog.php`, `upload_produk.php`) tetap terdaftar di `$allowedRedirects` agar redirect pasca-login tetap berjalan.

### 6. Guard BR-01 & Data Dinamis di `detail_produk.php`
- **Status saat ini (Sudah Diterapkan):**
  - `detail_produk.php` sudah mewajibkan login, menerapkan guard BR-01 (`$isOwnProduct`), memuat statistik penjual & ulasan nyata dari database, dan menyinkronkan modal/toast dengan data produk asli.
  - Fitur **Referensi Harga Pasar** sengaja tidak direfaktor karena akan disederhanakan dan dipindahkan terpisah di sisi frontend JS sesuai PRD.

### 7. Entry Point Navigasi
- `components/navbar.php` belum memiliki link/tombol menuju halaman **Jual Barang** / **Produk Saya** (baik di desktop maupun mobile menu).

---

## Urutan Progres Berikutnya (Rekomendasi)
1. **Seller Onboarding & Verifikasi Akun:** Lengkapi state form verifikasi pada `verifikasi_akun.php` (`is_verified = 0 -> 1` sementara sebelum approval admin) + tambahkan tombol "Jual Barang" di `components/navbar.php` jika diperlukan.
2. **CRUD Produk Seller:** Buat `tambah_produk.php` (form terstruktur JSON ADR-0001 + upload foto aman + dropdown kondisi baku) dan `produk_saya.php` (daftar, edit, hapus produk milik seller sendiri).
3. **Jalur Transaksi MVP:** Keranjang & Buy Now/Checkout + Escrow (BR-02), Negosiasi & Barter dengan timer 30 menit (BR-03), Input Resi & Ulasan (BR-05), lalu Admin Panel (`admin/index.php`).

---

## Suggested Skills
Untuk sesi berikutnya, panggil skill berikut sesuai kebutuhan:
- `ponytail` — menjaga implementasi PHP Native tetap ringkas, aman, dan bebas *over-engineering*.
- `antislop` & `antislop-ui` — saat merancang form upload produk, verifikasi seller, dan pembaruan tampilan kartu/galeri produk.
- `ponytail-review` — untuk meninjau keamanan upload file, query PDO, dan kepatuhan BR-01 s.d. BR-05 setelah fitur selesai dibuat.

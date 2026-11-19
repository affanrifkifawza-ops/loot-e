# Handoff Document: Loot-E Phase 1 Audit & Refactor Plan

## Context & Current State
- **Workspace:** `c:\laragon\www\loot-e`
- **Branch:** `refactor/code-audit`
- **Tech Stack:** PHP Native (PDO MySQL), HTML5, Tailwind CSS (CDN), Vanilla JS
- **Source of Truth:**
  - `c:\laragon\www\loot-e\PRD.md` (Version 2.0 - C2C Electronics Marketplace, single `user` role with `is_verified` seller gate + `admin` role, 3 transaction paths: Buy Now, Nego, Barter, Escrow system)
  - `c:\laragon\www\loot-e\db\db_loote.sql` (12 tables using `INT AUTO_INCREMENT` PKs, Indonesian table/column names, seeders included)

## Phase 1 Audit Findings Summary

### 1. Database Schema (`db/db_loote.sql`)
- **Status:** Layak pakai (Ready to use), struktur 12 tabel, PK/FK, ENUM, dan seeder sudah sesuai `PRD.md`.
- **Catatan Kecil / Potensi Penyesuaian:**
  1. **Collation `utf8mb4_0900_ai_ci`**: Valid di MySQL 8.0+ (standar Laragon baru), namun jika dijalankan di MariaDB / MySQL 5.7 akan error (`Unknown collation`). Opsional diganti ke `utf8mb4_general_ci` jika butuh kompatibilitas lintas versi.
  2. **Header SQL (`CREATE DATABASE` / `USE`)**: Belum ada `CREATE DATABASE IF NOT EXISTS db_loote; USE db_loote;` di awal file (harus klik DB dulu di phpMyAdmin sebelum import).
  3. **Sinkronisasi Seeder `kategori` (8 kategori) vs UI `categories.php` (16 kategori)**: Tabel `kategori` berisi 8 kategori, sedangkan `categories.php` menampilkan 16 kategori hardcoded.
  4. **Tabel `keranjang`**: Belum ada `UNIQUE KEY (id_pembeli, id_produk)`, sehingga pencegahan duplikasi baris produk yang sama di keranjang harus ditangani di logika PHP (`SELECT` sebelum `INSERT`/`UPDATE`).
  5. **Kolom `nomor_resi` di `pesanan`**: Menyimpan nama kurir + nomor resi sekaligus (contoh: `"JNE - JP12345678"`), sesuai pola `info_rekening`.

### 2. Codebase Issues Identified
- **`register.php` (CRITICAL BUG)**:
  - Masih menggunakan `generateUuidV4()` untuk `id` (padahal `pengguna.id` sekarang `INT AUTO_INCREMENT`).
  - Masih menggunakan `$peran = 'buyer'` dan `$validPeran = ['buyer', 'seller', 'admin']` (padahal `pengguna.peran` sekarang `ENUM('user', 'admin') DEFAULT 'user'`). Insert akan selalu gagal dengan PDOException.
- **`auth.php`**:
  - Fungsi `generateUuidV4()` sudah tidak relevan (dead code).
  - Default `peran` di `getCurrentUser()` masih `'buyer'`, belum menyimpan/mengambil `is_verified` dari session.
  - `requireAuth()` memiliki deteksi AJAX/JSON yang tidak terpakai.
- **`login.php`**:
  - Query `SELECT` belum mengambil kolom `is_verified` ke dalam `$_SESSION`.
  - Belum melakukan redirect berdasarkan `peran` (`admin` vs `user`) sesuai PRD 4.1.
  - Memuat `<link rel="stylesheet" href="style.css" />` yang filenya tidak ada (404).
  - Form POST tidak menyertakan input hidden `redirect`, dan variabel `$info` tidak pernah ditampilkan di HTML.
- **`index.php`**:
  - Seluruh daftar kategori dan produk (`$curatedPilihan`, `$curatedTrending`, `$curatedNewArrivals`) masih berupa array PHP statis/hardcoded, belum mengambil dari tabel `produk` dan `kategori`.
  - Link kategori mengarah ke `categories.php?cat=...` yang tidak diproses oleh `categories.php`.
- **`categories.php`**:
  - Daftar 16 kategori masih hardcoded di array PHP dan link mengarah ke `index.php?kategori=<slug>` (tidak terhubung ke query database di `search.php`).
- **`search.php`**:
  - Sudah query ke DB baru, namun badge `"Bisa Nego"` & `"Bisa Barter"` serta tombol aksi masih muncul statis di semua produk tanpa mengecek nilai kolom `bisa_nego` dan `bisa_barter`.
  - Filter sidebar belum memiliki filter opsi transaksi (`bisa_nego` / `bisa_barter`) sesuai PRD 4.3, dan masih ada checkbox dummy `"Trusted Seller"`.
  - Pagination menggunakan `array_slice()` dan link halaman belum mempertahankan query parameter filter lainnya.
- **`landingpage.php` & `help.php`**:
  - Duplikasi inisialisasi session manual (`'peran' => 'buyer'`) alih-alih memakai `auth.php`.
- **Unused / Redundant Files**:
  - `assets/logo loote.jpeg` (untracked file, duplikat dari `assets/logo.png`).

## Refactor To-Do List (Ordered by Priority)
- [x] **Tahap 1:** Finalisasi & Validasi Database (`db/db_loote.sql` & `config/koneksi.php`)
  - `config/koneksi.php`: Error handling dirapikan agar tidak memaksa header JSON pada halaman HTML.
  - `db/db_loote.sql`: Ditambahkan `CREATE DATABASE IF NOT EXISTS db_loote; USE db_loote;` dan `UNIQUE KEY uq_keranjang_user_produk (id_pembeli, id_produk)`.
  - Keputusan user: Tetap pakai 8 kategori awal di DB (nanti kategori dibaca dinamis dari DB agar user bisa tambah sendiri kapan saja); penyesuaian frontend kecil disetujui asal tidak mengubah UI signifikan.
- [x] **Tahap 2:** Refaktor Core Auth & Session (`auth.php`, `login.php`, `register.php`)
  - `auth.php`: Hapus `generateUuidV4()`, tambah `is_verified` di `getCurrentUser()`, tambah helper `isVerifiedSeller()` (BR-04), sederhanakan `requireAuth()`, default `peran` diubah ke `'user'`.
  - `register.php`: Hapus logika role lama (`buyer`/`seller`/`admin`) & UUID; `INSERT` memakai `AUTO_INCREMENT` (`lastInsertId()`), default `peran = 'user'`, `is_verified = 0`, tambah `session_regenerate_id(true)`, perbaiki link logo ke `landingpage.php`.
  - `login.php`: `SELECT` memuat `is_verified`, simpan ke `$_SESSION['is_verified']`, tambah redirect berbasis `peran` (`admin` ke `admin/index.php` jika sudah ada), tambah hidden input `redirect`, render alert `$info`, hapus `<link href="style.css">`, perbaiki link logo ke `landingpage.php`.
- [x] **Tahap 3:** Integrasi Database pada Main Page, Categories & Search (`index.php`, `categories.php`, `search.php`)
  - Baru: `config/helpers.php` (`kategoriIcon()` dengan fallback `ph-tag`, `formatRupiah()`), di-require dari `auth.php`.
  - `index.php`: array dummy dihapus; kategori & 3 section produk (Pilihan = bisa nego/barter, Trending = ulasan terbanyak, New Arrivals = terbaru) dibaca dari DB. Harga coret, rating, dan kota palsu dihapus (diganti kondisi, rating asli/"Belum ada ulasan", label Nego/Barter). Link kategori ke `search.php?kategori=<id>`.
  - `categories.php`: kategori dari tabel `kategori`; grup "PC Components" ditentukan daftar nama di PHP, sisanya (termasuk kategori baru) masuk "Elektronik Lainnya". Link ke `search.php?kategori=<id>`.
  - `search.php`: pagination via SQL (COUNT + LIMIT/OFFSET), tanpa GROUP BY; rating dari subquery (tanpa default 4.8/24 palsu); filter kondisi diperbaiki (`Baru` = persis, `Bekas` = prefix, sebelumnya "Bekas - Seperti Baru" ikut terfilter Baru); checkbox dummy "Trusted Seller" diganti filter Bisa Nego/Bisa Barter; badge & tombol Nego/Barter tampil sesuai kolom produk dan disembunyikan untuk produk sendiri (BR-01); link pagination mempertahankan semua filter; tombol "Chat Seller" yang tidak berfungsi dihapus.
  - Belum diuji lewat browser/DB (sandbox tidak bisa konek MySQL); `php -l` semua file lulus.
  - Sisa UI palsu di `search.php` (ditunda, UI diperbaiki user nanti): label "Jakarta" dan "Fast Response" masih statis.
- [x] **Tahap 4:** Cleanup & Standarisasi + alur pengunjung (`landingpage.php`, `help.php`, `components/navbar.php`, `components/footer.php`, `login.php`)
  - `landingpage.php` & `help.php`: inisialisasi session manual diganti `require_once "auth.php"` + `getCurrentUser()`.
  - Alur: `index.php`, `categories.php`, `search.php` tetap wajib login (guest dialihkan ke `landingpage.php` / `login.php?redirect=...`). Guest hanya melihat landing page.
  - `landingpage.php` section "Jelajahi Produk": 5 produk terbaru dari tabel `produk` (status tersedia); klik kartu -> `login.php?redirect=search.php?q=<nama>` untuk guest, langsung `search.php` untuk user login. Tanpa foto karena kolom `foto` belum dipakai.
  - Navbar/footer: "Categories" untuk guest -> `landingpage.php#produk` (user login tetap `categories.php`); "About" -> `landingpage.php#why-loote` (sebelumnya menunjuk `index.php#why-loote` yang tidak ada); label profil mobile: Admin / Verified Seller / Pengguna.
  - `login.php`: pesan info "Silakan login terlebih dahulu" muncul untuk semua redirect, bukan hanya kategori.
  - Dihapus: `assets/logo loote.jpeg` (tidak dipakai).
  - Belum ditangani: `help.php` masih bisa dibuka guest (info saja); mockup di section "Pilih Cara Bertransaksimu" & "Wawasan Keputusan" di landing masih angka ilustrasi statis (Rp 9.200.000, 4.8/5.0, 128 transaksi, dsb).

## Suggested Skills for Next Session
- `ponytail` (for keeping PHP Native refactors minimal, clean, and free of overengineering)
- `antislop` / `antislop-ui` (when adjusting UI components)
- `handoff` (to update progress after each refactor step)

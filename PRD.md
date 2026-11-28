# PRODUCT REQUIREMENT DOCUMENT (PRD): LOOT-E

**Version:** 2.0 (Scope & Development Context)  
**Product Name:** Loot-E (Electronics Marketplace)  
**Tech Stack:** PHP Native, MySQL (phpMyAdmin), HTML5, Tailwind CSS, Vanilla JavaScript

---

## 1. PRODUCT OVERVIEW & VISION

**Loot-E** adalah platform _marketplace_ C2C (_Customer-to-Customer_) yang berfokus pada jual-beli dan tukar-tambah komponen serta perangkat elektronik (baru maupun bekas).

**Nilai Utama (Core Value):**

- **Transparansi Kondisi Produk:** Informasi fisik, fungsi, riwayat pemakaian, riwayat servis, kelengkapan, hingga _defect_/minus disajikan secara terstruktur.
- **Fleksibilitas Transaksi:** Mendukung 3 jalur transaksi dalam satu platform: **Beli Langsung (Buy Now)**, **Negosiasi Harga**, dan **Barter / Tukar Tambah**.
- **Keamanan & Proteksi (Escrow):** Dana transaksi ditahan oleh sistem hingga barang diterima pembeli, dilengkapi opsi proteksi garansi retur berbasis video _unboxing_.
- **Alat Bantu Keputusan:** Dilengkapi _Referensi Harga Pasar_ saat pencarian dan _Smart Bottleneck Checker_ untuk mengecek kecocokan komponen PC/Laptop sebelum membeli.

---

## 2. ARCHITECTURE & TECH STACK CONSTRAINTS

- **Backend:** PHP Native (tanpa _framework_, menggunakan arsitektur prosedural/modular yang bersih dengan `PDO` atau `mysqli` _prepared statements_ untuk mencegah SQL Injection).
- **Database:** MySQL (skema sudah disiapkan secara terpisah menggunakan penamaan tabel dan kolom berbahasa Indonesia dengan _Primary Key_ `INT AUTO_INCREMENT`).
- **Frontend & Styling:** HTML5 dan Tailwind CSS (via CDN).
- **Interactivity:** Vanilla JavaScript (untuk modal interaktif, _countdown timer_ negosiasi/barter, _Smart Bottleneck Checker_, dan _Market Price Reference_).
- **Scope Boundary (Out of Scope MVP):** Tidak menggunakan _payment gateway_ pihak ketiga asli (gunakan simulasi konfirmasi bayar/QRIS statis), tidak menggunakan API kurir eksternal (input kurir & nomor resi manual), dan tidak membuat aplikasi _mobile native_.

---

## 3. USER ROLES & PERMISSIONS (C2C MODEL)

Loot-E menerapkan konsep **Single User Role** untuk pengguna umum. Tidak ada pemisahan akun statis antara pembeli dan penjual.

| Peran (`peran`)                | Hak Akses & Kapabilitas                                                                                                                                                                                                                                                                 |
| :----------------------------- | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **`user` (Unverified Seller)** | Hak akses _default_ saat registrasi. Dapat melakukan _browsing_, pencarian, menggunakan _Bottleneck Checker_, menambah keranjang, melakukan _Buy Now_, mengajukan _Nego/Barter_, melacak pesanan, dan memberi ulasan. **Belum dapat mengunggah produk.**                                |
| **`user` (Verified Seller)**   | Pengguna biasa yang telah melengkapi data verifikasi seller (**`nik`** 16 digit dan **`info_rekening`** JSON sehingga `is_verified = 1`). Memiliki seluruh hak akses pembeli **ditambah** hak untuk membuat/mengelola listing produk, merespons tawaran nego/barter, serta menginput nomor resi pengiriman. |
| **`admin`**                    | Mengelola master data pengguna, kategori, dan produk; memonitor seluruh transaksi; memverifikasi pembayaran/pencairan _Escrow_; serta melihat laporan pendapatan platform (dari biaya layanan).                                                                                         |

---

## 4. CORE FEATURES & FUNCTIONAL REQUIREMENTS

### 4.1 Autentikasi & Verifikasi Penjual (Seller Onboarding)

- **Registrasi & Login:** Satu pintu login (`login.php`) untuk semua peran. Sistem melakukan _redirect_ otomatis berdasarkan kolom `peran` (`admin` ke dashboard admin, `user` ke halaman utama).
- **Seller Verification Gate:** Saat pengguna dengan `is_verified = 0` mengakses menu "Toko Saya" atau halaman seller, sistem mengarahkan pengguna ke halaman verifikasi (`verifikasi_akun.php`) yang memiliki state gate dan state formulir verifikasi. Data yang disimpan ke database hanya **NIK** (wajib, 16 digit angka unik sebagai syarat kepemilikan KTP/usia) dan **Info Rekening** (wajib, JSON terstruktur `nama_bank`, `nama_pemilik`, `nomor_rekening` pada kolom `TEXT` untuk pencairan dana _Escrow_), disertai persetujuan syarat verifikasi yang wajib dicentang; sedangkan field pendukung di formulir (nama sesuai KTP, tanggal lahir, jenis kelamin, foto KTP, selfie wajah) bersifat demo opsional di sisi _frontend_ dan tidak disimpan ke database. Untuk tahap fokus pengguna biasa saat ini, pengiriman formulir langsung mengubah `is_verified = 1` (sementara sebelum alur approval admin ditambahkan pada fase admin panel).
- **Profil & Alamat Mandiri (Terpisah dari Verifikasi):** Kolom `no_hp` dan `alamat` bersifat opsional (default `NULL`) dan bukan syarat verifikasi seller; pengguna dapat mengisi atau memperbaruinya secara sukarela kapan saja melalui halaman `profile.php` dan `alamat_user.php`.

### 4.2 Manajemen Produk & Transparansi Kondisi

- **Form Listing Terstruktur:** Penjual wajib mengisi nama produk, kategori, foto, harga, stok, kondisi umum, serta form rincian kondisi (Fisik, Fungsi, Lama Pemakaian, Riwayat Servis, Minus/Defect, dan Kelengkapan) yang digabungkan menjadi teks terstruktur pada kolom `deskripsi`.
- **Opsi Transaksi Fleksibel:** Penjual dapat mencentang opsi **`bisa_nego`** dan/atau **`bisa_barter`** secara bebas (bisa pilih salah satu, keduanya, atau tidak dicentang sama sekali untuk harga pas).
- **Status Produk (`status_produk`):** Terdiri dari 4 status yang selaras dengan katalog toko: `tersedia` (*Sedang Tayang*, `stok >= 1`), `draft` (*Draft*, belum tayang di etalase publik), `nonaktif` (*Nonaktif*, disembunyikan sementara oleh penjual), dan `habis` (*Terjual*, `stok = 0`).

### 4.3 Pencarian, Filter & Market Price Reference (Frontend)

- **Search & Filter:** Pencarian berdasarkan kata kunci nama produk, filter kategori, rentang harga, kondisi barang, serta opsi transaksi (Bisa Nego / Bisa Barter).
- **Market Price Reference:** Saat pengguna melakukan pencarian kata kunci komponen/perangkat tertentu, sistem menampilkan _banner/card_ informatif di bagian atas hasil pencarian yang memuat kisaran **Harga Baru vs Harga Bekas** di pasaran (dikelola via kamus data JSON/JS statis di _frontend_).

### 4.4 Smart Bottleneck & Compatibility Checker (Frontend)

- **Lokasi & UI:** Tombol **"⚡ Cek Kecocokan & Bottleneck"** muncul di halaman detail produk (khusus kategori komponen seperti CPU, GPU, RAM) dan membuka **Modal Interaktif**.
- **Mekanisme:** Berjalan 100% di sisi _frontend_ menggunakan Vanilla JavaScript. Nama produk yang sedang dibuka otomatis terdeteksi skor performanya, lalu pengguna memilih komponen pembanding milik mereka dari _dropdown_ untuk melihat persentase potensi _bottleneck_ dan rekomendasi kecocokan.

### 4.5 Tiga Jalur Transaksi & Penawaran Paralel (Multi-Offer)

1. **Buy Now (Beli Langsung):** Pembeli langsung memasukkan barang ke keranjang atau _checkout_ sesuai harga listing.
2. **Negotiation (Tawar Harga):**
   - Hanya tersedia jika produk berstatus `tersedia` (`stok >= 1`) dan memiliki `bisa_nego = 1`.
   - Pembeli mengajukan penawaran harga (dibantu _suggested price_ di UI agar tidak menawar dari nol, dengan batas minimum wajar 50% dari harga listing). Penjual dapat menerima (`diterima`), menolak (`ditolak`), atau membalas tawar (`counter_offer`).
   - **Timer 30 Menit:** Setiap sesi negosiasi memiliki batas waktu 30 menit. Jika terjadi `counter_offer` dari pembeli maupun penjual, timer otomatis di-_reset_ kembali menjadi 30 menit. Jika waktu habis tanpa kesepakatan, status menjadi `expired`.
3. **Barter / Trade (Tukar Tambah):**
   - Hanya tersedia jika produk berstatus `tersedia` (`stok >= 1`) dan memiliki `bisa_barter = 1`.
   - **Syarat Mutlak:** Pengaju barter wajib memiliki minimal 1 produk berstatus `tersedia` (`stok >= 1`) miliknya sendiri yang juga dicentang `bisa_barter = 1`.
   - Saat tombol Barter diklik, muncul modal berisi daftar produk milik pengaju yang memenuhi syarat untuk dipilih sebagai barang tukaran, beserta input manual nominal **Tambah Dana** (jika nilai barang berbeda).
   - Mendukung alur `counter_offer` nominal tambahan dana dan timer 30 menit yang sama dengan fitur negosiasi.
4. **Penawaran Paralel (Multi-Offer) & Aturan Anti-Spam:**
   - Banyak pembeli dapat mengajukan Nego atau Barter secara bersamaan pada produk yang sama tanpa saling mengunci; penjual bebas memilih penawaran terbaik.
   - **Tanpa Kunci Stok Saat Menawar:** Proses tawar-menawar **tidak memotong stok barang** sebelum kesepakatan tercapai. Pembeli lain tetap dapat melakukan **Buy Now** atau mencapai kesepakatan lebih dulu. UI wajib menampilkan catatan transparansi ini kepada pengguna yang mengajukan nego/barter.
   - **Aturan Anti-Spam Re-Offer (setelah `ditolak`):**
     - Maksimal **1 sesi aktif** (`pending` / `counter_offer`) per pembeli per produk pada waktu bersamaan.
     - Maksimal **3x percobaan per hari** per pembeli per produk (status `gagal` karena stok habis/kurang tidak memotong kuota).
     - **Cooldown 30 menit** sejak penawaran terakhir ditolak (dihitung dari `updated_at`).
     - Penawaran ulang **wajib lebih tinggi** dari tawaran terakhir yang ditolak (untuk barter: wajib menaikkan `tambah_dana` atau mengganti barang tukaran).

### 4.6 Checkout, Escrow & Monetisasi (Biaya Layanan)

- **Sistem Escrow (Rekber):** Dana yang dibayarkan pembeli ditahan oleh platform selama proses pengiriman hingga pembeli mengonfirmasi pesanan selesai (`selesai`).
- **Monetisasi & Proteksi Garansi Retur:**
  - Pada transaksi reguler (`beli_langsung` & `hasil_nego`) serta barter dengan tambahan dana, sistem menambahkan **Biaya Layanan / Proteksi Rekber** (`biaya_layanan`) ke dalam total pembayaran sebagai pendapatan platform sekaligus jaminan garansi retur jika barang terbukti cacat melalui video _unboxing_.
  - **Khusus Barter Nilai Sama (`tambah_dana = 0`):** Pembeli diberikan kebebasan melalui _checkbox_ saat _checkout_ untuk memilih apakah ingin menggunakan **Biaya Proteksi Rekber** atau tidak (`biaya_layanan = 0`). Jika tidak dicentang, transaksi langsung diproses tanpa pembayaran dan risiko kerusakan barang ditanggung pribadi oleh kedua pihak.

### 4.7 Pengiriman, Ulasan & Notifikasi

- **Shipping:** Penjual menginput nama kurir dan nomor resi pengiriman (`nomor_resi`) setelah pembayaran terkonfirmasi (`dibayar`).
- **Review & Rating:** Pembeli hanya dapat memberikan rating (1–5) dan ulasan teks setelah status pesanan mencapai `selesai` (1 pesanan = 1 ulasan).
- **Notifikasi & Status Tracking:** Pengguna dapat memantau perubahan status pesanan serta status dan sisa waktu negosiasi/barter melalui halaman riwayat transaksi dan indikator notifikasi.

### 4.8 Admin Panel

- Dashboard monitoring statistik transaksi dan total akumulasi pendapatan platform dari `biaya_layanan`.
- Manajemen CRUD untuk data pengguna, kategori elektronik, dan moderasi produk.
- Pemantauan status pesanan dan konfirmasi status pembayaran/pencairan dana _Escrow_.

---

## 5. CRITICAL BUSINESS RULES (NON-NEGOTIABLE)

1. **BR-01 (Anti Self-Transaction):** Pengguna dilarang keras melakukan _Buy Now_, _Nego_, atau _Barter_ pada produk miliknya sendiri (`id_penjual == $_SESSION['id_user']`).
2. **BR-02 (Stock-Based Reservation & Auto-Habis):** Pengurangan `stok` terjadi secara atomik saat transaksi masuk ke tahap `pesanan` (`menunggu_pembayaran`), baik dari *Buy Now* maupun saat tawaran Nego/Barter disepakati (`diterima`). Jika sisa `stok > 0`, `status_produk` tetap `tersedia` agar pembeli lain tetap dapat membeli sisa unit; jika `stok == 0`, `status_produk` otomatis berubah menjadi `habis`. Untuk transaksi barter yang disepakati, stok **kedua produk** (`id_produk_diincar` dan `id_produk_ditawarkan`) dikurangi secara bersamaan dalam satu transaksi DB. Pembatalan pesanan sebelum dibayar (`dibatalkan`) mengembalikan stok dan mengubah `habis` kembali ke `tersedia`.
3. **BR-03 (Auto-Fail Competing Offers on Insufficient Stock):** Setiap kali stok berkurang (akibat *Buy Now* maupun kesepakatan Nego/Barter lain) atau penjual menonaktifkan produk, seluruh sesi `negosiasi` dan `barter` lain yang masih aktif (`pending`, `counter_offer`) dengan kuantitas melebihi sisa stok (termasuk seluruh sesi aktif saat `stok == 0` atau `status_produk == 'nonaktif'`) harus otomatis berubah statusnya menjadi `gagal`, dengan pesan UI yang membedakan kasus produk habis terjual, sisa stok tidak mencukupi, atau produk dinonaktifkan.
4. **BR-04 (Verified Seller Only):** Akses ke halaman pembuatan listing produk wajib dilindungi pengecekan `is_verified == 1` di sisi _backend_ PHP, bukan hanya menyembunyikan tombol di _frontend_.
5. **BR-05 (Review Integrity):** Form ulasan hanya dapat diakses jika `status_pesanan == 'selesai'` dan pesanan tersebut belum pernah diulas sebelumnya.

# Loot-E Frontend UI Implementation Handoff Guide (Design-to-Code)

> **Persistent Reference for AI Coding Agents**  
> Dokumen ini adalah panduan otoritatif dan *single source of truth* untuk mengimplementasikan screenshot, mockup UI/UX, atau desain Figma ke dalam codebase **Loot-E**.  
> **WAJIB DIBACA SEPENUHNYA** sebelum menulis atau memodifikasi baris kode apa pun.

---

## 1. Project Context

- **Nama Project:** **Loot-E** (*Find Better, Trade Smarter*)
- **Tujuan Project:** Platform *marketplace* C2C (*Customer-to-Customer*) khusus jual-beli dan tukar-tambah (*barter*) komponen komputer (PC hardware) serta perangkat elektronik (baru maupun bekas).
- **Problem yang Diselesaikan:**
  - Ketidakpastian kondisi barang elektronik bekas (diselesaikan melalui transparansi kondisi terstruktur: fisik, fungsi, lama pemakaian, riwayat servis, minus/defect, dan kelengkapan).
  - Keterbatasan metode transaksi di marketplace konvensional (diselesaikan dengan 3 jalur transaksi dalam satu platform: **Beli Langsung / Buy Now**, **Negosiasi Harga**, dan **Barter / Tukar Tambah**).
  - Risiko penipuan transaksi C2C (diselesaikan melalui sistem **Rekening Bersama / Escrow** dan proteksi garansi retur berbasis video *unboxing*).
  - Kesulitan pembeli menilai kewajaran harga dan kecocokan hardware (diselesaikan melalui **Referensi Harga Pasar** dan **Smart Bottleneck & Compatibility Checker** di sisi frontend).
- **Target User:** Perakitan PC (*PC builders*), *gamers*, pekerja kreatif/kantoran, dan penjual/pembeli perangkat elektronik bekas maupun baru di Indonesia.
- **User Roles (Model C2C Single User Role — Lihat `PRD.md` Bagian 3):**
  1. **`user` (Unverified Seller — `is_verified = 0`):** Role default saat registrasi. Bisa browsing, mencari produk, memakai Bottleneck Checker, menambah keranjang, Buy Now, mengajukan Nego/Barter, melacak pesanan, dan memberi ulasan. **Belum bisa upload/jual produk.**
  2. **`user` (Verified Seller, `is_verified = 1`):** Pengguna yang sudah melengkapi `nik` (16 digit) dan `info_rekening` (JSON `nama_bank`, `nama_pemilik`, `nomor_rekening`). Memiliki semua hak pembeli ditambah hak mengunggah/mengelola produk, merespons tawaran nego/barter, dan menginput nomor resi. Sementara `no_hp` dan `alamat` bersifat opsional (default `NULL`) dan diisi mandiri melalui `profile.php` serta `alamat_user.php`.
  3. **`admin` (`peran = 'admin'`):** Mengelola master data pengguna, kategori, moderasi produk, memonitor pesanan, verifikasi pembayaran/pencairan Escrow, dan laporan pendapatan dari `biaya_layanan`.
- **Dokumen Referensi Terkait (Jangan Diduplikasi, Baca Langsung Saat Dibutuhkan):**
  - Spesifikasi Produk & Business Rules (`BR-01` s.d. `BR-05`): [`PRD.md`](../PRD.md)
  - Skema Database & Data Seeder (11 Tabel): [`db/db_loote.sql`](../db/db_loote.sql)
  - Kontrak Format JSON Kolom `produk.deskripsi`: [`docs/adr/0001-structured-product-description.md`](adr/0001-structured-product-description.md)
  - Keputusan Status Produk & Penawaran Paralel: [`docs/adr/0002-product-status-and-parallel-offers.md`](adr/0002-product-status-and-parallel-offers.md)
  - Istilah Domain: [`docs/glossary.md`](glossary.md)
  - Catatan Teknis Persiapan Fitur Seller & Upload Produk: [`docs/loote-upload-produk-handoff.md`](loote-upload-produk-handoff.md)
  - Riwayat Audit Tahap 1: [`docs/loote-audit-handoff.md`](loote-audit-handoff.md)

> **PERINGATAN KONTEKS:** Konteks fungsional dan bisnis di atas bertujuan agar agent memahami **alasan dan peran** setiap halaman. Konteks ini **BUKAN izin** untuk mengimprovisasi, menambah, atau mengubah desain UI yang diberikan user.

---

## 2. Technology Stack

Agent masa depan **wajib mempertahankan stack aktual** berikut tanpa melakukan migrasi atau menambah build step:

- **Backend Language:** PHP Native (prosedural/modular, menggunakan strict types di helper, kompatibel PHP 8.x di lingkungan Laragon).
- **Database & Driver:** MySQL (`utf8mb4` / `utf8mb4_0900_ai_ci`) diakses menggunakan **PDO** (`PDO::ERRMODE_EXCEPTION`, `PDO::FETCH_ASSOC`, `PDO::ATTR_EMULATE_PREPARES => false`).
- **Markup:** HTML5 semantik (`lang="id"`).
- **CSS Framework:** **Tailwind CSS v3 via Play CDN** (`<script src="https://cdn.tailwindcss.com"></script>`, atau `<script src="https://cdn.tailwindcss.com/3.4.17"></script>` pada `landingpage.php`) dengan konfigurasi inline `tailwind.config` di setiap halaman. Tidak ada proses build Node.js/PostCSS/npm.
- **Typography CDN:** Google Fonts — **Plus Jakarta Sans** (`wght@400;500;600;700;800`).
- **Icon Library:** **Phosphor Icons Web** via CDN (`<script src="https://unpkg.com/@phosphor-icons/web"></script>`).
- **Frontend Interactivity:** **Vanilla JavaScript** (inline `<script>` di bagian bawah halaman atau komponen; menggunakan API standar DOM seperti `<dialog>.showModal()`, `classList.toggle`, `dataset`, `fetch` jika diperlukan nanti).
- **Development Environment:** Laragon (Windows), path workspace `c:\laragon\www\loot-e`, database `db_loote` (`localhost`, user `root`, password kosong `''`).
- **External Dependencies:** **Nol** (`composer.json` dan `package.json` tidak digunakan).

---

## 3. Design System

Loot-E menggunakan gaya visual **Modern Industrial-Tech Marketplace**: kontras tinggi antara warna kuning terang khas brand (`#FFC914`) dan hitam arang (`#272727`) di atas latar abu-abu terang bersih (`#F4F5F7` / `#F8F9FA`) dengan permukaan kartu putih (`#FFFFFF`) dan sudut tajam atau sedikit membulat tergantung konteks komponen.

### Inkonsistensi Minor yang Ditemukan di Codebase (Jangan Diubah Sepihak di Halaman Existing)
- **Background Body:**
  - `index.php`, `categories.php`, `detail_produk.php` menggunakan `bg-[#F4F5F7]`.
  - `search.php`, `login.php`, `register.php` menggunakan `bg-[#F8F9FA]`.
  - `help.php` menggunakan `bg-[#f5f5f6]` dengan teks utama `text-[#363638]`.
  - `landingpage.php` menggunakan CSS variable `--lt: #ffffff` dan `--card: #F4F4F4`.
- **Token Warna Tailwind Config:**
  - Sebagian besar halaman mendefinisikan alias pendek `ye: '#FFC914'` dan `ch: '#272727'` sekaligus namespace `brand: { yellow: '#FFC914', dark: '#272727', footer: '#272727', bg: '#F4F5F7' }` (pada `login.php` & `register.php` ditambah `brand.accent: '#16A34A'`).
  - **Aturan bagi Agent:** Selalu ikuti bentuk sudut (tajam vs membulat) persis seperti yang terlihat pada screenshot desain yang diberikan user!

---

## 4. Color Palette

Berikut adalah daftar nilai warna aktual yang digunakan di seluruh project:

### Brand & Core Colors
| Token / Class | HEX Value | Penggunaan Aktual |
| :--- | :--- | :--- |
| `brand-yellow` / `ye` | `#FFC914` | Warna aksen utama, tombol CTA primer, badge sorotan, avatar badge, focus outline, huruf "E" pada logo `Loot-E`. |
| `brand-dark` / `ch` | `#272727` | Background navbar (`components/navbar.php`), footer (`components/footer.php`), tombol aksi sekunder gelap ("Beli Sekarang", "Terapkan"), kartu spotlight gelap, teks utama di atas background kuning. |
| Page Background 1 | `#F4F5F7` | Background `<body>` pada `index.php`, `categories.php`, `detail_produk.php`. |
| Page Background 2 | `#F8F9FA` | Background `<body>` pada `search.php`, `login.php`, `register.php`. |
| Page Background 3 | `#F5F5F6` | Background `<body>` pada `help.php`. |
| Surface / Card | `#FFFFFF` (`bg-white`) | Permukaan kartu produk, kontainer section, sidebar filter, form login/register, modal dialog. |
| Surface Secondary / Thumbnail | `#FAFAFA` / `#FBFBFB` / `#F4F4F4` | Background kotak gambar produk (`aspect-square`), kotak ikon kategori (`bg-[#F4F4F4]`), dan kartu fitur landing page. |
| Warm Surface Highlight | `#FFFDF5` | Background kotak "Harga Pasar" di `detail_produk.php` dan hover state kategori bantuan di `help.php`. |

### Text Colors
| Class / Nilai | HEX Value | Penggunaan Aktual |
| :--- | :--- | :--- |
| `text-gray-900` / `text-[#272727]` | `#111827` / `#272727` | Heading utama, judul produk, nominal harga, label tebal. |
| `text-gray-800` | `#1F2937` | Warna teks default pada `<body>`, nama produk di kartu. |
| `text-gray-700` / `text-gray-600` | `#374151` / `#4B5563` | Label form, isi paragraf deskripsi, item menu dropdown, teks tabel spesifikasi. |
| `text-gray-500` / `text-gray-400` | `#6B7280` / `#9CA3AF` | Subtitle section, breadcrumb non-aktif, placeholder input, meta info, teks "Belum ada ulasan". |

### Border Colors
| Class / Nilai | HEX Value | Penggunaan Aktual |
| :--- | :--- | :--- |
| `border-gray-100` | `#F3F4F6` | Pemisah internal kartu (`border-t`), border kotak thumbnail produk, kontainer kategori beranda. |
| `border-gray-200` / `border-gray-200/80` / `border-gray-200/70` | `#E5E7EB` | Border standar kartu produk, panel detail produk, tabel spesifikasi. |
| `border-gray-300` | `#D1D5DB` | Border input form (`input`, `textarea`, `select`), tombol sekunder outline. |
| `border-gray-700` | `#374151` | Pemisah pada navbar mobile, footer, dan kartu spotlight gelap. |

### Semantic & Badge Colors
| Konteks | Background | Text | Border (Opsional) | Lokasi Referensi |
| :--- | :--- | :--- | :--- | :--- |
| **Kondisi / Nego / Barter Badge (Warm Amber)** | `bg-[#FEF3C7]` atau `bg-[#FFF8E1]` | `text-[#92400E]` atau `text-[#B88A00]` | - | `detail_produk.php`, `search.php` |
| **Success / Verified / Kondisi Baru** | `bg-emerald-50` atau `bg-emerald-100` | `text-emerald-700` atau `text-emerald-800` | `border-emerald-300` | `detail_produk.php`, `search.php` |
| **Warning / Info Notice / Own Product Guard** | `bg-amber-50` | `text-amber-800` / `text-amber-900` | `border-amber-200` | `login.php`, `detail_produk.php` |
| **Error / Danger / Above Market Price** | `bg-red-50` / `bg-rose-100` | `text-red-700` / `text-rose-800` | `border-red-200` / `border-rose-300` | `login.php`, `register.php`, `detail_produk.php` |
| **Star Rating Icon** | - | `text-amber-400` / `text-amber-500` | - | `index.php`, `search.php`, `detail_produk.php` |

### Interactive States
- **Focus State:**
  - Global CSS `:focus-visible`: `outline: 2px solid #FFC914; outline-offset: 2px;` (di `help.php`: `3px solid #f5b700`).
  - Input Form Tailwind: `focus:outline-none focus:border-brand-yellow focus:ring-2 focus:ring-brand-yellow/30` (atau `/40`, `/50`).
- **Hover State:**
  - Tombol Kuning (`bg-brand-yellow`): `hover:brightness-95`, `hover:bg-yellow-400`, atau `hover:bg-yellow-500`.
  - Tombol Gelap (`bg-[#272727]`): `hover:bg-black`, `hover:bg-neutral-800`, atau `hover:bg-gray-700`.
  - Kartu Produk: `hover:shadow-md hover:border-gray-300 transition` dan gambar di dalamnya `group-hover:scale-105 transition`.
  - Kartu Kategori (`categories.php`): `hover:border-brand-yellow hover:shadow-[0_8px_30px_rgba(245,183,0,0.12)] hover:-translate-y-1`.
- **Active State:** `active:scale-[0.99]`, `active:scale-95`, atau `active:translate-y-px`.

---

## 5. Typography

- **Font Family:** `"Plus Jakarta Sans", system-ui, sans-serif` (dimuat dari Google Fonts dengan weight `400`, `500`, `600`, `700`, `800`).
- **Tailwind Font Classes:** `font-sans` (default di `<body>`) dan `font-hd` (alias ke Plus Jakarta Sans untuk heading hero/section).
- **Skala Tipografi Aktual:**
  - **Hero Title (`h1`):** `text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight leading-[1.1]` (`index.php`, `categories.php`) atau hingga `md:text-6xl` (`landingpage.php`).
  - **Auth / Search Page Heading (`h1`):** `text-2xl sm:text-3xl md:text-4xl font-extrabold tracking-tight`.
  - **Product Detail Title (`h1`):** `text-xl sm:text-2xl font-bold text-gray-900 leading-tight`.
  - **Harga Utama (Detail Produk):** `text-2xl sm:text-3xl font-extrabold text-gray-900`.
  - **Section Heading (`h2`):** `text-base sm:text-lg font-bold text-gray-900` (`index.php`, `detail_produk.php`) atau `text-2xl md:text-[1.7rem] font-extrabold` (`categories.php`).
  - **Card Title (`h3` - Nama Produk):**
    - Grid 5 kolom (`index.php`): `text-xs font-semibold text-gray-800 line-clamp-2 leading-snug min-h-[32px]`.
    - Grid 3 kolom (`search.php`): `text-sm font-bold text-[#272727] leading-snug line-clamp-2 min-h-[38px]`.
  - **Harga di Kartu Produk:** `font-bold text-sm text-gray-900` (`index.php`) atau `text-base font-extrabold text-[#272727]` (`search.php`, `detail_produk.php` related).
  - **Body & Deskripsi:** `text-xs sm:text-sm text-gray-600 leading-relaxed`.
  - **Form Labels:** `text-sm font-semibold text-gray-700 mb-1.5` (`login.php`, `register.php`) atau `text-xs font-semibold text-gray-700 mb-1` (`detail_produk.php` modal) atau `text-xs font-bold uppercase tracking-wider text-gray-700` (`help.php` modal).
  - **Buttons:** Umumnya `text-xs font-bold` atau `text-sm font-bold` (pada `detail_produk.php` menggunakan `text-xs font-bold uppercase tracking-wide`).
  - **Micro Labels / Badges / Meta:** `text-[10px]` atau `text-[11px]` (`font-medium`, `font-semibold`, atau `font-bold`).

---

## 6. Layout and Spacing

- **Page Shell (Sticky Footer Layout):**
  ```html
  <body class="bg-[#F4F5F7] font-sans text-gray-800 antialiased min-h-screen flex flex-col">
      <?php require_once 'components/navbar.php'; ?>
      <main class="flex-1 ...">
          <!-- Konten Halaman -->
      </main>
      <?php require_once 'components/footer.php'; ?>
  </body>
  ```
- **Container & Max Width:**
  - Standar halaman aplikasi (`index.php`, `categories.php`, `search.php`, `components/footer.php`): `max-w-7xl mx-auto px-6 md:px-12` (pada `detail_produk.php`: `max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6`).
  - Halaman `help.php`: `max-w-[1288px] mx-auto px-5 sm:px-8 lg:px-[76px]`.
  - Halaman `login.php` & `register.php`: Full-viewport split layout (`min-h-screen flex flex-col lg:flex-row`), kolom kiri `w-full lg:w-1/2` (form `max-w-md mx-auto`), kolom kanan `hidden lg:flex lg:w-1/2 bg-brand-dark`.
- **Grid Patterns Aktual:**
  - **8 Kategori Beranda (`index.php`):** `grid grid-cols-4 sm:grid-cols-8 gap-3 sm:gap-4 text-center` (overlap ke hero dengan `-mt-6 sm:-mt-8 relative z-20`).
  - **Katalog Produk 5 Kolom (`index.php`, `landingpage.php`):** `grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3.5`.
  - **Kategori Explore (`categories.php`):** `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5`.
  - **Layout Search + Filter Sidebar (`search.php`):** `flex flex-col md:flex-row items-start gap-8` dengan `<aside class="hidden md:block w-full md:w-60 lg:w-64 shrink-0">` dan `<section class="flex-1 w-full">` berisi `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5`.
  - **Layout Detail Produk (`detail_produk.php`):**
    - Baris Atas: `grid grid-cols-1 md:grid-cols-12 gap-5 items-start` -> Kolom Foto (`lg:col-span-5`), Kolom Info & Aksi (`lg:col-span-4`), Kolom Penjual (`lg:col-span-3`).
    - Baris Tab & Ulasan: `grid grid-cols-1 lg:grid-cols-12 gap-8 items-start` -> Area Konten Tab (`lg:col-span-7`), Area Ringkasan Rating (`lg:col-span-5`).
- **Breakpoints:** Mengikuti breakpoint default Tailwind CSS (`sm: 640px`, `md: 768px`, `lg: 1024px`, `xl: 1280px`).

---

## 7. Icon System

- **Library Resmi:** **Phosphor Icons Web** (`https://unpkg.com/@phosphor-icons/web`).
- **Cara Implementasi:**
  - **Regular Outline Icon:** `<i class="ph ph-<icon-name>"></i>` (contoh: `<i class="ph ph-shopping-cart"></i>`, `<i class="ph ph-magnifying-glass"></i>`, `<i class="ph ph-bell"></i>`).
  - **Filled Icon:** `<i class="ph-fill ph-<icon-name>"></i>` (contoh: `<i class="ph-fill ph-star text-amber-400"></i>`, `<i class="ph-fill ph-shield-check"></i>`).
- **Dynamic Category Icons:**
  - **Wajib** memanggil fungsi `kategoriIcon($namaKategori)` dari `config/helpers.php`:
    ```php
    <i class="ph <?= htmlspecialchars(kategoriIcon($cat['nama_kategori'])) ?> text-2xl"></i>
    ```
  - Daftar mapping di `config/helpers.php`:
    - `gpu` -> `ph-graphics-card`
    - `cpu` -> `ph-cpu`
    - `ram` -> `ph-memory`
    - `motherboard` -> `ph-circuit-board`
    - `storage` -> `ph-hard-drives`
    - `power supply` -> `ph-lightning`
    - `pc case` -> `ph-desktop-tower`
    - `cpu cooler` -> `ph-fan`
    - `laptop` -> `ph-laptop`
    - `phone` / `smartphone` -> `ph-device-mobile`
    - `monitor` -> `ph-monitor`
    - `headphones` / `headset` -> `ph-headphones`
    - `keyboard` -> `ph-keyboard`
    - `mouse` -> `ph-mouse`
    - `tablet` -> `ph-device-tablet`
    - `aksesoris` -> `ph-bag-simple`
    - Default fallback -> `ph-tag`
- **Aturan Ketat Ikon:**
  - **DILARANG** mengganti Phosphor Icons dengan FontAwesome, Heroicons, Lucide, atau Material Icons.
  - **DILARANG** menggunakan emoji sebagai pengganti ikon UI (kecuali jika desain secara eksplisit menampilkannya).
  - *Catatan:* `landingpage.php` memiliki helper SVG inline lokal `data-i="..."` warisan implementasi awal, namun untuk semua halaman aplikasi baru **gunakan class Phosphor Icons (`ph ph-...`)**.

---

## 8. Assets

 Seluruh asset statis berada di folder `assets/`. Prioritaskan penggunaan asset berikut sebelum membuat elemen baru:

| Path File | Dimensi / Tipe | Penggunaan di Project |
| :--- | :--- | :--- |
| `assets/logo.png` | PNG (Circular Logo) | Logo utama Loot-E di `components/navbar.php`, `components/navbar_landing.php`, `components/footer.php`, `login.php`, dan `register.php`. |
| `assets/mainimage.png` | PNG (Hero Banner) | Gambar latar hero "Upgrade Your Setup" di `index.php`. |
| `assets/categoriesimage.png` | PNG (Hero Banner) | Gambar latar hero "Explore Categories" di `categories.php`. |
| `assets/landingimage.png` | PNG (Hero Banner) | Gambar latar hero utama di `landingpage.php`. |
| `assets/keyboard.png` | PNG (Product Cutout) | Ilustrasi kartu "HOT SPOTLIGHT: Mechanical Keyboards & Studio Audio Gear" di `index.php`. |
| `assets/image.png` | PNG (Illustration) | Ilustrasi preview di panel gelap sebelah kanan pada `login.php` dan `register.php`. |
| `assets/help.png` | PNG (`56x56`) | Ikon Floating Action Button (FAB) Pusat Bantuan di pojok kanan bawah (`components/footer.php`). |

### Pola Fallback Gambar Produk (`produk.foto`)
Tabel `produk` memiliki kolom `foto` (`VARCHAR(255) DEFAULT NULL`). Saat merender gambar produk di kartu maupun galeri detail:
- Selalu cek `if (!empty($item['foto']))`: tampilkan `<img src="<?= htmlspecialchars($item['foto']) ?>" alt="..." class="w-full h-full object-contain" />`.
- Jika `empty($item['foto'])`: tampilkan **fallback ikon kategori** menggunakan `kategoriIcon($item['nama_kategori'])` beserta teks nama kategori (lihat implementasi di `index.php` baris 221–228 atau `detail_produk.php` baris 267–275).

---

## 9. Existing UI Patterns

Sebelum membangun komponen baru, periksa dan gunakan kembali pattern yang sudah ada di codebase:

1. **Navbar Utama (`components/navbar.php`):**
   - Sticky top (`bg-brand-dark text-white sticky top-0 z-50 shadow-md`), layout 3 kolom di desktop (`lg:grid lg:grid-cols-[1fr_minmax(0,36rem)_1fr]`).
   - Kiri: Logo `assets/logo.png` + teks `Loot-<span class="text-brand-yellow">E</span>`.
   - Tengah: Search input pill (`rounded-full`) dengan tombol submit lingkaran kuning (`w-7 h-7 rounded-full bg-brand-yellow`) berisi `<i class="ph ph-arrow-right"></i>`. Di layar mobile (`md:hidden`), search bar berada di baris kedua.
   - Kanan: Tombol ikon Notifikasi (`ph-bell`), Keranjang (`ph-shopping-cart`), serta Dropdown Profil User (avatar inisial 2 huruf `bg-brand-yellow text-brand-dark rounded-full`, teks `"Halo, [Nama]!"`, menu dropdown berisi Lihat Profil, Toko Saya, Settings, dan Keluar `auth.php?action=logout`).
2. **Navbar Guest (`components/navbar_landing.php`):**
   - Dipakai saat user belum login (`landingpage.php`, `help.php` untuk guest). Memiliki menu link (`Home`, `Products`, `About`, `Help`), tombol `Login` & `Register`, serta hamburger menu mobile (`#navbar-mobile-toggle`).
3. **Footer & Floating Help FAB (`components/footer.php`):**
   - Footer gelap (`bg-[#272727] text-gray-300 py-12 px-6 md:px-12`) dengan logo, slogan *"Find Better, Trade Smarter."*, navigasi kontekstual, ikon sosial media (`ph-fill ph-youtube-logo`, dll.), serta **Floating Action Button (FAB)** `#loote-help-fab` (`fixed bottom-6 right-6 z-50`) yang mengarah ke `help.php` (atau membuka `#open-support-dialog` jika sudah berada di `help.php`).
4. **Breadcrumb:**
   - Pola di `detail_produk.php` (baris 246–254) dan `search.php` (baris 180–184): teks `text-xs text-gray-500`, pemisah `/`, item aktif `font-semibold text-gray-900`.
5. **Product Card:**
   - **Varian Ringkas 5-Kolom (`index.php` baris 218–252):** Kartu putih `bg-white border border-gray-200/70 p-3`, gambar `aspect-square bg-[#FBFBFB] border border-gray-100`, judul `text-xs font-semibold line-clamp-2 min-h-[32px]`, harga `font-bold text-sm`, kondisi `text-[10px] text-gray-400`, footer kartu berisi bintang rating + label `Nego · Barter`.
   - **Varian Lengkap 3-Kolom (`search.php` baris 302–409):** Kartu putih `bg-white border border-gray-200/80 p-4`, badge kondisi di atas (`Baru` emerald / `Bekas` kuning hangat), gambar `h-40`, teks kondisi, judul `text-sm font-bold`, nama kategori `text-xs font-bold text-brand-yellow`, harga `text-base font-extrabold`, indikator ikon `Bisa Nego` / `Bisa Barter`, rating, info penjual + badge `Trusted Seller`, serta tombol aksi `Ajukan Harga` & `Ajukan Barter` (disembunyikan jika produk milik sendiri / `BR-01`).
6. **Tabs Navigasi (`detail_produk.php` baris 500–540 & 917–939):**
   - Menggunakan tombol `.tab-link` dengan atribut `data-target="panel-..."`, `role="tab"`, dan `aria-selected`.
   - Tab aktif: `border-b-2 border-brand-yellow text-gray-900 font-bold`. Tab non-aktif: `border-b-2 border-transparent text-gray-500 hover:text-gray-900 font-semibold`.
   - Panel konten menggunakan class `.tab-panel` dan disembunyikan dengan class `hidden`.
7. **Modal Dialog (`<dialog>` HTML5 Native):**
   - Digunakan di `detail_produk.php` (`#modal-nego`, `#modal-barter` baris 830–897) dan `help.php` (`#support-dialog` baris 515–551).
   - Dibuka menggunakan `.showModal()` dan ditutup dengan `.close()`.
   - Styling backdrop: `backdrop:bg-black/40` atau CSS `dialog::backdrop { background: rgba(38, 38, 40, 0.64); }`.
8. **Toast Notification:**
   - Digunakan di `detail_produk.php` (`#toast-msg` baris 900–905: `fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg`) dan `help.php` (`#toast` baris 512: `fixed bottom-5 left-1/2 -translate-x-1/2 bg-brand-dark text-white rounded-xl`).
9. **Alerts & Guard Boxes:**
   - Alert Info/Warning: `bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4` (atau `rounded-lg p-3.5` di `detail_produk.php`).
   - Alert Error: `bg-red-50 border border-red-200 text-red-700 rounded-xl p-4`.
10. **Pagination (`search.php` baris 414–443):**
    - Tombol prev/next `w-8 h-8 rounded-md border border-gray-300`, nomor halaman aktif `w-8 h-8 rounded-full bg-[#272727] text-white text-xs font-bold`, halaman lain `w-8 h-8 rounded-full text-gray-700 hover:bg-gray-100`.
11. **Empty States:**
    - Kotak putih `bg-white border border-gray-200/80` dengan ikon lingkaran atau ikon besar di tengah, heading tebal, subteks penjelasan, dan tombol CTA reset/navigasi (lihat `search.php` baris 447–470 dan `help.php` baris 469–478).

---

## 10. Relevant Existing Page References

Gunakan file berikut sebagai referensi langsung ketika mengimplementasikan halaman baru:

| File Path | Nama Halaman | Bagian UI yang Dapat Dijadikan Referensi |
| :--- | :--- | :--- |
| [`components/navbar.php`](../components/navbar.php) | Navbar Aplikasi | Header navigasi sticky, search bar pill, tombol ikon notifikasi/keranjang, avatar inisial & menu dropdown profil. |
| [`components/navbar_landing.php`](../components/navbar_landing.php) | Navbar Guest | Header navigasi publik beserta menu dropdown mobile. |
| [`components/footer.php`](../components/footer.php) | Footer & Help FAB | Footer standar 3 kolom + Floating Action Button bantuan. |
| [`index.php`](../index.php) | Beranda Utama | Hero banner dengan gambar & overlay gradient kuning, grid 8 kategori, kartu spotlight gelap/terang, grid produk 5 kolom. |
| [`categories.php`](../categories.php) | Explore Categories | Hero pencarian kategori, section heading dengan garis aksen kuning (`w-10 h-1 bg-brand-yellow`), kartu kategori interaktif, banner CTA gelap di bawah. |
| [`search.php`](../search.php) | Pencarian & Filter | Layout 2 kolom (sidebar filter radio/checkbox/input range harga + grid kartu produk 3 kolom), toggle filter mobile, pagination, empty state pencarian. |
| [`detail_produk.php`](../detail_produk.php) | Detail Produk | Layout 12 kolom (Galeri Foto, Info & Harga + Grid Tombol Aksi + Kartu Info Penjual), sistem 4 Tab (`Deskripsi`, `Spesifikasi`, `Riwayat & Kondisi`, `Ulasan`), distribusi progress bar rating, native `<dialog>` modal form, dan toast notification. |
| [`help.php`](../help.php) | Pusat Bantuan | Filter pill kategori, akordion FAQ (`aria-expanded`), live search filter JS, kartu Contact Support, dan modal `<dialog>` form bantuan. |
| [`login.php`](../login.php) & [`register.php`](../register.php) | Autentikasi | Split-screen layout (`lg:w-1/2`), form input dengan ikon kiri + tombol toggle mata password (`toggleVis`), alert box info/error. |

---

## 11. Coding Conventions

1. **Struktur File Halaman PHP:**
   - Bagian atas file (sebelum `<!DOCTYPE html>`) berisi logika inisialisasi: `require_once "auth.php";`, pengecekan login/redirect, inisialisasi `$currentUser = getCurrentUser();`, dan variabel `$navActivePage`.
   - Seluruh output variabel dinamis ke HTML **wajib** di-escape dengan `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (atau `htmlspecialchars((string)$val)`).
   - Format mata uang Rupiah selalu menggunakan helper `formatRupiah((int)$angka)`.
2. **Penamaan File & Bahasa:**
   - Nama file halaman menggunakan **snake_case** berbahasa Indonesia atau Inggris sesuai fungsi yang sudah berjalan (`detail_produk.php`, `landingpage.php`, `categories.php`, `search.php`). Untuk halaman akun/seller/transaksi baru, ikuti konvensi snake_case Bahasa Indonesia seperti yang sudah diterapkan (`verifikasi_akun.php`, `alamat_user.php`, `dashboard_seller.php`, `katalog.php`, `upload_produk.php`), kecuali diinstruksikan lain oleh user.
   - Seluruh teks antarmuka pengguna (UI copy), label, pesan error/validasi, dan komentar kode menggunakan **Bahasa Indonesia** (dengan beberapa istilah domain tetap dalam Bahasa Inggris sesuai desain seperti *Upgrade Your Setup*, *Explore Categories*, *New Arrivals*, *Hot Spotlight*, *Verified Seller*).
3. **Styling & CSS:**
   - Gunakan utility classes Tailwind CSS langsung pada elemen HTML.
   - Hanya gunakan blok `<style>` di dalam `<head>` untuk:
     - CSS variables (`--brand-yellow`, `--brand-dark`)
     - Focus outline aksesibilitas (`:focus-visible`)
     - Styling pseudo-element khusus yang tidak terjangkau utility CDN (seperti `::-webkit-slider-thumb` atau `dialog::backdrop`)
     - `@media (prefers-reduced-motion: reduce)`
4. **JavaScript & Aksesibilitas:**
   - Bungkus script halaman di dalam IIFE `(function() { ... })();` atau `document.addEventListener('DOMContentLoaded', function() { ... });` di bagian bawah sebelum `</body>`.
   - Gunakan atribut aksesibilitas standar (`aria-label`, `aria-expanded`, `aria-selected`, `aria-controls`, `role="tab"`, `role="tabpanel"`, `role="status"`).

---

## 12. Design-to-Code Rules (STRICT)

Setiap kali user memberikan screenshot, gambar desain Figma, atau UI/UX mockup, perlakukan gambar tersebut sebagai **Primary Visual Source of Truth**.

### A. Design Accuracy (Kepatuhan Visual Mutlak)
Agent wajib mereproduksi desain seakurat mungkin (*faithful implementation*), mencakup:
- **Layout, Grid & Proportions:** Jumlah kolom, rasio lebar kolom, posisi sidebar/panel, dan max-width kontainer.
- **Alignment & Positioning:** Perataan teks (kiri/tengah/kanan), posisi badge absolut/relatif, perataan vertikal ikon dan teks.
- **Spacing & Whitespace:** Padding internal kartu, jarak antar elemen (`gap`, `space-y`), dan margin antar section.
- **Typography:** Ukuran font, ketebalan (`font-medium`, `font-semibold`, `font-bold`, `font-extrabold`), warna teks, *line-clamp*, dan *letter-spacing*.
- **Colors, Borders, Radius & Shadows:** Warna latar, warna tombol, ketebalan & warna border, sudut tajam vs membulat (`rounded-none` vs `rounded-lg`/`rounded-xl`/`rounded-full`), serta level bayangan (`shadow-2xs`, `shadow-sm`, `shadow-md`, `shadow-xl`).
- **Components, Icons & Assets:** Pemilihan ikon Phosphor yang paling tepat mewakili ikon pada desain, serta penggunaan gambar/logo dari `assets/`.

### B. No Unrequested Design Changes (Dilarang Improvisasi)
Agent **DILARANG KERAS**:
- Melakukan redesign atau "mempercantik" tampilan berdasarkan selera sendiri.
- Mengubah struktur layout atau memindahkan urutan section/komponen.
- Mengganti warna, font family, atau ukuran tipografi di luar yang tampak pada desain.
- Menambahkan dekorasi, *glow*, *gradient*, *shadow*, atau animasi yang tidak ada di desain.
- Menambahkan komponen, tombol, filter, badge, atau field form yang tidak ada pada screenshot.
- Menghilangkan elemen UI yang jelas-jelas ada di dalam screenshot.
- Mengganti icon library atau menggunakan emoji untuk menggantikan ikon UI.
- Membuat design system baru yang bertabrakan dengan fondasi Loot-E.

> **PRINSIP UTAMA:** *Implementasikan desain yang diberikan apa adanya — jangan mendesain ulang!*

### C. Existing Design System Consistency
1. Jika elemen pada screenshot cocok dengan komponen/pattern yang sudah ada di project (misal: navbar, footer, kartu produk, breadcrumb, modal, tombol kuning/hitam), **gunakan pattern kode dan token Tailwind existing** (`bg-brand-yellow`, `bg-brand-dark`, `font-sans`, Phosphor Icons).
2. Jika screenshot menampilkan halaman atau komponen dengan gaya visual baru yang spesifik untuk halaman tersebut, **ikuti screenshot sebagai acuan utama untuk halaman yang sedang dikerjakan**, tetapi **jangan mengubah komponen global (`navbar.php`, `footer.php`) atau halaman lain secara sepihak**.
3. Jika terdapat konflik besar antara screenshot dan komponen global existing, catat konflik tersebut dan tanyakan kepada user sebelum mengubah file global.

---

## 13. Page Context Rules

Saat memberikan screenshot desain, user akan menyertakan konteks halaman (nama halaman, tujuan halaman, fitur terkait, kebutuhan data, atau catatan tambahan).

1. **Pahami Tujuan Halaman:** Gunakan informasi konteks dari user dan `PRD.md` untuk memahami peran halaman tersebut di dalam alur aplikasi Loot-E (misal: apakah halaman untuk *Unverified Seller*, *Verified Seller*, *Pembeli*, atau *Admin*).
2. **Konteks Bukan Izin Redesign:**
   > **Page context is for understanding the implementation, not for redesigning the UI.**
   Jangan pernah menambahkan tombol, form input, atau panel tambahan hanya karena Anda membaca di `PRD.md` bahwa suatu fitur membutuhkannya, padahal elemen tersebut **tidak ada** di dalam screenshot desain yang diberikan user.
3. **Penanganan Missing Requirements:**
   - Jika ada kebutuhan fungsional/bisnis penting yang membutuhkan elemen UI tetapi tidak ditemukan di dalam screenshot desain:
     1. **Jangan** menambahkannya secara otomatis ke dalam UI.
     2. Implementasikan UI sesuai screenshot terlebih dahulu.
     3. Catat kekurangan tersebut sebagai **"Missing Requirement / Needs Confirmation"** pada laporan review untuk ditanyakan kepada user.

---

## 14. UI-First Workflow (Mandatory 4-Phase Process)

Setiap tugas implementasi desain UI ke kode **wajib** mengikuti 4 fase berurutan di bawah ini.

### PHASE 1 — DESIGN ANALYSIS
Sebelum menulis kode apa pun:
1. Baca dokumen handoff ini (`docs/loote-frontend-ui-handoff.md`).
2. Pahami konteks halaman dan instruksi yang diberikan user.
3. Amati screenshot/desain Figma secara mendetail (struktur layout, komponen, tipografi, warna, border radius, spacing, ikon, dan state).
4. Periksa halaman dan komponen existing yang relevan (`components/navbar.php`, `components/footer.php`, `config/helpers.php`, serta halaman referensi di Bagian 10).
5. Identifikasi komponen, class Tailwind, dan asset di `assets/` yang dapat digunakan kembali.
6. Tentukan file mana yang perlu dibuat atau dimodifikasi.
7. Identifikasi potensi ambiguitas atau *missing requirement*.

### PHASE 2 — UI IMPLEMENTATION ONLY (FRONTEND ONLY)
Pada tahap implementasi awal, agent **HANYA mengerjakan tampilan Frontend UI**:
- Gunakan tech stack existing (PHP Native untuk kerangka template/`require_once` komponen navbar & footer, HTML5, Tailwind CSS CDN, Phosphor Icons, dan Vanilla JS untuk interaksi UI murni seperti tab, accordion, modal toggle, atau preview).
- Gunakan **data statis / mock array PHP** di bagian atas file untuk merender daftar produk, tabel, atau profil secara realistis sesuai isi screenshot.
- **DILARANG mengimplementasikan pada Phase 2:**
  - Query SQL baru ke database (`SELECT`, `INSERT`, `UPDATE`, `DELETE`).
  - Pemrosesan form backend (`$_POST` processing ke database, file upload `move_uploaded_file`).
  - Logika bisnis backend, mutasi session autentikasi, atau integrasi transaksi nyata.
  - Perubahan pada skema database (`db/db_loote.sql`) atau file backend existing.

---

## 15. Visual Validation (PHASE 3)

Setelah kode UI selesai ditulis, jangan langsung menganggap pekerjaan selesai hanya karena file berhasil dibuat:
1. Jalankan pemeriksaan sintaks PHP (`php -l <file.php>`) untuk memastikan tidak ada parse error.
2. Render dan buka halaman jika memungkinkan di environment lokal.
3. Bandingkan hasil render secara berdampingan dengan screenshot desain asli dari user.
4. Periksa kembali satu per satu:
   - Apakah proporsi layout, lebar kontainer, dan jumlah kolom grid sudah sama?
   - Apakah warna background, permukaan kartu, teks, badge, dan tombol sudah akurat?
   - Apakah border radius (sudut tajam vs membulat), border color, dan shadow sudah sesuai?
   - Apakah ukuran font, ketebalan, dan hirarki visual sudah tepat?
   - Apakah ikon Phosphor dan gambar asset sudah tampil dengan benar tanpa broken image?
   - Apakah tampilan responsif (mobile & desktop) tetap rapi dan tidak rusak?
   - Apakah ada elemen tambahan yang tidak sengaja dibuat padahal tidak ada di desain?
5. Perbaiki setiap selisih visual yang ditemukan sebelum menyerahkan hasil ke user.

---

## 16. Approval Gate (PHASE 4 — STOP & WAIT)

Setelah Visual Validation selesai, berikan laporan kepada user yang memuat:
1. **Daftar File:** File yang dibuat atau dimodifikasi (beserta link klikabel).
2. **Ringkasan Implementasi UI:** Penjelasan singkat bagian UI dan interaksi frontend yang telah diimplementasikan.
3. **Hasil Visual Validation:** Konfirmasi kesesuaian layout, warna, tipografi, komponen, dan status `php -l`.
4. **Asumsi & Keterbatasan:** Daftar mock data yang digunakan, asumsi visual, atau *missing requirement* (jika ada).

### 🛑 HARD STOP RULE
Setelah memberikan laporan Phase 4:
- **BERHENTI SEPENUHNYA (STOP).**
- **JANGAN** menghubungkan halaman ke database MySQL.
- **JANGAN** menulis logika CRUD atau pemrosesan `$_POST`/`$_FILES`.
- **TUNGGU** hingga user memberikan **Approval UI secara eksplisit** (misal: *"UI approved, lanjut integrasi backend"*).

---

## 17. Backend Integration Workflow (Only After Explicit Approval)

Hanya setelah user menyatakan **UI Approved** dan meminta integrasi backend, lanjutkan dengan langkah berikut:

1. **Audit Database & Kontrak Data Aktual:**
   - Periksa skema tabel dan kolom di [`db/db_loote.sql`](../db/db_loote.sql) (11 tabel berbahasa Indonesia: `pengguna`, `kategori`, `produk`, `keranjang`, `negosiasi`, `riwayat_negosiasi`, `barter`, `riwayat_barter`, `pesanan`, `detail_pesanan`, `ulasan`).
   - Periksa catatan antisipasi bug di [`docs/loote-upload-produk-handoff.md`](loote-upload-produk-handoff.md) dan kontrak JSON `produk.deskripsi` di [`docs/adr/0001-structured-product-description.md`](adr/0001-structured-product-description.md).
2. **Gunakan Infrastruktur Backend Existing:**
   - Gunakan koneksi PDO dari `getDbConnection()` (`config/koneksi.php`).
   - Gunakan prepared statements (`$db->prepare(...)` + `$stmt->execute([...])`) untuk seluruh query yang melibatkan input pengguna.
   - Gunakan helper autentikasi di `auth.php` (`isLoggedIn()`, `getCurrentUser()`, `isVerifiedSeller()`, `requireAuth()`) dan helper format di `config/helpers.php` (`formatRupiah()`, `kategoriIcon()`, `parseDeskripsiProduk()`).
3. **Patuhi 5 Critical Business Rules (`PRD.md` Bagian 5):**
   - **BR-01 (Anti Self-Transaction):** User tidak boleh membeli, menawar, atau barter produk miliknya sendiri (`$produk['id_penjual'] == $currentUser['id']`).
   - **BR-02 (Stock-Based Reservation & Auto-Habis):** Stok berkurang secara atomik saat transaksi masuk tahap `pesanan` (`menunggu_pembayaran`) baik dari *Buy Now* maupun kesepakatan *Nego/Barter*. Produk tetap `tersedia` selama `stok > 0`, dan otomatis menjadi `habis` saat `stok == 0`. Untuk barter yang disepakati, stok kedua produk dikurangi bersamaan.
   - **BR-03 (Auto-Fail Competing Offers):** Saat stok berkurang atau produk dinonaktifkan, seluruh sesi `negosiasi` dan `barter` lain yang masih aktif (`pending` / `counter_offer`) dengan kuantitas melebihi sisa stok (atau semua sesi aktif saat `stok == 0` / `nonaktif`) otomatis berubah ke status `gagal`.
   - **BR-04 (Verified Seller Only):** Halaman pembuatan/pengelolaan produk seller wajib memvalidasi `is_verified == 1` di sisi backend PHP (dan sinkronkan `$_SESSION['is_verified']`).
   - **BR-05 (Review Integrity):** Ulasan hanya bisa dikirim jika `status_pesanan == 'selesai'` dan pesanan belum pernah diulas (`UNIQUE KEY uq_ulasan_pesanan`).
4. **Pertahankan UI yang Sudah Disetujui:**
   - Ganti mock array dengan data hasil query PDO tanpa mengubah struktur HTML, class Tailwind, tata letak, maupun desain visual yang sudah di-approve oleh user pada Phase 4.

---

## 18. Strict Prohibitions

Ringkasan larangan mutlak bagi seluruh AI agent yang bekerja di project Loot-E:

1. **DILARANG** mengimplementasikan query database, CRUD, autentikasi baru, atau logika bisnis backend pada tahap implementasi UI awal (sebelum UI di-approve user).
2. **DILARANG** melakukan redesign, menambah elemen visual yang tidak diminta, mengubah urutan section, atau mengimprovisasi fitur UI di luar screenshot desain.
3. **DILARANG** membuat design system baru, mengubah palet warna brand (`#FFC914` & `#272727`), atau mengganti font `Plus Jakarta Sans`.
4. **DILARANG** mengganti Phosphor Icons dengan library ikon lain atau menggunakan emoji untuk ikon UI.
5. **DILARANG** memperkenalkan framework baru (Laravel, React, Vue, Alpine.js, Bootstrap, jQuery) atau build tools (npm, Vite, Webpack).
6. **DILARANG** mengubah komponen global (`components/navbar.php`, `components/footer.php`, `config/helpers.php`, `auth.php`, `db/db_loote.sql`) secara sepihak saat hanya diminta membuat/mengubah satu halaman UI tertentu.
7. **DILARANG** mengarang nama tabel/kolom database tanpa memeriksa `db/db_loote.sql`.
8. **DILARANG** menampilkan output data ke HTML tanpa `htmlspecialchars()` atau menjalankan query berparameter tanpa PDO prepared statements.

---

## 19. Implementation Checklist

Gunakan daftar periksa ini setiap kali menerima tugas implementasi screenshot/Figma dari user:

### Pre-Implementation (Phase 1)
- [ ] Sudah membaca `docs/loote-frontend-ui-handoff.md` secara menyeluruh.
- [ ] Sudah memeriksa screenshot desain dan catatan konteks halaman dari user.
- [ ] Sudah memeriksa komponen global (`navbar.php`, `footer.php`, `helpers.php`) dan halaman referensi existing yang paling mirip.
- [ ] Sudah mengidentifikasi apakah ada elemen/requirement yang ambigu atau tidak ada di desain.

### UI Implementation (Phase 2)
- [ ] Menggunakan struktur HTML5 semantik (`lang="id"`), Google Fonts `Plus Jakarta Sans`, Phosphor Icons CDN, dan Tailwind CSS CDN dengan `tailwind.config` standar Loot-E.
- [ ] Menyertakan `components/navbar.php` (atau `navbar_landing.php`) dan `components/footer.php` sesuai konteks halaman.
- [ ] Mereproduksi layout, grid, spacing, warna, border radius (tajam vs membulat), shadow, dan tipografi sesuai screenshot.
- [ ] Menggunakan asset dari `assets/` dan helper `kategoriIcon()` / `formatRupiah()`.
- [ ] Menggunakan data statis/mock array untuk simulasi tampilan — **tanpa** query database atau pemrosesan backend baru.
- [ ] Tidak ada elemen, dekorasi, gradient, atau fitur tambahan di luar desain.

### Visual Validation & Approval Gate (Phase 3 & 4)
- [ ] Lulus uji sintaks `php -l` tanpa error.
- [ ] Hasil tampilan sudah dibandingkan dengan screenshot desain dan seluruh perbedaan visual sudah dikoreksi.
- [ ] Laporan implementasi, daftar file, hasil validasi visual, dan asumsi telah disiapkan untuk user.
- [ ] **STOP** dan menunggu approval eksplisit dari user sebelum menyentuh integrasi backend.

### Backend Integration (Hanya Setelah Approved)
- [ ] Sudah memeriksa tabel dan kolom terkait di `db/db_loote.sql` serta `docs/loote-upload-produk-handoff.md`.
- [ ] Menggunakan PDO prepared statements via `getDbConnection()` dan escaping `htmlspecialchars()`.
- [ ] Mematuhi aturan bisnis `BR-01` s.d. `BR-05` dan format JSON `produk.deskripsi` (ADR-0001).
- [ ] Desain UI yang sudah disetujui tetap terjaga 100% tanpa regresi visual.

---

## Suggested Skills for Future Sessions

Saat melanjutkan pekerjaan implementasi UI maupun integrasi backend di sesi berikutnya, agent disarankan memuat skill berikut:
- **`antislop`** & **`antislop-ui`** — memastikan hasil implementasi UI tajam, bersih, dan bebas dari pola desain generik AI (*AI slop*) sambil tetap patuh 100% pada desain Figma/screenshot user.
- **`ponytail`** — menjaga kode PHP Native, HTML, dan Vanilla JS tetap ringkas, langsung ke sasaran, dan bebas dari *over-engineering*.
- **`ponytail-review`** — untuk mengaudit keamanan query PDO, escaping XSS, dan kepatuhan aturan bisnis (`BR-01` s.d. `BR-05`) pada tahap integrasi backend setelah UI disetujui.

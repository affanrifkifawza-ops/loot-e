<?php
require_once "auth.php";
$currentUser = getCurrentUser();
$navActivePage = 'landing';

// Etalase untuk pengunjung: hanya tampilan, klik produk diarahkan ke login jika belum masuk
$produkTampil = getDbConnection()->query("
  SELECT p.id, p.nama_produk, p.harga, k.nama_kategori
  FROM produk p
  JOIN kategori k ON k.id = p.id_kategori
  WHERE p.status_produk = 'tersedia'
  ORDER BY p.created_at DESC, p.id DESC
  LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Loot-E - Temukan Lebih Baik. Transaksi Lebih Cerdas.</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            ye: '#FFC914',
            ch: '#272727',
            brand: {
              yellow: '#FFC914',
              dark: '#272727'
            }
          },
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
            hd: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif']
          }
        }
      }
    }
  </script>
  <style>
    :root {
      --lt: #ffffff;
      --card: #F4F4F4;
      --ink: #272727;
      --mute: #8a8a8a;
      --line: #e3e3e3;
      box-sizing: border-box;
      padding-top: env(safe-area-inset-top, 0px);
      padding-bottom: env(safe-area-inset-bottom, 0px)
    }

    @media(prefers-color-scheme:dark) {
      :root:not([data-theme="light"]) {
        --lt: #272727;
        --card: #262626;
        --ink: #f1f1f1;
        --mute: #a0a0a0;
        --line: #3d3d3d;
        color-scheme: dark
      }
    }

    :root[data-theme="dark"] {
      --lt: #272727;
      --card: #262626;
      --ink: #f1f1f1;
      --mute: #a0a0a0;
      --line: #3d3d3d;
      color-scheme: dark
    }

    html {
      scroll-padding-top: env(safe-area-inset-top, 0px);
      scroll-behavior: smooth
    }

    body::before {
      content: "";
      position: fixed;
      inset: 0 0 auto;
      height: env(safe-area-inset-top, 0px);
      background: #272727;
      z-index: 60
    }

    .slot:focus-visible,
    a:focus-visible,
    button:focus-visible {
      outline: 2px solid #FFC914;
      outline-offset: 2px
    }

    @media(prefers-reduced-motion:reduce) {
      html {
        scroll-behavior: auto
      }
    }
  </style>
</head>

<body class="font-sans bg-[var(--lt)] text-[var(--ink)] antialiased">

  <!-- NAVBAR -->
  <?php require_once 'components/navbar.php'; ?>

  <!-- HERO -->
  <section
    class="relative flex min-h-[580px] md:min-h-[640px] lg:min-h-[700px] flex-col justify-between overflow-hidden bg-gradient-to-br from-[#272727] to-[#1a1a1a] text-white">
    <!-- Gambar Hero Responsif Langsung di HTML -->
    <div class="absolute inset-0 h-full w-full overflow-hidden">
      <img src="./assets/landingimage.png" alt="Loot-E Electronics Marketplace Hero"
        class="h-full w-full object-cover object-center lg:object-[75%_center] filter brightness-[0.85] transition-all" />
      <!-- Overlay Gradient Responsif: Memastikan teks selalu kontras dan terbaca jelas -->
      <div
        class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/55 to-black/35 md:bg-gradient-to-r md:from-black/85 md:via-black/50 md:to-black/20">
      </div>
    </div>

    <!-- Padding atas -->
    <div class="h-2"></div>

    <!-- Konten Teks Hero -->
    <div class="relative z-10 mx-auto flex w-full max-w-[1200px] flex-1 flex-col justify-center px-6 py-16 lg:px-10">
      <p class="text-xs md:text-[13px] font-semibold tracking-[.12em] text-ye drop-shadow-sm">MARKETPLACE ELEKTRONIK,
        DIRANCANG ULANG</p>
      <h1 class="mt-3 font-hd text-3xl sm:text-5xl md:text-6xl font-bold leading-[1.15] drop-shadow-md">
        Temukan Lebih Baik.<br><span class="text-ye">Transaksi Lebih Cerdas.</span>
      </h1>
      <p
        class="mt-4 md:mt-5 max-w-xl text-sm sm:text-base md:text-lg font-medium leading-relaxed text-gray-200 drop-shadow">
        Marketplace elektronik untuk membeli, menjual, bernegosiasi, dan barter dengan cara yang lebih fleksibel dan
        transparan.
      </p>
      <div class="mt-7 flex flex-wrap gap-3">
        <a href="#produk"
          class="bg-ye px-6 py-3 text-xs font-bold text-[#272727] shadow-lg hover:brightness-95 transition">
          Jelajahi Marketplace
        </a>
        <a href="<?php echo $currentUser ? 'index.php' : 'login.php'; ?>"
          class="bg-white/20 px-6 py-3 text-xs font-semibold backdrop-blur hover:bg-white/30 transition border border-white/20">
          Masuk Ke Akun
        </a>
      </div>
    </div>

    <!-- Fitur Bar di Bagian Bawah Hero -->
    <div
      class="relative z-10 mx-auto grid w-full max-w-[1200px] grid-cols-1 rounded-t-[33px] md:rounded-t-[30px] bg-ch/95 backdrop-blur-sm sm:grid-cols-2 lg:grid-cols-4 border-t border-white/10 shadow-2xl">
      <div class="flex items-center gap-3 p-4 lg:px-6"><i data-i="shield" class="h-5 w-5 shrink-0 text-ye"></i>
        <p class="text-[11px]"><b class="block font-semibold">Rekening Bersama</b><span class="text-ye">Proteksi dana
            100% aman</span></p>
      </div>
      <div class="flex items-center gap-3 border-white/15 p-4 lg:border-l lg:px-6"><i data-i="swap"
          class="h-5 w-5 shrink-0 text-ye"></i>
        <p class="text-[11px]"><b class="block font-semibold">Opsi Barter Sah</b><span class="text-ye">Tukar tambah
            hardware terstruktur</span></p>
      </div>
      <div class="flex items-center gap-3 border-white/15 p-4 lg:border-l lg:px-6"><i data-i="sliders"
          class="h-5 w-5 shrink-0 text-ye"></i>
        <p class="text-[11px]"><b class="block font-semibold">Negosiasi Langsung</b><span class="text-ye">Tawar harga
            tanpa drama chat</span></p>
      </div>
      <div class="flex items-center gap-3 border-white/15 p-4 lg:border-l lg:px-6"><i data-i="chart"
          class="h-5 w-5 shrink-0 text-ye"></i>
        <p class="text-[11px]"><b class="block font-semibold">Rujukan Harga Pasar</b><span class="text-ye">Indeks nilai
            wajar objektif</span></p>
      </div>
    </div>
  </section>

  <!-- PRINSIP -->
  <section id="tentang" class="bg-ch text-white">
    <div class="mx-auto grid max-w-[1440px] gap-10 px-6 py-14 lg:grid-cols-[5fr_7fr] lg:px-10">
      <div>
        <p class="flex items-center gap-3 text-[11px] font-semibold text-ye"><span class="h-px w-7 bg-ye"></span>PRINSIP
          PLATFORM</p>
        <h2 class="mt-4 max-w-sm text-3xl font-semibold leading-tight">Lebih dari sekadar marketplace elektronik.</h2>
        <p class="mt-5 max-w-md text-sm leading-relaxed">Loot-E menghubungkan pembeli dan penjual elektronik dengan cara
          transaksi yang lebih fleksibel dan transparan. Tidak ada kompromi pada kejelasan riwayat barang maupun
          keamanan dana.</p>
      </div>
      <div>
        <p class="max-w-2xl text-sm font-semibold leading-relaxed">Loot-E memberikan lebih banyak pilihan dalam
          bertransaksi elektronik. Beli langsung, ajukan harga, atau tukarkan barang melalui satu platform yang aman.
        </p>
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
          <div class="bg-[var(--card)] p-4 text-[var(--ink)]"><span
              class="mb-3 flex h-7 w-7 items-center justify-center rounded-full bg-ch text-ye"><i data-i="bag"
                class="h-3.5 w-3.5"></i></span>
            <h3 class="text-sm font-semibold">Beli &amp; Jual</h3>
            <p class="mt-1 text-[11px] text-[var(--mute)]">Beli dan jual berbagai perangkat elektronik terpercaya.</p>
          </div>
          <div class="bg-[var(--card)] p-4 text-[var(--ink)]"><span
              class="mb-3 flex h-7 w-7 items-center justify-center rounded-full bg-ch text-ye"><i data-i="sliders"
                class="h-3.5 w-3.5"></i></span>
            <h3 class="text-sm font-semibold">Negosiasi</h3>
            <p class="mt-1 text-[11px] text-[var(--mute)]">Ajukan harga dan capai kesepakatan dengan penjual.</p>
          </div>
          <div class="bg-[var(--card)] p-4 text-[var(--ink)]"><span
              class="mb-3 flex h-7 w-7 items-center justify-center rounded-full bg-ch text-ye"><i data-i="swap"
                class="h-3.5 w-3.5"></i></span>
            <h3 class="text-sm font-semibold">Barter</h3>
            <p class="mt-1 text-[11px] text-[var(--mute)]">Tukar barang milikmu dengan produk pengguna lain aman.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- PRODUK -->
  <div id="products"></div>
  <section id="produk" class="bg-[var(--card)] px-6 pb-12 pt-12 lg:px-10">
    <div class="mx-auto max-w-[1100px] text-center">
      <p class="text-[10px] font-semibold tracking-wider text-[var(--mute)]">BERDAGANG SECARA FLEKSIBEL</p>
      <h2 class="mt-1 text-3xl font-semibold">Jelajahi Produk</h2>
      <p class="mx-auto mt-3 max-w-md text-xs text-[var(--mute)]">Setiap transaksi tidak harus dilakukan dengan cara
        yang sama. Tentukan metode yang paling menguntungkan kebutuhanmu.</p>
      <div class="mt-10 grid grid-cols-2 gap-3 text-left sm:grid-cols-3 lg:grid-cols-5">
        <?php foreach ($produkTampil as $p):
          $tujuan = 'search.php?q=' . urlencode($p['nama_produk']);
          $href = $currentUser ? $tujuan : 'login.php?redirect=' . urlencode($tujuan);
        ?>
          <a href="<?= htmlspecialchars($href) ?>" class="flex flex-col justify-between bg-[var(--lt)] p-4 hover:shadow-md transition">
            <div class="relative flex h-28 w-full flex-col items-center justify-center gap-1 overflow-hidden bg-[var(--card)] border border-[var(--line)] text-[var(--mute)] text-[10px]">
              <i class="ph <?= htmlspecialchars(kategoriIcon($p['nama_kategori'])) ?> text-2xl" aria-hidden="true"></i>
              <span><?= htmlspecialchars($p['nama_kategori']) ?></span>
            </div>
            <h3 class="mt-4 truncate text-sm font-semibold"><?= htmlspecialchars($p['nama_produk']) ?></h3>
            <p class="text-xs text-[var(--mute)]"><?= htmlspecialchars($p['nama_kategori']) ?></p>
            <p class="mt-2 text-sm font-semibold"><?= formatRupiah((int)$p['harga']) ?></p>
          </a>
        <?php endforeach; ?>
        <?php if (empty($produkTampil)): ?>
          <p class="col-span-full text-xs text-[var(--mute)]">Belum ada produk tersedia.</p>
        <?php endif; ?>
      </div>
      <a href="<?php echo $currentUser ? 'index.php' : 'login.php'; ?>"
        class="mt-6 inline-flex items-center gap-2 bg-ye px-5 py-2.5 text-xs font-bold text-[#272727] hover:brightness-95">Lihat
        Semua Produk <i data-i="arrow" class="h-4 w-4"></i></a>
    </div>
  </section>

  <!-- CARA BERTRANSAKSI -->
  <section class="bg-[var(--card)] px-6 pb-16 pt-6 lg:px-10">
    <div class="mx-auto max-w-[1200px] text-center">
      <p class="text-[10px] font-semibold tracking-wider text-[var(--mute)]">BERDAGANG SECARA FLEKSIBEL</p>
      <h2 class="mt-1 text-3xl font-semibold">Pilih Cara Bertransaksimu</h2>
      <p class="mx-auto mt-3 max-w-md text-xs text-[var(--mute)]">Setiap transaksi tidak harus dilakukan dengan cara
        yang sama. Tentukan metode yang paling menguntungkan kebutuhanmu.</p>
      <div class="mt-10 grid gap-5 text-left lg:grid-cols-3">
        <!-- Buy & Sell -->
        <article class="flex flex-col bg-[var(--lt)] p-5">
          <div class="flex items-center justify-between text-[10px] font-semibold"><span
              class=" bg-[var(--line)] px-2 py-1">BELI &amp; JUAL</span><span
              class="flex items-center gap-1 text-[var(--mute)]"><span
                class="h-1.5 w-1.5 rounded-full bg-ye"></span>Transaksi Instan</span></div>
          <div class="mt-4 bg-[var(--card)] p-3">
            <div
              class="relative flex h-40 w-full items-center justify-center overflow-hidden rounded bg-[var(--lt)] border border-[var(--line)]">
              <img src="" alt="Produk Beli & Jual" class="h-full w-full object-contain p-2"
                onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';" />
              <div class="flex flex-col items-center justify-center gap-1 text-[var(--mute)] text-[11px]">
                <i data-i="image" class="h-6 w-6"></i>
                <span>Foto Produk</span>
              </div>
            </div>
            <div class="mt-5 flex items-center justify-between"><b class="text-sm">Rp 9.200.000</b><span
                class="bg-ye px-2.5 py-1 text-[10px] font-semibold text-[#272727]">Beli Langsung</span></div>
          </div>
          <p class="mt-5 flex-1 text-[11px] leading-relaxed">Temukan perangkat elektronik yang kamu butuhkan atau jual
            barang yang sudah tidak digunakan dengan sistem pembayaran terlindungi.</p>
          <div
            class="mt-6 flex items-center justify-between border-t border-[var(--line)] pt-4 text-[11px] text-[var(--mute)]">
            Jaminan Checkout Langsung <i data-i="check" class="h-4 w-4"></i></div>
        </article>
        <!-- Negotiate -->
        <article class="flex flex-col bg-[var(--lt)] p-5">
          <div class="flex items-center justify-between text-[10px] font-semibold"><span
              class="bg-[var(--line)] px-2 py-1">NEGOSIASI</span><span
              class="flex items-center gap-1 text-[var(--mute)]"><i data-i="tag" class="h-3 w-3 text-ye"></i>Tawar
              Balik</span></div>
          <div class="mt-4 space-y-2.5 p-1 text-[11px]">
            <div class="flex items-center justify-between border border-[var(--line)] bg-[var(--card)] px-3 py-2.5">
              <span class="text-[var(--mute)]">Harga Produk:</span><b class="text-xs">Rp 6.800.000</b>
            </div>
            <div class="flex items-center justify-between border border-[var(--line)] bg-[var(--card)] px-3 py-2.5">
              <span class="text-[var(--mute)]">Penawaran Kamu:</span><b class="text-xs">Rp 6.200.000</b>
            </div>
            <div class="flex items-center justify-between border-2 border-ye bg-[var(--card)] px-3 py-2.5">
              <span class="text-[var(--mute)]">Tawaran Balik Penjual:</span><b class="text-xs">Rp 6.500.000</b>
            </div>
            <div id="st" class="flex items-center justify-center gap-2 bg-ch py-3 text-xs font-semibold text-ye"><i
                data-i="clock" class="h-3.5 w-3.5"></i><span id="stt">Status: Disetujui Bersama</span></div>
          </div>
          <p class="mt-5 flex-1 text-[11px] leading-relaxed">Ajukan harga dan temukan kesepakatan yang sesuai dengan
            penjual tanpa spam chat atau tawar-menawar tanpa kepastian.</p>
          <div
            class="mt-6 flex items-center justify-between border-t border-[var(--line)] pt-4 text-[11px] text-[var(--mute)]">
            Kesepakatan Harga Wajar <i data-i="check" class="h-4 w-4"></i></div>
        </article>
        <!-- Barter -->
        <article class="flex flex-col bg-[var(--lt)] p-5">
          <div class="flex items-center justify-between text-[10px] font-semibold"><span
              class="bg-[var(--line)] px-2 py-1">BARTER</span><span
              class="flex items-center gap-1 text-[var(--mute)]"><span
                class="h-1.5 w-1.5 rounded-full bg-ye"></span>Tukar Beberapa Barang</span></div>
          <div class="mt-4 bg-[var(--card)] p-3">
            <div class="relative grid grid-cols-2 gap-3">
              <div class="border border-[var(--line)] p-2 text-center text-[10px] font-semibold">
                <div
                  class="relative flex h-20 w-full items-center justify-center overflow-hidden rounded bg-[var(--lt)] border border-[var(--line)]">
                  <img src="" alt="Kingston 8GB RAM" class="h-full w-full object-contain p-1"
                    onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';" />
                  <div class="flex flex-col items-center justify-center gap-0.5 text-[var(--mute)] text-[9px]">
                    <i data-i="image" class="h-4 w-4"></i>
                    <span>RAM</span>
                  </div>
                </div>
                <p class="mt-3">Kingston 8GB RAM</p>
                <p class="font-normal text-[var(--mute)]">Estimasi Rp 350.000</p>
              </div>
              <div class="border border-[var(--line)] p-2 text-center text-[10px] font-semibold">
                <div
                  class="relative flex h-20 w-full items-center justify-center overflow-hidden rounded bg-[var(--lt)] border border-[var(--line)]">
                  <img src="" alt="Kingston 240GB SSD" class="h-full w-full object-contain p-1"
                    onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';" />
                  <div class="flex flex-col items-center justify-center gap-0.5 text-[var(--mute)] text-[9px]">
                    <i data-i="image" class="h-4 w-4"></i>
                    <span>SSD</span>
                  </div>
                </div>
                <p class="mt-3">Kingston 240GB SSD</p>
                <p class="font-normal text-[var(--mute)]">Estimasi Rp 340.000</p>
              </div>
              <span
                class="absolute left-1/2 top-1/2 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-ye text-[#272727]"><i
                  data-i="swap" class="h-4 w-4"></i></span>
            </div>
            <p class="mt-3 bg-[var(--lt)] py-1.5 text-center text-[10px] font-semibold text-[var(--mute)]">
              Barter Rekber: Verifikasi Kurir Ganda</p>
          </div>
          <p class="mt-5 flex-1 text-[11px] leading-relaxed">Pilih barang dari katalog milikmu dan tawarkan sebagai
            pertukaran dengan produk pengguna lain dengan proteksi penuh.</p>
          <div
            class="mt-6 flex items-center justify-between border-t border-[var(--line)] pt-4 text-[11px] text-[var(--mute)]">
            Pelacakan Rekber Dua Arah <i data-i="truck" class="h-4 w-4"></i></div>
        </article>
      </div>
    </div>
  </section>

  <!-- WAWASAN KEPUTUSAN -->
  <section class="bg-ch px-6 py-16 text-center text-white lg:px-10">
    <div class="mx-auto max-w-[1200px]">
      <p class="text-[11px] font-semibold text-ye">WAWASAN KEPUTUSAN</p>
      <h2 class="mt-1 text-3xl font-semibold">Ambil Keputusan Lebih Baik Sebelum Bertransaksi</h2>
      <p class="mx-auto mt-3 max-w-md text-xs">Dapatkan informasi penting dan transparansi objektif sebelum menentukan
        keputusan transaksi elektronik.</p>
      <div class="mt-9 grid gap-5 text-left text-[var(--ink)] lg:grid-cols-3">
        <article class="bg-[var(--card)] p-4">
          <h3 class="flex items-center gap-2 text-sm font-semibold"><i data-i="chart" class="h-4 w-4 text-ye"></i>Acuan
            Harga Pasar</h3>
          <p class="mt-2 text-[10px] text-[var(--mute)]">Estimasi rentang harga wajar berdasarkan data transaksi 30 hari
            terakhir.</p>
          <div class="mt-3 bg-[var(--lt)] p-3 text-[10px] text-[var(--mute)]">
            <div class="flex justify-between"><span>Terendah: Rp 5.8M</span><span>Tertinggi: Rp 6.5M</span></div>
            <div class="relative mt-2 h-2 rounded-full bg-[var(--line)]"><span
                class="absolute inset-y-0 left-[12%] right-[10%] bg-ye"></span><span
                class="absolute -inset-y-0.5 left-[52%] w-1 bg-[#272727]"></span></div>
          </div>
          <div class="mt-2 flex items-center justify-between bg-[var(--lt)] px-3 py-2.5 text-[10px]"><span
              class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-ye"></span>Nilai Wajar
              Pasar</span><b class="text-xs">Rp 6.150.000</b></div>
        </article>
        <article class="bg-[var(--card)] p-4">
          <h3 class="flex items-center gap-2 text-sm font-semibold"><i data-i="swap" class="h-4 w-4 text-ye"></i>Kondisi
            &amp; Riwayat</h3>
          <p class="mt-2 text-[10px] text-[var(--mute)]">Spesifikasi riil dan transparansi pemakaian unit hardware.</p>
          <dl class="mt-3 space-y-2.5 bg-[var(--lt)] p-3 text-[10px]">
            <div class="flex justify-between">
              <dt class="text-[var(--mute)]">Kondisi Fisik:</dt>
              <dd class="font-semibold">Baik (9 / 10)</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-[var(--mute)]">Masa Pakai:</dt>
              <dd class="font-semibold">8 Bulan</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-[var(--mute)]">Riwayat Servis:</dt>
              <dd class="text-[#b88a00]">Tidak Ada (Segel Utuh)</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-[var(--mute)]">Kelengkapan:</dt>
              <dd class="font-semibold">Unit, Dus, Garansi</dd>
            </div>
          </dl>
        </article>
        <article class="bg-[var(--card)] p-4">
          <h3 class="flex items-center gap-2 text-sm font-semibold"><i data-i="user"
              class="h-4 w-4 text-ye"></i>Performa Penjual</h3>
          <p class="mt-2 text-[10px] text-[var(--mute)]">Riwayat terukur reputasi penjual untuk meminimalkan risiko
            transaksi.</p>
          <div class="mt-3 space-y-2 bg-[var(--lt)] p-3 text-[10px]">
            <div class="flex justify-between"><span class="flex items-center gap-1 font-semibold"><i data-i="star"
                  class="h-3 w-3 text-ye"></i>4.8 / 5.0</span><span class="text-[var(--mute)]">128 Transaksi
                Sukses</span></div>
            <div class="flex items-center gap-1.5  bg-[var(--card)] px-2 py-1.5"><i data-i="clock"
                class="h-3 w-3 text-[var(--mute)]"></i>Respons Cepat: &lt;15 Menit</div>
            <div
              class="flex items-center gap-1.5 border border-[var(--line)] bg-[var(--card)] px-2 py-1.5 font-semibold text-[#b88a00]">
              <i data-i="tag" class="h-3 w-3"></i>KTP &amp; Rekber Terverifikasi
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- WHY LOOT-E -->
  <section id="why-loote" class="bg-[var(--card)] px-6 py-12 lg:px-10">
    <div class="mx-auto max-w-[1200px] bg-[var(--lt)] p-8 md:p-12 border border-[var(--line)] shadow-sm">
      <div class="text-center md:text-left">
        <h2 class="mt-1 font-hd text-2xl md:text-3xl font-bold">
          Why Loot-<span class="text-ye">E</span>?
        </h2>
        <p class="mt-2 text-xs md:text-sm text-[var(--mute)] max-w-lg">
          Kami hadir dengan fitur lengkap yang dirancang untuk memberikan pengalaman transaksi elektronik terbaik,
          transparan, dan terpercaya.
        </p>
      </div>

      <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="flex flex-col justify-between">
          <div>
            <i class="ph-fill ph-chart-bar text-3xl text-ye mb-3 block"></i>
            <h4 class="font-semibold text-sm mb-1.5">Market Price Reference</h4>
            <p class="text-xs text-[var(--mute)] leading-relaxed">Ketahui harga pasar terbaru dan nilai wajar barang
              sebelum membeli atau menawar.</p>
          </div>
        </div>
        <div class="flex flex-col justify-between">
          <div>
            <i class="ph-fill ph-check-circle text-3xl text-ye mb-3 block"></i>
            <h4 class="font-semibold text-sm mb-1.5">Product Condition &amp; History</h4>
            <p class="text-xs text-[var(--mute)] leading-relaxed">Lihat kondisi detail, riwayat pemakaian, kelengkapan
              kotak, dan foto asli produk.</p>
          </div>
        </div>
        <div class="flex flex-col justify-between">
          <div>
            <i class="ph-fill ph-user-circle text-3xl text-ye mb-3 block"></i>
            <h4 class="font-semibold text-sm mb-1.5">Seller Performance</h4>
            <p class="text-xs text-[var(--mute)] leading-relaxed">Cek reputasi, rating ulasan, dan histori transaksi
              seller sebelum kesepakatan dibuat.</p>
          </div>
        </div>
        <div class="flex flex-col justify-between">
          <div>
            <i class="ph-fill ph-lightning text-3xl text-ye mb-3 block"></i>
            <h4 class="font-semibold text-sm mb-1.5">Buy Now &amp; Nego</h4>
            <p class="text-xs text-[var(--mute)] leading-relaxed">Beli langsung dengan harga pas atau gunakan fitur
              tawar harga resmi tanpa spam chat.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA SECTION -->
  <section class="bg-[var(--card)] px-6 pb-14 lg:px-10">
    <div
      class="mx-auto flex max-w-[1200px] flex-col items-start justify-between gap-6 bg-ch px-8 py-10 text-white sm:flex-row sm:items-center shadow-xl border border-white/10 relative overflow-hidden">
      <div class="max-w-xl relative z-10">
        <p class="text-xs font-semibold tracking-wider text-ye uppercase">SIAP UPGRADE PERANGKAT?</p>
        <h2 class="mt-2 font-hd text-2xl md:text-3xl font-bold leading-snug">
          Temukan Perangkat Elektronik Impianmu di Loot-<span class="text-ye">E</span>
        </h2>
        <p class="mt-2.5 text-xs md:text-sm text-gray-300 leading-relaxed">
          Jelajahi ribuan produk elektronik dari berbagai seller terpercaya dengan harga terbaik, atau mulai tawarkan
          barangmu sekarang.
        </p>
      </div>
      <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto shrink-0 relative z-10">
        <a href="register.php"
          class="inline-flex items-center justify-center gap-2 bg-ye px-6 py-3.5 text-xs font-bold text-[#272727] shadow-lg hover:brightness-95 transition w-full sm:w-auto">
          Daftar Sekarang <i class="ph ph-arrow-right font-bold text-sm"></i>
        </a>
        <a href="login.php"
          class="inline-flex items-center justify-center border border-white/30 bg-white/10 px-6 py-3.5 text-xs font-semibold text-white backdrop-blur hover:bg-white/20 transition w-full sm:w-auto">
          Login
        </a>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <?php require_once 'components/footer.php'; ?>

  <script>
    (function() {
      var ICON = {
        menu: 'M4 7h16M4 12h16M4 17h16',
        arrow: 'M5 12h14M13 6l6 6-6 6',
        shield: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
        cshield: 'F:M12 2l9 3v7c0 5-4 8.5-9 10-5-1.5-9-5-9-10V5zM8.5 12l2.5 2.5 4.5-5',
        swap: 'M7 7h12l-3-3M17 17H5l3 3',
        sliders: 'M4 7h10M18 7h2M4 17h2M10 17h10M14 5v4M6 15v4',
        chart: 'M3 17l6-6 4 4 8-8M15 7h6v6',
        bag: 'M6 7h12l1 13H5zM9 7a3 3 0 016 0',
        check: 'M12 3a9 9 0 100 18 9 9 0 000-18zM8 12l3 3 5-6',
        tag: 'M12 3a9 9 0 100 18 9 9 0 000-18zM8 12l3 3 5-6',
        clock: 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l3 2',
        truck: 'M3 6h11v10H3zM14 9h4l3 3v4h-7M7 19.5h.01M17 19.5h.01',
        star: 'F:M12 2l3 6.5 7 .8-5.2 4.8 1.5 7-6.3-3.6L5.7 21l1.5-7L2 9.3l7-.8z',
        user: 'F:M12 12a4.5 4.5 0 100-9 4.5 4.5 0 000 9zM4 21c0-4 3.5-6.5 8-6.5s8 2.5 8 6.5z',
        bolt: 'F:M13 2L4 14h7l-1 8 9-12h-7z',
        bars: 'F:M3 21V11h4v10zM10 21V3h4v18zM17 21v-7h4v7z',
        image: 'M3 5h18v14H3zM3 16l5-5 4 4 3-3 6 6M9 9h.01',
        yt: 'M3 7a3 3 0 013-3h12a3 3 0 013 3v10a3 3 0 01-3 3H6a3 3 0 01-3-3zM10 9l5 3-5 3z',
        fb: 'F:M14 8h3V4h-3a4 4 0 00-4 4v2H7v4h3v6h4v-6h3l1-4h-4V8z',
        ig: 'M4 8a4 4 0 014-4h8a4 4 0 014 4v8a4 4 0 01-4 4H8a4 4 0 01-4-4zM12 8.5a3.5 3.5 0 100 7 3.5 3.5 0 000-7zM17 7h.01',
        dc: 'M7 6c3-1.5 7-1.5 10 0l2 10c-2 1.8-4 2.5-7 2.5S7 17.8 5 16zM9 12h.01M15 12h.01'
      };

      function icons(root) {
        root.querySelectorAll('[data-i]').forEach(function(e) {
          var d = ICON[e.dataset.i] || '',
            f = d.indexOf('F:') === 0;
          if (f) d = d.slice(2);
          e.innerHTML = '<svg viewBox="0 0 24 24" class="h-full w-full" fill="' + (f ? 'currentColor' : 'none') + '" stroke="' + (f ? 'none' : 'currentColor') + '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + d + '"/></svg>';
        });
      }
      icons(document);
    })();
  </script>
</body>

</html>

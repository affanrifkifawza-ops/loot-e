<?php
// verifikasi_akun.php - Halaman Gate & Informasi Verifikasi Akun Seller Loot-E
require_once "auth.php";

if (!isLoggedIn()) {
    header("Location: login.php?redirect=verifikasi_akun.php");
    exit;
}

$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

// Sinkronkan data terbaru pengguna dari database (BR-04)
$stmtUser = $db->prepare("
    SELECT id, nama, email, peran, nik, no_hp, alamat, info_rekening, is_verified
    FROM pengguna
    WHERE id = ?
    LIMIT 1
");
$stmtUser->execute([$userId]);
$userRow = $stmtUser->fetch();

if (!$userRow) {
    header("Location: auth.php?action=logout");
    exit;
}

$_SESSION['nama']        = $userRow['nama'];
$_SESSION['email']       = $userRow['email'];
$_SESSION['peran']       = $userRow['peran'];
$_SESSION['is_verified'] = (int)$userRow['is_verified'];

$currentUser = getCurrentUser();
$isVerified  = (int)$userRow['is_verified'] === 1;

$navActivePage = 'verifikasi';
$accountActiveMenu = 'verifikasi';
$accountSidebarUser = [
    'nama'        => (string)$userRow['nama'],
    'email'       => (string)$userRow['email'],
    'is_verified' => (int)$userRow['is_verified'],
];

// Ambil flash message dari redirect halaman seller (dashboard_seller.php / katalog.php / upload_produk.php)
$flash = null;
if (!empty($_SESSION['verifikasi_flash']) && is_array($_SESSION['verifikasi_flash'])) {
    $flash = $_SESSION['verifikasi_flash'];
    unset($_SESSION['verifikasi_flash']);
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verifikasi Akun | Loot-E</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ye: '#FFC914',
                        ch: '#272727',
                        brand: {
                            yellow: '#FFC914',
                            dark: '#272727',
                            footer: '#272727',
                            bg: '#F4F4F4'
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        hd: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif']
                    }
                }
            }
        };
    </script>

    <style>
        :root {
            --brand-yellow: #FFC914;
            --brand-dark: #272727;
        }

        a[href],
        button:not(:disabled),
        summary,
        select:not(:disabled),
        label[for] {
            cursor: pointer;
        }

        button:disabled,
        input:disabled,
        select:disabled {
            cursor: not-allowed;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            outline: 2px solid var(--brand-yellow);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="bg-[#F4F4F4] font-sans text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Header & Navigasi Utama -->
    <?php require_once 'components/navbar.php'; ?>

    <!-- Konten Utama -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 md:px-12 py-8 md:py-10">
        <div class="flex flex-col lg:flex-row items-start gap-6">

            <!-- Sidebar Navigasi Akun -->
            <?php require_once 'components/account_sidebar.php'; ?>

            <!-- Area Konten Utama Verifikasi Akun -->
            <section class="flex-1 w-full min-w-0">

                <!-- Header Judul Halaman -->
                <header>
                    <h1 class="text-2xl sm:text-[26px] font-extrabold text-[#272727] tracking-tight leading-tight">
                        Verifikasi Akun
                    </h1>
                    <p class="text-xs sm:text-[13px] text-gray-600 mt-1">
                        Verifikasi identitas akunmu untuk membuka akses berjualan dan pengelolaan toko di Loot-E
                    </p>
                </header>

                <!-- Notifikasi Redirect dari Halaman Seller -->
                <?php if ($flash && !empty($flash['message'])): ?>
                    <div role="alert"
                        class="mt-4 bg-[#FFFDF5] border border-amber-300/80 px-4 py-3 rounded-[2px] flex items-start gap-2.5 text-xs text-[#272727]">
                        <i class="ph-bold ph-warning-circle text-base text-amber-700 shrink-0 mt-0.5" aria-hidden="true"></i>
                        <p class="leading-relaxed">
                            <?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!$isVerified): ?>
                    <!-- ============================================================== -->
                    <!-- STATE A: AKUN BELUM TERVERIFIKASI                              -->
                    <!-- ============================================================== -->
                    <div id="section-unverified" class="mt-5">
                        <!-- Kartu Utama Status & CTA Verifikasi (Selaras dengan State Kosong alamat_user.php) -->
                        <div class="bg-white border border-gray-200/80 p-8 sm:p-12 lg:p-14 text-center rounded-[2px] shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                            <!-- Ilustrasi Kartu Identitas & Perisai Verifikasi Loot-E -->
                            <div class="w-28 h-24 mx-auto flex items-center justify-center">
                                <svg width="112" height="96" viewBox="0 0 112 96" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <ellipse cx="56" cy="84" rx="44" ry="8" fill="#EAEAEA" />
                                    <rect x="18" y="22" width="64" height="46" rx="4" fill="#E8EDF2" stroke="#CBD5E1" stroke-width="1.5" />
                                    <rect x="24" y="28" width="52" height="34" rx="2" fill="#F8FAFC" />
                                    <rect x="29" y="34" width="14" height="16" rx="2" fill="#CBD5E1" />
                                    <circle cx="36" cy="40" r="3" fill="#94A3B8" />
                                    <path d="M31 48C31 45.8 33.2 44.5 36 44.5C38.8 44.5 41 45.8 41 48" fill="#94A3B8" />
                                    <rect x="48" y="35" width="22" height="3.5" rx="1.5" fill="#CBD5E1" />
                                    <rect x="48" y="42" width="16" height="3" rx="1.5" fill="#E2E8F0" />
                                    <rect x="29" y="54" width="32" height="3" rx="1.5" fill="#E2E8F0" />
                                    <path d="M80 28L96 34V47C96 57.5 88.8 65.8 80 69C71.2 65.8 64 57.5 64 47V34L80 28Z" fill="#FFC914" stroke="#272727" stroke-width="1.75" stroke-linejoin="round" />
                                    <path d="M74.5 48.5L78.3 52.3L86 44.5" stroke="#272727" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>

                            <span class="inline-flex items-center gap-1.5 mt-3 bg-[#FFF8E1] border border-amber-200/80 text-[#854D0E] text-[11px] font-bold px-2.5 py-1 rounded-[2px]">
                                Status: Belum Terverifikasi
                            </span>

                            <h2 class="text-base sm:text-lg font-bold text-[#272727] mt-3">
                                Akun Belum Terverifikasi
                            </h2>
                            <p class="text-xs sm:text-[13px] text-gray-600 mt-2 max-w-md mx-auto leading-relaxed">
                                Akun kamu saat ini belum terverifikasi, sehingga belum bisa mengunggah atau menjual produk di Loot-E. Lengkapi verifikasi identitas satu kali untuk mulai membuka toko dan berjualan dengan aman.
                            </p>

                            <!-- TODO: arahkan ke halaman form verifikasi setelah dibuat -->
                            <button type="button" id="btn-mulai-verifikasi"
                                class="inline-flex items-center justify-center gap-2 min-h-[44px] bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-6 py-2.5 rounded-[2px] transition mt-6 cursor-pointer shadow-xs">
                                <i class="ph-bold ph-shield-check text-base" aria-hidden="true"></i>
                                <span>Verifikasi Akun Sekarang</span>
                            </button>

                            <p class="text-[11px] text-gray-500 mt-2.5">
                                Formulir verifikasi akun segera hadir.
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- ============================================================== -->
                    <!-- STATE B: AKUN SUDAH TERVERIFIKASI                              -->
                    <!-- ============================================================== -->
                    <div id="section-verified" class="mt-5">
                        <div class="bg-white border border-gray-200/80 p-8 sm:p-10 rounded-[2px] shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-gray-100">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-11 h-11 rounded-[2px] bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-700 shrink-0">
                                        <i class="ph-bold ph-seal-check text-2xl" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center gap-1 bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-bold px-2 py-0.5 rounded-[2px]">
                                            Status: Terverifikasi
                                        </span>
                                        <h2 class="text-base sm:text-lg font-bold text-[#272727] mt-1">
                                            Akun Sudah Terverifikasi
                                        </h2>
                                    </div>
                                </div>
                            </div>

                            <p class="text-xs sm:text-[13px] text-gray-600 mt-4 leading-relaxed">
                                Identitas akunmu sudah terverifikasi. Seluruh fitur penjual seperti Dashboard Toko, pengelolaan Katalog Produk, serta penerimaan tawaran Nego dan Barter sudah aktif digunakan.
                            </p>

                            <div class="mt-6 flex flex-wrap items-center gap-3">
                                <a href="dashboard_seller.php"
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition">
                                    <i class="ph ph-storefront text-base" aria-hidden="true"></i>
                                    <span>Buka Dashboard Toko</span>
                                </a>
                                <a href="katalog.php"
                                    class="inline-flex items-center justify-center gap-2 min-h-[44px] bg-[#272727] hover:bg-neutral-800 active:scale-[0.99] text-white font-bold text-xs sm:text-[13px] px-5 py-2.5 rounded-[2px] transition">
                                    <i class="ph ph-package text-base" aria-hidden="true"></i>
                                    <span>Kelola Katalog Produk</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </section>
        </div>
    </main>

    <!-- Toast Notifikasi Standar Loot-E -->
    <div id="verifikasi-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg max-w-sm">
    </div>

    <!-- Footer Standar Loot-E -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function () {
            const toast = document.getElementById('verifikasi-toast');
            const btnVerifikasi = document.getElementById('btn-mulai-verifikasi');
            const initialFlash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            let toastTimer = null;

            function showToast(message, type) {
                if (!toast || !message) return;
                toast.textContent = message;
                toast.classList.remove('hidden', 'border-brand-yellow', 'border-red-500');
                toast.classList.add(type === 'error' ? 'border-red-500' : 'border-brand-yellow');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function () {
                    toast.classList.add('hidden');
                }, 3800);
            }

            if (btnVerifikasi) {
                btnVerifikasi.addEventListener('click', function () {
                    // TODO: arahkan ke halaman form verifikasi setelah dibuat
                    showToast('Formulir verifikasi akun segera hadir.', 'info');
                });
            }

            document.querySelectorAll('[data-demo-toast]').forEach(function (el) {
                el.addEventListener('click', function () {
                    const msg = el.getAttribute('data-demo-toast');
                    if (msg) showToast(msg, 'info');
                });
            });

            if (initialFlash && initialFlash.message) {
                showToast(initialFlash.message, initialFlash.type || 'error');
            }
        })();
    </script>
</body>

</html>

<?php
// dashboard_seller.php - Halaman Dashboard Seller (Guard Login & BR-04)
require_once "auth.php";

if (!isLoggedIn()) {
    header("Location: login.php?redirect=dashboard_seller.php");
    exit;
}

$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

$stmtUser = $db->prepare("
    SELECT id, nama, email, peran, is_verified
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

// Sinkronkan session dengan data terbaru dari database (BR-04)
$_SESSION['nama']        = $userRow['nama'];
$_SESSION['email']       = $userRow['email'];
$_SESSION['peran']       = $userRow['peran'];
$_SESSION['is_verified'] = (int)$userRow['is_verified'];

if ((int)$userRow['is_verified'] !== 1) {
    $_SESSION['profile_flash'] = [
        'type'    => 'error',
        'message' => 'Selesaikan verifikasi akun terlebih dahulu untuk mengakses Toko Saya.',
    ];
    header("Location: profile.php");
    exit;
}

$currentUser = getCurrentUser();
$navActivePage = 'dashboard_seller';

$accountActiveMenu = 'toko_dashboard';
$accountSidebarUser = [
    'nama'        => (string)$userRow['nama'],
    'email'       => (string)$userRow['email'],
    'is_verified' => 1,
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Toko | Loot-E</title>

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

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
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

            <?php require_once 'components/account_sidebar.php'; ?>

            <!-- Area Konten Utama Dashboard Seller -->
            <section class="flex-1 w-full min-w-0">
                <header>
                    <h1 class="text-2xl sm:text-[26px] font-extrabold text-[#272727] tracking-tight leading-tight">
                        Dashboard
                    </h1>
                    <p class="text-xs sm:text-[13px] text-gray-500 mt-1">
                        Ringkasan performa dan aktivitas tokomu di Loot-E
                    </p>
                </header>

                <!-- Banner Informasi Belum Tersedia -->
                <div class="mt-5 bg-white border border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.03)] px-6 py-14 sm:py-16 text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-[#FFF8DB] border border-brand-yellow/40 flex items-center justify-center text-[#926B00]">
                        <i class="ph ph-storefront text-3xl" aria-hidden="true"></i>
                    </div>

                    <h2 class="mt-4 text-base sm:text-lg font-bold text-[#272727]">
                        Halaman Dashboard Belum Tersedia Saat Ini
                    </h2>
                    <p class="mt-1.5 text-xs sm:text-[13px] text-gray-500 max-w-md mx-auto leading-relaxed">
                        Fitur ringkasan Dashboard Seller masih dalam tahap pengembangan. Saat ini kamu dapat mengelola daftar produk tokomu melalui menu Katalog.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center justify-center gap-2.5">
                        <a href="katalog.php"
                            class="inline-flex items-center justify-center gap-2 bg-brand-yellow hover:brightness-95 active:scale-[0.99] text-[#272727] text-xs sm:text-[13px] font-bold px-5 py-2.5 rounded-[2px] transition">
                            <span>Buka Katalog Produk</span>
                            <i class="ph ph-arrow-right text-sm font-bold" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- Toast Notifikasi -->
    <div id="dashboard-toast" role="status" aria-live="polite"
        class="fixed bottom-6 left-6 z-50 hidden bg-brand-dark text-white border-l-4 border-brand-yellow px-4 py-3 rounded-md text-xs font-medium shadow-lg max-w-sm">
    </div>

    <!-- Footer & Help FAB -->
    <?php require_once 'components/footer.php'; ?>

    <script>
        (function () {
            const toast = document.getElementById('dashboard-toast');
            let toastTimer = null;

            function showToast(message) {
                if (!toast || !message) return;
                toast.textContent = message;
                toast.classList.remove('hidden');
                if (toastTimer) clearTimeout(toastTimer);
                toastTimer = setTimeout(function () {
                    toast.classList.add('hidden');
                }, 3200);
            }

            document.querySelectorAll('[data-demo-toast]').forEach(function (el) {
                el.addEventListener('click', function () {
                    const msg = el.getAttribute('data-demo-toast');
                    if (msg) showToast(msg);
                });
            });
        })();
    </script>
</body>

</html>

<?php

/**
 * components/footer.php - Komponen Footer Loot-E
 */
$_footerUser = $currentUser ?? (isset($_SESSION['user_id']) ? true : false);
$_fActive   = $navActivePage ?? '';
$_fOnLand   = $_fActive === 'landing';
$_fHome     = $_footerUser ? ($_fActive === 'home' ? '#' : 'index.php') : ($_fOnLand ? '#' : 'landingpage.php');
$_fProd     = $_fOnLand ? '#products' : 'landingpage.php#products';
$_fAbout    = $_fOnLand ? '#why-loote' : 'landingpage.php#why-loote';
?>
<footer class="bg-[#272727] text-gray-300 py-12 px-6 md:px-12">
    <div
        class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-8 mb-8 border-b border-gray-700 pb-8">
        <div class="text-center md:text-left">
            <div class="flex items-center justify-center md:justify-start gap-2 mb-2">
                <img src="assets/logo.png" alt="Logo Loot-E" class="h-10 w-10 rounded-full object-cover shadow-sm" />
                <span class="font-bold text-2xl text-white tracking-wide">LOOT-<span
                        class="text-brand-yellow">E</span></span>
            </div>
            <p class="text-xs">Find Better, Trade Smarter.</p>
        </div>
        <div class="flex flex-wrap justify-center gap-6 text-sm">
            <a href="<?= $_fHome ?>" class="hover:text-brand-yellow transition">Home</a>
            <?php if ($_footerUser): ?>
                <a href="categories.php" class="hover:text-brand-yellow transition">Categories</a>
                <a href="search.php" class="hover:text-brand-yellow transition">Cari Produk</a>
            <?php else: ?>
                <a href="<?= $_fProd ?>" class="hover:text-brand-yellow transition">Products</a>
                <a href="<?= $_fAbout ?>" class="hover:text-brand-yellow transition">About</a>
            <?php endif; ?>
            <a href="help.php" class="hover:text-brand-yellow transition">Help</a>
        </div>
        <div class="flex gap-4">
            <a href="#" class="hover:text-white transition" aria-label="YouTube"><i class="ph-fill ph-youtube-logo text-2xl"></i></a>
            <a href="#" class="hover:text-white transition" aria-label="Facebook"><i class="ph-fill ph-facebook-logo text-2xl"></i></a>
            <a href="#" class="hover:text-white transition" aria-label="Instagram"><i class="ph-fill ph-instagram-logo text-2xl"></i></a>
            <a href="#" class="hover:text-white transition" aria-label="Discord"><i class="ph-fill ph-discord-logo text-2xl"></i></a>
        </div>
    </div>
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between text-xs">
        <p>&copy; <?= date('Y') ?> Loot-E. All rights reserved.</p>
        <div class="flex gap-4 mt-4 md:mt-0">
            <a href="#" class="hover:text-white transition">Privacy</a>
            <a href="#" class="hover:text-white transition">Terms</a>
        </div>
    </div>
</footer>

<!-- Floating Action Button (FAB) Bantuan -->
<aside aria-label="Bantuan Cepat">
    <a href="help.php"
        id="loote-help-fab"
        class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 rounded-full drop-shadow-xl hover:drop-shadow-2xl hover:scale-110 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-brand-yellow/40 group"
        title="Pusat Bantuan Loot-E"
        aria-label="Buka Pusat Bantuan">
        <img src="assets/help.png" alt="Help" class="w-full h-full object-contain pointer-events-none select-none transition-transform group-hover:rotate-6" width="56" height="56" />
    </a>
</aside>

<script>
    (function() {
        if (window.location.pathname.endsWith('help.php')) {
            var fab = document.getElementById('loote-help-fab');
            if (fab) {
                fab.addEventListener('click', function(e) {
                    var openBtn = document.getElementById('open-support-dialog');
                    if (openBtn) {
                        e.preventDefault();
                        openBtn.click();
                    }
                });
            }
        }
    })();
</script>
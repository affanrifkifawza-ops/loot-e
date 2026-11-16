<?php

/**
 * components/footer.php - Komponen Footer Loot-E
 */
$_footerUser = $currentUser ?? (isset($_SESSION['user_id']) ? true : false);
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
            <a href="<?= $_footerUser ? 'index.php' : 'landingpage.php' ?>" class="hover:text-brand-yellow transition">Home</a>
            <a href="categories.php" class="hover:text-brand-yellow transition">Categories</a>
            <a href="<?= $_footerUser ? 'index.php#why-loote' : 'landingpage.php#why-loote' ?>" class="hover:text-brand-yellow transition">About</a>
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
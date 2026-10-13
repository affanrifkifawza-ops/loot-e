document.addEventListener('DOMContentLoaded', async () => {
    // 1. Search Functionality (Struktur Asli Dipertahankan)
    const searchInput = document.querySelector('input[type="text"]');
    const searchBtn = document.querySelector('button.bg-brand-yellow');

    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', () => {
            const query = searchInput.value.trim();
            if (query) {
                alert(`Mencari produk: ${query}`);
            }
        });

        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchBtn.click();
            }
        });
    }

    // 2. Dynamic Auth Check di Navbar (Menghubungkan Frontend ke Backend PHP)
    const userNavContainer = document.getElementById('userNavContainer');
    if (userNavContainer) {
        try {
            const res = await fetch('api/auth.php');
            const data = await res.json();

            if (data.logged_in && data.user) {
                const userName = data.user.nama || data.user.nama_lengkap || 'Pengguna';
                const avatarName = encodeURIComponent(userName);

                userNavContainer.innerHTML = `
                    <a href="dashboard.html" class="flex items-center gap-2 cursor-pointer group" title="Buka Dashboard">
                        <div class="h-8 w-8 rounded-full bg-brand-yellow/20 overflow-hidden border border-brand-yellow/60 flex items-center justify-center">
                            <img
                                src="https://ui-avatars.com/api/?name=${avatarName}&background=F5B700&color=303135&bold=true"
                                alt="${userName}"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <span class="text-sm text-gray-200 group-hover:text-brand-yellow transition hidden md:block font-medium">
                            ${userName}
                        </span>
                    </a>
                `;
            }
        } catch (e) {
            // Jika backend offline atau belum login, biarkan tombol Masuk & Daftar
        }
    }

    console.log("Loot-E scripts loaded successfully.");
});

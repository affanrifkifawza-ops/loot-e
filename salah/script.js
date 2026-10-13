/* ---------- Ikon (SVG stroke, 24x24) ---------- */
const svg = (p) =>
  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;

const ICONS = {
  bell: svg('<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>'),
  cart: svg('<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>'),
  user: svg('<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'),
  menu: svg('<path d="M4 6h16M4 12h16M4 18h16"/>'),
  search: svg('<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>'),
  arrow: svg('<path d="M5 12h14M13 6l6 6-6 6"/>'),
  gpu: svg('<rect x="2" y="7" width="20" height="10" rx="2"/><circle cx="8" cy="12" r="2.5"/><circle cx="16" cy="12" r="2.5"/><path d="M5 17v2M9 17v2"/>'),
  cpu: svg('<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>'),
  ram: svg('<rect x="2" y="7" width="20" height="8" rx="1"/><path d="M6 11h.01M10 11h.01M14 11h.01M18 11h.01M5 15v3M9 15v3M13 15v3M17 15v3"/>'),
  laptop: svg('<rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2 20h20"/>'),
  phone: svg('<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>'),
  monitor: svg('<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>'),
  headphones: svg('<path d="M3 14v-2a9 9 0 0 1 18 0v2"/><rect x="3" y="14" width="4" height="7" rx="1.5"/><rect x="17" y="14" width="4" height="7" rx="1.5"/>'),
  more: svg('<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>'),
  chart: svg('<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>'),
  shield: svg('<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>'),
  person: svg('<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'),
  bolt: svg('<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>'),
  check: svg('<path d="M5 12l4 4 10-10"/>'),
  youtube: svg('<rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3z"/>'),
  facebook: svg('<path d="M15 3h-2a4 4 0 0 0-4 4v3H6v4h3v7h4v-7h3l1-4h-4V7a1 1 0 0 1 1-1h2z"/>'),
  instagram: svg('<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>'),
  discord: svg('<path d="M5 6c2-1 4-1.5 7-1.5S17 5 19 6c1.5 3 2 6 2 10-2 1.5-3 2-4.5 2.5L15 16c-2 .7-4 .7-6 0l-1.5 2.5C6 18 5 17.500 3 16c0-4 .5-7 2-10z"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/>')
};

/* ---------- Data ---------- */
const categories = [
  { name: 'GPU', icon: 'gpu' },
  { name: 'CPU', icon: 'cpu' },
  { name: 'RAM', icon: 'ram' },
  { name: 'Laptop', icon: 'laptop' },
  { name: 'Phone', icon: 'phone' },
  { name: 'Monitor', icon: 'monitor' },
  { name: 'Headphones', icon: 'headphones' },
  { name: 'More', icon: 'more' }
];

const products = [
  { name: 'GTX 1660 Super', type: 'GPU - Nvidia', category: 'GPU', price: 2400000, rating: 4.8, reviews: 120, icon: 'gpu' },
  { name: 'Lenovo S145-15AST', type: 'Laptop - Lenovo', category: 'Laptop', price: 1725000, rating: 4.7, reviews: 67, icon: 'laptop' },
  { name: 'Iphone 11', type: 'Phone - Apple', category: 'Phone', price: 3150000, rating: 4.9, reviews: 84, icon: 'phone' },
  { name: 'ASUS ROG Strix XG27...', type: 'Monitor - Asus', category: 'Monitor', price: 6441000, rating: 4.8, reviews: 10, icon: 'monitor' },
  { name: 'Team T-Force Delta R...', type: 'RAM - T-Force', category: 'RAM', price: 968200, rating: 4.7, reviews: 168, icon: 'ram' }
];

const features = [
  { icon: 'chart', title: 'Market Price Reference', text: 'Ketahui harga pasar terkini sebelum membeli atau menawar.' },
  { icon: 'shield', title: 'Product Condition & History', text: 'Lihat kondisi detail dan riwayat produk.' },
  { icon: 'person', title: 'Seller Performance', text: 'Cek rating dan aktivitas seller sebelum bertransaksi.' },
  { icon: 'bolt', title: 'Buy Now', text: 'Transaksi cepat dengan harga tetap.' }
];

const formatRupiah = (n) => 'Rp ' + n.toLocaleString('id-ID');

/* ---------- Render ---------- */
document.querySelectorAll('[data-icon]').forEach((el) => (el.innerHTML = ICONS[el.dataset.icon]));
document.querySelectorAll('[data-icon-big]').forEach((el) => (el.innerHTML = ICONS[el.dataset.iconBig]));

const categoryList = document.getElementById('categoryList');
const productList = document.getElementById('productList');
const emptyState = document.getElementById('emptyState');

categoryList.innerHTML = categories
  .map((c) => `<button class="cat-card" data-cat="${c.name}">${ICONS[c.icon]}<span>${c.name}</span></button>`)
  .join('');

function renderProducts(list) {
  productList.innerHTML = list
    .map(
      (p) => `
      <article class="product-card">
        <div class="product-img">${ICONS[p.icon]}</div>
        <h3 class="truncate text-[11px] font-semibold">${p.name}</h3>
        <p class="text-[10px] text-gray-400">${p.type}</p>
        <p class="mt-2 text-xs font-bold">${formatRupiah(p.price)}</p>
        <p class="mt-1 flex items-center gap-1 text-[10px] font-medium">
          <span class="text-brand">&#9733;</span>${p.rating} (${p.reviews})
        </p>
        <p class="mt-3 flex items-center gap-1 text-[9px] text-gray-500">
          <span class="text-brand [&>svg]:h-3 [&>svg]:w-3">${ICONS.check}</span>Trusted Seller
        </p>
      </article>`
    )
    .join('');
  emptyState.classList.toggle('hidden', list.length > 0);
}
renderProducts(products);

document.getElementById('featureList').innerHTML = features
  .map(
    (f) => `
    <div>
      <span class="inline-block text-brand [&>svg]:h-6 [&>svg]:w-6">${ICONS[f.icon]}</span>
      <h3 class="mt-3 text-[11px] font-semibold">${f.title}</h3>
      <p class="mt-1 max-w-[170px] text-[10px] leading-relaxed text-gray-500">${f.text}</p>
    </div>`
  )
  .join('');

document.getElementById('socials').innerHTML = ['youtube', 'facebook', 'instagram', 'discord']
  .map((s) => `<a href="#" aria-label="${s}" class="text-white hover:text-brand [&>svg]:h-4 [&>svg]:w-4">${ICONS[s]}</a>`)
  .join('');

/* ---------- Interaksi ---------- */
// Menu mobile
document.getElementById('menuBtn').addEventListener('click', () => {
  document.getElementById('mobileMenu').classList.toggle('hidden');
});

// Filter kategori
let activeCat = null;
categoryList.addEventListener('click', (e) => {
  const btn = e.target.closest('.cat-card');
  if (!btn) return;
  const cat = btn.dataset.cat;
  if (cat === 'More') return;
  activeCat = activeCat === cat ? null : cat;
  categoryList.querySelectorAll('.cat-card').forEach((b) => b.classList.toggle('active', b.dataset.cat === activeCat));
  applyFilters();
});

// Pencarian
const searchInput = document.getElementById('searchInput');
document.getElementById('searchForm').addEventListener('submit', (e) => {
  e.preventDefault();
  applyFilters();
  document.getElementById('categories').scrollIntoView({ behavior: 'smooth' });
});
searchInput.addEventListener('input', applyFilters);

function applyFilters() {
  const q = searchInput.value.trim().toLowerCase();
  const result = products.filter(
    (p) =>
      (!activeCat || p.category === activeCat) &&
      (!q || (p.name + ' ' + p.type + ' ' + p.category).toLowerCase().includes(q))
  );
  renderProducts(result);
}

document.getElementById('year').textContent = new Date().getFullYear();

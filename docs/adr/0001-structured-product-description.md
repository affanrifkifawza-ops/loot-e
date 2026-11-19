# ADR-0001: Struktur JSON pada Kolom `produk.deskripsi` & Lingkup Pencarian Produk

- **Status:** Accepted
- **Tanggal:** 2026-11-19
- **Konteks:**
  Desain UI halaman detail produk ([detail_produk.php](../../detail_produk.php)) membagi informasi produk menjadi 3 tab: **Deskripsi**, **Spesifikasi**, dan **Riwayat & Kondisi**. Sementara itu, skema database ([db_loote.sql](../../db/db_loote.sql)) dan [PRD.md](../../PRD.md) menetapkan bahwa tabel `produk` hanya memiliki 1 kolom `deskripsi` (`TEXT`) tanpa mengubah struktur kolom tabel. Selain itu, halaman pencarian ([search.php](../../search.php)) sebelumnya ikut mencari ke kolom `p.deskripsi`, sehingga kata kunci seperti `"baterai"` memunculkan laptop/mouse yang hanya menyebut daya tahan baterai di dalam deskripsinya.

## Keputusan
1. **Format JSON Terstruktur di `produk.deskripsi`:**
   Kolom `produk.deskripsi` tetap 1 kolom `TEXT`, namun menyimpan JSON dengan struktur:
   ```json
   {
     "ringkasan": "Teks deskripsi umum produk",
     "spesifikasi": [
       { "label": "Nama Spesifikasi", "nilai": "Nilai Spesifikasi" }
     ],
     "riwayat": {
       "fisik": "...",
       "fungsi": "...",
       "lama_pakai": "...",
       "servis": "...",
       "minus": "...",
       "kelengkapan": "..."
     }
   }
   ```
2. **Spesifikasi Dinamis Bebas (Label & Nilai):**
   Penjual dapat menambahkan pasangan `label` dan `nilai` secara bebas sesuai kebutuhan kategori produk (GPU, Laptop, Phone, Aksesoris, dll.).
3. **Helper Parsing & Fallback Aman:**
   Fungsi `parseDeskripsiProduk()` di [config/helpers.php](../../config/helpers.php) mem-parsing JSON menjadi array terstruktur, dan tetap memiliki fallback aman jika menemui teks biasa (non-JSON).
4. **Lingkup Pencarian di `search.php`:**
   Pencarian kata kunci (`q`) hanya mencocokkan `p.nama_produk` dan `k.nama_kategori`, tidak lagi `p.deskripsi`.

## Konsekuensi
- **Positif:** Tidak perlu `ALTER TABLE` pada `db_loote.sql`; UI 3 tab pada Figma tetap terpenuhi; pencarian lebih akurat dan tidak tercemar teks deskripsi/JSON.
- **Perhatian ke depan:** Form tambah/edit produk penjual wajib menyusun input menjadi JSON via `json_encode(..., JSON_UNESCAPED_UNICODE)` sebelum menyimpan ke kolom `deskripsi`.

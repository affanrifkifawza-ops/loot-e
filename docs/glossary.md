# Glossary Domain Loot-E

| Istilah | Definisi |
| :--- | :--- |
| **`produk.deskripsi`** | Kolom `TEXT` pada tabel `produk` yang menyimpan payload JSON terstruktur (`ringkasan`, `spesifikasi`, `riwayat`) agar dapat dirender ke 3 tab pada `detail_produk.php`. |
| **`ringkasan`** | Bagian narasi/deskripsi umum produk yang ditampilkan di tab **Deskripsi**. |
| **`spesifikasi`** | Daftar pasangan `label` dan `nilai` teknis yang dibuat dinamis oleh penjual dan ditampilkan sebagai tabel di tab **Spesifikasi**. |
| **`riwayat`** | Rincian kondisi & pemakaian produk (`fisik`, `fungsi`, `lama_pakai`, `servis`, `minus`, `kelengkapan`) yang ditampilkan di tab **Riwayat & Kondisi**. |
| **Escrow (Rekber)** | Mekanisme penahanan dana oleh platform hingga pembeli mengonfirmasi barang diterima sesuai kondisi (`status_pesanan = 'selesai'`). |

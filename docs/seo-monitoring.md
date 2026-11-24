# Pemantauan SEO Ruethies

Baseline diberikan pemilik: **39 klik, 254 impressions, CTR 15,4%, posisi 6,5**. Periode belum disebutkan; catat tanggal awal/akhir sebelum membandingkan. Rata-rata keseluruhan bukan ranking “snack box Cikarang”. Jangan mengganti angka ini dengan hasil crawl atau pengujian browser.

## Search Console setelah rilis produksi

1. Catat tanggal/commit rilis. Jalankan audit CLI di luar webroot dan pastikan sitemap XML 200, hanya URL canonical yang terlihat; filter, draft, admin, PO privat/callback dan staging tidak masuk.
2. Kirim/periksa `https://ruethiespatisserie.com/sitemap.xml`. Jangan mengganti URL sitemap tanpa kebutuhan.
3. URL Inspection prioritas: `/`, `/menu/`, `/snack-box-cikarang/`, `/menu/platter/`, `/menu/lapis-marble/`, kemudian produk dan artikel yang berubah. Periksa rendered HTML, user canonical vs Google canonical, robots, respons, dan penemuan melalui sitemap/link. Ajukan indeks setelah perubahan bermakna; pengajuan berulang tidak menjamin percepatan.
4. `/menu` harus 301 satu hop ke `/menu/`. “Page with redirect” normal untuk alias; “Redirect error” harus diperiksa pada URL tepat, bukan menganggap semua redirect gagal. “Discovered, currently not indexed” berarti belum terindeks; bukan otomatis diblokir kode. Jangan memalsukan lastmod untuk memancing crawl.
5. URL Lapis Surabaya lama tetap 404 sesuai keputusan pemilik; URL produk baru Lapis Marble yang diminta indeks. Jangan menghidupkan kembali konten yang sudah dicabut.
6. Validasi schema dengan Rich Results Test/URL Inspection. Menu/Bakery yang valid tidak menjamin fitur hasil pencarian tertentu. Artikel terjemahan memakai self canonical masing-masing dan hreflang hanya pasangan yang benar-benar diterbitkan.
7. Pastikan staging tetap noindex. Robots adalah petunjuk crawl, bukan autentikasi atau mekanisme langsung untuk menghapus URL terindeks.

## Perbandingan setiap 28 hari

Gunakan properti, jenis pencarian, negara, perangkat dan periode dengan panjang sama. Simpan ekspor Queries, Pages, Devices, Countries, Chart, Filters. Bandingkan 28 hari terakhir dengan 28 hari sebelumnya; tinjau lagi setelah 8–12 minggu. Jika data kecil, hindari menyimpulkan dari satu klik atau satu perubahan posisi.

| Ukuran | Cara meninjau | Makna dan batas |
|---|---|---|
| Merek | Kueri mengandung Ruethies/variasi ejaan yang ditinjau | Permintaan merek, pisahkan dari perolehan baru |
| Nonmerek | Kueri sisanya yang relevan dengan produk | Impressions/klik untuk toko kue, jajanan, snack box dan produk nyata |
| Per intent | Halaman dan kueri Snackbox, Platter, produk kue/gurih, panduan corporate | Hindari satu angka rata-rata lintas intent |
| CTR | Kueri + halaman + perangkat + posisi yang sebanding | Snippet relevan dapat membantu; naik/turun posisi mengubah CTR juga |
| Posisi | Kueri tertentu dengan impressions yang cukup | Bukan janji ranking atau volume pencarian |
| Pengindeksan | URL canonical publik vs status URL Inspection | Jumlah sitemap tidak harus sama dengan indeks Google |
| Perangkat/lokal | Mobile/desktop, Indonesia, kota jika tersedia di sumber yang sah | Jangan menganggap semua impressions adalah pelanggan Cikarang |
| Core Web Vitals | Laporan Experience/CrUX bila sampel tersedia | Target LCP≤2,5s, INP≤200ms, CLS≤0,1; belum ada bukti field untuk implementasi ini |

Catat permintaan WhatsApp berdasarkan sumber yang pelanggan bersedia sebutkan: produk, Snackbox, pertanyaan corporate atau PO staging. Klik WhatsApp adalah **niat menghubungi**, bukan bukti pesan dikirim, pesanan atau pembayaran berhasil. Tidak menambah pelacak, dependency, API, atau layanan pihak ketiga dalam tugas ini. Jika konversi otomatis dibutuhkan, tentukan kebutuhan dan privasi sebelum implementasi terpisah. Jangan memakai transaksi sandbox sebagai penjualan organik.

## Google Business Profile — lewat akses pemilik

- Pastikan profil resmi terverifikasi; gunakan nama bisnis asli, alamat dan pin toko yang benar. Hindari keyword tambahan pada nama.
- Pilih kategori bisnis berdasarkan operasi nyata (misalnya Bakery/toko kue). Jangan menambah café/coffee shop karena keyword kandidat jika layanan itu belum ada.
- Selaraskan telepon, URL website canonical, jam harian 07.00–21.00 WIB dan jam khusus/libur jika berubah.
- Pastikan menu/produk mengarah ke `/menu/`, Snackbox dan produk yang masih dijual. Area layanan hanya wilayah yang benar-benar dilayani dan dikonfirmasi.
- Tambahkan foto produk/toko yang autentik dan masih sesuai. Tidak mengurangi kualitas foto demi skor audit.
- Minta ulasan secara wajar dari pelanggan nyata, tanpa insentif/ulasan buatan. Jawab pertanyaan serta ulasan dengan informasi faktual.
- Periksa profil duplikat, nomor lama, tautan sosial dan petunjuk arah. Website tidak dapat mengubah akun GBP melalui commit.

## Prioritas konten selanjutnya

Gunakan peta keyword untuk menyunting konten yang membantu pelanggan dan menghubungkan artikel ke produk relevan. Utamakan informasi yang telah dikonfirmasi: pilihan sajian, kebutuhan rapat/acara, lokasi dan cara memesan. Isi, harga, minimum, lead time, alergen, sertifikasi, stok serta pengantaran tidak boleh ditebak.

Belum membuat `/coffee/`, `/pastry/`, `/corporate-order/` atau halaman daerah lain. Buat halaman baru hanya bila penawaran nyata, intent berbeda, isi orisinal cukup, dan halaman yang ada tidak memenuhi kebutuhan. Artikel ID/EN tetap dua URL dengan relasi, satu kartu pasangan untuk pengunjung; jangan membuat terjemahan otomatis yang kosong.

## Rujukan resmi

- [Canonical dan duplikasi URL](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls).
- [Sitemap dan tanggal modifikasi](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).
- [Versi bahasa dan hreflang](https://developers.google.com/search/docs/specialty/international/localized-versions).
- [Situs multibahasa](https://developers.google.com/search/docs/specialty/international/managing-multi-regional-sites).
- [Structured data LocalBusiness](https://developers.google.com/search/docs/appearance/structured-data/local-business).
- [Judul pada hasil pencarian](https://developers.google.com/search/docs/appearance/title-link).

Pedoman ini membantu konsistensi teknis dan relevansi; tidak menjamin pengindeksan, rich result atau posisi tertentu.

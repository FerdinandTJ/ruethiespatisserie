# Implementasi SEO menyeluruh — staging

Tanggal: 24 November 2026. Dikembangkan dari staging `ce1be90` pada branch `feat/seo-comprehensive-staging`. Baseline main `021e23a`. Audit dan peta keyword dicatat terlebih dahulu pada commit `fd753af`. Sesuai arahan pemilik berikutnya, perbaikan SEO umum dibawa ke main lokal dari baseline `021e23a`, tanpa navigasi/flow PO atau tema staging. Belum push atau deploy; laporan ini tidak menyatakan perubahan sudah aktif di hosting.

## Perubahan dan batasnya

- Satu konfigurasi metadata pada `public-lib/seo.php`: lima halaman umum dan sepuluh produk yang benar-benar ada di produksi. Judul/deskripsi produk mengikuti data CMS hosting; artikel mempertahankan judul/ringkasan penulis. Tidak menambahkan keyword café, kopi, macaron atau pastry yang tidak ada pada katalog aktif.
- Bakery, WebSite, WebPage dan Article saling merujuk pada satu identitas bisnis. ID lama `/#cafe` dipertahankan agar identitas tidak terputus; tipenya tetap Bakery. Alamat diselaraskan dengan teks publik, menu menuju `/menu/`. `areaServed` yang belum dibuktikan dihapus, bukan diganti dengan cakupan layanan baru.
- Halaman artikel EN memberi HTML awal `lang=en`, label UI EN, `og:locale=en_US`, `og:type=article`. Dua artikel dengan relasi terjemahan published mempunyai hreflang ID/EN timbal balik dan self canonical. Tidak ada hreflang buatan untuk halaman umum yang hanya punya satu URL atau artikel tanpa pasangan.
- Prasyarat fitur umum main yang tertinggal di staging dibawa secara terbatas: pengelompokan kartu artikel ID/EN, tombol bahasa menuju pasangan artikel, dukungan variannya tetap. Navigasi Pre-order staging dipertahankan. Tidak mengubah backend PO, tabel, harga, kuota, snapshot, callback, Fonnte atau kredensial.
- Label kategori produk kini tautan HTML menuju filter kategori katalog yang dikelola admin. Parameter filter tetap noindex; tidak dijadikan landing baru. Tautan menu di halaman 404 langsung menuju `/menu/`.
- `tools/seo-audit.php` menyediakan crawl CLI rekursif tanpa dependency baru, laporan JSON dan Markdown, pemetaan origin canonical untuk staging, metadata/schema/hreflang/tautan/sitemap, orphan yang terdeteksi, redirect serta indikasi soft 404. Fixture menguji deteksi tersebut dan membuktikan admin tidak diminta oleh crawler.
- Tidak ada halaman marketing baru, migrasi JSON/database, penulisan akun, atau publikasi artikel. Tidak mengubah aset foto, kualitas/resolusi, palet, susunan halaman, atau animasi.

## Metadata sebelum dan sesudah

Ini perbandingan dengan snapshot **data publik produksi** pada saat audit. Kode baru diuji lokal; hosting belum berubah. Tanda `/` dalam judul tabel menggantikan pemisah `|`. Isi artikel tetap dari admin, bukan judul hasil karangan implementasi.

| URL | Title sebelum | Title sesudah | Description sebelum | Description sesudah |
|---|---|---|---|---|
| / | Toko Kue & Snack Box Lippo Cikarang  /  Ruethies Patisserie | Toko Kue & Snack Box Lippo Cikarang  /  Ruethies Patisserie | Toko kue di Lippo Cikarang dengan pilihan kue, jajanan pasar, camilan gurih dan snack box. Lihat menu Ruethies dan tanyakan pemesanan via WhatsApp. | Toko kue di Lippo Cikarang dengan pilihan kue, jajanan pasar, camilan gurih dan snack box. Lihat menu Ruethies dan tanyakan pemesanan via WhatsApp. |
| /menu/ | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | Menu Ruethies Patisserie di Cikarang  /  Kue & Camilan | Jelajahi kue, camilan gurih, snack box dan platter Ruethies Patisserie di Lippo Cikarang. Tanyakan pilihan serta harga melalui WhatsApp. | Jelajahi katalog Ruethies Patisserie: kue, jajanan, camilan gurih dan paket sajian di Lippo Cikarang. Tanyakan pilihan dan pemesanan melalui WhatsApp. |
| /snack-box-cikarang/ | Pesan Snack Box Cikarang  /  Ruethies Patisserie | Snack Box Cikarang untuk Rapat & Acara  /  Ruethies Patisserie | Pesan snack box di Lippo Cikarang. Lihat foto Ruethies, lalu tanyakan pilihan isi, harga, jumlah kotak dan tanggal kebutuhan melalui WhatsApp. | Snack box untuk rapat, arisan dan pertemuan di Lippo Cikarang. Lihat foto Ruethies, lalu konfirmasikan isi, jumlah, tanggal dan harga melalui WhatsApp. |
| /tentang-kami/ | Cerita di balik Ruethies  /  Ruethies Patisserie | Tentang Ruethies Patisserie  /  Toko Kue Lippo Cikarang | Kenali Ruethies Patisserie di Lippo Cikarang. Jelajahi kue klasik, camilan gurih, snack box dan galeri produk kami. | Kenali Ruethies Patisserie di Lippo Cikarang: pilihan kue, jajanan, camilan gurih, snack box, dan galeri sajian. Temukan menu serta kontak toko. |
| /hubungi-kami/ | Kunjungi & hubungi Ruethies  /  Ruethies Patisserie | Lokasi & Kontak Ruethies Patisserie  /  Lippo Cikarang | Alamat, jam buka, WhatsApp dan email Ruethies Patisserie di Lippo Cikarang. Dapatkan rute ke Ruko The Neo Patio East No. 51. | Kunjungi Ruethies Patisserie di Ruko The Neo Patio East No. 51, Lippo Cikarang. Buka setiap hari 07.00–21.00 WIB. Lihat rute, WhatsApp, dan email. |
| /cara-pesan/ | Cara pesan di Ruethies  /  Ruethies Patisserie | Cara Pesan Kue & Snack Box Ruethies  /  WhatsApp & FAQ | Panduan memesan kue dan snack box Ruethies melalui WhatsApp. Sampaikan pilihan, jumlah serta tanggal dan konfirmasikan kebutuhan bersama tim kami. | Cara memesan kue dan snack box Ruethies di Lippo Cikarang melalui WhatsApp: pilih sajian, sampaikan jumlah dan tanggal, lalu konfirmasikan pesanan. |
| /artikel/ | Artikel Ruethies  /  Ruethies Patisserie | Artikel Ruethies  /  Ruethies Patisserie | Panduan pemesanan, inspirasi acara dan tips sajian Ruethies Patisserie di Lippo Cikarang. | Panduan pemesanan, inspirasi acara dan tips sajian Ruethies Patisserie di Lippo Cikarang. |
| /menu/bolu-jadoel/ | Bolu Jadoel di Cikarang  /  Ruethies Patisserie | Bolu Jadoel di Cikarang  /  Ruethies Patisserie | Bolu klasik beraroma mocca dan cokelat dengan tekstur lembut, menghadirkan nostalgia dalam setiap potongan. Ruethies Patisserie di Lippo Cikarang. Konfirmasikan harga dan | Bolu Jadoel di Lippo Cikarang. Bolu klasik beraroma mocca dan cokelat dengan tekstur lembut, menghadirkan nostalgia dalam setiap potongan. Tanyakan pilihan… |
| /menu/brownies-cokelat/ | Brownies Cokelat di Cikarang  /  Ruethies Patisserie | Brownies Cokelat di Cikarang  /  Ruethies Patisserie | Brownies dari cokelat pilihan dengan tekstur padat, lembut, dan lumer, untuk waktu santai maupun perayaan istimewa. Ruethies Patisserie di Lippo Cikarang. Konfirmasikan h | Brownies Cokelat di Lippo Cikarang. Brownies dari cokelat pilihan dengan tekstur padat, lembut, dan lumer, untuk waktu santai maupun perayaan istimewa.… |
| /menu/lapis-marble/ | Lapis Marble di Cikarang  /  Ruethies Patisserie | Lapis Marble di Cikarang  /  Ruethies Patisserie | Kue lapis lembut bercorak marmer dengan perpaduan dua karakter rasa yang saling melengkapi. Ruethies Patisserie di Lippo Cikarang. Konfirmasikan harga dan pemesanan melal | Lapis Marble di Lippo Cikarang. Kue lapis lembut bercorak marmer dengan perpaduan dua karakter rasa yang saling melengkapi. Tanyakan pilihan dan pemesanan… |
| /menu/marble-cake/ | Marble Cake di Cikarang  /  Ruethies Patisserie | Marble Cake di Cikarang  /  Ruethies Patisserie | Perpaduan adonan mentega lembut dan cokelat premium dengan corak marmer yang khas dalam setiap irisan. Ruethies Patisserie di Lippo Cikarang. Konfirmasikan harga dan peme | Marble Cake di Lippo Cikarang. Perpaduan adonan mentega lembut dan cokelat premium dengan corak marmer yang khas dalam setiap irisan. Tanyakan pilihan dan… |
| /menu/jongkong/ | Jongkong di Cikarang  /  Ruethies Patisserie | Jongkong di Cikarang  /  Ruethies Patisserie | Jajanan dalam cup dengan lapisan hijau dan putih. Ruethies Patisserie di Lippo Cikarang. Konfirmasikan harga dan pemesanan melalui WhatsApp. | Jongkong di Lippo Cikarang. Jajanan dalam cup dengan lapisan hijau dan putih. Tanyakan pilihan dan pemesanan melalui WhatsApp. |
| /menu/pastel/ | Pastel Ayam di Cikarang  /  Ruethies Patisserie | Pastel Ayam di Cikarang  /  Ruethies Patisserie | Pastel berkulit renyah dengan isian ragout ayam, wortel, kentang, dan bihun yang gurih. Ruethies Patisserie di Lippo Cikarang. Konfirmasikan harga dan pemesanan melalui W | Pastel Ayam di Lippo Cikarang. Pastel berkulit renyah dengan isian ragout ayam, wortel, kentang, dan bihun yang gurih. Tanyakan pilihan dan pemesanan melalui… |
| /menu/kroket/ | Kroket di Cikarang  /  Ruethies Patisserie | Kroket di Cikarang  /  Ruethies Patisserie | Kroket yang memadukan rasa klasik, tekstur renyah, dan kehangatan dalam setiap gigitan. Ruethies Patisserie di Lippo Cikarang. Konfirmasikan harga dan pemesanan melalui W | Kroket di Lippo Cikarang. Kroket yang memadukan rasa klasik, tekstur renyah, dan kehangatan dalam setiap gigitan. Tanyakan pilihan dan pemesanan melalui… |
| /menu/risol/ | Risol di Cikarang  /  Ruethies Patisserie | Risol di Cikarang  /  Ruethies Patisserie | Risol berbalut renyah keemasan dengan isian lembut dan creamy, untuk menghangatkan suasana. Ruethies Patisserie di Lippo Cikarang. Konfirmasikan harga dan pemesanan melal | Risol di Lippo Cikarang. Risol berbalut renyah keemasan dengan isian lembut dan creamy, untuk menghangatkan suasana. Tanyakan pilihan dan pemesanan melalui… |
| /menu/platter/ | Platter di Cikarang  /  Ruethies Patisserie | Platter Kudapan di Cikarang  /  Ruethies Patisserie | Platter Ruethies di Lippo Cikarang untuk sajian bersama. Lihat foto dan tanyakan isi, porsi, harga serta pemesanan melalui WhatsApp. | Platter di Lippo Cikarang. Pilihan sajian campuran dalam platter. Tanyakan pilihan isi dan kemasannya. Tanyakan pilihan dan pemesanan melalui WhatsApp. |
| /artikel/checklist-memesan-konsumsi-kantor/ | Checklist Memesan Konsumsi Kantor: Apa Saja yang Perlu Disiapkan?  /  Ruethies Patisserie | Checklist Memesan Konsumsi Kantor: Apa Saja yang Perlu Disiapkan?  /  Ruethies Patisserie | Siapkan pesanan konsumsi kantor dengan checklist jumlah tamu, waktu acara, pilihan sajian, anggaran, kebutuhan khusus, dan penerimaan pesanan. | Siapkan pesanan konsumsi kantor dengan checklist jumlah tamu, waktu acara, pilihan sajian, anggaran, kebutuhan khusus, dan penerimaan pesanan. |
| /artikel/cara-memilih-snack-box-untuk-rapat-kantor/ | Cara Memilih Snack Box untuk Rapat Kantor  /  Ruethies Patisserie | Cara Memilih Snack Box untuk Rapat Kantor  /  Ruethies Patisserie | Panduan memilih snack box rapat kantor: jumlah tamu, waktu penyajian, pilihan manis dan gurih, serta hal yang perlu dikonfirmasi sebelum memesan. | Panduan memilih snack box rapat kantor: jumlah tamu, waktu penyajian, pilihan manis dan gurih, serta hal yang perlu dikonfirmasi sebelum memesan. |
| /artikel/office-refreshment-ordering-checklist/ | Office Refreshment Ordering Checklist  /  Ruethies Patisserie | Office Refreshment Ordering Checklist  /  Ruethies Patisserie | Plan office refreshments with a checklist covering guest count, menu preferences, budget, dietary questions, delivery, and invoicing. | Plan office refreshments with a checklist covering guest count, menu preferences, budget, dietary questions, delivery, and invoicing. |
| /artikel/how-to-choose-snack-boxes-for-corporate-meetings/ | How to Choose Snack Boxes for Corporate Meetings  /  Ruethies Patisserie | How to Choose Snack Boxes for Corporate Meetings  /  Ruethies Patisserie | Choose snack boxes for corporate meetings by considering guest count, timing, sweet and savoury options, dietary needs, and order details. | Choose snack boxes for corporate meetings by considering guest count, timing, sweet and savoury options, dietary needs, and order details. |

H1 produk tetap nama produk CMS; H1 halaman umum tetap gaya editorial yang sekarang dengan konteks usaha/lokasi pada teks terlihat. Tidak mengubah slug untuk mengejar keyword. Peta intent/keyword setiap URL ada di [seo-keyword-map.md](seo-keyword-map.md).

## URL, canonical, sitemap dan robots

- Seluruh 20 URL canonical produksi yang ditemukan tetap sama. Katalog `/menu/`, Snackbox `/snack-box-cikarang/`, produk `/menu/{slug}/`, artikel `/artikel/{slug}/`, empat halaman informasi.
- Alias `/menu`, `/index.html` dan alias Snackbox mengikuti redirect 301 yang sudah ada. Tidak menambah rantai redirect. `/menu/lapis-surabaya/` tetap 404 sesuai keputusan pemilik; URL yang berlaku `/menu/lapis-marble/`.
- Sitemap dinamis menyertakan halaman umum dan produk terlihat; artikel hanya ketika flag aktif dan published. Draft, produk tersembunyi, filter, admin, callback, invoice, token dan PO privat tidak dimasukkan. `/artikel/` hanya bila ada artikel published.
- `lastmod` tetap memakai waktu konten, kategori serta renderer yang benar-benar dipakai; tidak diisi tanggal audit secara massal. Ketergantungan Last-Modified HTTP masih cukup luas: perubahan FAQ/menu bisa mengubah header halaman lain. ETag berbasis isi tetap mencegah pemakaian konten lama. Ini keterbatasan presisi timestamp, bukan alasan membuat tanggal palsu.
- Produksi mengizinkan halaman publik; staging tetap `X-Robots-Tag: noindex, nofollow, noarchive` dari aturan host yang ada. Tidak mengubah `.htaccess`, robots, autentikasi atau pengecualian webhook. Respons sukses staging bukan izin pengindeksan.
- Schema tidak menambah Offer, harga, stok, review atau rating. JSON-LD aman untuk konteks HTML. FAQ schema hanya ketika FAQ terlihat. Validasi otomatis memeriksa JSON, referensi entitas, bahasa, canonical dan kecocokan konten; belum ada hasil Rich Results Test dari Google.

## Cara memakai audit CLI

Dari root checkout, gunakan PHP dengan cURL, DOM dan SimpleXML yang sudah tersedia. Simpan laporan **di luar public_html**. Script hanya GET publik, tidak login/POST, tidak mengikuti redirect lintas origin, membatasi 150 halaman dan 3 MiB/respons secara default. Query di luar kategori/pencarian/paginasi yang diizinkan dilewati agar tidak mengakses magic link. Sitemap produksi dipetakan hanya ke origin staging saat audit staging; canonical HTML tetap produksi.

```sh
php tools/seo-audit.php --base=https://ruethiespatisserie.com --output=/tmp/ruethies-seo-main.json --markdown=/tmp/ruethies-seo-main.md --seed=/menu --seed=/index.html
php tools/seo-audit.php --base=https://staging.ruethiespatisserie.com --canonical-origin=https://ruethiespatisserie.com --output=/tmp/ruethies-seo-staging.json --markdown=/tmp/ruethies-seo-staging.md
php tests/seo-audit.php
php tests/seo-metadata.php
```

`--max-pages`, `--delay-ms`, dan `--seed` dapat disesuaikan. Periksa `truncated`, error jaringan dan URL yang dikecualikan sebelum menyimpulkan audit lengkap. Exit 0 berarti laporan berhasil dibuat, **bukan semua SEO lulus**. `issues`, `broken_links`, `duplicate_metadata`, serta `sitemap_urls` perlu ditinjau. Deteksi soft 404 berupa heuristik kata error; bukan keputusan Google. Crawler tidak menguji seluruh file gambar/CSS, state setelah interaksi atau endpoint privat.

## Bukti pengujian

- Crawl awal produksi: 32 URL termasuk utilitas/diagnostik, 20 canonical sitemap, tanpa orphan/duplikasi metadata pada URL canonical. Crawl ulang CLI final: 28 URL (seed utilitas lebih sedikit), tetap 20 sitemap; mendeteksi empat artikel tanpa hreflang dan dua artikel EN ber-HTML ID pada hosting yang belum diperbarui.
- Crawl staging final: 18 URL, 11 sitemap setelah pemetaan origin. Semua HTML mendapat noindex staging. Satu tautan `/artikel/ide-isi-snack-box/` menuju `/menu/platter/` rusak karena Platter tidak ada pada katalog staging saat ini.
- Crawl fixture sesudah: 40 URL, 31 sitemap (artikel demonstrasi lebih banyak), JSON-LD dan hreflang valid, tidak ada duplikasi metadata. Semua halaman menaut ke Pre-order yang berstatus 503 **pada fixture tanpa konfigurasi database**; ini keterbatasan preview. Landing PO hosting 200/noindex dan regresi checkout database terisolasi lulus.
- Regresi PHP lulus: SEO publik (200/301/404/503, ETag, sitemap, CMS menu/FAQ/galeri), kategori termasuk race/revisi/CSRF, artikel termasuk draft/role/editor/upload/terjemahan, keamanan auth/resource/upgrade, routing Apache produksi/staging dan PO, serta 70 pemeriksaan Midtrans Sandbox dengan transport Fonnte/provider tiruan.
- Regresi JavaScript lulus: form WhatsApp dan varian, bahasa artikel, carousel/swipe/reduced motion, scheduler mobile, filter/kategori, quantity, animasi pemesanan, updater staging yang mempertahankan konten hosting. Tidak mengirim WhatsApp atau transaksi sungguhan.
- Browser Chromium terisolasi: delapan halaman × lebar 320/390/768/1440 = 32 pemeriksaan tanpa overflow; bahasa artikel/navigasi menuju URL benar, kartu pasangan, burger/Escape dan fallback EN tanpa JavaScript lulus, tanpa exception runtime.
- Sintaks 84 file PHP dan tiga JS yang berubah diperiksa. PHP lokal 8.5; hosting diketahui 8.3. Database uji lokal MySQL 9.4, sedangkan hosting menggunakan MariaDB 10.5; kompatibilitas hosting tetap memerlukan smoke test. Tidak memakai fitur PHP baru yang membutuhkan 8.5. Smoke test hosting masih diperlukan setelah persetujuan deploy.

### Performa terukur tanpa merusak foto

Perbandingan tiga initial-load beranda lokal sebelum (main baseline) / sesudah (fitur dari staging), katalog sama, foto sama, Chromium sama, viewport 390×844 DPR2, CPU4×, latensi100ms, unduh400000 B/detik, cache dinonaktifkan. Tema staging berbeda dari main sehingga ini pemeriksaan regresi dengan layout/aset sama, bukan benchmark produksi sempurna.

| Ukuran lokal | Sebelum | Sesudah |
|---|---:|---:|
| LCP median | 1484 ms | 1488 ms |
| Akumulasi pergeseran tanpa input selama initial load | 0,00018 | 0,00018 |
| Transfer resource median | 408556 byte | 408556 byte |
| HTML decoded | 33029 byte | 33742 byte |

Tidak ada pengurangan kualitas foto atau library baru. Perbedaan LCP 4 ms tidak diperlakukan sebagai peningkatan ataupun penurunan yang signifikan. Nilai ini **bukan Lighthouse, INP, CrUX atau jaminan Core Web Vitals**. Pengamatan pergeseran initial-load bukan pengukuran seluruh session window CLS lapangan. Respons hosting, jaringan pelanggan, cache dan font perlu diuji lagi pada deployment yang sama. Optimasi gambar agresif tidak diterapkan karena kualitas foto adalah persyaratan pemilik.

## Penerapan dan pemulihan

1. Tinjau commit fitur, uji di staging, lalu push hanya setelah diminta. Main tidak menerima backend PO; perubahan umum dibawa terpisah setelah staging disetujui. Jangan merge seluruh staging ke main.
2. Sebelum pull hosting, simpan commit aktif dan backup kode serta menu/kategori/FAQ/galeri, gambar unggahan, dan direktori privat di luar webroot. Jangan mengganti JSON hosting dengan salinan laptop.
3. Pakai updater staging yang sudah menjaga konten dan proteksi hPanel. Perubahan ini tidak memerlukan CLI migrasi baru atau impor phpMyAdmin; upgrade lama hanya diperlukan bila belum pernah diterapkan.
4. Setelah deploy staging: audit CLI, login/admin edit di akun uji, artikel ID/EN, filter, gambar/carousel, lalu checkout sandbox dan worker Fonnte dengan penerima uji. Pastikan header noindex staging tetap ada.
5. Setelah rilis produksi yang terpisah: cek `/menu` satu hop ke `/menu/`, seluruh sitemap 200, canonical produksi, artikel EN dan hreflang, serta tidak ada X-Robots-Tag noindex produksi. Lakukan Search Console sesuai [seo-monitoring.md](seo-monitoring.md).
6. Rollback kode saja dengan revert commit implementasi yang dipilih, lalu deploy melalui updater dan backup yang sama. Baseline staging `ce1be90` diketahui sebelum pekerjaan ini. Jangan reset hard atau mengembalikan akun/metadata keamanan, pesanan, pembayaran, JSON/gambar CMS dari backup lama. Tidak ada migrasi data untuk dibalik.

## Tindakan manual dan risiko tersisa

- Perbaiki tautan Platter pada artikel staging melalui admin, menuju produk yang benar atau hapus tautan jika tidak lagi relevan. Jangan membuat produk palsu untuk menghilangkan 404.
- Periksa pasangan artikel produksi dari admin; kode hanya menautkan relasi terjemahan yang benar-benar diisi dan published, tidak menerjemahkan isi secara otomatis.
- Pastikan alamat, pin Maps yang saat ini sudah terpasang, jam 07.00–21.00, kontak dan profil sosial sesuai kenyataan bersama pemilik. Tidak mengklaim validasi identitas/GBP dari akses akun yang tidak tersedia.
- Lengkapi rentang tanggal GSC dan rincian nonmerek, cakupan pengantaran, kebutuhan corporate dan ketentuan pesanan hanya jika dikonfirmasi; tidak ditambahkan sebagai fakta website.
- Rich Results Test dan URL Inspection Google perlu akses/pemeriksaan sesudah rilis. Informasi publik memenuhi kelayakan teknis; pengindeksan/peringkat tetap keputusan Google.

## Integrasi main

Perbaikan dibawa secara selektif ke main atas permintaan pemilik. Tema biru–cream, tes main yang sudah ada, data CMS, serta penghapusan lokal `logo.jpeg` milik pengguna dipertahankan. Tidak merge seluruh branch staging. Untuk membatalkan integrasi, revert commit SEO main; jangan memulihkan data akun/keamanan dari versi lama. Regresi main dijalankan ulang terpisah dari laporan staging di atas.

Verifikasi integrasi main: 374 pemeriksaan PHP dalam enam suite (SEO publik, artikel, kategori HTTP, routing Apache, metadata dan crawler) lulus; tes JavaScript bahasa artikel, form WhatsApp/varian dan carousel juga lulus. Tidak ada error sintaks PHP pada file implementasi/tes yang berubah.

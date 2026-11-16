# Animasi mobile — Fase A (staging)

Status: implementasi Fase A saja. Hentikan pekerjaan sebelum Fase B/C sampai pemilik selesai menguji. Main dan produksi tidak diubah.

## Modul dan integrasi

| Efek | File | Perilaku |
|---|---|---|
| A1 Header | `mobile-header.js`, `mobile-header.css` | Mobile <=760px: arah scroll dengan ambang 8px; tampil dekat atas, saat navigasi terbuka atau fokus berada di header. Reduced motion mempertahankan header. |
| A2 WhatsApp | `mobile-whatsapp.js`, `mobile-whatsapp.css` | Menyempurnakan bar lama menjadi satu CTA, bukan membuat bar kedua. Setelah CTA hero lewat, sembunyi saat Lokasi/footer/form pesanan terlihat. Ruang bawah dan safe area disediakan sejak CSS awal. |
| A3 Baca per kata | `mobile-reading.js`, `mobile-reading.css` | Paragraf pengenalan tetap tersedia utuh untuk pembaca layar; salinan visual dekoratif berubah melalui opacity lapisan tinta. Teks pudar tetap berkontras AA, termasuk ukuran normal. |
| A4 Pilihan produk | `mobile-favourites.js`, `mobile-favourites.css` | Native horizontal scroll-snap di mobile, titik 44px, navigasi keyboard; grid di tablet/desktop >760px. Foto samping memudar, teks tetap terbaca. Tidak ada autoplay atau gambar baru. |
| A5 Jam/lokasi | `mobile-hours.js`, `mobile-hours.css` | Pill footer dan Lokasi memakai Intl Asia/Jakarta. Buka 07.00 inklusif hingga sebelum 21.00, satu konstanta jam. Pin di tautan lokasi beranimasi sekali. |

`mobile-motion.js` menyediakan satu passive scroll listener, scheduler rAF dan satu IntersectionObserver untuk semua modul Fase A. Beberapa modul dapat mengamati node sama. Observer animasi tiga langkah tetap terpisah dan kodenya tidak diubah.

`public-lib/profile.php` memuat pasangan aset per efek. A3/A4 hanya beranda; modul lain memakai shell halaman publik. `tools/preview-profile.php` menyalin aset ke fixture preview. Checkout PO mandiri beserta pembayaran/notifikasi tetap tidak diubah.

Untuk mematikan satu efek, hapus hanya pemuatan JS/CSS pasangan efek tersebut dari shell. Pertahankan runtime bersama jika efek lain masih digunakan. Data CMS, foto, heading, SEO, tujuan URL dan nomor WhatsApp tidak diubah. A2 tetap menggunakan tautan produk/draft yang diperbarui `profile.js`, bukan menimpa pesannya sendiri.

## Pengujian lokal 16 November 2026

Browser Chrome terisolasi, viewport 360/390/768/1440 x 844, CPU throttling 4x:

- Tidak ada overflow horizontal halaman; scroll carousel hanya di dalam track.
- Scroll turun/naik, burger terbuka, Lokasi dan footer, pilihan titik carousel, ID/EN, reduced motion, dan JavaScript nonaktif diuji.
- Kontrol carousel/tombol berfungsi dengan tautan produk yang sama. Tanpa JavaScript, carousel tetap dapat digeser secara native; status jam statis dan tautan WhatsApp tetap ada.
- Tidak ada runtime exception. Pada pengukuran awal mobile tiap halaman, CLS 0 dan tidak ada long task >=50ms yang tercatat. Sampel desktop beranda mencatat pergeseran font kecil sekitar 0,00020.
- Lighthouse 13.6.0 mobile, satu run per versi pada fixture yang sama dengan cache dingin, konfigurasi default yang sama:

| Metrik beranda | Sebelum | Sesudah |
|---|---:|---:|
| Performance | 94 | 93 |
| Accessibility | 100 | 100 |
| LCP | 3,001 s | 3,078 s |
| CLS | 0,000256 | 0,000177 |
| TBT | 0 ms | 0 ms |

Ini pengukuran laboratorium lokal, bukan skor hosting atau data pengguna nyata. Selisih satu poin bukan jaminan skor tetap sama setelah deploy. Foto dan font tetap sama. Total tambahan enam file JavaScript sekitar 3,9 KiB gzip (penjumlahan gzip tiap file), di bawah anggaran 10 KiB. Tidak ada dependency runtime baru.

Regresi: `node tests/mobile-motion.cjs`, `node tests/order-story.cjs`, `node tests/profile-form.cjs`, `node tests/product-carousel.cjs`, `php tests/staging-legacy.php`, `php tests/seo-public.php`, dan `php tests/seo-routing.php`.

Hasil Lighthouse mentah dan laporan browser disimpan sebagai artefak sementara lokal `/private/tmp/phase-a-before.lighthouse.json`, `/private/tmp/phase-a-after.lighthouse.json`, `/private/tmp/phase-a-results.json`. Bukan konten untuk hosting.

## Uji di hosting staging setelah pull

1. Beranda pada 360/390px: scroll ke bawah lebih dari 8px, lalu naik. Header harus kembali; burger/fokus keyboard mempertahankan header.
2. Saat tombol hero masih terlihat, bar bawah tersembunyi. Setelah lewat, bar tampil. Saat Lokasi/footer masuk layar, bar tersembunyi; kembali ke atas harus memulihkannya sesuai posisi.
3. Geser Sajian pilihan kami, tekan setiap titik, buka foto/nama/detail: tujuan tetap halaman produk, bukan WhatsApp. Di 768/1440px kembali menjadi grid.
4. Scroll paragraf Untuk dinikmati bersama ke atas/bawah. Ubah ID/EN: teks utuh, tidak bertumpuk atau tercampur.
5. Periksa status WIB pada pill Lokasi/footer. Buka pukul 07.00–20.59, tutup mulai 21.00. Tombol lokasi dan rute lama tetap bekerja.
6. Buka katalog, Snack Box dan detail Brownies langsung, refresh saat scroll di tengah. Di produk, bar tidak menimpa form; tautan WA tetap memuat nama/jumlah/tanggal/catatan dari form lama.
7. Aktifkan reduced motion: header stabil, tidak ada reveal/denyut/bounce/scale/transisi panjang. Matikan JavaScript: navigasi, konten, native carousel, dan tautan WA tetap tersedia.
8. Uji Safari/iPhone sebenarnya dengan safe area. Hasil laboratorium Chrome tidak menggantikan uji perangkat ini.

## Konflik dan keputusan yang perlu diketahui

- Dokumen tugas menyebut FAQ 09.00–21.00, tetapi FAQ di repo saat pengerjaan sudah 07.00–21.00. FAQ hosting tidak ditulis ulang atau diasumsikan sama. Konfirmasi kepada pemilik bila data hosting berbeda; TODO ada di modul jam. Jam dinamis sementara 07.00–21.00 sesuai instruksi.
- Pin beranda ditempatkan dekat tautan **Lihat lokasi & kontak** yang benar-benar ada, tanpa mengganti label/URL menjadi rute baru.
- Opacity kartu samping diterapkan pada foto, bukan seluruh teks, untuk menjaga keterbacaan. Scale .94 dipakai sebagai efek kedalaman yang halus.
- Header awal memanfaatkan media query `scripting: enabled` agar navigasi fallback tidak mendorong konten saat skrip deferred selesai. Jika browser lama tidak mendukung query ini, fallback navigasi lama tetap berlaku.
- Konflik dua pengamat pada section Lokasi telah diperbaiki dengan subscriber bersama. Pergeseran inisialisasi carousel dicegah dengan layout native dari HTML/CSS awal dan ruang titik yang sudah disediakan.
- Tidak ada konflik langsung yang memerlukan perubahan pada `order-story.js` atau `order-story.css`.
- Belum ada stepper atau perubahan filter; keputusan Fase C menunggu fase berikutnya.

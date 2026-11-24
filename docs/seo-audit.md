# Audit SEO Ruethies Patisserie

Tanggal: 24 November 2026 (WIB). Baseline main `021e23a`; staging `ce1be90`. Audit dilakukan sebelum perubahan kode aplikasi. Tidak ada deploy atau perubahan data hosting.

## Arsitektur dan lingkup

PHP native SSR melalui `index.php`, rewrite Apache/LiteSpeed `.htaccess`; menu/FAQ/galeri/kategori JSON hosting, artikel JSON privat di luar webroot. Editor owner/staff memakai autentikasi, CSRF, lock, backup, dan revisi yang sudah ada. Main tidak memiliki checkout/database PO. Staging mempunyai MariaDB PO, Midtrans Sandbox, dan antrean Fonnte; semuanya tetap terpisah. Tidak ada build/package manager wajib.

URL produk stabil `/menu/{slug}/`, Snackbox `/snack-box-cikarang/`; halaman informasi `/tentang-kami/`, `/hubungi-kami/`, `/cara-pesan/`; artikel `/artikel/{slug}/`. ID/EN untuk halaman umum memakai satu URL dengan peningkatan JS, bukan versi Inggris indexable tersendiri. Artikel diterjemahkan memakai dua URL dan relasi eksplisit. Kategori adalah parameter utilitas noindex, bukan landing SEO.

## Metode dan keterbatasan

Crawler baca-saja menelusuri tautan HTML awal + sitemap, filter kategori, pagination, serta beberapa seed diagnostik. Maksimal 150 URL produksi/120 staging; jeda 200 ms; tanpa login, checkout, form POST, token, atau traffic sintetis pencarian. Produksi selesai tanpa terpotong: 32 URL, 20 URL sitemap. Staging awal 18 URL; URL sitemap ber-domain produksi perlu dipetakan ke origin staging untuk pemeriksaan yang benar.

Snapshot laporan JSON berada di `/private/tmp/seo-production-before.json` dan `/private/tmp/seo-staging-before.json`; bukan data GSC real-time. Status/indexability di laporan adalah kelayakan HTTP/robots, bukan bukti indeks Google. Tidak ada akses akun GSC/GBP, CrUX, atau validasi interaktif Rich Results Test.

## Baseline GSC dari pemilik

39 klik, 254 impressions, CTR 15,4%, posisi rata-rata 6,5. Rentang tanggal dan rincian per-query baru belum disertakan. Tidak ditafsirkan sebagai ranking setiap kata kunci. Pisahkan merek/nonmerek sebelum evaluasi.

## Inventaris produksi (respons awal)

| URL | HTTP akhir | Title saat audit | Bahasa HTML | Kelayakan |
|---|---:|---|---|---|
| / | 200 | Toko Kue & Snack Box Lippo Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/ | 200 | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /snack-box-cikarang/ | 200 | Pesan Snack Box Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /tentang-kami/ | 200 | Cerita di balik Ruethies  /  Ruethies Patisserie | id | indexable secara teknis |
| /hubungi-kami/ | 200 | Kunjungi & hubungi Ruethies  /  Ruethies Patisserie | id | indexable secara teknis |
| /cara-pesan/ | 200 | Cara pesan di Ruethies  /  Ruethies Patisserie | id | indexable secara teknis |
| /artikel/ | 200 | Artikel Ruethies  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu | 200 via 301 | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | id | alias / noindex / error |
| /index.html | 200 via 301 | Toko Kue & Snack Box Lippo Cikarang  /  Ruethies Patisserie | id | alias / noindex / error |
| /menu/lapis-surabaya/ | 404 | Halaman tidak ditemukan — Ruethies Patisserie Cikarang | id | alias / noindex / error |
| /menu/seo-audit-missing-product/ | 404 | Halaman tidak ditemukan — Ruethies Patisserie Cikarang | id | alias / noindex / error |
| /menu/?q=risol | 200 | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | id | alias / noindex / error |
| /menu/bolu-jadoel/ | 200 | Bolu Jadoel di Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/brownies-cokelat/ | 200 | Brownies Cokelat di Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/lapis-marble/ | 200 | Lapis Marble di Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/marble-cake/ | 200 | Marble Cake di Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/jongkong/ | 200 | Jongkong di Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/pastel/ | 200 | Pastel Ayam di Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/kroket/ | 200 | Kroket di Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/risol/ | 200 | Risol di Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/platter/ | 200 | Platter di Cikarang  /  Ruethies Patisserie | id | indexable secara teknis |
| /artikel/checklist-memesan-konsumsi-kantor/ | 200 | Checklist Memesan Konsumsi Kantor: Apa Saja yang Perlu Disiapkan?  /  Ruethies Patisserie | id | indexable secara teknis |
| /artikel/cara-memilih-snack-box-untuk-rapat-kantor/ | 200 | Cara Memilih Snack Box untuk Rapat Kantor  /  Ruethies Patisserie | id | indexable secara teknis |
| /artikel/office-refreshment-ordering-checklist/ | 200 | Office Refreshment Ordering Checklist  /  Ruethies Patisserie | id | indexable secara teknis |
| /artikel/how-to-choose-snack-boxes-for-corporate-meetings/ | 200 | How to Choose Snack Boxes for Corporate Meetings  /  Ruethies Patisserie | id | indexable secara teknis |
| /menu/?kategori=cakery | 200 | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | id | alias / noindex / error |
| /menu/?kategori=savoury | 200 | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | id | alias / noindex / error |
| /menu/?kategori=packages | 200 | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | id | alias / noindex / error |
| /artikel/?kategori=panduan | 200 | Artikel Ruethies  /  Ruethies Patisserie | id | alias / noindex / error |
| /menu/?kategori=cakery&q=risol | 200 | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | id | alias / noindex / error |
| /menu/?kategori=savoury&q=risol | 200 | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | id | alias / noindex / error |
| /menu/?kategori=packages&q=risol | 200 | Menu Kue & Snack Box Cikarang  /  Ruethies Patisserie | id | alias / noindex / error |

## Temuan prioritas

1. **P1 — bahasa artikel:** dua artikel EN dikirim dengan `<html lang="id">`, `og:type=website`, dan tanpa hreflang head. Isi artikel sudah SSR dan canonical terpisah; pertahankan keduanya, perbaiki bahasa server dan alternate reciprocal hanya pasangan terbit.
2. **P1 — staging tertinggal fitur umum:** renderer kartu terjemahan dan konteks lokal terbaru belum sama dengan main; jangan menimpa navigasi atau flow PO ketika menyamakan komponen publik.
3. **P2 — metadata informasi:** judul Tentang/Lokasi/Cara Pesan kurang menyebut konteks bisnis/lokasi. Deskripsi produk dibentuk terpusat agar mengikuti katalog admin, tanpa klaim harga/stok. Home sudah memiliki fokus toko kue Lippo Cikarang; pertahankan arahnya.
4. **P2 — schema:** Bakery sudah dipakai dan menu sudah `/menu/`; Menu/FAQ/Breadcrumb umumnya valid JSON. ID entitas dan relasi publisher/about belum konsisten pada semua halaman. `areaServed` regional berasal dari template lama dan belum membuktikan jangkauan antar; hapus klaim cakupan sampai pemilik mengonfirmasi. Alamat schema perlu disamakan dengan HTML toko.
5. **P2 — tautan staging:** artikel `ide-isi-snack-box` memuat `/menu/platter/` yang 404 karena katalog staging hanya 3 produk (Snackbox, Brownies Cokelat, Pastel Ayam). Jangan membuat produk palsu atau mengubah artikel hosting diam-diam; beri tindakan manual untuk editor.
6. **P2 — pemantauan:** belum ada crawler CLI rekursif untuk mendeteksi metadata, schema, canonical, sitemap, tautan putus, dan orphan dalam satu laporan.
7. **P3 — 404:** link “Lihat Menu” masih memakai `/#menu`, sebaiknya langsung `/menu/`. Tidak perlu canonical/rich metadata untuk halaman error noindex.
8. **P3 — lastmod:** timestamp untuk semua halaman terlalu dipengaruhi file yang tidak relevan. Pertahankan tanggal konten sebenarnya dan timestamp renderer bila isi/metadata berubah; hindari tanggal audit sebagai lastmod.

## Yang sudah benar dan dipertahankan

- Nama, deskripsi, produk, tautan, FAQ dan JSON-LD tersedia dalam HTML awal.
- 10 produk produksi terlihat dan ada pada sitemap; seluruh URL canonical HTTP 200. Tidak ditemukan duplikasi title/description di antara URL canonical indexable.
- `/menu` dan `/index.html` masing-masing satu redirect 301 ke URL akhir. Produk tak dikenal dan Lapis Surabaya lama 404, sesuai keputusan pemilik; tidak dihidupkan kembali.
- Filter kategori/pencarian noindex dengan canonical katalog; tidak dibuat landing duplikat.
- Staging memiliki `X-Robots-Tag: noindex, nofollow, noarchive`; produksi tidak mendapat header itu. Proteksi admin dan jalur staging tetap.
- Foto detail lazy sesuai slide, hero prioritas, map menunggu klik, tanpa library tambahan. Kualitas foto tidak diturunkan.

## Kategori dan data hosting

Produksi: Kue & Jajanan (`cakery`), Camilan Gurih (`savoury`), Paket & Sajian (`packages`), Minuman (`category-ebaf53289cbe624b`).

Staging memakai slug Pastel Ayam `/menu/pastel-ayam/`, sedangkan produksi `/menu/pastel/`. Perbedaan ini adalah data hosting; tidak diubah massal.

## Rekomendasi rilis

Implementasi dan regression test dilakukan pada branch fitur dari staging. Tidak deploy langsung, tidak mengubah JSON/akun/rahasia hosting. Tidak membuat halaman café/kopi/pastry/corporate tambahan sebelum ada data bisnis dan isi yang cukup. Perbaiki metadata dinamis, entitas, bahasa artikel, tautan kategori produk, audit CLI, dan dokumentasi terlebih dahulu.

## Verifikasi crawler final

Crawler permanen memetakan URL sitemap canonical produksi ke origin staging hanya untuk pengujian: staging mempunyai **11 URL sitemap**, berbeda dari 20 produksi karena katalog/artikel hosting berbeda. Crawl produksi ulang (28 URL, utilitas diagnostik lebih sedikit daripada baseline 32) memperjelas empat pasangan artikel tanpa hreflang dan dua artikel EN dengan HTML awal ID. Perbandingan metadata lengkap, hasil sesudah, serta keterbatasan timestamp/performa ada di [seo-implementation.md](seo-implementation.md). Tidak ada perubahan katalog hosting atau klaim indeks Google dari crawler.

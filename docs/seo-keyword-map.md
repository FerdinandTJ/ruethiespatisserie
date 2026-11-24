# Peta kata kunci dan intent

Pemetaan editorial berdasarkan katalog/halaman publik 24 November 2026; bukan estimasi volume atau ranking. Bahasa utama Indonesia. Lokasi usaha Lippo Cikarang, Cikarang Selatan. Tidak menyatakan jangkauan pengiriman.

| Halaman canonical | Intent utama | Kata kunci utama | Pendukung yang relevan |
|---|---|---|---|
| / | menemukan toko dan merek | toko kue Lippo Cikarang | Ruethies Patisserie, patisserie Cikarang, toko kue di Lippo Cikarang, dessert Cikarang |
| /menu/ | melihat seluruh pilihan | menu Ruethies Patisserie | menu kue Cikarang, camilan gurih Cikarang, kue dan jajanan pasar |
| /snack-box-cikarang/ | konsultasi dan memesan paket kotak | snack box Cikarang | snackbox Lippo Cikarang, snack box rapat kantor, snack box acara, pesan snack box |
| /tentang-kami/ | mengenal usaha dan sajian | Ruethies Patisserie Cikarang | Ruethies Lippo Cikarang, patisserie Lippo Cikarang |
| /hubungi-kami/ | alamat dan menghubungi toko | lokasi Ruethies Patisserie | alamat toko kue Lippo Cikarang, jam buka Ruethies, Ruko Neo Patio East No. 51 |
| /cara-pesan/ | panduan transaksi WhatsApp | cara pesan Ruethies Patisserie | pesan kue Cikarang, konfirmasi snack box, pemesanan melalui WhatsApp |
| /artikel/ | mencari panduan terkait sajian | panduan pemesanan Ruethies | tips memilih snack box, ide acara, panduan kue |
| /menu/bolu-jadoel/ | mengenal dan memesan produk | bolu jadoel Cikarang | pesan bolu jadoel, kue Lippo Cikarang |
| /menu/brownies-cokelat/ | mengenal dan memesan produk | brownies cokelat Cikarang | pesan brownies cokelat, kue Lippo Cikarang |
| /menu/lapis-marble/ | mengenal dan memesan produk | lapis marble Cikarang | pesan lapis marble, kue Lippo Cikarang |
| /menu/marble-cake/ | mengenal dan memesan produk | marble cake Cikarang | pesan marble cake, kue Lippo Cikarang |
| /menu/jongkong/ | mengenal dan memesan produk | jongkong Cikarang | kue jongkong Cikarang, jajanan pasar Cikarang |
| /menu/pastel/ | mengenal dan memesan produk | pastel ayam Cikarang | pastel ayam Cikarang, camilan gurih Cikarang |
| /menu/kroket/ | mengenal dan memesan produk | kroket Cikarang | pesan kroket, kue Lippo Cikarang |
| /menu/risol/ | mengenal dan memesan produk | risol Cikarang | risoles Cikarang, camilan gurih Lippo Cikarang |
| /menu/platter/ | mengenal dan memesan produk | platter Cikarang | platter kudapan Cikarang, sajian untuk berbagi |
| /artikel/checklist-memesan-konsumsi-kantor/ | panduan persiapan, bukan landing transaksi | checklist memesan konsumsi kantor | jumlah tamu, tanggal kebutuhan, pilihan sajian, konfirmasi pemesanan |
| /artikel/cara-memilih-snack-box-untuk-rapat-kantor/ | panduan persiapan, bukan landing transaksi | cara memilih snack box rapat kantor | jumlah tamu, tanggal kebutuhan, pilihan sajian, konfirmasi pemesanan |
| /artikel/office-refreshment-ordering-checklist/ | panduan persiapan, bukan landing transaksi | office refreshment ordering checklist | meeting brief, corporate refreshments |
| /artikel/how-to-choose-snack-boxes-for-corporate-meetings/ | panduan persiapan, bukan landing transaksi | how to choose corporate meeting snack boxes | meeting brief, corporate refreshments |

## Kategori dinamis

Kategori `cakery` (Kue & Jajanan), `savoury` (Camilan Gurih), dan `packages` (Paket & Sajian) memandu discovery pada katalog. Parameter `?kategori=` tetap utilitas noindex dengan canonical `/menu/`; tidak ditargetkan sebagai landing terpisah. Produk baru/berganti nama mengikuti data admin untuk metadata; slug dan ID tidak diubah. Kategori coffee staging lama didukung bila ada, tetapi tidak menjadi klaim produksi saat tidak ada produknya.

## Kata kunci yang ditunda / ditolak

- Café, café aesthetic, coffee shop, kopi, matcha, reservasi: belum ada produk/fasilitas tersebut di katalog produksi yang diaudit. Jangan memakai contoh judul dalam brief secara harfiah.
- Croissant, macarons, cheesecake, opera cake, éclair: tidak ada pada katalog produksi; query yang pernah muncul bukan bukti produk dijual.
- Risol mayo: isi belum dikonfirmasi; gunakan risol/risoles tanpa mengarang varian.
- Premium: hanya gunakan bila copy bisnis yang ditinjau benar-benar menjelaskan maknanya; bukan sisipan kata kunci.
- “Terdekat” diperlakukan sebagai intent lokal Google/Maps, bukan frasa wajib berulang.
- Bekasi adalah wilayah alamat toko, bukan janji pengiriman seluruh Bekasi.
- PO otomatis hanya staging; main tetap pesan WhatsApp. Tidak membuat landing pre-order seolah checkout produksi sudah tersedia.
- “Soto” dan query tidak relevan diabaikan.

## Keputusan arsitektur

Tidak membuat `/coffee/`, `/pastry/`, `/corporate-order/`, `/cakery/`, atau halaman daerah baru pada rilis ini. Katalog + detail produk + Snackbox + artikel faktual sudah memenuhi intent yang diketahui tanpa halaman tipis/duplikat. Halaman corporate tersendiri dievaluasi lagi jika layanan, pilihan, proses, dan informasi khususnya sudah cukup dan berbeda dari Snackbox.

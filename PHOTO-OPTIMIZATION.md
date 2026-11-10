# Foto katalog dan detail

Beranda tetap memakai thumbnail lama; tidak ada preload untuk foto detail. Snapshot katalog hanya memuat URL, bukan mengunduh gambar. Saat detail dibuka, browser memilih varian WebP dengan srcset/sizes sesuai lebar layar dan DPR. Hanya foto yang dipilih dimuat. Halaman produk penuh juga memakai sumber responsif; foto tambahan tetap lazy-loaded.

Untuk 10 foto utama bawaan, varian dibuat ulang dari foto pilihan Hasil Sortir, bukan memperbesar thumbnail. Varian berakhiran -detail.webp memiliki sisi panjang maksimal 800 px, -full.webp maksimal 1600 px, WebP kualitas 82, rasio asli, tanpa upscale. Kedua varian hanya ditambahkan sebagai aset; menu.json hosting tidak ditimpa.

Unggahan admin baru membuat thumbnail dan kedua varian dari satu decode sumber, dengan validasi ukuran/dimensi dan pembatasan upload yang tetap berlaku. Nama file unik memungkinkan cache lama tetap aman. Penghapusan foto yang tidak lagi dirujuk turut membersihkan kedua varian. Mengganti nama atau menyembunyikan produk tidak membuang fotonya.

Foto hosting lama yang memakai nama upload sendiri tetap berfungsi dengan fallback. Jika varian resolusi tinggi belum ada, unggah ulang foto asli melalui editor menu. Foto 640 px yang sudah diperkecil tidak dapat dipulihkan ketajamannya dengan upscale. Foto sumber yang buram juga tetap buram.

## Penerapan

Backup konten hosting seperti panduan SEO-DEPLOYMENT.md, lalu pull main dengan umask 022. Tidak perlu CLI upgrade baru atau impor database. Jika pull menolak karena index.html sudah diperbarui CMS, simpan dan tinjau perubahan hosting terlebih dahulu; jangan reset --hard dan jangan menimpa JSON hosting.

## Verifikasi

- Tes galeri, fallback gambar gagal, sumber responsif dan penutupan modal.
- Tes upload membuat thumbnail 640 px dan varian 800/1600 px dari sumber 1920 px; upload detail mempertahankan rasio.
- Tes penghapusan varian, referensi bersama, validasi gambar dan regresi HTTP/admin.
- Browser lokal pada viewport 390 px, DPR 3: tidak ada permintaan foto -detail/-full saat pemuatan awal, baru meminta foto produk yang dibuka. Halaman produk menawarkan srcset resolusi tinggi.
- Pengujian browser menggunakan Chrome terisolasi, belum Safari perangkat nyata.

## Galeri Suasana — 11 November 2026

Detail Suasana memakai panel beige, foto utuh, tombol tutup dan navigasi yang sama dengan detail produk. Foto sementara adalah 01_Utama dari Hasil Sortir: Snackbox IMG_2061, Platter IMG_1821, dan Marble Cake IMG_1678. Galeri memakai thumbnail dan varian -full.webp yang sudah tersedia; versi penuh hanya dimuat ketika detail dibuka. Caption ID/EN dan foto tetap dapat diubah melalui admin Galeri.

Saat menerapkan penggantian tiga foto ini di hosting, backup atmosphere.json hosting sebelum pull. Jika prosedur deployment memulihkan seluruh JSON hosting, terapkan atmosphere.json dari commit baru sesudahnya (git show HEAD:atmosphere.json > atmosphere.json). Penggantian ini hanya untuk galeri yang diminta; jangan menimpa menu.json atau faq.json hosting.

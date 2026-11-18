# Perapian jarak halaman — 18 November 2026

Main dan staging memakai jarak antarbagiannya yang sama. Foto, isi CMS, URL, metadata SEO, nomor WhatsApp, akun dan alur PO tidak diubah.

## Yang diperbaiki

- Footer baru memiliki margin atas eksplisit nol; tidak lagi mewarisi margin 40px desain lama.
- Padding section mengikuti satu ukuran: 28–36px desktop, 24px HP. Batas bagian tidak lagi menumpuk 100px + 100px.
- Akhir halaman artikel memakai padding 28px desktop/24px HP, bukan 130px. Section terakhir tidak menambah padding bawah lagi.
- Jarak breadcrumb, judul halaman, ajakan penutup dan tautan kembali ke katalog diringkas. Ruang aman bilah WhatsApp di bawah dokumen tetap tersedia, termasuk area aman layar HP.
- Storytelling memakai jarak gulir yang lebih singkat dan akhir langkah yang mengikuti isi. Pada desktop pendek, ruang akhir dihitung dari tinggi kartu/header supaya kartu terakhir tidak tertutup header.
- Preferensi kurangi gerakan menampilkan tiga langkah sebagai bacaan ringkas; ilustrasi dekoratif dan jarak gulir buatan tidak diperlukan. Mengubah preferensi ketika halaman terbuka langsung memperbarui tata letak.
- Kategori bertumpuk khusus staging tetap memakai sticky/scale. Gap kini dibatasi 24–64px, tinggi minimum kartu 140px; pada reduced motion, margin dan gap tidak ditumpuk.
- Versi URL stylesheet/script diperbarui untuk revalidasi browser.

## Hasil perbandingan lokal

Pada fixture yang sama, tinggi viewport 844px. Angka mengukur jarak visual antarkonten, bukan satu padding tunggal; isi hosting berbeda dapat mengubah hasil.

| Bagian | HP 390px sebelum → sesudah | Desktop 1440px sebelum → sesudah |
| --- | --- | --- |
| Artikel terakhir ke isi footer pada detail artikel | 304 → 94px | 368 → 104px |
| Akhir storytelling ke bagian berikutnya | 242 → 83px | 415 → 103px |
| Isi ajakan penutup Cara Pesan ke isi footer | 187 → 93px | 225 → 105px |
| Isi ajakan penutup Tentang Kami ke isi footer | 187 → 93px | 225 → 105px |

## Verifikasi

- Audit ulang 346 kombinasi halaman/ukuran layar pada fixture main dan staging; lebar 320/390/768/1440px. Tidak ditemukan overflow horizontal atau exception JavaScript.
- Alur baca artikel, semua sepuluh halaman produk, katalog/filter/pencarian kosong, halaman informasi, admin dan tampilan PO contoh diperiksa.
- Pemeriksaan tambahan: ID/EN, FAQ dibuka, perpindahan animasi 1→2→3→2→1 pada HP/tablet/desktop termasuk tinggi 700px, reduced motion dan pergantian preferensi langsung, serta fallback tanpa JavaScript. Sampul artikel juga diperiksa pada keempat ukuran; foto tetap utuh dan tidak meluber.
- Tes regresi `tests/seo-public.php`, `tests/order-story.cjs`, `tests/profile-form.cjs`, `tests/product-carousel.cjs`, serta `tests/profile-preview.cjs`. Staging juga menjalankan `tests/mobile-phase-b.cjs` dan `tests/mobile-motion.cjs`.
- Browser pengujian Chromium terisolasi. Pengukuran ini tidak menyatakan hasil Safari sudah diuji langsung. Transaksi/WA nyata tidak dilakukan; sumber konten hosting tidak ditimpa.

## Deployment

Tidak ada migrasi database atau CLI upgrade khusus perapian jarak. Gunakan prosedur backup/pull biasa yang mempertahankan konten hosting. PO tetap khusus staging. Periksa halaman artikel dan beranda setelah deployment; versi asset baru membantu browser mengambil CSS/JS terbaru.

## Penyelarasan animasi publik ke main

Stacking kategori dan modul publik tambahan kini juga dibawa ke main; lihat [PUBLIC-MOTION.md](PUBLIC-MOTION.md). Gap kategori tetap dibatasi 24–64px. Tabel perbandingan di atas mencatat rilis perapian jarak sebelum penyelarasan ini.

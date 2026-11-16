# Animasi mobile — Fase B (staging)

Fase B menambahkan dua efek pada beranda saja. Fase C belum dikerjakan; lanjut setelah pemilik menguji. Produksi/main, CMS, data kategori/menu, foto, UID/slug dan flow PO tidak diubah.

## B1 — Kartu kategori menumpuk

- File: `mobile-category-stack.js`, `mobile-category-stack.css`.
- Berlaku pada `#menu .rp-categories`, yaitu bagian **Manis, gurih, untuk berbagi.**
- Kartu tetap merupakan tautan kategori asli dari admin, mengikuti nama ID/EN, jumlah, ID dan urutannya. Tidak ada kategori yang dibuat atau dikunci dalam JavaScript.
- Mobile <=760px dengan tinggi >=600px: kartu sticky dengan jarak atas bertingkat, permukaan krem/krem tua/wine, dan scale sekitar .94/.90 pada kartu di bawahnya. Kartu terakhir tetap skala 1.
- Tablet/desktop >760px tetap grid biasa. Layar pendek memakai susunan semula. Reduced motion memakai susunan statis tanpa sticky/scale.
- Layout ditetapkan sejak CSS awal. JavaScript hanya menulis transform melalui custom property; pembacaan geometri dibatch sebelum penulisan. Kartu yang difokuskan muncul di atas tumpukan dan memiliki outline.
- Jarak antarkartu menggunakan flex gap, agar margin bawah tidak memaksa kartu keluar sebelum tumpukan terbentuk. Tingkat top dibatasi untuk kategori ke-5 dan seterusnya agar daftar panjang tidak mendorong kartu keluar viewport.

## B2 — Revisi 17 November 2026: wine lokal

- File aktif: `mobile-section-themes.css`. Skrip `mobile-section-themes.js` dihapus karena halaman tidak lagi mengganti tema global saat scroll.
- Latar wine hanya untuk bagian **Untuk dinikmati bersama.**, dengan teks krem dan reveal kata yang tetap berkontras AA.
- Header dan latar halaman tetap krem. Logo tidak memakai kotak krem tambahan.
- Lokasi kembali memakai section bawaan dengan latar lembut, tanpa panel besar membulat atau lapisan wine di belakangnya.
- Warna lokal ditentukan CSS sejak HTML awal, sehingga tetap bekerja tanpa JavaScript dan dengan reduced motion. Tidak menambah observer/scroll listener.
- Kategori menumpuk, tiga langkah, bar WhatsApp, carousel, stepper dan filter tidak diubah oleh revisi ini.
- Kode/warna `order-story.js`, `order-story.css`, dan bagian `#info` tetap dipertahankan.

## Integrasi dan mematikan efek

Shell memuat pasangan CSS/JS B1 dan CSS lokal B2 hanya di beranda. Preview dan fixture HTTP menyediakan aset aktif yang sama. Untuk menonaktifkan B1, hapus pemuatan pasangannya; untuk menonaktifkan warna lokal B2, hapus pemuatan stylesheet-nya. Data/tautan kategori tetap tersedia.

## Pemeriksaan versi awal, sebelum revisi 17 November

- Browser Chrome terisolasi, CPU throttling 4x, lebar 360, 390, 768 dan 1440, tinggi 844 piksel.
- Sticky stack, fokus kategori, pergantian tema maju/mundur, ID/EN, refresh di section wine, kembali ke atas, reduced motion dan JavaScript nonaktif lulus.
- Tidak ada overflow halaman atau runtime exception. Sampel scroll kategori mobile mencatat CLS 0 dan tidak ada long task >=50ms.
- Warna aktual DOM diperiksa: header wine `#7A0000`, teks krem `#FFFDF9`, teks pudar `#EAD9CC`, kartu tiga langkah tetap `#FAF6F0`. Pasangan palet diuji terhadap kontras AA 4,5:1, termasuk teks normal.
- `node tests/mobile-phase-b.cjs`: jumlah/urutan kategori dinamis, reversibilitas scale/tema, fokus, reduced motion, dan kontras palet.
- Regresi lulus: `node tests/mobile-motion.cjs`, `node tests/order-story.cjs`, `node tests/profile-form.cjs`, `node tests/product-carousel.cjs`, `php tests/seo-public.php`, `php tests/staging-legacy.php`.
- Pengukuran Lighthouse sebelum/sesudah dicatat di bawah. Ini hasil lab lokal, bukan skor hosting atau data pengguna nyata. Uji Safari/iPhone asli tetap diperlukan setelah pull.

## Uji di staging setelah pull

1. Pada 360/390px, scroll kategori: tumpukan tetap bekerja. Pada 768/1440px, kategori tetap grid.
2. Scroll ke pengenalan: wine hanya pada section tersebut, teks krem tetap terbaca dan reveal per kata tetap bekerja.
3. Header harus tetap krem saat muncul, tanpa kotak baru di belakang logo. Latar halaman di luar pengenalan tidak berubah wine.
4. Lokasi harus berbentuk section biasa dengan sudut lurus. Scroll balik ke tiga langkah: warna/animasi tetap sama.
5. Ulangi ID/EN, reduced motion dan tanpa JavaScript. Uji Safari/iPhone asli setelah pull.

## Hal yang perlu diputuskan pemilik

Tidak ada informasi usaha baru yang diperlukan untuk Fase B. Mohon tinjau kenyamanan panjang scroll kategori dan tampilan pengenalan wine di perangkat asli. Fase C sudah tersedia secara terpisah; lihat `MOBILE-ANIMATIONS-C.md`.

## Perbandingan Lighthouse

Angka historis versi awal B2, sebelum revisi lokal 17 November: Lighthouse 13.6.0 mobile, satu run cache dingin per versi, fixture lokal dan konfigurasi sama; baseline adalah Fase A (`bfcd340`).

| Metrik | Sebelum B | Sesudah B |
|---|---:|---:|
| Performance | 93 | 92 |
| Accessibility | 100 | 100 |
| LCP | 3,080 s | 3,231 s |
| CLS | 0,001517 | 0,001517 |
| TBT | 0 ms | 0 ms |

Selisih satu poin pada satu run merupakan indikasi lab, bukan jaminan skor hosting. Pergeseran font kecil yang sudah ada pada baseline tetap terukur; tidak mengklaim CLS nol untuk seluruh halaman. Tidak dilakukan kompresi ulang gambar demi skor.

Artefak sementara lokal: `/private/tmp/phase-b-before.lighthouse.json`, `/private/tmp/phase-b-after.lighthouse.json`, `/private/tmp/phase-b-results.json`, dan screenshot `/private/tmp/phase-b-stack-390.png`, `/private/tmp/phase-b-wine-390.png`. Tidak ikut deployment.

## Verifikasi revisi lokal 17 November

Chrome terisolasi pada 360/390/768/1440 piksel: header dan body tetap `#FAF6F0`, logo transparan, pengenalan `#7A0000` dengan tinta `#FFFDF9`, sudut Lokasi 0 dan tanpa pseudo-element panel. Tidak ada overflow atau runtime exception. ID/EN, reduced motion, tanpa JavaScript, filter dan stepper ikut diuji. Regresi HTTP/CMS serta animasi tiga langkah lulus. Lighthouse tidak diulang untuk revisi warna ini; angka historis di atas bukan pengukuran revisi terbaru.

Artefak lokal: `/private/tmp/local-theme-results.json`, `/private/tmp/local-theme-1440.png`, `/private/tmp/local-theme-390.png`.

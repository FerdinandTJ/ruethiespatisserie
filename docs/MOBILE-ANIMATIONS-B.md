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

## B2 — Tema antarsection

- File: `mobile-section-themes.js`, `mobile-section-themes.css`.
- `public-lib/profile.php` menambahkan penanda `data-theme` pada section beranda. Bagian **Untuk dinikmati bersama.** memakai wine; bagian lain krem.
- Modul mengikuti section pada garis baca 45% tinggi viewport. Berlaku di mobile dan desktop, dapat dibalik saat scroll naik, dan mengembalikan state setelah refresh di tengah halaman.
- Canvas halaman bertransisi melalui opacity lapisan warna tetap selama 650ms. Header, menu mobile dan CTA memakai pasangan custom property warna yang sesuai tema.
- Permukaan teks lokal tetap opak dan sudah memiliki pasangan warna yang benar sejak HTML/CSS awal. Warna teks/background UI diganti bersamaan, bukan dianimasikan silang, agar teks tidak sempat krem di atas krem pada pertengahan transisi.
- Logo wine tetap memakai aset yang sama, diberi permukaan krem saat header wine; tidak ada gambar/font tambahan.
- Bagian `#info` **Pesan dalam tiga langkah** tetap memiliki permukaan/warna aslinya; token `--wine`, `--beige`, `--ow-*` tidak diubah. File `order-story.js` dan `order-story.css` tidak disentuh.
- Reduced motion mempertahankan canvas/header krem dan meniadakan transisi scroll. Bagian pengenalan wine tetap menjadi blok statis yang terbaca. Tanpa JavaScript, konten dan tautan tetap tersedia dengan tema lokal statis.

## Integrasi dan mematikan efek

Shell memuat pasangan CSS/JS masing-masing efek hanya di beranda. `tools/preview-profile.php` dan fixture `tests/seo-public.php` ikut menyediakan aset ini. Untuk menonaktifkan salah satu efek, hapus hanya pemuatan pasangan CSS/JS terkait dari shell, tanpa mengubah data kategori atau tautan. Runtime `mobile-motion.js` dari Fase A tetap dipakai; tidak menambah scroll listener atau IntersectionObserver terpisah.

Total JavaScript animasi A+B sekitar **5,1 KiB gzip** (penjumlahan kompresi setiap file), masih di bawah anggaran 10 KiB. Tidak ada dependency runtime, foto atau font baru; tidak ada perubahan kualitas gambar.

## Pemeriksaan

- Browser Chrome terisolasi, CPU throttling 4x, lebar 360, 390, 768 dan 1440, tinggi 844 piksel.
- Sticky stack, fokus kategori, pergantian tema maju/mundur, ID/EN, refresh di section wine, kembali ke atas, reduced motion dan JavaScript nonaktif lulus.
- Tidak ada overflow halaman atau runtime exception. Sampel scroll kategori mobile mencatat CLS 0 dan tidak ada long task >=50ms.
- Warna aktual DOM diperiksa: header wine `#7A0000`, teks krem `#FFFDF9`, teks pudar `#EAD9CC`, kartu tiga langkah tetap `#FAF6F0`. Pasangan palet diuji terhadap kontras AA 4,5:1, termasuk teks normal.
- `node tests/mobile-phase-b.cjs`: jumlah/urutan kategori dinamis, reversibilitas scale/tema, fokus, reduced motion, dan kontras palet.
- Regresi lulus: `node tests/mobile-motion.cjs`, `node tests/order-story.cjs`, `node tests/profile-form.cjs`, `node tests/product-carousel.cjs`, `php tests/seo-public.php`, `php tests/staging-legacy.php`.
- Pengukuran Lighthouse sebelum/sesudah dicatat di bawah. Ini hasil lab lokal, bukan skor hosting atau data pengguna nyata. Uji Safari/iPhone asli tetap diperlukan setelah pull.

## Uji di staging setelah pull

1. Beranda pada 360/390px: scroll bagian **Manis, gurih, untuk berbagi.** Kartu sebelumnya mengecil di belakang kartu berikutnya. Scroll naik harus membuka kartu sebelumnya lagi.
2. Buka tautan tiap kategori, termasuk lewat keyboard. Nama/urutan ID/EN harus sesuai admin. Pada 768/1440px kategori tetap grid.
3. Scroll melewati tiga langkah ke **Untuk dinikmati bersama.** Latar wine dan teks krem, header mengikuti tema ketika muncul lagi. Scroll balik ke tiga langkah: permukaan dan animasi lama tetap sama.
4. Refresh saat berada pada bagian pengenalan. Tema harus segera mengikuti posisi; di footer dan saat kembali ke atas harus krem lagi.
5. Aktifkan reduced motion, lalu matikan JavaScript pada percobaan terpisah. Semua kategori/konten/tautan WA tetap dapat digunakan. Tidak ada sticky/scale pada mode reduced motion.
6. Periksa menu burger, ID/EN, CTA, fokus dan teks pada kedua tema. Jangan menilai warna hanya dari `data-section-theme`: periksa tampilan/kontras aktual juga.

## Hal yang perlu diputuskan pemilik

Tidak ada informasi usaha baru yang diperlukan untuk Fase B. Mohon tinjau kenyamanan panjang scroll kategori dan tampilan pengenalan wine di perangkat asli. Fase C (stepper jumlah dan filter FLIP) tetap menunggu persetujuan setelah uji ini.

## Perbandingan Lighthouse

Lighthouse 13.6.0 mobile, satu run cache dingin per versi, fixture lokal dan konfigurasi sama; baseline adalah Fase A (`bfcd340`).

| Metrik | Sebelum B | Sesudah B |
|---|---:|---:|
| Performance | 93 | 92 |
| Accessibility | 100 | 100 |
| LCP | 3,080 s | 3,231 s |
| CLS | 0,001517 | 0,001517 |
| TBT | 0 ms | 0 ms |

Selisih satu poin pada satu run merupakan indikasi lab, bukan jaminan skor hosting. Pergeseran font kecil yang sudah ada pada baseline tetap terukur; tidak mengklaim CLS nol untuk seluruh halaman. Tidak dilakukan kompresi ulang gambar demi skor.

Artefak sementara lokal: `/private/tmp/phase-b-before.lighthouse.json`, `/private/tmp/phase-b-after.lighthouse.json`, `/private/tmp/phase-b-results.json`, dan screenshot `/private/tmp/phase-b-stack-390.png`, `/private/tmp/phase-b-wine-390.png`. Tidak ikut deployment.

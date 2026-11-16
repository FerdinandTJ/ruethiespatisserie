# Animasi mobile — Fase C (staging)

Fase C selesai pada branch staging. Implementasi tidak mengubah main/produksi, foto, katalog hosting, akun, database atau flow pembayaran/PO.

## C1 — Stepper jumlah

- File baru: `mobile-quantity.js`, `mobile-quantity.css`.
- Integrasi: `public-lib/profile.php` memuat aset hanya pada detail produk, termasuk `/snack-box-cikarang/`. `profile.js` tetap menjadi satu-satunya penyusun pesan WhatsApp.
- Penempatan: kolom **Jumlah** pada form yang sudah ada. Input angka tetap bisa diketik dan mempunyai label aslinya; tombol minus/plus ditempatkan di luar label untuk menjaga semantik aksesibilitas.
- Batas 1–999 berlaku pada form pesan WhatsApp katalog. Nilai noninteger/di luar batas ditolak. Tombol batas dinonaktifkan dan seluruh target tombol minimal 44px.
- Angka visual bergulir 180ms melalui transform/opacity; input dan live region mengumumkan nilai sebenarnya. Klik cepat membatalkan animasi lama; edit manual tidak menyisakan angka bayangan.
- Pesan tetap memakai sapaan, nama produk, canonical, jumlah, tanggal/catatan opsional dan permintaan konfirmasi dari composer lama. Nomor tetap `6285139281257`. Data form hanya di DOM; tidak disimpan atau dikirim sebelum pengunjung melanjutkan ke WhatsApp.
- ID/EN dan reduced motion didukung. Tanpa JavaScript, tautan WhatsApp dasar lama tetap berfungsi.

## C2 — Filter FLIP

- File baru: `mobile-catalog-filter.js`, `mobile-catalog-filter.css`; hanya dimuat pada `/menu/`.
- Hasil kategori diambil melalui GET ke URL tautan kategori yang sudah ada. Filter/pencarian tetap ditentukan server, sehingga kategori dinamis, pencarian Indonesia/Inggris dan produk tersembunyi mengikuti CMS.
- Tidak mengambil semua katalog lewat request tambahan saat awal halaman. Fetch hanya terjadi setelah perpindahan filter/history.
- Kartu memakai UID permanen untuk mempertahankan node yang cocok. Posisi diukur sebelum/sesudah layout; perpindahan hanya menganimasikan transform/opacity. Kartu baru memudar masuk; kartu keluar menjadi inert dan keluar dari accessibility tree sebelum dihapus.
- Hasil kosong ditampilkan segera tanpa kartu keluar menimpa pesannya. Pesan jumlah/status memakai live region ID/EN dengan ruang yang sudah dicadangkan.
- Request sebelumnya dibatalkan; nomor urut request juga mencegah respons lama menimpa filter terbaru. Animasi lama dibersihkan sebelum perpindahan berikutnya.
- URL memakai `history.replaceState` sesuai instruksi. Ini tidak menambah riwayat untuk setiap chip; Back kembali ke entri sebelumnya. `popstate` menyinkronkan hasil jika riwayat berubah dalam dokumen yang sama. Keluar halaman membatalkan request dan membersihkan status sibuk; pemulihan history didukung.
- Parameter pencarian `q` yang sudah dikirim tetap dipertahankan oleh tautan kategori. Form Cari tetap GET native. Modifier/new tab tidak diintersep; tanpa JavaScript, semua tautan kategori tetap navigasi server biasa.
- Saat fetch gagal, browser memakai navigasi GET biasa ke URL tujuan; tidak menyimpan katalog lama sebagai hasil palsu. Validasi server dan respons 404/503 tetap berlaku.
- Judul, heading dan canonical tetap. Kebijakan robots mengikuti respons server yang sama untuk URL filter tersebut; tidak membuat landing SEO baru.

## Pengujian

Browser Chrome terisolasi, CPU throttling 4x, viewport 360, 390, 768 dan 1440 × 844:

- Stepper 1/999, input pecahan, edit manual, Unicode/karakter khusus pada pesan WhatsApp, label ID/EN, reduced motion dan fallback tanpa JavaScript lulus.
- Filter kategori, hasil kosong dengan `q`, pergantian cepat dengan respons sengaja diperlambat, URL, Back/popstate, fallback network error, ID/EN dan tanpa JavaScript lulus.
- Tidak ada runtime exception atau overflow horizontal. Sampel awal mobile pada Menu dan Snack Box mencatat CLS 0 dan tidak ada long task >=50ms. Hasil lab Lighthouse dicatat terpisah; ini bukan janji CLS seluruh sesi nol.
- Regresi: `node tests/mobile-quantity.cjs`, `node tests/profile-form.cjs`, `node tests/mobile-motion.cjs`, `node tests/mobile-phase-b.cjs`, `node tests/order-story.cjs`, `php tests/seo-public.php`, `php tests/staging-legacy.php`.
- Total tambahan JavaScript A–C sekitar **8,7 KiB gzip**, dihitung sebagai penjumlahan gzip tiap file. Tidak menambah dependency runtime, gambar atau font.

Artefak browser sementara: `/private/tmp/phase-c-results.json`, `/private/tmp/phase-c-stepper-390.png`, `/private/tmp/phase-c-filter-390.png`. Bukan konten hosting.

## Cara uji setelah pull staging

1. Buka Snack Box dan satu detail produk. Pada kolom Jumlah, tekan plus/minus, ketik 20, lalu periksa isi draft WhatsApp. Pastikan tanggal dan catatan ikut sesuai isian. Tidak perlu mengirim pesan untuk memeriksa draft.
2. Coba 0, pecahan, angka di atas 999 dan tanggal lampau. Tombol melanjutkan tidak boleh membuka draft yang tidak valid.
3. Buka Menu; pilih beberapa kategori dengan cepat. Hasil terakhir, chip aktif, status jumlah dan URL harus sama. Coba tautan kategori dengan `q`, termasuk hasil kosong.
4. Buka Menu dari beranda, ganti filter, lalu Back. Perilakunya kembali ke halaman sebelumnya; bukan kembali satu chip sebelumnya karena menggunakan replaceState.
5. Ubah ID/EN sebelum dan sesudah filter. Periksa kartu baru, label stepper dan draft WhatsApp. Buka kategori di tab baru atau refresh: server tetap menampilkan filter yang benar.
6. Ulangi dengan reduced motion dan JavaScript dimatikan. Uji Safari/iPhone asli setelah pengujian Chrome lokal.

## Keputusan dan batasan

Penempatan stepper memakai kolom Jumlah yang sudah tersedia; tidak menambah section atau pilihan varian. Nama dan kebijakan pesan lama tetap dipakai. Tidak ada informasi usaha baru yang perlu ditentukan untuk fase ini. Semua uji lokal memakai fixture publik; pesan WhatsApp tidak benar-benar dikirim.

Pengukuran Lighthouse adalah satu run per versi pada fixture dan konfigurasi mobile yang sama; hasil hosting dapat berbeda. Tidak dilakukan kompresi ulang gambar untuk mengejar skor.

## Perbandingan Lighthouse

Lighthouse 13.6.0 mobile, cache dingin, satu run per versi/halaman dengan konfigurasi sama. Baseline adalah Fase B (`50be6c5`).

| Halaman | Metrik | Sebelum C | Sesudah C |
|---|---|---:|---:|
| Menu | Performance | 94 | 99 |
| Menu | Accessibility | 98 | 98 |
| Menu | LCP | 3,005 s | 2,029 s |
| Menu | CLS | 0,000010 | 0,001350 |
| Menu | TBT | 0 ms | 0 ms |
| Snack Box | Performance | 97 | 97 |
| Snack Box | Accessibility | 100 | 100 |
| Snack Box | LCP | 2,403 s | 2,403 s |
| Snack Box | CLS | 0,011810 | 0,011810 |
| Snack Box | TBT | 0 ms | 0 ms |

LCP/skor bisa bervariasi antar-run dan elemen terbesar yang dipilih Lighthouse; kenaikan skor Menu tidak boleh dianggap jaminan percepatan hosting. Peringatan urutan heading Menu sudah ada pada baseline. Struktur heading tidak diubah sesuai batasan tugas. Nilai CLS seluruh halaman bukan nol, meskipun sampel awal pengujian interaksi mobile mencatat nol.

Artefak mentah lokal: `/private/tmp/phase-c-menu-before.lighthouse.json`, `/private/tmp/phase-c-menu-after.lighthouse.json`, `/private/tmp/phase-c-snack-before.lighthouse.json`, `/private/tmp/phase-c-snack-after.lighthouse.json`.

Tidak diperlukan migrasi atau CLI upgrade untuk deployment fase ini. Gunakan updater staging yang sudah tersedia agar konten hosting tetap dipertahankan. Untuk mematikan satu efek, hapus hanya pemuatan pasangan CSS/JS efek tersebut dari shell; validasi form dan navigasi native tetap tersedia.

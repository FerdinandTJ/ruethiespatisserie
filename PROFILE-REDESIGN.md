# Redesign profil Ruethies — preview lokal

Branch: `feat/redesign-profile`, dibuat dari `main`. Belum dipush, digabungkan ke main, atau dideploy. Tidak ada migrasi database maupun perubahan konfigurasi pembayaran.

## Melihat preview

Dari checkout branch ini:

```bash
php tools/preview-profile.php --port=8872
```

Buka http://127.0.0.1:8872/. Pilih port lain bila sudah digunakan. Hentikan dengan Ctrl+C. Helper hanya berjalan pada loopback, membuat salinan JSON publik serta metadata katalog di direktori sementara, dan tidak menyalin akun atau konfigurasi privat. JSON sumber tidak diubah; fixture dibersihkan saat proses berhenti.

Preview memakai data katalog lokal. Data/foto detail dari hosting yang tidak ada dalam katalog lokal tidak dikarang. Bila hanya ada satu foto, kontrol carousel tidak ditampilkan; foto tambahan tetap dapat dikelola melalui admin setelah desain disetujui.

## Halaman dan alur

- `/`: hero satu foto, tiga kategori, produk pilihan, langkah pemesanan, perkenalan singkat, dan lokasi.
- `/menu/`: grid katalog, kategori dan pencarian GET. Parameter kategori/q tidak membuat URL canonical baru.
- URL produk lama dan `/snack-box-cikarang/`: galeri kiri, informasi/form WhatsApp kanan, detail, FAQ dan tautan katalog. Snackbox/Platter memakai alternatif timbal balik jika produknya terlihat.
- `/tentang-kami/`: perkenalan faktual dan galeri yang tetap bersumber dari atmosphere.json.
- `/hubungi-kami/`: kontak, alamat, jam buka, rute dan peta setelah interaksi.
- `/cara-pesan/`: langkah pesan, informasi yang perlu dikonfirmasi, serta FAQ hosting.

Tombol header menuju WhatsApp umum. Form produk hanya menyusun pesan berisi produk, URL, jumlah, tanggal opsional dan catatan. Tidak membuat pesanan, memesan stok, menghitung total atau melakukan pembayaran. Data form tidak disimpan; hanya pilihan bahasa yang menggunakan penyimpanan browser. Tanpa JavaScript, navigasi/filter/FAQ tetap berfungsi dan WhatsApp dasar tetap tersedia.

## Arsitektur dan kompatibilitas

Renderer publik native PHP memakai shell header/footer dan komponen bersama di public-lib/profile.php. styles.css tetap menyediakan identitas wine–cream dan font; profile.css mengatur layout baru. profile.js menangani bahasa, menu mobile, draft WhatsApp, peta serta galeri. carousel.js memakai perilaku carousel yang sebelumnya dikembangkan.

index.html sengaja dipertahankan untuk identitas bisnis dan kompatibilitas penanda regenerasi FAQ CMS/security upgrade. Halaman publik tetap melalui index.php; akses index.html diarahkan ke root. Antarmuka, akun, role, 2FA dan konfigurasi admin tidak diubah. Skrip/tampilan lama tidak dihapus agar perubahan ini tidak sekaligus menjadi pembersihan aset atau penggabungan fitur staging.

menu.json, faq.json dan atmosphere.json tidak berubah. Produk tersembunyi/deleted tetap 404 dan tidak masuk sitemap. Data rusak pada halaman yang membutuhkannya menghasilkan 503. ETag/revalidasi, header keamanan, isolasi staging dan redirect URL lama dipertahankan. Anchor beranda lama tetap mempunyai ringkasan yang relevan.

## Verifikasi

Pemeriksaan yang lulus:

- HTTP/CMS: tujuh jenis halaman, 10 produk, canonical, sitemap, filter/pencarian native, input array, 404/503, encoding HTML/JSON-LD, edit/tambah/hapus/sembunyikan produk dan perubahan foto.
- Penyimpanan FAQ dan galeri melalui admin lama; hasil langsung tampil di Cara Pesan dan Tentang Kami.
- Apache HTTPS fixture dengan .htaccess asli: routing baru, 301 trailing slash, cache/ETag, header noindex staging dan penolakan path staging terenkode.
- Unit form WhatsApp: jumlah positif/bulat, tanggal WIB pada batas hari UTC, tanggal lampau, catatan maksimal 500, encoding karakter khusus, ID/EN serta sticky link kontekstual.
- Unit carousel: gambar sesuai pilihan, fokus/hover, tab latar, reduced motion, indikator, keyboard dan swipe.
- Browser Chrome terisolasi: 320/390/1280/1440 px, tidak meluber, menu tertutup benar, galeri/modal, carousel dengan foto detail fixture, tanpa JavaScript dan tanpa exception JavaScript. Tautan WhatsApp diperiksa tanpa mengirim pesan sungguhan. Peta tidak diberi src sebelum interaksi.
- Regresi admin UI/role/sesi, header keamanan, unggahan dan penghapusan foto, katalog, SEO/FAQ serta tautan WhatsApp lama.
- Helper preview: metadata dibuat pada salinan, akun/config tidak disalin, sumber JSON tetap sama dan fixture dibersihkan saat dihentikan.

### Perbandingan pemuatan awal lokal

Chrome yang sama, viewport 1440 × 900, DPR 1, cache dimatikan, dua pengukuran setiap versi tanpa interaksi:

| Ukuran | Sebelum | Redesign |
|---|---:|---:|
| HTML beranda | 48.425 byte | sekitar 18.454 byte |
| Transfer awal | 618 KiB | 588 KiB |
| Request | 16 | 16 |
| Request peta/foto detail | 0 | 0 |

Layout shift awal tercatat 0 pada sampel beranda mobile/desktop dan detail produk mobile/desktop. Ini pemeriksaan lokal dengan jendela pengamatan singkat, bukan skor PageSpeed atau hasil Core Web Vitals produksi. Safari perangkat nyata dan kondisi jaringan pelanggan belum diuji.

## Informasi usaha yang belum tersedia

Cerita pendiri/sejarah, foto tim/dapur/fasad, harga dan varian resmi, isi paket tetap, minimum pesanan, lead time, area/biaya antar, ketentuan perubahan/pembatalan, alergen dan penyimpanan belum ditambahkan. Halaman hanya memakai fakta/aset yang sudah ada dan mengarahkan konfirmasi ke WhatsApp.

Review desain publik ini dahulu. Penyesuaian UI admin dan setiap deployment produksi menjadi pekerjaan terpisah setelah diminta.

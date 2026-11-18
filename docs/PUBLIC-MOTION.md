# Animasi publik main dan staging — 18 November 2026

Efek publik yang sebelumnya khusus staging kini tersedia pada main, dengan palet biru–krem dan jarak ringkas terbaru. PO, pembayaran, notifikasi pesanan, akun serta data CMS tidak ikut dibawa ke main. Tidak ada migrasi atau CLI upgrade baru.

## Efek dan lokasi

| Efek | Lokasi | Ukuran layar |
| --- | --- | --- |
| Header mengikuti arah scroll | Seluruh halaman publik | HP sampai 760px |
| Bar WhatsApp muncul sesuai posisi, tidak bertumpuk dengan form/lokasi/footer | Seluruh halaman publik | HP sampai 760px |
| Tinta teks muncul per kata mengikuti scroll | Pengenalan beranda | HP dan desktop |
| Sajian pilihan dapat digeser, indikator titik, kedalaman kartu | Beranda | HP sampai 760px |
| Kategori sticky bertumpuk dengan gap dibatasi 24–64px | Beranda | HP sampai 760px, tinggi minimal 600px |
| Status buka/tutup WIB dan pin lokasi | Lokasi/footer | HP dan desktop |
| Tombol jumlah −/+ dengan perubahan angka | Form produk dan Snack Box | HP dan desktop |
| Peralihan kartu kategori tanpa memuat ulang seluruh halaman | Katalog | HP dan desktop |

Storytelling tiga langkah, carousel foto dan galeri yang sudah sama di kedua branch tetap dipertahankan. Latar biru bagian pengenalan juga mengikuti staging.

## Implementasi dan batasan

- Satu runtime bersama `mobile-motion.js` mengatur scroll melalui passive listener dan requestAnimationFrame; observer storytelling tetap terpisah seperti sebelumnya.
- Modul membaca kategori, produk, UID dan teks ID/EN dari HTML/data CMS. Tidak menulis katalog atau mengubah alamat produk.
- Filter mengambil HTML katalog dari server yang sama, mempertahankan URL kategori, canonical serta noindex hasil filter. Tanpa JavaScript, tautan filter tetap membuka halaman server.
- Jumlah form WhatsApp memakai bilangan bulat 1–999 seperti staging, tetap bisa diketik. Ini permintaan melalui chat, bukan aturan stok/kuota PO. Pesan dan nomor WhatsApp tetap disusun oleh `profile.js`.
- Konten utama tetap tersedia dalam HTML awal. Reduced motion mematikan reveal/scale/gerakan angka; header stabil dan langkah cerita mengikuti isi. Tanpa JavaScript, navigasi, kategori, teks, foto serta tautan WhatsApp tetap tersedia.
- Tidak menambahkan library, foto atau font. Total sembilan modul JS tambahan sekitar 8.2 KiB gzip dan sembilan stylesheet sekitar 3.9 KiB gzip (penjumlahan kompresi per file). Tidak semua modul dimuat pada setiap halaman: reveal/favourites/stack hanya beranda, stepper hanya produk, filter hanya katalog. Angka ini bukan hasil Lighthouse atau ukuran transfer hosting yang dijamin.
- Fitur ini tidak mengimpor `po.php`, navigasi Pre-order, Midtrans, Fonnte, konfigurasi privat, atau schema PO ke main.

## Pengujian dan rilis

Gunakan preview fixture `php tools/preview-articles.php --port=9061` atau `php tools/preview-profile.php --port=8872`. Keduanya memakai salinan data lokal; jangan menyalin akun/config hosting ke fixture.

Regresi: `php tests/seo-public.php`, `php tests/articles.php`, `node tests/mobile-motion.cjs`, `node tests/mobile-phase-b.cjs`, `node tests/mobile-quantity.cjs`, `node tests/profile-form.cjs`, `node tests/order-story.cjs`, `node tests/product-carousel.cjs`, dan `node tests/profile-preview.cjs`.

Uji browser lokal mencakup halaman publik pada 320/390/768/1440px, arah scroll header, WhatsApp kontekstual, stacking, titik carousel sajian, teks per kata, status WIB, filter dari respons server, stepper dan pesan ID/EN, reduced motion serta fallback tanpa JavaScript. Safari/iPhone asli tetap perlu diperiksa setelah deployment; Chromium lokal tidak menggantikan pengujian perangkat itu.

Setelah ada instruksi push/deploy, gunakan backup/pull yang mempertahankan CMS hosting seperti biasa. Tidak perlu impor database atau mengaktifkan layanan pembayaran.

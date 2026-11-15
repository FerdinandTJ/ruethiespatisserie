# Foto menu: satu display utama dan dua detail

Di **Admin → Kelola Menu → Tambah menu / Ubah menu**:

- Foto display utama wajib untuk menu baru; menu lama tetap menggunakan foto yang tersimpan bila tidak diganti.
- Maksimal dua foto detail opsional, masing-masing di slot Foto detail 1 dan 2. Urutan mengikuti nomor slot.
- Unggah di slot yang sama untuk mengganti foto. Centang Hapus foto detail untuk mengosongkan slot. Jika hapus dan unggah dipilih sekaligus, foto baru digunakan.
- Format JPG, PNG, atau WebP; maksimal 5 MB per foto sumber. Browser mengompres foto berurutan sebelum dikirim. Tanpa JavaScript, unggahan multipart tetap didukung selama total request sesuai batas hosting.
- Foto utama menjadi 640 × 480. Foto detail mempertahankan proporsi dengan sisi terpanjang maksimal 1.200 piksel. Validasi keamanan gambar dan pembatasan 20 percobaan unggahan per akun per 15 menit tetap berlaku, setiap foto dihitung satu percobaan.

Klik nama menu, foto, atau tautan teks “Lihat detail” pada daftar menu untuk membuka detail produk berisi nama, deskripsi, dan galeri foto. Foto pada kartu Signature juga membuka detail yang sama. Galeri berisi foto utama diikuti foto detail yang tersedia, maksimal tiga foto. Tersedia navigasi, swipe, Escape untuk menutup, dan tautan pertanyaan WhatsApp sesuai produk dan bahasa. Foto detail tidak dimuat sebelum dibuka.

Data detail disimpan pada `detail_images` di `menu.json`; data lama tanpa field ini tetap didukung. Tidak perlu impor SQL, migrasi database, atau menjalankan ulang upgrade keamanan untuk fitur foto ini. Foto hero beranda tetap terpisah.

## Penerapan

Backup konten CMS dan direktori data privat hosting, lalu dari `public_html`:

```sh
git status --short
git pull --ff-only origin main
```

Jika pull ditolak karena perubahan CMS, simpan perubahan hosting terlebih dahulu dan selaraskan file yang berkonflik; jangan menjalankan reset hard atau menimpa `menu.json` dari laptop. Rilis foto ini tidak mengganti `menu.json`, FAQ, atau foto katalog hosting. Perubahan CMS pada `index.html` mungkin tetap memerlukan penyelarasan sambil mempertahankan konten FAQ hosting.

Setelah pull, buka Tambah/Ubah menu, uji foto utama dan dua detail, simpan, lalu klik foto produk di website. Jika browser masih memakai aset lama, muat ulang halaman.

## Pemeriksaan

- `php tests/admin-ui.php`: fixture HTTP terisolasi; dua jalur upload, penyimpanan tiga foto, penggantian/penghapusan, proporsi, rollback unggahan tidak valid, serta regresi akses admin.
- `node tests/admin-upload.cjs`: pengiriman tiga foto melalui data URL yang diizinkan CSP, termasuk kegagalan baca/decode dan percobaan ulang.
- `node tests/menu-gallery.cjs`: batas dan jalur foto publik, navigasi produk, WhatsApp dan galeri suasana.
- `php tests/security-resources.php`, `php tests/menu-photo-delete.php`, `php tests/security-upgrade.php`, `php tests/catalog.php`, `php tests/seo-check.php`, `php tests/whatsapp.php`, `node tests/whatsapp.cjs`.
- Browser lokal: desktop/mobile, tiga foto dalam satu pengiriman form admin, navigasi galeri, pemuatan tertunda, fokus keyboard, galeri suasana, dan tautan WhatsApp.

## Kompatibilitas CSP unggahan

Preview dan kompresi di admin membaca file melalui `FileReader.readAsDataURL`, sesuai `img-src` hosting yang mengizinkan `data:`. CSP tidak perlu dilonggarkan untuk mengizinkan `blob:`. Versi aset admin dinaikkan agar browser mengambil kode unggahan terbaru.

Regresi browser lokal diperiksa dengan header CSP yang sama persis dari `.htaccess` dan tiga WebP Bolu Jadoel dari Hasil Sortir (`01_Utama_IMG_5085`, `02_Pendamping_IMG_5082`, `03_Kemasan_IMG_5111`). Kode lama mereproduksi pelanggaran CSP pada `blob:` dan tidak mengubah menu; kode baru berhasil menyimpan ketiga foto. File sumber tidak diubah.

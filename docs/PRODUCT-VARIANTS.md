# Pilihan rasa / varian produk

Dikembangkan pada staging dari baseline `8031554`, lalu diterapkan ke main setelah persetujuan tampilan. Baseline main sebelum penggabungan: `1f7ee9e`. Fitur ini menambahkan metadata opsional di `menu.json`, tanpa migrasi database atau perubahan katalog hosting otomatis.

## Rencana dan keputusan

1. Tambahkan `variants_enabled` dan `variants` pada satu produk; UID/slug produk tetap.
2. Editor menu memakai checkbox, daftar varian yang dapat dibuka, tambah, naik/turun, dan konfirmasi hapus saat disimpan.
3. Halaman detail menampilkan pilihan rasa, keterangan, serta foto sesuai pilihan; pesan WhatsApp menyertakan varian.
4. Uji autentikasi/CSRF/revisi katalog, simpan/nonaktif/hapus, ID/EN, foto, tanpa JavaScript, serta regresi publik dan PO.

## Cara admin memakai

- Buka Menu → Tambah menu atau Ubah.
- Produk biasa: biarkan **Pilihan rasa / varian** tidak dicentang.
- Produk bervarian: centang, klik **+ Tambah varian**, isi nama. Nama Inggris dan keterangan opsional.
- Maksimal **8 varian** per produk; nama tidak boleh sama (mengabaikan kapital dan spasi tepi). Nama maksimal 60, keterangan 300 karakter.
- Foto tiap varian opsional; tanpa foto memakai foto utama produk. Foto utama + dua foto detail produk tetap tersedia sebelum pelanggan memilih rasa.
- Foto menggunakan pipeline unggah menu yang sudah ada: JPG/PNG/WebP, maksimal 5 MB per file, pemeriksaan dimensi dan re-encoding server, fallback base64 hosting. Jika banyak foto sekaligus melampaui batas POST hosting, simpan beberapa foto secara bertahap.
- Tombol ↑/↓ mengurutkan varian. Checkbox hapus meminta konfirmasi di browser; penghapusan baru diterapkan setelah **Simpan menu**.
- Mematikan fitur mempertahankan baris serta foto varian. Aktifkan lagi untuk memunculkannya.
- Tanpa JavaScript, semua delapan slot tersedia untuk diisi; pengurutan praktis memakai JavaScript.
- Pemilik dan staf mengikuti izin editor menu yang sekarang, dengan CSRF, shared catalogue lock, revision checks, backup, atomic writes, dan log tambah/ubah menu yang sama.

## Pengunjung

Satu kartu katalog per produk, disertai nama-nama varian dan **Lihat pilihan**. Di halaman detail, tidak ada rasa yang otomatis terpilih. Pelanggan memilih satu rasa sebelum tombol WhatsApp membuka chat. Foto varian baru dimuat saat dipilih; tidak ada library atau layanan baru.

ID/EN mengikuti pilihan bahasa situs, dengan fallback nama/keterangan Indonesia. Tanpa JavaScript, setiap rasa memiliki tautan WhatsApp sendiri berisi produk, varian, dan URL canonical. Jumlah/tanggal disampaikan lewat chat.

Ini adalah pilihan untuk **pesan via WhatsApp**. PO tetap menggunakan UID produk yang sama; harga, kuota, checkout, snapshot pesanan, Midtrans, dan Fonnte tidak mendapatkan opsi varian baru pada tahap ini. Membuat Swiss Roll sebagai produk PO tidak otomatis mencatat rasa di pesanan PO.

## Data

```json
{
  "variants_enabled": true,
  "variants": [
    {
      "uid": "32 karakter hex permanen",
      "id": {"n": "Vanilla Strawberry", "d": ""},
      "en": {"n": "Vanilla Strawberry", "d": ""},
      "img": ""
    }
  ]
}
```

Produk lama tanpa field ini tetap produk biasa. Data rusak atau foto di luar path menu yang diperbolehkan ditolak, bukan dirender sebagai path bebas. Field tidak aktif tetap divalidasi. Foto yang masih direferensikan varian tidak dihapus oleh pembersihan foto menu.

## Deploy dan rollback

Main dan staging memakai implementasi varian yang sama. Untuk staging gunakan updater biasa; untuk main backup kode/konten lalu pull main. Tidak menyalin JSON laptop ke hosting; tidak perlu CLI upgrade atau phpMyAdmin. Updater mempertahankan `menu.json` dan gambar hosting.

Sebelum pull, backup kode, `menu.json` dan gambar. Untuk rollback kode gunakan baseline branch yang sesuai di atas, dengan snapshot katalog/gambar yang cocok bila diperlukan. Jangan memulihkan akun atau metadata keamanan lama. Rollback kode lama dapat mempertahankan field varian tak dikenal tetapi tidak menampilkannya; hentikan pengeditan menu selama pergantian versi.

## Hasil verifikasi lokal

- Tes HTTP kategori + varian: lolos, termasuk owner/staf, CSRF, revisi lama, nama duplikat, HTML, hapus/nonaktif dan data gambar traversal.
- Regresi CMS/SEO publik, validasi WhatsApp ID/EN, kategori, dan pembersihan foto: lolos.
- Browser Chromium dengan akun fixture: tambah tiga varian, unggah foto melalui base64, simpan, foto hanya diunduh ketika dipilih, pergantian bahasa, pilihan wajib, fallback tanpa JavaScript, dan tanpa overflow pada 320/390/768/1440px. Safari/iPhone fisik belum diuji.
- Tes Midtrans Sandbox: 70 pemeriksaan lolos dengan database lokal dan transport provider mock, termasuk checkout, idempotensi, webhook paralel, Fonnte untuk pemilik/pelanggan, serta stok habis. Fixture sengaja menyertakan produk dengan metadata varian. Tidak ada pesan WhatsApp atau pembayaran sungguhan yang dikirim.
- `tests/po-flow.php` lama masih menargetkan alur demo sebelum Midtrans; berhenti pada guard Sandbox yang memang berlaku. Suite aktif yang digunakan adalah `tests/po-midtrans.php`.
- Preview admin sekarang menyalin gambar menu ke fixture, sehingga percobaan unggah tidak menulis ke gambar sumber.

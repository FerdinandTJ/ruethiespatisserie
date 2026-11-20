# Editor visual Artikel — staging

Editor Artikel staging menampilkan tulisan berformat langsung; pengelola tidak perlu mengetik Markdown. Main belum diubah. Tidak ada migrasi, library editor, API, build baru, atau perubahan konfigurasi hosting.

## Cara memakai

1. Masuk admin → Artikel → Buat artikel atau Ubah.
2. Ketik isi seperti biasa. Blok teks lalu pilih Tebal, Miring atau Tautan. Pilih Judul bagian/Subbagian, Daftar poin/angka atau Kutipan sesuai kebutuhan.
3. Menempel dari Word/chat mengambil teks biasa; terapkan format dari toolbar agar gaya dokumen luar tidak terbawa.
4. Urungkan/Ulangi menggunakan riwayat edit browser. Pergantian mode menginisialisasi kembali isi sehingga jangan mengandalkan riwayat undo lintas mode.
5. Lihat tampilan membuka pratinjau privat tanpa menyimpan atau menerbitkan. Simpan tetap mengikuti status sebelumnya: artikel baru draft; artikel terbit langsung diperbarui oleh penyimpanan seperti sebelumnya.
6. Buka mode teks hanya untuk pengguna yang ingin mengedit Markdown. Tulisan tidak disimpan ke localStorage atau server sebelum Simpan. Peringatan meninggalkan halaman untuk perubahan isi menggunakan kemampuan browser.

## Kompatibilitas dan pengamanan

- Penyimpanan tetap `body` Markdown dalam JSON privat, dengan lock, revisi, backup, CSRF, role owner/staff dan audit yang sudah ada. Tidak menambah field data.
- Artikel tanpa perubahan isi dikirim sebagai Markdown asli, tanpa konversi ulang. Tabel sederhana lama dipertahankan. Pembuatan tabel baru dilakukan melalui mode teks pada tahap ini.
- Adapter admin mengubah HTML visual ke subset Markdown yang sudah dirender aman oleh server. Script, gambar inline, iframe, atribut event/style dan tautan berbahaya tidak disimpan sebagai HTML aktif.
- Daftar bertingkat, sel tabel gabungan, format inline bertumpuk dan karakter pembatas tertentu belum didukung renderer lama; ditolak dengan penjelasan, bukan diratakan diam-diam. Pemeriksaan sebelum submit menjaga tulisan tetap bisa diperbaiki di editor. Direct POST ditolak juga oleh server; salinan HTML yang ditolak tersedia sebagai teks ter-escape pada halaman error editor agar dapat dipulihkan.
- Endpoint `admin/article-editor.php` hanya konversi; tidak menyimpan data. Memerlukan POST, login owner/staff, CSRF dan limiter 120 konversi per akun/15 menit. Tidak merender HTML request secara langsung.
- Runtime menggunakan `contenteditable` dan `document.execCommand` untuk toolbar serta riwayat undo native tanpa dependency baru. API perintah edit ini deprecated, sehingga dukungan diperiksa dan mode teks tetap menjadi fallback. Chromium lokal sudah diuji; Safari/iPhone dan Firefox asli masih perlu smoke test sebelum menganggap kompatibilitas lintas browser tervalidasi. Rujukan: [MDN execCommand](https://developer.mozilla.org/en-US/docs/Web/API/Document/execCommand).
- Aset hanya dimuat pada area Artikel admin; fungsi JS keluar langsung jika bukan form editor. Tidak menambah beban halaman publik atau mengubah PO/Midtrans/Fonnte.

## Validasi dan penerapan

- `php tests/article-visual.php`: konversi format, Unicode, tabel, roundtrip dan payload berbahaya.
- `ARTICLE_TEST_STAGING=1 php tests/articles.php`: HTTP izin/CSRF/GET, konversi, penyimpanan draft, pratinjau tanpa simpan, pemulihan kesalahan, seluruh regresi artikel/kategori/akun dan isolasi data staging.
- Browser fixture lokal: toolbar, judul bagian, daftar, tautan, undo/redo, pergantian visual/teks, paste teks, simpan dan simpan ulang tanpa perubahan, fallback tanpa JS, 320/390/768/1440px tanpa overflow dan error runtime.

Belum dipush atau dideploy. Setelah disetujui, gunakan updater staging yang mempertahankan CMS hosting. Tidak perlu CLI upgrade kategori/artikel atau impor phpMyAdmin untuk rilis editor ini. Rollback kode ke versi sebelum rilis tetap bisa membaca seluruh artikel karena schema dan format penyimpanan tidak berubah; jangan memulihkan akun/config/data privat lama.

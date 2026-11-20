# Editor visual Artikel — main dan staging

Editor Artikel main dan staging menampilkan tulisan berformat langsung; pengelola tidak perlu mengetik Markdown. Implementasi editor sama, tetapi data masing-masing hosting tetap terpisah. Tidak ada migrasi, library editor, API, build baru, atau perubahan konfigurasi hosting.

## Cara memakai

1. Masuk admin → Artikel → Buat artikel atau Ubah.
2. Ketik isi seperti biasa. Blok teks lalu pilih Tebal, Miring atau Link. Pilih Judul bagian/Subjudul, Bullet point, Daftar angka atau Kutipan sesuai kebutuhan.
   Untuk tabel, klik Tabel → pilih jumlah kolom/baris → Buat tabel. Klik sel untuk mengisi dan memunculkan kontrol Tambah/Hapus baris atau kolom. Tombol mode tetap berada di atas area isi.
3. Menempel dari Word/chat mengambil teks biasa; terapkan format dari toolbar agar gaya dokumen luar tidak terbawa.
4. Urungkan/Ulangi menggunakan riwayat edit browser. Pergantian mode menginisialisasi kembali isi sehingga jangan mengandalkan riwayat undo lintas mode.
5. Lihat tampilan membuka pratinjau privat tanpa menyimpan atau menerbitkan. Simpan tetap mengikuti status sebelumnya: artikel baru draft; artikel terbit langsung diperbarui oleh penyimpanan seperti sebelumnya.
6. Buka mode teks hanya untuk pengguna yang ingin mengedit Markdown. Tulisan tidak disimpan ke localStorage atau server sebelum Simpan. Peringatan meninggalkan halaman untuk perubahan isi menggunakan kemampuan browser.

## Kompatibilitas dan pengamanan

- Penyimpanan tetap `body` Markdown dalam JSON privat, dengan lock, revisi, backup, CSRF, role owner/staff dan audit yang sudah ada. Tidak menambah field data.
- Artikel tanpa perubahan isi dikirim sebagai Markdown asli, tanpa konversi ulang. Tabel sederhana lama dipertahankan dan tabel baru bisa dibuat dari toolbar. Batas kontrol visual: 6 kolom dan 20 baris isi, ditambah satu baris judul. Baris judul tidak dapat dihapus sendiri. Hapus baris/kolom berisi teks dan Hapus tabel meminta konfirmasi; Undo mengembalikan perubahan tabel. Sel tidak digabung. Tab/Shift+Tab berpindah antar sel di dalam tabel.
- Adapter admin mengubah HTML visual ke subset Markdown yang sudah dirender aman oleh server. Script, gambar inline, iframe, atribut event/style dan tautan berbahaya tidak disimpan sebagai HTML aktif.
- Daftar bertingkat, sel tabel gabungan, format inline bertumpuk dan karakter pembatas tertentu belum didukung renderer lama; ditolak dengan penjelasan, bukan diratakan diam-diam. Pemeriksaan sebelum submit menjaga tulisan tetap bisa diperbaiki di editor. Direct POST ditolak juga oleh server; salinan HTML yang ditolak tersedia sebagai teks ter-escape pada halaman error editor agar dapat dipulihkan.
- Endpoint `admin/article-editor.php` hanya konversi; tidak menyimpan data. Memerlukan POST, login owner/staff, CSRF dan limiter 120 konversi per akun/15 menit. Tidak merender HTML request secara langsung.
- Runtime menggunakan `contenteditable` dan `document.execCommand` untuk toolbar serta riwayat undo native tanpa dependency baru. API perintah edit ini deprecated, sehingga dukungan diperiksa dan mode teks tetap menjadi fallback. Chromium lokal sudah diuji; Safari/iPhone dan Firefox asli masih perlu smoke test sebelum menganggap kompatibilitas lintas browser tervalidasi. Rujukan: [MDN execCommand](https://developer.mozilla.org/en-US/docs/Web/API/Document/execCommand).
- Aset hanya dimuat pada area Artikel admin; fungsi JS keluar langsung jika bukan form editor. Tidak menambah beban halaman publik atau membawa PO/Midtrans/Fonnte ke main.

## Validasi dan penerapan

- `php tests/article-visual.php`: konversi format, Unicode, tabel, roundtrip dan payload berbahaya.
- `php tests/articles.php` (main), `ARTICLE_TEST_STAGING=1 php tests/articles.php`: HTTP izin/CSRF/GET, konversi, penyimpanan draft, pratinjau tanpa simpan, pemulihan kesalahan, seluruh regresi artikel/kategori/akun dan isolasi data staging.
- Browser fixture lokal: toolbar, judul bagian, bullet/daftar angka, tautan, undo/redo, buat tabel, edit sel, tambah/hapus baris dan kolom, perlindungan baris judul, hapus/undo tabel, pergantian visual/teks, paste teks, simpan/reopen, simpan ulang tanpa perubahan, fallback tanpa JS, 320/390/768/1440px tanpa overflow dan error runtime.

Pengembangan dan pengujian dilakukan lokal terlebih dahulu. Untuk deployment yang diminta pemilik, backup konten hosting dan pull branch sesuai lingkungan. Updater staging tetap hanya digunakan di staging; jangan menjalankannya pada main. Tidak perlu CLI upgrade kategori/artikel atau impor phpMyAdmin untuk rilis editor ini. Rollback kode ke versi sebelum rilis tetap bisa membaca seluruh artikel karena schema dan format penyimpanan tidak berubah; jangan memulihkan akun/config/data privat lama.

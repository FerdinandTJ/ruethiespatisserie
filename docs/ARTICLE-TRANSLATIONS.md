# Satu kartu per pasangan artikel ID/EN

Artikel Indonesia dan Inggris tetap dua entri admin dengan URL, isi, status, canonical dan metadata masing-masing. Kolom **Artikel asal yang diterjemahkan** menghubungkan pasangan, bukan menggabungkan data atau menerjemahkan otomatis.

## Tampilan publik

- Daftar `/artikel/`, tiga artikel beranda, Baca juga pada produk, dan Artikel lain menampilkan satu kartu per keluarga terjemahan yang dihubungkan eksplisit. Judul yang mirip tidak dikelompokkan otomatis.
- Bahasa awal HTML adalah Indonesia jika ada versi Indonesia terbit; jika belum, tampil versi Inggris terbit. Tanpa JavaScript tetap satu kartu dan link yang berfungsi.
- Pilihan ID/EN pada header mengganti kartu ke versi terbit yang sesuai: judul, ringkasan, sampul, tanggal, waktu baca dan tujuan tautan. Jika hanya satu bahasa tersedia, kartu memakai bahasa tersebut. Tidak menampilkan versi draft atau dihapus.
- Pagination menghitung keluarga artikel, maksimal 12 per halaman, sehingga menerbitkan terjemahan tidak menambah kartu atau halaman duplikat. Kategori tetap memakai ID kategori admin. Jika pasangan diberi kategori berbeda, filter kategori memilih versi yang memang berada dalam kategori itu; disarankan memakai kategori sama untuk satu pasangan.
- Baca juga menganggap keluarga relevan jika salah satu versi menautkan produk terkait. Artikel lain tidak mengulang versi bahasa lain dari artikel yang sedang dibaca; link versi bahasa lain tetap tersedia dekat isi artikel.
- Jika ada beberapa artikel satu bahasa pada hubungan terjemahan yang sama, kartu memilih yang pertama dalam urutan publik (terbit terbaru). Admin tetap menampilkan semua entri; periksa hubungan terjemahan agar tidak menghubungkan artikel yang tidak terkait.

## URL, SEO, data dan performa

Tidak ada migrasi, pengubahan slug, redirect, penggabungan entri atau publikasi otomatis. Sitemap mempertahankan kedua URL terbit dan canonical masing-masing tetap menuju dirinya sendiri. Membuka URL detail menampilkan isi bahasa artikel tersebut; toggle header tidak otomatis memindahkan URL atau menerjemahkan isi. Link pasangan yang sudah terbit tetap tersedia pada detail.

`article-cards.js` hanya dimuat jika halaman memuat kartu atau daftar artikel. Alternatif terbit berada dalam template HTML inert, tidak memuat seluruh isi artikel dan tidak mengunduh foto versi lain sampai dipilih. Pergantian bahasa tidak melakukan fetch. Filter/pagination AJAX tetap mengambil respons HTML server, lalu menerapkan bahasa aktif pada kartu baru. Tidak ada library, foto, font atau CSS tambahan.

Perubahan flag Artikel, penerbitan atau penyembunyian tetap memengaruhi ETag dan sitemap melalui mekanisme yang ada. Kartu tidak menulis JSON, preferensi atau akun.

## Pengujian

- `php tests/articles.php` mencakup pengelompokan, SSR Indonesia, fallback Inggris, draft tidak terekspos, pagination, respons filter, beranda, relevansi produk, URL/canonical, sitemap dua bahasa dan dua entri admin.
- `php tests/seo-public.php` dan `php tests/article-images.php` memeriksa regresi CMS, SEO, encoding dan gambar.
- Preview lokal: `php tools/preview-articles.php --port=9067 --translations` membuat pasangan demo dan contoh Inggris tanpa terjemahan di fixture privat sementara. Tidak memakai akun atau artikel hosting.
- Chromium lokal: ID/EN berulang, tautan/foto yang benar, filter/pagination AJAX, kartu beranda/produk, penundaan foto alternatif, tanpa JavaScript dan lebar 320/390/768/1440px lulus tanpa error runtime. Safari/iPhone asli belum diuji pada rilis ini.

Setelah diminta push, deployment cukup backup dan pull main yang mempertahankan konten hosting. Tidak perlu menjalankan CLI draft lagi atau migrasi database. Hubungkan dan terbitkan pasangan melalui admin seperti biasa. Rollback kode tetap kompatibel dengan store artikel yang sama.

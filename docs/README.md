# Dokumentasi Ruethies Patisserie

Dokumentasi proyek disimpan di folder ini. Jalankan perintah PHP, Git, dan tool deployment dari root checkout (`public_html` untuk main), bukan dari `docs/`.

## Operasional dan deployment

- [Animasi publik bersama main dan staging](PUBLIC-MOTION.md)

- [Perapian jarak halaman dan hasil pemeriksaan](SPACING-REFINEMENT.md)

- [Panduan harian untuk pengelola website](ADMIN-PANDUAN.md)

- [Sinkronisasi Artikel dan tema main–staging](ARTICLE-STAGING-SYNC.md)

- [Artikel: admin, draft, pengujian dan rollback](ARTICLES.md)
- [Editor visual Artikel, tabel dan bullet point](ARTICLE-VISUAL-EDITOR.md)
- [Pasangan artikel Indonesia–Inggris dan satu kartu publik](ARTICLE-TRANSLATIONS.md)
- [Kategori melalui admin](CATEGORY-MANAGEMENT.md)
- [Upgrade keamanan dan pemulihan akun](SECURITY-UPGRADE.md)
- [Deployment SEO dan pemulihan](SEO-DEPLOYMENT.md)
- [Validasi SEO](SEO-VALIDATION.md)
- [SEO lokal dan draft artikel Indonesia](SEO-LOCAL-20261120.md)

Dokumen upgrade mencatat perubahan rilis tertentu. Pemindahan dokumentasi ini tidak memerlukan CLI upgrade atau impor database, dan tidak mengubah konten CMS.

## Katalog dan foto

- [Pembaruan katalog](CATALOG-UPDATE.md)
- [Filosofi dan deskripsi menu](MENU-COPY-UPDATE.md)
- [Foto utama dan foto detail](MENU-PHOTOS.md)
- [Foto katalog dan galeri](PHOTO-OPTIMIZATION.md)

## Tampilan publik

- [Redesign profil](PROFILE-REDESIGN.md)
- [Tema biru–cream main](BLUE-CREAM-THEME.md)
- [Animasi tiga langkah pemesanan](ORDER-STORY.md)

## Catatan pribadi

`docs/local/` hanya untuk catatan pribadi pada komputer lokal, diabaikan Git, dan tidak ikut push atau deployment. Folder tersebut bisa tidak ada pada checkout lain.

Dokumentasi yang dilacak Git akan berpindah dari root ke `docs/` saat hosting menarik commit ini. File Markdown tambahan yang tidak dilacak Git tidak dihapus otomatis. Jika pull berhenti karena dokumen hosting telah diubah, arsipkan perubahan tersebut di luar `public_html` terlebih dahulu; jangan menghapus perubahan CMS untuk merapikan dokumentasi.

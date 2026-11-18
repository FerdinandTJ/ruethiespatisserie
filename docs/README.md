# Dokumentasi Ruethies Patisserie

Dokumentasi proyek disimpan di folder ini. Jalankan perintah PHP, Git, dan tool deployment dari root checkout (`public_html` untuk main), bukan dari `docs/`.

## Operasional dan deployment

- [Artikel: admin, draft, pengujian dan rollback](ARTICLES.md)
- [Kategori melalui admin](CATEGORY-MANAGEMENT.md)
- [Upgrade keamanan dan pemulihan akun](SECURITY-UPGRADE.md)
- [Deployment SEO dan pemulihan](SEO-DEPLOYMENT.md)
- [Validasi SEO](SEO-VALIDATION.md)

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

## Khusus staging

- [Penyelarasan main dan staging](STAGING-SYNC.md)
- [Midtrans Sandbox](MIDTRANS-SANDBOX.md)
- [Notifikasi Fonnte](PO-FONNTE-STAGING.md)
- [Animasi pemesanan staging](ORDER-STORY-STAGING.md)
- [Animasi mobile Fase A dan hasil pengujian](MOBILE-ANIMATIONS-A.md)
- [Animasi mobile Fase B dan hasil pengujian](MOBILE-ANIMATIONS-B.md)
- [Animasi mobile Fase C dan hasil pengujian](MOBILE-ANIMATIONS-C.md)

Perintah untuk staging dijalankan dari root checkout staging (`public_html/staging`), bukan dari folder dokumentasi. Tema wine dan flow PO tetap khusus staging.

## Catatan pribadi

`docs/local/` hanya untuk catatan pribadi pada komputer lokal, diabaikan Git, dan tidak ikut push atau deployment. Folder tersebut bisa tidak ada pada checkout lain.

Dokumentasi yang dilacak Git akan berpindah dari root ke `docs/` saat hosting menarik commit ini. File Markdown tambahan yang tidak dilacak Git tidak dihapus otomatis. Jika pull berhenti karena dokumen hosting telah diubah, arsipkan perubahan tersebut di luar `public_html` terlebih dahulu; jangan menghapus perubahan CMS untuk merapikan dokumentasi.

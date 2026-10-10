# Ruethies Patisserie — Design Guidelines v2.1 (Editorial Mentok)
> Status: LOCKED palette. Baby blue max **8% opacity** (keputusan user). Menu tanpa harga. Vibe: **Editorial French Journal + Arch Arcade** — anti-template, berani tapi aman.

---

## 0. Ringkasan Eksekutif

- **Brand:** Ruethies Patisserie
- **Hashtag:** `#MadeWithDelicacy`
- **Konsep:** Haute French Patisserie & Specialty Espresso Lounge di ruko — tapi rasa hotel bintang 5.
- **Lokasi (canonical):** Ruko The Neo Patio East NO. 51, Lippo, Cikarang Sel., Kabupaten Bekasi, Jawa Barat 17530
- **Maps short-link (canonical):** https://maps.app.goo.gl/6kpvgE4QUcHWc7it5 → place “Ruethies Patisserie” @ `-6.3443722, 107.1490519`, z18. Embed pakai koordinat + label agar pin tepat di tengah.
- **Bahasa:** Bilingual ID (default) + EN, toggle di navbar.
- **Struktur site:** Standar cafe lengkap — Home + Signature + Story + Menu + Atmosphere/Galeri + Visit (Maps interaktif) + Reservasi WA + Footer.
- **Aset:** Belum ada logo/foto asli → pakai strategi placeholder terarah (Section 7).
- **Prinsip aesthetic:** *Satu layar, satu bintang.* Setiap viewport hanya 1 focal point (headline besar ATAU foto arch ATAU harga). Sisanya whitespace beige. Whitespace = kemewahan.

---

## 1. Color System — PALETTE DIKUNCI

### 1.1 Core (JANGAN DIUBAH HEX-NYA)

| Token | Hex | Peran |
|---|---|---|
| Satu-satunya warna berani. Headline, tombol primer, harga, ikon aktif, footer gelap. |
| `--beige` | `#FAF6F0` | Kanvas utama. Background page, ruang napas. |
| `--baby` | `#89CFF0` | HANYA cahaya/dekorasi. Glow, sheen, dot kecil. **Dilarang untuk teks.** |

> Aturan keras user: palette ini final. Turunan di bawah adalah tints/shades/alpha dari ketiga warna di atas + netral hangat pendamping — bukan warna baru.

### 1.2 Turunan Resmi (derived, boleh dipakai)

```css
:root {
  /* wine scale */
  --wine: #7A0000;
  --wine-deep: #560000;          /* footer bg, hover press, overlay foto 40% */
  --wine-soft: #A83A3A;          /* hover ringan, italic accent */
  --wine-ink: #3D0000;           /* teks di atas beige muda saat butuh ekstra kontras */
  --wine-12: rgba(122,0,0,.12);  /* hairline border, chip bg */
  --wine-08: rgba(122,0,0,.08);

  /* beige scale */
  --beige: #FAF6F0;
  --paper: #FFFDF9;              /* card bg agar layer di atas beige */
  --beige-deep: #F1E8DC;         /* section selang-seling, divider */
  --beige-line: #E7DACA;         /* border netral hangat */

  /* baby — hanya glow max 8% */
  --baby: #89CFF0;
  --baby-glow: rgba(137,207,240,.08);
  --baby-faint: rgba(137,207,240,.08);

  /* netral hangat pendamping (bukan palette baru, untuk teks) */
  --ink: #2B2320;                /* body text — warm charcoal */
  --muted: #6F625B;              /* secondary text */
  --faint: #A89A8E;              /* eyebrow, caption, placeholder */

  /* state */
  --success: #1E7A3C;            /* hanya untuk sukses reservasi / WA */
  --danger: #7A0000;             /* reuse wine */
}
```

### 1.3 Aturan Pakai (60-30-10 + 5)

- **60%** beige/paper — background, whitespace.
- **30%** ink/teks + foto warm.
- **10%** wine — headline, CTA, harga.
- **≤5%** baby — glow radial di hero, dot, sheen pada badge. Tidak lebih.
- Rasio ini yang bikin terlihat mahal. Kalau wine dipakai di mana-mana, langsung terlihat murah.

### 1.4 Kontras & Aksesibilitas (WCAG AA)

- Body `--ink` di atas `--beige` → ±13:1 ✅
- `--wine` di atas `--beige` → ±9.5:1 ✅ (boleh untuk headline & tombol teks putih di atas wine → ±10:1 ✅)
- `--baby` di atas `--beige` → ±1.6:1 ❌ → **DILARANG untuk teks, hanya glow/dekorasi.**
- Semua teks interaktif minimal 16px, tombol minimal 44px touch target.
- Focus ring: `2px solid var(--wine)` offset 3px, selalu visible.

### 1.5 Do / Don't Warna

- ✅ DO: satu tombol wine per layar. Secondary = outline wine.
- ✅ DO: glow baby di belakang foto arch, opacity MAX 8%.
- ❌ DON'T: background baby full section.
- ❌ DON'T: teks baby, teks muted di atas foto tanpa scrim.
- ❌ DON'T: gradient norak wine→baby. Hanya radial fade ke transparent.

---

## 2. Typography — Editorial French Luxury

### 2.1 Font (final, gratis Google Fonts)

- **Display:** `Cormorant Garamond` — Light 300, Regular 400, Italic 400/500, Bold 600/700. Untuk hero, headline section, nama menu, quote.
- **Body/UI:** `Plus Jakarta Sans` — Regular 400, Medium 500, SemiBold 600. Untuk nav, body, harga, tombol, form, footer.
- Load via `next/font` atau Google Fonts dengan `display=swap`. Preload display 500 italic.

### 2.2 Skala Fluid (siap pakai `clamp()`)

| Level | clamp | Pakai di |
|---|---|---|
| Display hero | `clamp(3.5rem, 8vw, 6rem)` / 1.02 / -0.02em | Hero H1, 1 kata italic |
| H1 | `clamp(2.5rem, 5vw, 3.5rem)` / 1.08 | Judul halaman Menu/Visit |
| H2 section | `clamp(1.9rem, 3.5vw, 2.5rem)` / 1.12 | Judul section |
| H3 card | `1.375rem` / 1.25 | Nama pastry/menu |
| Body | `1rem–1.125rem` / 1.7 | Deskripsi |
| Small | `0.875rem` / 1.6 | Caption, jam, alamat |
| Eyebrow | `0.75rem` / uppercase / `letter-spacing: .25em` | Label section: `01 — Signature` |
| Price | Jakarta SemiBold `1.05rem`, wine | Harga selalu wine, tabular-nums |

Aturan italic: **maksimal 1–2 kata italic per headline**, selalu kata Perancis/emosional: *Délicatesse, Artisan, Douceur, Lumière.* Contoh: `Baked with *Délicatesse*`.

### 2.3 Bilingual Typesetting

- ID default. EN cenderung 10–15% lebih pendek — desain harus tahan kedua panjang (jangan fixed-height pada card).
- Key pattern: `hero.title`, `hero.subtitle`, `menu.tabs.signature`, dst. Toggle tidak reload penuh, persist `localStorage: ruethies-lang`, hormati `?lang=en`.
- Contoh dictionary:
```json
{ "hero": {
  "eyebrow_id": "Patisserie Artisan — Cikarang",
  "eyebrow_en": "Artisan Patisserie — Cikarang",
  "title_id": "Dipanggang dengan <em>Kelembutan</em>",
  "title_en": "Baked with <em>Délicatesse</em>"
}}
```

---

## 3. Layout, Spacing, Shape — Rumus Elegan

- **Container:** max 1200px, gutter 24px (mobile 20px).
- **Section rhythm:** padding 112px desktop / 72px mobile. Selang-seling: beige → paper → beige-deep → wine-deep (footer). Divider antar section = hairline ATAU kurva organik (SVG wave sangat subtil, 40px).
- **Grid:** 12-col. Hero: 7 (teks) + 5 (arch foto). Menu: 3-col card desktop, 1-col mobile swipe.
- **Spacing base 8pt:** 8/16/24/32/48/72/112. Dilarang angka random.
- **Radius (signature):**
  - Foto hero/galeri: **arch** → `border-radius: 999px 999px 28px 28px` (lengkung gereja Perancis — pembeda utama brand).
  - Card menu: 24px. Tombol & pill: full. Input: 16px. Modal: 28px.
- **Border:** hairline `1px solid var(--wine-12)` atau `var(--beige-line)`. Tidak ada border tebal kecuali 2px wine untuk state aktif.
- **Shadow (warm, bukan hitam keras):**
  - card: `0 24px 60px -24px rgba(61,0,0,.18)`
  - floating badge: `0 12px 32px -12px rgba(43,35,32,.22)`
  - glow hero: `radial-gradient(600px, var(--baby-glow), transparent)` + `radial-gradient(500px, rgba(122,0,0,.10), transparent)`.
- **Tekstur:** noise grain SVG opacity 0.03 di hero + footer wine. Jangan lebih — cukup untuk rasa kertas mewah.

---

## 4. Komponen (Spec Siap Build)

### 4.1 Navbar
- Sticky, glass: `backdrop-blur(16px) + rgba(250,246,240,.75) + hairline bottom`.
- Kiri: wordmark `Ruethies` (Cormorant 600) + kecil `PATISSERIE` tracking .3em.
- Tengah: Home, Menu, Atmosphere, Visit (anchor smooth scroll).
- Kanan: toggle **ID|EN segmented pill** + tombol `Reservasi` (wine pill, mobile jadi ikon).
- Scroll >24px: tambah shadow lembut.

### 4.2 Button
- **Primary:** bg wine, teks paper, pill, padding 14/28, Jakarta SemiBold 15px. Hover: bg wine-deep + translateY(-1px) + shadow. Active: scale .98.
- **Secondary:** transparent, border 1px wine, teks wine. Hover: bg wine-08.
- **Ghost/Link:** teks wine + underline hairline animasi.
- Disabled: opacity .45, no pointer.

### 4.3 Hero (paling penting — aesthetic mentok di sini)
- Kiri: eyebrow N°01 + garis ganda, display clamp(3.8rem,8vw,7rem), label vertikal tepi, 2 CTA, proof real (NO.51 • fresh oven • buka tiap hari).
- Kanan: foto arch besar warm + 1 mini arch overlap + frame offset wine + glow baby max 8% + badge: `Fresh Daily`, `Signature Croissant`, `N°51`.
- Background: beige + 2 radial glow + grain + lengkung bawah ke section berikutnya.

### 4.4 Marquee
- Strip tipis wine-deep atau beige-deep, teks Cormorant italic: `#MadeWithDelicacy • Croissant • Éclair • Latte •` infinite 30s linear. Jeda saat hover. Hormati reduced-motion.

### 4.5 Menu Interaktif
- Tab: Signature Pastry / Specialty Coffee / Artisanal Mains — segmented pill dengan indikator geser (layout animation 300ms).
- Card: foto arch-top 4:3, badge (Signature/Best Seller) wine chip, nama Cormorant 22px, desc 2-line clamp, harga wine SemiBold + garis titik-titik ala menu Perancis (`leader dots`).
- Hover: foto scale 1.05, card lift -4px.
- Data dari JSON agar mudah ganti ke foto asli nanti.

### 4.6 Atmosphere / Galeri
- 3 arch frames sejajar: Interior / Craft / Detail. Caption Cormorant italic + ikon line 1.5px.
- Klik → lightbox sederhana (blur backdrop, swipe mobile).

### 4.7 Visit + Interactive Maps
- Layout 2 kolom: kiri kartu info (alamat canonical, jam, WA, IG, tombol Direction + Copy Address), kanan maps.
- **Implementasi tanpa API key (fase 1):**
```html
<iframe
  title="Peta Ruethies Patisserie — NO.51"
  src="https://www.google.com/maps?q=-6.3443722,107.1490519(Ruethies%20Patisserie)&z=18&output=embed"
  loading="lazy" referrerpolicy="no-referrer-when-downgrade"
  style="border:0; border-radius:24px; width:100%; height:340px"
  allowfullscreen></iframe>
<a href="https://maps.app.goo.gl/6kpvgE4QUcHWc7it5" target="_blank" rel="noopener">Buka di Google Maps →</a>
```
- Frame maps: radius 24px + hairline + shadow warm. Tanpa filter sepia agar pin merah Google jelas. Overlay kartu `N°51` di kiri atas + hint zoom.
- **Fase 2 (opsional, butuh API key):** Google Maps JS API + marker wine custom + info window brand.
- Fallback offline: kartu alamat + tombol direction tetap bisa diklik.

### 4.8 Reservasi WA (modal global)
- Trigger dari semua CTA Reservasi. Sheet: radius 28px, blur backdrop.
- Field: nama, jumlah tamu (stepper), tanggal (min hari ini), jam (slot), catatan. Validasi inline bahasa sesuai lang aktif.
- Submit → format pesan bilingual → buka `https://wa.me/62XXXXXXXXXX?text=<encoded>` (ganti nomor asli). Contoh format:
```
Halo Ruethies Patisserie! Saya [Nama] — reservasi [N] orang, [Tanggal] pukul [Jam]. Catatan: [X]. Terima kasih! #MadeWithDelicacy
```
- Sukses: state centang + tombol `Buka WhatsApp` hijau (satu-satunya hijau yang diizinkan).

### 4.9 Footer
- Background wine-deep, teks beige/paper. 3 kolom: brand + hashtag, jam & alamat, kontak + social. Bawah: © + `ID|EN` kecil + credit tipis.

### 4.10 Ikon & Bahasa
- Ikon: line SVG 1.5px, round caps (gaya Lucide). Ukuran 18/20/24. Warna wine untuk aktif, muted untuk idle.
- Toggle bahasa: pill kecil, opsi aktif bg wine teks paper.

---

## 5. Motion & Interaction

| Jenis | Durasi / Easing | Pakai |
|---|---|---|
| Hover lift | 200ms `ease-out` | Button, card |
| Tab indicator | 300ms `cubic-bezier(.22,1,.36,1)` | Menu tabs |
| Reveal on scroll | 600ms fade + translateY 16px, stagger 80ms | Semua section |
| Marquee | 30s linear infinite | Hashtag strip |
| Modal/sheet | 250ms scale .96→1 + fade | Reservasi, lightbox |
| Parallax glow | rAF, max 12px | Hero glow saja |

- Hormati `prefers-reduced-motion`: matikan marquee/parallax/reveal (tampilkan langsung).
- Jangan ada animasi yang menghalangi baca menu/harga.

---

## 6. Imagery Direction (tanpa aset → tetap aesthetic)

- **Palette foto:** warm natural light, kayu + marmer + linen, croissant golden, latte creamy, tangan menabur gula. Hindari neon, hindari background dingin/biru.
- **Treatment wajib agar menyatu:** `contrast 1.02 + saturate .92 + overlay beige 10% + vignette lembut`. Semua foto dari Unsplash/Pexels sementara harus lewat filter ini.
- **Shot-list untuk foto asli nanti:**
  1. Hero arch: croissant + kopi di meja marmer dekat jendela.
  2. Tangan chef menabur powdered sugar (close-up).
  3. Interior ruko: sudut hangat dengan cahaya sore.
  4. Line-up éclair/box packaging dengan pita wine.
  5. Latte art top-down di atas linen beige.
- **Placeholder sementara:** gradient `beige-deep → paper` + pola arch SVG + label Cormorant italic (contoh: *Croissant au Beurre*). Jangan pakai grey box polos — merusak kesan mewah.

---

## 7. Voice & Tone (bilingual elegan)

- ID: hangat, sopan, pendek. EN: soft French-touch, tidak kaku.
- ID: `Dipanggang segar setiap pagi.` EN: `Baked fresh every morning.`
- Hindari: bahasa promo agresif (MURAH!!, DISKON GEDE), emoji di headline, capslock.
- Emoji maksimal 0 di UI, 1 di pesan WA opsional.

---

## 8. Responsive & Teknis

- **Breakpoint:** 360 / 768 / 1024 / 1280. Mobile-first. Hero stack: teks → foto arch. Menu jadi horizontal swipe. Maps tinggi 320px mobile.
- **Stack rekomendasi:** Next.js + Tailwind + CSS variables di atas. Font via `next/font`. Tanpa API key untuk fase 1.
- **SEO:** title `Ruethies Patisserie — Artisan Cafe di Cikarang | #MadeWithDelicacy`, meta ID+EN, schema.org `CafeOrCoffeeShop` dengan `address`, `openingHours`, `hasMap` (short-link), `servesCuisine: French Pastry, Coffee`.
- **Performa:** image `sizes` + lazy di bawah fold, iframe maps `loading=lazy`, total JS awal <150KB.

### Tailwind token (copy-paste)

```js
// tailwind.config extend
colors: {
  wine: { DEFAULT:'#7A0000', deep:'#560000', soft:'#A83A3A', ink:'#3D0000' },
  beige:{ DEFAULT:'#FAF6F0', paper:'#FFFDF9', deep:'#F1E8DC', line:'#E7DACA' },
  baby: { DEFAULT:'#89CFF0' },
  ink: '#2B2320', muted:'#6F625B', faint:'#A89A8E',
},
fontFamily: {
  display:['"Cormorant Garamond"','serif'],
  sans:['"Plus Jakarta Sans"','sans-serif'],
}
```

---

## 9. Checklist Kualitas (definisi "mentok")

- [ ] Satu layar satu bintang, whitespace lega.
- [ ] Tidak ada teks baby-blue. Glow baby ≤8% opacity.
- [ ] Semua foto arch + filter warm seragam.
- [ ] Tidak ada harga di website (keputusan: menu tanpa harga, tanya via WA).
- [ ] Toggle ID/EN bekerja di semua section + persist.
- [ ] Maps interaktif (zoom/pan) + tombol Buka di Google Maps ke short-link asli.
- [ ] Modal WA memformat pesan benar + nomor WA diganti nomor asli.
- [ ] Kontras AA, focus visible, reduced-motion dihormati.
- [ ] Lighthouse mobile ≥90 (kecuali maps iframe).

---

## 10. Yang Masih Butuh Darimu

1. **Nomor WA aktif** (ganti `62XXXXXXXXXX`) + **jam operasional** + **Instagram** — untuk kartu Visit + pesan WA + schema SEO.
2. **Nomor WA + jam + IG asli** — ganti dummy sebelum live.
3. Logo jika sudah ada (jika belum, wordmark Cormorant tetap dipakai).

> Setelah 3 info ini ada, website bisa di-scaffold 1:1 mengikuti guidelines ini tanpa revisi arah desain.

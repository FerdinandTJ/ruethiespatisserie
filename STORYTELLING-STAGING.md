# Snack box storytelling — staging only

A homepage section after the featured products, before the existing ordering and About sections. It is rendered only while Snackbox is public, so its CTA cannot lead to a hidden product. Existing CMS data, theme, ordering, Midtrans and Fonnte are unchanged.

Desktop uses one sticky scene and three native-scroll chapters. A single scheduled animation frame selects the nearest chapter; reverse scrolling restores the preceding stage. The scroll listener is removed while the section is outside the viewport. Mobile and short viewports use three inline, static phase scenes with a short entrance transition. Reduced motion removes transitions. Copy and CTA are server-rendered, and ID/EN uses the existing language system.

## Assets

Original local `pastel.webp`, `risol.webp`, and `brownies-cokelat.webp` are reused without recompression or overwriting. CSS masks isolate a piece of each photograph. No transparent source cutouts were available; the masks may retain small edges of the original background.

The box is a temporary SVG/CSS illustration, explicitly captioned as such. It is not a photograph of official packaging. The lid, seal and food masks are isolated in `profile_snack_scene()` and `storytelling.css` for replacement with approved transparent food/packaging assets later. No external image service or animation library is used.

Only the homepage loads `storytelling.css` and `storytelling.js`. Images are lazy loaded, sized explicitly and shared by all scene instances through the browser cache.

## Review and deployment

Local preview: `php tools/preview-profile.php --port=8945` (copied public fixture, no production accounts).

Deploy only staging using its existing updater; no upgrade CLI or database migration:

```bash
cd /home/u857797475/domains/ruethiespatisserie.com/public_html/staging
(
set -e
git fetch origin staging
STAGING_DEPLOY_SCRIPT=$(mktemp)
git show origin/staging:tools/update-staging.sh > "$STAGING_DEPLOY_SCRIPT"
bash "$STAGING_DEPLOY_SCRIPT"
rm -f "$STAGING_DEPLOY_SCRIPT"
)
```

Review desktop stages 1 → 2 → 3 → 2 → 1, sticky ending, mobile 360px, ID/EN, reduced motion, no JavaScript, and the snack box CTA. Main must not be merged or deployed as part of this change.

## Verification — 15 November 2026

- Browser review: desktop 1280/1440 and mobile 320/360/390/768, both languages, no horizontal overflow, sticky clears the following section and does not overlap the header.
- Forward/reverse stages, native CTA navigation, reduced-motion styles and no-JavaScript fallback passed; no JavaScript runtime exceptions.
- Five other public pages exclude the new markup and assets.
- `tests/seo-public.php`: public rendering, safe encoding, SEO, ETag and isolated CMS create/edit/hide/delete/FAQ/gallery flows passed.
- `tests/storytelling.cjs`, `tests/profile-preview.cjs`, `tests/product-carousel.cjs`, `tests/profile-form.cjs`, `tests/po-ui.cjs`, `tests/update-staging.cjs` passed.
- No image binaries, hosting JSON, payment configuration, admin implementation or main files changed. No live payments or WhatsApp messages were sent by these tests.

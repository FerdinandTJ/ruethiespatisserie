# Public performance — local review

Work branch: `fix/public-performance`, based on main `5986c6f`. No production deployment or push is included. Hosting JSON, account metadata, user logo changes and local redesign/PO documents are not part of this change.

## What changes

- Closed native details/summary navigation is present from the first HTML response. The panel keeps its wine/cream appearance and opens without JavaScript. Escape/outside click/focus handling progressively enhance it. There is no visible fallback panel to remove after first paint.
- Local licensed Latin WOFF2 fonts replace the public Google Fonts dependency. Regular faces are discovered/preloaded from the head, fallback metrics reduce swapping movement, and JavaScript no longer activates font CSS. Admin/legacy font configuration is retained.
- A single generated public stylesheet contains only the tokens/reset, FAQ/photo/carousel rules and profile layout used by the public redesign. Legacy styles.css remains for compatibility and 404/admin references. Edit public-fonts.css/public-base.css/profile.css, run `php tools/build-public-css.php`, and bump the public.css version when releasing changes. `--check` detects an out-of-date bundle.
- Hero, catalogue cards, story photos and gallery thumbnails use existing responsive companions, real dimensions, srcset and layout-specific sizes. Large detail images are retained. Public requests never generate files or fully decode images.
- Uploads create small WebP companions at upload time, using the already decoded image. Existing input limits/re-encoding/security are retained. Deleting an unused menu photo also removes its small companions; shared references remain protected.
- WOFF2 has an explicit MIME type and 30-day cache. Public HTML still revalidates and JSON/CMS updates retain their existing behavior. No new JS library or third-party service is added.

## Existing hosting photos

`admin/image-upgrade.php --check` validates referenced sources and reports missing/stale/invalid variants without writing files. `--apply` holds the upload lock and generates only missing/stale companions. It does not rewrite menu.json/atmosphere.json or original photos. Existing companions being regenerated are archived privately; a source hash manifest is retained. Handled failures restore replaced companions and remove newly generated outputs.

Paths are allowlisted and sources/targets must not be symlinks; existing image metadata limits apply before decode. Widths are 320/480/640/960/1280 where the source is large enough. No upscaling. The renderer falls back to existing originals if companions have not yet been generated.

Apply during a quiet admin window. Sudden process/host termination can leave a partial set of new companions, but originals remain usable; repeat check/apply to complete it. An image edit during upgrade is not supported. There is no database migration or security-account upgrade.

## Local preview and validation

Run `php tools/preview-profile.php --port=8892` from this branch. The helper copies public JSON into a disposable loopback fixture, prepares catalogue metadata on the copy, and links only public images/fonts. No private configuration/accounts are copied and source JSON is unchanged.

Required checks: SEO public HTTP/CMS, real Apache routing/security, image-variants CLI/upload fixtures, photo deletion, admin UI, preview isolation, WhatsApp form and carousel regressions, generated CSS consistency. Visual checks at 320/390/1440 and native nav behavior with/without JavaScript. Cold-cache throttled recordings and repeated Lighthouse results are summarized in PERFORMANCE-RESULTS.md.

## Release after review approval only

1. Back up code, hosting JSON and private data outside web root. Record the old commit. Preserve hosting modifications before pulling, especially .htaccess and CMS data; do not overwrite them with laptop JSON.
2. Merge the reviewed branch into main/push only when requested. Pull using the site's content-preserving deployment procedure. Keep newly changed routing/MIME/cache rules while retaining any hosting-only directives.
3. Set normal public file permissions (644 files, 755 directories). Ensure fonts/, public.css and companions are readable. Never apply public permissions recursively to private data/backups.
4. Run `php admin/image-upgrade.php --check`, then `--apply`, then `--check`. This requires GD/WebP, already used by existing uploads. No phpMyAdmin import or account reset is required.
5. Purge relevant HTML/CDN cache and smoke-test all pages, menu, language, WhatsApp, FAQ, carousel and CMS. Run fresh PageSpeed tests on production and compare medians. Local localhost measurements do not reproduce Hostinger/CDN latency or real-user Core Web Vitals.
6. Roll back code to the recorded release if needed. Original images/hosting JSON do not require restoration because they were not overwritten. Restore only matched code/content deployment backups if a separate change requires it; never restore old account/auth metadata. Companion files can remain harmlessly unused by the old renderer.

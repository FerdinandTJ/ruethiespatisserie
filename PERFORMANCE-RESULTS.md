# Performance results — local review, 13 November 2026

No production change or push was performed. The owner-provided production screenshot (72 / CLS 0.513 / LCP 3.0 s) is useful context, not the numeric baseline in this controlled comparison. Lighthouse Viewer URLs did not expose a saved immutable report to the available reader.

## Matched Lighthouse comparison

Three cold-storage navigations per page/version, median below. Lighthouse 13.0.1, Chrome 157, mobile 390×844 / DPR 1.75, Lighthouse simulated mobile defaults (150 ms RTT, 1638.4 Kbit/s, CPU 4×), same Mac and PHP loopback server. The owner's report used Lighthouse 13.6.0; scores across those environments are not directly comparable. Local server has no Hostinger/CDN round trip and no compression, so these are not production Core Web Vitals. Complete medians: performance-results.json.

| Page | Performance before → after | CLS before → after | LCP before → after | Transfer before → after |
|---|---:|---:|---:|---:|
| Beranda | 95 → 98 | 0.0003 → 0.0000 | 2.93 → 2.48 s | 600 → 318 KiB |
| Menu | 99 → 100 | 0.0500 → 0.0000 | 1.88 → 1.80 s | 718 → 367 KiB |
| Snack Box | 99 → 99 | 0.0126 → 0.0000 | 2.10 → 2.25 s | 253 → 206 KiB |
| Tentang Kami | 95 → 99 | 0.0602 → 0.0000 | 2.85 → 2.25 s | 481 → 253 KiB |
| Hubungi Kami | 100 → 100 | 0.0004 → 0.0000 | 1.50 → 1.50 s | 158 → 111 KiB |

TBT stayed 0 ms in these Lighthouse runs. Accessibility/SEO scores were maintained (menu accessibility was already 98; the other four were 100). Best Practices was 96 on both versions because this intentionally minimal preview lacks the manifest's old logo.jpeg icon; that fixture limitation does not diagnose production. Fonts, header logo and product imagery required for the review were present. No production score is claimed.

Not every page's LCP must improve numerically: local fonts are discovered sooner to avoid late swapping, and request scheduling differs. The final median meets the 2.5 s LCP and 0.1 CLS targets in this local run, but production must be checked after a reviewed release. No ranking or guaranteed Lighthouse score is implied.

## Observed layout-shift cause

Separate CDP recordings used real applied network throttling (150 ms, 1.6 Mbit/s, CPU 4×), cache cleared, and an observer installed before navigation. Baseline contact runs 2 and 3 produced CLS 0.5018: main#home moved from y=528.078 to y=73 when the initially visible nav was closed by JavaScript. This was intermittent and absent from some Lighthouse recordings, so a low median alone would have missed it. The final native disclosure has no initial open state and never removes an in-flow fallback panel. Font fallback metrics address smaller later shifts.

All 15 final applied-throttling recordings had CLS 0; none requested the map before interaction. The final browser recording observes load/font completion plus 1.6 seconds (25-second cap) and does not replace real-user monitoring. No map request is made before interaction. Review includes 320/390/1440 px screenshots and no-JavaScript native navigation, keyboard/Escape/outside-click, ID/EN, form validation, gallery and deferred carousel checks.

## Regression commands

```sh
php tests/seo-public.php
php tests/seo-routing.php
php tests/admin-ui.php
php tests/image-variants.php
php tests/menu-photo-delete.php
node tests/profile-form.cjs
node tests/product-carousel.cjs
node tests/profile-preview.cjs
php tools/build-public-css.php --check
```

Raw local Lighthouse JSON is retained under /private/tmp/ruethies-lighthouse-before/ and /private/tmp/ruethies-lighthouse-optimized/. The benchmark was run with a temporary Lighthouse installation outside the repository; it adds no website runtime dependency. See PERFORMANCE-UPGRADE.md before any eventual deployment. Safari on physical hardware and production CDN/TTFB remain unverified.

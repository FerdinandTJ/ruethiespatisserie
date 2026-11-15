# Homepage ordering story — staging only

Only the existing “Pesan dalam tiga langkah” homepage section changes. The three original instructions, WhatsApp URLs, CTA, SEO text, other sections, admin and pre-order/payment/notification code are preserved. The ordering page retains its original static steps. Phase 2 category animation is not included.

## Implementation

- `public-lib/profile.php`: optional interactive presentation of the existing step list; reusable decorative HTML/SVG card. Assets are included only on the homepage.
- `order-story.css`: wine/cream card, sticky two-column desktop flow, a shared mobile card with inline fallback, transitions, menu-pill stagger, typing letters and SVG check.
- `order-story.js`: scoped native IntersectionObserver, `data-active-step`, reversible 1/2/3 states, one-time typing and existing ID/EN data attributes.
- `tools/preview-profile.php`: includes these assets in its disposable public preview.
- `tests/order-story.cjs`, `tests/seo-public.php`: observer/replay/i18n and HTTP/CMS regression coverage.

No photos, fonts, libraries, account changes, database upgrade or CMS content migration. The mock chat is decorative and explicitly labelled as an ordering-flow illustration; it does not send a WhatsApp message or imply an order was accepted.

Desktop >=900px uses 78vh chapters and one sticky card. Phones/tablets below 900px and at least 740px tall use the same shared card (240px) beneath the header, plus a decorative 01–02–03 indicator. Its observer focus band is placed in the free reading area below the card and above the mobile WhatsApp bar. Shorter screens retain the normal vertical flow with inline cards. Without JavaScript, mobile also retains that inline fallback. Desktop screens shorter than 700px use the compact fallback. Card states have fixed dimensions; only transform/opacity animate except the specifically requested SVG check stroke-dashoffset.

Inactive body copy stays fully opaque. Large inactive headings use reduced opacity while preserving the AA contrast requirement for large text; decorative numbers may be lighter. Reduced motion removes typing, stagger, fades and check animation. Without JavaScript, all three instructions remain readable.

## Review

Run `php tools/preview-profile.php --port=8947` for a copied public fixture. Scroll the homepage ordering section through 1 → 2 → 3 → 2 → 1. Check 1440px, 768px and 375px; toggle ID/EN, including during typing. Enable reduced motion and disable JavaScript. No WhatsApp action is required to test the illustration.

Chat copy follows the supplied brief: 20 snack boxes for Saturday, followed by a response confirming that price and availability will be checked. These are illustrative examples, not order conditions. There is no blocking business decision; these example messages can be revised during review.

Use the existing `tools/update-staging.sh` deployment workflow after fetching origin/staging. No upgrade CLI is required. Do not merge this presentation experiment into main unless approved.

## Verification — 15 November 2026

- Browser: desktop 900/1440 × 900, mobile/tablet 320/375/768; forward/reverse states and sticky ending passed without overflow or header collision.
- Card dimensions remained constant across all phases. Local phase-transition measurements recorded CLS 0 and no long tasks. This is not a new hosted Lighthouse score or field-performance claim; language-switch reflow was evaluated separately from animation.
- ID/EN, one-time typing, no JavaScript, reduced motion and five unaffected public routes passed. Browser reported no JavaScript runtime exceptions.
- Public/CMS HTTP regression, preview isolation, form WhatsApp, product carousel, observer unit tests and PO UI tests passed.
- Combined new CSS/JS transfer is approximately 3.7 KB with gzip. No image or font requests are introduced.


## Mobile refinement

One continuous mobile story replaces the three repeated visual cards on tall screens. The selected menu pill shrinks as the example message fades in, followed by the reply/check. Each chapter remains native scrolling, with reversible states and no scroll lock. The desktop presentation, existing copy and real WhatsApp actions are unchanged.


Mobile QA: 320×740, 375×812, 390×844, 768×1024 and 899×900 cover the shared-card presentation. Short-screen fallback covers 375×667 and 768×600. Forward/reverse steps, header/card/text/WhatsApp separation, indicator state, ID/EN, reduced motion and no-JavaScript fallback are verified locally. Desktop 1440×900 retains its prior presentation. Card size stays fixed during state changes.

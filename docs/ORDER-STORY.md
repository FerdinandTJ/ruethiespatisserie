# Ordering story on main

The homepage's existing three-step ordering section now uses the presentation validated on staging: one sticky HTML/SVG card and reversible scroll states. Tall phones/tablets use one 240px card plus a 01–02–03 indicator. Short screens and mobile without JavaScript retain three inline illustrations. The ordering page retains its original static instructions.

Colours inherit main's existing blue/cream variables, including accessible dark blue for text and selected pills. Card shadows use blue rather than staging's wine tint. Existing branding, navigation, SEO text, WhatsApp destinations and CMS JSON remain unchanged. No pre-order, Midtrans or Fonnte functionality is imported.

New shared assets: `order-story.css` and `order-story.js`, loaded only on the homepage. Rendering is in `public-lib/profile.php`; tests cover observers, one-time typing, language changes, native HTML and CMS regression. No image files, fonts, libraries or database migrations are introduced.

The chat is labelled as an illustration and sends no message. Reduced motion removes typing/stagger/transitions; all instructions remain readable without JavaScript. Scroll remains native and can be reversed freely.

Deployment: back up hosting content/private data and inspect `git status --short` before pulling main. Preserve hosting JSON and any CMS changes if Git reports a conflict; do not replace them with laptop data. No upgrade CLI or phpMyAdmin import is needed. Check the homepage section at 375px, a short screen, and desktop, then toggle ID/EN and verify normal WhatsApp links.

Verification on 15 November 2026: PHP/JavaScript syntax, public HTTP/CMS regression, isolated preview, observer/replay/i18n unit tests, form WhatsApp and carousel checks passed. Other main renderer content/navigation was compared with the previous main revision and remained identical outside the intended section and its asset links. Theme checks verify main's #8ACEF1 branding and dark-blue bubble; no PO navigation is present. No changes to hosting data are required.

Final browser checks passed at desktop 1440×900 and mobile 375×812, after wider coverage at 320/390/768/899px. Shared-card forward/reverse states, ID/EN, header/WhatsApp separation, sticky ending, short-screen fallback, reduced motion and no-JavaScript flow passed without runtime exceptions. Final phase-transition samples recorded CLS 0. A fixed-size decorative brand slot and the already-used normal serif style prevent late font metrics from moving the card header. The sticky surface matches the existing main section tint, including an opaque fallback for browsers without color-mix support.

# Ruethies public palette preview

Branch `feat/ruethies-blue-cream` includes the pending menu-copy/Lapis Marble routing revisions. No push/deployment is included in this preview.

- Primary blue: `#8ACEF1` (buttons, selected states, footer).
- Primary cream: `#FFF3E1` (page background/header).
- Dark blue ink: `#143E55` (headings, text on blue, wordmark tint).
- Link blue: `#225C79`; secondary text: `#4B6471`.

Light blue on cream has only 1.57:1 contrast, so readable foregrounds use dark blue rather than light blue or white. Ink on primary blue measures 6.58:1; link blue on cream 6.66:1; secondary text on cream 5.70:1. These are calculated solid-color pairs, not a complete accessibility certification.

The existing transparent wordmark is tinted using a CSS alpha mask, preserving the source logo geometry and files. A text fallback exists for browsers without CSS masking. Brand icon/photographic files remain original. No product-photo files, dimensions, compression, cropping, image routing or loading strategy are modified by the palette commit. Admin CSS/UI remains unchanged.

Public CSS compatibility variables retain their legacy names, with blue/cream values. Browser theme-color and manifest colors match the new palette. Public/404 CSS URLs are versioned for cache refresh.

Preview: `php tools/preview-profile.php --port=8901` from this branch, then http://127.0.0.1:8901/. The helper uses a disposable copy of public JSON; hosting data is untouched.

Checks: seven public page types at 320/390/1440 px without horizontal overflow, exact computed background/button colors, mobile navigation and ID/EN, readable footer, SEO/public CMS and catalogue checks, and admin regression. Production/Safari physical-device checks remain pending release review.

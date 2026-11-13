# Local brand fonts

Latin WOFF2 subsets from Google Fonts, fetched 2026-11-13 over verified HTTPS. Family names/design unchanged. The accompanying OFL licenses permit redistribution.

- Cormorant Garamond regular source (variable): https://fonts.gstatic.com/s/cormorantgaramond/v21/co3bmX5slCNuHLi8bLeY9MK7whWMhyjYqXtKky2F7g.woff2
- Cormorant Garamond italic source (variable): https://fonts.gstatic.com/s/cormorantgaramond/v21/co3ZmX5slCNuHLi8bLeY9MK7whWMhyjYrEtImSqn7B6D.woff2
- Plus Jakarta Sans regular, weights 400–700: https://fonts.gstatic.com/s/plusjakartasans/v12/LDIoaomQNQcsA88c7O9yZ4KMCoOg4Ko20yygg_vb.woff2

Discovery uses the matching Google Fonts CSS2 family/range query. Two regular faces are preloaded; italic is fetched only when needed. Use a new filename if updating font binaries, because WOFF2 responses are cached for 30 days. Other Unicode scripts use the system fallback. Public CSS sources are public-fonts.css, public-base.css and profile.css; regenerate with php tools/build-public-css.php.

Fallback x-height ratios use Cormorant (386/1000) vs Georgia (986/2048), and Jakarta (536/1000) vs Arial (1062/2048). Line metric overrides use the brand hhea ascent/descent, adjusted for those ratios. Local fonts are optional fallbacks: platforms without Georgia/Arial continue to the system serif/sans-serif.

Cormorant regular/italic are instantiated at weight 400 using fontTools from the original variable Latin files above; this is the only serif weight used by the public profile. Plus Jakarta Sans retains its variable weights 400–700. The unmodified OFL license is distributed with both.

# /assets/images

Drop the real event artwork here when it is available:

| Suggested file | Used for |
|----------------|----------|
| `ssc-logo.png` | Supreme Student Council seal (navbar + receipt letterhead) |
| `batstateu-logo.png` | University seal (receipt / report letterhead) |
| `sigla-hero.jpg` | Optional hero background photo |
| `qr-sample.png` | Static QR image, if you prefer it over the generated SVG |

The prototype currently ships **zero binary assets on purpose** — every visual
(brand mark, key-visual spheres, ticket, QR code) is drawn with CSS or inline
SVG, so the project runs from a fresh copy with no missing-image placeholders.

To use a real logo in the navbar, replace the `<span class="brand-mark">` block
in `layouts/public_nav.php` and `layouts/sidebar.php` with:

```html
<img src="<?= url('assets/images/ssc-logo.png') ?>" alt="SSC" width="44" height="44">
```

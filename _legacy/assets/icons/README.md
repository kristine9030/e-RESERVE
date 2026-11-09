# /assets/icons

Iconography is served by **Font Awesome 6.5** from a CDN (see
`layouts/head.php`), so no icon files are required to run the prototype.

The browser-tab favicon is an inline SVG data URI, also declared in
`layouts/head.php`.

Use this folder if you later want to:

- self-host Font Awesome (copy its `webfonts/` + `css/` here and update the link)
- add PWA icons (`icon-192.png`, `icon-512.png`) alongside a `manifest.json`
- ship custom SVG marks for GCash / PayMongo branding on the checkout page

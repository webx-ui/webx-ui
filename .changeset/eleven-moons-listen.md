---
'@webx-ui/php': minor
---

`webx-ui/module-inbox`: the form on the site.

`<x-webx-form slug="contact" />` prints a form of the panel — a control per field type, the
honeypot, the hidden timestamp, the captcha block a form asks for — out of views the site
publishes and rewrites, with no stylesheet and no design tokens of ours following it there.

It works with JavaScript switched off: the form posts, and the page comes back with the errors
under their inputs or the thank-you in place. The script adds only that this happens without a
reload; it is one file with no dependencies, served from the package.

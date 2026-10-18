---
'@webx-ui/php': minor
---

`webx-ui/module-media`: an uploaded SVG can no longer run scripts on the site.

- **Types come from the bytes.** The extension a file is kept under and its `mime` are read from its content (`WebxUi\Media\Support\FileTypes`), not from the name or the type the client sent. A file whose name disagrees with its content is kept under the extension its content has (a PNG named `x.html` becomes `.png`); one whose content makes no listed type is refused. Browser documents — `html`, `xml`, `js`, `svgz`, PHP — are refused even when added to `upload.extensions`.
- **SVG is sanitized on upload** (`WebxUi\Media\Support\SvgSanitizer`, on top of the new dependency `rhukster/dom-sanitizer`, MIT): `<script>`, `on*` handlers, `<foreignObject>`, `javascript:` and `data:` addresses and anything pointing outside the drawing are removed; every `href` must be `#fragment`, except an inline PNG/JPEG/GIF/WebP in `<image>`. An SVG that is not a well-formed drawing is refused with a validation message. The stored file is the sanitized one, so its bytes and hash differ from what was sent. This applies to the panel's uploads, chunked uploads and `media_upload_from_url`.
- **`php artisan webx:media:sanitize-svg [--dry-run] [--id=…]`** runs SVGs already in the library through the sanitizer, keeps their keys, and lists file by file what was removed; it also lists SVGs it cannot sanitize and files kept under an extension the library no longer accepts.
- **Headers.** `files/{file}/source` answers with `X-Content-Type-Options: nosniff` and, for SVG, `Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox` (`WebxUi\Media\Support\ServeHeaders`). The public disk is served by the web server: the site skeleton's `docker/nginx.conf` and `public/.htaccess` now set the same headers on `/storage/`; existing sites add them by hand (module README, «Serving files»).

`webx-ui/module-inbox`: an attachment's `mime` is read from its bytes, not taken from the visitor's browser.

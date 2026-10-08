---
'@webx-ui/module-media': minor
'@webx-ui/php': patch
---

«Optimize» can convert to WebP. Ticked in the dialog (or `convert: true` of
`media_optimize_images`, or `php artisan webx:media:webp`), a still JPEG or PNG — and a HEIC where
Imagick reads it — becomes a WebP under the same uuid when that is smaller, and every reference to
the old key is rewritten in the same transaction — blocks, rich text, settings, versions and the
journal; a foreign key needs nothing. The old key stays as an alias: its public
address answers 301 and `files/by-path` finds the file by it. Previews and rendered caches are let
go of; «Restore original» re-encodes the kept original into the current format. Usage sources can
take part in a rewrite through `UsageRewriter`. The folder tree shows a placeholder while it loads
instead of an English «Nothing here yet».

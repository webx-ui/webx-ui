---
'@webx-ui/php': patch
---

A block's template is handed what the field type makes of a value, not the row as stored — the
same way a screen's values are read for the site. A `wx-media` field keeps `{ path, alt, title }`
and the template now also gets `url`, worked out when the block is printed, so the picture no
longer has to be assembled from the disk's configuration inside the Blade. A value whose type
nobody registered — `wx-blocks` above all — and a value whose key the schema does not name pass
through as they are; a field inside `wx-repeater` or inside layout is resolved too, and the
preview, the panel's own drawing and the check before publishing all see the same values as the
page. The media field remembers the files it looked up for the length of one response, so a
gallery costs one query per picture rather than one per mention.

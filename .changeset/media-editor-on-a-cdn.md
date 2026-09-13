---
'@webx-ui/module-media': minor
'@webx-ui/admin': patch
'@webx-ui/php': minor
---

Three things found by putting the library on S3 behind a CDN.

The image editor could not open a picture at all. It draws onto a canvas and writes that canvas
out, which a browser refuses for bytes fetched from another origin without CORS headers — and a
private bucket cannot be given those headers for the panel in any case. So `webx-ui/module-media`
serves the picture itself at `files/{id}/source`, behind the same permission as the listing, and a
`MediaFile` now says where that is. `url` stays what everything that only looks at a file uses.

The editor also spoke English in a Russian panel: it is a component of the design system, so its
words are props, and the manager was not passing any. It has its own ten-language group now, as
does the question the card asks before deleting one file.

`@webx-ui/admin`: changing the language renames the sections too. Titles are translated on the
server and travel in the manifest, which was fetched in the previous language — so the panel used
to switch everything except its own navigation until the page was reloaded.

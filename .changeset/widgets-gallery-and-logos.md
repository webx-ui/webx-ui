---
'@webx-ui/php': minor
---

Two blocks from `webx-ui/widgets`, offered to `module-blocks` on a site with the media library and installed by `webx:setup` (or `php artisan webx:blocks:offered --install --module=widgets`): **Gallery** — pictures of the library as a grid, as many columns as the column it stands in has room for, or as the slider's gallery with thumbnails; a click opens a picture in the lightbox and pages through that block's pictures; the title of a picture is its caption. **Logos** — a name, a logo and a link each, in the slider's running strip with its pause button. Once installed they belong to the site, like every offered block.

The demo seeds a theme's `demo/media/` into the library, and a theme's demo page names one of those pictures as `"path": "demo:<file name>"`; the blocks demo installs the offered types the theme's pages stand on. `theme-default`'s kitchen sink shows the gallery and the logos as the real blocks, on pictures of the library, and no longer publishes its showcase pictures on every site.

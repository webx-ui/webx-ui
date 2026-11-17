---
'@webx-ui/php': patch
---

widgets: the gallery's ribbon with `zoom` opens a frame without a link of its own over the page — one group for the ribbon, captioned by the picture's alt, else its title — while a frame with a link still goes to its post, and a mouse drag of the strip opens neither; the switch is offered for the ribbon too. The parallax of a picture (hero, call to action, text with tiles, banners) moves again inside `<picture style="display: contents">`, the wrapper of the library's formats: the script measures and watches the frame past the box-less wrapper. Re-import the gallery type on a site: `php artisan webx:blocks:import --path=vendor/webx-ui/widgets/resources/blocks --replace=gallery --publish`; the parallax needs no re-import.

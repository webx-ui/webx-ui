---
'@webx-ui/php': patch
---

widgets: the arrows of a gallery mosaic carousel set under the head (`arrows_at: head`) stand above the pictures again: the rows of the slider's grid are explicit, since its slides keep the first cell for an overlay. A site that installed the Gallery type before takes the fix with `php artisan webx:blocks:import gallery --path=vendor/webx-ui/widgets/resources/blocks --replace=gallery --publish`.

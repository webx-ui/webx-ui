---
'@webx-ui/php': patch
---

theme-default and widgets: the first `hero` of a page fetches its picture and its badge first (`fetchpriority="high"`, decided per request by `WebxUi\Widgets\FirstScreen`), and the pictures of a `gallery` mosaic, still and carousel, wait until they near the screen (`loading="lazy" decoding="async"`). A site gets them with `php artisan webx:blocks:import hero --theme --replace=hero --publish` and `php artisan webx:blocks:import gallery --path=vendor/webx-ui/widgets/resources/blocks --replace=gallery --publish`.

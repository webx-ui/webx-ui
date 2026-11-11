---
'@webx-ui/php': minor
---

Widgets: `<x-webx-slider pagination="fraction">` — a counter "1/6" between the arrows in place of the dots (`.webx-slider__count` with `__current` and `__total`), for the photos of a flat or a product. The script follows the slide in view by `realIndex`, with a loop and without; the copies of a running strip are not counted. It is hidden from a screen reader — the slides are named "2 / 6" and Swiper's live region announces the move — hidden like the dots when every slide is in view, absent with one slide; without JavaScript the controls stay hidden as before. `pagination` true, false and `bullets` mean what they did; any other value is an error. Theme-default: Kitchen sink › Slider › Photos with a counter.

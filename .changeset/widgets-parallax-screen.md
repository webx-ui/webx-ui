---
'@webx-ui/php': minor
---

widgets: the parallax of the first screen — `data-webx-parallax="screen"` moves the picture down k · scrollY and zooms it to 1 + z from the top of the page until its frame is gone, also while a section slides over it and pins it (`overlap` now writes `--webx-overlap-gone` and tells `webx:overlap`); its overscan is above the frame, never short of the way. Knobs `--webx-parallax-screen-rate`, `-rate-narrow`, `-zoom`, `-overscan`. theme-default: the page's first hero with `height: screen` prints `screen` before the strength.

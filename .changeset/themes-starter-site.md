---
'@webx-ui/php': minor
---

A theme can bring demo content: `webx:demo` takes `demo/pages/home.json` and `about.json` from the
first theme layer that has them, and seeds every other document there as pages under the home
page (with `children`), removed by `webx:demo --remove` like the rest. `webx-ui/theme-default`
brings the first showcase pages ("Kitchen sink": long words, a dark block in the main column).
The menu demo links only the top of the pages it finds, not the pages under them. In the default
theme, prose sets a text colour with every background it paints, and paints its muted colour
outside blocks only (or under `site-prose` inside one). `scripts/starter-site.sh` rebuilds the
starter site from the skeleton.

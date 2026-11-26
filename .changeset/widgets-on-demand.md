---
'@webx-ui/php': minor
---

widgets: the scripts of the slider, the lightbox, the map, the form and its parts and the other
widgets that start on an element of their own are fetched on demand — when the element comes
within 200px of the window or is touched first; a click before the script is held and given
again. A page where none comes near fetches none of them. `data-webx-eager="<widget>"` or
`Widgets::eager()` loads one at once (WIDGETS §4.1). The overlap edge drops
`fetchpriority="low"`: right under a short hero it was the largest paint, and waited behind
every script of the page. theme-default: the showcase page «First screen».

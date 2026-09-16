---
'@webx-ui/module-media': minor
---

Three more media fields: `wx-gallery`, `wx-file` and `wx-files`.

A gallery is a grid of thumbnails in the order somebody dragged them into, `wx-files` the same
list as file cards, `wx-file` one of those cards. All three store what `wx-media` stores —
`{ path, alt?, title? }` — singly or as a list, so the rules, the storage and the resolve are
written once. `openMediaFiles({ accept: 'image', max: 10 })` is the library opened from code with
multiple selection on, resolving with `MediaFile[]`.

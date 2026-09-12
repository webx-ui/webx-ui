---
'@webx-ui/core': minor
---

Add `WxImageEditor` and `openImageEditor()`.

A picture, a rectangle over it and a blob at the end: a crop with the eight grips everybody knows,
ratios (locked with `aspect`, or picked from `ratios`), quarter turns, mirrorings, and an output
size capped by `maxWidth` / `maxHeight`. It uploads nothing — `save` carries the blob, a named
`File`, the measurements and the crop it was cut from, so a server can arrive at the same picture
from the original. Everything on screen is geometry and the picture is drawn exactly once, at the
end, under a single transform.

`openImageEditor({ src })` is the editor in a dialog, awaited: it is `createModal` over it, and the
answer is `undefined` when the panel is closed without saving. The edit action on `WxFileCard` now
has something to open.

Also: `rotate-left`, `rotate-right`, `flip-horizontal` and `flip-vertical` in the icon set, and
`createModal<T, P>` now takes any object as `P` — an `interface` of props included, which the old
`Record<string, unknown>` constraint turned away for want of an index signature.

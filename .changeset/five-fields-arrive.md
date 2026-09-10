---
'@webx-ui/core': minor
---

Five secondary form controls: `WxRate`, `WxSlider`, `WxTagsInput`, `WxDateRangePicker` and
`WxColorPicker`.

`WxRate` is stars with optional halves, cleared by clicking the current value and moved with the
arrow keys. `WxSlider` covers one thumb or two, with marks under the track, and keeps a plain number
in the model for the single case rather than a one-element array. `WxDateRangePicker` stores
`[start, end]` in the backend's format and shows two months at once. `WxColorPicker` is a hex field
with the colour in it, opening a saturation square, a hue strip and preset swatches.

`WxTagsInput` is written here rather than wrapped: Enter adds what was typed, Backspace marks the
last tag and removes it on the second press, and suggestions come from a `search` event so the list
can live on a server.

---
'@webx-ui/core': patch
---

Floating panels share one layer, so a list opened inside a panel is not swallowed by it.

`WxDropdown`, `WxSelect`, `WxAutocomplete`, `WxCascader` and `WxTagsInput` drew their panels on
`--wx-z-index-dropdown`, below `--wx-z-index-popover` — so a select inside a popover had its list
disappear behind the panel it was opened from. Ranking panels by kind cannot work: what has to be
on top is whatever was opened last, whichever kind it happens to be. They all draw on
`--wx-z-index-popover` now, and since a panel is added to the document when it opens, the order of
opening decides. `--wx-z-index-dialog` and above are unchanged: those are for surfaces that take
over the page.

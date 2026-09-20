---
'@webx-ui/core': minor
---

`WxButton` takes an `icon` name

The button had an `icon` slot and no `icon` prop, so `<wx-button icon="plus">` — which is how two
dozen call sites across the panel wrote it — fell through to the `<button>` element as an attribute
and drew nothing at all. Every other component that carries a picture beside its label takes the
name (`WxDropdownItem`, `WxMenuItem`, `WxTab`), and this one now does too: `icon?: IconName` renders
a `WxIcon` into the existing `.wx-button__icon` span. The slot still wins when both are given, and
`loading` still replaces both with the spinner.

The call sites themselves were repaired in the meantime — the head of a screen draws its actions
from `ScreenAction.icon` now — so this is the missing prop rather than a fix to any of them.

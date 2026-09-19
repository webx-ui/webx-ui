---
'@webx-ui/core': minor
'@webx-ui/module-auth': patch
'@webx-ui/module-seo': patch
---

`WxButton` takes an `icon` name

Twenty-three call sites across the panel wrote `<wx-button icon="plus">`, but the button only had
an `icon` slot: the attribute fell through to the `<button>` element and drew nothing. Rather than
rewrite them all, `WxButton` now has a real `icon?: IconName` prop that renders a `WxIcon` into the
existing `.wx-button__icon` span; the `icon` slot still wins when both are given, and `loading`
still replaces both with the spinner. Three of those call sites also asked for `add`, which is not
an icon in the set — they now ask for `plus`.

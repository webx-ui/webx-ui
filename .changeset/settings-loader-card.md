---
'@webx-ui/module-admin': patch
'@webx-ui/module-settings': patch
---

Settings wait in the shape of what comes: a card with lines, while the values load and then while the screen does, instead of bare lines on the page's background. `WxScreen` (the panel's screen by name) takes a `loading` slot for that; without one it draws the lines as before.

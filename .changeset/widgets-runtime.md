---
'@webx-ui/php': minor
---

New library `webx-ui/widgets`: the interactive pieces of the public site, as the bottom layer of
the theme chain. This first release holds the loading — `@webxTheme` links the widgets'
stylesheets first in the cascade and their scripts before `</body>`, a widget's own files only on
the page that claims it with `Widgets::need()` — and the shared runtime with the light
behaviours: `data-webx-disclosure`, `data-webx-dialog` / `<x-webx-dialog>`, `data-webx-tabs` /
`<x-webx-tabs>`, `data-webx-accordion`. `webx.mount(root)` starts both blocks and widgets inside
`root`, `webx.unmount(root)` lets the widgets go. `webx-ui/theme-default` requires the package,
so every new site gets it. The theme engine gains `Contracts\HeadPart` and `BottomLayers`, and
`webx:theme:sync` publishes the widgets' `dist/` next to the themes'. The blocks runtime now joins
an existing `window.webx` instead of replacing it.

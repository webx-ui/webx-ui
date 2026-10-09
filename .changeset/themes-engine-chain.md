---
'@webx-ui/php': minor
---

New library `webx-ui/themes`, the engine of site themes, starting with the chain. A theme is a
`"type": "webx-theme"` package describing itself under `extra.webx.theme`, or a local `theme/`
with the same keys in `theme.json`; `webx-themes.theme` (`WEBX_THEME`) names the top of the chain
and `uses` the layers below it. Blade then looks a view up in `resources/views`, the local theme,
the packaged themes and only then the module — for `<ns>::` views too, with the site's
`resources/views/vendor/<ns>` still first. A loop in `uses` or a missing theme fails at boot; an
empty setting leaves the site exactly as it was.

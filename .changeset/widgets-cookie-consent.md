---
'@webx-ui/php': minor
---

`webx-ui/widgets`: cookie consent. A banner on every page with a theme until the visitor answers
("Reject all" and "Accept all" of one class, "Customize" opens a dialog with a switch per
category; a category nothing on the site uses is not offered), its own `dist/consent.js|css`
(≈3 KB gzip). The answer is the first-party cookie `webx_consent` with the policy version — a new
version asks again — read on the server by `Consent::has()`. Third-party code waits for its
category (`script type="text/plain"`, `iframe data-src`, `<x-webx-consent>`); taking an answer
back reloads the page; Google Consent Mode v2 and Global Privacy Control; `webx.consent` and the
`webx:consent` event. Set up on the "Cookie" tab of `module-settings` (a screen patch, reachable
over MCP) or in `config/webx-widgets.php`. `theme-default` puts `<x-webx-consent-link>` in its
footer and adds a showcase page about consent.

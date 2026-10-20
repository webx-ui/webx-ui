---
'@webx-ui/php': patch
---

The pages the panel serves outside its Vue app — the consent screen of an agent's connection
(`/oauth/authorize`), the plain one of `webx-ui/mcp`, the gate of a closed site — share one frame,
`webx-admin::page`, and draw the panel's own card, alert, checkbox, buttons and link by their
classes. The rules come from the components of `@webx-ui/core` (generated into
`resources/css/components.css`), so the buttons are the panel's size, font and colour; the frame
also links the stylesheets the site built for the panel (`PanelStyles`), which brings the panel's
typeface. A refused consent screen — an unknown application, an unregistered address — is a page
of the panel for a browser instead of an OAuth error in JSON; a program still gets the JSON.

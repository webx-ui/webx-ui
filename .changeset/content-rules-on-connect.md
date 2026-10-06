---
'@webx-ui/module-auth': patch
'@webx-ui/php': patch
---

The rules for agents moved from the settings to «Connect an agent»: tone, what never to say and
notes are a card there, with «Save» in its footer, shown when `module-settings` is installed and
to whoever may see the settings. They are the screen `settings.content` now, with
`GET` / `PUT /api/cms/settings/content`; stored and read as before, the MCP resource unchanged.
A project that patched `content-card` on `settings.index` patches it on `settings.content`.

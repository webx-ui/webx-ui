---
'@webx-ui/php': minor
---

`webx:panel --sync` (and so `webx:setup`) writes `.mcp.json` in the site: one server, named after
the site's address and pointing at its own MCP endpoint, so that Claude Code opened in a site's
folder connects to that site and no other. Other servers in the file, and a key renamed by hand
over the same address, are kept; a file that is not JSON is left alone with a warning.

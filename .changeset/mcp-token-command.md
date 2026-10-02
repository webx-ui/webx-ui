---
'@webx-ui/php': minor
---

`webx:mcp:token --name= [--scopes=] [--admin=] [--json] [--revoke-existing]` issues a personal access token for the MCP server without the consent screen — for a script that made the site and wants into it next. The token acts as an administrator (the first active super administrator unless `--admin` names one); without scopes, or with `all`, it carries `mcp:use` and reaches whatever that account may do, while named scopes are checked against what the installed modules declare. Only the token is printed — or `{"token","type","scopes","expires_at"}` with `--json` — and every refusal is a non-zero exit. The PHP smoke run gained a scenario that makes a site the way a hosting platform does: `webx:setup` with every answer given, the skeleton's Docker image, `webx:doctor --strict` inside it, a token, `webx:module:add` and a rebuild from the layer cache.

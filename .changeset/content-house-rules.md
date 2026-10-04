---
'@webx-ui/php': minor
---

Content house rules for MCP agents. `webx-ui/module-settings` gets a «Content» tab in Settings — tone of voice, don'ts, notes for the agent (`content.tone`, `content.donts`, `content.notes`) — and serves them, together with the site's languages from `webx-ui/localization`, as the MCP resource `settings://content-rules`. The MCP server's instructions (`webx-ui/mcp`) tell every agent to read it before writing anything a visitor will read, whenever the resource is served. The demo fills the rules in; `AGENTS.md` of both packages names the resource and the keys.

---
'@webx-ui/php': minor
---

Keys for programs. `webx-ui/mcp` gets the `mcp_keys` table and `Keys` (which key a call came with, its last use, revoking it, and whether the caller is a program at all); a tool outside a key's scopes is now left out of `tools/list`. `webx-ui/module-auth` issues keys through one `KeyIssuer` for the panel (`/api/cms/auth/keys`, behind `admins.manage`) and for `webx:mcp:token`, which gains `--days` (1–3650, a year by default) and lists its keys in the panel. `webx-ui/module-tasks` no longer takes a key issued without scopes for a person: it carried `mcp:use` like an OAuth connection and could accept a stage marked `human` or answer an agent's question.

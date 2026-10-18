---
'@webx-ui/php': minor
'@webx-ui/module-auth': minor
---

Keys for programs never reach further than their owner, and roles can be made through MCP (WEBX_UI_PERMISSIONS.md §7, §19).

`webx-ui/module-auth`:

- A key carries only the scopes its owner's permissions reach: `mcp:use` and each scope with at least one tool the owner may use. Anything else is refused with a validation error on `scopes` — in the panel and in `webx:mcp:token` alike, whoever issues it. `GET auth/keys/scopes?admin=` says which scopes a key for that administrator may carry. Every call is still checked against the owner's permissions of that moment, so a role taken away narrows their keys at once.
- Your own keys need no permission: `GET/POST/DELETE auth/keys` are no longer behind `admins.manage` as a whole. The list shows your own keys, or everybody's with `admins.manage` (`meta.can_see_everybody`). A key for somebody else still needs `admins.manage` and the rules against handing out more than you hold; switching off somebody else's key needs `admins.manage`, and a super administrator's key is switched off only by a super administrator or its owner. **Behaviour change:** an administrator with `admins.manage` but without super status can no longer revoke a super administrator's key.
- New MCP tools `admins_list_permissions`, `admins_create_role` and `admins_update_role` (behind `roles.manage`, with `dry_run`, the form's validation and the same rules as the screen «Roles»). `admins_list_roles` names who holds each role and is open to whoever reads roles in the panel (`admins.view`, `admins.manage`, `roles.manage`, `roles.assign`).
- `KeyRefused` names the field at fault (`$refused->field`).

`@webx-ui/module-auth`: the key dialog offers only the scopes of the chosen administrator; `KeysApi.scopesFor(admin)`.

---
'@webx-ui/php': minor
'@webx-ui/module-admin': patch
---

One policy decides every permission check of the panel (WEBX_UI_PERMISSIONS.md §4): `WebxUi\Admin\Access\AccessPolicy` in `webx-ui/module-admin`. A super administrator passes, anybody else holds what their roles list, joined; a call made with a token is the intersection of its scopes and its owner's permissions; a limit by record can be registered later with `constrain()` without any door changing. `CmsUser::hasPermission()`, `cms.can`, the MCP server (`Permissions`, `Scopes`) and the journal all ask it. An account that can say what it was granted implements `WebxUi\Admin\Access\HoldsPermissions` (`CmsUser` does); one that only answers `hasPermission()` keeps working.

`cms.can` and `cms.auth` now run before a route's model bindings: without the permission the answer is 403, not a 404 that tells whether the record exists.

`webx-ui/module-auth`:

- New permissions `roles.manage` (edit roles) and `roles.assign` (give and take roles). Assigning a role — in the administrator form, when creating one, and the MCP `admins_grant_role` / `admins_revoke_role` — needs `roles.assign` instead of `admins.manage`. A migration gives `roles.assign` to every role that has `admins.manage`, so nobody loses what they could do.
- The rules against handing out more than you hold (§6.2) live in `WebxUi\Auth\Access\AccessRules`, for the panel, MCP and keys alike: not your own roles, not a permission you do not hold (in an assignment, a new account, or a key for somebody else), not an account that holds more than you, only a super administrator makes or touches one, not a role you hold, and the last **active** super administrator stays one.
- **Behaviour change:** every refusal is a 403 with `message`, `rule` and, when it is the reason, `missing`. «Not on your own account» and «the last super administrator» used to be 422.
- **Behaviour change:** `PATCH auth/admins/{id}` changes the fields it is sent and nothing else. Leaving out `is_super` no longer takes the status away, leaving out `is_active` no longer switches the account on; `name` and `email` are optional on an edit.
- Accounts (`admins.admin`) and roles (`admins.role`) are in the journal: changed fields, who holds which roles before and after, that a password was changed, and every refusal (a new event, `refused`). Read with `admins.audit` or `admins.manage`.
- `WebxUi\Auth\Testing\AssertsPermissions` — the test every module carries: each panel route answers 401 to nobody and 403 to an administrator without permissions, each MCP tool is hidden from and refused to them, and every permission asked for is declared. Every package of the monorepo runs it (`AccessCoverageTest`).

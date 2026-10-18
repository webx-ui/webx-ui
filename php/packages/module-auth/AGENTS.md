# webx-ui/module-auth

The panel's people: administrators in their own table behind their own guard, roles as lists of
permissions, sign-in and its trail, the section «Administrators», and the consent screen and
«Connect an agent» section through which an administrator lets an AI agent act as them. The MCP server,
its grants and call log are `webx-ui/mcp`; which permissions exist is decided by the modules
registered in `webx-ui/module-admin` — read their guides for those.

## What it owns

- **Tables** `cms_users` (`WebxUi\Auth\Models\CmsUser`: `email`, `is_super`, `is_active`,
  `locale`, `theme`, `avatar`), `cms_roles` (`Role`: `slug`, `name`, `permissions` JSON),
  `cms_role_user`, `cms_login_records` (every sign-in, failed ones included).
- **Guard** `cms` over provider `cms_users`, registered unless the application defines them.
  Middleware aliases `cms.auth` (signed in) and `cms.can:<permission>,...` (any of them).
  `$user->hasPermission()` — `is_super` answers yes to everything.
- **Panel protection**: with `protect_panel` on, the panel API runs through
  `panel_api_middleware` (`web`, `cms.auth`, `webx.panel-locale`).
- **API** under `/api/cms/auth`: `login`, `logout`, `me`, `locale`, `theme`, `connections`,
  `roles` (list, `roles/permissions` — the matrix, `roles/{id}` with its holders), `admins`, `mcp-calls`; the consent posts under `/oauth/consent`.
- **Panel sections** `admins` (list, dialog, the screen «Roles» with its matrix, sign-in trail,
  agent calls) and `connect` (shown only when the MCP door and Passport are there).
- **Ready-made roles** (`Access\DefaultRoles`): editor, seo, leads, catalog, developer, viewer —
  made of the installed modules, only where the code is free, by the first `webx:admin`, by the
  update migration and by `webx:roles:defaults`.
- **MCP** tools `admins_list_admins`, `admins_list_roles` (with holders),
  `admins_list_permissions`, `admins_create_role` and `admins_update_role` (same rules as the
  screen), `admins_grant_role`, `admins_revoke_role`, `admins_recent_sign_ins`, `admins_failed_sign_in_bursts`. Scopes `admins:read`,
  `admins:write`, `admins:audit`. No tool sets a password, creates an account, deletes a role or
  issues a token.
- **Commands** `webx:admin` (`--name`, `--email`, `--super`; password from `WEBX_ADMIN_PASSWORD`
  when nobody is there to ask) and `webx:mcp:token` (`--name`, `--scopes`, `--admin`, `--days`,
  `--json`, `--revoke-existing`) — the same key the panel issues under Administrators → Keys
  (`/api/cms/auth/keys`), listed and revoked there. A key carries only scopes its owner's permissions reach
  (`keys/scopes`), and every call is asked again: a role taken away narrows the key at once.
- **Events** `AdminLoggedIn`, `AdminLoggedOut`, `AdminLoginFailed`.

### Permissions

| Permission      | Opens                                                                                                                                  |
| --------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| `admins.view`   | the list and card of administrators (`GET admins`, `admins/{admin}`), the roles read-only, `admins_list_admins`                        |
| `admins.manage` | the same, plus create, edit, switch off and delete accounts; everybody's keys and agent connections; the journal of accounts and roles |
| `admins.audit`  | the agent calls (`GET mcp-calls`), the journal of accounts and roles, `admins_recent_sign_ins`, `admins_failed_sign_in_bursts`         |
| `roles.manage`  | the screen «Roles»: create, edit, delete a role (`POST`/`PUT`/`DELETE roles`), `admins_create_role`, `admins_update_role`              |
| `roles.assign`  | who holds which role: the roles of an account in its dialog (with `admins.manage`), `admins_grant_role`, `admins_revoke_role`          |

Any of the four but `admins.audit` reads the roles (`GET roles`, `roles/permissions`, `roles/{role}`,
`admins_list_roles`, `admins_list_permissions`). Open to every signed-in administrator, about
themselves: `me` (read and profile — its own fields only, any other is a 422 by name), `me/email`
(a new address, moved by a signed link in a letter to it), `locale`, `theme`, `site-bar`, `logout`,
their own keys (`keys?mine=1`, `keys/scopes`) and agent connections (`connections`), and the section
«Connect an agent» (`connect` declares no permissions; it lists and issues their own keys). The rules hold even for holders: nobody changes their own
roles, hands out more than they hold, or touches a super administrator without being one. A route
or screen node the site adds next to the module asks the same: `cms.can:admins.view,admins.manage`
to read, `cms.can:admins.manage` to write.

## Change it without forking

| You want                                   | Do this                                                                                 |
| ------------------------------------------ | --------------------------------------------------------------------------------------- |
| The first administrator                    | `php artisan webx:admin` — the first account is always super                            |
| A role                                     | Administrators → Roles, or a `Role` row in a seeder (`slug`, `name`, `permissions`)     |
| The ready-made roles on an older site      | `php artisan webx:roles:defaults` — creates the missing ones, touches none that exist   |
| An account an agent may connect as         | a role with just the permissions it needs, not `is_super`                               |
| A key for a script or CI                   | Administrators → Keys, or `php artisan webx:mcp:token --name=ci --admin=... --days=365` |
| A key for yourself, without `admins.*`     | Connect an agent → Your keys                                                            |
| Changing your own address without mail     | `WEBX_AUTH_EMAIL_CHANGE=true` only if letters really leave; otherwise an admin edits it |
| Your own middleware stack on the panel API | `'protect_panel' => false` in `config/webx-auth.php`, add `cms.auth` to your stack      |
| Another sign-in rate limit                 | `'throttle'` (`attempts,minutes`, default `6,1`)                                        |
| The panel's sign-in screen at another path | `'login_path'`, matching the `path` option of the `auth()` plugin                       |
| Another consent screen                     | `php artisan vendor:publish --tag=webx-auth-views`                                      |
| Other words in the panel                   | `php artisan vendor:publish --tag=webx-auth-lang`                                       |
| Publish the config                         | `php artisan vendor:publish --tag=webx-auth-config`                                     |
| React to sign-ins                          | listen to `WebxUi\Auth\Events\AdminLoggedIn` / `AdminLoginFailed`                       |

## Do not

- Do not edit anything in `vendor/webx-ui/module-auth` or copy it into the site. Every row
  above is the supported way; if none fits, the package is missing a seam — say so.
- Do not use the site's `users` table or `web` guard for administrators: the panel reads
  `cms_users` behind `cms`, so a leak on the public site never reaches the panel.
- Do not connect an agent as a super administrator: `is_super` passes every permission,
  including ones a module installed later adds. Give the account a role instead.
- Do not set a password or create an account with SQL: the password must be hashed and the
  rules (12 characters, unique email) checked. Use `webx:admin` or the section.
- Do not switch off or delete the last super administrator, or yourself — the server refuses
  both. Create another super administrator first.
- Do not list permissions in a role that no module declares: `cms.can` matches names exactly,
  so a typo grants nothing. `php artisan webx:mcp-tools` shows the permission of each tool.
- Do not turn `protect_panel` off without putting `cms.auth` on the panel API yourself: the
  panel's data would be public.

## Check your work

- Sign in to the panel as the new account: the menu shows only the sections its role allows.
- With MCP: `admins_list_admins` and `admins_list_roles`; a role change through
  `admins_grant_role` with `dry_run: true` first.
- `php artisan webx:doctor` — what is misconfigured on the site.

## Read more

- [README.md](README.md) in this directory — the PHP API.
- Guide: https://webx-ui.github.io/webx-ui/guide/admins
- Connecting agents: https://webx-ui.github.io/webx-ui/guide/agents
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MCP_ACCESS.md

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
  `roles`, `admins`, `mcp-calls`; the consent posts under `/oauth/consent`.
- **Panel sections** `admins` (list, dialog, roles read-only, sign-in trail, agent calls) and
  `connect` (shown only when the MCP door and Passport are there). Permissions `admins.view`,
  `admins.manage`, `admins.audit`.
- **MCP** tools `admins_list_admins`, `admins_list_roles`, `admins_grant_role`,
  `admins_revoke_role`, `admins_recent_sign_ins`, `admins_failed_sign_in_bursts`. Scopes
  `admins:read`, `admins:write`, `admins:audit`. No tool sets a password, creates an account or
  issues a token.
- **Commands** `webx:admin` (`--name`, `--email`, `--super`; password from `WEBX_ADMIN_PASSWORD`
  when nobody is there to ask) and `webx:mcp:token` (`--name`, `--scopes`, `--admin`, `--json`,
  `--revoke-existing`).
- **Events** `AdminLoggedIn`, `AdminLoggedOut`, `AdminLoginFailed`.

## Change it without forking

| You want                                   | Do this                                                                                |
| ------------------------------------------ | -------------------------------------------------------------------------------------- |
| The first administrator                    | `php artisan webx:admin` — the first account is always super                           |
| A role                                     | a `Role` row in a seeder (`slug`, `name`, `permissions`); the panel only assigns roles |
| An account an agent may connect as         | a role with just the permissions it needs, not `is_super`                              |
| A key for a script or CI                   | `php artisan webx:mcp:token --name=ci --admin=... --scopes=pages:write`                |
| Your own middleware stack on the panel API | `'protect_panel' => false` in `config/webx-auth.php`, add `cms.auth` to your stack     |
| Another sign-in rate limit                 | `'throttle'` (`attempts,minutes`, default `6,1`)                                       |
| The panel's sign-in screen at another path | `'login_path'`, matching the `path` option of the `auth()` plugin                      |
| Another consent screen                     | `php artisan vendor:publish --tag=webx-auth-views`                                     |
| Other words in the panel                   | `php artisan vendor:publish --tag=webx-auth-lang`                                      |
| Publish the config                         | `php artisan vendor:publish --tag=webx-auth-config`                                    |
| React to sign-ins                          | listen to `WebxUi\Auth\Events\AdminLoggedIn` / `AdminLoginFailed`                      |

## Do not

- Do not edit anything in `vendor/webx-ui/module-auth`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
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

---
'@webx-ui/php': patch
---

How a module declares and checks its permissions is written down (guide `permissions`, WEBX_UI_PERMISSIONS.md §20).

`webx-ui/module-admin`: `php artisan webx:make-module` also writes `tests/Feature/Cms/<Name>ModuleAccessTest.php` — the test that every panel route and MCP tool of the module asks for a declared permission, built on `WebxUi\Auth\Testing\AssertsPermissions`. It is marked incomplete until the module serves its first route and tool. An existing file is kept without `--force`.

Every `AGENTS.md` of a package with panel routes or MCP tools has a `### Permissions` subsection: what each permission opens and what a route added next to the module should ask for.

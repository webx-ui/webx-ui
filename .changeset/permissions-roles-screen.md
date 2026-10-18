---
'@webx-ui/php': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': minor
---

The screen «Roles» and a panel that shows each administrator what they may open (WEBX_UI_PERMISSIONS.md §11, §18).

`webx-ui/module-auth`:

- Roles are edited from the panel: `POST/PUT/DELETE auth/roles` behind `roles.manage` and the rules against handing out more than you hold (a role gains only what its editor holds; a role you hold is not yours to edit or delete). `GET auth/roles/permissions` is the matrix — every permission an installed module declares, by module, with a label in the panel's language and whether the reader holds it; `GET auth/roles/{id}` names who holds it. A role may list only declared permissions, plus those it already had. Deleting a role takes it from its holders and writes a line in each one's history.
- `GET auth/roles` is now open to `roles.manage` and `roles.assign` as well as `admins.view|admins.manage`, and says of each role whether the reader holds it (`held`). `GET auth/me` gives each role its `id`.
- Ready-made roles (`editor`, `seo`, `leads`, `catalog`, `developer`, `viewer`), made of the modules installed and only where the code is free: by the first `webx:admin`, by a migration on a site that already has administrators, and by the new `webx:roles:defaults`.
- Front end: the view «Roles» under Administrators with the matrix dialog (`WxRoleList`, `WxRoleDialog`); each view of the section is shown only to whoever may open it; the administrator form shows the roles only with `roles.assign` and «Super administrator» only to a super administrator, and sends neither otherwise.

`webx-ui/module-admin`:

- The manifest says of every module whether the signed-in administrator may open it (`allowed`). A module without permissions of its own names the ones that open it with the new `WebxUi\Admin\Contracts\HasVisibility::visibleWith()` — the catalogue's dictionaries and the header and footer do. The cached half of the manifest stays one per language.
- Front end: the menu leaves out what is not allowed, and a page of such a section shows «No access» instead of drawing itself and failing.

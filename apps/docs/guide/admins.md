# Administrators

`@webx-ui/module-auth` is the sign-in screen, the user menu — and the people who sign in. This
page is about the last part: the section that lists them, the form that edits them, and the
picker that opens from code when another module needs to name one.

Nothing here works without `webx-ui/module-auth` on the server.

## The section

```ts
import { admins, auth, WxUserMenu } from '@webx-ui/module-auth'

createAdmin({
  modules: [admins()],
  plugins: [auth()],
  userMenu: WxUserMenu,
})
```

A searchable, filterable, paginated list — by role, and by whether the account is switched on —
and a dialog over it to create somebody or edit them. Deleting is on the row.

Two edits are refused by the server rather than by the button: **you cannot switch yourself off
or delete yourself**, and **the last super administrator cannot stop being one**. Both are ways
to end up with a panel nobody can get into.

## The photograph

An administrator's photo is a picture like any other, so it lives in the media library — but
`module-auth` does not depend on `module-media`. It does not need to: the library registers its
field as the screen type `wx-media` and tells the panel where a key lives now (`assetUrls`), and
the administrators' form, their list and the menu in the corner of the header take both from
there. With `media()` installed the photographs simply work; without it the form has no photo
field and rows show initials — which is what they showed anyway for everybody who never uploaded
one. What is stored is the library's key.

Something other than the library can still be handed in, and then it wins:

```ts
import { admins, auth } from '@webx-ui/module-auth'

const panel = createAdmin({
  modules: [admins({ avatarField: MyPhotoField, resolveAvatar })],
  plugins: [auth({ avatarField: MyPhotoField, resolveAvatar })],
})
```

`auth()` is how the menu in the corner shows the signed-in person's own photograph: the session
carries the key, and the plugin hands the field and the resolver to `WxUserMenu`.

## Choosing somebody, from code

```ts
import { selectAdmin, selectAdmins } from '@webx-ui/module-auth'

const owner = await selectAdmin()
if (owner) task.owner_id = owner.id

const reviewers = await selectAdmins()
```

One of the [dialogs from code](/guide/modals): the same list, in a dialog, resolving with who
was chosen and with `undefined` when it was closed. One is chosen by clicking the row; several
are ticked and confirmed.

Both need `admins.view`, which is deliberately separate from `admins.manage`: assigning work to
somebody is not the same as being allowed to edit their account.

## Talking to it directly

```ts
import { createAdminsApi } from '@webx-ui/module-auth'
import { useAdmin } from '@webx-ui/module-admin'

const api = createAdminsApi(useAdmin())

const page = await api.list({ q: 'anna', role: 'editors', active: 'yes' })
const roles = await api.roles()
```

`list` and `roles` are the read half; `create`, `update` and `remove` are the other one.

## Roles

A role is a named set of permissions; an administrator may hold several, and their permissions add
up. Which permissions exist is still decided by the modules installed — the view **Roles** of the
section lists them as a matrix, one row per module in the order of the menu, and a role can only
tick what a module declares.

- Reading the roles takes `admins.view`, `admins.manage`, `roles.manage` or `roles.assign`;
  editing them takes `roles.manage`, and giving them to people takes `roles.assign`.
- Nobody hands out more than they hold: a permission the editor does not have is shown greyed out,
  and a role the editor holds opens read-only. The server checks both again.
- A new site gets ready-made roles — editor, SEO, leads, catalogue, developer, viewer — made of its
  modules. A module installed later is topped up into them: every deploy (`webx:boot`) and
  `webx:module:add` add its permissions to the ready-made roles whose definition names them. A
  role remembers what it has been offered, so a permission you take away from it stays away, and
  a role of your own — even one called `editor` — is never touched. Missing ready-made roles are
  created only when you ask: `php artisan webx:roles:defaults` (`--top-up` to only add,
  `--dry-run` to see what would change), or the MCP tool `admins_sync_default_roles`.
  `webx:doctor` names the roles that are behind.

The menu is built for whoever is signed in: a section none of whose permissions they hold is left
out, and its address shows «No access». The rules are in
[the permissions specification](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_PERMISSIONS.md).

```ts
const matrix = await api.permissions()
await api.createRole({ name: 'Editors', permissions: ['pages.view', 'pages.manage'] })
```

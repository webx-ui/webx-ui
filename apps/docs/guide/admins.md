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
`module-auth` does not depend on `module-media`. The panel, which is the only place that knows
both are installed, hands the pieces in:

```ts
import { admins, auth } from '@webx-ui/module-auth'
import { createMediaApi, WxMediaField } from '@webx-ui/module-media'

const resolveAvatar = async (key: string) =>
  (await createMediaApi(panel.context).fileByPath(key))?.url ?? null

const panel = createAdmin({
  modules: [media(), admins({ avatarField: WxMediaField, resolveAvatar })],
  plugins: [auth({ resolveAvatar })],
})
```

Without them the form simply has no photo field and rows show initials — which is what they
showed anyway for everybody who never uploaded one. What is stored is the library's key.

The same function goes to `auth()`, which is how the menu in the corner of the header shows
the signed-in person's own photograph: the session carries the key, and the plugin hands the
resolver to `WxUserMenu`.

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

Read-only, on purpose. A role is a set of permissions, and which permissions exist is decided by
which modules are installed — editing that from a form would be editing the shape of the
application from inside it. Roles are created in code or in a seeder; the panel assigns them.

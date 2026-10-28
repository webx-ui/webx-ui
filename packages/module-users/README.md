# @webx-ui/module-users

«Customers» in the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: the visitors who
register and sign in on the site — never administrators, with no way into the panel.

The other half is the Composer package `webx-ui/module-users`, which owns the accounts, the guard
`site`, the sign-in and the registration, the account pages, the social networks and the API. The
sections appear in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-users
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { users } from '@webx-ui/module-users'
import '@webx-ui/module-users/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...users()],
}).mount()
```

`users()` is three sections in one menu group — **Customers**, **Groups**, **Settings** — and
`users({ path: '/clients' })` puts them somewhere else inside the panel. Who sees which is the
server's: `users.view` and `users.manage` open the customers and the groups, `users.settings`
alone opens the settings.

## Customers

The list: the name and the address (a mark when it is not confirmed), the phone, the groups, the
ways to sign in, the registration and the last sign-in. Searched by name, e-mail and phone;
filtered by group, a confirmed address, the way to sign in, blocked and the period of the
registration; sorted by the registration, the last sign-in or the address. The filters live in the
address of the page. There are no bulk actions: the groups and the block are the card's.

The card is the screen `users.form`, described in JSON on the server: the profile, the card
`project-fields` with the site's own fields, the groups, the account — `wx-users-state` and
`wx-users-ways`, the ways to sign in with «Unlink» (never the last one) — and the history. Modules
add their tabs to `tabs` and their lines to `summary`. The head holds the letters (a password link,
or the invitation again for an account without a password; the confirmation), «Confirm by hand»,
the block and the deletion, which shows what the modules say first.

The panel never sees nor sets a password. **New customer** is the same card at `…/users/new`:
an address and a profile, and «Send an invitation» — a link to set a password, alive 7 days.

## Groups and settings

The groups are a code and a name in one language, by name, with the number of customers; deleting
one is never refused. The settings are the registration open or closed and «hidden / optional /
required» for every field the customer fills in (the screen `users.settings`, kept with the site's
settings), a card per social network — the fields of its application, a secret answered only with
«set» and replaced or forgotten, the return address to copy, what is missing before it is shown —
and where the letters go.

## Words

The panel's words come from the server (`webx-users::*`, ten languages). `usersMessages` is the
English floor the sections fall back on while they load, or when a key is missing.

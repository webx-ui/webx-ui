# @webx-ui/module-auth

Signing in to a WebX UI admin panel: the screen, the session and the router guard.

The front-end half of the Composer package
[`webx-ui/module-auth`](https://packagist.org/packages/webx-ui/module-auth), which is what
closes the panel on the server. This is what lets somebody back in.

## Install

```bash
pnpm add @webx-ui/module-auth @webx-ui/admin @webx-ui/core vue vue-router
```

```ts
import { createAdmin } from '@webx-ui/admin'
import { auth, WxUserMenu } from '@webx-ui/module-auth'

createAdmin({
  plugins: [auth()],
  userMenu: WxUserMenu,
}).mount()
```

That is the whole integration. Installing it also tells the panel how to find out who is signed
in, which is why `@webx-ui/admin` knows nothing about authentication — it holds the answer, not
the question.

## A plugin, not a section

It owns a route and a guard and has no place in the menu:

- `/login` renders the card
- a guard sends anybody without a session there, remembering where they were going
- a watcher brings them back when the session arrives, and out again when it ends — a sign-out
  in another tab, an account switched off

Redirects are driven by the panel's state rather than by an event from the card. Signing in
flips the panel to `ready`, the shell swaps the branch it renders, and the card is unmounted
before the line after `await` runs.

## The card

No logo, no product name, no welcome. An admin panel's front door should say as little as
possible to somebody who has no business behind it, and the people who do already know where
they are.

What it does carry: a password reveal, a Caps Lock warning, `autocomplete="username"` and
`current-password` so password managers work, validation errors landing on the right field, and
a countdown when the server throttles.

Every string is a prop. The library speaks English; the panels built with it do not have to.

```ts
auth({
  card: {
    emailLabel: 'Електронна пошта',
    passwordLabel: 'Пароль',
    submitLabel: 'Увійти',
    rememberLabel: 'Запам’ятати мене',
  },
})
```

`WxLoginCard` is exported for a panel that wants to place it itself.

## The session

```ts
import { useAuth } from '@webx-ui/module-auth'

const auth = useAuth()

await auth.login({ email, password, remember: true })
await auth.logout()
const user = await auth.me() // null when nobody is
```

There is nothing kept here: the session lives in a cookie, the server is the source of truth,
and the panel asks rather than remembers.

## Licence

MIT.

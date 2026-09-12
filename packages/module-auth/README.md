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

Every string is a prop, and a prop given wins. What happens when one is not given is that the
label comes from the panel's dictionary — assembled by the server from the same `lang` files it
writes its own messages from, so the card and the 422 under its fields speak one language
without anybody listing labels twice.

```ts
// Nothing to pass: a panel in Ukrainian draws a Ukrainian card.
auth()

// Unless this particular panel wants its own words.
auth({ card: { submitLabel: 'Увійти' } })
```

Placed outside a panel, with no server to ask, the card falls back to the English it ships.

`WxLoginCard` is exported for a panel that wants to place it itself.

## The session

```ts
import { useAuth } from '@webx-ui/module-auth'

const auth = useAuth()

await auth.login({ email, password, remember: true })
await auth.logout()
const user = await auth.me() // null when nobody is
await auth.setLocale('uk') // the language this administrator reads the panel in
```

There is nothing kept here: the session lives in a cookie, the server is the source of truth,
and the panel asks rather than remembers.

## Licence

MIT.

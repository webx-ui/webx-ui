# @webx-ui/admin

The frame a WebX UI admin panel runs in: the bootstrap, the shell, the HTTP client and the
module registry.

It is the front-end half of the Composer package [`webx-ui/admin`](https://packagist.org/packages/webx-ui/admin),
and the two are paired by the manifest the server publishes — so a section appears in the panel
when both halves have it, and the menu is what the installation actually has rather than a list
written twice.

## Install

```bash
pnpm add @webx-ui/admin @webx-ui/core @webx-ui/tokens vue vue-router
```

On the Laravel side, `php artisan webx:panel` writes the entry file below and wires it into
Vite for you.

## The panel

```ts
import { createAdmin } from '@webx-ui/admin'
import { auth, WxUserMenu } from '@webx-ui/module-auth'
import { pages } from '@webx-ui/module-pages'

createAdmin({
  modules: [pages],
  plugins: [auth()],
  userMenu: WxUserMenu,
}).mount()
```

`createAdmin()` mounts into `#webx-app` — the element the server's Blade shell renders — and
reads the manifest address out of the `webx-manifest` meta tag beside it. Both are overridable
for a panel served some other way.

Mounting does not wait for the server. The manifest needs a signed-in session, so a visit that
starts at the sign-in screen would otherwise stare at a blank page waiting for a request it is
bound to lose.

## A module

```ts
import type { AdminModule } from '@webx-ui/admin'

export const pages: AdminModule = {
  id: 'pages', // the same id the server-side module answers to
  path: '/pages',
  routes: [{ path: '/pages', component: () => import('./PagesScreen.vue') }],
}
```

A module the server reports with no front end installed has nowhere to send anybody, so it
stays out of the menu; one installed but not reported is not there at all.

## Talking to the backend

```ts
import { useAdmin, HttpError } from '@webx-ui/admin'

const { http, can, state } = useAdmin()

const body = await http.get<{ data: Page[] }>(`${state.manifest?.apiPath}/pages`)
```

The client speaks this backend's conventions rather than HTTP in general: a session cookie, a
CSRF token fetched when needed and refreshed once if the server says it has gone stale, `422`
arriving as `errors` ready for `WxForm`, `429` carrying `retryAfter`.

```ts
try {
  await http.post('/api/cms/pages', page)
} catch (error) {
  if (error instanceof HttpError && error.isValidation) {
    formErrors.value = error.errors
  }
}
```

`can('pages.manage')` answers from the signed-in session — a super administrator passes
everything, the same rule the server applies.

## Exports

| Export                    | What it is                                     |
| ------------------------- | ---------------------------------------------- |
| `createAdmin`             | Assembles and mounts the panel                 |
| `useAdmin`                | The context: `http`, `state`, `nav`, `can`     |
| `createHttp`, `HttpError` | The client, usable on its own                  |
| `AdminShell`, `AdminNav`  | The layout, for a panel that assembles its own |

Styles come with `@webx-ui/core`; this package adds a little of its own:

```ts
import '@webx-ui/admin/style.css'
```

## Licence

MIT.

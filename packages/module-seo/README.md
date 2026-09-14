# @webx-ui/module-seo

SEO as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: the rules
written for addresses, the addresses that have moved, and the card that edits what a page says
about itself.

The other half is `webx-ui/module-seo` on the server, which is what actually prints a `<head>`.
Neither is useful alone.

## Install

```bash
pnpm add @webx-ui/module-seo
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { WxMediaField } from '@webx-ui/module-media'
import { seo } from '@webx-ui/module-seo'
import '@webx-ui/module-seo/style.css'

createAdmin({
  modules: [seo({ mediaField: WxMediaField })],
})
```

The section appears under **System** once the Composer half is installed and migrated.
Permissions: `seo.view`, `seo.manage`.

`mediaField` is handed in rather than imported, so this package does not depend on the media
library being installed — without it every other SEO field still works and only the share image
is missing.

## What it brings

- `/seo` — the rules, listed in the order the site tries them.
- `/seo/redirects` — the addresses that have moved, busiest first.
- **Check an address** — which redirect catches it, which rule matched, what every source
  contributed, and what the page ends up saying. One call instead of a reading of the code.
- `wx-seo` — a field type any screen can use, whose value is everything a page says about itself
  as one object. `WxSeo` is exported directly for a form that would rather place it by hand.

## Talking to it directly

```ts
import { createSeoApi } from '@webx-ui/module-seo'

const api = createSeoApi(useAdmin())

await api.urls({ q: '/catalog' })
await api.test('/catalog/shoes?page=2')
```

The guide is at [webx-ui.github.io/webx-ui/guide/seo](https://webx-ui.github.io/webx-ui/guide/seo.html).

## License

MIT.

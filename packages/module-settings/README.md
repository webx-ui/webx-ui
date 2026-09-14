# @webx-ui/module-settings

The site's settings, as a section of a [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel. The screen itself is described by the server — `webx-ui/module-settings` ships the tree
and the project patches it — and this package draws it and saves it.

Nothing here works without the Composer half.

## Install

```bash
pnpm add @webx-ui/module-settings
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { settings } from '@webx-ui/module-settings'
import '@webx-ui/module-settings/style.css'

createAdmin({
  modules: [settings()],
  // The project's own operations over the screen, on top of the server's.
  screens: {
    'settings.index': [{ op: 'set', target: 'project-name', props: { placeholder: 'Acme' } }],
  },
})
```

The section appears under "System" in the navigation once the server reports the module.

## What it does

- `settings()` — the module: a route at `/settings` and the page.
- `WxSettingsPage` — the page: loads the values, draws `settings.index` through `WxScreen`, and
  saves with one button. A 422 lands under the field it names.
- `createSettingsApi(admin)` — `load()` and `save(values)`, for anything else that needs them.

The guide: https://webx-ui.github.io/webx-ui/guide/settings.html.

## Licence

MIT.

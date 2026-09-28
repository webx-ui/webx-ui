# @webx-ui/module-banners

The front end of the banners section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: pictures (and a narrower one for a phone), an optional video, a title, a text and up to
three buttons, standing in **named places** and arranged by hand.

The other half is the Composer package `webx-ui/module-banners`, which owns the places, the banners,
`banners()` and `banners_layout()` for templates and the API. A section appears in the panel when
both halves are installed. Banners reach a site only through a template helper: the site's own
markup draws them.

## Install

```bash
npm install @webx-ui/module-banners
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { banners } from '@webx-ui/module-banners'
import '@webx-ui/module-banners/style.css'

createAdmin({
  basePath: '/cms',
  modules: [banners()],
}).mount()
```

`banners()` is one section and one entry of the menu, at the top level. `banners({ path: '/promo' })`
puts it somewhere else inside the panel.

## Places and banners

`/banners` is the places on the left and the banners of one of them on the right. A place the
site's config declares is on the list before anything was saved into it, carries a lock and cannot
be renamed or deleted here: a template asks for it by key, and the list says how —
`banners('hero')`. A place of somebody's own is made with **New place**, renamed from its row, and
deleted only when it is empty, the bin included.

The banners of a place are in the order the site shows them: drag the grip, or pick it up with the
keyboard (space, arrows, space). A switched-off banner stays in its place, quieter; the bin is the
same list turned over, and a banner comes back from it with **Restore**.

A banner opens on a page of its own (`/banners/<id>`, a new one at `/banners/new?place=<key>`):
the place above the described screen `banners.form`, one **Save** under it, Ctrl+S, and a question
before leaving with something unsaved. Moved to another place, a banner stands last in it. A new
banner starts switched off.

The guide — places, the two helpers, a complete template with its styles and slider script, the
agent's tools — is at https://webx-ui.github.io/webx-ui/guide/banners.

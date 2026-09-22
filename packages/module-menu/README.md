# @webx-ui/module-menu

Menus as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: the named
menus of a site, the tree of items in each one, and what every item points at.

The other half is `webx-ui/module-menu` on the server, which holds the menus and renders them.
Neither is useful alone.

## Install

```bash
pnpm add @webx-ui/module-menu
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { menu } from '@webx-ui/module-menu'
import '@webx-ui/module-menu/style.css'

createAdmin({
  modules: [menu()],
})
```

The section appears among the content sections once the Composer half is installed and migrated.
Permissions: `menu.view`, `menu.manage`.

## What it brings

- `/menus` — the menus on the left, the items of the open one on the right. Which menu is open
  travels in the address, so a link to the footer is a link somebody can send.
- **Dragging** changes both the order and the parent: every level is its own list, and where a
  row ends up is where it is.
- **An item points at three things** — an entity chosen from the picker, an address of your own,
  or nothing at all, which is what a heading is. The picker is `WxLinkPicker` from
  `@webx-ui/module-admin`, the same one every link field in the panel uses.
- **The cache** is marked under each menu — "built today at 08:10", "not built", "off" — with a
  reset beside it and one for every menu in the head of the section. It is what somebody presses
  to test the guess that they are looking at something stale.

## Licence

MIT

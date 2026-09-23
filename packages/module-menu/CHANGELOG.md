# @webx-ui/module-menu

## 0.1.2

### Patch Changes

- Updated dependencies [2071b2d]
  - @webx-ui/core@0.33.0
  - @webx-ui/module-admin@0.14.4

## 0.1.1

### Patch Changes

- fde8622: The block editor draws a block on a page of the site, not on the browser's defaults.

  `webx-ui/module-blocks` gets `webx-blocks.layout` — the component the editor's stage stands in, the
  same `<x-layout>` the pages use — and `/_preview/block-stage`, which prints that layout with an
  empty place for the block. `webx:panel --sync` sets the key and `webx:doctor` warns while it is
  empty, as they do for pages and the blog; empty prints `webx-blocks::standalone`, a bare document.
  `render` names the stage in its answer.

  `@webx-ui/module-blocks`: `BlockStage` loads the stage once and swaps the block and its styles in
  on every change, so the header and footer do not redraw under typing; a new script reloads the
  page. The site's links are inert there, and the stage scrolls to the block. A server without the
  stage still gets the bare document. `frame.ts` gains `freezeFrame`, `fillStage` and `mountScript`.

  `@webx-ui/module-menu`: a long address under a menu item ends in `…` instead of running out of
  the card.

- Updated dependencies [a0556f1]
  - @webx-ui/core@0.32.0
  - @webx-ui/module-admin@0.14.1

## 0.1.0

### Minor Changes

- 8e0d587: The **Menus** section: the menus of a site on the left, the tree of one of them on the right

  `@webx-ui/module-menu` is the panel half of `webx-ui/module-menu`. One screen and no editor under
  it — a menu is arranged in place and an item is a dialog over the tree it belongs to — with which
  menu is open kept in the address, so that "the footer" is a link somebody can send.

  Dragging changes both the order and the parent. Every level is its own list and they share a group,
  so where a row ends up is where it is, rather than a guess about how far sideways it was dropped.
  Each level reports its own new order and the screen works out which item moved; one drag is one
  `move`, and a refusal puts the tree back rather than leaving the screen disagreeing with the
  database.

  An item points at one of three things and says which: an entity chosen from `WxLinkPicker` — the
  same picker every link field in the panel opens — an address of your own, or nothing at all, which
  is what a heading is. A draft target is drawn dimmed and marked **Not on the site** rather than
  hidden, because a menu is built before the pages in it are published.

  The cache is marked under every menu — "built today at 08:10", "not built", "off" — with a reset
  beside it and one for every menu in the head of the section. It is not "rebuild": the records are
  forgotten and the next visitor builds them again. It exists because the list of places a menu can
  change from ends where bulk operations begin, and it is what somebody presses to test the guess
  that they are looking at something stale, instead of finding out where artisan lives. The mark is
  read again after the reset, since a button that leaves it saying "built today at 08:10" is a button
  nobody believes twice.

  On the server: nine addresses under `/api/cms/menus`, including both cache resets, a menu resource
  carrying `cache: { enabled, built_at }` and an item resource carrying the resolved target, so the
  screen never goes looking for a name.

  In `@webx-ui/core`, `WxListDetail` now also says whether an open record still stands beside the
  list (`detail-inline`), the way it already said it about the chooser's column. It is what lets a
  screen open its first record where there is room for one without raising a panel over a list
  nobody has touched on a phone — and it is only said once the pane has been measured, since an
  unmeasured pane answers "inline" to every threshold.

  In `@webx-ui/module-admin`, `LinkUrls` gains `candidates()` and `hrefWith()`: a screen that draws
  forty links resolves them in one query per kind instead of forty.

### Patch Changes

- Updated dependencies [8e0d587]
- Updated dependencies [8e0d587]
  - @webx-ui/module-admin@0.14.0
  - @webx-ui/core@0.31.0

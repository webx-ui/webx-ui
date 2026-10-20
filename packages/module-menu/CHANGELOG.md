# @webx-ui/module-menu

## 0.2.2

### Patch Changes

- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
  - @webx-ui/module-admin@0.32.0
  - @webx-ui/core@0.44.0

## 0.2.1

### Patch Changes

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-admin@0.31.0

## 0.2.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0

## 0.1.22

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0

## 0.1.21

### Patch Changes

- Updated dependencies [152a118]
  - @webx-ui/module-admin@0.28.0

## 0.1.20

### Patch Changes

- Updated dependencies [be30498]
  - @webx-ui/module-admin@0.27.0

## 0.1.19

### Patch Changes

- Updated dependencies [e107a30]
- Updated dependencies [e107a30]
  - @webx-ui/module-admin@0.26.1
  - @webx-ui/core@0.41.0

## 0.1.18

### Patch Changes

- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
  - @webx-ui/module-admin@0.26.0
  - @webx-ui/core@0.40.2

## 0.1.17

### Patch Changes

- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
  - @webx-ui/core@0.40.0
  - @webx-ui/module-admin@0.25.0

## 0.1.16

### Patch Changes

- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
  - @webx-ui/core@0.39.0
  - @webx-ui/module-admin@0.24.0

## 0.1.15

### Patch Changes

- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/module-admin@0.23.5

## 0.1.14

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2

## 0.1.13

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1

## 0.1.12

### Patch Changes

- Updated dependencies [41f2059]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0

## 0.1.11

### Patch Changes

- Updated dependencies [3a08c6c]
- Updated dependencies [67e44aa]
  - @webx-ui/module-admin@0.22.0
  - @webx-ui/core@0.34.3

## 0.1.10

### Patch Changes

- Updated dependencies [38c5b08]
- Updated dependencies [5ebd999]
  - @webx-ui/module-admin@0.21.0
  - @webx-ui/core@0.34.2

## 0.1.9

### Patch Changes

- Updated dependencies [2ba4290]
  - @webx-ui/module-admin@0.20.0

## 0.1.8

### Patch Changes

- Updated dependencies [d1ff63d]
  - @webx-ui/module-admin@0.19.0

## 0.1.7

### Patch Changes

- Updated dependencies [50ea8a5]
- Updated dependencies [85b8fca]
  - @webx-ui/core@0.34.0
  - @webx-ui/module-admin@0.18.1

## 0.1.6

### Patch Changes

- Updated dependencies [da138e0]
- Updated dependencies [da138e0]
  - @webx-ui/module-admin@0.18.0

## 0.1.5

### Patch Changes

- Updated dependencies [c7b0084]
- Updated dependencies [c7b0084]
- Updated dependencies [c7b0084]
  - @webx-ui/module-admin@0.17.0
  - @webx-ui/core@0.33.2

## 0.1.4

### Patch Changes

- Updated dependencies [298c894]
  - @webx-ui/module-admin@0.16.0

## 0.1.3

### Patch Changes

- Updated dependencies [42d427e]
  - @webx-ui/module-admin@0.15.0

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

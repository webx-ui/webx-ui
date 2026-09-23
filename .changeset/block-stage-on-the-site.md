---
'@webx-ui/php': minor
'@webx-ui/module-blocks': minor
'@webx-ui/module-menu': patch
---

The block editor draws a block on a page of the site, not on the browser's defaults.

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

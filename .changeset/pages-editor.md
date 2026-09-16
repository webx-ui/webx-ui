---
'@webx-ui/module-pages': minor
'@webx-ui/module-blocks': minor
'@webx-ui/core': minor
'@webx-ui/php': minor
---

The page editor: `pages.form` as a described screen, and the screen that saves it.

`webx-ui/module-pages` registers `pages.form` — four tabs, Content · Settings · SEO · History —
so the SEO card can arrive as a patch rather than as a fork of the editor. `GET /api/cms/pages/{id}`
now answers with the values of that screen, the trail above the page, a signed preview link and a
`revision`; `PUT` takes the values back through `ScreenValues`, so what the tree does not name is
dropped and a 422 lands under the field it is about. The `revision` is a short hash of the
content, the way `module-blocks` computes one: a save that names a revision that is no longer the
current one is refused with a 409 carrying the page as it now is and who wrote it, and two people
who saved the same thing are not a conflict. `GET .../versions` lists the publications with their
author and source, and `POST .../versions/{n}/restore` makes an old one the draft — publishing it
stays the separate step it always was.

`@webx-ui/module-pages` draws it. The head stays put: the trail through the tree, the page's state,
the preview link and the two buttons, folded into a menu on a narrow panel. Saving is by autosave —
a pause after the last keystroke and the moment a field is left — with an explicit button beside a
chip that says saved · saving · not saved yet, and a guard that flushes the pause on the way out
and only asks when the save did not go through. The parts of the screen that are not fields are
node types of their own: `wx-page-place` prints the whole address the page answers at and moves it
in the tree, `wx-page-danger` takes it off the site or into the bin, `wx-page-history` is the
history.

`@webx-ui/module-blocks`: `wx-blocks` takes a `fill` prop — be as tall as what it is drawn in and
let the tree, the form and the preview scroll each in itself. Without it the constructor sizes
itself to its content, and a page made of twenty blocks scrolls the editor's head off the top of
the screen along with it.

`@webx-ui/core`: a screen that fills its column says so with `data-wx-fill`, and `WxMain` stops
growing with it. A percentage height inside a scrolling column resolves against nothing while the
column is as tall as its own content, and a screen cannot reach its own ancestors any other way.

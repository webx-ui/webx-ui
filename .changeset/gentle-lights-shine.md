---
'@webx-ui/module-settings': minor
'@webx-ui/module-blocks': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-pages': minor
'@webx-ui/module-media': minor
'@webx-ui/module-auth': minor
'@webx-ui/tokens': minor
'@webx-ui/core': minor
'@webx-ui/php': minor
---

A pass over the panel: the chrome, the editors and the constructor.

**The chrome.** The button that collapses the sidebar stands at the far end of
the brand row instead of against the logo. The foot of an open sidebar says who
is signed in rather than only showing them. The menu drawer keeps its width on a
phone instead of covering the page — `WxDrawer` has `full-screen` for that — and
icon buttons there are one size down.

**Settings the panel wears.** A picture chosen in the library no longer vanishes
from its field when the form is saved, and the logo in the corner changes with
it: `AdminContext` gained `refreshManifest()`, which fetches a new manifest
without the panel passing through `loading`.

**`WxActionBar`.** The strip of body colour the bar painted in the gap below it
is gone: it erased the part of the bar's own shadow that fell there, and a
descendant is always on top of its ancestor's shadow. What shows through the gap
instead is a sliver of the page still moving, which is what a bar floating over
a scrolling page looks like.

**Editors.** `WxBackButton` and `WxRenameButton` in `@webx-ui/module-admin`: a
way out of a screen that opens one record, and renaming as an act rather than as
typing into what looks like a heading. Both editors use them.

**Tabs that hold a form.** Only the tab holding the constructor is a box of a fixed height
with its own scrollbar; the others grow with their content and the page scrolls. A scroll box
clips, and the cards inside one had their shadows cut off square at all four edges.

**The constructor.** The preview is a picture of the page rather than the page:
nothing in it navigates or submits, a click opens the block it landed in, the
block under the pointer is outlined, and choosing one in the tree scrolls the
frame to it.

**Help.** `WxHelpButton` shows a page of Markdown from a module's own `lang`
files — and `module-blocks` hands the identical page to an agent at
`blocks://schema`.

The page explains fields in more than one language, and writing it turned up that they
did not work: a block with a `localized` field handed its template the whole language
map, Blade refused to print an array, and the renderer caught that and printed nothing —
the block vanished from the page. It is given one language now, down the same chain every
localized value is read through.

**The library.** A file can be downloaded from its card: `WxFileCard` takes
`download-url`.

**Smaller things.** A dialog puts air between whatever its body was given, so three stacked
fields are not one block of controls. `WxSkeleton` is `border-box`, so a loader given padding
no longer stands wider than the card it is in. And there is a guide to
[languages](https://webx-ui.github.io/webx-ui/guide/languages).

**Yourself.** `PUT auth/me` and the profile dialog behind the corner menu: your
name, your photograph, your password — the last of those only with the current
one.

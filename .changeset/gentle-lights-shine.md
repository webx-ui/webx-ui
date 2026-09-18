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

**`WxActionBar`.** The shadow rises instead of being cut off underneath by the
strip the bar paints in the gap below it. New token: `--wx-shadow-bar`.

**Editors.** `WxBackButton` and `WxRenameButton` in `@webx-ui/module-admin`: a
way out of a screen that opens one record, and renaming as an act rather than as
typing into what looks like a heading. Both editors use them.

**The constructor.** The preview is a picture of the page rather than the page:
nothing in it navigates or submits, a click opens the block it landed in, the
block under the pointer is outlined, and choosing one in the tree scrolls the
frame to it.

**Help.** `WxHelpButton` shows a page of Markdown from a module's own `lang`
files — and `module-blocks` hands the identical page to an agent at
`blocks://schema`.

**The library.** A file can be downloaded from its card: `WxFileCard` takes
`download-url`.

**Yourself.** `PUT auth/me` and the profile dialog behind the corner menu: your
name, your photograph, your password — the last of those only with the current
one.

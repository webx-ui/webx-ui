---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': patch
'@webx-ui/module-blocks': patch
'@webx-ui/module-media': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-seo': patch
'@webx-ui/php': patch
---

One shape for every list in the panel

Five sections each answered "where does the heading go" on their own, and there were five
answers: a heading inside the card on `Administrators`, two headings on `SEO`, a search outside
the card on `Blocks`, no heading at all on `Files`, a bare red bin in every row here and a menu
there. They are one shape now — the section's name on its own line, the one action it exists
for beside it, the views of the list as tabs under that, and a card holding nothing but the
rows. The search stays inside the table: it narrows the rows, not the screen.

`WxListScreen` in `module-admin` is that frame, and it is a screen node type — `wx-list` — so
the next section describes its list rather than writing a sixth copy of the same markup.

`WxTabs` grew an `items` mode for it: the strip is built from a list and the default slot is the
**one** panel under it. A view is a different question to the server, not a different panel, so
nothing is unmounted on a switch and the table keeps its search, its page and its scroll.
`collapseBelow` folds the strip into a single switch labelled with the open view when its own
container gets narrow — not into three dots, which in this panel mean actions.

`WxRowMenu` is the other half: a `···` at the end of every row in every list, even for a single
action. It orders the destructive one last, behind a rule, in red, and what somebody has no
right to is left out rather than greyed. Underneath it is `WxActions` with the new
`collapse="always"`, which never builds the row of icons at all — so the width of a table cell
stops deciding whether a row has a menu. `WxFileCard` takes the same choice as `actionsMenu`,
which is how a single file in the library gets one.

Uploading is the media library's main action now: a filled blue button with a word on it in the
line of the heading, rather than the third grey icon in a row of six. Blue, because green in
this system means "it worked". Inside the picker dialog the toolbar keeps its upload icon —
there is no screen around it there.

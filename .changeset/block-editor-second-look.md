---
'@webx-ui/module-blocks': patch
---

Each tab of the block type editor gets what it needs beside it, and the others get the screen

The screen was two columns: six tabs in the left one, and in the right one the block, the form
of its sample and a card saying where the type stands. So the picture of the block was also
standing beside the settings and beside the history, at half their width, and the sample's form
was open on all six tabs — including the four where nobody is looking at values.

What stands beside the tabs is what the open one needs. **Template** and **Styles** — both draw
the block — have the block, sticky, so the picture stays while the file scrolls under it.
**Fields** has the form its schema builds. **Script** has three examples under the editor instead:
a handler, a value out of `values`, and a library through `webx.use()`. The rest have the width
of the screen. The icon of a type is picked from the set now (`WxIconPicker`) rather than typed
into a box that accepts anything and draws nothing. **Where the type stands** is a popover behind the
words "on 3 pages" in the subtitle, styled as the link it is: five page names took a quarter of
a column to say what the subtitle already says in three words.

Three more things the same look found.

**The action bar no longer repeats the state.** It carried the same three badges as the head —
draft, live, unsaved — and on a 1440×900 window both pairs are on screen at once, on a phone
all the more so. What state a type is in is a fact about the type, not about the last
keystroke, and the panel says the state of a record beside its name. The two buttons stay:
those did scroll away with the head.

**The stage opens at the width its column can draw.** It always opened on a desktop 1280, so
in the narrow column of a phone it drew the block at a third of size — a picture of a page
whose words are two pixels tall. It now starts at the widest device that fits at half size or
better, and from the first click on the switch the width is the editor's.

**The frame is the height of the block again.** It measured `documentElement.scrollHeight`,
which is never shorter than the frame's own window: once the frame had been given a height, it
was measuring itself, so a block that got shorter kept the height of the one before it with
white space under it. Measured on the body, as everything else that watches a frame here does — and measured again
when the tab holding it comes back, since a document nobody is showing has no height at all.

Plus one line that read wrong: `on :count pages` said "on 1 pages" exactly when a type had
just been put on its first page. There is a line for one now — `page.on-page`, and
`page.delete-used-one` beside it — in all ten languages.

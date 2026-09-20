---
'@webx-ui/module-blocks': patch
---

The editor of a block type is its files and the block, and nothing else beside them

A second look at the screen, and the column beside the stage is gone.

**The sample's form is the second half of the Fields tab**, where the schema that builds it is.
It used to be a card in the right column, which meant it stood open on all six tabs — including
the four where nobody is looking at values — and pushed the picture of the block up by its own
height. **Where the type stands is a popover** behind the words "on 3 pages" in the subtitle,
styled as the link it is: five page names took a quarter of a column to say what the subtitle
says in three words. What is left beside the tabs is the block itself.

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
white space under it. Measured on the body, as everything else that watches a frame here does.

Plus one line that read wrong: `on :count pages` said "on 1 pages" exactly when a type had
just been put on its first page. There is a line for one now — `page.on-page`, and
`page.delete-used-one` beside it — in all ten languages.

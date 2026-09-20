---
'@webx-ui/module-blog': minor
---

The article's action bar is two buttons and a mark

It used to be three buttons and a sentence — "live since the eighteenth · edits waiting",
"discard", "save draft", "publish" — which on a phone is three ragged rows, and on any width is
a bar doing the head's job. Now the bar holds what is done here, and the head holds what this
record is:

- **the day moves under the name** as the head's subtitle. When the article goes out is a fact
  about the article, not about the last keystroke;
- **"edits waiting" becomes the second badge** beside the state, exactly as the articles list
  already draws it — because "Live" is the same word for `published` and `modified`, and a
  colour on its own is not a statement;
- **"Discard" leaves the bar for the `···`**, where `ScreenAction.menu` says destructive things
  belong: throwing away what was written is not something to keep one slip away from "publish".
  It is still offered only while there is a difference between what is written and what is live;
- **"Save draft" becomes "Save"** — the button beside it is the publication, so there is nothing
  left to tell apart.

Measured on a 375px screen: one row, 60px tall, which is what the page editor's bar has been all
along.

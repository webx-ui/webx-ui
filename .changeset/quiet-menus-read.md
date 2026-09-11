---
'@webx-ui/core': patch
---

Navigation reads a step heavier, and the account menu stays where it belongs

Menu labels are `500`, and `600` in a sidebar: a sidebar is the page's own table of
contents and is looked at all day, while a bar between a logo and a user menu reads
better a step lighter. The gap between an entry's icon and its label comes down
from 10px to 8px.

`WxHeader`'s `end` group no longer shrinks. Left as an ordinary flex item, it was
the first thing a wide navigation bar squeezed — the account menu slid under the
bar and off the edge of the header.

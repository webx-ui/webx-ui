---
'@webx-ui/module-inbox': patch
'@webx-ui/module-blog': patch
---

Even air around the submissions list, one line in the tags bar.

The step the pane keeps around its table was a side padding on the card view alone, so the cards
stood further in than the search field above them and the rows below them, and the step showed as
a disagreement. It is one padding on the table now, on all four sides and in both views, and none
at all in the drawer, where the screen's edge is the boundary and the pane's own step is all the
air the list needs. The scroll bar of the card list rides in that step rather than in the cards'
own right edge, so the list has the same air on both sides.

The tags selection bar stays on one line on a phone, and its `···` is the size of the button
beside it. The bar keeps its state box at 220px so that a sentence about a draft has room to be
read; "2 selected" does not need it, and asking for it put "Merge" and the `···` on a second line
at every phone width. A row menu is 30px because it stands at the end of a row; in a bar it stands
beside a button, and the pair read as a control and a leftover.

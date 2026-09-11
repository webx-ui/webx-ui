---
'@webx-ui/core': patch
---

`WxTable`: pinned columns land where they actually are, and the chrome behaves

Four things a full-page table made visible:

- **Pinned columns left a gap.** The offsets came from the widths the caller
  declared, and a declared width is honoured only while there is room: in `auto`
  layout a table that has to scroll squeezes every column proportionally. The
  numbers stopped being true exactly when pinning starts to matter, so the frozen
  block sat a few pixels wide of the column behind it and the scrolling rows showed
  through the seam. The offsets are now measured off the heading row.
- **A table that fitted still had a scrollbar.** The strip that covers the seam
  beside a right-pinned cell sat a pixel past the table's edge, and that pixel is a
  pixel of scrollable width. It is now flush.
- **Rounded corners under a heading.** With a title or a search field above the
  rows, the heading strip curved away from two square corners and left a white wedge
  in each. Those corners are square now.
- **Edge shadows with nothing to hide.** The frozen block cast its shadow whether or
  not anything was underneath it. It now appears only on the side that has more to
  show, and goes away when the table fits.

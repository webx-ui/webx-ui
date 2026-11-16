---
'@webx-ui/php': patch
---

widgets: the lightbox link in a `gallery` cell is a block, so the picture fills the cell whatever copy the browser picked (it stood at the natural width of the preview, a narrow strip in the cell). The grid's `sizes` allows for the page's gutters: a phone whose column folds the grid into one column is asked for a copy as wide as the column, not half of it.

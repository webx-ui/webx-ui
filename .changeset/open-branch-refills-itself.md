---
'@webx-ui/core': patch
---

A lazy tree keeps its open branches when the level around them is fetched again. Deleting a
page used to empty every branch that was open beside it and leave the chevron toggling
nothing until the page was reloaded: the fetched children were recorded by key, and the key
came back the same on rows that were new objects. They are recorded against the node itself
now, and a branch that is open with nothing under it asks for its children once.

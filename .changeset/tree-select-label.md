---
'@webx-ui/core': patch
---

TreeSelect: the field names a node the panel has just fetched

The field keeps one index to name its value and the tree inside its panel keeps another, and it is
the tree that does the fetching. The signal that said "a branch arrived, rebuild" was private to
each index, so picking a city out of a branch that had just loaded left the field showing
`ua-odesa`. Every index now shares it.

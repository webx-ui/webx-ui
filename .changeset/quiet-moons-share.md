---
'@webx-ui/core': patch
---

A drawer is spaced by the panel's step where there is one. Its sideways padding now reads
`--wx-gap` and keeps its own 18 (12 on a narrow screen) as the fallback, so the same list is the
same distance from the edge in a card and in a drawer — it was 16 against 18 on a desktop and 8
against 12 on a phone. A drawer is teleported out of the application's tree, which is why the
shell writes that step on the document as well.

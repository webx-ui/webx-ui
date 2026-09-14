---
'@webx-ui/core': patch
'@webx-ui/module-seo': patch
---

A card spaces what it holds, and a table's filters stop running off a phone

Both showed up the moment the SEO settings tab put six fields in one card. `WxForm` hands its gap
to its own children, and fields that land inside a card are not those: they stacked flush, and the
hint under one read as the label of the next. A card's body and its two sidebar columns now stack
what they hold with the same space the card keeps around it.

The other one is `WxTable`'s header: a filter and a search field are each wider than a phone can
spare, and an input will not shrink below its own intrinsic width — it overflowed sideways instead,
and the whole page scrolled with it. The tools wrap now, and once the rows have become cards they
are a column of their own, full width and all one height.

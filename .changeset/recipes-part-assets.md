---
'@webx-ui/php': patch
---

A customised recipe card now brings its styles and script to the recipes' own pages — the index, a
category and a recipe with its similar ones. Those pages render their body before the layout and
print `@webxPartAssets` in the head: the bundle of what was rendered when the blocks module is
installed, nothing when it is not. Before this the card printed and its CSS stayed off the page.

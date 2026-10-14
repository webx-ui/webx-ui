---
'@webx-ui/php': patch
---

`webx-ui/module-blocks`: the bundle of block styles and scripts is named by the version rows of its types, not their version numbers. A type deleted and created again under the same slug starts over at version 1, and the page went on getting the old type's CSS — under a URL cached forever.

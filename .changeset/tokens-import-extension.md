---
'@webx-ui/tokens': patch
---

`dist/index.js` imports `./generated/tokens.js` with its extension: Node and any loader without a
bundler's guessing could not load the package.

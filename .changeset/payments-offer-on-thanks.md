---
'@webx-ui/php': patch
---

`webx-ui/module-payments`, `webx-ui/module-orders`: «Pay online now» of an order on receipt is held on «Thank you» too. The page prints the order's body, so the block came with it; tests now keep it there, the form's way to the gateway from this page included, and the specs say so.

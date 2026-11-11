---
'@webx-ui/php': patch
---

Checkout: with nothing in the cart that can be bought, "Place the order" (and its copy in the sticky row) is disabled with a line saying to change the cart first; it comes back on after the cart's dialog adds something that can be bought, without a reload. The server still refuses such an order. A line links to its product only while the product has an open page — the same rule in the order contents and in the cart's dialog.

---
'@webx-ui/php': patch
---

Checkout: «N items for» in the column counts only the lines that go into the order — the ones in the sum beside it (`CartState::availableUnits()`); the header's counter still keeps the unavailable ones. A line that cannot be bought has no sum, as in the dialog of the cart, instead of «0.00»; when nothing in the cart can be bought, the row says so instead of «0 items for 0.00».

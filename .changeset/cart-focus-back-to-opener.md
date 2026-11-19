---
'@webx-ui/php': patch
---

module-cart: closed, the cart's drawer or dialog gives the focus back to the buy button that opened it, not to the cart's icon in the header. `webx.cart.open(opener?)` takes the element to come back to, the icon when none; when the opener has left the page by the time the cart closes (a list drawn anew), the focus goes to the icon instead of `<body>`. `dist/cart.js` rebuilt.

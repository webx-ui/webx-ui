---
'@webx-ui/php': patch
---

The checkout's «Order contents» only lists the lines — the name, «× quantity», the sum, a line that can no longer be bought marked in the cart's words — and its «Edit», like «Open the cart» of the warnings, opens the cart's own dialog: the checkout's minimal layout has no cart in its header, so the page prints `<x-webx-cart :trigger="false" />`, the main view without the icon, the mini cart or the counter. Each change in the dialog redraws the steps, the warnings and the totals; the dialog's «Checkout» only closes it there, and the focus goes back to the link that opened it. Any `[data-webx-cart-edit]` on a page opens the cart and gets the focus back. «Open the cart» used to do nothing on the checkout.

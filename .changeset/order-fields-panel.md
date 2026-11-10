---
'@webx-ui/php': minor
'@webx-ui/module-orders': minor
---

Orders: the extra fields of the checkout in the panel. The tab «Checkout» of the order settings gets the card «Extra fields» (`wx-orders-checkout-fields`): the fields grouped by the places of the checkout, the ones shown behind a checkbox under it, dragged inside their place, switched on and off from the row; a dialog writes the label and hint in every language, the key and the type (fixed once saved), the options of a list, the place and the checkbox it is shown behind. A change the buyer will feel — required now, switched off, moved, options taken away — is said before it is made, and deleting a checkbox with fields behind it is refused with their names. The API `/api/cms/orders/checkout-fields` (`orders.checkout.settings`, only while the checkout is on) answers a dry run in the panel's words and a refusal under the input it is about. On a site without the settings module the tab keeps this card alone. Nothing is asked on the checkout yet.

---
'@webx-ui/php': minor
'@webx-ui/module-orders': minor
---

Orders: the extra fields in the card of an order. The tab «Buyer» gets the card «Additional» (`wx-orders-fields`): the rows the order keeps, then every field switched on with no answer, «label — value»; whoever manages the order changes them with the pencil, saved on their own by `PUT /api/cms/orders/{id}/details` with `fields: {key: value | null}` — only the keys sent, null, an empty line or `false` clears a row. The type, the length and the option are checked as at the checkout, the requirement and the condition are not; an answer as it was keeps the order's words, a new one takes the field's words in the order's language. A field that is gone keeps its label: a line or a text still changes, a checkbox and a list only go. The edit is a line «Additional fields» of the order's timeline, «label: value; …» in the journal and `fields` in `OrderDetailsChanged`; the buyer gets no letter. `OrderDetails::fields()` does the same from code. An opened row of the list shows the filled rows beside the comment. `orders.view` reads the rows without the pencil.

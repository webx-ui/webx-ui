---
'@webx-ui/php': minor
---

`webx-ui/module-payments`: the page of payment offers the list of the checkout's step. «Choose another method» is `<x-webx-payments::methods>`, group → method, with one «Continue» under it, and `methods.js` takes Apple Pay away there too. An order «on receipt» no longer has a button per online method and a separate offline list: it has this same list of every other method of the order, by the rules of its delivery and amount, standing open as «Pay online now» when any of them is online. `POST /pay/{number}/method` reads `group` and `method` by the rule of the step, never falls to the order's own method, and now says why when nothing is chosen or the method has gone. A theme's copy of `pay/page` or `pay/others` keeps working as it was, its `{ key, title }` are still there, but it does not get the list: Apple Pay stays on it, and the classes `webx-payments-others__form` and `__choice` have no base look any more.

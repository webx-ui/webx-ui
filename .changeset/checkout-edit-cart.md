---
'@webx-ui/php': patch
---

`webx-ui/module-orders`: the checkout changes the cart in place. «Order contents» is open by default and each line has a stepper and «Remove» on the cart's own API, with and without JavaScript; the totals, delivery and payment are counted again, the focus stays, and the last line removed leaves the page of an empty cart. A site's copy of `checkout/summary.blade.php` keeps working, without the controls.

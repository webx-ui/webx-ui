---
'@webx-ui/php': minor
---

Orders: the definitions of the checkout's extra fields — table `order_fields` (`OrderField`), written only through `OrderFields::write|remove|arrange` with dry runs and `FieldRefused` codes (key, type locked once saved, place, label, options, a checkbox it is shown behind, at most 30 fields and 50 options), cache group `order-fields`, each write in the journal. Where a field stands is a key of the registry `Checkout\FieldPlaces`: `contacts`, `extra-before-comment` (the default) and `extra-after-comment`; a module adds a place of its own without a migration. Nothing is asked on the checkout yet.

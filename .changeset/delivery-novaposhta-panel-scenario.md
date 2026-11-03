---
'@webx-ui/php': minor
'@webx-ui/module-delivery': patch
'@webx-ui/module-admin': patch
---

Delivery panel after the walk-through on a real carrier: the list of localities with no country
shows every country that is on, and an empty search says where the towns are (another country, or
one switched off); the remarks of a pass come in words from the server (`NamesSkipReasons`, the
base's «Skipped, for another reason: N» for a reason nobody names); a carrier whose point names
carry the street drops the address line under them (`NamesPointLines`); a locality that is its own
region is named once; the carrier's settings show their defaults as placeholders; «By hand only»
is words, not code; a method named after its type has no echo line; and a toast over an open
dialog goes to the top instead of covering its footer.

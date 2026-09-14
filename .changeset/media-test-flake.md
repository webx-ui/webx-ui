---
'@webx-ui/php': patch
---

`webx-ui/module-media` stops shipping its tests, and stops flaking

Seven of the eight composer packages carry a `.gitattributes` that keeps `tests/` out of the
published archive and normalises line endings; `module-media` was the one that did not, so its
test suite has been riding along to Packagist. It has one now, the same one.

The tests it keeps to itself are one bug lighter. A fake upload is a blank canvas of the size
asked for and nothing else — the name is never drawn into it — so two of them with the same
dimensions are the same bytes, and the store deduplicates by content inside a directory. One
helper drew a random width to keep its two files apart, which worked about seven hundred and
ninety-nine times out of eight hundred; the other trusted a unique name, which never mattered at
all and held only because no test yet puts two such files in one folder. Both now give each file
a width of its own, the way the third helper already did.

---
'@webx-ui/php': patch
---

The file library moves into the `System` group

`MediaModule` had no `group()` at all, so `Files` hung at the top level of the menu next to
`Pages` — as if a file store were one of the things a site is made of, rather than a tool the
sections share. It now returns `'system'` and leads that group with `order() = 500`, ahead of
`Blocks` (600), `SEO` (700), `Settings` (800) and `Administrators` (900): of everything in
`System`, the library is the one an editor opens while writing.

The cost is a click: the library is now behind a group that starts collapsed. If it turns out
to be opened more often than the settings around it, the order to reconsider is this one — but
on watched use, not on argument.

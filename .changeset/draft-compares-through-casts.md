---
'@webx-ui/php': patch
---

A draft is compared with the published record through the model's casts: an event's untouched `all_day` (`false` in the form, `0` in the column), a date sent as ISO with an offset, a decimal written `12.5` no longer count as changes — in `*_discard` dry runs, and when deciding that an update changing nothing leaves no draft.

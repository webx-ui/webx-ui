---
'@webx-ui/php': patch
---

A block that stands on one page is refused in words that fit one page

`page.delete-used` has `:count` in it, and Russian — like English — gets that wrong at exactly
one, which is when a type is most likely to be looked at: it has just been put somewhere for
the first time. The dictionary gains `page.delete-used-one` and `page.on-page` in all ten
languages, and the controller picks the line rather than the number.

The same file gains the words the editor screen grew this round — the captions of the three script
examples and the three of the icon picker — and loses `page.sample-help` and `page.usage-empty`,
whose places on the screen are gone.

---
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': patch
'@webx-ui/module-blocks': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-seo': patch
'@webx-ui/php': patch
---

One way for the panel to say when something happened

`module-admin` gains `useDates()` and `WxDate`, and every section that showed a date now calls
them: "today at 08:10", "yesterday at 14:03", "16 September at 14:03", "16 September 2025",
"never". Twenty-four hours and no seconds in the column; the exact moment stays in a tip and in
`<time datetime>`.

There were three formats on three screens before this, all of them `toLocaleString()` with no
locale — so a panel drawn in Russian dated its rows in American order, with `AM/PM` and seconds
nobody wanted. The month names and the order of the parts now come from `Intl` in the panel's
own language; only `today`, `yesterday` and `never` are translated by hand, and the word for
"never" moved out of the two modules that each had their own copy.

"Today" is counted in the reader's calendar day rather than in UTC, which is what the server
sends: the small hours of a morning are today to the person reading them. Sorting is untouched —
a column still sorts on the value the server sent, never on the words.

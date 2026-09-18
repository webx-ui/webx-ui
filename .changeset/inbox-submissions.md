---
'@webx-ui/module-admin': minor
'@webx-ui/module-inbox': minor
'@webx-ui/php': minor
---

The submissions: the list, the card, and notes on any record of the panel.

**`@webx-ui/module-admin` and `webx-ui/module-admin` — notes.** A record somebody can write a
note on takes `HasNotes`, declares `Notable` and names the permission its notes are behind; the
table, the endpoint and the `WxNotes` feed are the panel's own, so the next section that wants
one — an order, a client — adds a trait rather than a copy. Two things the shared endpoint
cannot be allowed to get wrong are closed in it: the type in the address is an alias of the
morph map and never a class name, and the permission is the record's answer, never the
controller's guess.

**`webx-ui/module-inbox` — the panel's side of a submission.** One form's list, with its own
`in_table` fields as columns and the counts of every tab travelling beside the rows; spam out of
"all" and reachable by its own tab; a pile moved, marked or thrown away row by row, so the log is
written and the attachments go with it. Beside it, one submission opened at an address of its
own: the answers with the words they were asked in, the files, what the intake saw around it, the
status and the assignee, the notes, the log, a reply by `mailto:`, and the arrows to the next one
in the same filtered pile. Submissions can also be typed in by hand, through the same intake as
the public door — a call that came by telephone lands in the same list, and carries none of the
administrator's own browser and address as if a visitor had them.

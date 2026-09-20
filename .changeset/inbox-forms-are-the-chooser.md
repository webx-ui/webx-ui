---
'@webx-ui/core': minor
'@webx-ui/module-inbox': minor
'@webx-ui/php': minor
---

The inbox opens on the submissions, and the forms are the chooser

Somebody opens "Inbox" to see what has come in. What the section showed them was a column of
three form names, and on a phone that column was the whole screen: the submissions were a
record opened beside it, so they lived in the drawer and the reader had to pick a form before
seeing anything at all.

The two swap places. The forms are the `filters` column of `WxListDetail` — the thing that
narrows the list — and the submissions are the list. Nothing moves on a wide screen: the
forms are still 270px down the left. On a narrow one it is the forms that fold, into a panel
raised by a **Forms** button in the head of the submissions, and the list is the screen. One
form is always open — the first, unless the address names another — which also covers an
address naming a form that has since been deleted.

`WxListDetail` grew the case that makes this possible: with no `detail` slot, the list is the
main pane rather than a fixed column with an empty pane beside it, and the only threshold left
is the chooser's, `filtersWidth + detailMin`. It is the shape for a list whose records open on
a route of their own — which is what a submission does, and what a file in a library does.

Gone from the head of the submissions: **Settings**. It is an action on the form, and the
form's own `···` in the list of forms already offers it beside Duplicate and Delete — a second
door on the same strip, one word away from the list it was not about. `panel.choose-form` goes
with it on both halves: there is no longer a moment with no form chosen.

The button in the head is now **New submission**. The section is opened to read what came in
dozens of times for every once a form is added, and what stood there in blue was the form: a
new form is the `+` over the list of forms, beside the things it makes one more of, and in the
drawer — where an icon alone under the drawer's heading reads as a stray mark — it is a button
with the word on it. The dialog stays with the list of submissions and is exposed to the head,
because what is written has to land in that list, in the filter that is on, and be counted in
its tabs. On a narrow screen **Forms** joins it up there, so the two ways out of the list stand
together instead of one being in the head of the section and the other in the head of the pane.
`panel.new-submission` reads "New submission" rather than "Add by hand" on all ten dictionaries;
the dialog it opens still says which case it is for.

`WxListDetail` says `filters-inline` whenever the chooser's column appears or folds, and once at
the start. The `list` slot has always been handed that as a slot prop, but a head that stands
outside the pane — above the card, where a screen's actions live — cannot read one.

One inset, kept by the pane. The name of the form, the tabs, the search box and the rows now
all begin on the same line down the left: the table added a step of its own inside the pane's,
which is exactly what `flush` says it should not, and on a phone that put the head at 17 and
the list at 33 — two panels stacked rather than one screen. The change is a rule removed from
this screen, so no other list in the panel moves.

What scrolls is now the page. The section used to be as tall as the window with the rows
scrolling inside a box of their own: a bar down the middle of the screen, and a wheel that
meant one thing over the rows and another an inch to the left. Every other list in the panel
scrolls as a page, and this one does too — the card is as tall as what is in it.

A switched-off form is said by its name, struck through and grey, instead of by a badge
beside it. The badge did not shrink, so in a 270px column already holding a name, a count and
a `···` it ran under the menu — measured at 396px against a row ending at 346 — and it said in
a word what the type says at a glance. The strike is on the name only: the count beside it is
still true.

Fixed on the way: between 640 and about 672 pixels the pane and the table measured the same
threshold a step apart — the pane's own padding stood between them — and the table drew cards
out of the full set of columns, five lines of "Label: value" for one enquiry. The pane decides
now and the table is told, so a tablet holds fourteen rows where it held three cards.

---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': patch
'@webx-ui/module-blocks': patch
'@webx-ui/module-media': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-seo': patch
'@webx-ui/module-settings': patch
'@webx-ui/php': minor
---

Lists that mean what they show: a tree stays a tree, a row promises only what it does, and
anything that cannot be undone asks first.

**The page tree is a table at every width.** Below 640px it used to become cards, and a card has
no indentation to read and no chevron to open — so the section quietly asked the server for a flat
list instead, and a phone had no tree at all. The cards were the mistake, not the tree. The table
now drops columns as the width goes: when it was last touched, then what state it is in, then
where it lives, until a row is its title and its `···`. Measured at 375px: 65px a row against
250px a card, ten pages on screen instead of three and a half, with the chevron still opening
branches.

**`WxTable` takes a `clickable` prop.** It still infers the answer from whether anybody listens
for `row-click`, which is right for an ordinary list and needs nothing said. It is not right for a
list whose rows stop leading anywhere while it is on screen — the bin of `Pages`, an archive, a
picker taking several rows at once — because the listener a component was rendered with cannot be
read again. `:clickable="false"` withdraws the whole promise: no pointer, no highlight, and no
`row-click` either. The bin, the two SEO lists for a reader who may not edit them, and the
administrator picker in multiple mode all say so now.

**One place decides what a failed request says.** `useErrorText()` turns an error into a sentence
in the panel's language. A 422 is repeated word for word — every refusal that reaches one is
written by a module to be read — and every other status gets the panel's own words, so clicking a
page somebody else deleted says "It is not there any more" rather than
`No query results for model [WebxUi\Pages\Models\Page] 8`. Twenty-odd places that printed the
server's `message` now go through it, and `webx-admin::errors` ships the lines in ten languages.

**Confirmations, in numbers.** Restoring from the bin, publishing a page, moving a branch by drag
or by the "Inside" picker, deleting a block that holds others and deleting an empty media folder
all ask now, and the question carries the consequence as a figure: how many pages come back, which
address the page starts answering at, how many addresses a move rewrites, how many blocks go with
the one being removed. A move of a single page stays a gesture and asks nothing, because a redirect
is left on every address a move vacates — there is no undo to offer, only a second move.

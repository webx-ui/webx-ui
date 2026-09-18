---
'@webx-ui/module-inbox': minor
'@webx-ui/module-admin': minor
---

`@webx-ui/module-inbox`: the section, the form editor and the statuses.

One screen rather than two: the forms of the site in a column that is dragged into order, each
with what is waiting in it, and the submissions of the chosen one beside them. The columns of
that list are the fields of the chosen form, so a list of everything would have had no columns
worth the name — and the two questions this section is opened to ask, "what is new" and "what
does this form ask", are now one click apart instead of one screen apart. Which form is open
lives in the address, so a link to it is a link somebody can send.

The editor is five tabs and one save, with the fields as the exception: a question is a row
with an identity that answers point at, so it is written in its own dialog, dragged into its
own order and deleted softly. Its settings are the ones its type has and no others. The
embedding tab prints the one line that puts the form on a page and the names the page fills
its hidden fields by.

`@webx-ui/module-admin` gains `landing` on a module and a route for `/`. Nothing answered
there until now — the routes are the modules' and none of them claimed the root — so signing
in landed on a blank page. The landing module is honoured once the manifest says the panel
actually has it, and the first entry of the menu is the fallback.

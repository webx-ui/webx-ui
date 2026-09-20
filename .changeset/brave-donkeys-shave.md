---
'@webx-ui/module-blog': minor
'@webx-ui/php': minor
---

The address of an article is one row, not two

The settings tab used to hold a field labelled "Address" and, directly under it, a row also
labelled "Address" printing the whole thing. A full row of the form, and a second label, spent
on one constant segment — `/blog/` — which taught the reader to skim both. The spec had asked
for the other thing all along: "the address, with the prefix pasted on the left".

So the prefix moves inside the control. `wx-article-slug` replaces the pair of `wx-input` and
`wx-article-address`: a localized text field whose `#prefix` is the prefix of the blog, set in
the same monospace face the address is read in, with the language chip still on the right. The
whole address is now read and written in one place, and the card is a row shorter.

What is kept is the part that is not a duplicate: the line that says an article on the site is
about to answer at a different address and that the old one will keep working. It appears only
when there is something to lose, and it still appears before the save rather than in a toast
after it.

Gone with the row: `WxArticleAddress` and the node type `wx-article-address`, and the words
`article.address` and `article.no-address` on both halves. A project that patched the `address`
node of `blog.article-form` has no node to patch any more — the id is not in the screen.

The server registers `wx-article-slug` as the text type `wx-input` already was, so what a save
is checked against does not change.

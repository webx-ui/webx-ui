---
'@webx-ui/module-blog': minor
'@webx-ui/php': minor
---

Blog: the screens for rubrics and tags

**Rubrics** are a menu, so they are edited as one: `WxListDetail` with the list on the left,
dragged into the order the site has them in, and the form for the one that is open on the right.
No paginator and no search — a site has eight rubrics, and a menu you have to search is a menu
that is already wrong. The form looks up `wx-media` and `wx-seo` in the panel's own type
registry rather than importing either, so a panel without the file manager or without SEO gets a
shorter form instead of one that will not mount. The SEO card starts folded behind a sentence
saying where the title of the page comes from without it.

Deleting a rubric that still holds articles is refused with the number in the message, and the
button stays on screen and out of reach with the reason beside it: a button that disappears does
not answer "why can I not delete this".

**Tags** are entered from the article form by the hundred, so the screen is built for raking them
over. Renaming happens in the row — Enter saves, Escape puts back — and the address does not move
with the word, because a tag spelled three ways before lunch would otherwise leave three aliases
behind a decision nobody made. Selecting rows raises a bar that opens, closes or deletes the pile
at once, and merges it: the articles move over, the pivot deduplicates, and a checkbox decides
whether the addresses that existed go on answering as redirects. The merge is irreversible and
the dialog says so.

The column **Indexing** has three states, not two — `indexed`, `indexed — SEO rule`, `noindex` —
and the filter beside it counts by the same rule the rendered page follows, through
`UrlRuleSource::hasRuleFor()`. Anything less leaves the editor who wrote the rule looking at a row
that says `noindex` about a page that is in the index.

Server side: `GET/POST/PUT/DELETE /api/cms/blog/rubrics` with `rubrics/reorder`, and
`GET/POST/PUT/DELETE /api/cms/blog/tags` with `tags/merge` and `tags/mass`. The tags endpoint is
one answer to "which tags are there": the dropdown on the article form asks for its first page.
`HasUrl` gains `urlOf()`, so a screen that has already loaded the `routes` relation for a page of
rows does not go back to the registry once per row to learn what it was handed.

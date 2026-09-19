---
'@webx-ui/module-blog': minor
'@webx-ui/php': minor
---

`Blog`: the section, the panel API and the list of articles

The blog arrives in the navigation as three entries under one heading — Articles, Rubrics,
Tags — because the panel draws one entry per module and a blog wants three. Rubrics and tags are
declared on the server and stay out of the menu until their screens are written: an entry with
no screen has nowhere to send anybody, so it is silently skipped.

The API is a paginator rather than a level of a tree, which is the whole difference from
`Pages`: `GET /api/cms/blog/articles` with a search term, a rubric, a tag, an author and a
state, plus create, save, publish, unpublish, delete and restore. Every filter is a subquery and
none of them is a join — an article is in several rubrics and carries several tags, and joining
the pivot turns a page of twenty into seventeen articles with three of them drawn twice.

Five states, and the pair worth keeping apart is the last two: an article that was never
published and one that was taken off the site this morning both have no publication date, and
only the history tells them apart. Publishing takes an optional date, so "on the site next
Tuesday" is that date and not a scheduler.

What is saved goes to two places, and the split is deliberate. The title, the address, the lead
and the cover go into the draft — the site keeps showing what was published. The rubrics, the
tags, the related articles and the pin do not, and cannot: a pivot row is not a column, and
there is no such thing as half a row. A translated field travels as its whole language map, so
saving from a Russian panel that is showing an English fallback no longer copies the English
title into the Russian slot.

`@webx-ui/module-blog` is the front end: the list with its filters, its views as tabs, the bin,
and a row menu. Below 640 pixels the row becomes a card with the cover on the left and the title,
one rubric, the state, the date and the author beside it — ten articles on a phone screen rather
than two.

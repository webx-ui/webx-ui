---
'@webx-ui/php': minor
---

`webx-ui/module-blog`: the package, the addresses and the public half

Articles, rubrics and tags. Almost none of it is written here — the address is `routing`, the
content is `module-blocks`, the draft and the history are `module-admin`, the covers are
`module-media`, what a page says about itself is `module-seo` — and what the package adds is the
three things that make an article an article rather than a page: a date, several rubrics, and
tags.

Three types in the registry under one prefix (`webx-blog.prefix`, `blog` by default), all
`OnConflict::Fail` in one flat namespace: a rubric called "Repairs" and an article slugged
`repairs` are one address, and the second of them is an error under the field rather than a
quiet `repairs-2`. The rubric is deliberately not part of an article's address — an article has
three of them, "which one" has no answer, and any answer would be a hidden main rubric that
moved the article when somebody reordered the checkboxes.

Publication is one column and no scheduler. `published_at` in the future means the article is
waiting, in the past means it sits where that date puts it in the feed, and which of the two it
is gets decided where the article is read. Worth remembering: to the frame underneath, a
scheduled article is already published, so a general count of live records elsewhere in the
panel counts it.

A rubric has `is_visible` instead of a draft, and refuses to be deleted while it holds articles,
naming how many — its articles are not its property, and a soft-deleted rubric with live
articles in it is a hole in the navigation nobody notices. Tags merge into one, and the
addresses that existed can be kept as rows in `seo_redirects`: an alias of `routing` is keyed to
the entity and dies with it.

A tag page is out of the index by default, and a rule in `seo_urls` for its address opens it
completely. That cannot be a merge of fields — a rule filling in a title and leaving `robots`
empty would leave the module's `noindex` standing underneath it, and the editor who wrote the
rule would never find out — so `Panel\UrlRuleSource` in `module-seo` gains `hasRuleFor()`, over
the same compiled list `UrlMatcher` works on, and the blog asks that instead of matching masks
of its own.

The public half ships as five bare views, a feed at `{prefix}` with `?page=`, an RSS, and worked
out "read next": pinned first, then most tags in common, then the main rubric.

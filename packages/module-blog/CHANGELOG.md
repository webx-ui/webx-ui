# @webx-ui/module-blog

## 0.1.0

### Minor Changes

- f87e4ec: The article editor: tabs, blocks, autosave, the day it goes out

  `blog.article-form` is a described screen, like the page editor and for the same reason: the SEO
  card arrives as a patch from `module-seo` rather than being named in the blog's own description,
  and a project adds a tab the same way. Four tabs — the block constructor, the settings, SEO and
  the history — with a head above them that never moves and an action bar below.

  The settings are §10 of the spec: the address printed whole under the field that edits its last
  segment, the lead with a counter, the rubrics as a list that is dragged into order because the
  first one is the main one, a tag box that makes the tag it cannot find, the author, the cover,
  the pin, and the articles pinned under this one by hand. Five of them are node types the blog
  registers on both halves, so a rubric that is not a rubric is refused where every screen is
  checked rather than wherever somebody remembered.

  **The day is the part that is not a page editor.** The date in the settings tab is what
  "publish" publishes under, and the bar says which day that is before it is pressed: ahead, the
  article waits and answers 404 until its morning; behind, it moves down the feed. For an article
  that has never been on the site the day waits in the draft, because `published_at` is what "on
  the site" means and there is no column for a date that has not happened yet. For one that is
  already dated, moving the date writes the column at once — every listing orders by it.

  `WxActionBar` wraps. Its state box may shrink to nothing, and the words in it went on being
  painted where the box no longer was — straight across the buttons. Measured on a 375px screen:
  the box 0px wide and 105 tall, "Saved · goes out on 25 September at 17:06" over the top of "Save
  draft". Past the width of a short sentence the buttons now take a line of their own, still
  against the end of the bar.

  Saving is autosave, checked against the revision the form read and refused with a 409 when
  somebody wrote in between; the answer carries the article as it now is, so the panel asks which
  version the site gets instead of keeping one of the two silently. `PUT` now takes the screen's
  `values`, the history has its own two routes, `POST .../discard` throws away what is waiting,
  and `GET|POST /blog/tags` is the half of the tags API the article form needs — the screen that
  rakes them over comes with session D.

- f87e4ec: `Blog`: the section, the panel API and the list of articles

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

- f87e4ec: Blog: the screens for rubrics and tags

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

### Patch Changes

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0
  - @webx-ui/module-admin@0.9.0
  - @webx-ui/module-blocks@0.5.1
  - @webx-ui/schema@0.3.1

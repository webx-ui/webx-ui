---
'@webx-ui/module-blog': minor
'@webx-ui/core': patch
'@webx-ui/php': minor
---

The article editor: tabs, blocks, autosave, the day it goes out

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

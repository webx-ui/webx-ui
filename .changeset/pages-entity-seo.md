---
'@webx-ui/php': minor
---

A page says something about itself. `webx-ui/module-seo` grows the half it had deliberately left
out: `seo_meta`, one translated row per entity; the `HasSeo` trait, which is the whole of what a
content module has to write (`$page->seoValue()`, `$page->saveSeo()`, `$page->seoData($locale)`);
and `EntitySource` at priority 50, between the rules an editor wrote for an address and the
defaults the site falls back on — a rule was written because a page was wrong, so it wins; the
defaults are what is said when nothing was said, so they lose. `wx-seo` is now a field type on the
server as well, so the card can be dropped onto any entity's screen by a patch, and
`webx-ui/module-pages` gets it that way: the SEO tab of the page editor is filled in by the SEO
module rather than described by the pages one. What the card holds is saved the moment it is
saved, on a published page and on an unpublished one alike — it never goes into the draft, because
a description that only reaches search engines at the next publication is the kind of thing an
editor finds out about from a search engine. An emptied card deletes its row instead of keeping
one full of blanks, which is what lets the site's defaults back through.

---
'@webx-ui/php': patch
---

The blog's five public views are plain, not broken

Two things an unstyled page still owes the reader. Without `max-width: 100%` a 1200px cover
pushed a phone's page out to 1248px and took every line of text off the screen with it — three
rules in a partial the four page views include, the same three `module-pages` shows in its own
example. And nothing derives a title from an entity, so an article whose SEO card was never
filled had no `<title>` at all: each view now falls back to what it is about when the card and
the defaults are silent, which is what the demo site had already written by hand for pages.

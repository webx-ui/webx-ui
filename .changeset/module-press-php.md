---
'@webx-ui/php': minor
---

New `webx-ui/module-press`: the outlets that wrote about the site — a logo, a name, a few words and
a link — and the articles in them, each leading out to the article or to a PDF from the library.
A page per outlet under its own prefix, seen in a language only where it has an article titled in
it; kinds of article from the config; the date to the day, the month or the year; `press()` for
templates; schema.org `ItemList` of `Article`s on an outlet's page; three offered block types — a
strip of logos, a catalogue of outlets grouped by kind, a feed of the latest articles.
`module-seo` patches its card onto the new screen, `webx:setup` and `webx:doctor` know the
package, and `BlockOffers::offer()` takes a callback that puts the site's config into a document as
it is installed.

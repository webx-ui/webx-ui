---
'@webx-ui/php': minor
---

SEO: rules for addresses, redirects, and the `<head>` a page prints

`webx-ui/module-seo` is the panel section for SEO that belongs to no entity, and the renderer that
turns it into markup. Sources are asked in order and merged **field by field** — a rule that fills
in nothing but a title keeps the description and the picture that came from below it — so the
entity source that arrives with the first content module is an addition, not a change.

One matcher serves rules and redirects alike: exact, then mask (`*` inside a segment, `**` across
them), then a regular expression, by priority inside each group, against the path with its query
string. A pattern that will not compile is refused when it is saved and never matches if it got in
anyway. The active ones are one compiled list in the cache, dropped whenever any of them changes.

Redirects run as global middleware rather than in the `web` group, because the addresses worth
redirecting are the ones the site has no route for and those never reach a group at all — with the
panel's own paths stepped over, so a mask cannot lock an editor out of the screen they wrote it on.
`/robots.txt` answers from a setting; the SEO tab of the settings screen now comes from the module
instead of from each project's own patch. `POST /seo/test-url` says what an address ends up saying
and where every part of it came from.

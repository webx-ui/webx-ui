---
'@webx-ui/php': minor
---

One place decides how an address is written: routing's resolver asks a `Spelling` contract (the registry's own by default — one slash, none at the end, lower case, a file keeps its case), and `module-seo` binds it to the «One address per page» settings, the same function its middleware uses. An address takes at most one 301 for every difference at once — the mirror, https, slashes, the index file, case, the trailing slash — never a chain and never a loop; «keep as it is» is obeyed instead of the registry imposing its own spelling; the panel follows the mirror and https too. «With a slash» is no longer offered (a saved one reads as «keep»). A migration writes the registry's spelling into the SEO tab where nobody had saved it, so the tab shows what the site does.

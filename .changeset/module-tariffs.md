---
'@webx-ui/php': minor
---

`webx-ui/module-tariffs`: price cards — a name, a badge, a price in a currency from a list the
site configures or words instead of one, a period, what the plan includes, a description, one
button with a look from the config, and a "recommended" mark — in flat groups, and linked to the
services they are for when the site has `module-services`. No page of their own: they reach the
site in the offered block **Tariffs** (a slider with arrows and a count, or a grid — "what it
costs" on a service's page is the grid with "only related to the current one") and through
`tariffs()` in a template, `tariffs()->categories()` for a page of prices with a tab per group.
`webx:setup` knows the module as `tariffs`, and `webx:doctor` checks `tariffs()`.

To an agent the section is `tariffs_*` and `tariff_groups_*` — rows of the list as strings or maps
of languages, the button as `{ label, link, variant }`, a currency or a look the site lacks refused
with the keys it has — and `tariffs://catalog`; `webx:demo` seeds the sample it was drawn from and a
page `/pricing` with the block.

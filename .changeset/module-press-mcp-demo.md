---
'@webx-ui/php': minor
'@webx-ui/module-press': minor
---

The press for agents and in the demo. Ten MCP tools — `press_list`, `press_get`, `press_create`,
`press_update`, `press_delete`, `press_reorder`, and the articles one at a time with
`press_articles_add`, `press_articles_update`, `press_articles_delete` and `press_articles_move` —
each a save of the outlet's form in one transaction, and `press://catalog` to read first: every
outlet with its articles, where each is seen and where it leads. `webx:demo` brings four outlets
and eight articles with logos and a PDF in the library, and a page at the prefix with the strip of
logos over the catalogue. The panel registers as `press()` rather than `...press()`, and the address
field of an outlet no longer warns of a move when the panel and the edited text are in different
languages.

---
'@webx-ui/php': minor
---

Compositions after the pilot: a base says `vars` — the variables its leaves get — and `dispatcher` — the view that includes it; `webx:composition:show` prints both (`--json` too), and the JSON schema knows them. `webx:theme:check` looks for a copy of a composition by its `dispatcher`, notes a file for the handle of a page composition's own name (`blog.article/blog.article.json`) and a theme's view that reads a variable the leaves do not get. `WebxUi\Compositions\Testing\ComparesHtml::assertSameHtml()` compares HTML up to the whitespace between tags. module-blog: the article includes `blog.article` with the handle `default` — a theme patches `compositions/blog.article/default.json`; the base says `vars` (`article`, `related`, `content`) and `dispatcher` (`webx-blog::article`).

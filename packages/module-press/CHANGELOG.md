# @webx-ui/module-press

## 0.1.0

### Minor Changes

- 50ea8a5: The press for agents and in the demo. Ten MCP tools — `press_list`, `press_get`, `press_create`,
  `press_update`, `press_delete`, `press_reorder`, and the articles one at a time with
  `press_articles_add`, `press_articles_update`, `press_articles_delete` and `press_articles_move` —
  each a save of the outlet's form in one transaction, and `press://catalog` to read first: every
  outlet with its articles, where each is seen and where it leads. `webx:demo` brings four outlets
  and eight articles with logos and a PDF in the library, and a page at the prefix with the strip of
  logos over the catalogue. The panel registers as `press()` rather than `...press()`, and the address
  field of an outlet no longer warns of a move when the panel and the edited text are in different
  languages.
- 50ea8a5: The press section of the panel: the outlets on the left, in an order dragged by hand, each with
  its logo, the number of its articles and the languages it is seen in — and a warning on one that
  is published and seen nowhere; the outlet on the right, as a form of three tabs, General, Articles
  and SEO, saved whole with one button or Ctrl+S. The articles are rows of that form: folded to
  "#1 · title", reordered by their grips, a link, a PDF from the library, or both.

### Patch Changes

- Updated dependencies [50ea8a5]
- Updated dependencies [85b8fca]
  - @webx-ui/core@0.34.0
  - @webx-ui/schema@0.6.2
  - @webx-ui/module-admin@0.18.1

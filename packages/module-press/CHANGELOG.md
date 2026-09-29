# @webx-ui/module-press

## 0.1.3

### Patch Changes

- Updated dependencies [2ba4290]
  - @webx-ui/module-admin@0.20.0

## 0.1.2

### Patch Changes

- Updated dependencies [d1ff63d]
  - @webx-ui/module-admin@0.19.0

## 0.1.1

### Patch Changes

- 6686e25: Tails from the last five modules. `WxInputNumber` with `precision` shows a whole number bare
  (`1380`, not `1380.00`) and a fraction to the precision (`1380.50`); the model is untouched. A
  refusal of a repeater row (`duties.1.text.ru`) is drawn under that row only, no longer a second
  time under the repeater as a whole. Ctrl+S in the editors saves also when a click on empty space
  left the focus on `<body>` — `useBodyKeys` in `module-admin`.
- Updated dependencies [6686e25]
  - @webx-ui/core@0.34.1
  - @webx-ui/schema@0.6.4
  - @webx-ui/module-admin@0.18.2

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

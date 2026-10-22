# @webx-ui/module-catalog-manticore

## 0.3.3

### Patch Changes

- e42643b: The catalog and its satellites no longer fall back to the English defaults of core components in a translated panel: drag handles, branch toggles, table spinners, picker search boxes and their empty answers are said in the panel's language. The words live in `webx-admin::filters` (`loading`, `reorder`, `search`, `nothing-found`, `expand`, `collapse`) in all ten languages, for any module to hand to a core component.
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
  - @webx-ui/module-admin@0.33.0
  - @webx-ui/core@0.45.0

## 0.3.2

### Patch Changes

- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
  - @webx-ui/module-admin@0.32.0
  - @webx-ui/core@0.44.0

## 0.3.1

### Patch Changes

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-admin@0.31.0

## 0.3.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0

## 0.2.8

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0

## 0.2.7

### Patch Changes

- Updated dependencies [152a118]
  - @webx-ui/module-admin@0.28.0

## 0.2.6

### Patch Changes

- Updated dependencies [be30498]
  - @webx-ui/module-admin@0.27.0

## 0.2.5

### Patch Changes

- Updated dependencies [e107a30]
- Updated dependencies [e107a30]
  - @webx-ui/module-admin@0.26.1
  - @webx-ui/core@0.41.0

## 0.2.4

### Patch Changes

- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
  - @webx-ui/module-admin@0.26.0
  - @webx-ui/core@0.40.2

## 0.2.3

### Patch Changes

- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
  - @webx-ui/core@0.40.0
  - @webx-ui/module-admin@0.25.0

## 0.2.2

### Patch Changes

- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
  - @webx-ui/core@0.39.0
  - @webx-ui/module-admin@0.24.0

## 0.2.1

### Patch Changes

- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/module-admin@0.23.5

## 0.2.0

### Minor Changes

- 3dfe40a: The console's `webx:catalog:index --rebuild` and the panel's «Rebuild» share one lock: the second
  one is refused, and the search index page holds its button back while the console rebuilds.
  `WxTable` keeps its heading row in sight while the page scrolls past a long table (`stickyHeader`,
  on by default). In the audit's page card, a picture that answered shows its thumbnail and opens
  full size; the stand's, unchecked and broken ones keep the placeholder and are not fetched.

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2

## 0.1.2

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1

## 0.1.1

### Patch Changes

- Updated dependencies [41f2059]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0

## 0.1.0

### Minor Changes

- 92c75c1: The search index in the panel (M3 of the Manticore engine): «System → Search index» — on the Manticore engine only — shows the server, each language's table against the products in the database, and the queue, and rebuilds an out-of-date table as a job on the queue with its progress (`search-index.view`, `search-index.manage`); `catalog_index_status` tells an agent the same, and why one product is or is not found. The list of products says so when the index does not answer and the database does (`fell_back`). A table of an older schema is asked and written by the columns it has until it is rebuilt, instead of failing the list and the queue. New npm package `@webx-ui/module-catalog-manticore`; `Indexer::rebuild()` reports its progress.

### Patch Changes

- 9226fe8: The Manticore engine accepted on a real shop export (93 677 products: a rebuild in 44 s, a category with ten facets in 44 ms, a corrected typo in 51 ms). A question that falls into a rebuild's swap is asked once more instead of failing; «System → Search index» counts a rebuild started from the console and follows it; the stock status in a product's document is the one the filter shows, so a hidden status is counted by neither engine.

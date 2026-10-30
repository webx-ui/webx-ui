# @webx-ui/module-catalog-landings

## 0.2.4

### Patch Changes

- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
  - @webx-ui/core@0.46.0
  - @webx-ui/module-admin@0.34.0
  - @webx-ui/schema@0.10.0
  - @webx-ui/module-catalog@0.10.1

## 0.2.3

### Patch Changes

- e42643b: The catalog and its satellites no longer fall back to the English defaults of core components in a translated panel: drag handles, branch toggles, table spinners, picker search boxes and their empty answers are said in the panel's language. The words live in `webx-admin::filters` (`loading`, `reorder`, `search`, `nothing-found`, `expand`, `collapse`) in all ten languages, for any module to hand to a core component.
- e42643b: Removing one row of a list asks first, in a small popover by the button («Delete?» — «Yes» / «Cancel»), instead of removing at once. `WxAction` gains `confirm`, `confirmText` and `cancelText`: with a question set, `click` comes only on a yes. `WxPopconfirm` puts the focus on Cancel, goes ahead on Enter and reads the question out as the panel's description. The panel's words are `useRemoveConfirm()` of `@webx-ui/module-admin` (`webx-admin::confirm.*`, ten languages); labels and categories of a record, related records, banner layers, landing products and sets, category properties, intervals, inbox choices, notification chats and emails, SEO links, FAQ rows and the mapping and column rows of an exchange profile ask with it. Deleting a whole record keeps its dialog.
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
  - @webx-ui/module-catalog@0.10.0
  - @webx-ui/module-admin@0.33.0
  - @webx-ui/core@0.45.0
  - @webx-ui/schema@0.9.5

## 0.2.2

### Patch Changes

- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
  - @webx-ui/module-admin@0.32.0
  - @webx-ui/core@0.44.0
  - @webx-ui/module-catalog@0.9.2
  - @webx-ui/schema@0.9.4

## 0.2.1

### Patch Changes

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-admin@0.31.0
  - @webx-ui/module-catalog@0.9.1

## 0.2.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0
  - @webx-ui/module-catalog@0.9.0
  - @webx-ui/schema@0.9.3

## 0.1.12

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0
  - @webx-ui/module-catalog@0.8.10
  - @webx-ui/schema@0.9.2

## 0.1.11

### Patch Changes

- Updated dependencies [152a118]
  - @webx-ui/module-admin@0.28.0
  - @webx-ui/module-catalog@0.8.9

## 0.1.10

### Patch Changes

- Updated dependencies [be30498]
  - @webx-ui/module-admin@0.27.0
  - @webx-ui/module-catalog@0.8.8

## 0.1.9

### Patch Changes

- Updated dependencies [e107a30]
- Updated dependencies [e107a30]
  - @webx-ui/module-admin@0.26.1
  - @webx-ui/core@0.41.0
  - @webx-ui/module-catalog@0.8.7
  - @webx-ui/schema@0.9.1

## 0.1.8

### Patch Changes

- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
  - @webx-ui/module-admin@0.26.0
  - @webx-ui/core@0.40.2
  - @webx-ui/module-catalog@0.8.6

## 0.1.7

### Patch Changes

- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
  - @webx-ui/core@0.40.0
  - @webx-ui/schema@0.9.0
  - @webx-ui/module-admin@0.25.0
  - @webx-ui/module-catalog@0.8.5

## 0.1.6

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
  - @webx-ui/module-catalog@0.8.4
  - @webx-ui/schema@0.8.1

## 0.1.5

### Patch Changes

- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/schema@0.8.0
  - @webx-ui/module-admin@0.23.5
  - @webx-ui/module-catalog@0.8.3

## 0.1.4

### Patch Changes

- a814314: A switch says what it turns beside itself, not in a heading above it. In screens a `wx-switch` or
  `wx-checkbox` node hands its label to the control (`labelProp` on a field type), and the form
  item keeps the help and the error; the hand-written forms of SEO, the inbox and the landings'
  filters follow.
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
  - @webx-ui/core@0.37.1
  - @webx-ui/module-admin@0.23.4
  - @webx-ui/schema@0.7.4

## 0.1.3

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2
  - @webx-ui/module-catalog@0.8.2
  - @webx-ui/schema@0.7.3

## 0.1.2

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1
  - @webx-ui/module-catalog@0.8.1
  - @webx-ui/schema@0.7.2

## 0.1.1

### Patch Changes

- Updated dependencies [41f2059]
- Updated dependencies [6743abb]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0
  - @webx-ui/module-catalog@0.8.0
  - @webx-ui/schema@0.7.1

## 0.1.0

### Minor Changes

- c01b228: First release: the section «Landings» of the panel — the list with the base as a tree, «needs attention», published, empty and the bin; the form with the set builder, the live number of products and the warning of a taken set, the address the set suggests, the texts, the default sort, the recommended products found and put in order, SEO and history; and «Create in bulk» with a preview and the queue's progress.

### Patch Changes

- Updated dependencies [64a5353]
- Updated dependencies [92c75c1]
  - @webx-ui/module-catalog@0.7.0

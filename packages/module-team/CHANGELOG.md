# @webx-ui/module-team

## 0.2.3

### Patch Changes

- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
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
  - @webx-ui/schema@0.9.4

## 0.2.1

### Patch Changes

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-admin@0.31.0

## 0.2.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0
  - @webx-ui/schema@0.9.3

## 0.1.16

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0
  - @webx-ui/schema@0.9.2

## 0.1.15

### Patch Changes

- Updated dependencies [152a118]
  - @webx-ui/module-admin@0.28.0

## 0.1.14

### Patch Changes

- Updated dependencies [be30498]
  - @webx-ui/module-admin@0.27.0

## 0.1.13

### Patch Changes

- e107a30: `WxSortableList`: проп `hoverable` — список сам подсвечивает строку под курсором и текущую (`.is-current` или `aria-current` внутри) и убирает разделители над ней и под ней. Рубрики и категории, места баннеров, вопросы FAQ и команда переведены на него.
- Updated dependencies [e107a30]
- Updated dependencies [e107a30]
  - @webx-ui/module-admin@0.26.1
  - @webx-ui/core@0.41.0
  - @webx-ui/schema@0.9.1

## 0.1.12

### Patch Changes

- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
  - @webx-ui/module-admin@0.26.0
  - @webx-ui/core@0.40.2

## 0.1.11

### Patch Changes

- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
  - @webx-ui/core@0.40.0
  - @webx-ui/schema@0.9.0
  - @webx-ui/module-admin@0.25.0

## 0.1.10

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
  - @webx-ui/schema@0.8.1

## 0.1.9

### Patch Changes

- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/schema@0.8.0
  - @webx-ui/module-admin@0.23.5

## 0.1.8

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2
  - @webx-ui/schema@0.7.3

## 0.1.7

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1
  - @webx-ui/schema@0.7.2

## 0.1.6

### Patch Changes

- Updated dependencies [41f2059]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0
  - @webx-ui/schema@0.7.1

## 0.1.5

### Patch Changes

- Updated dependencies [3a08c6c]
- Updated dependencies [67e44aa]
  - @webx-ui/module-admin@0.22.0
  - @webx-ui/schema@0.7.0
  - @webx-ui/core@0.34.3

## 0.1.4

### Patch Changes

- Updated dependencies [38c5b08]
- Updated dependencies [5ebd999]
  - @webx-ui/module-admin@0.21.0
  - @webx-ui/core@0.34.2

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

- 1966d54: `@webx-ui/module-team` — the team section of the panel: people with a photo, a job title, a short
  text, social links and the services they do, in one order dragged by hand. A list and the form of
  one person side by side (`?member=`), a new person written before they exist and made on the first
  save, the bin, `Ctrl+S`, and a question before unsaved words are left. One menu entry, no group,
  no categories.

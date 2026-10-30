# @webx-ui/module-catalog-properties

## 0.2.6

### Patch Changes

- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
  - @webx-ui/core@0.46.0
  - @webx-ui/module-admin@0.34.0
  - @webx-ui/schema@0.10.0
  - @webx-ui/module-media@0.12.1
  - @webx-ui/module-catalog@0.10.1

## 0.2.5

### Patch Changes

- d0e3145: Panel lists: the rules around a hovered or current row go. Properties, the inbox forms, reviews, press, tariffs,
  services, vacancies and recipes tint their rows through `WxSortableList`'s `hoverable` instead of rules of their own,
  so the lines above and below the tinted row are hidden, and the divider between reviews (press, tariffs) is straight
  rather than curved by the row's radius. `WxSortableList` with `plain` drops the head's own top step — the frame that
  holds a plain list insets it already — and the inbox's column of forms starts at the pane's step with its name on the
  pane head's line. The inbox search box stands the same 8px under the status tabs and over the table.
- Updated dependencies [d0e3145]
  - @webx-ui/core@0.45.3

## 0.2.4

### Patch Changes

- Updated dependencies [b4296aa]
  - @webx-ui/module-media@0.12.0

## 0.2.3

### Patch Changes

- e42643b: The catalog and its satellites no longer fall back to the English defaults of core components in a translated panel: drag handles, branch toggles, table spinners, picker search boxes and their empty answers are said in the panel's language. The words live in `webx-admin::filters` (`loading`, `reorder`, `search`, `nothing-found`, `expand`, `collapse`) in all ten languages, for any module to hand to a core component.
- e42643b: Every picture the panel shows small opens large: a click on it opens `WxLightbox` over the whole set it stands in, on the full picture and in the panel's language. `@webx-ui/module-admin` gains `WxPreviewThumb` (a thumbnail that is a button opening the gallery, keeping its click from the row) and `useLightbox()` (`open`, `labels`, and `click`, which leaves Ctrl and the middle button to a link); the words move from `webx-media::lightbox.*` to `webx-admin::lightbox.*` and gain `play` and `view`. Wired in: the product gallery (with its videos), the gallery and file fields and the picture field of forms — whose picture now opens large, swapping it is a button of the bar — the library (its double click and «View»), the covers of the lists of products, articles, events, services and recipes, the banners of a place, the pictures of property values and their dialog, the share picture of the SEO card and the pictures of an audited page. The panel rows of events and recipes carry `cover.url`, and those of banners `image`, for the full picture.
- e42643b: Removing one row of a list asks first, in a small popover by the button («Delete?» — «Yes» / «Cancel»), instead of removing at once. `WxAction` gains `confirm`, `confirmText` and `cancelText`: with a question set, `click` comes only on a yes. `WxPopconfirm` puts the focus on Cancel, goes ahead on Enter and reads the question out as the panel's description. The panel's words are `useRemoveConfirm()` of `@webx-ui/module-admin` (`webx-admin::confirm.*`, ten languages); labels and categories of a record, related records, banner layers, landing products and sets, category properties, intervals, inbox choices, notification chats and emails, SEO links, FAQ rows and the mapping and column rows of an exchange profile ask with it. Deleting a whole record keeps its dialog.
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
  - @webx-ui/module-catalog@0.10.0
  - @webx-ui/module-admin@0.33.0
  - @webx-ui/module-media@0.11.2
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
  - @webx-ui/module-media@0.11.1
  - @webx-ui/schema@0.9.4

## 0.2.1

### Patch Changes

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-media@0.11.0
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
  - @webx-ui/module-media@0.10.7
  - @webx-ui/schema@0.9.3

## 0.1.14

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0
  - @webx-ui/module-catalog@0.8.10
  - @webx-ui/module-media@0.10.6
  - @webx-ui/schema@0.9.2

## 0.1.13

### Patch Changes

- Updated dependencies [152a118]
  - @webx-ui/module-admin@0.28.0
  - @webx-ui/module-catalog@0.8.9
  - @webx-ui/module-media@0.10.5

## 0.1.12

### Patch Changes

- Updated dependencies [be30498]
  - @webx-ui/module-admin@0.27.0
  - @webx-ui/module-catalog@0.8.8
  - @webx-ui/module-media@0.10.4

## 0.1.11

### Patch Changes

- Updated dependencies [e107a30]
- Updated dependencies [e107a30]
  - @webx-ui/module-admin@0.26.1
  - @webx-ui/core@0.41.0
  - @webx-ui/module-catalog@0.8.7
  - @webx-ui/module-media@0.10.3
  - @webx-ui/schema@0.9.1

## 0.1.10

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
  - @webx-ui/module-media@0.10.2

## 0.1.9

### Patch Changes

- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
  - @webx-ui/core@0.40.0
  - @webx-ui/schema@0.9.0
  - @webx-ui/module-admin@0.25.0
  - @webx-ui/module-catalog@0.8.5
  - @webx-ui/module-media@0.10.1

## 0.1.8

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
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
  - @webx-ui/core@0.39.0
  - @webx-ui/module-admin@0.24.0
  - @webx-ui/module-media@0.10.0
  - @webx-ui/module-catalog@0.8.4
  - @webx-ui/schema@0.8.1

## 0.1.7

### Patch Changes

- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/schema@0.8.0
  - @webx-ui/module-media@0.9.1
  - @webx-ui/module-admin@0.23.5
  - @webx-ui/module-catalog@0.8.3

## 0.1.6

### Patch Changes

- Updated dependencies [17c2dfd]
- Updated dependencies [17c2dfd]
  - @webx-ui/module-media@0.9.0

## 0.1.5

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2
  - @webx-ui/module-catalog@0.8.2
  - @webx-ui/module-media@0.8.23
  - @webx-ui/schema@0.7.3

## 0.1.4

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1
  - @webx-ui/module-catalog@0.8.1
  - @webx-ui/module-media@0.8.22
  - @webx-ui/schema@0.7.2

## 0.1.3

### Patch Changes

- Updated dependencies [41f2059]
- Updated dependencies [6743abb]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0
  - @webx-ui/module-catalog@0.8.0
  - @webx-ui/module-media@0.8.21
  - @webx-ui/schema@0.7.1

## 0.1.2

### Patch Changes

- Updated dependencies [64a5353]
- Updated dependencies [92c75c1]
  - @webx-ui/module-catalog@0.7.0

## 0.1.1

### Patch Changes

- Updated dependencies [31a364b]
  - @webx-ui/module-catalog@0.6.0

## 0.1.0

### Minor Changes

- 3a08c6c: `module-catalog-properties` in the panel (P4 of the properties series). A new npm package, `@webx-ui/module-catalog-properties` — `catalogProperties()`: «Catalog» → «Dictionaries» → «Properties», the list with its flags as icons, the number of products, a filter by type and group, the order dragged and «Deleted» with a restore; the groups of the card behind a «Groups» button on the shared category screens; the page of one property, whose «Main» shows only the fields its type has, keeps the type fixed and prints a number as the site will (`⌀12 мм`), «Values» — a lazy tree with a search, a quick add, colour and picture from the media library, drag of order and nesting by hand, «Merge with…» that counts the products first — and «Intervals», a table saved at once with «Sort by bounds». A category gets the «Properties» tab (its set: inherited ones muted with where they come from, its own added by search, dragged and removed, saved at once), a product the «Specifications» tab: the set of its main category by groups — read again the moment the category changes, before saving — with a combobox that searches the book and creates a value, a tree picker, numbers with their units, a switch, a text per language, and the values outside the set in a collapsed block with one button to delete them. `module-catalog`: the product editor hands its live values to the nodes of its screen (`ProductEditorContext.values`). `module-admin`: the shared category screens take a `title` and a `back` for a list that is not an entry of the navigation. `schema`: `screenErrorsKey` is exported, for a node that puts each line of a refusal under its own control. The composer half: the section's module (order 312, under «Dictionaries»), the category-form patch, the screens' patches as files, `ids[]` on the lists of properties and values (a value by id carries its `ancestors`), and properties marked «column in the list» as columns of the list of products — the core's `ProductColumns` takes column sources (`ColumnSource`), asked on every read, for columns that live in the database.

### Patch Changes

- c6a2b7d: A number in the product's «Specifications» keeps its decimals: the field used to round `0.85` to `1` and would save the `1`. It now keeps the property's precision, or more when the product holds more.
- 67e44aa: Panel polish around catalog properties: the sidebar scrolls with a thin bar that keeps its room
  (and none on the rail), opens scrolled to the current section, and a menu group in a rail flyout
  keeps its heading and start alignment. A count before a row's buttons in `WxSortableList` gets
  air of its own. "Site regions" moves under "System". The interval delete button is the shared
  remove action, a product's characteristics sit on a card like the category's, the stock card stands under the price, and the labels, stock and brand fields no
  longer repeat the title of their card. On the characteristics tab the fields keep the step of the
  other tabs, and a yes/no property names itself beside its switch, as "Published" does.
- Updated dependencies [3a08c6c]
- Updated dependencies [67e44aa]
  - @webx-ui/module-catalog@0.5.0
  - @webx-ui/module-admin@0.22.0
  - @webx-ui/schema@0.7.0
  - @webx-ui/core@0.34.3
  - @webx-ui/module-media@0.8.20

# @webx-ui/module-catalog-properties

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

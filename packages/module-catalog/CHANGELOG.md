# @webx-ui/module-catalog

## 0.6.0

### Minor Changes

- 31a364b: The exchange of the catalogue in the panel (E3 of the exchange series). «Exchange» stands in the `···` of the products after «Deleted»: every import and export with its totals, the first hundred errors and all of them as CSV, the finished file of an export and what an import changed in the journal, with runs still going asked after every two seconds. Import is a wizard on `WxSteps` — a file uploaded in pieces or an address, its columns matched to the catalogue's with five rows of the file under each, then the settings — that checks the file without writing or runs it, and saves it all as a profile. Export writes the list's selection from «Actions» or the whole catalogue from «Exchange», by a profile or by columns chosen with their languages. Profiles have a list and a form. The settings of an import and the head of a profile are the described screens `catalog.exchange-import` and `catalog.exchange-profile`, registered by the server with their words in English and Russian. New exports: `createExchangeApi`, `useRunPolling`, `EXCHANGE_PURPOSE` and the exchange's types, and the pages as `WxCatalogExchange*`.

## 0.5.0

### Minor Changes

- 3a08c6c: `module-catalog-properties` in the panel (P4 of the properties series). A new npm package, `@webx-ui/module-catalog-properties` — `catalogProperties()`: «Catalog» → «Dictionaries» → «Properties», the list with its flags as icons, the number of products, a filter by type and group, the order dragged and «Deleted» with a restore; the groups of the card behind a «Groups» button on the shared category screens; the page of one property, whose «Main» shows only the fields its type has, keeps the type fixed and prints a number as the site will (`⌀12 мм`), «Values» — a lazy tree with a search, a quick add, colour and picture from the media library, drag of order and nesting by hand, «Merge with…» that counts the products first — and «Intervals», a table saved at once with «Sort by bounds». A category gets the «Properties» tab (its set: inherited ones muted with where they come from, its own added by search, dragged and removed, saved at once), a product the «Specifications» tab: the set of its main category by groups — read again the moment the category changes, before saving — with a combobox that searches the book and creates a value, a tree picker, numbers with their units, a switch, a text per language, and the values outside the set in a collapsed block with one button to delete them. `module-catalog`: the product editor hands its live values to the nodes of its screen (`ProductEditorContext.values`). `module-admin`: the shared category screens take a `title` and a `back` for a list that is not an entry of the navigation. `schema`: `screenErrorsKey` is exported, for a node that puts each line of a refusal under its own control. The composer half: the section's module (order 312, under «Dictionaries»), the category-form patch, the screens' patches as files, `ids[]` on the lists of properties and values (a value by id carries its `ancestors`), and properties marked «column in the list» as columns of the list of products — the core's `ProductColumns` takes column sources (`ColumnSource`), asked on every read, for columns that live in the database.

### Patch Changes

- Updated dependencies [3a08c6c]
- Updated dependencies [67e44aa]
  - @webx-ui/module-admin@0.22.0
  - @webx-ui/schema@0.7.0
  - @webx-ui/core@0.34.3

## 0.4.0

### Minor Changes

- 38c5b08: The catalogue's reference books in the panel: `@webx-ui/module-catalog-labels`, `-stock` and `-brands` — `catalogLabels()`, `catalogStock()`, `catalogBrands()`, each a section on the shared category screens with the number of products on a row and «Show its products» as the list narrowed by its facet. `module-catalog` gets the frame they stand on (`dictionarySection()`), the tone field of a label or a status (`wx-catalog-tone`), satellite columns drawn by the shape of their value (tags of a tone, a tag, a name muted when off the site) with breakpoints that keep the product's name readable, and bulk params asked out of a reference book (`source`). `module-admin`: `wx-select` takes `source` like `wx-categories`, and the shared category editor names its record to `wx-history`. `WxSelect` with `filterable` shows the label of a value whose options arrive after it. The composer half: `PartField` carries `source`, the list's facets of labels and stock come in the order an editor gave them, the product form's fields say what empty means.

### Patch Changes

- 5ebd999: A navigation group can split its entries with captions: the group declares `sections` in `webx-admin.groups`, a module stands under one by implementing `HasNavSection`, and the panel draws each caption with `WxMenuGroup`. The catalog group gets a «Dictionaries» caption for its reference lists, and «Categories» now comes before «Products».
- Updated dependencies [38c5b08]
- Updated dependencies [5ebd999]
  - @webx-ui/module-admin@0.21.0
  - @webx-ui/core@0.34.2

## 0.3.0

### Minor Changes

- 769e8a9: Videos in the product gallery, in the panel. A video is attached to a picture, which becomes its
  poster.

  - **Drop a video** (MP4 or WebM) into the gallery with the pictures. The browser takes a frame of
    it before anything is sent, and the frame goes up as the row's picture. The file then goes up in
    pieces through `useChunkedUpload` and is attached to that picture with its length. The row
    appears at once with its poster and a bar showing percentage, speed, time left, pause and cancel.
    If the browser cannot open the file, the panel says to add a picture and attach the video to it.
  - **Row actions**: «Attach a video file…» and «Attach a YouTube link…», or «Remove the video» (after
    a question) on a row that has one. A row with a video has a ▶ on its preview, and the preview
    opens the video.
  - **By address**: «Upload from an address» takes YouTube links and direct MP4/WebM links. A direct
    link is downloaded on the server, and the panel says so. A row left with the server's plain
    poster says how to get a real one.
  - **Uploads belong to the editor**, not the gallery's tab: they carry on while other tabs are
    open. Leaving mid-upload asks first, and an interrupted upload is offered again after a reload,
    to be continued by choosing the same file.
  - The screen's `video`, `videoTypes` and `videoMaxBytes` props on the `gallery` node set the
    limits, which are checked before a byte is sent. `video: false` (and a server that sends no
    prop) means no menu, no `video/*` in the zone and no upload.
  - `CatalogApi` gains `fetchImage`, `attachVideo` and `detachVideo`. `ProductImage` gains `video`,
    and the new types `ProductVideo` and `QueuedVideo` come with it. `createGalleryVideo` lets an
    editor of your own keep the uploads the way `WxCatalogProductEditor` does.

### Patch Changes

- Updated dependencies [2ba4290]
  - @webx-ui/module-admin@0.20.0

## 0.2.0

### Minor Changes

- 5f2f583: Catalog panel polish. The categories are an entry of the navigation of their own
  (`catalog-categories`), and the products' entry is called «Products»: `catalog()` now returns two
  modules — write `...catalog()` in `modules`. The product form puts the publish switch under the
  name, the unit beside the price and the priority on a «Settings» tab; every other tab is a card. The
  gallery rows are compact, with the size on the picture, and the upload from an address is a card of
  its own. A category's «Filters» tab is off by default (`webx-catalog.fields.facets`), and its
  picture is half as wide.

## 0.1.0

### Minor Changes

- 7d47c13: Bulk actions in the product list: tick rows or pick "everything found" (sent as the list's query
  and turned into ids by the server when the run starts), choose an action from the server's list
  — the core's and every satellite's, only those the administrator may start — and answer what it
  asks (a category from the tree). A small selection is done at once; a large one runs in the
  background with a progress bar, and what refused is listed by product and left selected. New in
  the API client: `bulkActions()`, `startBulk()`, `bulkRun()`, with the `BulkActionInfo`,
  `BulkSelection` and `BulkRun` types.
- 31ef272: The catalogue's screens in the panel (K3): the list of products with search, views, facets as
  filters, the satellites' columns and picked rows; the product editor with its gallery (order,
  `alt` and `title` in every language, uploads and pictures by address) and a way to the product
  holding a taken article number; the tree of categories with counts, drag and publication; the
  category editor with its «Filters» tab (own setting, or whose it inherits); and «Deleted».

### Patch Changes

- Updated dependencies [d1ff63d]
  - @webx-ui/module-admin@0.19.0

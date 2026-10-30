# @webx-ui/module-catalog

## 0.10.1

### Patch Changes

- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
  - @webx-ui/core@0.46.0
  - @webx-ui/module-admin@0.34.0
  - @webx-ui/schema@0.10.0

## 0.10.0

### Minor Changes

- e42643b: A catalogue label can have a colour of its own besides the six tones: `color` = `#rrggbb`, and `text_color` for the text on it — black or white by WCAG contrast when left empty. The editor offers «Own colour» next to the tones with a colour picker and a live preview; the panel's list of products draws the label in its colours; the card prints `wx-catalog-badge--custom` with `--webx-label-bg` / `--webx-label-fg` on the element, read by the new base stylesheet `resources/theme/catalog-labels.css`. `catalog_labels_create` takes `values` now (any category kind's `_create` does), and the labels' tools describe both fields. Stock statuses stay on tones. New migration: `text_color` on `catalog_labels`.

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
  - @webx-ui/module-admin@0.33.0
  - @webx-ui/core@0.45.0
  - @webx-ui/schema@0.9.5

## 0.9.2

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

## 0.9.1

### Patch Changes

- a33dc79: Private files, the library's pickers and the checks that asked the wrong permission (WEBX_UI_PERMISSIONS.md §22).

  Composer packages:

  - **Private files refuse a public disk.** `WebxUi\Admin\Support\PrivateDisk` (`module-admin`) calls a disk public when it has `visibility: public` or a local root inside the web root or `storage/app/public`. Attachments of submissions (`webx-inbox.disk`), the catalogue exchange (`webx-catalog.exchange.disk`) and database dumps (`webx-admin.backup.disk`) now throw a `RuntimeException` naming the setting before the first write, instead of serving visitors' files and exports to anyone with the address.
  - **A catalogue export is its starter's** (choice 11 (a)). Everybody with the catalogue still sees the runs; the file — `runs/{id}/file` and `file_url` — is for whoever started the run and super administrators. Others get `file_url: null`, the new `file_yours: false`, and **403** from `runs/{id}/file`. An import address is shown to them without its query, fragment and user (`ExchangeRun::withoutSecrets()`). `ExchangeRun::toResponse()` takes the reader; `catalog_exchange_run` answers the same way.
  - **Pickers count the `manage` of the same family**: the link picker, collections and relations let `x.manage` in where they asked for `x.view` alone (`AccessPolicy::sees()`, `AccessPolicy::seeing()`).
  - **The content of a record through the blocks tools needs the record's section too**: `blocks_set_content` and `blocks_edit_content` ask the section's permission from `SiteBar::entity()` (`pages.manage` for a page) besides `blocks.manage`; `blocks_get_content` and `blocks_preview_url` ask its `view` or `manage`.
  - **The rows of a history run** (`history/runs/{id}`, `history_runs` with `run_id`) are only those of types the reader may read. `HistoryReader::rowsOf()` takes `types:`.
  - MCP tools brought in line with the panel's routes: `catalog_index_status` is behind `search-index.view|manage` (no longer `catalog.view`); the catalogue's read tools also take `catalog.delete`; the audit's read tools also take `audit.run`; `tasks_stage_release` also takes `tasks.manage` (scope `tasks:write` or `tasks:work`); `tasks_comment` also takes the scope `tasks:review`. The panel's notification preview (`POST notifications/preview`, `channels/{id}/preview`) is open to `notifications.view`, as the tool already was.
  - The SEO interlinking and FAQ exports no longer leave an empty temporary file behind.

  `@webx-ui/module-admin`: `AdminModule.pickImageWith` — the permissions that let somebody open `pickImage`; without one `admin.pickImage` is `null` and `wx-rich-text` draws no image button. The backup note shows with `settings.manage` too.

  `@webx-ui/module-media`: the fields over the library (`wx-media`, `wx-gallery`, `wx-file`, `wx-files`) open the picker only for `media.view` or `media.manage`; without either they keep, caption and clear what is chosen and say the library is closed (`field.locked`). The module sets `pickImageWith` and does not ask the library for addresses without the right. New exports: `canBrowseMedia`, `MEDIA_BROWSE`.

  `@webx-ui/module-catalog`: a finished export that is somebody else's says whose it is rather than «no longer kept» (`file_yours`).

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-admin@0.31.0

## 0.9.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0
  - @webx-ui/schema@0.9.3

## 0.8.10

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0
  - @webx-ui/schema@0.9.2

## 0.8.9

### Patch Changes

- Updated dependencies [152a118]
  - @webx-ui/module-admin@0.28.0

## 0.8.8

### Patch Changes

- Updated dependencies [be30498]
  - @webx-ui/module-admin@0.27.0

## 0.8.7

### Patch Changes

- Updated dependencies [e107a30]
- Updated dependencies [e107a30]
  - @webx-ui/module-admin@0.26.1
  - @webx-ui/core@0.41.0
  - @webx-ui/schema@0.9.1

## 0.8.6

### Patch Changes

- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
  - @webx-ui/module-admin@0.26.0
  - @webx-ui/core@0.40.2

## 0.8.5

### Patch Changes

- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
  - @webx-ui/core@0.40.0
  - @webx-ui/schema@0.9.0
  - @webx-ui/module-admin@0.25.0

## 0.8.4

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

## 0.8.3

### Patch Changes

- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/schema@0.8.0
  - @webx-ui/module-admin@0.23.5

## 0.8.2

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2
  - @webx-ui/schema@0.7.3

## 0.8.1

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1
  - @webx-ui/schema@0.7.2

## 0.8.0

### Minor Changes

- 41f2059: `WxTable` takes `selectRowLabel` and `selectAllLabel` for its checkboxes, and every selectable list of the panel passes them translated (`filters.select-row`, `filters.select-all` in `module-admin`). The history shows a list of names as the names, comma-separated, instead of JSON. The catalogue's list of products warns when the database engine is past `sql_engine_limit`; the tree of categories shows an address without the trailing slash; the category picker of a bulk action says «Choose a category».

### Patch Changes

- 6743abb: The list of products sizes its columns by what they hold, so a 1440px window shows three satellite columns instead of one: a satellite 120, the price 120, the state 130, the category 140, the name from 180.
- Updated dependencies [41f2059]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0
  - @webx-ui/schema@0.7.1

## 0.7.0

### Minor Changes

- 64a5353: The catalogue search on Manticore (M2): the codes of a product — article number, barcode, external id — searched as written and by any part of their letters and digits, the product whose code the search is first, and the storefront going straight to its card; a search that finds nothing tried with the other keyboard layout of the site's languages, then corrected word by word, with «Showing results for …, search instead for …» on the storefront and in the panel; and 503 with `Retry-After` on the site's own page when the catalogue is too large to fall back on the database. The tables gain `codes`, `codes_flat`, `code_keys` and `min_infix_len`: `webx:doctor` reports them out of date, `webx:catalog:index --rebuild` makes them anew.
- 92c75c1: The search index in the panel (M3 of the Manticore engine): «System → Search index» — on the Manticore engine only — shows the server, each language's table against the products in the database, and the queue, and rebuilds an out-of-date table as a job on the queue with its progress (`search-index.view`, `search-index.manage`); `catalog_index_status` tells an agent the same, and why one product is or is not found. The list of products says so when the index does not answer and the database does (`fell_back`). A table of an older schema is asked and written by the columns it has until it is rebuilt, instead of failing the list and the queue. New npm package `@webx-ui/module-catalog-manticore`; `Indexer::rebuild()` reports its progress.

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

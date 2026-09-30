# @webx-ui/module-catalog

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

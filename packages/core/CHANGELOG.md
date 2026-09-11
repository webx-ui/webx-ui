# @webx-ui/core

## 0.2.0

### Minor Changes

- ec2d955: `WxTable` and `WxPagination`, the pair that renders a paginated list.

  `WxTable` takes a Laravel `->paginate()` payload as it arrives — or a plain array — and renders
  columns described in an object: a dotted `key` reads through an eager-loaded relation, `formatter`
  turns the value into text, and `cell-<key>` replaces the cell outright. Sorting cycles ascending,
  descending and off, and is reported rather than applied: the rows on screen are one page out of an
  ordered query, so reordering them here would shuffle the page and look right while being wrong.
  Selection holds row keys rather than rows, so it survives paging away and back, and the header
  checkbox works on the current page without disturbing keys picked elsewhere. Loading dims the table
  instead of emptying it, and the empty state waits for the load to finish.

  `WxPagination` reads the page, the size and the totals straight out of the paginator, keeps the
  first and last pages reachable, and spells out a gap of a single page rather than hiding it behind
  an ellipsis.

  The table paginates itself as soon as `data` is a paginator, and reports the page, the size, the
  sort and the search term together in one `state-change` event — fired on mount as well, so a single
  handler is the whole wiring. `persist="orders"` remembers that state in local storage and restores
  it on the next visit, which is why the mount event matters: the first fetch is the right one rather
  than a default followed by a correction.

  The table also carries a header bar — a title on the left, a debounced search field and `#actions`
  on the right — summary lines under the rows for totals that a caller works out, rows that open to
  show what does not fit in them, and columns pinned to either edge while the rest scrolls sideways.
  `max-height` caps the height and sticks the header and the footer to it.

  Both state their own `display`, `overflow`, `margin`, `border`, `min-width` and row background
  rather than inheriting them, so neither a host stylesheet that restyles bare `table`, `tr` and `li`
  elements nor a flex container that will not let its items shrink can take the layout away.

## 0.1.0

### Minor Changes

- b98bc03: Five secondary form controls: `WxRate`, `WxSlider`, `WxTagsInput`, `WxDateRangePicker` and
  `WxColorPicker`.

  `WxRate` is stars with optional halves, cleared by clicking the current value and moved with the
  arrow keys. `WxSlider` covers one thumb or two, with marks under the track, and keeps a plain number
  in the model for the single case rather than a one-element array. `WxDateRangePicker` stores
  `[start, end]` in the backend's format and shows two months at once. `WxColorPicker` is a hex field
  with the colour in it, opening a saturation square, a hue strip and preset swatches.

  `WxTagsInput` is written here rather than wrapped: Enter adds what was typed, Backspace marks the
  last tag and removes it on the second press, and suggestions come from a `search` event so the list
  can live on a server.

- 4336b09: Form controls: `WxForm`, `WxFormItem`, `WxTextarea`, `WxCheckbox`, `WxCheckboxGroup`, `WxRadio`,
  `WxRadioGroup`, `WxSwitch` and `WxInputNumber`.

  `WxForm` takes the `errors` object from a Laravel 422 response as-is; each `WxFormItem` looks up its
  own `name` and gives the control inside it the error state, the message and the `aria-describedby`
  wiring without the control needing any props. `disabled` and `size` cascade form → item → control,
  most specific winning.

  Every control is built on a native input, so keyboard behaviour and form semantics come from the
  browser. `WxInput` now takes part in the same contract.

- 61c184c: `WxCard` gains a `sidebar` slot: filling it splits the body into a narrow column and the main
  content. The columns stack once the card itself drops below 560px — a container query, so a card
  placed in a narrow column collapses even on a wide screen. Width is `--wx-card-sidebar-width`,
  `240px` by default.
- 766abfd: `WxSelect`: a dropdown for one value or several, wrapping Reka UI's Combobox — which brings the
  keyboard behaviour, the floating positioning and the ARIA wiring.

  The model holds the option's value and `null` when nothing is picked; with `multiple` it holds an
  array that is empty rather than null, so the shape a backend receives never changes with the
  selection. `filterable` adds a search field, and the `search` event carries every keystroke for
  lists that live on the server. Selected values render as removable tags in multiple mode, and the
  list is teleported so it escapes a card's `overflow: hidden`.

  Clicking anywhere on the field opens the list — the underlying combobox does not do that by
  default, which is not what anyone expects from a select. A filterable field shows the label of the
  selection rather than its raw value, and stays empty in multiple mode where the tags already carry
  the selection.

- f0473b8: `WxRichText`: a WYSIWYG editor built on Tiptap — formatting, headings, lists, quotes, tables with
  row and column controls, links, images and YouTube embeds. The model is an HTML string, and an empty
  document is an empty string rather than `<p></p>`.

  The editor never uploads anything itself. `upload(file)` handles pasting, dropping and the toolbar
  button; `pickImage()` is the seam a media library plugs into. Each file is inserted only once its
  upload resolves, so a failure leaves nothing half-inserted. Without either prop the image button is
  not rendered.

  Tiptap is a dependency but stays external to our bundle, so apps that never import the editor do not
  ship it.

- f4188b5: Date, time and date-time pickers: `WxDatePicker`, `WxDateTimePicker` and `WxTimePicker`, wrapping
  `@vuepic/vue-datepicker`.

  The model holds a string in the backend's format by default — `yyyy-MM-dd`, `yyyy-MM-dd HH:mm` or
  `HH:mm` — so a value can travel to a Laravel API and back without conversion; an empty field is
  `null`. `valueFormat` overrides it, and `valueFormat="date"` keeps `Date` objects instead.

  The library's `--dp-*` variables are mapped onto WebX tokens, so the calendar follows the theme and
  dark mode without its own `dark` prop. It stays external to our bundle so apps de-duplicate it.

- 8e45eb9: Adopt the WebX admin visual language in the token layer: real palette with `base` / `hover` /
  `active` / `disabled` / `soft` states per accent colour, pixel-keyed spacing scale, 42px comfortable
  controls with a `wx-density-compact` override, 10px control radius and 16px card radius, and a
  tinted focus border instead of a ring. `WxCard` is now borderless with a soft shadow by default —
  `borderless` is replaced by `bordered`.

### Patch Changes

- 6e451f8: The editor's content area no longer inherits a host application's decoration of
  content tags. A global `h2` rule was giving every heading inside `WxRichText` a top
  border and 24px of padding, and a global `table` rule was turning tables into
  `display: block`, which drops the fixed column layout. Headings, tables and the
  document edges are now stated explicitly, and the first and last blocks in the
  document carry no outer margin.
- 7d21946: Fixes four things in the pickers.

  The time-only menu was sized for a calendar it never shows, leaving about 150px
  of empty space around the columns; it now passes a smaller `modeHeight`.

  The menu clips its content, so its rounded corners stay round. `.dp--overlay` is
  an opaque square panel inset a pixel from the menu edge — it has to be opaque,
  since it covers the calendar when the month or year list opens — and it painted
  over each corner.

  The library's pointer is dropped. `.dp--arrow-top`, unlike its bottom twin, never
  gets a horizontal position, so it landed on the menu's left corner — invisible at
  the library's 4px radius, a white shape beside the corner at ours.

  `WxTimePicker` and `WxDateTimePicker` were handing `WxDatePicker` an explicit
  `false` for every boolean prop the caller had not set, because Vue casts an absent
  boolean prop to `false` and the presets forwarded their whole prop object. That
  silently turned off `is24`, `clearable`, `autoApply` and `teleport` — the last of
  which would clip the menu inside a `WxCard`.

- Updated dependencies [8e45eb9]
  - @webx-ui/tokens@0.1.0

## 0.0.2

### Patch Changes

- 16106cb: Bootstrap the monorepo: design tokens generated from JSON, `WxButton` / `WxInput` / `WxCard`, schema
  contracts, VitePress documentation and the CI / release / docs pipelines.
- Updated dependencies [16106cb]
  - @webx-ui/tokens@0.0.2

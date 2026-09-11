# @webx-ui/core

## 0.7.0

### Minor Changes

- cb1d4e1: The rest of Wave 1: `WxAlert`, `WxDivider`, `WxSpace`, the layout shell, the grid, the menu, the
  breadcrumb trail and `WxScrollbar` — thirteen new components, each with a page of its own in the
  documentation.

  `WxAlert` is the message that stays on the page rather than the one that flies past: four types
  across the usual three weights, an icon that follows the type, a `title` over a body, an `actions`
  slot, and a × that hides the alert itself — unlike a badge, an alert has nobody else to remove it.
  `live` announces one that appears in response to something, and is off by default so a message
  rendered with the page is not read out of nowhere.

  `WxDivider` draws a rule across the flow or a hairline along the line. A plain one is a
  `role="separator"`; one carrying a label is not, because a separator with words inside it tells a
  screen reader two contradictory things at once.

  `WxSpace` is the even gap between things — the answer to the margin that would otherwise be added
  to a button "just this once".

  The shell is `WxContainer` with `WxHeader`, `WxAside`, `WxMain` and `WxFooter`, each rendering the
  element it is named after, so a page has real landmarks. The bars are chrome, and padded like it:
  10px, which is the gutter the sidebar's icons stand in, so a toggle in the header sits exactly
  above the icons below it. Both bars also take an `end` slot — a group pushed to the far side, where
  the user menu and the notifications go, instead of the `margin-inline-start: auto` every admin
  panel would otherwise write for itself. The sidebar collapses to a rail, the main
  column takes a reading width, and either can scroll on its own while the chrome stays put. The same
  five parts make the other shape an admin panel takes: a horizontal menu in the header and no
  sidebar at all, with the whole width left to the content.

  `useResponsiveShell` is the rule those shapes follow — the full sidebar, an icon rail under 1024px,
  a drawer behind a burger under 640px — out of one measurement and two thresholds. It returns
  `layout`, `collapsed`, `showAside`, `drawerOpen`, `toggle` and `close`, so one button in the header
  collapses and expands the sidebar while the sidebar is on the page, and opens the drawer once the
  menu has left it — which is also when a burger is the right icon for it, and not before. The width
  chooses the shape rather than holding it: on a tablet the sidebar starts as a rail and the button
  still expands it in place, since the reader can see what they are expanding.

  The two answers that button can give are not kept the same way. Closing a sidebar is a decision: it
  holds at every width, and with `persist` across reloads, under a key in `localStorage`. Opening one
  only says "not collapsed, here", and is let go as soon as the screen changes size class — otherwise
  a sidebar opened on a desktop would be sitting there on a tablet over a screen with no room for it.
  Like the
  grid, it measures an element rather than the viewport, which is what makes a shell inside a preview
  or a split screen behave like the narrow thing it is; `shellLayoutFor` is the same rule as a pure
  function, for a page that would rather drive the state itself.

  `WxRow` and `WxCol` are the 24-column grid, and its breakpoints measure the row rather than the
  window: `md` means "from 768px of row", so the same grid stacks inside a 400px drawer and spreads
  across a wide screen without being told which it is in. Every width a column is given is published
  as a CSS variable and the stylesheet holds one `@container` rule per breakpoint, each falling back
  to the one below it — four rules instead of the several hundred a class-per-span grid ships, and a
  column that can still be adjusted from the outside. The gutter is padding on the columns pulled
  back by a negative margin on the row, which is what keeps `span="12"` an honest half.

  `WxMenu` with `WxMenuItem`, `WxSubmenu` and `WxMenuGroup` is the navigation: a sidebar or a bar, an
  icon rail, `accordion`, and a branch that expands itself around the active entry. A branch opens
  inline where there is room and as a flyout where there is not — a bar, or a collapsed rail — and
  the branches inside a flyout open inline in the same panel, so three levels deep is still one panel
  rather than a chain of them across the screen. It renders a list of links and buttons rather than
  `role="menu"`, whose keyboard model promises a desktop application menu that admin navigation is
  not.

  `WxBreadcrumb` and `WxBreadcrumbItem` are the trail above a title; a crumb that links nowhere is
  recognised as the page you are on and gets `aria-current`.

  `WxScrollbar` is native scrolling, themed: `scrollbar-color` where it is honoured and
  `::-webkit-scrollbar` where it is not, with the scrolling element and `scrollTo`, `scrollToTop` and
  `scrollToBottom` exposed for a log that follows its own output.

## 0.6.0

### Minor Changes

- f352b2c: Two new components: `WxDialog` and `WxDrawer` — the same panel, one in the middle of the screen and
  one anchored to an edge of it.

  Both are built the same way: a heading with its `extra` slot and a ×, a body, a footer for the
  buttons. The heading and the footer stay where they are and only the body scrolls; a `sidebar` slot
  splits that body into two columns that scroll on their own. What has no appearance — the focus
  trap, the scroll lock, Escape, the portal, `aria-modal` — is Reka's dialog primitive, the same one
  `WxPopover` and `WxDropdown` already sit on; everything that can be seen is ours and is styled
  through tokens alone.

  Size is given in pixels or per cent: `width` and `height` for the dialog, `size` for the drawer,
  where it means the width on the left and right and the height at the top and bottom. `side` picks
  the edge the drawer slides in from.

  A dialog can be `draggable` by its heading and `resizable` from the grip in its bottom-right corner;
  a drawer is resized by the edge it faces the page with, which is a real `separator` — it takes focus
  and answers the arrow keys, so the panel can be resized without a pointer. `persist` gives either
  one a key in `localStorage` and the panel opens at the size and position it was last left at;
  `reset()` puts it back and forgets. Both gestures are pointer-only, since a finger dragging a
  heading is a finger not scrolling.

  A dialog with more in it than fits on the screen picks with `scroll`: `body` keeps the panel inside
  the screen and scrolls what is between the heading and the footer, `panel` lets it grow as tall as
  its content and scrolls the whole of it — the heading goes with it, and `sticky-footer` decides
  whether the buttons rest against the bottom of the screen or sit at the end of the content. A panel
  of that kind has nowhere to be dragged to and nothing to be stretched into, so `draggable` and
  `resizable` are ignored while it is in use.

  Under 640px the dialog takes the width of the screen and the drawer covers it whichever edge it came
  from, the paddings of the heading, body and footer tighten so more of the content fits, a remembered
  size and position are ignored rather than opening the panel half off the screen, and a sidebar
  stacks above the body instead of standing beside it. The paddings are custom properties —
  `--wx-dialog-pad-x`, `--wx-dialog-pad-y`, `--wx-dialog-body-pad` and their `--wx-drawer-` twins — so
  a panel can set its own.

## 0.5.0

### Minor Changes

- 2b0fe26: New component: `WxKanban` — a board of columns cards are dragged between.

  Columns carry their own cards, a card needs nothing but an `id`, and what a card looks like is the
  application's business: the `card` slot hands back the card, its column and its index. A move
  writes itself into the arrays the board was given — that is what makes a card land where it was
  dropped — and then reports `{ card, from, to, via }`, where the indices are what a Laravel update
  of `status` and `position` wants.

  A column's `limit` is enforced rather than decorated: at the limit the count turns red and the
  column refuses further cards, while reordering inside it still works, since that does not make it
  any fuller.

  A column is more than a heading over a list. The `column-actions` slot is the end of that heading —
  a plus, a menu — and sits outside everything that drags, so pressing it never starts a move.
  `collapsible` folds a column down to a strip with its name read the long way, remembered through
  `v-model:collapsed`; folded, it holds nothing reachable, so it takes no cards and a keyboard move
  passes it by. `column-addable` puts a column-shaped button after the last column, and
  `reorder-columns` lets the columns themselves be dragged by their headings — by the heading only,
  so a card is still picked up by the card.

  The dragging is SortableJS, through `vue-draggable-plus`, and it has nothing to say to a keyboard —
  so the board carries its own. Space picks a card up, the arrows move it between positions and
  columns (stepping over any column that is full or frozen), space drops it and escape puts it back;
  every move is read out through a live region and the focus follows the card. On a phone the columns
  scroll sideways and the swipe snaps to one at a time, and a drag starts after a short press, which
  is what tells it apart from a scroll.

## 0.4.0

### Minor Changes

- 66818dd: New component: `WxPopover` — a panel hung off a control, for a small form, a confirmation, or an
  explanation too long for a tooltip.

  It is the counterpart to `WxDropdown` rather than a second copy of it. Both stand on Reka's
  popover, and the difference is what a click inside means: in a menu the click is the whole
  interaction, so the panel closes; in a popover it is part of the work being done, so the panel
  stays until the ×, a footer button, Escape, or a click outside. The panel has a heading, a footer
  for the buttons and an arrow pointing at its trigger, takes focus when it opens and hands it back
  when it closes, and is rendered in a portal so it is not clipped by a scrolling strip or a table.

- 66818dd: New components: `WxTabs` / `WxTab` and `WxAccordion` / `WxAccordionItem`.

  A tab and its panel are written in one place — `<wx-tab value="seo" label="SEO">` carries
  both the button in the strip and the content behind it — and the tabs open the first
  usable one by themselves, including when the tabs arrive from a request. Three variants:
  an underlined strip, a segmented control, folder tabs; horizontal or a column beside the
  panel; hidden panels are dropped from the DOM unless `keep-alive` says otherwise.

  The strip is built for a phone. It scrolls sideways instead of wrapping into a second row
  that would push the panel down, the open tab is scrolled into view whenever it changes,
  the end with more behind it fades, arrows show up for a mouse and stay out of the tab
  order, and a column of tabs lies back down into a strip when the container gets narrow —
  measured on the tabs themselves, so a narrow drawer on a wide monitor is treated the same
  as a phone.

  The accordion folds a long page into headed sections: one open at a time or several,
  headers that are real headings at the level you choose, a chevron on either side, and an
  `extra` slot for a switch or a menu in the header. The heading holds the button and
  nothing else — `extra` sits beside it — so skimming a page by its headings reads out the
  section titles and not the controls next to them, and pressing one of those controls does
  not open the section under the pointer.

### Patch Changes

- 059272d: Floating panels share one layer, so a list opened inside a panel is not swallowed by it.

  `WxDropdown`, `WxSelect`, `WxAutocomplete`, `WxCascader` and `WxTagsInput` drew their panels on
  `--wx-z-index-dropdown`, below `--wx-z-index-popover` — so a select inside a popover had its list
  disappear behind the panel it was opened from. Ranking panels by kind cannot work: what has to be
  on top is whatever was opened last, whichever kind it happens to be. They all draw on
  `--wx-z-index-popover` now, and since a panel is added to the document when it opens, the order of
  opening decides. `--wx-z-index-dialog` and above are unchanged: those are for surfaces that take
  over the page.

## 0.3.1

### Patch Changes

- d3e0743: `WxTable` and the four pickers ship real types again.

  Each of them builds slot names out of data — the table out of its column keys, the
  pickers out of whatever slots they are handed — and a template that enumerates its own
  slots makes the slot type depend on itself. TypeScript answers that circle by giving
  up: the declarations carried `slots: any`, and in the table's case the props collapsed
  to `any` as well, so nothing about `<wx-table>` was checked at all.

  The slots are now declared instead of inferred. The table's are spelled out —
  `cell-<key>` hands back the row, the value, the index and the column; `header-<key>`
  the column; `summary-<key>` the summary line — and the pickers declare that they
  forward whatever they are given. The type-check diagnostics that `vite-plugin-dts`
  printed on every build are gone with them.

## 0.3.0

### Minor Changes

- 2f72134: `WxAction`, `WxActions`, `WxDropdown` and `WxDropdownItem` — the row of icon buttons at the end of a
  record, and the panel it folds into.

  An action is described by what it does: `type="remove"` is a red trash can called "Delete", and
  `icon`, `tone` and `label` each override one of those when a screen needs something else. `hidden`
  draws nothing but keeps the square, so a list where one record may not be deleted still lines up
  with the rows where it may — the distinction `disabled` cannot make, since a greyed button says
  "not now" rather than "not for this record".

  `WxActions` lays the row out and, with `collapse`, measures it against its container and folds it
  into a dropdown as soon as it stops fitting. The row is never unmounted, only taken out of the
  flow: keeping its natural width is the only way to know when there is room for it again.

  `WxDropdown` is the general case — a `trigger` slot and a content slot, nothing assumed about
  either. It renders the trigger as the element you pass rather than wrapping it in a button of its
  own, because the trigger is nearly always a `WxButton` or a `WxAction` and a button inside a button
  is invalid HTML. A click inside closes the panel by default; a panel of filters turns that off with
  `:close-on-click="false"` and dismisses itself through the `close` handed to the content slot.
  `WxDropdownItem` is one row of a menu — icon, label, trailing note, and a `danger` tone for the
  destructive one at the bottom.

- b4dda58: The small pieces an admin screen is assembled from: icons, badges, typography, and four components
  that had been standing in as markup.

  `WxIcon` draws one icon from a built-in set of 24×24 stroke drawings that take the colour and the
  size of the text around them; `registerIcons` adds your own, so a name is all the JSON schema
  renderer will ever need. `WxBadge` is the label that says what something is, and `WxIndicator` the
  count or dot pinned to a button, an icon or a link — Element Plus splits the same job between
  `el-tag` and `el-badge`.

  `WxButtonGroup` joins buttons into one segmented control and hands its look down to them, which is
  why `WxButton` now resolves `type`, `variant` and `size` from the group when its own are unset — a
  button that sets one still wins.

  Typography: `WxHeading` separates the level in the outline from the size on screen, `WxText` covers
  the body, the hints and the truncation, `WxLink` opens an external target safely and renders through
  `RouterLink` when asked, and `WxProse` gives the editor's HTML the typography of the design system —
  headings, lists, quotes, code, images, and a pasted table that scrolls inside its own box.

  `WxAutocomplete` suggests without constraining: the model is the text, `search` is debounced and
  held back by `min-length`, and picking a suggestion does not ask the backend for what it has just
  been given. `WxCascader` picks out of a tree one column per level, either handed over whole or
  fetched level by level through `load`, and walks with the arrow keys.

  `WxEntityCard` is one record as a row — picture, name, the fields under it, and an actions slot
  whose clicks stay out of the row's own. `WxTimeline` and `WxTimelineItem` show what happened and
  when. `WxStatistic` groups a number through `Intl` rather than a hard-coded separator, and
  `WxCountdown` counts down to a moment with a format where only the tokens present consume time, so
  `mm:ss` on two hours prints `120:00` instead of quietly dropping the hours.

### Patch Changes

- 1a76119: `WxRadio` draws its mark as one SVG, so the dot stays in the centre of the ring.

  The dot used to be a CSS box centred inside another CSS box, with the ring drawn as a 1px border
  between them. At a fractional device pixel ratio — Windows at 125% or 150%, which is most HiDPI
  screens — that border is 1.25 or 1.5 physical pixels and gets rounded on each side independently.
  The content box then sits off the centre of the border box, and the dot rides along with it.

  Ring and dot are now two circles sharing one origin in one coordinate system. Nothing is laid out
  between them, so nothing can round them apart: the renderer resolves both against real geometry and
  antialiases them. `non-scaling-stroke` keeps the ring one pixel wide at every size, the way the
  checkbox border is, rather than thinning to 0.8px on `sm` and thickening to 1.2px on `lg`.

- fc7ea62: `WxCountdown` no longer starts its timer during server-side rendering. The interval
  had nothing to clear it there — `onBeforeUnmount` never runs on the server — so it
  held the render process open: a static build of a page carrying a countdown finished
  rendering and then hung. The clock now starts in `onMounted`, the one hook a server
  render never reaches, while the displayed value is still computed in both places.
- 571f915: `WxPagination` wraps on a narrow screen instead of running off the edge of it.

  Neither the controls nor the list of page buttons wrapped, and buttons do not shrink, so on a phone
  the row overflowed its container well before the page count got interesting — taking the last pages
  with it, and often the next arrow too. Both now wrap and stay aligned to the trailing edge, and the
  "Per page" label no longer breaks across two lines. Nothing changes on a wide screen, where there
  is no wrapping to align.

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

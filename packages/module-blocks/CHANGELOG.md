# @webx-ui/module-blocks

## 0.8.2

### Patch Changes

- 35d229c: Block thumbnails are drawn in the site's own clothes: the panel loads the stage page once, keeps its stylesheets, fonts and the wrappers around the block's place, and drops the header, the footer and every script. The manifest names the stage (`meta.stage`) once `webx-blocks.layout` is set; without it the thumbnails stay bare.

## 0.8.1

### Patch Changes

- 6c45f08: The block editor's stage and the page preview let a click reach the site's own handlers again, keeping only navigation and submits inert: a newsletter popup or a cookie bar the layout opens over the preview can now be closed.
- Updated dependencies [b965650]
- Updated dependencies [7bdeb63]
  - @webx-ui/schema@0.5.0
  - @webx-ui/module-admin@0.14.2

## 0.8.0

### Minor Changes

- 76b4a71: The block editor suggests as you type: fields after `{{` and `$` in the template, the keys of a picked file and of a repeater item, classes from the styles in `class="…"`, Blade directives after `@`; classes from the template after `.` in the styles; node keys and registered types in the fields. `WxCodeEditor` styles an autocompletion list passed through `extensions`, and its tooltips stand on `--wx-bg-surface` instead of the dialog backdrop colour.

### Patch Changes

- Updated dependencies [76b4a71]
  - @webx-ui/core@0.32.1

## 0.7.1

### Patch Changes

- d57c68b: The block editor's stage is as tall as the page of the site in it, and the panel scrolls — the
  way the page preview does. The stage used to be a window capped at 62% of the screen with a
  scrollbar of its own, and on a site with a real footer the bottom of the page was cut off behind
  it.

## 0.7.0

### Minor Changes

- fde8622: The block editor draws a block on a page of the site, not on the browser's defaults.

  `webx-ui/module-blocks` gets `webx-blocks.layout` — the component the editor's stage stands in, the
  same `<x-layout>` the pages use — and `/_preview/block-stage`, which prints that layout with an
  empty place for the block. `webx:panel --sync` sets the key and `webx:doctor` warns while it is
  empty, as they do for pages and the blog; empty prints `webx-blocks::standalone`, a bare document.
  `render` names the stage in its answer.

  `@webx-ui/module-blocks`: `BlockStage` loads the stage once and swaps the block and its styles in
  on every change, so the header and footer do not redraw under typing; a new script reloads the
  page. The site's links are inert there, and the stage scrolls to the block. A server without the
  stage still gets the bare document. `frame.ts` gains `freezeFrame`, `fillStage` and `mountScript`.

  `@webx-ui/module-menu`: a long address under a menu item ends in `…` instead of running out of
  the card.

### Patch Changes

- Updated dependencies [a0556f1]
  - @webx-ui/core@0.32.0
  - @webx-ui/schema@0.4.0
  - @webx-ui/module-admin@0.14.1

## 0.6.5

### Patch Changes

- Updated dependencies [8e0d587]
- Updated dependencies [8e0d587]
  - @webx-ui/module-admin@0.14.0
  - @webx-ui/core@0.31.0
  - @webx-ui/schema@0.3.8

## 0.6.4

### Patch Changes

- Updated dependencies [f623fac]
- Updated dependencies [cd95a2e]
- Updated dependencies [b1aeb52]
  - @webx-ui/module-admin@0.13.0
  - @webx-ui/core@0.30.0
  - @webx-ui/schema@0.3.7

## 0.6.3

### Patch Changes

- 0a506df: Each tab of the block type editor gets what it needs beside it, and the others get the screen

  The screen was two columns: six tabs in the left one, and in the right one the block, the form
  of its sample and a card saying where the type stands. So the picture of the block was also
  standing beside the settings and beside the history, at half their width, and the sample's form
  was open on all six tabs — including the four where nobody is looking at values.

  What stands beside the tabs is what the open one needs. **Template** and **Styles** — both draw
  the block — have the block, sticky, so the picture stays while the file scrolls under it.
  **Fields** has the form its schema builds. **Script** has three examples under the editor instead:
  a handler, a value out of `values`, and a library through `webx.use()`. The rest have the width
  of the screen. The icon of a type is picked from the set now (`WxIconPicker`) rather than typed
  into a box that accepts anything and draws nothing. **Where the type stands** is a popover behind the
  words "on 3 pages" in the subtitle, styled as the link it is: five page names took a quarter of
  a column to say what the subtitle already says in three words.

  Three more things the same look found.

  **The action bar no longer repeats the state.** It carried the same three badges as the head —
  draft, live, unsaved — and on a 1440×900 window both pairs are on screen at once, on a phone
  all the more so. What state a type is in is a fact about the type, not about the last
  keystroke, and the panel says the state of a record beside its name. The two buttons stay:
  those did scroll away with the head.

  **The stage opens at the width its column can draw.** It always opened on a desktop 1280, so
  in the narrow column of a phone it drew the block at a third of size — a picture of a page
  whose words are two pixels tall. It now starts at the widest device that fits at half size or
  better, and from the first click on the switch the width is the editor's.

  **The frame is the height of the block again.** It measured `documentElement.scrollHeight`,
  which is never shorter than the frame's own window: once the frame had been given a height, it
  was measuring itself, so a block that got shorter kept the height of the one before it with
  white space under it. Measured on the body, as everything else that watches a frame here does — and measured again
  when the tab holding it comes back, since a document nobody is showing has no height at all.

  Plus one line that read wrong: `on :count pages` said "on 1 pages" exactly when a type had
  just been put on its first page. There is a line for one now — `page.on-page`, and
  `page.delete-used-one` beside it — in all ten languages.

- Updated dependencies [0a506df]
  - @webx-ui/core@0.29.0
  - @webx-ui/module-admin@0.12.2
  - @webx-ui/schema@0.3.6

## 0.6.2

### Patch Changes

- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
  - @webx-ui/core@0.28.0
  - @webx-ui/module-admin@0.12.1
  - @webx-ui/schema@0.3.5

## 0.6.1

### Patch Changes

- e98734c: The block constructor's own preview switches width by picture too

  `BlockStage` — the preview beside the template in the Blocks section — still spelled out
  "Desktop · Tablet · Phone" while the page preview had already moved to the three device icons.
  Two previews in one panel, one of each kind, is the sort of difference an editor reads as
  meaning something. The words stay as the accessible name, and the group is named "Width".

## 0.6.0

### Minor Changes

- 537df98: The constructor gives the page back its room

  Two columns now, in both states, and the tree and the open block's form take turns in the narrow
  one. It used to be three — tree, form, and a 420 px preview — and a desktop page does not go in
  420 px, so the preview was squeezed into a phone at one to one and every block was edited against
  a picture of a phone. Measured on a 1474 px window: 260 + 626 + 420 became 455 + 867, which is a
  1280 px page at 0.66 rather than a 390 px one.

  The preview itself is now as tall as the page inside it, and the panel scrolls. There is no window
  of ours over a page that has its own — that was two scrollbars for one document, and the inner one
  could not be reached with the wheel, so a block taller than the window could not be seen whole at
  all. The height comes from a `ResizeObserver` inside the frame, measured off the body.

  Its bar carries the width switcher at all times — desktop, tablet, phone, as icons — and the two
  buttons beside it are icons as well. The bar sticks to the top of the window while the page goes by
  under it. Selecting a block brings it into view by as little as it takes, never centring it, and
  leaves it alone when it is already there; the panel is what moves, and the page inside the frame
  never does.

  On a panel too narrow for two columns there is one, and it is the preview: the tree and the form
  move into a sheet over the page, opened by a pencil on the preview's bar or by clicking a block in
  the page itself — which opens it straight onto that block's form. The preview starts as a phone
  there, or as a tablet where there is room for one. That replaces the old arrangement, where the
  preview was the thing that folded away on a narrow screen and the editor was left with a list of
  names.

  What the tree carried while it is off the screen lives in the form's head: steps to the previous and
  the next block through the flattened page, the trail of containers the open block sits in, and the
  button that opens the preview full screen — that one matters below the width where the preview folds
  away, which is exactly where the tree's own foot is not on screen.

  Breaking, in the small way a `0.x` field can be: the `fill` prop is gone from `WxBlocks` and from
  the preview. It meant "be a window tall and scroll each panel inside itself", which is the layout
  this release replaces. A screen that passed it can simply stop. The tile with the block type's icon
  is gone from the tree rows too — most types have no icon of their own, so it drew the same square on
  every row and spent the width of a name on saying nothing.

### Patch Changes

- Updated dependencies [537df98]
- Updated dependencies [537df98]
  - @webx-ui/module-admin@0.12.0
  - @webx-ui/core@0.27.0
  - @webx-ui/schema@0.3.4

## 0.5.3

### Patch Changes

- 48dfd9e: One head for every screen of the panel

  Eight screens each answered "what goes at the top" on their own, and gave eight answers: the
  heading at three sizes, the way out as an arrow on four of them and as a line of breadcrumbs on the
  rest, the buttons folding into a `···` on two editors and wrapping onto a third line everywhere
  else. Writing a new screen meant writing that line again and getting it slightly different again.

  `WxScreenHead` is that line, once: the way out, the name with the state said beside it, the line
  under it that says which record this is, and what can be done here. `WxListScreen` is built on it,
  so a list and the editor a row opens are the same object rather than two similar ones — and it
  takes `back` now, which is what the statuses screen used to draw above its own heading for want of
  anywhere to put it.

  **The actions are declared rather than drawn.** The same action has to be a button on a desktop and
  a line of a menu on a phone, and one vnode cannot be mounted in two places — as markup it had to be
  written twice, which is exactly what the page and article editors did. As `ScreenAction[]` it is
  written once: `primary` is the one thing the screen exists for and the one that keeps a button when
  the head runs out of room, `danger` is never a button at all, `menu` is in the `···` at every
  width, and `loading`, `disabled` and `href` mean what they say. Below 720px — 480 on a list, which
  carries one word and no trail — everything but the primary folds behind the `···` and that primary
  takes the line under the name, full width.

  The name’s line is the head: the way out at the start of it and the actions at the end, both
  centred on it however many badges stand beside the name. The trail is the line above, and it
  scrolls sideways with no scrollbar showing rather than wrapping — on a phone a path four levels
  deep was two lines of the smallest type on the screen, standing between the reader and the name of
  what they had opened.

  Two things that were quietly wrong come out with it. Nineteen buttons across the panel passed
  `icon="plus"` to `WxButton`, which has no such prop: the attribute landed on the `<button>` and
  drew nothing, so the panel’s main actions had no icons at all. And the `···` said `More` in English
  in every language, because the core carries English defaults and knows no dictionary — the panel
  gives it the word now, in all ten.

- Updated dependencies [a9383bb]
- Updated dependencies [b6a09a6]
- Updated dependencies [48dfd9e]
  - @webx-ui/core@0.26.0
  - @webx-ui/module-admin@0.11.0
  - @webx-ui/schema@0.3.3

## 0.5.2

### Patch Changes

- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [937f4e2]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
  - @webx-ui/core@0.25.0
  - @webx-ui/module-admin@0.10.0
  - @webx-ui/schema@0.3.2

## 0.5.1

### Patch Changes

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0
  - @webx-ui/module-admin@0.9.0
  - @webx-ui/schema@0.3.1

## 0.5.0

### Minor Changes

- 74d1369: A round of panel fixes, mostly from looking at the two demo sites on a phone.

  - A field stops at a width it can be read at: `WxFormItem` caps its control at
    `--wx-field-max-width` (640px), and what is not a field in that sense says `wide` — a prop on
    the item, `wide: true` on a registry entry.
  - A hovered table row and the `···` at its end no longer paint themselves the same grey: the row
    goes a tone softer, and the row menu carries no fill at rest.
  - Tooltips never open on a touch screen, where the tap that opens one is the tap that was meant
    for the button under it. `useHoverPointer()` is the question, asked once for the application.
  - The panel's step reaches what the panel teleports out of itself — drawers, dialogs, the toaster
    — so a phone no longer lays one screen out with desktop air.
  - Blocks: a row of the tree offers its actions as the panel's `···` rather than three icons that
    only appeared on hover, and removing a block always asks first.
  - Inbox: the form editor no longer draws its save bar across the middle of the form, the section's
    panes stay inside the card's corners, the recipient's bin is red and asks, and a submission
    keeps its notes, its log and its metadata in one card with three tabs instead of three cards.

### Patch Changes

- Updated dependencies [74d1369]
  - @webx-ui/core@0.23.0
  - @webx-ui/schema@0.3.0
  - @webx-ui/module-admin@0.8.0

## 0.4.1

### Patch Changes

- Updated dependencies [4644d28]
- Updated dependencies [4644d28]
  - @webx-ui/module-admin@0.7.0

## 0.4.0

### Minor Changes

- ad9ead7: A pass over the panel: the chrome, the editors and the constructor.

  **The chrome.** The button that collapses the sidebar stands at the far end of
  the brand row instead of against the logo. The foot of an open sidebar says who
  is signed in rather than only showing them. The menu drawer keeps its width on a
  phone instead of covering the page — `WxDrawer` has `full-screen` for that — and
  icon buttons there are one size down.

  **Settings the panel wears.** A picture chosen in the library no longer vanishes
  from its field when the form is saved, and the logo in the corner changes with
  it: `AdminContext` gained `refreshManifest()`, which fetches a new manifest
  without the panel passing through `loading`.

  **`WxActionBar`.** The strip of body colour the bar painted in the gap below it
  is gone: it erased the part of the bar's own shadow that fell there, and a
  descendant is always on top of its ancestor's shadow. What shows through the gap
  instead is a sliver of the page still moving, which is what a bar floating over
  a scrolling page looks like.

  **Editors.** `WxBackButton` and `WxRenameButton` in `@webx-ui/module-admin`: a
  way out of a screen that opens one record, and renaming as an act rather than as
  typing into what looks like a heading. Both editors use them.

  **Tabs that hold a form.** Only the tab holding the constructor is a box of a fixed height
  with its own scrollbar; the others grow with their content and the page scrolls. A scroll box
  clips, and the cards inside one had their shadows cut off square at all four edges.

  **The constructor.** The preview is a picture of the page rather than the page:
  nothing in it navigates or submits, a click opens the block it landed in, the
  block under the pointer is outlined, and choosing one in the tree scrolls the
  frame to it.

  **Help.** `WxHelpButton` shows a page of Markdown from a module's own `lang`
  files — and `module-blocks` hands the identical page to an agent at
  `blocks://schema`.

  The page explains fields in more than one language, and writing it turned up that they
  did not work: a block with a `localized` field handed its template the whole language
  map, Blade refused to print an array, and the renderer caught that and printed nothing —
  the block vanished from the page. It is given one language now, down the same chain every
  localized value is read through.

  **The library.** A file can be downloaded from its card: `WxFileCard` takes
  `download-url`.

  **Smaller things.** A dialog puts air between whatever its body was given, so three stacked
  fields are not one block of controls. `WxSkeleton` is `border-box`, so a loader given padding
  no longer stands wider than the card it is in. And there is a guide to
  [languages](https://webx-ui.github.io/webx-ui/guide/languages).

  **Yourself.** `PUT auth/me` and the profile dialog behind the corner menu: your
  name, your photograph, your password — the last of those only with the current
  one.

### Patch Changes

- 99853f4: Block editor styles name tokens that exist: the muted and strong borders and the warning and
  danger colours were spelled `--wx-color-border-muted`, `--wx-color-border-strong`,
  `--wx-color-warning-text` and `--wx-color-danger-text`, and only the fallback kept them drawn.
- Updated dependencies [ad9ead7]
  - @webx-ui/module-admin@0.6.0
  - @webx-ui/core@0.22.0
  - @webx-ui/schema@0.2.3

## 0.3.0

### Minor Changes

- 14d79c1: A block can be switched off

  A block on a page gets a switch: it stays in the content, it is edited in the panel exactly as
  before, and the site does not draw it. Not a page draft, not a delete, and not the block type's
  `is_enabled` — a switch on one block.

  The eye stands first in the row of the tree, before duplicate and remove. A switched-off block
  is dimmed and carries an `eye-off` beside its name, always and not only under the cursor: the
  actions are invisible at rest, and a row that is merely dimmer than its neighbours says nothing
  on its own. It is absent from the preview too — a preview that still showed it would not tell
  you which block is the one that is off.

  In the content it is a fourth key on the node, `hidden: true`, written only when it is on: every
  tree from before the switch existed reads as visible, with no migration. The skip is one line in
  `Renderer::list()`, which is why a switched-off container takes everything inside it with it
  while their own switches keep their values — the recursion never reaches them — and why the
  type's styles and script stay off the page.

  `blocks_edit_content` gets `hide` and `show` by key, and `outline` reports `hidden: true`: an
  agent has no other way of telling that a block it can read is not on the site.

- 738a7e9: The screen's own bar along the bottom, and a preview that fills the height it was given.

  A screen's buttons live in its head, and since the page started scrolling natively the head goes
  with it — so a form long enough to need saving is a form whose save button is off the top of the
  window by the time it is needed. `WxActionBar` is that button brought back: the state of the work
  on the left, what can be done about it on the right.

  It is part of the screen rather than of the shell — a list has none, a form has one — and it is
  the last row of the screen rather than a layer over it. That is why it never covers anything: at
  the end of a page it is the last thing on it, and above that it sticks to the bottom of the window
  without taking the room it would need to be there. `--wx-action-bar-bottom` is how far off the
  edge it stops, and the strip below it is painted over, or the page scrolling past would show
  through the gap.

  `WxMain` gives a screen that holds one a floor of `--wx-fill-height`, so a form of one field still
  has its bar along the bottom of the window rather than halfway up the page.

  In the panel: the settings screen and the block type editor duplicate the buttons from their
  heads, and the page editor — which is exactly as tall as the window, so nothing ever scrolls away
  — moves `Save` and `Publish` into the bar instead of repeating them, leaving the head the links
  out to the site.

  The page preview inside the block constructor now fills the card it stands in. A scaled iframe
  keeps its layout height, so a page 1280px wide drawn in a 420px column used to paint a third of
  the card and leave the rest empty for the card to scroll; the frame is now divided by its own
  scale, and what scrolls inside it is the site. On a form that scrolls, the tree and the field
  panel are sticky with their own scrollbars, the way the preview beside them already was.

### Patch Changes

- 2e27380: Lists that mean what they show: a tree stays a tree, a row promises only what it does, and
  anything that cannot be undone asks first.

  **The page tree is a table at every width.** Below 640px it used to become cards, and a card has
  no indentation to read and no chevron to open — so the section quietly asked the server for a flat
  list instead, and a phone had no tree at all. The cards were the mistake, not the tree. The table
  now drops columns as the width goes: when it was last touched, then what state it is in, then
  where it lives, until a row is its title and its `···`. Measured at 375px: 65px a row against
  250px a card, ten pages on screen instead of three and a half, with the chevron still opening
  branches.

  **`WxTable` takes a `clickable` prop.** It still infers the answer from whether anybody listens
  for `row-click`, which is right for an ordinary list and needs nothing said. It is not right for a
  list whose rows stop leading anywhere while it is on screen — the bin of `Pages`, an archive, a
  picker taking several rows at once — because the listener a component was rendered with cannot be
  read again. `:clickable="false"` withdraws the whole promise: no pointer, no highlight, and no
  `row-click` either. The bin, the two SEO lists for a reader who may not edit them, and the
  administrator picker in multiple mode all say so now.

  **One place decides what a failed request says.** `useErrorText()` turns an error into a sentence
  in the panel's language. A 422 is repeated word for word — every refusal that reaches one is
  written by a module to be read — and every other status gets the panel's own words, so clicking a
  page somebody else deleted says "It is not there any more" rather than
  `No query results for model [WebxUi\Pages\Models\Page] 8`. Twenty-odd places that printed the
  server's `message` now go through it, and `webx-admin::errors` ships the lines in ten languages.

  **Confirmations, in numbers.** Restoring from the bin, publishing a page, moving a branch by drag
  or by the "Inside" picker, deleting a block that holds others and deleting an empty media folder
  all ask now, and the question carries the consequence as a figure: how many pages come back, which
  address the page starts answering at, how many addresses a move rewrites, how many blocks go with
  the one being removed. A move of a single page stays a gesture and asks nothing, because a redirect
  is left on every address a move vacates — there is no undo to offer, only a second move.

- 738a7e9: One shape for every list in the panel

  Five sections each answered "where does the heading go" on their own, and there were five
  answers: a heading inside the card on `Administrators`, two headings on `SEO`, a search outside
  the card on `Blocks`, no heading at all on `Files`, a bare red bin in every row here and a menu
  there. They are one shape now — the section's name on its own line, the one action it exists
  for beside it, the views of the list as tabs under that, and a card holding nothing but the
  rows. The search stays inside the table: it narrows the rows, not the screen.

  `WxListScreen` in `module-admin` is that frame, and it is a screen node type — `wx-list` — so
  the next section describes its list rather than writing a sixth copy of the same markup.

  `WxTabs` grew an `items` mode for it: the strip is built from a list and the default slot is the
  **one** panel under it. A view is a different question to the server, not a different panel, so
  nothing is unmounted on a switch and the table keeps its search, its page and its scroll.
  `collapseBelow` folds the strip into a single switch labelled with the open view when its own
  container gets narrow — not into three dots, which in this panel mean actions.

  `WxRowMenu` is the other half: a `···` at the end of every row in every list, even for a single
  action. It orders the destructive one last, behind a rule, in red, and what somebody has no
  right to is left out rather than greyed. Underneath it is `WxActions` with the new
  `collapse="always"`, which never builds the row of icons at all — so the width of a table cell
  stops deciding whether a row has a menu. `WxFileCard` takes the same choice as `actionsMenu`,
  which is how a single file in the library gets one.

  Uploading is the media library's main action now: a filled blue button with a word on it in the
  line of the heading, rather than the third grey icon in a row of six. Blue, because green in
  this system means "it worked". Inside the picker dialog the toolbar keeps its upload icon —
  there is no screen around it there.

- 738a7e9: One step for the whole panel: cards, grids and forms read `--wx-gap`

  The panel's spacing step — 8 on a phone, 12 on a tablet, 16 on a desktop — used to space the
  frame alone. It now spaces everything: the air inside a card and between the things in it, the
  gap between the fields of a form, the gutter of a grid, the space between the strip of tabs and
  what it switches. Where there is no panel around them, the components fall back to 16, which is
  what they had.

  Two things change on their own account. A form's `gap="md"` is 16 rather than 24, so a form laid
  out by a card and a form laid out by itself finally agree. And a tab is now a column that spaces
  what it holds — two cards in a tab used to stand flush and read as one.

- 30a3d30: One way for the panel to say when something happened

  `module-admin` gains `useDates()` and `WxDate`, and every section that showed a date now calls
  them: "today at 08:10", "yesterday at 14:03", "16 September at 14:03", "16 September 2025",
  "never". Twenty-four hours and no seconds in the column; the exact moment stays in a tip and in
  `<time datetime>`.

  There were three formats on three screens before this, all of them `toLocaleString()` with no
  locale — so a panel drawn in Russian dated its rows in American order, with `AM/PM` and seconds
  nobody wanted. The month names and the order of the parts now come from `Intl` in the panel's
  own language; only `today`, `yesterday` and `never` are translated by hand, and the word for
  "never" moved out of the two modules that each had their own copy.

  "Today" is counted in the reader's calendar day rather than in UTC, which is what the server
  sends: the small hours of a morning are today to the person reading them. Sorting is untouched —
  a column still sorts on the value the server sent, never on the words.

- Updated dependencies [738a7e9]
- Updated dependencies [2e27380]
- Updated dependencies [738a7e9]
- Updated dependencies [738a7e9]
- Updated dependencies [30a3d30]
- Updated dependencies [738a7e9]
- Updated dependencies [e93ae5b]
- Updated dependencies [a16ff45]
- Updated dependencies [046c6ba]
  - @webx-ui/core@0.21.0
  - @webx-ui/module-admin@0.5.0
  - @webx-ui/schema@0.2.2

## 0.2.0

### Minor Changes

- 2c2c2ba: The page editor: `pages.form` as a described screen, and the screen that saves it.

  `webx-ui/module-pages` registers `pages.form` — four tabs, Content · Settings · SEO · History —
  so the SEO card can arrive as a patch rather than as a fork of the editor. `GET /api/cms/pages/{id}`
  now answers with the values of that screen, the trail above the page, a signed preview link and a
  `revision`; `PUT` takes the values back through `ScreenValues`, so what the tree does not name is
  dropped and a 422 lands under the field it is about. The `revision` is a short hash of the
  content, the way `module-blocks` computes one: a save that names a revision that is no longer the
  current one is refused with a 409 carrying the page as it now is and who wrote it, and two people
  who saved the same thing are not a conflict. `GET .../versions` lists the publications with their
  author and source, and `POST .../versions/{n}/restore` makes an old one the draft — publishing it
  stays the separate step it always was.

  `@webx-ui/module-pages` draws it. The head stays put: the trail through the tree, the page's state,
  the preview link and the two buttons, folded into a menu on a narrow panel. Saving is by autosave —
  a pause after the last keystroke and the moment a field is left — with an explicit button beside a
  chip that says saved · saving · not saved yet, and a guard that flushes the pause on the way out
  and only asks when the save did not go through. The parts of the screen that are not fields are
  node types of their own: `wx-page-place` prints the whole address the page answers at and moves it
  in the tree, `wx-page-danger` takes it off the site or into the bin, `wx-page-history` is the
  history.

  `@webx-ui/module-blocks`: `wx-blocks` takes a `fill` prop — be as tall as what it is drawn in and
  let the tree, the form and the preview scroll each in itself. Without it the constructor sizes
  itself to its content, and a page made of twenty blocks scrolls the editor's head off the top of
  the screen along with it.

  `@webx-ui/core`: a screen that fills its column says so with `data-wx-fill`, and `WxMain` stops
  growing with it. A percentage height inside a scrolling column resolves against nothing while the
  column is as tall as its own content, and a screen cannot reach its own ancestors any other way.

### Patch Changes

- Updated dependencies [2c2c2ba]
  - @webx-ui/core@0.20.0
  - @webx-ui/module-admin@0.4.2
  - @webx-ui/schema@0.2.1

## 0.1.1

### Patch Changes

- f7bdc63: The section's settings and history stand on a card, the search stands over the grid it narrows
  and the new-block button beside the heading, and both pages load into placeholders shaped like
  what is coming — cards on the list, the head and two cards in the editor — rather than into
  lines on the grey. The picker's search is there for four types as well as for forty.
- Updated dependencies [f7bdc63]
  - @webx-ui/core@0.19.1

## 0.1.0

### Minor Changes

- 7cecf88: The block constructor: the panel

  `@webx-ui/module-blocks` is new: the section where a block type is made — a list of cards with
  live thumbnails, and an editor with the template, styles, script, fields and settings on one side
  and the block drawn on its sample, the sample's form built from the schema being edited and where
  the type stands on the other. Checks run live under the editor; publishing is a separate step,
  refused with the line when the template fails on the sample or on a page. `wx-blocks` is the field
  that builds an entity out of blocks: a tree with drag to reorder, a picker of types with pictures
  that offers only what may go here, the selected block's fields as a form, and the entity's own
  preview beside them — the whole page while looking at it, a phone at one to one while editing a
  block, swapped in place after a field changes. `provideBlocksPreview()` is how the hosting screen
  hands the preview address in.

  In `webx-ui/module-blocks`, the panel half: the module (`blocks.view`, `blocks.manage`, the groups
  and `webx.provide()` names in the manifest), the API under `/blocks` — types, catalogue, save
  (a version per save, none for an unchanged one), publish with the check on every page's values,
  render on sent values or on an unsaved template, usage, history and restore — the lints the
  server sends back with a saved version, `webx-blocks.editing` as a read-only switch, and the
  dictionary in ten languages.

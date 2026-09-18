# @webx-ui/module-pages

## 0.3.2

### Patch Changes

- Updated dependencies [4644d28]
- Updated dependencies [4644d28]
  - @webx-ui/module-admin@0.7.0
  - @webx-ui/module-blocks@0.4.1

## 0.3.1

### Patch Changes

- 2c4ee49: The bin of the pages section answers the search box.

  `GET /api/cms/pages` read the term for the tree and dropped it for the bin, so an editor looking
  for one deleted page among a hundred got the whole bin back and no sign that the box had been
  ignored. The term now narrows both lists — and `pages_tree` with `trashed`, which had the same
  gap, because an agent asking the bin for a name should not be handed everything in it either.

  The section says the right thing when a search comes back with nothing: it used to answer “the
  bin is empty” for any empty bin view, which was true until the box started working.

## 0.3.0

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

- Updated dependencies [ad9ead7]
- Updated dependencies [99853f4]
  - @webx-ui/module-blocks@0.4.0
  - @webx-ui/module-admin@0.6.0
  - @webx-ui/core@0.22.0
  - @webx-ui/schema@0.2.3

## 0.2.0

### Minor Changes

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

- Updated dependencies [14d79c1]
- Updated dependencies [738a7e9]
- Updated dependencies [2e27380]
- Updated dependencies [738a7e9]
- Updated dependencies [738a7e9]
- Updated dependencies [30a3d30]
- Updated dependencies [738a7e9]
- Updated dependencies [e93ae5b]
- Updated dependencies [a16ff45]
- Updated dependencies [046c6ba]
  - @webx-ui/module-blocks@0.3.0
  - @webx-ui/core@0.21.0
  - @webx-ui/module-admin@0.5.0
  - @webx-ui/schema@0.2.2

## 0.1.0

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

- 2c2c2ba: The pages section: the tree of a site's pages, and the panel API behind it.

  `@webx-ui/module-pages` is a new npm package — the front end of the section. The list is a table
  tree read a level at a time: the home page is pinned at the top and its children are the top
  level, because everything on the site is inside it and a branch drawn for that would give every
  row a step of indentation that says nothing. Children arrive when a branch is opened, searching
  puts the tree away and answers with a flat list of matches and their addresses, and the bin is a
  filter rather than a section of its own. A page is moved by dragging it or through “Move…” and a
  tree of pages — the one that works on a touch screen and in a catalogue where the page and its
  new parent are four screens apart — and either way the section says out loud how many addresses
  the move rewrote, because an editor should not hear about a thousand redirects from a search
  engine. Row actions: open, add a page inside, duplicate, move, copy the address, open on the
  site, delete; in the bin, restore.

  `webx-ui/module-pages` gains the section and the endpoints under `/api/cms/pages`: the level of
  the tree with `can` and `children_count` on every row, create, save the draft, move, duplicate,
  publish, unpublish, delete into the bin with the branch, and restore. A page's title comes from
  its draft and its address from the registry, so a page renamed and not yet published shows its
  new name beside the address the site is still serving. Its refusals — the home page cannot be
  moved or deleted, a page cannot be dropped into its own branch, nothing stands beside the home
  page — answer as a 422 under the field they are about, the same way a taken address does.

### Patch Changes

- Updated dependencies [2c2c2ba]
  - @webx-ui/module-blocks@0.2.0
  - @webx-ui/core@0.20.0
  - @webx-ui/module-admin@0.4.2
  - @webx-ui/schema@0.2.1

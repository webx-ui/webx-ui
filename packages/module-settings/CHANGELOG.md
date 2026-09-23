# @webx-ui/module-settings

## 0.3.11

### Patch Changes

- Updated dependencies [a0556f1]
  - @webx-ui/core@0.32.0
  - @webx-ui/schema@0.4.0
  - @webx-ui/module-admin@0.14.1

## 0.3.10

### Patch Changes

- Updated dependencies [8e0d587]
- Updated dependencies [8e0d587]
  - @webx-ui/module-admin@0.14.0
  - @webx-ui/core@0.31.0
  - @webx-ui/schema@0.3.8

## 0.3.9

### Patch Changes

- cd95a2e: A gzipped dump of the database every night, and one line in the panel saying so

  Insurance, not a restore system. The file lands on the same disk as the database it came from,
  so it survives a mistake and not a dead server, and there is no restore button anywhere — what
  it is for is getting yesterday's version of one row, one table or one article back by hand. It
  exists because backups are an extra on a good many hosts and absent on the rest, and having
  something is better than having nothing.

  - `webx:db:backup` writes `storage/app/private/backups/<database>-2026-09-21-0310.sql.gz`,
    gzipped as the dump comes out, so no uncompressed copy of the database ever touches the disk.
    `mysqldump` for MySQL and MariaDB, `pg_dump` for PostgreSQL, a copy of the file for SQLite.
  - Rotation runs **after** a dump has succeeded and never touches the newest file. Clearing out
    last week without having written tonight is the one thing a backup command must not do, and
    it is exactly what happens if the two steps are written the other way round. A failure exits
    non-zero, logs why, deletes its own half-written file and leaves everything else alone.
  - Structure for every table, rows for the ones worth keeping: `cache`, `sessions`, `jobs` and
    the rest of `skip_data` are dumped with `--no-data`, which on most sites is most of the file.
    The tables that keep their rows are dumped structure-and-data together, so pulling one table
    out of the finished file is a single contiguous range — the guide has the one-liner.
  - The password never appears in an argument, where `ps` would show it to anybody with a shell:
    MySQL gets a 0600 defaults file and PostgreSQL a 0600 `.pgpass`, both removed in a `finally`.
    `--single-transaction --quick` so the nightly dump does not lock the site, `--no-tablespaces`
    so it runs as a shared-hosting user, `utf8mb4` so the translated JSON columns survive.
  - `module-admin` puts the task on the scheduler itself, at `webx-admin.backup.at`. What it
    cannot do is run the scheduler: the site still needs a system cron on `schedule:run`, and the
    line in the panel is what notices when there is not one.
  - That line is at the foot of the settings screen, for whoever has `settings.view`: "Last
    database snapshot: today at 03:10 · 4.2 MB", and the same line as a warning when the newest
    file is more than two days old or there is none. Nothing is recorded in the database — the
    line is the newest file in the directory, and a task that failed is the file that is not
    there. `WxBackupNote`, fed from a new `backup` key in the manifest.

- Updated dependencies [f623fac]
- Updated dependencies [cd95a2e]
- Updated dependencies [b1aeb52]
  - @webx-ui/module-admin@0.13.0
  - @webx-ui/core@0.30.0
  - @webx-ui/schema@0.3.7

## 0.3.8

### Patch Changes

- Updated dependencies [0a506df]
  - @webx-ui/core@0.29.0
  - @webx-ui/module-admin@0.12.2
  - @webx-ui/schema@0.3.6

## 0.3.7

### Patch Changes

- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
  - @webx-ui/core@0.28.0
  - @webx-ui/module-admin@0.12.1
  - @webx-ui/schema@0.3.5

## 0.3.6

### Patch Changes

- Updated dependencies [537df98]
- Updated dependencies [537df98]
  - @webx-ui/module-admin@0.12.0
  - @webx-ui/core@0.27.0
  - @webx-ui/schema@0.3.4

## 0.3.5

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

## 0.3.4

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

## 0.3.3

### Patch Changes

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0
  - @webx-ui/module-admin@0.9.0
  - @webx-ui/schema@0.3.1

## 0.3.2

### Patch Changes

- Updated dependencies [74d1369]
  - @webx-ui/core@0.23.0
  - @webx-ui/schema@0.3.0
  - @webx-ui/module-admin@0.8.0

## 0.3.1

### Patch Changes

- Updated dependencies [4644d28]
- Updated dependencies [4644d28]
  - @webx-ui/module-admin@0.7.0

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

- 738a7e9: One step for the whole panel: cards, grids and forms read `--wx-gap`

  The panel's spacing step — 8 on a phone, 12 on a tablet, 16 on a desktop — used to space the
  frame alone. It now spaces everything: the air inside a card and between the things in it, the
  gap between the fields of a form, the gutter of a grid, the space between the strip of tabs and
  what it switches. Where there is no panel around them, the components fall back to 16, which is
  what they had.

  Two things change on their own account. A form's `gap="md"` is 16 rather than 24, so a form laid
  out by a card and a form laid out by itself finally agree. And a tab is now a column that spaces
  what it holds — two cards in a tab used to stand flush and read as one.

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

## 0.1.3

### Patch Changes

- Updated dependencies [2c2c2ba]
  - @webx-ui/core@0.20.0
  - @webx-ui/module-admin@0.4.2
  - @webx-ui/schema@0.2.1

## 0.1.2

### Patch Changes

- Updated dependencies [c92f42a]
- Updated dependencies [0304791]
  - @webx-ui/core@0.19.0
  - @webx-ui/schema@0.2.0
  - @webx-ui/module-admin@0.4.1

## 0.1.1

### Patch Changes

- d792e56: Republish so the dependency on `@webx-ui/module-admin` names the version that has `WxScreen`.

  The first version of the package was published by hand, before the release bumped the frame, so
  the tarball froze `^0.3.1` — a range that excludes the 0.4.0 which introduced `WxScreen`. Installs
  picked a nested copy of the older frame and the app failed to build on the missing export.

## 0.1.0

### Minor Changes

- 47d52ee: Screens in the panel, both halves, and the first section built on them.

  - `module-admin` (PHP): `Screens::register` / `Screens::extend`, the `FieldTypes` registry with the core types, `ScreenValues` (save by description — a key the tree does not name is dropped, a node without permission is closed for writing, rules per type and per language), `GET /api/cms/screens/{name}` (patched, permission-filtered, translated). The manifest carries `screens`, `groups` and each module's `group`; `Module::group()` is new on the contract (`AbstractModule` answers null). `webx-admin.groups` declares the `system` group.
  - `@webx-ui/module-admin`: `createAdmin({ screens, types })`, `WxScreen`, `admin.loadScreen()` cached per language, `admin.types`, navigation groups drawn as branches — "System" holds settings and administrators.
  - `module-media`, both halves: registers `wx-media` (the field on the client, the stored key with the resolved address on the server).
  - `module-settings`, both halves, new: the `settings.index` screen with one "General" tab, `cms_settings`, `GET`/`PUT /api/cms/settings`, `settings()` on the site, cache and `SettingsSaved`, MCP `settings_list` / `settings_get` / `settings_set`. The SEO tab is a project patch, not part of the module.
  - `core`: a `gear` icon; the language chip on a localized field unrolls every language in the site order — the current one included and marked — instead of reshuffling, and switching puts the caret into the field that was switched.
  - `module-auth`: the administrator implements `HasPermissions`, `cms.auth` makes the guard the request's default, and the section sits in the "System" group.

### Patch Changes

- Updated dependencies [47d52ee]
  - @webx-ui/core@0.18.0
  - @webx-ui/module-admin@0.4.0
  - @webx-ui/schema@0.1.1

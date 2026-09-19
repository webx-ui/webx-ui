# @webx-ui/core

## 0.24.0

### Minor Changes

- f87e4ec: `wx-rich-text`: the editor as a field of a screen

  A node type on both halves. On the server it is checked against `props.maxlength`, stored
  through an allowlist — a `<script>`, an `onclick` or a `javascript:` address does not survive —
  and an emptied editor is stored as `null` rather than as `<p></p>`. `localized` needs nothing of
  its own: the language map is picked apart one layer up, so a translated article is the same type
  run once per language.

  Pictures come from the file manager. `AdminModule` gains `pickImage`, which `module-media`
  supplies and the panel hands to every editor on every screen; a panel without a file manager
  draws no image button, because the editor does not offer what it cannot do.

  What a document keeps for a picture is the library's **key**, as `data-wx-path`, and the address
  is worked out again on every read through `WebxUi\Admin\Contracts\AssetUrls`. The same rule
  `wx-media` has always followed, one layer in: the address differs between deployments of one
  site, a private bucket's address expires, and an image edited in place changes the version stamp
  without changing the key.

  `WxRichText` itself gains `localized` — one editor with a language chip, as `WxInput` and
  `WxTextarea` have — and `labels`, so the panel can put its own words on the toolbar.

  `HasDraft::publish()` takes an optional `?CarbonInterface $at`: the date an entity is published
  under is not always now, and it cannot travel through the draft.

### Patch Changes

- f87e4ec: The article editor: tabs, blocks, autosave, the day it goes out

  `blog.article-form` is a described screen, like the page editor and for the same reason: the SEO
  card arrives as a patch from `module-seo` rather than being named in the blog's own description,
  and a project adds a tab the same way. Four tabs — the block constructor, the settings, SEO and
  the history — with a head above them that never moves and an action bar below.

  The settings are §10 of the spec: the address printed whole under the field that edits its last
  segment, the lead with a counter, the rubrics as a list that is dragged into order because the
  first one is the main one, a tag box that makes the tag it cannot find, the author, the cover,
  the pin, and the articles pinned under this one by hand. Five of them are node types the blog
  registers on both halves, so a rubric that is not a rubric is refused where every screen is
  checked rather than wherever somebody remembered.

  **The day is the part that is not a page editor.** The date in the settings tab is what
  "publish" publishes under, and the bar says which day that is before it is pressed: ahead, the
  article waits and answers 404 until its morning; behind, it moves down the feed. For an article
  that has never been on the site the day waits in the draft, because `published_at` is what "on
  the site" means and there is no column for a date that has not happened yet. For one that is
  already dated, moving the date writes the column at once — every listing orders by it.

  `WxActionBar` wraps. Its state box may shrink to nothing, and the words in it went on being
  painted where the box no longer was — straight across the buttons. Measured on a 375px screen:
  the box 0px wide and 105 tall, "Saved · goes out on 25 September at 17:06" over the top of "Save
  draft". Past the width of a short sentence the buttons now take a line of their own, still
  against the end of the bar.

  Saving is autosave, checked against the revision the form read and refused with a 409 when
  somebody wrote in between; the answer carries the article as it now is, so the panel asks which
  version the site gets instead of keeping one of the two silently. `PUT` now takes the screen's
  `values`, the history has its own two routes, `POST .../discard` throws away what is waiting,
  and `GET|POST /blog/tags` is the half of the tags API the article form needs — the screen that
  rakes them over comes with session D.

## 0.23.0

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

## 0.22.0

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
  - @webx-ui/tokens@0.3.0

## 0.21.0

### Minor Changes

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

- e93ae5b: The panel's frame: the bar goes into the sidebar, and the page scrolls itself.

  On a desktop and a tablet there is no bar across the top of the panel any more. The sidebar is
  the whole of the chrome and has three zones — the brand and the collapse button, the menu with its
  own scrollbar, the account at the bottom with its menu opening upwards — and the 56px the bar took
  out of the window's height go to the screen. A phone has no such column, so there the bar comes
  back with the burger, the brand and the account, and the menu is a drawer; choosing a section
  there now closes the drawer, which it did not before.

  The frame floats: the sidebar and the phone's bar are cards inset from the edges of the window,
  with the body colour running all the way round them. The inset is the panel's spacing step —
  8 on a phone, 12 on a tablet, 16 on a desktop — and the column is 220px wide, 56px as a rail,
  which leaves a screen exactly the width it had under the old frame at 1280 and at 1440.

  What scrolls is the page, natively: the shell no longer caps itself at one viewport, and the
  sidebar stands still beside a document that moves. A screen that has to be exactly as tall as the
  window still says `data-wx-fill`, but the height it gets is now measured from the window rather
  than from a scrolling column.

  `WxAside` grew the `top` and `bottom` slots — with either of them filled, `scroll` moves to the
  middle zone — plus `sticky`, for a column that stands beside a scrolling page, and `floating`, for
  one drawn as a card. `WxHeader` takes `floating` too. Both are additions: every existing shape
  behaves exactly as it did, and `viewport` shells are untouched.

  `webx-ui/module-admin` adds `nav.expand` in all ten languages, for the button on the rail.

- a16ff45: Icon buttons say what they are, and the tree gives its width back

  `WxAction` draws its own tooltip. `title` is now what the tip says rather than what the browser
  draws: one shape across a panel, a delay of our own, and a side that can be turned away from the
  edge of a dialog with the new `tooltipSide`. The accessible name is unchanged — `aria-label`
  carries `label`, then `title`, then the English name of the type — but the `title` attribute is
  gone from the markup, so an action is found by its name and no longer by `[title=…]`.

  A greyed action keeps its tip, which is when an icon needs one most. That needed the other half
  of the change: `disabled` puts `aria-disabled` and `tabindex="-1"` on the control instead of the
  attribute, because a disabled button receives no pointer events at all and nothing would ever
  open. Clicks and keys are turned away as before, and the control stays out of the tab order.

  The tooltip brings a portal beside the control, so an action is no longer a single root node.
  Templates do not notice; a test does — reach for the `button` inside the wrapper rather than for
  the wrapper itself.

  `WxTree` gives back the width it was spending on nothing. Two pixels between branches, so a list
  of names stops reading as one block. The level step is 16 rather than 21, and `indent` now
  counts the guide line inside itself, so a lined tree and a plain one indent by the same amount.
  The drag handle leaves the flow: it sits in the row's left margin and appears on hover, instead
  of holding 20 px of every row at every level. From the edge to the text: 77 px to 60 at the
  first level, 127 to 100 at the fourth. A leaf keeps the room where a chevron would be, so the
  names of leaves and branches still line up.

  New icon: `folder-move`, a folder with an arrow going down into it.

  The media library uses both. The button that shows the folder tree on a narrow screen is now
  `sidebar` — the same icon the shell shows and hides its own column with — and the one that moves
  files into a folder is `folder-move`. They were both drawn as `folder`, in the same row.

### Patch Changes

- 738a7e9: A note under a field is smaller than its label, and a group of fields has a card

  `WxFormItem` sets the help text and the error to `--wx-font-size-xs` — 12 against the label's 14.
  They used to share a size and differ only in weight and colour, so a two-line note read as a
  paragraph of its own and the eye lost the seam between one field and the next. The error moves
  with the help text rather than staying at 14: it takes the help text's place, and a line that
  jumps a size on the first failed save is worse than either size.

  The SEO tab of a page gets the card it never had. It arrives as a patch from `module-seo`, so
  the fix is in the patched node: the `wx-seo` field now travels inside a `wx-card`, and the tab
  stops being the one place in the panel where fields lie straight on the page background. The
  card holds SEO's own three sub-tabs — one card, tabs inside it, nothing nested.

  The snippet preview inside that card also gets its frame back. It asked for
  `--wx-color-border`, `--wx-color-surface-sunken`, `--wx-color-text-muted` and `--wx-color-text`,
  none of which are tokens; a name that does not exist resolves to nothing without complaint, so
  the box had no border, no background and no colour of its own.

## 0.20.0

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

## 0.19.1

### Patch Changes

- f7bdc63: `WxDialog` opens with the caret in the field marked `autofocus`. Reka handed focus to the first
  tabbable thing in the panel — the × in the heading — after the native `autofocus` had already
  fired, so a search or a title field asked for it and lost.

## 0.19.0

### Minor Changes

- 0304791: Repeater: a field whose value is a list of records

  `WxRepeater` is `WxSortableList` once every row is a form — a set of fields, repeated, in an order
  that is part of the answer. Rows fold to a name taken from their own fields, and each keeps a key
  of its own, so writing a field, removing the row above or dragging one elsewhere never rebuilds the
  form under the caret. `WxSortableList` gained `itemLabel` for the same reason: a row has to be
  called something out loud.

  In a described screen it is `wx-repeater`, the one type whose model is nested: the node's children
  are the fields of one item, and a `name` inside it is a key of that item. A type of its own can do
  the same with `nested: true`, which hands the component the node and the render context.

  On the server `RepeaterType` checks, stores and resolves items with the types their children
  declare — per language where a child is localized — and a failed row says which row it was.
  `FieldType::resolve` now takes the requested locale as a third argument, and `Tree::fields` stops
  at a named node: a repeater's children belong to its value, not to the screen.

### Patch Changes

- c92f42a: A card spaces what it holds, and a table's filters stop running off a phone

  Both showed up the moment the SEO settings tab put six fields in one card. `WxForm` hands its gap
  to its own children, and fields that land inside a card are not those: they stacked flush, and the
  hint under one read as the label of the next. A card's body and its two sidebar columns now stack
  what they hold with the same space the card keeps around it.

  The other one is `WxTable`'s header: a filter and a search field are each wider than a phone can
  spare, and an input will not shrink below its own intrinsic width — it overflowed sideways instead,
  and the whole page scrolled with it. The tools wrap now, and once the rows have become cards they
  are a column of their own, full width and all one height.

## 0.18.0

### Minor Changes

- 47d52ee: Screens in the panel, both halves, and the first section built on them.

  - `module-admin` (PHP): `Screens::register` / `Screens::extend`, the `FieldTypes` registry with the core types, `ScreenValues` (save by description — a key the tree does not name is dropped, a node without permission is closed for writing, rules per type and per language), `GET /api/cms/screens/{name}` (patched, permission-filtered, translated). The manifest carries `screens`, `groups` and each module's `group`; `Module::group()` is new on the contract (`AbstractModule` answers null). `webx-admin.groups` declares the `system` group.
  - `@webx-ui/module-admin`: `createAdmin({ screens, types })`, `WxScreen`, `admin.loadScreen()` cached per language, `admin.types`, navigation groups drawn as branches — "System" holds settings and administrators.
  - `module-media`, both halves: registers `wx-media` (the field on the client, the stored key with the resolved address on the server).
  - `module-settings`, both halves, new: the `settings.index` screen with one "General" tab, `cms_settings`, `GET`/`PUT /api/cms/settings`, `settings()` on the site, cache and `SettingsSaved`, MCP `settings_list` / `settings_get` / `settings_set`. The SEO tab is a project patch, not part of the module.
  - `core`: a `gear` icon; the language chip on a localized field unrolls every language in the site order — the current one included and marked — instead of reshuffling, and switching puts the caret into the field that was switched.
  - `module-auth`: the administrator implements `HasPermissions`, `cms.auth` makes the guard the request's default, and the section sits in the "System" group.

## 0.17.0

### Minor Changes

- 1d691b8: `WxCodeEditor` — a code field on CodeMirror 6: JSON (with a linter and `format()`), JavaScript, TypeScript, HTML, CSS, Markdown, YAML, PHP and plain text, styled entirely with the design tokens. The counterpart of `WxRichText` for code.

## 0.16.0

### Minor Changes

- 3e64cd7: Таблицы на телефоне и восемь правок по форме администратора.

  `@webx-ui/core`: ниже `cardsBelow` (480 по умолчанию, ширина самой таблицы, а не окна) строка
  рисуется карточкой из пар «подпись — значение». Ячейки те же самые — те же слоты `cell-<key>`,
  те же форматтеры, — так что экран, написанный под таблицу, переживает телефон без единой правки.
  Колонка с `hideOnCards` в карточку не попадает, а действия строки уезжают в слот `card-actions`:
  в карточке нет колонки, где им быть. Пагинация показывается только когда страниц больше одной —
  одну страницу листать некуда, а строку экрана она занимает. Проп `flush` снимает с таблицы
  её собственные поля вокруг шапки и карточек — для таблицы внутри карточки, где поля уже держит
  карточка, и второй их комплект ставил поле поиска на шаг правее кнопки над ним.

  `webx-ui/localization` кладёт под приложение переводы сообщений валидатора на десять языков.
  Laravel везёт их только по-английски, и панель, переведённая на десять языков, отвечала на
  незаполненную форму по-английски. `addPath` добавляет, а не заменяет: строка, опубликованная в
  `lang/` приложения, по-прежнему главнее.

  `@webx-ui/module-media`: у поля картинки появились пропорции — `aspect` с пресетами `16/9`,
  `4/3`, `1/1` и любым `width / height`.

  `@webx-ui/module-auth`: форма в две колонки — фото сбоку, поля справа, а на узком экране одной
  колонкой; кнопка и заголовок диалога короткие («Добавить», «Редактировать»); фильтры над списком
  администраторов убраны (их полдюжины, фильтр над шестью строками — это контрол, который надо
  прочитать вместо того, чтобы посмотреть).

## 0.15.0

### Minor Changes

- 0d288c1: Форма научилась говорить на нескольких языках, а картинка — жить на форме.

  `@webx-ui/core`: `localized` на `WxInput` и `WxTextarea`. Модель становится записью по языкам,
  инпуты называются `title[uk]`, `title[ru]` — так, как это читает обычный POST, — а язык, который
  редактируется, общий на весь экран: страница наполовину на одном языке и наполовину на другом это
  и есть непереведённая запись. Языки берутся не из пропсов каждого поля, а из панели
  (`provideLocales`), и это языки контента сайта, а не язык самой панели. `WxLocales` — то же самое
  вокруг секции, вкладками. Плюс `localizedValue()` для чтения такого значения в таблице.

  Пикер теперь умеет и загружать. Выбор только из того, что уже лежит в библиотеке, отправляет
  человека уходить со страницы, загружать файл и искать форму заново. И `accept` стал не
  подсказкой, а правилом: когда вызывающий сказал «картинка», фильтр типов из панели убирается и
  вернуть «все» нельзя.

  `@webx-ui/module-media`: `WxMediaField` переписан под два состояния — пустая рамка, открывающая
  библиотеку, и картинка с двумя действиями: подписи (`alt` и `title`, мультиязычные) в поповере и
  очистка поля с подтверждением, которая не удаляет файл. Запись хранит только ключ, поэтому поле
  само находит файл по нему: `webx-ui/module-media` отвечает на `GET files/by-path`.

  `@webx-ui/core` заодно: внутри `WxSelectionArea` больше не терялся двойной клик. Указатель
  захватывался на нажатии, и совместимостные mouse-события уходили вместе с ним — файл в менеджере
  настоящей мышью не открывался, хотя синтетический `dblclick` в тестах проходил.

## 0.14.3

### Patch Changes

- 47cc998: A second pass over the file manager, from using it: upload refusals written in extensions rather
  than a paragraph of mime types, a status bar under the grid instead of a toolbar that grows a
  line, folders created through a dialog rather than a `prompt` the browser may refuse, the page in
  a card, and the image editor cropping what the person actually framed.

  `@webx-ui/core`: `WxFileCard` falls back to the old clipboard when the modern one refuses, so the
  green tick appears wherever the copy actually worked.

## 0.14.2

### Patch Changes

- 752adf0: A field the browser has filled in keeps the panel's own colours. Browsers paint an autofilled
  input in a colour that belongs to no theme — yellow in a light panel, olive in a dark one — and
  set it with `!important`, so `WxInput` covers it with an inset shadow instead of trying to
  override it.

## 0.14.1

### Patch Changes

- d110a5f: A modal that closes now takes its node away exactly once. An unmount hook that throws came
  back through the host's `onErrorCaptured`, which unmounted again over a half-gone tree —
  turning a single error into a stack overflow.

## 0.14.0

### Minor Changes

- 7a8caa3: `WxImageEditor`: adjustments, a named output size, and four icons redrawn.

  **`filters`** offers brightness, contrast, saturation and black-and-white behind one button — off
  by default, so an editor asked for as a cropper stays one. The picture on screen is shown through a
  CSS `filter` and the canvas is drawn under the same string, so the preview and the file are one
  calculation and the picture is still sampled exactly once. The result carries both the numbers and
  the string, for a server that has to arrive at the same picture from the original. Where a canvas
  has never heard of `filter` — Safari before 16.4, where it fails silently and writes an unadjusted
  file — the same arithmetic is done over the pixels instead.

  **The output size** was a lone width beside two numbers, which left the reader to work out whether
  they measured the crop, the picture or the panel. It is captioned **Output** now, with a tip saying
  it is the size of the picture you will get and a field on each side of the `×`: type into either
  and the other follows the crop's shape. A height typed in comes back as exactly that height — the
  width is kept as a fraction behind the scenes, so nothing reads back a pixel out.

  **The icons.** `rotate-left` and `rotate-right` were drawing as two crescents with the arrowhead
  adrift: the arc was written between two points near enough the diameter that it could not be one
  sweep. Both are a ring with a corner for a head now. `flip-horizontal` and `flip-vertical` were two
  identical triangles, which read as a pair rather than as a mirroring and as a blot at toolbar size;
  they are a shape and its reflection, one filled and one drawn.

  New labels: `outputLabel`, `outputHint`, `widthLabel`, `heightLabel`, `adjustLabel`,
  `brightnessLabel`, `contrastLabel`, `saturationLabel`, `monoLabel`.

  Also: a modal's closing timer no longer reaches through `window` to clean up after itself, which
  threw a reference error whenever the panel outlived the page that opened it.

## 0.13.0

### Minor Changes

- e3e7ec3: Add `WxImageEditor` and `openImageEditor()`.

  A picture, a rectangle over it and a blob at the end: a crop with the eight grips everybody knows,
  ratios (locked with `aspect`, or picked from `ratios`), quarter turns, mirrorings, and an output
  size capped by `maxWidth` / `maxHeight`. It uploads nothing — `save` carries the blob, a named
  `File`, the measurements and the crop it was cut from, so a server can arrive at the same picture
  from the original. Everything on screen is geometry and the picture is drawn exactly once, at the
  end, under a single transform.

  `openImageEditor({ src })` is the editor in a dialog, awaited: it is `createModal` over it, and the
  answer is `undefined` when the panel is closed without saving. The edit action on `WxFileCard` now
  has something to open.

  Also: `rotate-left`, `rotate-right`, `flip-horizontal` and `flip-vertical` in the icon set, and
  `createModal<T, P>` now takes any object as `P` — an `interface` of props included, which the old
  `Record<string, unknown>` constraint turned away for want of an index signature.

## 0.12.0

### Minor Changes

- fc29012: `WxFileCard` — one file in a media library.

  A preview where there is a picture to show and a glyph where there is not, the name on one line
  with the full one in a tooltip when it does not fit, and the few things that can be done to the
  file: rename it, open a picture in an editor, copy its link, delete it.

  Renaming opens a small panel under the actions rather than replacing the name with a field:
  swapping a line of text for an input changes the height of the card, and a card in a grid changes
  the height of its row, so renaming one file made the whole library jump. Deleting asks first, in a
  panel of its own, with the file named in the question — a grid of thumbnails all looks much alike.

  `WxActions` gains `v-model:menuOpen`, since its menu's panel is teleported: a row that hides itself
  until the pointer is over it loses that pointer the moment the menu opens, and had no way of
  knowing to stay.

  It does none of them. Renaming reports a name, deleting reports a wish, the edit action reports
  that somebody wants an editor — nothing happens to the file until the screen holding the cards says
  so. The clipboard is the exception, because copying a URL is finished the moment it happens.

  The icon set gains a `file-<extension>` family — around sixty names over thirteen drawings, since a
  `.docx` and an `.odt` are both a page of prose — and `crop`. An extension nobody has drawn gets the
  plain page with its own name written under it, and `registerIcons({ 'file-dwg': … })` in an
  application is enough for a `.dwg` to have a drawing of its own: the card asks the registry, so no
  release of this library is involved.

  `extensionOf`, `isPicture` and `fileIconName` are exported, since a table of files wants the same
  answers.

## 0.11.0

### Minor Changes

- 6d876dc: A round of fixes from reading the docs on a phone.

  - `WxSelectionArea` now picks the item a finger taps. It used to ignore touch entirely unless
    `touch` was on, so a phone selected nothing at all; a finger that travels is still left to the
    scroller, and a cancelled gesture no longer picks whatever it started on. With `touch` on, the
    area sets `touch-action: none` — without it the browser took the drag away on a real device,
    which is why it worked in a desktop emulator and nowhere else.
  - `WxSteps` turns down the page on its own once a step would be narrower than `minStepWidth`
    (132px; `0` never folds). Across a phone the titles used to wrap to a letter a line.
  - `WxDescriptions` clamps a pair's `span` to the columns the list has, and folds every span to one
    when the list folds to one column. A `:span="2"` pair used to ask for more tracks than the grid
    had and threw the placement of every pair after it.
  - `WxMenu` shuts an open overflow flyout before re-splitting the bar, instead of leaving it
    standing with an empty list.
  - `WxSubmenu` sets the first child of an open branch off its own title, rather than leaving the
    same two pixels there as between siblings.
  - `WxTree` gains `springDelay` (600ms): holding a node over a closed branch opens it, as
    `WxTable`'s tree already did.
  - `WxDateRangePicker` no longer offers a clock under the calendar — the date-only format threw
    away whatever was set on it.
  - `WxTooltip`'s default `delay` is 150ms rather than 400ms.

- 828cd57: `WxSelectionArea`: a click picks a card again, and it can be told to hold only one.

  - A plain mouse click cleared the selection instead of picking the card under it. The area captures
    the pointer, and every event after that is retargeted to the element holding the capture — so the
    release reported the area itself, which read as a click on the background. The gesture is now
    read off where it began, which is also what a click is: a press and a release on the same thing.
  - New `multiple` prop, `true` by default. Off, the model never holds more than one value: there is
    no box, no run and no toggle, and a click or a tap picks the item under it. A gallery wants
    several; a file picker wants one.

### Patch Changes

- e8c2bd5: Three fixes from the same round, read on a phone again.

  - The overflow branch of a horizontal `WxMenu` rendered an empty list in a production build. Vnodes
    handed along as a prop skip the cloning Vue does for a slot rendered in a template, so an entry
    moving from the bar to the branch was being asked to mount twice — and in production a static
    entry is cached and handed back as the very same object. `WxNodes` now clones what it renders,
    which covers every split slot at once. It never showed in a development build.
  - The splitting bar is `overflow-x: clip` rather than `hidden`. A box with `hidden` is still a
    scroll container, and the browser scrolls one to reveal a focused button inside it — which, in
    the frame where everything is back in the bar to be measured, left the bar pushed sideways.
  - A tap in `WxSelectionArea` adds and removes rather than replacing, the way ctrl-click does. With
    no modifier and no box, a tap that replaced the selection meant a touch screen could never hold
    more than one item in it.

## 0.10.0

### Minor Changes

- f9b18b4: Table: rows that nest

  `tree` turns `WxTable` into the screen a pages or categories module wants — the tree and the data
  in one pane, instead of a sidebar tree beside a list of the same records. The first column carries
  the indentation and the disclosure; every other column is still a column.

  - **Lazy by design.** `data` is the roots and `load(row)` fetches one level, because a catalogue of
    five thousand categories is not a payload. A row says whether it is worth a chevron with
    `has_children` — `withCount('children')` under a name of your choosing. Nothing said about
    children still gets one: a branch nobody described is worth a request to find out.
  - **Dragging is the ordering.** Which third of a row the pointer is over decides the landing, and a
    row can never enter its own subtree. `node-drop` carries `parent` and `index`, which is a
    `PATCH` and nothing else.
  - **Holding a row over a closed branch opens it**, fetching it where the children are not in yet,
    so a move across the tree is one drag rather than a drag, a wait and another drag. Dropping into
    a branch that was never opened fetches it first: the position a row lands at is not a guess.
  - **Sorting and pagination are off in this mode**, and say so by not being drawn. Ordering rows
    would scatter the branches, and a page of a tree cuts them in half.

  It runs on `useTreeNodes`, the same machinery behind [`WxTree`](/components/tree), so a drop means
  the same thing in both. What it does not have is the keyboard equivalent of a drag that the tree
  has; a long move wants a **Move** action and a picker.

- 66b71f5: Transfer: two lists and a pair of arrows

  `WxTransfer` is the shape for choosing out of a set you also need to see — permissions, roles, the
  columns of a report. `items` is everything, `v-model` is the right-hand panel, and the left is
  simply what is left.

  - **The right panel is in the model's order**, not the catalogue's. The model is an array and that
    order is what will be saved; a panel that showed some other order would be quietly lying about
    what is about to be sent.
  - **The heading's checkbox ticks what the search left showing**, and nothing behind it. A
    select-all that quietly took forty hidden rows with it is a trap, not a convenience.
  - Tick and press an arrow, or double-click a row to move that one. `disabled` on an item pins it to
    the side it is on, from either direction. Every move is announced in a live region.
  - Side by side is the point of it, so when there is no room the panels stack and the arrows turn to
    point up and down — decided by the panel's width, not the window's.

  Also fixes a **disabled outline button that could not be seen**: `.wx-button--outline.is-disabled`
  took its border and its label from `--wx-button-bg-disabled`, which for the default type is the
  surface colour — so the button was painted in the colour of whatever it was sitting on, in both
  themes. It now uses the muted border and the disabled text colour. A disabled control still has to
  be seen to be disabled.

- 0fdbc7c: TreeSelect: the tree as a form field

  `WxTreeSelect` is a trigger that reads like every other control and a panel holding
  [`WxTree`](/components/tree). It is the "parent category" field, and with `multiple` the set of
  sections a record belongs to.

  - **The model is a key, not a node** — `parent_id` is what a form sends. The node comes with the
    `change` event, since an id alone is rarely what the screen needs to show.
  - **`multiple` puts a checkbox on every node** and leaves the panel open, because a set is rarely
    finished after one tick. `check-strictly` keeps a tick where it was made, for "exactly these
    three" rather than "everything under Brakes".
  - **Opening the panel reveals what is already chosen**: the branches leading to it open, so a tree
    of five hundred nodes does not open on its first page with the answer somewhere below.
  - **`show-path` spells out which node it is** — two categories called "Seals" under two different
    parents are otherwise one field showing "Seals" twice.
  - `filterable`, `lazy` with `load`, `clearable`, sizes, statuses, and the hidden input a plain
    `<form>` post needs.

  `useTreeNodes` now takes the tree as a getter as well as a `ref`: the field reads a prop it never
  rearranges, and reuses the same index the tree keeps for finding a node by key and the path to it.

- ef27c02: Tree: a structure you can see and rearrange

  `WxTree` draws a tree of records the way the backend already sends them — `node-key`, `label-key`
  and `children-key` name the fields, so a Laravel resource goes in without a `map` over it first.

  - **A drop is three zones on a row**: the edges put the node before or after, the middle puts it
    inside — on a leaf too, which is how a leaf becomes a branch. A node can never land inside its
    own subtree; that check belongs to the tree, not to the caller.
  - **`Alt` and the arrow keys move a node the same four ways a drag does**, and say so in a live
    region. It is the half most trees skip, and it is also the only way to rearrange one on a touch
    screen, where HTML drag and drop does not exist.
  - **`filter` is a string, not a callback.** Matches stay, so do the branches leading to them, and
    those branches open for as long as the term stands — a match hidden behind a closed parent is the
    one thing a search must never do.
  - **Checkboxes cascade both ways** unless `check-strictly` says otherwise, and a disabled node
    stays out of the cascade.
  - **`lazy` fetches a branch the first time it opens**, and opens it once the children are in, so a
    spinner is never followed by an empty box that then fills.

  The machinery — what is where, what is open, what a filter leaves standing, what a move does to the
  arrays — lives in `useTreeNodes`, which is what a tree-shaped `WxTable` will run on rather than a
  second implementation of the same thing.

### Patch Changes

- 3fdce1c: TreeSelect: the field names a node the panel has just fetched

  The field keeps one index to name its value and the tree inside its panel keeps another, and it is
  the tree that does the fetching. The signal that said "a branch arrived, rebuild" was private to
  each index, so picking a city out of a branch that had just loaded left the field showing
  `ua-odesa`. Every index now shares it.

## 0.9.0

### Minor Changes

- 52b655a: Dialogs from code: `openModal`, `createModal`, `useModal` and `confirm`

  Some dialogs do not belong in a template. "Are you sure?" belongs in the middle of the function that
  deletes something, and a picker belongs wherever a field needs filling — not declared once per
  screen, wired to a boolean, and answered through an event three components away.

  ```ts
  if (await confirm('Delete this product?')) await api.delete(product)

  export const productBrowser = createModal<Product, { exclude?: number[] }>(ProductBrowser, {
    resolveOn: 'select',
  })

  const product = await productBrowser({ exclude: chosen.value.map((item) => item.id) })
  ```

  - **The component stays a plain component.** It takes props and emits events; the opener turns one
    of those events into the answer, so the same file works in a template too. `useModal()` is there
    for a component that wants to close itself or for a button deep inside one — outside a modal it
    answers all the same, with calls that do nothing.
  - **It renders in your app.** A component mounted outside the tree would lose the plugins, the
    provides, the router, the store and the translations; this one is given the app's own context, so
    injection works as it would in a template. `app.use(WebxUI)` arranges it; `connectModals(app)` for
    anyone importing components one at a time.
  - **Dismissed is not an error.** The promise resolves with `undefined` when the panel is closed
    without an answer, and rejects only when the component itself throws — a cancel that throws turns
    every call site into a `try` block and one forgotten `catch` into an unhandled rejection.
  - **It closes before it goes away.** The promise settles the moment the answer is known and the node
    is taken 250ms later, so the panel plays its closing animation rather than being cut off mid-fade.
  - `confirm()` answers `true` or `false`, with a `danger` tone for anything that destroys something.

- c9a5992: SelectionArea: the rubber band over a grid or a list

  Drag across a media library, a card grid or a list of rows and everything the box touches is
  selected. `WxSelectionArea` draws the box and holds the selection; `v-wx-select="file.id"` hands it
  an item.

  - A directive rather than a wrapper component, because the thing being selected is already an
    element — a card, a row, a `<tr>` — and a box of ours around each one would break the grid or the
    table it sits in. It also keeps the value's type, which a `data-` attribute cannot: the selection
    comes back as the numbers the API expects. Markup that is not written in Vue can still say
    `data-wx-selectable="42"` and get the string.
  - The whole gesture, not just the box: shift or ctrl adds, alt takes away, a click picks one, a
    ctrl-click toggles it, a shift-click takes the run, a click beside them clears, ctrl+A and escape
    do all and none. A selection people can only make by dragging is one they cannot make an item at
    a time.
  - A drag that begins on a link, a button or a field is left to that control, so the bin on a tile
    stays a bin.
  - Dragging past the edge scrolls, faster the further past it you are. The items are measured once,
    in coordinates that do not move when anything scrolls, so the scrolling costs nothing per frame
    but the arithmetic.
  - Off for touch by default: on a phone a drag across a grid means scroll, and taking that away
    leaves people stranded.

- 5046f23: SortableList: a list whose order is the point

  `WxSortableList` holds a list in the order it is shown, with a heading above it and buttons at the
  end of every row — a gallery, the blocks on a page, the products picked for a promotion.

  - **The grip is ours.** Every row carries one, and only the grip starts a drag: something has to
    say the row can be moved, and a keyboard cannot drag anything. It takes focus, and from there
    space picks the row up, the arrows move it, space drops it and escape puts it back — the same
    splice the pointer performs, announced in a live region and with the focus following the row.
    `handle="row"` drags by the whole row instead; `handle=".my-grip"` gives it to a button of yours.
  - **A heading of its own**, `title` and `extra`, the pair `WxCard` uses — so a list that had been
    living inside a card keeps the same markup with one less wrapper.
  - **Buttons in a row are not handles.** Links, fields and the actions are filtered out of the
    gesture, so a bin at the end of a row stays a bin even when the whole row is draggable.
  - **Lists that share a `group` pass rows between them**, and an empty one is still somewhere to
    drop: the empty message is a row of the list rather than a note under it.

- 0ddba11: Wave 2 is complete: seventeen components, and a toast queue

  The ones the playground had been faking by hand:

  - **Avatar** and **AvatarGroup** — a picture where there is one, initials where there is not. The
    colour comes from the name by default, so the same person is the same colour on every screen, and
    a list of twenty is scannable without anybody choosing twenty colours. The initials sit under the
    picture rather than instead of it, so a slow connection shows them and nothing moves when the
    photograph lands.
  - **Empty** — what a list says when it has nothing in it, with room to say _which_ kind of empty:
    nothing yet, or nothing matched.
  - **Descriptions** and **DescriptionsItem** — a record read rather than edited. A `<dl>` laid out as
    a grid, because the pairs are a list and not tabular data; each pair is two grid items rather than
    a box holding two, which is what lets labels in different rows line up. Bordered draws the lattice
    with the grid's own gaps, exact at any column count and under any span.

  The rest of the wave:

  - **Toast** — `useToast()` and `WxToaster`. The queue is module state on purpose: a toast almost
    always comes from a place with no view of its own. Message and Notification are one component
    here; the difference between them is a title and a corner.
  - **Tooltip**, **Popconfirm**, **Loading**, **Skeleton**, **Progress**, **Result**, **Segmented**,
    **Steps**, **Image**, **Upload**, **Affix**, **Backtop**.

  Three of those are ours rather than what the checklist suggested, for the same reason each time.
  `Upload` does not upload: a component that owned the request would own the URL, the headers, the
  CSRF token and the shape of an error, none of which it can know — so it collects and checks, and
  the caller sends. `Affix` sticks with `position: sticky` and uses JavaScript only to report it,
  which avoids both bugs a `fixed` switch inherits. `Segmented` is a radio group rather than a row of
  buttons, so a screen reader announces "2 of 4" and the arrow keys work.

## 0.8.0

### Minor Changes

- 8b15353: A horizontal `WxMenu` folds what it cannot fit into a branch at the end of the bar

  An admin with eight sections outgrows a header long before the window becomes a
  phone. Until now the bar scrolled sideways, out of sight — and worse, its entries
  shrank past their own labels and slid over one another, because an entry in a bar
  was an ordinary flex item that kept `white-space: nowrap`.

  Entries that do not fit now move into a branch at the end of the bar, and come
  back as it widens. `overflow="scroll"` keeps the old behaviour, and
  `overflow-title` names the branch.

  They _move_: each entry is rendered in exactly one of the two places, so it keeps
  one identity and the branch shows as the trail when the page you are on is inside
  it. The bar measures itself with a `ResizeObserver`, and again once the typeface
  has loaded — text in the fallback face is a few pixels narrower per label, which
  is enough to leave one entry in the bar that the real face has no room for.

- 8b15353: `WxContainer` gains `viewport`, the shape an admin shell actually has

  `full-height` is `min-height: 100dvh`: the container may grow past the window, and
  so nothing inside it ever overflows. That made `scroll` on `WxMain` a no-op — the
  column grew with its content and the page was simply cut off at the bottom of the
  shell, with no scrollbar anywhere.

  `viewport` caps the shell at the window instead, so the column inside it overflows
  and scrolls. `full-height` still means what it said, for a document whose page
  scrolls as a whole.

- 353feff: `useElementWidth` — the measurement `useResponsiveShell` was built on, now on its own and exported.

  A layout that answers to its own box rather than to the window needs one number: how wide that box
  is. The shell composable had it inside; a screen with columns of its own needs the same thing for a
  different element — a reading pane deserves its width measured against what is left after the
  sidebar, not against the viewport that still counts it.

  ```ts
  const el = ref<HTMLElement | null>(null)
  const width = useElementWidth(el)
  ```

  `0` until it is measured, as in `useResponsiveShell`: treat it as "assume the roomy case", since the
  real number arrives before paint. Pass nothing to measure the page itself.

- 9005935: Controls read at 14px, and the size is a token now

  Every control — input, textarea, number, select, tags, autocomplete, cascader,
  colour, the date fields and their calendar, checkbox, radio, switch, rate, button —
  took its text size straight off the body scale, which put the default at 16px. That
  is a size for reading paragraphs. An admin panel is a page of controls, and beside
  navigation at 14px they were reading a size too large.

  The scale drops a notch: `md` from 16px to 14px, `sm` from 14px to 12px, `lg` from
  18px to 16px. Control heights are unchanged — `data-density="compact"` is still the
  way to tighten those.

  The size is no longer read off the body scale at all. `@webx-ui/tokens` gains
  `--wx-font-size-control-sm`, `--wx-font-size-control-md` and
  `--wx-font-size-control-lg`, and every control points at them, so retuning how
  controls read is three lines in a stylesheet rather than an override per component.

- f920ade: Controls read at the size the navigation does

  A select is a list of choices, and it now reads like the one in the sidebar:
  14px, medium — the value in the field and the options in the panel. Checkbox and
  radio labels come down to the same 14px, since a tick beside a label is a choice in
  a list too.

  Tabs go the other way. Given a strip wider than 600px they step up to 16px: at that
  size they are page-level navigation rather than a control, and were reading a size
  too small for the job. The question is put to the strip, not to the window, so tabs
  in a narrow panel on a wide desktop keep the compact size.

  `WxForm` gives its rows more room — `md` goes from 16px to 24px and `lg` from 24px
  to 32px. At 16px a field's hint sat close enough to the next field's label to be
  read as belonging to it.

- c4a0da2: `WxListDetail` — the screen an admin panel keeps coming back to, as a component: something to narrow
  the set, the records, and the open one.

  Inbox and messages, orders and an order, tickets, users, invoices — the furniture is identical every
  time, and so is the part nobody enjoys writing twice: what happens at 900px. It is layout only. Rows,
  records and filters stay yours, in the `filters`, `list`, `detail` and `empty` slots.

  `v-model:open` is one idea doing two jobs: beside the list it picks the detail over the empty state,
  and on a screen too narrow for a third column it raises the record as a panel — with `back` handed to
  the slot, so the column and the screen are written once. Which record is open stays with the caller;
  the component only knows whether there is one.

  The thresholds are not breakpoints but arithmetic on the widths you gave: the filters column folds
  away below `filtersWidth + listWidth + detailMin`, the detail below `listWidth + detailMin`. And they
  are measured against the component's own width, so a sidebar collapsing to a rail hands the screen
  160px and the filters column comes back by itself, while the same screen in a 700px drawer behaves
  like the phone it effectively is. Widen the list and both thresholds move with it.

  When the filters column does not fit, that slot moves into a drawer and the `list` slot is handed the
  button to open it.

### Patch Changes

- 8933ca2: Two fixes the inbox screen turned up, both about height.

  `WxMain` is a flex column now, and its inner element stretches. As a block it was only as tall as
  its content, so a screen asked to fill the page — a `WxListDetail` under a `<router-view />` —
  measured its `height: 100%` against that instead of against the column, and stopped halfway down
  the window. A scrolling column keeps the old arrangement, because there the inner element has to be
  as tall as its content or the bottom padding never makes it into the scroll — 1288px of content in
  a 216px column scrolls 1312px, not 1300.

  `WxAside scroll` is capped with `max-height: 100dvh` rather than fixed at `height: 100dvh`. Under a
  header, in a shell that fills the screen, the column is already the height of its row, and a hard
  viewport height there is a header taller than the window: the layout overflowed by exactly the
  header, every time. The cap still does its job on a page that scrolls, which is what the viewport
  height was there for.

- f920ade: `WxTable`: pinned columns land where they actually are, and the chrome behaves

  Four things a full-page table made visible:

  - **Pinned columns left a gap.** The offsets came from the widths the caller
    declared, and a declared width is honoured only while there is room: in `auto`
    layout a table that has to scroll squeezes every column proportionally. The
    numbers stopped being true exactly when pinning starts to matter, so the frozen
    block sat a few pixels wide of the column behind it and the scrolling rows showed
    through the seam. The offsets are now measured off the heading row.
  - **A table that fitted still had a scrollbar.** The strip that covers the seam
    beside a right-pinned cell sat a pixel past the table's edge, and that pixel is a
    pixel of scrollable width. It is now flush.
  - **Rounded corners under a heading.** With a title or a search field above the
    rows, the heading strip curved away from two square corners and left a white wedge
    in each. Those corners are square now.
  - **Edge shadows with nothing to hide.** The frozen block cast its shadow whether or
    not anything was underneath it. It now appears only on the side that has more to
    show, and goes away when the table fits.

- 8b15353: Navigation reads a step heavier, and the account menu stays where it belongs

  Menu labels are `500`, and `600` in a sidebar: a sidebar is the page's own table of
  contents and is looked at all day, while a bar between a logo and a user menu reads
  better a step lighter. The gap between an entry's icon and its label comes down
  from 10px to 8px.

  `WxHeader`'s `end` group no longer shrinks. Left as an ordinary flex item, it was
  the first thing a wide navigation bar squeezed — the account menu slid under the
  bar and off the edge of the header.

- dc82be5: `class` and `style` on a form control now land on the control

  Every control sets `inheritAttrs: false` and hands `$attrs` to the element inside
  it, so that `placeholder`, `autocomplete` and the ARIA attributes reach the real
  input. Taken literally that sent `class` and `style` there too, and both were then
  in the wrong place:

  - `class="w-60"` on a `<wx-select>` is asking for a narrower select, and the select
    is the wrapper, not its input.
  - A parent's scoped CSS could not reach it. Scoped styles carry an attribute
    stamped on a child component's root, so a class landing three elements deep
    matched nothing — silently.
  - Where `$attrs` went to an element that is only sometimes rendered — the search
    field of a filterable `WxSelect` — the class disappeared altogether.

  `class` and `style` now go on the root; everything else still goes on the control.
  The split is exported as `useControlAttrs` for anyone building a control of their
  own.

- Updated dependencies [acd2c88]
- Updated dependencies [9005935]
  - @webx-ui/tokens@0.2.0

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

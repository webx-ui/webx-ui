# @webx-ui/admin

## 0.9.0

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

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0
  - @webx-ui/schema@0.3.1

## 0.8.0

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

## 0.7.0

### Minor Changes

- 4644d28: `@webx-ui/module-inbox`: the section, the form editor and the statuses.

  One screen rather than two: the forms of the site in a column that is dragged into order, each
  with what is waiting in it, and the submissions of the chosen one beside them. The columns of
  that list are the fields of the chosen form, so a list of everything would have had no columns
  worth the name — and the two questions this section is opened to ask, "what is new" and "what
  does this form ask", are now one click apart instead of one screen apart. Which form is open
  lives in the address, so a link to it is a link somebody can send.

  The editor is five tabs and one save, with the fields as the exception: a question is a row
  with an identity that answers point at, so it is written in its own dialog, dragged into its
  own order and deleted softly. Its settings are the ones its type has and no others. The
  embedding tab prints the one line that puts the form on a page and the names the page fills
  its hidden fields by.

  `@webx-ui/module-admin` gains `landing` on a module and a route for `/`. Nothing answered
  there until now — the routes are the modules' and none of them claimed the root — so signing
  in landed on a blank page. The landing module is honoured once the manifest says the panel
  actually has it, and the first entry of the menu is the fallback.

- 4644d28: The submissions: the list, the card, and notes on any record of the panel.

  **`@webx-ui/module-admin` and `webx-ui/module-admin` — notes.** A record somebody can write a
  note on takes `HasNotes`, declares `Notable` and names the permission its notes are behind; the
  table, the endpoint and the `WxNotes` feed are the panel's own, so the next section that wants
  one — an order, a client — adds a trait rather than a copy. Two things the shared endpoint
  cannot be allowed to get wrong are closed in it: the type in the address is an alias of the
  morph map and never a class name, and the permission is the record's answer, never the
  controller's guess.

  **`webx-ui/module-inbox` — the panel's side of a submission.** One form's list, with its own
  `in_table` fields as columns and the counts of every tab travelling beside the rows; spam out of
  "all" and reachable by its own tab; a pile moved, marked or thrown away row by row, so the log is
  written and the attachments go with it. Beside it, one submission opened at an address of its
  own: the answers with the words they were asked in, the files, what the intake saw around it, the
  status and the assignee, the notes, the log, a reply by `mailto:`, and the arrows to the next one
  in the same filtered pile. Submissions can also be typed in by hand, through the same intake as
  the public door — a call that came by telephone lands in the same list, and carries none of the
  administrator's own browser and address as if a visitor had them.

## 0.6.0

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
  - @webx-ui/core@0.22.0
  - @webx-ui/schema@0.2.3

## 0.5.0

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

- 046c6ba: The panel wears the client's logo

  The corner used to hold `WEBX_ADMIN_TITLE`, a name from a deploy file, which made every
  installation look like the same borrowed tool. Settings gets a **Branding** tab with two
  pictures, and the frame wears them: `branding.logo` in the corner of the open sidebar at 28 px
  tall, `branding.mark` on the 56 px rail, above the button that opens the sidebar again.

  Two pictures rather than one and a cropping rule — a wordmark cut to a square is its first two
  letters, and only the client knows what their mark is. A mark left empty leaves the rail
  exactly as it was.

  The name does not leave. `general.project-name`, the localized field that has sat on the
  `General` tab since the section was written without anybody reading it, now becomes
  `manifest.title`: the text in the corner when there is no logo, the logo's `alt` when there
  is, and the deployed title again when it is cleared.

  On the server this is one binding — `WebxUi\Admin\Contracts\BrandingSource`, answered by
  `module-settings`. `module-admin` neither knows nor requires the section that holds a logo, and
  a panel with no source bound is the panel as it always was. The picture fields are `wx-media`,
  so `module-media` is what turns them into addresses; without it the values stay library paths
  the frame cannot read and the corner keeps its name, the same tolerance `module-seo` has for
  its `og:image`.

### Patch Changes

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
- Updated dependencies [738a7e9]
- Updated dependencies [e93ae5b]
- Updated dependencies [a16ff45]
  - @webx-ui/core@0.21.0
  - @webx-ui/schema@0.2.2

## 0.4.2

### Patch Changes

- Updated dependencies [2c2c2ba]
  - @webx-ui/core@0.20.0
  - @webx-ui/schema@0.2.1

## 0.4.1

### Patch Changes

- Updated dependencies [c92f42a]
- Updated dependencies [0304791]
  - @webx-ui/core@0.19.0
  - @webx-ui/schema@0.2.0

## 0.4.0

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
  - @webx-ui/schema@0.1.1

## 0.3.1

### Patch Changes

- b554497: Фотография в углу шапки. Сессия (`/auth/me`, ответ на вход) теперь несёт `avatar` — ключ, под
  которым лежит фотография, тот же, что в ресурсе администратора. `auth()` принимает
  `resolveAvatar` — ту же функцию, что и `admins()`, — и `WxUserMenu` показывает картинку вместо
  инициалов; без неё всё как раньше. Аватар в шапке стал крупнее (`lg`).

  Мелочи вокруг: чекбокс «Stay signed in» придвинут к полю пароля, иконка показа пароля больше не
  уменьшается до шрифта кнопки и совпадает по размеру с замком, разделители в меню пользователя
  получили настоящий `spacing="sm"` вместо несуществующего значения, из-за которого они брали
  отступ по умолчанию.

- Updated dependencies [1d691b8]
  - @webx-ui/core@0.17.0

## 0.3.0

### Minor Changes

- e5e129d: Каркас панели называется `module-admin`: `@webx-ui/module-admin` на npm и `webx-ui/module-admin` на
  Packagist вместо `@webx-ui/admin` и `webx-ui/admin`. Правило теперь одно на обе половины: всё, из
  чего состоит панель, — каркас и разделы — с префиксом `module-`, библиотеки, которые живут и без
  панели (`nested-set`, `localization`, `mcp`), — без него.

  Код не изменился: namespace `WebxUi\Admin`, конфиг `webx-admin`, экспорт `createAdmin` — те же.
  В composer `webx-ui/module-admin` объявляет `replace: webx-ui/admin`, так что сайт, который ещё
  требует старое имя, получит новый пакет; в npm старый пакет помечен deprecated. В сайте меняется
  импорт: `from '@webx-ui/module-admin'` и `'@webx-ui/module-admin/style.css'`.

## 0.2.4

### Patch Changes

- Updated dependencies [3e64cd7]
  - @webx-ui/core@0.16.0

## 0.2.3

### Patch Changes

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

- Updated dependencies [0d288c1]
  - @webx-ui/core@0.15.0

## 0.2.2

### Patch Changes

- 9e1b513: Three things found by putting the library on S3 behind a CDN.

  The image editor could not open a picture at all. It draws onto a canvas and writes that canvas
  out, which a browser refuses for bytes fetched from another origin without CORS headers — and a
  private bucket cannot be given those headers for the panel in any case. So `webx-ui/module-media`
  serves the picture itself at `files/{id}/source`, behind the same permission as the listing, and a
  `MediaFile` now says where that is. `url` stays what everything that only looks at a file uses.

  The editor also spoke English in a Russian panel: it is a component of the design system, so its
  words are props, and the manager was not passing any. It has its own ten-language group now, as
  does the question the card asks before deleting one file.

  `@webx-ui/admin`: changing the language renames the sections too. Titles are translated on the
  server and travel in the manifest, which was fetched in the previous language — so the panel used
  to switch everything except its own navigation until the page was reloaded.

## 0.2.1

### Patch Changes

- 47cc998: The file manager after an hour with it: icon actions instead of labelled buttons, filters behind
  popovers, folders in a drawer on a phone, case-insensitive search in any alphabet, previews that
  change when a picture is edited, and a copy-the-link that says whether it worked.

  `@webx-ui/admin` gains the toaster the panel never had — until now every `toast()` from every
  module reported into silence.

- Updated dependencies [47cc998]
  - @webx-ui/core@0.14.3

## 0.2.0

### Minor Changes

- 9c0a762: The panel has a language. `useTranslate('webx-admin')` and `useI18n()` draw the interface from
  a dictionary the server assembles out of every installed package's `lang` files, over the
  English this package carries in its own code — so a module is translated once, in the half that
  also writes the server's validation messages, and a panel with no server behind it still has
  labels.

  Which language belongs to the person reading, not to the site: the manifest carries their
  choice, and every request now says which language the panel is currently showing, so a 422
  arrives in the same language as the field it lands under. `i18n.state.contentLocales` is the
  separate list an editing screen builds its tabs from — the languages the site publishes in,
  which has nothing to do with the language of the chrome around them.

  `createHttp` takes a `headers` callback for standing headers read at the time of each request.

## 0.1.0

### Minor Changes

- 266f47d: The shell paints the page it owns. Every state now carries `wx-root` — the class holding the
  font family, the text colour and the page background — so the panel no longer renders in the
  browser's default serif on whatever background the page happened to have. Where the panel is
  the whole page rather than a widget on one, the body's own margin is reset, and the centred
  plain layout is `border-box`, so its padding no longer added a scrollbar to a column exactly
  one viewport tall.

### Patch Changes

- Updated dependencies [752adf0]
  - @webx-ui/core@0.14.2

# @webx-ui/module-auth

## 0.6.3

### Patch Changes

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0
  - @webx-ui/module-admin@0.9.0

## 0.6.2

### Patch Changes

- Updated dependencies [74d1369]
  - @webx-ui/core@0.23.0
  - @webx-ui/module-admin@0.8.0

## 0.6.1

### Patch Changes

- Updated dependencies [4644d28]
- Updated dependencies [4644d28]
  - @webx-ui/module-admin@0.7.0

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
  - @webx-ui/module-admin@0.6.0
  - @webx-ui/core@0.22.0

## 0.5.4

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

## 0.5.3

### Patch Changes

- Updated dependencies [2c2c2ba]
  - @webx-ui/core@0.20.0
  - @webx-ui/module-admin@0.4.2

## 0.5.2

### Patch Changes

- Updated dependencies [c92f42a]
- Updated dependencies [0304791]
  - @webx-ui/core@0.19.0
  - @webx-ui/module-admin@0.4.1

## 0.5.1

### Patch Changes

- 47d52ee: Screens in the panel, both halves, and the first section built on them.

  - `module-admin` (PHP): `Screens::register` / `Screens::extend`, the `FieldTypes` registry with the core types, `ScreenValues` (save by description — a key the tree does not name is dropped, a node without permission is closed for writing, rules per type and per language), `GET /api/cms/screens/{name}` (patched, permission-filtered, translated). The manifest carries `screens`, `groups` and each module's `group`; `Module::group()` is new on the contract (`AbstractModule` answers null). `webx-admin.groups` declares the `system` group.
  - `@webx-ui/module-admin`: `createAdmin({ screens, types })`, `WxScreen`, `admin.loadScreen()` cached per language, `admin.types`, navigation groups drawn as branches — "System" holds settings and administrators.
  - `module-media`, both halves: registers `wx-media` (the field on the client, the stored key with the resolved address on the server).
  - `module-settings`, both halves, new: the `settings.index` screen with one "General" tab, `cms_settings`, `GET`/`PUT /api/cms/settings`, `settings()` on the site, cache and `SettingsSaved`, MCP `settings_list` / `settings_get` / `settings_set`. The SEO tab is a project patch, not part of the module.
  - `core`: a `gear` icon; the language chip on a localized field unrolls every language in the site order — the current one included and marked — instead of reshuffling, and switching puts the caret into the field that was switched.
  - `module-auth`: the administrator implements `HasPermissions`, `cms.auth` makes the guard the request's default, and the section sits in the "System" group.

- Updated dependencies [47d52ee]
  - @webx-ui/core@0.18.0
  - @webx-ui/module-admin@0.4.0

## 0.5.0

### Minor Changes

- b554497: Фотография в углу шапки. Сессия (`/auth/me`, ответ на вход) теперь несёт `avatar` — ключ, под
  которым лежит фотография, тот же, что в ресурсе администратора. `auth()` принимает
  `resolveAvatar` — ту же функцию, что и `admins()`, — и `WxUserMenu` показывает картинку вместо
  инициалов; без неё всё как раньше. Аватар в шапке стал крупнее (`lg`).

  Мелочи вокруг: чекбокс «Stay signed in» придвинут к полю пароля, иконка показа пароля больше не
  уменьшается до шрифта кнопки и совпадает по размеру с замком, разделители в меню пользователя
  получили настоящий `spacing="sm"` вместо несуществующего значения, из-за которого они брали
  отступ по умолчанию.

### Patch Changes

- Updated dependencies [1d691b8]
- Updated dependencies [b554497]
  - @webx-ui/core@0.17.0
  - @webx-ui/module-admin@0.3.1

## 0.4.1

### Patch Changes

- e5e129d: Каркас панели называется `module-admin`: `@webx-ui/module-admin` на npm и `webx-ui/module-admin` на
  Packagist вместо `@webx-ui/admin` и `webx-ui/admin`. Правило теперь одно на обе половины: всё, из
  чего состоит панель, — каркас и разделы — с префиксом `module-`, библиотеки, которые живут и без
  панели (`nested-set`, `localization`, `mcp`), — без него.

  Код не изменился: namespace `WebxUi\Admin`, конфиг `webx-admin`, экспорт `createAdmin` — те же.
  В composer `webx-ui/module-admin` объявляет `replace: webx-ui/admin`, так что сайт, который ещё
  требует старое имя, получит новый пакет; в npm старый пакет помечен deprecated. В сайте меняется
  импорт: `from '@webx-ui/module-admin'` и `'@webx-ui/module-admin/style.css'`.

- Updated dependencies [e5e129d]
  - @webx-ui/module-admin@0.3.0

## 0.4.0

### Minor Changes

- 6f7b501: Модуль администраторов называется `admins`, а не `users`: раздел живёт по адресу `/admins`, права
  стали `admins.view`, `admins.manage`, `admins.audit`, области MCP — `admins:read`, `admins:write`,
  `admins:audit`, инструменты — `admins_list_admins`, `admins_grant_role` и остальные с тем же
  префиксом. `users` оставлено пользователям сайта: когда сайту понадобятся регистрация и вход, они
  станут отдельным модулем, и два раздела с одним именем столкнулись бы в правах и в навигации.

  Миграция переписывает права в уже сохранённых ролях, так что роль, которой вчера выдали
  `users.manage`, сегодня по-прежнему разрешает управлять администраторами.

## 0.3.0

### Minor Changes

- 3e64cd7: Администраторы: список, форма и выбор из кода.

  `webx-ui/module-auth` получил CRUD поверх того, что уже было, — поиск, фильтры по роли и
  состоянию, пагинация, роли только на чтение. Два действия сервер отклоняет сам, а не прячет
  кнопку: выключить или удалить самого себя и снять последнего суперадминистратора. Оба способа
  оставить панель, в которую некому войти. У администратора появилось фото — ключ библиотеки, как
  и везде.

  `@webx-ui/module-auth`: раздел `admins()`, диалог создания и редактирования, `selectAdmin()` и
  `selectAdmins()` — тот же список в диалоге, возвращает выбранных. Для выбора достаточно права
  `users.view`: назначить кому-то задачу и редактировать его учётную запись — разные вещи.

  Модуль не зависит от медиа: поле для фотографии и способ превратить ключ в адрес передаёт панель
  — единственное место, которое знает, что установлено и то и другое.

  Попутно: `WxAvatar` берёт `name` и сам считает инициалы, а проп `label` у него отсутствует — меню
  пользователя показывало всем заглушку вместо букв. И сама аватарка в меню больше не завёрнута в
  экшен: это не ещё один инструмент в ряду иконок, а кто вошёл.

### Patch Changes

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

- Updated dependencies [3e64cd7]
  - @webx-ui/core@0.16.0
  - @webx-ui/admin@0.2.4

## 0.2.1

### Patch Changes

- Updated dependencies [0d288c1]
  - @webx-ui/core@0.15.0
  - @webx-ui/admin@0.2.3

## 0.2.0

### Minor Changes

- 9c0a762: The sign-in card and the user menu speak the panel's language. Every string is still a prop and
  a prop given still wins; what changed is the default, which now comes from the dictionary rather
  than from English hardcoded in the component.

  The user menu gained a language picker — it belongs within reach rather than three clicks into a
  section somebody cannot read — and `auth.setLocale()` stores the choice against the
  administrator, so it follows them to the next machine.

  A throttle notice takes `:seconds` rather than `{seconds}`, matching the way the same string is
  written in the `lang` file it now comes from.

### Patch Changes

- Updated dependencies [9c0a762]
  - @webx-ui/admin@0.2.0

## 0.1.0

### Minor Changes

- 266f47d: The password reveal is a bare icon at the end of the field. It was a button with a surface of
  its own, and a second box inside an input reads as a second control — it took up a third of the
  field to say something the icon says on its own.

### Patch Changes

- Updated dependencies [752adf0]
- Updated dependencies [266f47d]
  - @webx-ui/core@0.14.2
  - @webx-ui/admin@0.1.0

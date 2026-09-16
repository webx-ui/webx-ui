# @webx-ui/module-media

## 0.6.1

### Patch Changes

- Updated dependencies [2c2c2ba]
  - @webx-ui/core@0.20.0
  - @webx-ui/module-admin@0.4.2
  - @webx-ui/schema@0.2.1

## 0.6.0

### Minor Changes

- 4d1d996: Three more media fields: `wx-gallery`, `wx-file` and `wx-files`.

  A gallery is a grid of thumbnails in the order somebody dragged them into, `wx-files` the same
  list as file cards, `wx-file` one of those cards. All three store what `wx-media` stores —
  `{ path, alt?, title? }` — singly or as a list, so the rules, the storage and the resolve are
  written once. `openMediaFiles({ accept: 'image', max: 10 })` is the library opened from code with
  multiple selection on, resolving with `MediaFile[]`.

## 0.5.1

### Patch Changes

- Updated dependencies [c92f42a]
- Updated dependencies [0304791]
  - @webx-ui/core@0.19.0
  - @webx-ui/schema@0.2.0
  - @webx-ui/module-admin@0.4.1

## 0.5.0

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

## 0.4.2

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

### Patch Changes

- Updated dependencies [3e64cd7]
  - @webx-ui/core@0.16.0
  - @webx-ui/admin@0.2.4

## 0.3.0

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

### Patch Changes

- Updated dependencies [0d288c1]
  - @webx-ui/core@0.15.0
  - @webx-ui/admin@0.2.3

## 0.2.0

### Minor Changes

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

### Patch Changes

- Updated dependencies [9e1b513]
  - @webx-ui/admin@0.2.2

## 0.1.0

### Minor Changes

- 47cc998: The file manager after an hour with it: icon actions instead of labelled buttons, filters behind
  popovers, folders in a drawer on a phone, case-insensitive search in any alphabet, previews that
  change when a picture is edited, and a copy-the-link that says whether it worked.

  `@webx-ui/admin` gains the toaster the panel never had — until now every `toast()` from every
  module reported into silence.

- 47cc998: New package `@webx-ui/module-media`: the file manager as a section of the panel, a picker that
  opens from code, and a form field that keeps `{ path, alt, title }` on the entity rather than on
  the file. Batch deletion moved to `POST files/delete` on the server, because the panel's own HTTP
  client sends no body on `DELETE`.
- 47cc998: A second pass over the file manager, from using it: upload refusals written in extensions rather
  than a paragraph of mime types, a status bar under the grid instead of a toolbar that grows a
  line, folders created through a dialog rather than a `prompt` the browser may refuse, the page in
  a card, and the image editor cropping what the person actually framed.

  `@webx-ui/core`: `WxFileCard` falls back to the old clipboard when the modern one refuses, so the
  green tick appears wherever the copy actually worked.

### Patch Changes

- 47cc998: Three things the file manager got wrong in Russian: the dialogs' buttons stood shoulder to
  shoulder, the confirm button read `manager.save` because nobody had shipped the key, and an
  upload refusal came back in English — the uploader is a bare `XMLHttpRequest` and was the one
  request in the panel that never said which language it was drawn in.
- Updated dependencies [47cc998]
- Updated dependencies [47cc998]
  - @webx-ui/admin@0.2.1
  - @webx-ui/core@0.14.3

# @webx-ui/php

## 0.17.0

### Minor Changes

- 4d1d996: `module-media` stores and resolves the three new field types, `module-blocks` tells an agent
  about them.

  `GalleryFieldType`, `FileFieldType` and `FilesFieldType` join `wx-media` in
  `WebxUi\Media\Screens`, sharing one set of rules, one `store` and one `resolve`. A resolved value
  now carries the whole of what the library knows — `url`, `thumb`, `name`, `extension`, `mime`,
  `size`, `width`, `height` — because a Blade template has nothing else to ask with, and a whole
  list is looked up in one query rather than one per value. `props.accept` is checked on the way in
  against the row fetched for the resolve; a key whose file has been deleted is kept and resolves
  to `url: null`.

## 0.16.2

### Patch Changes

- dd41bd8: A block's template is handed what the field type makes of a value, not the row as stored — the
  same way a screen's values are read for the site. A `wx-media` field keeps `{ path, alt, title }`
  and the template now also gets `url`, worked out when the block is printed, so the picture no
  longer has to be assembled from the disk's configuration inside the Blade. A value whose type
  nobody registered — `wx-blocks` above all — and a value whose key the schema does not name pass
  through as they are; a field inside `wx-repeater` or inside layout is resolved too, and the
  preview, the panel's own drawing and the check before publishing all see the same values as the
  page. The media field remembers the files it looked up for the length of one response, so a
  gallery costs one query per picture rather than one per mention.

## 0.16.1

### Patch Changes

- f7bdc63: `module-blocks`: the section moves into the System group of the navigation, first in it, above
  SEO — a block type is made once and then lives on the pages, so the section is opened the way
  the settings are.

## 0.16.0

### Minor Changes

- 7cecf88: The block constructor: the agent's doors, and the files

  `webx-ui/mcp` now serves what the modules declare. `WebxServer` is a `laravel/mcp` 1.0 server
  that reads the tool registry when it starts, so a panel exposes exactly the tools of the modules
  it has — over Streamable HTTP at `{api_path}/mcp`, closed by `webx.mcp-auth` until a Sanctum
  token opens it, and over stdio as `mcp:start webx`. `php artisan webx:mcp:token` issues a token
  to an administrator with the scopes as its abilities; a scope is checked once, before any handler
  runs. A handler now also receives the administrator the call acts as, and refuses with a thrown
  `ToolFailure` that the agent reads verbatim.

  `webx-ui/module-blocks` speaks it: `blocks_list`, `blocks_get`, `blocks_create`, `blocks_update`,
  `blocks_publish`, `blocks_render`, `blocks_get_content`, `blocks_set_content` and
  `blocks_preview_url`, through the same doors the panel uses and with `mcp` as the source in the
  history; the resources `blocks://guidelines`, `blocks://catalog`, `blocks://fields` and
  `blocks://site`; the prompt `design_block`. And the files: `webx:blocks:export` writes each type
  to `resources/blocks/{slug}.json`, `webx:blocks:import` reads them back — a version only where the
  content differs, `--publish` to publish what passes the checks, `--dry-run` to be told.

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

- 7cecf88: The block constructor: the package and the renderer

  `webx-ui/module-blocks` is the section of the panel where a block type is made entirely — its
  fields, its Blade template, its styles and its script — and the mechanism that prints an entity's
  content from such blocks. This is its first half, the rendering: three tables (`blocks`,
  `block_versions`, `block_bundles`), the `Block` and `BlockVersion` models with `saveVersion()` and
  `publish()`, the cached registry `BlockTypes`, Blade compiled from the database into one file per
  version, the `@blocks` directive for nesting, the `HasBlocks` trait with the `$table->blocks()`
  macro, and `Blocks::render()`.

  Every block renders inside its own try/catch, so one broken template leaves a gap and a report
  rather than taking the page with it; in preview mode the gap is a notice with the template's line,
  and every block is wrapped in a pair of comments the panel finds it by. Publishing a version renders
  it on its sample values first and refuses, with the line, when that throws. The schema's fields are
  the template's variables — a field added after the content was written is `null` on the old pages,
  not an error.

  The second half of the same package, the styles and scripts: the set of types a page rendered, at
  their versions, makes a hash that names a row of `block_bundles` with the glued CSS and JS, served
  by `/blocks/{hash}.css` and `.js` with a year-long immutable cache. `@webxBlocks` in the layout
  prints the tags — evaluated where it stands, after the content under `@extends` and components —
  with `('styles')`, `('scripts')` and `('runtime')` variants and an inline mode for small sets. A
  block's script is an initialiser per instance behind a small runtime (`webx.block`, `webx.mount`,
  `webx.provide`, `webx.use`) that also ships on its own at `/blocks/runtime.js`. Commands:
  `webx:blocks:bundles --prune|--warm` and `webx:blocks:clear`.

  The preview: `/_preview/{type}/{id}?token=…` shows the draft of an entity as the page it will
  be, through the same handler and view that answer the real address, with a `Resolution` of the
  entity's own, the drafts of the block types, the marker comments, and `no-store` plus `noindex`
  on the response. The token is one signed parameter that opens one entity for an hour;
  `Preview::url($entity)` makes it, `PreviewGrant::of($request)` is how a handler tells a preview
  from a visit. The preview prefix is closed to the address registry.

  What the preview stands on, in `webx-ui/module-admin`: drafts and versions for any entity.
  `HasDraft` keeps what is being prepared in a `draft` column next to what the site shows, with
  `saveDraft()`, `withDraft()`, `publish()`, `unpublish()` and `isPublished()` by `published_at`;
  `HasVersions` writes a numbered snapshot into `entity_versions` on every publication, keeps a
  ring of autosaves beside the history, trims to `webx-admin.versions.limit` with pinned versions
  excepted, and restores an old version into the draft. `$table->draft()` adds the columns,
  `webx:versions:prune` applies a lowered limit. And in `webx-ui/nested-set`: a detached node —
  `saveDetached()` saves a row with no place in the tree, for a "new page" that exists before
  anybody has decided where it goes; it joins the tree with the first placement.

  The panel section and the MCP tools follow.

### Patch Changes

- f78e485: A compiled block template is named by its content as well as its version. The slug and the
  version number alone are not unique across databases: a test suite on an in-memory database
  and the developer's own site compile into one directory, each with a `hero` at version 1 and
  a different template — and whichever compiled first served both, so a page printed the other
  site's block, or nothing. The file name now carries a hash of the template; a new version is
  still a new file, and nothing is ever invalidated.

## 0.15.0

### Minor Changes

- f98786f: The registry of a site's public addresses

  `webx-ui/routing` is one flat namespace — `/about`, `/blog`, `/alternator-belt-7100104` — one row
  per address, and one resolver that hands a request to whoever owns the path it matched. Uniqueness
  holds across every kind of content at once, which is the part no single module can do on its own: a
  module cannot see another module's addresses, and those are exactly the ones it collides with. A
  library rather than a section: the public side of a site uses it with no panel in sight.

  Add `HasUrl` to a model and register its type, and saving, renaming, moving in the tree, deleting
  and restoring keep the registry in step from then on. The address itself is built by the type's
  formatter — `Slug`, `TreePath`, `SlugId`, `SlugSku`, `Prefixed`, or one of your own — and a
  formatter is a pure function of the entity, so a save, a preview in a form and
  `webx:routes:rebuild` cannot disagree about what the address is. A project overrides somebody
  else's formatter from config and moves the addresses that already exist with one command; nothing
  dies in the move, because every address that changes leaves an alias behind. Collisions are settled
  by the type: refused under the slug field, or suffixed with the suffix written back into the entity
  so the form shows what the site will serve.

  Reading is a fallback route, which is the whole trick — a fallback is tried only when nothing else
  matched, so a project's own `/search` wins with no ordering to arrange. One spelling per address
  (a trailing slash, a capital letter or a doubled slash is a 301, query kept); exact beats prefix, so
  `/about/mission` is its own page rather than a tail handed to `/about`; an alias answers 301 and
  takes the tail with it, so a renamed category keeps its pages of filters. Publication stays the
  entity's business: the registry has a row for everything that exists, and the handler decides
  whether to show it. Addresses the application answers itself are refused when an entity is saved,
  not when a request arrives — losing silently to a live route leaves an editor with a page that
  exists everywhere except on the site.

  `webx:routes:rebuild` recomputes the addresses of a type, `webx:routes:check` reports what no
  constraint can — rows with no entity, entities with no row, aliases leading nowhere, addresses a
  project has since claimed with a route of its own — and exits 1 so a deploy can stop on it.

  `UrlNormaliser` has moved here from `webx-ui/module-seo`: one spelling of an address for both
  halves of the system, with `key()` added beside `normalise()` for the registry's own. `module-seo`
  now depends on `webx-ui/routing`, reads the aliases through the narrow `RouteAliases` contract,
  says in `POST /seo/test-url` what the registry holds at an address, and prints the `<head>` of a
  page the resolver found without the template having to name it.

  The guide is `apps/docs/guide/routing.md`.

## 0.14.1

### Patch Changes

- aac21d2: `webx-ui/module-media` stops shipping its tests, and stops flaking

  Seven of the eight composer packages carry a `.gitattributes` that keeps `tests/` out of the
  published archive and normalises line endings; `module-media` was the one that did not, so its
  test suite has been riding along to Packagist. It has one now, the same one.

  The tests it keeps to itself are one bug lighter. A fake upload is a blank canvas of the size
  asked for and nothing else — the name is never drawn into it — so two of them with the same
  dimensions are the same bytes, and the store deduplicates by content inside a directory. One
  helper drew a random width to keep its two files apart, which worked about seven hundred and
  ninety-nine times out of eight hundred; the other trusted a unique name, which never mattered at
  all and held only because no test yet puts two such files in one folder. Both now give each file
  a width of its own, the way the third helper already did.

## 0.14.0

### Minor Changes

- c92f42a: SEO: rules for addresses, redirects, and the `<head>` a page prints

  `webx-ui/module-seo` is the panel section for SEO that belongs to no entity, and the renderer that
  turns it into markup. Sources are asked in order and merged **field by field** — a rule that fills
  in nothing but a title keeps the description and the picture that came from below it — so the
  entity source that arrives with the first content module is an addition, not a change.

  One matcher serves rules and redirects alike: exact, then mask (`*` inside a segment, `**` across
  them), then a regular expression, by priority inside each group, against the path with its query
  string. A pattern that will not compile is refused when it is saved and never matches if it got in
  anyway. The active ones are one compiled list in the cache, dropped whenever any of them changes.

  Redirects run as global middleware rather than in the `web` group, because the addresses worth
  redirecting are the ones the site has no route for and those never reach a group at all — with the
  panel's own paths stepped over, so a mask cannot lock an editor out of the screen they wrote it on.
  `/robots.txt` answers from a setting; the SEO tab of the settings screen now comes from the module
  instead of from each project's own patch. `POST /seo/test-url` says what an address ends up saying
  and where every part of it came from.

- c92f42a: SEO in the panel: rules for addresses, redirects, and `wx-seo`

  `@webx-ui/module-seo` is the front half of the section, and the card that edits what a page says
  about itself. Two screens rather than two tabs — rules and redirects each have their own paging
  and their own search, and a tab that resets both on the way back is worse than a second address.
  Rules are listed in the order the site tries them, so reading the table top to bottom is reading
  what will happen.

  `WxSeo` is registered as the `wx-seo` field type on both halves: its value is everything a page
  says about itself as one object, so an entity's form gets the whole card from a patch the day it
  has somewhere to keep it. The text fields are language maps and grow the same chip every localized
  field in the panel does; the picture is not one, deliberately. The length counters are soft —
  search engines shorten what they shorten, and nothing here refuses a longer line.

  The share image comes in as `seo({ mediaField: WxMediaField })` rather than as an import, so the
  package does not depend on the library being installed.

  **Check an address** answers the question this section gets asked most — which redirect catches
  it, which rule matched, what each source contributed, what the page ends up with — in one call.

  The settings tab has moved out of every project's own patch and into the module, which makes it
  the first screen patch laid by a module rather than by a project. The guide is
  `apps/docs/guide/seo.md`.

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

## 0.13.0

### Minor Changes

- 47d52ee: Screens in the panel, both halves, and the first section built on them.

  - `module-admin` (PHP): `Screens::register` / `Screens::extend`, the `FieldTypes` registry with the core types, `ScreenValues` (save by description — a key the tree does not name is dropped, a node without permission is closed for writing, rules per type and per language), `GET /api/cms/screens/{name}` (patched, permission-filtered, translated). The manifest carries `screens`, `groups` and each module's `group`; `Module::group()` is new on the contract (`AbstractModule` answers null). `webx-admin.groups` declares the `system` group.
  - `@webx-ui/module-admin`: `createAdmin({ screens, types })`, `WxScreen`, `admin.loadScreen()` cached per language, `admin.types`, navigation groups drawn as branches — "System" holds settings and administrators.
  - `module-media`, both halves: registers `wx-media` (the field on the client, the stored key with the resolved address on the server).
  - `module-settings`, both halves, new: the `settings.index` screen with one "General" tab, `cms_settings`, `GET`/`PUT /api/cms/settings`, `settings()` on the site, cache and `SettingsSaved`, MCP `settings_list` / `settings_get` / `settings_set`. The SEO tab is a project patch, not part of the module.
  - `core`: a `gear` icon; the language chip on a localized field unrolls every language in the site order — the current one included and marked — instead of reshuffling, and switching puts the caret into the field that was switched.
  - `module-auth`: the administrator implements `HasPermissions`, `cms.auth` makes the guard the request's default, and the section sits in the "System" group.

## 0.12.1

### Patch Changes

- b554497: Фотография в углу шапки. Сессия (`/auth/me`, ответ на вход) теперь несёт `avatar` — ключ, под
  которым лежит фотография, тот же, что в ресурсе администратора. `auth()` принимает
  `resolveAvatar` — ту же функцию, что и `admins()`, — и `WxUserMenu` показывает картинку вместо
  инициалов; без неё всё как раньше. Аватар в шапке стал крупнее (`lg`).

  Мелочи вокруг: чекбокс «Stay signed in» придвинут к полю пароля, иконка показа пароля больше не
  уменьшается до шрифта кнопки и совпадает по размеру с замком, разделители в меню пользователя
  получили настоящий `spacing="sm"` вместо несуществующего значения, из-за которого они брали
  отступ по умолчанию.

## 0.12.0

### Minor Changes

- e5e129d: Каркас панели называется `module-admin`: `@webx-ui/module-admin` на npm и `webx-ui/module-admin` на
  Packagist вместо `@webx-ui/admin` и `webx-ui/admin`. Правило теперь одно на обе половины: всё, из
  чего состоит панель, — каркас и разделы — с префиксом `module-`, библиотеки, которые живут и без
  панели (`nested-set`, `localization`, `mcp`), — без него.

  Код не изменился: namespace `WebxUi\Admin`, конфиг `webx-admin`, экспорт `createAdmin` — те же.
  В composer `webx-ui/module-admin` объявляет `replace: webx-ui/admin`, так что сайт, который ещё
  требует старое имя, получит новый пакет; в npm старый пакет помечен deprecated. В сайте меняется
  импорт: `from '@webx-ui/module-admin'` и `'@webx-ui/module-admin/style.css'`.

## 0.11.0

### Minor Changes

- 6f7b501: Модуль администраторов называется `admins`, а не `users`: раздел живёт по адресу `/admins`, права
  стали `admins.view`, `admins.manage`, `admins.audit`, области MCP — `admins:read`, `admins:write`,
  `admins:audit`, инструменты — `admins_list_admins`, `admins_grant_role` и остальные с тем же
  префиксом. `users` оставлено пользователям сайта: когда сайту понадобятся регистрация и вход, они
  станут отдельным модулем, и два раздела с одним именем столкнулись бы в правах и в навигации.

  Миграция переписывает права в уже сохранённых ролях, так что роль, которой вчера выдали
  `users.manage`, сегодня по-прежнему разрешает управлять администраторами.

## 0.10.0

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

## 0.9.0

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

## 0.8.0

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

## 0.7.0

### Minor Changes

- 760d372: `webx-ui/module-media` gains its folder endpoints: the tree in one answer, create, rename, move,
  and a delete that refuses a folder with anything in it until it is asked again with `force`. The
  refusal carries the counts, because the panel has a real question to put to the person: pictures
  already placed in articles will stop opening.
- a7fd824: `webx-ui/module-media` gains image editing and its MCP tools. An edit is written over the same
  key, so every address already in an article keeps working, and the picture as it arrived is kept
  once so any edit can be undone. The tools cover the library the way an agent would use it —
  except deleting a folder, which is deliberately absent.
- de2b3a4: `webx-ui/module-media` gains its file endpoints and its previews: a paginated, searchable,
  filterable listing, multi-file upload, rename, batch move, batch delete, and a thumbnail
  endpoint that cuts a variant once and then redirects to it so the disk — or the CDN in front of
  it — serves the grid instead of PHP.
- 47cc998: The file manager after an hour with it: icon actions instead of labelled buttons, filters behind
  popovers, folders in a drawer on a phone, case-insensitive search in any alphabet, previews that
  change when a picture is edited, and a copy-the-link that says whether it worked.

  `@webx-ui/admin` gains the toaster the panel never had — until now every `toast()` from every
  module reported into silence.

- 5ccdccc: New package `webx-ui/module-media`: the panel's file manager. This is its first step — the
  section registers itself, carries its configuration and its ten languages, and declares the
  permissions the rest of the module will be built against. Folders, files and the endpoints
  around them follow.
- 377e894: `webx-ui/module-media` gains its schema and its storage: folders as a nested set, files that
  belong to one, and the service that puts bytes on a disk and takes them off it. Keys are built
  from a uuid and say nothing about the folder, so the same picture in two folders is two keys and
  moving a file between folders never touches the bytes.
- 47cc998: A second pass over the file manager, from using it: upload refusals written in extensions rather
  than a paragraph of mime types, a status bar under the grid instead of a toolbar that grows a
  line, folders created through a dialog rather than a `prompt` the browser may refuse, the page in
  a card, and the image editor cropping what the person actually framed.

  `@webx-ui/core`: `WxFileCard` falls back to the old clipboard when the modern one refuses, so the
  green tick appears wherever the copy actually worked.

### Patch Changes

- 47cc998: New package `@webx-ui/module-media`: the file manager as a section of the panel, a picker that
  opens from code, and a form field that keeps `{ path, alt, title }` on the entity rather than on
  the file. Batch deletion moved to `POST files/delete` on the server, because the panel's own HTTP
  client sends no body on `DELETE`.
- 47cc998: Three things the file manager got wrong in Russian: the dialogs' buttons stood shoulder to
  shoulder, the confirm button read `manager.save` because nobody had shipped the key, and an
  upload refusal came back in English — the uploader is a bare `XMLHttpRequest` and was the one
  request in the panel that never said which language it was drawn in.

## 0.6.0

### Minor Changes

- a9240d6: Seven more languages in the panel: German, Polish, French, Spanish, Italian, Portuguese and
  Turkish, alongside the English, Russian and Ukrainian that were already there. A site still
  decides which of them to offer in `config('webx-localization.panel')`.

  A test in each package holds the ten key sets together — a missing line falls back to English
  rather than to a key, which is right and also the reason a gap can sit unnoticed.

## 0.5.0

### Minor Changes

- 78d8ef3: The panel answers in the language of whoever is reading it. `webx-ui/admin` serves the language
  list and the interface dictionary — both public, because the sign-in screen is drawn before
  there is a session to ask — and carries `locale`, `locales` and `panelLocales` in the manifest.
  `webx-ui/module-auth` stores each administrator's choice on the administrator, so it follows
  them to the next machine and so validation messages arrive in the same language as the labels
  above them.

  Both packages ship English, Russian and Ukrainian. A site adds a language they never shipped by
  publishing their `lang` files and translating what is missing; the merge is per line, so an
  untranslated key falls back on its own rather than taking its screen with it.

- 78d8ef3: `webx-ui/localization` — the languages a site is published in, translated Eloquent attributes,
  and the dictionary the admin panel is drawn from.

  It keeps two things apart that are easy to run together. Interface phrases are written by
  whoever wrote the module, change at deploy, and live in the package's `lang` files; content is
  written by whoever runs the site, changes all day, and lives in the database. One store for
  each, and neither knows about the other.

  A model names its translatable columns and goes on being a model — the value is a JSON language
  map, readable by anything that understands `spatie/laravel-translatable`. The panel's own words
  come from the same `lang` files the server reads, so a module is translated once rather than
  once per half.

### Patch Changes

- 9c0a762: The panel's sidebar toggle has a translated label: `webx-admin::nav.collapse`, in English,
  Russian and Ukrainian. Without it that one control fell back to the English the npm package
  carries, which is a small thing that looks exactly like a broken translation.

## 0.4.0

### Minor Changes

- fd98bdd: `webx:panel` wires the panel's front end into the application that hosts it: it writes the
  entry file, adds it to the Vite inputs, points `webx-admin.vite` at it, and names the npm
  packages to install. Where it cannot recognise a Vite configuration it says which line to add
  rather than rewriting a build it does not understand.
- 7fa1539: `webx-ui/admin` can load the panel's own assets. `webx-admin.assets` names the built files, or
  `webx-admin.vite` names entry points for an application that builds the panel with Laravel's
  own Vite. With neither, the shell stays deliberately blank — the frame installed and the panel
  not is a real state, and it should look like one.

### Patch Changes

- 48c6e34: The entry `webx:panel` writes now imports the stylesheets. The packages ship compiled CSS that
  nothing imports on its own, so the panel built from the previous stub ran perfectly and looked
  like an unstyled form.

## 0.3.1

### Patch Changes

- b193bdf: `webx:admin` accepts the password in `WEBX_ADMIN_PASSWORD` when there is nobody to ask, so a
  provisioning script or a container entrypoint can create the first administrator. Still no
  `--password` option: an argument lands in the shell history and in the process list.

## 0.3.0

### Minor Changes

- 18d9a7e: `webx-ui/module-auth`: administrators, roles and sign-in. Installing it is what closes the
  panel — until now `webx-ui/admin` served its API to anyone. Administrators live in their own
  table behind their own guard, roles grant the permissions modules declare in the manifest, and
  every sign-in attempt is written down. Its MCP tools can read who has what and move people
  between roles, and deliberately cannot touch a password or mint a token.

## 0.2.0

### Minor Changes

- cc9d156: Two packages the admin panel is built from. `webx-ui/admin` carries the module contract, the
  registry, the manifest the front end reads before it draws anything, and the catch-all that
  keeps a deep link from 404ing. `webx-ui/mcp` carries the contract by which a module offers
  itself to an AI agent — a mutating tool is given `dry_run` and a write scope whether its author
  remembered them or not.

## 0.1.0

### Minor Changes

- 219fd59: First release of the Composer packages. `webx-ui/nested-set` brings nested set trees to
  Eloquent: subtree reads in one query, placement and moves that keep the bounds consistent,
  `toTree` for handing a whole tree to the front end, and `fixTree` / `checkTreeIntegrity` for
  when something has gone wrong anyway.

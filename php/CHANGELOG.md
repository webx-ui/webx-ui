# @webx-ui/php

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

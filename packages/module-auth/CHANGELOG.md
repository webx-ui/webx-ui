# @webx-ui/module-auth

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

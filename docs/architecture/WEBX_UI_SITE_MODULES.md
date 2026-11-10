# Модули сайта — спецификация

Модуль сайта — раздел панели, который живёт в репозитории одного сайта, а не в монорепе, но устроен
ровно как пакетный модуль: тот же провайдер, экраны, миграции, MCP, права, справка, демо, блоки и
`AGENTS.md`, та же npm-половина. Перенос в монорепу — перенести папку и поменять имена (§13), без
переписывания. Реестр пакетов — [`WEBX_UI_COMPOSER_PACKAGES.md`](WEBX_UI_COMPOSER_PACKAGES.md),
установка модулей на сайт — [`WEBX_UI_NEW_SITE.md`](WEBX_UI_NEW_SITE.md) §5–§6, права —
[`WEBX_UI_PERMISSIONS.md`](WEBX_UI_PERMISSIONS.md), справка — [`WEBX_UI_PANEL_HELP.md`](WEBX_UI_PANEL_HELP.md).

Статус: спроектировано, ждёт приёмки (webx-ui#138, этап 1). Открытые вопросы — §16, план — §15.

Пример во всей спеке — вымышленный модуль «Справочник офисов»: id `offices`, таблица `offices`,
папка `modules/offices`. Ни в коде, ни в доках, ни в тестах монорепы модуль сайта не называется
по клиенту или объекту.

## 1. Зачем и границы

Сайту бывает нужен раздел панели, у которого нет смысла как у публичного пакета: справочник,
который есть только у этого сайта и выводится только его блоками. Сейчас модуль WebX — это пакет
монорепы, опубликованный в npm и на Packagist, а то, что даёт `webx:make-module` сегодня, — класс
`App\Cms\Modules\<Name>Module` в `app/` сайта без npm-половины, без миграций, справки и MCP в одном
месте. Его нельзя перенести в монорепу, не переписав.

Спека владеет: раскладкой папки и именами (§4–§5), подключением composer и npm (§6–§7), тем, что
из контракта пакетного модуля работает само и что надо доделать (§8), заготовкой (§9), тестами
(§10), деплоем (§11), live и lab (§12), переносом в монорепу (§13).

**Не** владеет: тем, что модуль делает (это его собственная спека в репозитории сайта), CI
клиентского репозитория, платформой хостинга (её файлы отсюда не читаются — §16, вопрос 6).

## 2. Решения человека (10.11.2026, не переоткрывать)

1. Модуль сайта — папка в репозитории сайта, `modules/<name>`, подключается composer как
   **`path`-пакет** — без Packagist и реестра.
2. **Контракт тот же, что у пакетного модуля**: `AbstractModule` и провайдер, экраны JSON,
   миграции, MCP-инструменты (всё покрыто MCP), права и роли по умолчанию, справка панели,
   демо-данные, блоки через `BlockOffers`, `AGENTS.md`.
3. **npm-часть учитывается так же, как у пакетов**: фронт раздела — отдельный npm-пакет внутри
   папки модуля; `extra.webx.npm` / `extra.webx.panel` работают, `webx:panel --sync` вписывает его в
   `resources/js/admin.ts`, сборка панели сайта его включает. Перенос в монорепу — перенести папку и
   поменять имя и вендор.
4. Заготовка — `webx:make-module --local <name>`: обе половины модуля в сайте, по образцу FAQ.

## 3. Как устроен пакетный модуль сейчас

То, на что опирается спека, — прочитано в коде, а не предположено.

- **Сервер.** Провайдер из `extra.laravel.providers` (`package:discover`) в `boot()` регистрирует:
  разделы — `ModuleRegistry::register()` (класс на `AbstractModule`, `id()`, `permissions()`,
  `ProvidesDemo`, `ProvidesMcpTools`); экраны — `ScreenRegistry`; справку —
  `HelpRegistry::directory()`; блоки — `BlockOffers::offer()`; миграции, переводы
  (`loadTranslationsFrom(…, 'webx-<id>')`) и маршруты панели (`routes/api.php`, `cms.can`). Образец
  — `php/packages/module-faq/src/FaqServiceProvider.php`.
- **Реестр половинок** — `extra.webx` в `composer.json` пакета: `module`, `npm` (имя → диапазон),
  `panel.import` / `panel.style` / `panel.register`. Читают его `PackageRegistry` (из
  `vendor/composer/installed.json`, то есть **любого** установленного пакета, path-пакет тоже) —
  для `webx:panel --sync` и проверки `Halves` в `webx:doctor`.
- **`webx:panel --sync`** (`PanelCommand`) дописывает между маркерами `admin.ts` импорт, стиль и
  вызов; добавляет в `package.json` сайта зависимости из `extra.webx.npm` как есть (диапазон вида
  `file:` не трогает — `VersionRange::floor()` у «места» нет); ставит `layout` в
  `config/webx-<module>.php`; пишет `AGENTS.md` сайта из `AGENTS.md` пакетов.
- **`webx:module:add <vendor/package>`** — `composer require`, `webx:panel --sync`,
  `webx:roles:defaults --top-up`, `npm install`, `npm run build`. Принимает любой пакет, не только
  из каталога.
- **`Setup\Catalogue`** — записанный руками список модулей монорепы (id, пакет, npm, требования,
  языки демо); `webx:modules` показывает каталог и установленные пакеты вне его («outsiders»).
- **Права и роли.** `permissions()` модуля + `AccessCoverageTest` (трейт `AssertsPermissions` из
  `module-auth`). Готовые роли (`DefaultRoles`) — список прав в `module-auth` плюс
  вклад модулей контрактом `HasDefaultRoles` (этап 2).
- **Справка.** `AssertsHelp` гоняет `HelpAudit` строго по `en`, `ru`, `uk` по всем экранам
  приложения.
- **npm-половина** (`packages/module-faq`): `src/index.ts` экспортирует `faq()` — список
  `AdminModule` с ленивыми маршрутами; `messages.ts` с английскими ключами,
  `i18n.defaults('webx-faq', …)`; собирается своим `vite build` в `dist` + `style.css`; сайт грузит
  `dist`.
- **Скелет сайта** (`php/site`): в `vite.config.js` нет `@vitejs/plugin-vue` — панель приезжает
  собранными `dist`; `Dockerfile` собирает фронт в стадии `assets`, куда копируются только
  `package.json`, lock, `vite.config.js`, `resources`, `theme`, `public`; тесты сайта —
  `tests/{Unit,Feature}` на sqlite в памяти.

## 4. Раскладка папки

Одна папка — обе половины, как у пакетного модуля, только рядом, а не в двух деревьях монорепы:

```
modules/offices/
  composer.json          site/module-offices, extra.laravel + extra.webx
  AGENTS.md              формат WEBX_UI_AGENT_DOCS.md, ≤150 строк
  README.md
  config/webx-offices.php
  database/migrations/
  lang/<язык>/{module,screen,errors,…}.php
  resources/
    screens/form.json
    help/<язык>/offices.index.md, offices.form.md
    blocks/*.json        что модуль предлагает BlockOffers
    demo/                фикстуры ProvidesDemo
    theme/*.css          базовый CSS модуля для сайта (если есть)
    views/               публичные вьюхи (если есть)
  routes/api.php
  src/                   Site\Offices\  — провайдер, Panel\OfficesModule, Models, Http, Mcp, Demo
  tests/                 Site\Offices\Tests\
  js/
    package.json         @site/module-offices, private
    tsconfig.json
    src/index.ts, module.ts, messages.ts, i18n.ts, *.vue, *.test.ts
```

- **`js/`, а не `packages/` или `npm/`**: имя говорит, что внутри, и не путается с `packages/`
  монорепы. При переносе `js/` уезжает в `packages/module-offices`, остальное — в
  `php/packages/module-offices` (§13).
- Внутри `src/` и `js/src/` — ровно раскладка пакета монорепы; заготовка (§9) пишет её с образца
  FAQ, а не своей схемой.
- Модуль без фронта (только сервер, разделов в панели нет) — без `js/` и без `extra.webx.panel`, как
  `module-cart`.

## 5. Имена

Правило одно: **всё, что видно сайту, панели, базе и агенту, называется так же, как назвалось бы
в монорепе**; отличаются только три имени упаковки — их и меняет перенос.

| Что                                                                   | Пакет монорепы                  | Модуль сайта           | При переносе     |
| --------------------------------------------------------------------- | ------------------------------- | ---------------------- | ---------------- |
| composer-пакет                                                        | `webx-ui/module-offices`        | `site/module-offices`  | вендор `webx-ui` |
| PHP namespace                                                         | `WebxUi\Offices\`               | `Site\Offices\`        | `WebxUi\`        |
| npm-пакет                                                             | `@webx-ui/module-offices`       | `@site/module-offices` | scope `@webx-ui` |
| id модуля, права `offices.*`, MCP `offices_*`, области `offices:read` | `offices`                       | `offices`              | не меняется      |
| таблицы                                                               | `offices`, `offices_*`          | то же                  | не меняется      |
| переводы, вьюхи, конфиг, компоненты Blade                             | `webx-offices`                  | `webx-offices`         | не меняется      |
| ключи справки и экранов                                               | `offices.index`, `offices.form` | то же                  | не меняется      |
| имя функции npm-половины                                              | `offices()`                     | `offices()`            | не меняется      |

Почему так:

- **Пространство `webx-<id>` у модуля сайта — не вольность, а требование кода.** Словарь панели
  собирается только из пространств с префиксом из `config('webx-localization.namespaces')`
  (`['webx-']`), `webx:panel --sync` ищет `layout` в `config("webx-{$module}")`. Своё пространство
  (`site-offices`) означало бы правку конфига на каждом сайте и переименование ключей в каждом файле
  при переносе — ровно то переписывание, от которого спека уходит.
- **Вендор `site` один на все сайты.** Пересекаться между сайтами нечему — это разные приложения;
  с `webx-ui/*` вендор не пересекается никогда. Одно известное имя даёт инструментам признак
  «модуль сайта» без настройки (§8, строки 15–18): `AgentDocs`, `ModuleStyles`, `webx:modules`
  узнают его по префиксу `site/`. Своё имя вендора у каждого сайта — §16, вопрос 1.
- **`@site/…` — `"private": true`.** Пакет ставится только `file:`-ссылкой (§7); `private` не даёт
  случайно его опубликовать, а реестр npm под этим именем не спрашивается никогда.
- **Пересечение id с пакетом монорепы** — единственное опасное: `ModuleRegistry` отказывает
  дублю, и сайт с `offices` своим и пакетным не загрузится. Поэтому заготовка не даёт id, который
  есть в `Setup\Catalogue` или занят установленным модулем, а `webx:module:add` отказывает пакету,
  чей id уже у модуля сайта (§15, этап 6). Таблицы — под id, как у разделов (CLAUDE.md §1), значит
  свободный id — это и свободный префикс таблиц.

## 6. composer

В `composer.json` сайта — один path-репозиторий на все модули и обычный `require`:

```json
"repositories": [
    { "type": "path", "url": "modules/*", "options": { "symlink": true } }
],
"require": {
    "site/module-offices": "^0.1"
}
```

В `composer.json` модуля — **`"version": "0.1.0"`**. Проверено (§12.3): без версии composer берёт
её из git сайта (`dev-main`), и при `minimum-stability: stable` скелета `require` не решается
(«does not match your minimum-stability»). С версией пакет ставится симлинком
`vendor/site/module-offices -> ../../modules/offices/` — **относительным**, поэтому переживает
`COPY` в образе, другой каталог и другую ветку (`dist` у деплоя через cPanel). Версию не
поднимаем: модуль живёт вместе с сайтом, его версия — коммит сайта. При переносе поле удаляется
(в монорепе версию даёт тег).

- **`composer install` на деплое** ставит пакет из lock тем же симлинком; правка PHP модуля видна
  сразу, как правка `app/`.
- **Правка `composer.json` модуля** (новый `require`, `autoload`, провайдер) доходит только через
  `composer update site/module-offices`: автозагрузка и `extra` берутся из lock, а не с диска. То
  же, что `webx sync` после правки `composer.json` пакета на live-сайте.
- **`require-dev` и `autoload-dev` модуля composer не читает** — у зависимости они не действуют
  никогда. Автозагрузка тестов модуля — в `autoload-dev` **сайта** (§10); пишет её заготовка.
- **`Setup\Catalogue` модулей сайта не знает и знать не должен**: это список того, что монорепа
  поставляет. `webx:setup --modules=` их не предлагает; `webx:modules` уже показывает установленные
  пакеты вне каталога — доделать колонку «local» (§8, строка 18). `webx:module:add
site/module-offices` работает как есть, если path-репозиторий уже в `composer.json`.

## 7. npm

npm-половина — пакет `@site/module-offices` в `modules/offices/js`:

```json
{
  "name": "@site/module-offices",
  "version": "0.1.0",
  "private": true,
  "type": "module",
  "exports": { ".": "./src/index.ts" },
  "peerDependencies": {
    "@webx-ui/core": "*",
    "@webx-ui/module-admin": "*",
    "vue": "^3.5.0",
    "vue-router": "^4.5.0"
  }
}
```

`extra.webx` в `composer.json` модуля:

```json
"webx": {
    "module": "offices",
    "npm": { "@site/module-offices": "file:modules/offices/js" },
    "panel": {
        "import": "import { offices } from '@site/module-offices'",
        "register": "offices()"
    }
}
```

- **`file:`, а не workspaces.** `webx:panel --sync` уже переносит `extra.webx.npm` в
  `dependencies` сайта как есть и не трогает диапазон-место; workspaces потребовали бы вести поле
  `workspaces` ещё одним кодом. Проверено на npm 10.9 (§12.3): и так и так npm кладёт ссылку
  `node_modules/@site/module-offices -> ../../modules/offices/js` и поднимает зависимости в корень
  сайта, `node_modules` внутри модуля не появляется.
- **Зависимости — только `peerDependencies`.** `@webx-ui/*` и `vue` модуль берёт у сайта: импорт из
  `modules/offices/js/src` резолвится вверх по дереву до `node_modules` сайта, копии Vue не
  появляется (вторая копия — ловушка «Сайт, слинкованный с монорепой…» в
  `docs/pitfalls/packages-and-demo-sites.md`; `dedupe` скелета её всё равно держит). Своя
  зависимость модуля (библиотека, которой у сайта нет) — в `dependencies`, npm поднимет её в корень.
- **Собирает Vite сайта, из исходников; `dist` нет.** Модуль не публикуется, и своя сборка дала бы
  только лишний шаг, который забывают: ловушка «правка npm-половины доезжает до сайта только
  сборкой» исчезает, `npm run dev` сайта даёт HMR по файлам модуля. Отсюда:
  - сайту нужен `@vitejs/plugin-vue` в `vite.config.js` и `devDependencies` — **в скелете его нет**
    (§8, строка 16; §15, этап 4);
  - `panel.style` у модуля сайта нет: стили однофайловых компонентов уходят в CSS бандла панели
    сами. При переносе появляется `style.css` и строка `panel.style` (§13);
  - плагин `sources()` скелета хеширует и файлы модуля (реальный путь внутри сайта, не
    `node_modules`), так что `webx:doctor` видит устаревший бандл и после правки модуля.
- **`messages.ts`** — в `js/src/messages.ts`, те же английские ключи, что в `lang/en` модуля,
  объявляются `i18n.defaults('webx-offices', messages)` (образец — `packages/module-faq/src/i18n.ts`).
  Тест «ключи совпадают» читает `../../lang/en` вместо `../../../php/packages/module-faq/lang/en`.
- **Типы** — `vue-tsc` по `js/tsconfig.json` модуля; `@webx-ui/*` дают типы из своих `dist` в
  `node_modules` сайта. `vue-tsc` и `vitest` — в `devDependencies` сайта (devDependencies
  `file:`-пакета npm не ставит); скрипты `typecheck:modules`, `test:modules` в `package.json` сайта
  пишет заготовка.

## 8. Контракт: что работает само и что доделать

Проверка — по коду пакетов монорепы на этой ветке. «Само» значит: path-пакет с `extra.laravel` и
`extra.webx` проходит тот же путь, что пакет из `vendor/webx-ui`, без правок в webx-ui.

| #   | Умеет пакетный модуль                                                                                      | Модуль сайта                                                                                                                                                    | Доделать в webx-ui                                 |
| --- | ---------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------- |
| 1   | Провайдер из `extra.laravel.providers`                                                                     | само: `package:discover` читает `installed.json`, path-пакет там есть                                                                                           | —                                                  |
| 2   | Раздел: `AbstractModule`, `ModuleRegistry`, группа в `webx-admin.groups`                                   | само                                                                                                                                                            | —                                                  |
| 3   | Экраны JSON, патчи, `FieldTypes`, `NoteTypes`                                                              | само: `ScreenRegistry::register()` с путём модуля                                                                                                               | —                                                  |
| 4   | Маршруты панели в `webx.panel` за `cms.can`                                                                | само: `loadRoutesFrom` модуля                                                                                                                                   | —                                                  |
| 5   | Миграции                                                                                                   | само: `loadMigrationsFrom`; `migrate` на деплое и в `webx:boot` их видит                                                                                        | —                                                  |
| 6   | Переводы `webx-<id>`, словарь панели                                                                       | само при пространстве `webx-<id>` (§5); языки — §16, вопрос 2                                                                                                   | —                                                  |
| 7   | MCP: `ProvidesMcpTools`, `dry_run`, журнал, области, `webx:mcp-tools`                                      | само: `ToolRegistry` собирает инструменты из `ModuleRegistry`                                                                                                   | —                                                  |
| 8   | Права `permissions()`, `AccessCoverageTest`                                                                | само: трейт `AssertsPermissions` работает в тестах сайта                                                                                                        | —                                                  |
| 9   | Готовые роли (`webx:roles:defaults`, `--top-up`)                                                           | **сделано (этап 2)**: модуль называет свои права в `editor` / `seo` / `developer` контрактом `HasDefaultRoles`                                                  | **сделано (этап 5)**: заготовка реализует контракт |
| 10  | Справка: `HelpRegistry::directory()`, `webx:help`, MCP `help_get`                                          | само                                                                                                                                                            | —                                                  |
| 11  | Тест справки `AssertsHelp`                                                                                 | **сделано (этап 3)**: `assertModuleScreensHaveHelp($folder)` — свои экраны, `en` + языки панели сайта; ключи переводов — `AssertsTranslations` (`localization`) | —                                                  |
| 12  | Демо `ProvidesDemo`, `webx:demo --module=`                                                                 | само: `DemoCommand` берёт модули из `ModuleRegistry`                                                                                                            | —                                                  |
| 13  | Блоки `BlockOffers`, `webx:blocks:offered --module=`                                                       | само; подсказка `webx:module:add` читает `vendor/<пакет>/resources/blocks` — по симлинку тоже                                                                   | —                                                  |
| 14  | Реестры сайта: `CollectionSources`, `RouteTypes`, `SeoSources`, `SiteBar`, `CacheGroups`, `SnapshotTables` | само: регистрация из провайдера                                                                                                                                 | —                                                  |
| 15  | Базовый CSS модуля `resources/theme/*.css` → `webx:theme:sync`                                             | **нет**: `ModuleStyles::installed()` берёт только `webx-ui/module-*`                                                                                            | признак модуля сайта (этап 1)                      |
| 16  | `webx:panel --sync` → `admin.ts`, `package.json`, `layout`                                                 | **сделано (этап 4)**: импорт и вызов — само, `file:` переносится как есть; `@vitejs/plugin-vue` в скелете                                                       | —                                                  |
| 17  | `AGENTS.md` сайта из `AGENTS.md` пакетов                                                                   | **нет**: `AgentDocs::VENDOR = 'webx-ui/'`                                                                                                                       | признак модуля сайта (этап 1)                      |
| 18  | `webx:modules`                                                                                             | показан среди «outsiders» с именем пакета                                                                                                                       | колонка «local» (этап 1)                           |
| 19  | `webx:module:add`                                                                                          | само для `site/module-<x>`, если path-репозиторий уже есть                                                                                                      | отказ пакету с id модуля сайта (этап 6)            |
| 20  | `webx:doctor`: «обе половины», диапазоны npm, свежесть бандла, миграции                                    | само (`Halves` по `extra.webx`; у `file:` нет нижней границы — не проверяется); **сделано (этап 4)**: проверка `SiteModules`                                    | —                                                  |
| 21  | `webx:snapshot` записывает версии пакетов                                                                  | версии `site/*` не пишутся (`Snapshotter` фильтрует `webx-ui/`)                                                                                                 | признак модуля сайта (этап 1)                      |
| 22  | Отладочная панель называет модуль источником запроса                                                       | модуль сайта считается чужим `vendor` (`CallSite`)                                                                                                              | признак модуля сайта (этап 1)                      |
| 23  | Бюджеты запросов `CountsQueries`, `PerformanceTest`, кеш `CacheGroups`                                     | само: трейты работают в тестах сайта                                                                                                                            | —                                                  |
| 24  | Тесты Testbench в пакете                                                                                   | тесты на приложении сайта (§10)                                                                                                                                 | **сделано (этап 5)**: заготовка `TestCase`         |
| 25  | Сборка образа, деплой                                                                                      | **сделано (этап 4)**: `modules/` в стадии `assets` до `npm ci`, `**/node_modules` в `.dockerignore`                                                             | —                                                  |
| 26  | Lab и linked-сайты (`scripts/lib/linked-site.sh`)                                                          | **теряется**: `point_composer` переписывает `repositories` целиком, path-репозиторий сайта пропадает                                                            | сохранять относительные path-репозитории (этап 6)  |
| 27  | Changeset, релиз, Packagist, npm                                                                           | не нужны: модуль едет с коммитом сайта                                                                                                                          | —                                                  |

**Признак модуля сайта** (строки 15, 17, 18, 21, 22) — одно место в `module-admin`, а не пять
сравнений строк: список вендоров, чьи пакеты — «наши» (`webx-ui/`, `site/`), с методом «модуль
сайта ли это» (`site/`). Пакет темы и `module-debug` спрашивают его, а не держат свою константу.

## 9. Заготовка: `webx:make-module --local <name>`

Команда в `module-admin` рядом с нынешней `webx:make-module`. Порядок:

1. **Имя и id.** `offices` → id `offices`, класс `OfficesModule`, namespace `Site\Offices\`, пакеты
   `site/module-offices` и `@site/module-offices`. Отказ, если id есть в `Setup\Catalogue`, занят
   установленным модулем или `modules/offices` уже есть (`--force` — только перезапись файлов
   заготовки, не чужих).
2. **Файлы** из `module-admin/stubs/local-module/**` — раскладка §4, наполнение по образцу FAQ, но
   на одной сущности: модель `Office` с переводимым `title` и местом в списке `position` (его пишет общий `Ordering`, как у разделов монорепы), миграция; провайдер со всеми
   регистрациями §3; раздел с `offices.view` / `offices.manage`; маршруты списка, записи, порядка за
   `cms.can`; экран формы JSON; MCP `offices_list`, `offices_get`, `offices_create`,
   `offices_update`, `offices_delete`, `offices_reorder` (изменяющие — `Tool::mutating()`, значит с
   `dry_run`) и ресурс `offices://catalog`; справка `offices.index` и `offices.form` с каждым полем;
   `lang/` на языках панели сайта (§16, вопрос 2); демо на три записи; `AGENTS.md`; тесты §10;
   `js/` — `offices()` с маршрутами списка (`WxListScreen`) и формы (`WxScreen`), `messages.ts`,
   тест ключей.
3. **Проводка сайта** — идемпотентно, с отчётом строкой на файл, как `webx:panel`: path-репозиторий
   `modules/*` в `composer.json`; `autoload-dev` `Site\Offices\Tests\` → `modules/offices/tests/`;
   набор `Modules` (`modules/*/tests`) в `phpunit.xml`; `@vitejs/plugin-vue`, `vue-tsc`, `vitest`,
   `@vue/test-utils`, `jsdom` в `devDependencies` и скрипты `typecheck:modules`, `test:modules`;
   плагин Vue в `vite.config.js` (нет маркера — сказать строку, а не угадывать).
4. **Установка** — дочерним `webx:module:add site/module-offices` (composer, `webx:panel --sync`,
   роли, `npm install`, сборка), по той же причине, что в `webx:setup` §7.1: этот процесс загрузился
   до пакета. Последние строки — `php artisan migrate` и, если модуль предлагает блоки,
   `webx:blocks:offered --install --module=offices`.

Сгенерированный модуль сразу проходит свои тесты и `webx:doctor`. Это и есть проверка заготовки в
монорепе: тест команды генерирует модуль во временный сайт Testbench, проверяет файлы и правки
`composer.json`/`phpunit.xml` (повторный запуск ничего не меняет) и прогоняет сгенерированные
тесты; закоммиченного примера модуля в монорепе нет — он устарел бы молча (§16, вопрос 10).

Нынешний `webx:make-module <Name>` без `--local` остаётся (§16, вопрос 5).

## 10. Тесты

**PHP — на приложении сайта, а не своим Testbench.** Свой Testbench в папке модуля — это свой
`vendor` со всеми `webx-ui/*` второй раз и свой `composer install` на каждой машине; тесты на
приложении сайта видят ровно те пакеты и конфиг, с которыми модуль работает.

- Тесты — `modules/offices/tests`, namespace `Site\Offices\Tests`, автозагрузка — `autoload-dev`
  сайта (§6), запуск — `php artisan test` сайта (набор `Modules` в `phpunit.xml`), база — sqlite в
  памяти, как у тестов скелета.
- `Site\Offices\Tests\TestCase` расширяет `Tests\TestCase` сайта и берёт `RefreshDatabase`; при
  переносе это **единственный** файл, который пишется заново — на Testbench по образцу
  `module-faq/tests/TestCase.php`. Тесты пользуются только трейтами пакетов (`AssertsPermissions`,
  `AssertsHelp`, `CountsQueries`), которые работают в обоих местах.
- **Обязательно** (генерирует заготовка): `AccessCoverageTest`, `HelpCoverageTest` (по своей папке,
  §8, строка 11), `McpDryRunTest` (каждый изменяющий инструмент с `dry_run` ничего не пишет),
  `TranslationsTest` (наборы ключей совпадают на языках модуля), `PerformanceTest` (список и форма
  панели, MCP-список; публичная страница — если есть), `PanelTest` на CRUD.
- **Не обязательно** для модуля сайта: десять языков (§16, вопрос 2), `AgentDocsCoverageTest`
  (держит `php/packages/*` монорепы), changeset, PHPStan (у скелета его нет; §16, вопрос 7). Всё это
  — шаги переноса (§13).

**npm** — `npm run typecheck:modules` (`vue-tsc` по `modules/*/js`) обязателен; `npm run
test:modules` (`vitest`, jsdom) — тест ключей `messages.ts` против `lang/en` генерируется, остальное
по нужде (§16, вопрос 8). Визуальное — в браузере, как везде (CLAUDE.md §3).

Кто гоняет тесты — решает сайт: у скелета CI нет (`WEBX_UI_NEW_SITE.md` §1). Сайт, который гоняет
`php artisan test` в тестовой стадии образа, получает тесты модулей без правок.

## 11. Деплой

Модуль — обычные файлы репозитория сайта, отдельной доставки у него нет. Что от этого ломается и
где:

- **Образ по `Dockerfile` скелета.** Стадия `vendor` делает `COPY . .` и `composer install` —
  `modules/` в контексте, симлинк относительный, стадия `app` копирует его как есть: работает.
  Стадия `assets` копирует только `package.json`, lock, `vite.config.js`, `resources`, `theme` и
  `public`; `npm ci` молча ставит ссылку `file:modules/offices/js` в никуда, и `npm run build`
  падает на импорте `@site/module-offices` (проверено сборкой в этапе 4). Лечится копированием `modules/` до `npm ci` (этап 4): в скелете — `COPY --from=vendor /app/modules ./modules`, как `theme/`, потому что у сайта без модулей папки нет, а `COPY` несуществующей папки валит сборку (стадия `vendor` создаёт её `mkdir -p`); сайту со своим `Dockerfile` хватает `COPY modules ./modules`. `.dockerignore`
  скелета — `**/node_modules` вместо одного корневого (этап 4).
  **`Dockerfile` — файл сайта**: сайты, заведённые раньше, получают правку не релизом, а руками;
  поэтому проверка доктора (этап 4) и строка в выводе заготовки.
- **Платформа хостинга** (стейджинг собирает образ на каждый коммит, релиз — тегом) собирает образ
  из репозитория сайта. Что она берёт за основу — `Dockerfile` сайта или свой — отсюда не видно:
  файлы платформы не читаются (§16, вопрос 6). Если `Dockerfile` сайта, хватает правки выше.
- **Хостинг клиента на cPanel.** Схема, как у сайта, который так деплоится: фронт собирается
  отдельно (`npm ci && npm run build` в worktree коммита, `public/build` коммитится в ветку `dist`),
  на сервере — `git pull --ff-only`, `composer install` из lock, `webx:boot`, `webx:doctor`.
  `modules/` в git — сборка фронта его видит, `composer install` ставит относительный симлинк,
  `webx:boot` мигрирует таблицы модуля. Правок в скрипте не требуется; косметика — строка «new
  migrations» скрипта считает только `database/migrations`, стоит добавить
  `modules/*/database/migrations` (это скрипт сайта, не webx-ui). `composer.lock` должен быть в
  git — у такого сайта он есть.
- `composer install --no-dev --classmap-authoritative` в образе: пакет модуля — обычный пакет, его
  `src/` в карте классов; тесты модуля (`autoload-dev` сайта) в проде не грузятся.

## 12. Live-режим, lab и эксперимент

### 12.1. Live-сайты платформы

Пакеты `webx-ui/*` live-сайта — ссылки на монорепу; модуль сайта живёт в самом репозитории сайта
относительным path-репозиторием, ссылки его не касаются. Правка PHP, Blade, `lang` модуля видна
сразу, как правка `app/`; правка `composer.json` модуля, новые миграции, фронт при выключенном
watch — `webx sync <сайт>`. Сохраняет ли live-режим собственные `repositories` сайта, когда
переписывает `composer.json` под ссылки, — отсюда не проверить (§16, вопрос 6).

### 12.2. Lab-сайт

- `scripts/lib/linked-site.sh` (`point_composer`) **переписывает `repositories` целиком**: после
  ближайшего `scripts/lab-site.sh refresh` path-репозитория `modules/*` нет, и `composer update
"webx-ui/*"` падает на `site/module-offices`. Чинится тем, что `point_composer` сохраняет
  относительные path-репозитории сайта (этап 6); `point_npm` трогает только `@webx-ui/*`, `@site/*`
  ему безопасен. То же стоит проверить в `scripts/packages.mjs` репозитория демо-сайта — он не в
  этой монорепе.
- У lab нет git, `reset` сносит папку — модуль сайта на lab живёт до ближайшего `reset`. Сводная
  проверка (этап 8) идёт на копии lab (`LAB_NAME=webx-lab-copy scripts/lab-site.sh create`, как
  велит раздел «Lab-сайт» ловушек), чтобы не держать постоянный lab в непредусмотренном состоянии.

### 12.3. Что проверено экспериментом

На lab эксперимента не было: всё, что он показал бы про сервер (`installed.json`, `extra.webx`,
`webx:panel --sync`, `AgentDocs`, `ModuleStyles`), однозначно читается в коде (§3, §8), а
`refresh` lab затёр бы path-репозиторий (§12.2). Проверено в скретчпаде, офлайн:

- composer 2, path-репозиторий `modules/*`, `minimum-stability: stable`: без `version` в пакете —
  отказ «does not match your minimum-stability»; с `"version": "0.1.0"` — установка симлинком
  `../../modules/<x>/`, переживает смену ветки и `rm -rf vendor` + `composer install` из lock.
- npm 10.9: `file:modules/<x>/js` и workspaces `modules/*/js` дают одинаковую раскладку — ссылка в
  `node_modules/@site`, зависимости модуля подняты в корень, `node_modules` в модуле нет.

## 13. Перенос в монорепу

Модуль сайта стал нужен второму сайту — переносим. Пошагово:

1. **Id свободен** в `Setup\Catalogue` и среди пакетов монорепы; иначе — сначала переименование
   на сайте (id — это права, таблицы, MCP; это миграция данных, не правка имени).
2. **Папки.** `modules/offices` без `js/` → `php/packages/module-offices`; `modules/offices/js` →
   `packages/module-offices`.
3. **Имена** (§5): `site/module-offices` → `webx-ui/module-offices`, `Site\Offices\` →
   `WebxUi\Offices\` (`src/`, `tests/`, `composer.json`, ссылки на классы в `routes/` и
   `config/`), `@site/module-offices` → `@webx-ui/module-offices` (`package.json`, `extra.webx`,
   импорты); `"version"` из `composer.json` убрать; `require` — `^<общая версия>`.
4. **npm-половина → пакет монорепы**: `vite.config.ts`, `tsconfig.build.json`, `exports` на `dist`,
   `files`, `publishConfig`, `"private"` убрать, `peerDependencies` на `@webx-ui/*` →
   `dependencies: workspace:^` — как в `packages/module-faq`; `panel.style` в `extra.webx`;
   `extra.webx.npm` → `^0.0.0` до первой версии (ловушка `refresh` в `packages-and-demo-sites.md`).
   Путь в тесте ключей — `../../../php/packages/module-offices/lang/en`.
5. **Тесты**: `tests/TestCase.php` — на Testbench по образцу FAQ (§10); остальные тесты не
   меняются.
6. **Молчащие места** (CLAUDE.md §5): в `php/` — `require`, `autoload-dev.psr-4`,
   `phpunit.xml.dist`, `phpstan.neon.dist`, затем `node scripts/sync-php-version.mjs`; раздел —
   `Setup\Catalogue`, список модулей в `apps/playground/src/panel/main.ts` (и фикстуры
   `apps/playground/server/panel/`), `scripts/packages.mjs` репозитория демо-сайта; `RouteType` —
   `SiteBar::entity()`; `HelpCoverageTest` и `directory()`; права в `### Permissions` `AGENTS.md`;
   контентный раздел — в готовых ролях (этап 2 делает это свойством модуля).
7. **Правила монорепы, которых у модуля сайта не было**: десять языков `lang/` (`en`, `ru`, `uk`
   вычитаны носителем, остальные — машинный перевод, README говорит об этом); PHPStan;
   `AgentDocsCoverageTest`; ни слова о клиенте — в коде, фикстурах, демо, справке (CLAUDE.md §1).
8. **Документация**: спека `WEBX_UI_MODULE_OFFICES.md` и строка в `docs/architecture/README.md`,
   карточка в `WEBX_UI_COMPOSER_PACKAGES.md`, гайд `apps/docs/guide/offices.md`, строка в
   `apps/docs/guide/roadmap.md`, если там ведутся модули.
9. **Changeset** на `@webx-ui/module-offices` и `@webx-ui/php`.
10. **Человек**: зеркало `webx-ui/module-offices` и отправка на Packagist, первая версия npm-пакета
    (`WEBX_UI_PHP_RELEASE.md`, «Однажды настроено»; `docs/pitfalls/release-and-ci.md`).
11. **Сайт-источник** переходит на пакет: удалить `modules/offices`, `require
"webx-ui/module-offices"`, `webx:panel --sync` (строка импорта меняется — убрать старую), убрать
    `autoload-dev` и `file:`-зависимость. Миграции не повторяются: таблицы и имена миграций те же,
    `migrations` их помнит.

## 14. Обезличивание

- В монорепе — команда, заготовки, гайд, тесты, эта спека — модуль сайта называется вымышленно
  (`offices`, «Справочник офисов»), без названий клиентов, объектов и адресов; фикстуры демо — по
  правилам CLAUDE.md (фейковые телефоны, адреса без страны).
- Модуль на сайте клиента — код клиента в его репозитории; в монорепу он попадает только переносом
  (§13, шаг 7 — обезличивание входит в перенос).

## 15. План этапов

Каждый этап — один проверяемый результат; гейт — `pnpm gate:affected`, после — `lab-site.sh
refresh`.

| #   | Результат                                                                                                                                                                                                                                                                                                         | Пакеты                                   | Размер | Проверка                                                                                                            |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- | ------ | ------------------------------------------------------------------------------------------------------------------- |
| 1   | **Признак модуля сайта**: одно место с вендорами `webx-ui/` и `site/`; через него — `AgentDocs` (раздел модулей сайта в `AGENTS.md` сайта), `ModuleStyles` (`site/module-*`), `Snapshotter`, `CallSite`, колонка «local» в `webx:modules`                                                                         | `module-admin`, `themes`, `module-debug` | M      | тесты на фикстуре `installed.json` с `site/module-offices`: каждый из пяти видит его                                |
| 2   | **Готовые роли от модуля**: необязательный контракт, которым модуль называет свои права в `editor` / `seo` / `developer`; `DefaultRoles` складывает его со своим списком; `--top-up` доносит                                                                                                                      | `module-admin`, `module-auth`            | M      | тест: модуль с контрактом попадает в `editor` при создании и при `--top-up`; снятое владельцем не возвращается      |
| 3   | **Тесты для сайта**: `AssertsHelp` по своей папке справки и своим языкам; трейт «ключи переводов совпадают» для модуля вне монорепы                                                                                                                                                                               | `module-admin`, `localization`           | S      | тесты трейтов: чужой экран без справки не валит, свой — валит; язык вне списка не требуется                         |
| 4   | **Скелет и доктор**: `@vitejs/plugin-vue` в `vite.config.js` и `package.json` скелета; `COPY modules ./modules` до `npm ci`, `**/node_modules` в `.dockerignore`, набор `Modules` в `phpunit.xml`; проверки доктора: модуль сайта с `.vue` без плагина, `Dockerfile` без `modules`, id модуля сайта = id каталога | `php/site`, `module-admin`               | M      | `scripts/php-smoke.sh` зелёный; тесты проверок; `docker build` скелета с модулем-фикстурой проходит стадию `assets` |
| 5   | **`webx:make-module --local <name>`**: заготовки обеих половин (§9), проводка сайта, дочерний `webx:module:add`                                                                                                                                                                                                   | `module-admin`                           | L      | тест команды: файлы, идемпотентная проводка, отказ на занятый id; сгенерированные тесты зелёные на временном сайте  |
| 6   | **Линковка и защита id**: `point_composer` сохраняет относительные path-репозитории; `webx:module:add` отказывает пакету с id модуля сайта                                                                                                                                                                        | `scripts`, `module-admin`                | S      | `refresh` копии lab с модулем сайта: репозиторий на месте; тест отказа                                              |
| 7   | **Документация**: гайд `apps/docs/guide/site-modules.md` (en; создание, тесты, деплой, перенос), ссылки из `extending.md` и `new-site.md`, ловушки темы `packages-and-demo-sites.md`, статус спеки                                                                                                                | `apps/docs`, `docs`                      | S      | `pnpm docs:build`; prettier                                                                                         |
| 8   | **Сводная проверка на копии lab**: модуль создан командой; раздел в панели, CRUD; MCP-инструменты видны и пишут с `dry_run` и без; миграции вперёд и назад; справка на экранах; `npm run build`; `rm -rf vendor node_modules` + `composer install` + `npm ci` с нуля; `docker build`; `webx:doctor --strict`      | — (чинить в этапах выше)                 | M      | отчёт по каждому пункту; копия убрана (`site down`, папка, база)                                                    |

Порядок: 1–4 независимы; 5 опирается на 1–4; 6 — до 8; 7 — после 5.

## 16. Открытые вопросы

1. **Вендор: общий `site` или свой у каждого сайта** (`acme/module-offices`, `Acme\Offices\`,
   `@acme/…`)? Рекомендация — общий `site`: инструментам нужен признак без настройки (§8), а
   пересекаться между сайтами нечему. Свой вендор потребовал бы ключа конфигурации «наши вендоры» на
   каждом сайте.
2. **Языки модуля сайта.** Рекомендация — `en` обязателен (это fallback словаря и `messages.ts`) плюс
   языки панели сайта (`config('webx-localization.panel')`); тест сверяет ключи между тем, что есть.
   Десять языков — шаг переноса (§13, шаг 7). Справка — те же языки; `en` обязателен.
3. **Сборка npm-половины — Vite сайта из исходников или своя сборка в `dist`.** Рекомендация — из
   исходников (§7): ни шага, который забывают, ни правок в скриптах деплоя; цена — плагин Vue в
   скелете и пять строк при переносе. Своя сборка ближе к монорепе, но требует `build` до сборки
   сайта в `Dockerfile`, в сборке ветки `dist` и в `npm run dev`.
4. **Пересечение id с будущим пакетом монорепы.** Рекомендация — запрет при создании по каталогу и
   отказ `webx:module:add` (§5); пакет, чей id заняли раньше, на таком сайте ставится после
   переименования модуля сайта или после переноса.
5. **`webx:make-module` без `--local`** (класс в `app/Cms/Modules`, без npm-половины). Рекомендация —
   оставить для раздела «один класс поверх готового» и сказать в гайде, когда что; со временем —
   решить, не убрать ли.
6. **Платформа хостинга** — вопрос к человеку: собирает ли стейджинг образ по `Dockerfile` сайта, и
   сохраняет ли live-режим собственные `repositories` сайта, когда переписывает `composer.json` под
   ссылки на монорепу? Файлы платформы отсюда не читаются. Рекомендация — проверить на стейджинге
   первого сайта с модулем; если нет — задача на платформу.
7. **PHPStan для модуля сайта.** Рекомендация — не в заготовке: у скелета его нет; появляется при
   переносе.
8. **vitest обязателен?** Рекомендация — обязателен `vue-tsc` и сгенерированный тест ключей; тесты
   логики — по нужде, как решит сайт.
9. **Версия path-пакета.** Рекомендация — `0.1.0` навсегда (§6): поднимать её незачем, модуль едет
   коммитом сайта; при переносе поле удаляется.
10. **Пример модуля в монорепе.** Рекомендация — не держать закоммиченный пример: заготовка и тест
    команды, который генерирует модуль и гоняет его тесты, — пример, который не устаревает.

# WebX UI — бриф для Claude Code

Названия: npm scope `@webx-ui`, GitHub-организация `webx-ui`, репозиторий `webx-ui/webx-ui`,
префикс компонентов `Wx` (в шаблонах `<wx-button>`), CSS-классы `.wx-*`, CSS-переменные `--wx-*`.

## 1. Что это

Публичная open-source дизайн-система на Vue 3 и ядро админки (CMS) сайтов на Laravel. Две половины:

- **npm** (`packages/*`): `@webx-ui/tokens` (JSON → CSS-переменные, светлая и тёмная темы),
  `@webx-ui/core` (компоненты; логика без стиля — Reka UI, drag-and-drop — `vue-draggable-plus`,
  датапикер — `@vuepic/vue-datepicker`, rich-text — Tiptap), `@webx-ui/schema` (экраны как JSON) и
  `@webx-ui/module-*` — фронт разделов панели. `@webx-ui/adapter-laravel` не начинался.
- **composer** (`php/packages/*`, вендор `webx-ui/*`, namespace `WebxUi`, одна общая версия):
  разделы панели `module-*` и библиотеки `cache`, `localization`, `mcp`, `nested-set`, `routing`,
  `themes`, `theme-default`, `widgets`. Список и назначение — `ls php/packages` и реестр
  `docs/architecture/WEBX_UI_COMPOSER_PACKAGES.md`.

**Имена.** Всё, из чего состоит панель, — с префиксом `module-` (каркас `module-admin`, разделы);
что живёт без панели — библиотека без префикса. SEO — один пакет `module-seo`: рендеру `<head>`
без раздела рисовать нечего (шов внутри: `WebxUi\Seo\Rendering\` и `WebxUi\Seo\Panel\`).
**Таблицы:** каркас (`module-admin`, `module-auth`, `module-settings`) — `cms_*`, раздел — под своим
именем (`blog_*`, `catalog_*`; связка категорий — `<x>_category_<x>`), библиотека — под своим
(`routes`, `locales`, `mcp_*`). Идентификатор модуля — то, чем он называется в панели:
администраторы — `admins`, `users` оставлено пользователям сайта.

**Принципы:** компоненты знают только CSS-переменные; компоненты не ходят в API (данные —
пропсами, наружу — события); таблица принимает `->paginate()` Laravel как есть; `vue` — в
`peerDependencies`; **в публичное репо не попадает ничего специфичного для конкретных сайтов и
клиентов**.

## 2. Где что лежит

```
packages/{tokens,core,schema,module-*}   # npm; компонент: components/<Name>/{Name.vue,types.ts,Name.test.ts,index.ts}
php/packages/<name>/                    # composer-пакет: src, tests, README, AGENTS.md
php/site/                               # скелет webx-ui/site: create-project → php artisan webx:setup
apps/docs/                              # VitePress: components/*.md + demos/*.vue + guide/*.md
apps/playground/                        # песочница; /panel/ — настоящая панель на фикстурах
docs/architecture/                      # спеки и справочники, оглавление — README.md
docs/pitfalls/                          # ловушки по темам
scripts/                                # link-panel, starter-site, sync-php-version, …
```

- **Версии здесь не пишем** — сверяй по `package.json` пакетов, `php/package.json`,
  `npm view @webx-ui/core version`, `composer show webx-ui/module-admin`.
- Компоненты и их статус — только `apps/docs/guide/roadmap.md`.
- Спеки — `docs/architecture/README.md`; ключевые: экраны (`WEBX_UI_SCREENS.md`, читать до правки
  любого экрана панели), адреса сайта (`WEBX_UI_ROUTING.md`: любой контентный модуль получает адрес
  там), релиз (`WEBX_UI_PHP_RELEASE.md`), производительность и кеш (`WEBX_UI_PERFORMANCE.md`,
  `WEBX_UI_CACHE.md`), темы (`WEBX_UI_THEMES.md`), виджеты и формы сайта
  (`WEBX_UI_WIDGETS.md`), панель на сайте (`WEBX_UI_SITE_BAR.md`).
- «Диалоги из кода» (`openModal`, `confirm`, …) — `apps/docs/guide/modals.md`; тосты — `useToast`.

## 3. Опасно с первой минуты

- **Визуальное проверять в браузере, а не в jsdom** — jsdom не считает layout. Порядок: замер
  (`getBoundingClientRect`, `getComputedStyle`, `scrollWidth` vs `clientWidth`) → правка → повторный
  замер. Настоящая причина почти всегда не та, на которую похоже.
- **`rm -rf` внутри `vendor/webx-ui/*` сносит исходники монорепы**: path-репозиторий кладёт туда
  симлинк. Перед правкой — `ls -la vendor/webx-ui`; сайт с симлинками сносить только после
  `find <сайт> -type l -delete`.
- **`pnpm` из worktree сносит `node_modules` основного чекаута**, если там симлинк. Из worktree —
  бинарники напрямую (`npx vite build`, `npx vue-tsc …`). Лечится `pnpm install --frozen-lockfile`
  в основном чекауте.
- **Bash-инструмент съедает обратные слэши**, даже в закавыченном heredoc'е. Файлы со слэшами
  (psr-4, regex) и большие `.vue` — инструментом Write, точечные правки — скриптом на php/node.
- **Не-ASCII в аргументе команды приезжает вопросительными знаками.** Русский текст для `curl` и
  прочего — в файл и `--data-binary @файл`.

## 4. Ловушки по темам

Симптом, причина, лечение, проверка. Прочитать **до** работы в области: почти каждая выглядит как
баг в твоём коде.

| Файл                                       | Когда читать                                                                                 |
| ------------------------------------------ | -------------------------------------------------------------------------------------------- |
| `docs/pitfalls/layout-and-browser.md`      | CSS и раскладка, проверка в браузере, жесты, фреймы, таблицы                                 |
| `docs/pitfalls/vue-and-tests.md`           | пропсы и обёртки, фрагменты, Reka-слои, Tiptap/CodeMirror/датапикер, vitest, экраны панели   |
| `docs/pitfalls/laravel-and-php.md`         | модели, nested-set, миграции, Blade, middleware, Testbench, PHPStan, `webx:setup`, MCP       |
| `docs/pitfalls/packages-and-demo-sites.md` | сайт на пакетах из монорепы, live-сайты, worktree, гейт и smoke локально, Docker, плейграунд |
| `docs/pitfalls/release-and-ci.md`          | очередь мержа, публикация в npm и Packagist, первая версия нового пакета, канал `next`       |

Новую ловушку, на которую ушло время, дописывать в файл её темы тем же коммитом: симптом первым,
без дат и номеров PR. Починено так, что в яму не попасть, — удалить пункт.

## 5. Правила кода

- **Только существующие токены.** Отступы — 0 2 4 6 8 10 12 14 16 18 24 32 40 48 64 96 128
  (`--wx-space-20` нет); радиусы — none xs control sm md lg full. Несуществующий `var(--wx-…)` молчит.
  Перед гейтом: `grep -rho "var(--wx-[a-z0-9-]*)" <src> | sort -u` против
  `packages/tokens/dist/tokens.css`. Поверхность всплывающего — `--wx-bg-surface` (`--wx-bg-overlay`
  — затемнение за диалогом), слой поверх страницы — `--wx-z-index-overlay`.
- **Контейнерные запросы вместо медиа-запросов**: решает ширина панели, а не окна. Поведение —
  `useElementWidth`.
- **Комментарии, тексты и слова интерфейса — английские**; комментарий объясняет «почему».
- Классы — БЭМ с префиксом `wx-`, состояния — `is-*`.
- Компонент = `.vue` + `types.ts` + тест + страница доков с живым демо + экспорт из
  `components/index.ts` (плагин подхватывает всё, что называется `Wx*`).
- **Слова компонента ядра — пропсы, а не словарь**: английские дефолты, переводит тот, кто открывает.
- **`localized` умеет сам компонент ядра** (как `WxInput`, `WxTextarea`); обёртка в `module-admin`
  только пробрасывает проп.
- **Дату в панели рисует `WxDate` / `useDates()`** из `module-admin`, не `toLocaleString()`. Слов у
  даты три (`dates.today`, `dates.yesterday`, `dates.never`) — копий не заводить.
- **Строка с `:count` ломается на единице**: счёт в конец («Статей: :count»), либо строка на каждую
  форму CLDR и `pluralForm(count, locale)` из `module-admin`.
- **Выравнивание и подложки проверять в тёмной теме** — светлая прячет края тинтов.
- **Новый composer-пакет прописать в `php/` четыре раза:** `require`, `autoload-dev.psr-4`,
  `phpunit.xml.dist`, `phpstan.neon.dist` (без третьего — «Class … Tests\TestCase not found»).
  Версию в path-репозитории дописывает `node scripts/sync-php-version.mjs`.
- **Новый раздел панели — ещё четыре молчащих места:** `Setup\Catalogue` в `module-admin`;
  `extra.webx.npm` и `extra.webx.panel` в composer.json пакета; список модулей в
  `apps/playground/src/panel/main.ts`; `scripts/packages.mjs` в репозитории демо-сайта.
- **Пятое — панель на сайте, оно не молчит:** раздел с адресами (`RouteType`) в том же коммите
  регистрирует каждый тип в `SiteBar::entity()`, иначе падает `SiteBarCoverageTest`. Всё для панели
  на сайте — через реестр и `siteBarTabs`, не в Blade (гайд `apps/docs/guide/site-bar-modules.md`).
- **`AGENTS.md` пакета** — формат `docs/architecture/WEBX_UI_AGENT_DOCS.md`, ≤150 строк, держит
  `AgentDocsCoverageTest`.

## 6. Процесс

- **Начинать с `gh pr list`**: ветка с незакрытым PR — незаконченный разговор, а не мусор.
- **Ветка дня.** Работа идёт в `hot/YYYY-MM-DD` (дата дня) от свежего `origin/main`: `git fetch`,
  ветка от `origin/main`. Ветка на сегодня уже есть — работать в ней; если `main` ушёл вперёд
  (вышел релиз) — подтянуть его в ветку, чтобы live-сайты не отставали. PR, CI и релиз — раз в
  конце дня, а не после каждой задачи.
- **Пушить только в remote `claude`, никогда в `origin` и никогда в `main`.** После мержа:
  `git fetch claude && git merge --ff-only claude/main`.
- **Гейт перед пушем** — то же, что CI (перед ним погасить dev-серверы, он упирается в память):
  ```
  pnpm build && pnpm typecheck && pnpm lint && npx prettier --check . && pnpm test && pnpm docs:build
  ```
  `pnpm build` — до typecheck (доки резолвят `@webx-ui/core` из `dist`); typecheck только
  рекурсивный. Один тест — из корня: `npx vitest run <файл> --pool=forks --poolOptions.forks.singleFork`.
  PHP-половина — из `php/`: `composer lint && composer analyse && composer test`.
- **Чек-лист производительности перед сдачей** — если тронуты публичная страница, список или форма
  панели, MCP-список или кеш (правила — `WEBX_UI_PERFORMANCE.md`, гайд `guide/performance.md`):
  - **N+1** — связи, которые трогает шаблон строки (`routes`, обложка, категории), грузятся
    запросом на список; `assertNoRepeatedQueries` на тёплом списке зелёный;
  - **кеш** — данные каждой страницы в `CacheGroups::remember` с группами; модели в `bumpOn`
    своей группы; путь записи без события (массовый `update`, импорт, реордер, сырой SQL) — `bump()`;
  - **индексы** — под новые `WHERE`/`ORDER BY`, проверены `EXPLAIN` на MariaDB, миграция
    откатывается;
  - **бюджет** — `PerformanceTest` модуля покрывает новую страницу и список, тест свежести — каждую
    группу; поднятый бюджет — с причиной в тесте.
- **`main` закрыт ruleset'ом с очередью мержа.** Нужен зелёный чек `Lint, typecheck, test, build`
  (агрегатор джобов `ci.yml`). В очередь — мутацией, `gh pr merge` не ставит:
  `gh api graphql -f query='mutation{enqueuePullRequest(input:{pullRequestId:"<id>"}){mergeQueueEntry{state position}}}'`,
  `<id>` — `gh pr view <N> --json id -q .id`; ответ `QUEUED`.
- **Changeset** на каждый PR, меняющий публикуемый пакет; docs-only — без него. Правка в
  `php/packages/*` — changeset на приватный `@webx-ui/php` (общая версия composer-пакетов).
- **Релиз, канал `next`, зеркала для Packagist** — `docs/architecture/WEBX_UI_PHP_RELEASE.md`.
  Проверять `npm view`, а не веру. **Первую версию нового npm-пакета публикует человек**
  (`docs/pitfalls/release-and-ci.md`).
- Детали для людей — `CONTRIBUTING.md`.

## 7. Проверка вживую

- **Доки и плейграунд** — `pnpm docs:dev -- --host --port 5173` и `pnpm dev -- --host --port 5174`;
  адрес — `https://ws-<порт>.$DEV_DOMAIN`. Долгоживущий сервер — в отдельном окне tmux. Демо в
  `apps/docs/components/demos/` — ровно то, что увидит читатель.
- **Панель без Laravel** — `/panel/` плейграунда: настоящая панель на фикстурах
  (`apps/playground/server/panel/`).
- **Live-сайты платформы** — пакеты `webx-ui/*` там — ссылки на эту монорепу, правка PHP/Blade/lang
  видна сразу; `webx status`, `webx sync <сайт>` (после правки `composer.json`, миграций, ассетов),
  `webx logs <сайт>`. Подробности и стартовый сайт (`scripts/starter-site.sh`) —
  `docs/pitfalls/packages-and-demo-sites.md`.
- **Хомлаб** `https://webx-cms.alexx.group` — на опубликованных пакетах; вёрстку и жесты проверяем
  там на настоящих устройствах. После релиза — обновить его (`composer update "webx-ui/*"` в его
  репозитории): ночной прогон свежий релиз не привозит.

## 8. Что дальше

Порядок примерный; что выпущено — реестр пакетов и разделы «Выпуск» спек.

1. Ядро: остаток волны 3, Gantt (своим), CMS-блок Markdown — `apps/docs/guide/roadmap.md`.
2. Каталог: хвосты волны A и волна B — §10 `WEBX_UI_CATALOG.md`.
3. Документация для агентов: AG5 — `WEBX_UI_AGENT_DOCS.md`.
4. Аудит сайта: открытое — §12 `WEBX_UI_MODULE_AUDIT.md`.
5. Формы сайта дальше (вход, кабинет, заказ) — §17–§18 `WEBX_UI_WIDGETS.md`.
6. Темы: каталог тем и сайты по подписке — §21 `WEBX_UI_THEMES.md`.
7. Экраны: `admins.form` и форма правила SEO пока не переводятся; `@webx-ui/adapter-laravel` — после.
8. Многосайтовость — `WEBX_UI_MULTISITE.md`, не начата.

Открытое по модулям (проверки на телефоне, мелкие хвосты) — в разделах «Открыто» их спек.

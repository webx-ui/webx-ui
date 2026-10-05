# WebX UI — дизайн-система на Vue 3, бриф для Claude Code

Названия: npm scope `@webx-ui`, GitHub-организация `webx-ui`, репозиторий `webx-ui/webx-ui`,
префикс компонентов `Wx` (в шаблонах `<wx-button>`), CSS-классы `.wx-*`, CSS-переменные `--wx-*`.

## 1. Что это

Публичная open-source дизайн-система на Vue 3 — основа для админок (CMS) нескольких сайтов на
Laravel. Библиотека публикуется в npm, админки — отдельные приватные проекты, которые её
подключают.

Пакеты:

1. **`@webx-ui/tokens`** — цвета, отступы, типографика, радиусы, тени. JSON → CSS-переменные,
   светлая и тёмная темы.
2. **`@webx-ui/core`** — компоненты. Список компонентов брали из Element Plus как чек-лист, но
   реализуем сами. Сложная логика без стиля (Dialog, Dropdown, Popover, Tooltip, Combobox, Tabs) —
   из **Reka UI**; drag-and-drop — `vue-draggable-plus`, датапикер — `@vuepic/vue-datepicker`,
   rich-text — Tiptap.
3. **`@webx-ui/schema`** — экраны как JSON: узел `{ id, type, name, label, props, children, visible, can }`,
   патчи (add/remove/replace/move/set), реестр типов на компонентах ядра, `WxScreenRenderer`,
   JSON-схемы. Гайд — `apps/docs/guide/screens.md`, спецификация — §1 ниже.
4. **`@webx-ui/adapter-laravel`** — адаптер под Laravel (`LengthAwarePaginator`, ошибки 422,
   query-параметры сортировки и фильтров). Не начинался.

Вторая половина системы — **composer-пакеты** в `php/packages/*` (вендор `webx-ui/*`, namespace
`WebxUi`), они же ядро админки на Laravel. **Имена пакетов на обеих половинах:** всё, из чего
состоит панель, — с префиксом `module-`: каркас `module-admin` и разделы `module-auth`,
`module-media`, `module-settings`, `module-seo`; библиотеки, которые живут и без панели, — без него: `nested-set`, `localization`,
`mcp`, `routing`. **Таблицы — по тому же правилу:** каркас панели (`module-admin`, `module-auth`,
`module-settings`) — `cms_*`, раздел — под своим именем (`blog_*`, `faq_*`, `catalog_*`; связка
категорий — `<x>_category_<x>`), библиотека — под своим (`routes`, `locales`, `mcp_*`). Идентификатор модуля — то, чем он называется в панели, а не то, что он хранит:
администраторы — `admins`, а `users` оставлено пользователям сайта, которые станут отдельным
разделом. По тому же правилу SEO собран **одним** пакетом, а не библиотекой плюс разделом:
рендеру `<head>` без раздела рисовать нечего — ни правил, ни дефолтов, ни Organization, — то
есть библиотекой он по нашему же критерию не является. Шов между половинами держится внутри
(`WebxUi\Seo\Rendering\` и `WebxUi\Seo\Panel\`, источники через контракт). Реестр и планы — в
`docs/architecture/WEBX_UI_COMPOSER_PACKAGES.md`, конвейер публикации на Packagist — в
`docs/architecture/WEBX_UI_PHP_RELEASE.md`. **Экраны как описание** — дерево узлов в JSON,
патчи от проекта, реестр типов на обеих половинах — согласованная спецификация в
`docs/architecture/WEBX_UI_SCREENS.md`; читать до того, как трогать любой экран панели.
**Адреса сайта** — общий плоский реестр в `routing` (`routes`, форматтеры, алиасы, резолвер за
`Route::fallback()`): спецификация — `docs/architecture/WEBX_UI_ROUTING.md`, гайд —
`apps/docs/guide/routing.md`. Любой контентный модуль получает адрес там, а не у себя; `module-seo`
показывает алиасы вкладкой «Автоматические» рядом с ручными редиректами.

Ключевые принципы:

- Компоненты знают только CSS-переменные, никаких хардкод-цветов.
- Компоненты не ходят в API: данные приходят пропсами, наружу — события.
- Таблица принимает формат Laravel `->paginate()` как есть.
- **В публичное репо не попадает ничего специфичного для конкретных сайтов/клиентов.**
- `vue` — в `peerDependencies`.

## 2. Состояние

Актуально на 29.09.2026: `@webx-ui/core@0.34.1`, `@webx-ui/tokens@0.4.0`, `@webx-ui/schema@0.6.4`
и двадцать `@webx-ui/module-*` опубликованы в npm, composer-половина — одной версией `v0.50.0`
на Packagist, сайт документации живёт на https://webx-ui.github.io/webx-ui/. Версии тут устаревают
первыми — считать их подсказкой, а не фактом: точный ответ даёт `npm view @webx-ui/core version` и
`composer show webx-ui/module-admin`.

- **Компоненты ядра** — волны 1 и 2 закрыты, волна 3 — частично. Сверх списка Element Plus:
  `WxListDetail`, `WxKanban`, `WxEntityCard`, `WxFileCard`, `WxActions`/`WxAction`,
  `WxImageEditor`, `WxSelectionArea` (+ директива `v-wx-select`), `WxSortableList`, `WxRichText`,
  `WxCodeEditor` (CodeMirror 6), `WxThemeSwitch`.
- **Не компоненты:** `useToast`/`toast` — очередь уведомлений, вызываемая откуда угодно;
  `openModal` / `createModal` / `useModal` / `confirm` / `openImageEditor` — «диалоги из
  кода»: любой компонент монтируется вне дерева приложения и возвращает промис с ответом
  (`apps/docs/guide/modals.md`).
- **Панель** — 20 разделов (`module-*`) и 4 библиотеки (`nested-set`, `localization`, `mcp`,
  `routing`), все выпущены. Что есть, зачем и с какой версии — реестр
  `docs/architecture/WEBX_UI_COMPOSER_PACKAGES.md`; каждая спека — в
  `docs/architecture/README.md`.
- **Единственный источник правды по компонентам — `apps/docs/guide/roadmap.md`.** Там статус
  каждого компонента, что ещё открыто, что выброшено и почему. Здесь список не дублируем.
- **Панель живёт в двух настоящих сайтах, и оба держим свежими:** `webx-cms.local` рядом (на
  пакетах из этой монорепы) и `https://webx-cms.alexx.group` на хомлабе (на опубликованных, деплой
  из Gitea при каждом пуше и ночью). Вёрстку и жесты проверяем там — на настоящих устройствах.
  Бриф того репозитория — в его `CLAUDE.md`; переключает сайт между монорепой и реестром
  `scripts/link-panel.sh` здесь.

## 3. Структура репозитория

```
packages/
├── tokens/src/tokens.json        # источник правды по стилю; scripts/generate.mjs → dist/tokens.css
├── core/src/
│   ├── components/<Name>/        # Name.vue + types.ts + Name.test.ts + index.ts
│   ├── composables/              # useToast, useModal, confirm, useFormField, useElementWidth, …
│   ├── internal/                 # то, что не экспортируется наружу (nodes.ts, ConfirmDialog.vue)
│   ├── styles/                   # база и подключение токенов
│   ├── plugin.ts                 # app.use(WebxUI): компоненты + директива + connectModals
│   └── index.ts                  # именованные экспорты
├── schema/src/                   # экраны как JSON: типы, патчи, WxScreenRenderer
└── module-*/                      # npm-половина разделов панели (module-admin — каркас)
apps/
├── docs/                         # VitePress: components/*.md + components/demos/*.vue + guide/*
└── playground/                   # Vite-песочница, две страницы:
    ├── index.html                # придуманные экраны на одном ядре
    ├── panel.html                # /panel/* — настоящая панель: createAdmin + модули
    └── server/panel/             # её сервер: /api/cms/* из фикстур в памяти
php/
├── composer.json                 # dev-корень: тулинг + path-репозитории пакетов
├── package.json                  # приватный @webx-ui/php: общая версия composer-пакетов
├── packages/<name>/              # composer.json + src + tests + README + LICENSE
└── site/                         # скелет webx-ui/site: composer create-project → php artisan webx:setup
docs/
├── architecture/                 # спеки и справочники; оглавление — README.md
└── pitfalls/                     # ловушки по темам (см. §4)
```

## 4. Правила и ловушки

### Опасно с первой минуты

- **Визуальное проверять в браузере, а не в jsdom.** jsdom не считает layout, поэтому ни один баг
  вёрстки он не поймает. Порядок такой: `preview_start` доков → замер (`getBoundingClientRect`,
  `getComputedStyle`, `scrollWidth` vs `clientWidth`) → правка → повторный замер. Почти каждый раз
  настоящая причина оказывалась не той, на которую было похоже.
- **`rm -rf` внутри `vendor/webx-ui/*` сносит исходники монорепы.** `path`-репозиторий кладёт в
  `vendor` симлинк на чекаут. Перед любой правкой или удалением там — `ls -la vendor/webx-ui`;
  сайт с симлинками сносить только после `find <сайт> -type l -delete`.
- **`pnpm` из worktree сносит `node_modules` основного чекаута**, если там `node_modules` —
  симлинк. Из worktree запускать бинарники напрямую (`npx vite build`, `npx vue-tsc …`), а
  `preview_start` по имени не звать — он запускает pnpm. Лечится `pnpm install
--frozen-lockfile` в основном чекауте.
- **Bash-инструмент съедает обратные слэши, даже в закавыченном heredoc'е.** Файлы со слэшами
  (psr-4 в `composer.json`, regex) и большие `.vue` писать инструментом Write, точечные правки —
  скриптом на php/node, который держит шаблон в файле.
- **Не-ASCII в аргументе команды приезжает вопросительными знаками.** Русский текст для `curl` и
  прочего — в файл (Write) и `--data-binary @файл`.

### Ловушки по темам

Каждая ловушка — симптом, причина, как лечится и как проверить. Прочитать нужный файл **до**
работы в этой области: почти каждая выглядит как баг в твоём коде, а он ни при чём.

| Файл                                       | Когда читать                                                                                                            |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- |
| `docs/pitfalls/layout-and-browser.md`      | правишь CSS или раскладку, проверяешь что-то в панели браузера, жесты, фреймы, таблицы                                  |
| `docs/pitfalls/vue-and-tests.md`           | пропсы и обёртки, фрагменты, Reka-слои, Tiptap/CodeMirror/датапикер, vitest и jsdom, экраны панели                      |
| `docs/pitfalls/laravel-and-php.md`         | модели, nested-set, миграции, Blade, middleware, типы полей, Testbench, PHPStan, `webx:setup`, Passport, MCP, Manticore |
| `docs/pitfalls/packages-and-demo-sites.md` | линкуешь `webx-cms.local` с монорепой, worktree, Git Bash и OSPanel, гейт и smoke локально, Docker, плейграунд          |
| `docs/pitfalls/release-and-ci.md`          | очередь мержа, публикация в npm и Packagist, первая версия нового пакета, демо после релиза                             |

Новую ловушку, на которую ушло время, дописывать в файл её темы тем же коммитом: симптом первым,
без дат и номеров PR. Когда код починен так, что в яму больше не попасть, — удалить пункт или
оставить одну строку «было так, починено в …».

### Правила кода

- **Компоненты знают только существующие токены.** Шкала отступов — 0 2 4 6 8 10 12 14 16 18 24
  32 40 48 64 96 128; `--wx-space-20` не существует. Радиусы: none xs control sm md lg full.
  Несуществующий `var(--wx-…)` молчит — пустое значение, ни рамки, ни фона. Перед гейтом:
  `grep -rho "var(--wx-[a-z0-9-]*)" <src> | sort -u` и сверить с
  `packages/tokens/dist/tokens.css`. Поверхность всего, что всплывает, — `--wx-bg-surface`
  (`--wx-bg-overlay` — это затемнение за диалогом); слой поверх страницы —
  `--wx-z-index-overlay`, а не число из головы.
- **Контейнерные запросы вместо медиа-запросов** (`container-type: inline-size` + `@container`):
  решает ширина панели, а не окна. Где нужна не вёрстка, а поведение, — `useElementWidth`
  (ResizeObserver).
- **Комментарии и тексты — английские**, объясняют «почему», а не пересказывают код. Слова в
  интерфейсе тоже английские.
- Классы — БЭМ с префиксом `wx-`, состояния — `is-*`.
- Каждый компонент: `.vue` + `types.ts` + тест + страница доков с живым демо + экспорт из
  `components/index.ts` (плагин сам подхватывает всё, что называется `Wx*`).
- **Слова компонента ядра — это пропсы, а не словарь.** `WxImageEditor`, `WxFileCard` и прочие
  несут английские дефолты; переводит тот, кто их открывает. Тесты на паритет ключей такую дыру
  не видят — ключа просто нет.
- **Поле с `localized` умеет сам компонент ядра** (как `WxInput` и `WxTextarea`): `LocalePicker`
  наружу не экспортируется, обёртка в `module-admin` только пробрасывает проп.
- **Дату в панели рисует `WxDate` / `useDates()` из `module-admin`**, а не `toLocaleString()`:
  без явной локали формат берётся из браузера. Слов у даты три (`dates.today`,
  `dates.yesterday`, `dates.never`) — своих копий не заводить.
- **Строка с `:count` ломается на единице.** Плюрализации у панели нет: счёт в конец строки
  («Статей: :count») или отдельная строка на единицу.
- **Выравнивание и подложки проверять в тёмной теме.** Светлая прячет края тинтов; замер —
  левые кромки `getBoundingClientRect` у соседних блоков.
- **Смотреть — на доках и плейграунде.** Серверы в `.claude/launch.json`: `docs` (5173),
  `playground` (5174), `docs-alt` (5175), `docs-alt2` (5176). Демо в
  `apps/docs/components/demos/` — ровно то, что увидит читатель. Экран панели — на
  `http://localhost:5174/panel/`: настоящая панель на фикстурах, без Laravel (подробности —
  `docs/pitfalls/packages-and-demo-sites.md`).
- **Новый composer-пакет прописать в `php/` четыре раза:** `require`, `autoload-dev.psr-4` для
  namespace тестов, `phpunit.xml.dist` и `phpstan.neon.dist`. Без третьего PHPUnit падает
  «Class … Tests\TestCase not found» — похоже на опечатку в тесте. Версию в path-репозитории
  дописывает `node scripts/sync-php-version.mjs`. Сайт, слинкованный с монорепой, держит свою
  карту версий (`repositories[].options.versions`).
- **Новый раздел панели регистрируется ещё в четырёх местах, и все четыре молчат:**
  `Setup\Catalogue` в `module-admin` (иначе `webx:setup` его не предложит); `extra.webx.npm` и
  `extra.webx.panel` в composer.json пакета (иначе `webx:panel --sync` не добавит ни
  зависимости, ни импорта); список модулей в `apps/playground/src/panel/main.ts`; и
  `scripts/packages.mjs` в самом `webx-cms.local`.

## 5. Процесс

- **Начинать с `gh pr list`:** работа могла остаться в открытом PR, а не в `main`. Ветка с
  незакрытым PR — это незаконченный разговор, а не мусор.
- **Пушить в remote `claude`, никогда в `origin`.** PR — через `gh`, он не в PATH:
  `"C:\Program Files\GitHub CLI\gh.exe"`. После мержа локальный `main` двигать
  `git fetch claude && git merge --ff-only claude/main`.
- **Гейт перед пушем** — ровно то, что делает CI:
  ```
  pnpm build && pnpm typecheck && pnpm lint && npx prettier --check . && pnpm test && pnpm docs:build
  ```
  `pnpm typecheck` рекурсивный, по одному пакету не считается. `pnpm build` обязателен **до**
  typecheck доков: `apps/docs` резолвит `@webx-ui/core` из `dist`. Гейт упирается в память —
  перед ним погасить dev-серверы; отдельный тест — из корня репозитория:
  `npx vitest run <файл> --pool=forks --poolOptions.forks.singleFork`. Гейт php-половины —
  `composer lint && composer analyse && composer test` из `php/` интерпретатором
  `C:\Work\OSPanel\modules\PHP-8.4\php.exe` (composer не в PATH — phar в скретчпад).
- **`main` закрыт ruleset'ом с очередью мержа.** Апрув не нужен, нужен зелёный чек
  `Lint, typecheck, test, build` (агрегатор параллельных джобов `ci.yml` — упавший смотреть в
  них). Когда собственный CI PR зелёный, ставить в очередь мутацией — **`gh pr merge` в очередь
  не ставит**:
  `gh api graphql -f query='mutation{enqueuePullRequest(input:{pullRequestId:"<id>"}){mergeQueueEntry{state position}}}'`,
  где `<id>` — `gh pr view <N> --json id -q .id`; ответ `QUEUED`, мерж через ~3 минуты.
- **Changeset** на каждый PR, который меняет публикуемый пакет; docs-only — без него. Правка в
  `php/packages/*` — changeset на приватный `@webx-ui/php`: он носит общую версию всех
  composer-пакетов.
- **Релиз:** мерж PR с changeset'ами → GitHub App открывает «chore: version packages» → мерж
  этого PR публикует npm-пакеты, вешает `php-v<версия>` и вызывает `php-split.yml`, который
  зеркалит каждый composer-пакет в `webx-ui/<имя>` с тегом `v<версия>` — Packagist подхватывает
  вебхуком. Проверять `npm view`, а не веру. Подробности — `docs/architecture/WEBX_UI_PHP_RELEASE.md`.
- **Первую версию нового npm-пакета публикует человек** — из worktree ветки
  `changeset-release/main`, потом Trusted Publishing на npmjs.com. Почему именно так —
  `docs/pitfalls/release-and-ci.md`.
- **Показать ветку на хомлабе без релиза — канал `next`:** `gh workflow run release.yml --ref
<ветка>` публикует снапшот под dist-tag `next` и зеркалит php-пакеты веткой `next`; на сайте —
  `node scripts/packages.mjs next`, после настоящего релиза — `registry`. Подробности —
  `docs/architecture/WEBX_UI_RELEASE_SPEED.md`.
- **После релиза — обновить оба демо.** Локальное: `scripts/link-panel.sh` и
  `composer update "webx-ui/*"` в сайте. Хомлаб: в его репозитории — режим `registry` того же
  переключателя, `composer update "webx-ui/*"`, коммит и пуш; ночной прогон свежий релиз не
  привозит.
- Детали для людей — в `CONTRIBUTING.md`.

## 6. Что дальше

Выпущенное — в реестре `docs/architecture/WEBX_UI_COMPOSER_PACKAGES.md` и в разделах «Выпуск»
спек; здесь только то, что впереди. Порядок не догма, но примерно такой:

1. Остаток волны 3 ядра: Carousel, Anchor, Splitter, Watermark, Marquee.
2. **Gantt** — решено делать своим, не начинали (обоснование в roadmap).
3. CMS-блоки: из списка открыт только Markdown.
4. **Каталог для магазинов** — волна A выпущена (v0.56.0): ядро, свойства, справочники, видео,
   обмен, Manticore, посадочные. Что выпущено и что не начато — таблица §10
   `docs/architecture/WEBX_UI_CATALOG.md`, гайд — `apps/docs/guide/catalog.md`. Хвосты
   волны A — v0.57.0; волна B не начата.
5. **Документация для агентов** — после каталога. `AGENTS.md` в каждом composer-пакете и корневой
   файл сайта, который собирают `webx:setup` / `webx:panel --sync`; правила контента сайта — в
   базе, ресурсом MCP. Цель — 100% охвата: человек без опыта с агентом разворачивает сайт в Docker
   и правит стили. Охват проверяет тест.
6. **Аудит сайта — `module-audit`** — выпущен (A1–A5, v0.58.0), ставится по умолчанию. Свой
   обход и проверки без внешних сервисов, снимок каждой страницы, исправления кнопкой от модулей.
   Открытое — §12 `docs/architecture/WEBX_UI_MODULE_AUDIT.md`.
7. Экраны как описание: `admins.form` и форма правила SEO на экраны пока не переводятся
   (решение 14.09.2026); `@webx-ui/adapter-laravel` — после.
8. **Многосайтовость** — v3: одна установка, много доменов, одна панель, у каждого домена свой
   дизайн. Спроектирована, не начиналась: `docs/architecture/WEBX_UI_MULTISITE.md`.

Хвосты, про которые стоит помнить:

- Плейграунд не показывает `SelectionArea` — он живёт только в доках.
- Открытое по модулям — в разделах «Открыто» их спек (проверки на телефоне, валидаторы разметки,
  мелкие хвосты).

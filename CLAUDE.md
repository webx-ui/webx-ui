# \# WebX UI — дизайн-система на Vue 3, бриф для Claude Code

#

# Названия: npm scope `@webx-ui`, GitHub-организация `webx-ui`, репозиторий `webx-ui/webx-ui`, префикс компонентов `Wx` (в шаблонах `<wx-button>`), CSS-классы `.wx-\*`, CSS-переменные `--wx-\*`.

#

# \## 1. Контекст и цель

#

# Строим публичную open-source дизайн-систему на Vue 3, которая станет основой для админок (CMS) нескольких сайтов на Laravel. Библиотека публикуется в npm, админки — отдельные приватные проекты, которые её подключают.

#

# Общая архитектура (на будущее, реализуется поэтапно):

#

# 1\. \*\*`@webx-ui/tokens`\*\* — стиль: цвета, отступы, типографика, радиусы, тени. JSON → CSS-переменные, светлая/тёмная тема.

# 2\. \*\*`@webx-ui/core`\*\* — UI-компоненты админки, стилизованные только через токены. Список компонентов берём из Element Plus как чек-лист, но реализуем сами (без обёрток над Element Plus). Сложную логику без стиля (Dialog, Dropdown, Popover, Tooltip, Combobox, Toast) берём из headless-библиотеки \*\*Reka UI\*\*; дерево, drag-and-drop, датапикер, rich-text — из сторонних библиотек.

# 3\. \*\*`@webx-ui/schema`\*\* — рендерер интерфейса админки из JSON: узел `{ type, props, children, on, visible }`, реестр компонентов, реестр действий, интерфейс адаптера данных. Бэкенд-агностичен.

# 4\. \*\*`@webx-ui/adapter-laravel`\*\* — адаптер данных под Laravel (LengthAwarePaginator, ошибки валидации 422, query-параметры сортировки/фильтров).

# 5\. Позже: CMS-компоненты (Repeater, MediaLibrary, RichText, BlockPicker, Sortable-список блоков) и редактор компонентов в админке (поля через JSON + Blade-шаблон + CSS → Laravel генерирует файлы).

#

# Ключевые принципы:

# \- Компоненты знают только CSS-переменные, никаких хардкод-цветов.

# \- Компоненты не ходят в API; данные приходят через пропсы/адаптер, наружу — события.

# \- Таблица принимает формат Laravel `->paginate()` как есть (`data, current\_page, last\_page, per\_page, total, from, to`).

# \- В публичное репо не попадает ничего специфичного для конкретных сайтов/клиентов.

# \- `vue` — в `peerDependencies`.

#

# \## 2. Задачи текущего этапа

#

# Только это, остальное потом:

#

# 1\. Настроить GitHub-репозиторий и монорепо.

# 2\. Настроить CI и публикацию пакетов в npm.

# 3\. Настроить сайт документации с деплоем на GitHub Pages.

# 4\. Заложить структуру пакета `core` и список компонентов (реализовать 2–3 для проверки конвейера, остальные — заглушки/план).

#

# \## 3. Структура репозитория

#

# ```

# webx-ui/

# ├── .github/

# │ └── workflows/

# │ ├── ci.yml # lint, typecheck, test, build на PR и push

# │ ├── release.yml # Changesets → публикация в npm

# │ └── docs.yml # сборка VitePress → GitHub Pages

# ├── .changeset/

# ├── packages/

# │ ├── tokens/ # @webx-ui/tokens

# │ ├── core/ # @webx-ui/core

# │ └── schema/ # @webx-ui/schema (пока пустой каркас)

# ├── apps/

# │ ├── docs/ # VitePress, не публикуется

# │ └── playground/ # Vite-песочница, не публикуется

# ├── pnpm-workspace.yaml

# ├── package.json

# ├── tsconfig.base.json

# ├── eslint.config.js

# ├── LICENSE # MIT

# ├── README.md

# └── CONTRIBUTING.md

# ```

#

# Стек: pnpm workspaces, Vite (library mode), TypeScript, `vite-plugin-dts`, Vitest + Vue Test Utils, ESLint + Prettier, Changesets, VitePress.

#

# \## 4. Настройка GitHub

#

# \- Публичный репозиторий, лицензия MIT.

# \- Ветка `main` защищена: обязательный CI, merge через PR.

# \- Публикация в npm через \*\*npm Trusted Publishing (OIDC)\*\* из GitHub Actions — без хранения токена в секретах. Scope `@webx-ui` завести на npmjs.com заранее.

# \- Changesets: PR «Version packages» создаётся ботом, публикация при мерже.

# \- GitHub Pages: источник GitHub Actions уже включён в настройках репозитория. Workflow `docs.yml` собирает `apps/docs` и деплоит. Репозиторий `webx-ui/webx-ui` совпадает с именем организации, поэтому сайт живёт на `https://webx-ui.github.io/` и в VitePress `base: "/"`.

# \- Организация `webx-ui` на npm уже создана (scope зарезервирован), 2FA включена. Первую публикацию `0.0.1` каждого пакета владелец сделает вручную (`pnpm publish --access public`), после чего на npm настраивается Trusted Publishing (GitHub Actions, workflow `release.yml`) и дальнейшие релизы идут без токенов.

# \- Первым делом опубликовать пустые пакеты версии `0.0.1`, чтобы проверить весь конвейер end-to-end.

#

# \## 5. Пакет `tokens` (минимум для этого этапа)

#

# \- `src/tokens.json` — примитивы (палитра, шкала отступов, размеры шрифтов, радиусы, тени, z-index).

# \- Семантический слой: `color-primary`, `color-danger`, `bg-surface`, `bg-muted`, `text-default`, `text-muted`, `border-default` и т.д.

# \- Скрипт генерации `dist/tokens.css` с переменными вида `--wx-color-primary`, плюс `\[data-theme="dark"]`.

# \- Экспорт также как TS-объект для использования в коде.

#

# Полная палитра не нужна сейчас — достаточно, чтобы 2–3 компонента были стилизованы через переменные.

#

# \## 6. Структура пакета `core`

#

# ```

# packages/core/

# ├── src/

# │ ├── components/

# │ │ └── Button/

# │ │ ├── Button.vue

# │ │ ├── Button.test.ts

# │ │ ├── types.ts

# │ │ └── index.ts

# │ ├── composables/ # useToast, useModal, ...

# │ ├── styles/ # базовые стили, reset, подключение токенов

# │ ├── plugin.ts # app.use(UI) — регистрация всех компонентов

# │ └── index.ts # именованные экспорты для tree-shaking

# ├── vite.config.ts

# ├── package.json # files: \["dist"], peerDependencies: vue

# └── tsconfig.json

# ```

#

# Соглашения:

# \- Префикс компонентов `Wx`: экспорт и файлы в PascalCase (`WxButton`, `WxTable`), в шаблонах используем kebab-case (`<wx-button>`, `<wx-table>`), CSS-классы БЭМ с префиксом (`.wx-button`, `.wx-button--primary`). В документации и примерах всегда показывать kebab-case запись.

# \- `<script setup lang="ts">`, `defineProps` с типами, `defineEmits`, `defineModel` для `v-model`.

# \- Стили — scoped CSS или CSS-модули, только через CSS-переменные токенов.

# \- Каждый компонент: `.vue` + `types.ts` + тест + страница в `apps/docs`.

# \- Экспорт: и по одному компоненту, и через плагин.

#

# \## 7. Список компонентов (по Element Plus)

#

# Все реализуем сами; в скобках — на что опереться для логики.

#

# \*\*Волна 1 — базовые (делаем первыми):\*\*

# Button, ButtonGroup, Icon, Input, Textarea, InputNumber, Select (Reka Combobox), Checkbox, CheckboxGroup, Radio, RadioGroup, Switch, Form, FormItem, Card, Tag, Badge, Alert, Divider, Layout (Container, Header, Aside, Main, Footer, Row, Col), Space, Tabs (Reka), Menu / Sidebar, Breadcrumb, Link, Text, Scrollbar.

#

# \*\*Волна 2 — данные и оверлеи:\*\*

# Table (под Laravel `Paginated`), Pagination, Dialog (Reka), Drawer (Reka), Dropdown (Reka), Tooltip (Reka), Popover (Reka), Popconfirm, Message, Notification / Toast (Reka), Loading, Skeleton, Empty, Result, Progress, Descriptions, Avatar, Image, Upload (Uppy / FilePond), DatePicker, TimePicker, DateTimePicker (`@vuepic/vue-datepicker`), Steps, Collapse, Segmented, Statistic, Backtop, Affix.

#

# \*\*Волна 3 — по мере надобности:\*\*

# Tree, TreeSelect (`he-tree-vue` / Reka Tree), Cascader, Transfer, Autocomplete, ColorPicker, Slider, Rate, Timeline, Calendar, Carousel, Mention, Anchor, Splitter, Watermark, Tour, Marquee.

#

# \*\*Нет в Element Plus, нужно для CMS (позже):\*\*

# MediaLibrary / Gallery, Repeater, RichText (Tiptap), Markdown, LinkPicker, BlockPicker, SortableList (`vue-draggable-plus`), SchemaRenderer (в пакете `schema`).

#

# На этом этапе реализовать полностью: \*\*Button, Input, Card\*\* — с тестами и документацией. Для остальных из волны 1 создать папки с `README.md` («planned») или ничего — на усмотрение.

#

# \## 8. Документация (`apps/docs`)

#

# \- VitePress, подключает пакеты через `workspace:\*` — правки в компонентах видны сразу.

# \- Разделы: Введение / Установка, Токены (живые свотчи цветов, шкалы), Компоненты (по одной странице, живые демо + таблица пропсов/событий/слотов), Гайды.

# \- Демо-компоненты писать как `.vue` в `apps/docs/components/demos/` и вставлять в markdown.

#

# \## 9. Definition of Done для этапа

#

# \- \[ ] Репо на GitHub, MIT, README, CONTRIBUTING, защищённый `main`.

# \- \[ ] `pnpm install \&\& pnpm build \&\& pnpm test \&\& pnpm lint` проходят локально и в CI.

# \- \[ ] Пакеты `tokens`, `core`, `schema` опубликованы в npm (версия 0.0.x) через release-workflow.

# \- \[ ] Сайт документации открывается по адресу GitHub Pages.

# \- \[ ] `WxButton`, `WxInput`, `WxCard` реализованы, стилизованы через токены, покрыты тестами, есть страницы в доке.

# \- \[ ] Список компонентов из раздела 7 зафиксирован в документации как roadmap.

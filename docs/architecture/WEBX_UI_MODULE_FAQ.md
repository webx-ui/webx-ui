# `webx-ui/module-faq` и контракт «вставить блоком» — спецификация и план реализации

Статус: спроектирован 24.09.2026, код не начат. Пакеты — `webx-ui/module-faq` (composer) и
`@webx-ui/module-faq` (npm); контракт — в `module-admin` и `module-blocks` на обеих половинах.

FAQ — вопросы и ответы с плоскими категориями. Своей страницы у вопроса нет, у модуля нет ни
одного публичного маршрута: вопросы попадают на сайт **блоком**, поставленным на любую страницу, —
«FAQ по оплате» на странице услуги или «все вопросы с фильтром» на обычной странице `/faq`.

Этот способ попадать на сайт — общий. Team и Reviews из списка 23.09.2026 устроены так же, и
услуги ждут вставки FAQ, отзывов и специалистов (§7 `WEBX_UI_MODULE_SERVICES.md`). Поэтому
спека делится на два этапа, и контракт идёт первым:

| Этап  | Что                                                                        | Где описано |
| ----- | -------------------------------------------------------------------------- | ----------- |
| **1** | Контракт «вставить блоком»: источник записей, тип поля, типы блоков модуля | §3 здесь    |
| **2** | `module-faq` — первый потребитель                                          | §4 здесь    |

Выпуск у двух этапов **один, в самом конце** (§6): все сессии идут на одной ветке, без
промежуточных PR и без ожидания CI между ними.

## 1. Границы

**Внутри этапа 1:** контракт `CollectionSource` и реестр источников в `module-admin`; тип поля
`wx-collection` на обеих половинах (выбор категорий источника, лимит, фильтр, разметка); значение
раскрывается на чтении сайта в список записей; модуль предлагает свои типы блоков, а сайт
ставит их один раз (`BlockOffers` в `module-blocks`); одна разметка schema.org на страницу из
нескольких блоков (`Seo::put()` в `module-seo`); плейграунд рисует такой блок.

**Внутри этапа 2:** вопросы и категории FAQ, порядок, переводы, якоря, источник `faq`, тип блока
«FAQ» с аккордеоном и фильтром, `FAQPage`, экраны панели, API, MCP, демо.

**Снаружи:** Team и Reviews (следующие потребители контракта); выбор отдельных записей руками
(«эти три вопроса»), а не категорий; вставка записей без блоков (директива в Blade сайта); экран
«предложенные типы» в панели «Блоки»; поиск по вопросам на сайте; голосование «помог ли ответ».

## 2. Принятые решения (не переоткрывать)

Решения 1–5 приняты в обсуждении 23.09.2026 (§5 спеки услуг), 6–12 — 24.09.2026.

1. **Категории плоские, «многие ко многим», код общий, таблица своя** — `faq_categories` и
   `faq_category_question` на ядре категорий из `module-admin` (этап 1 спеки услуг).
2. **У категории FAQ нет адреса, SEO и блоков:** `slug` пустой, `CategoryLinkSource` не
   регистрируется. Экран категории — название, видимость и поля проекта.
3. **У вопроса нет своей страницы и нет главной категории:** поле `wx-categories` с `main: false`.
4. **Два порядка, как у услуг:** общий (`position`) и внутри категории (`item_position` в связи);
   перетаскивают в том списке, который видят.
5. **Поля проекта — патч экрана плюс `extra`**, через `ScreenRecord`, как у услуг.
6. **Общей страницы у модуля нет.** «Общая страница FAQ» — обычная страница `module-pages` с
   блоком FAQ в режиме «все категории, с фильтром». Адрес, SEO, меню, карта сайта и перевод слага
   приходят от страницы; у модуля нет маршрутов, которым можно столкнуться с чужими адресами; «не
   нужна общая страница» — значит блок не поставили.
7. **Контракт делается до модуля и сразу общим**, хотя потребитель пока один: Team и Reviews уже
   названы, и форма контракта должна выдержать их, а не только FAQ.
8. **Разметка `FAQPage` — флаг на блоке.** По умолчанию она включена только в режиме «все
   категории» и выключена у блока с выбранными категориями: Google просит не размечать один
   вопрос на нескольких страницах, а «FAQ по оплате» будет стоять на каждой услуге. Редактор
   может включить разметку и там — его страница, его решение.
9. **Вопрос без перевода на язык страницы не показывается.** Вопрос виден на языке, если на нём
   есть и вопрос, и ответ; подстановки языка по умолчанию нет. FAQ, где половина ответов на
   другом языке, хуже короткого.
10. **Якорь генерируется сам.** При создании — из вопроса на языке по умолчанию, уникальный в
    таблице; потом он не меняется, даже если вопрос переписали: иначе ссылки
    `…/faq#oplata-kartoy` ломаются молча. В форме якорь показывается только для чтения, с
    копированием ссылки.
11. **Ответ — rich-text, а не блоки.** Ответ — это абзац, список, ссылка, иногда картинка, и
    `FAQPage` нужен его текст. `wx-rich-text` уже хранит ключ картинки, а не адрес.
12. **Черновиков и версий у вопроса нет** — только `published`. Вопрос правится за минуту;
    `HasDraft` для него — церемония, ради которой редактор будет искать кнопку «Опубликовать».
    Корзина есть, как у всех.

## 3. Этап 1 — контракт «вставить блоком»

### 3.1. Как это выглядит у автора блока

В схеме блока — поле нового типа:

```json
{ "type": "wx-collection", "id": "questions", "label": "Questions", "props": { "source": "faq" } }
```

Редактор страницы видит в этом поле выбор категорий источника (пусто — «все»), лимит, флаг
«показывать фильтр по категориям» и флаг «разметка для поисковиков», если источник её умеет.
В базе лежит только выбор:

```json
{ "categories": [3, 5], "limit": null, "filter": false, "markup": null }
```

`markup: null` — «по умолчанию» (решение 8: включена, когда категорий не выбрано); `true` и
`false` — явный выбор редактора. Источник из значения не хранится: он записан в `props` поля
схемы, и блок, собранный под FAQ, не превращается в блок отзывов правкой содержимого.

Шаблон блока получает значение уже раскрытым (`FieldType::resolve()`, §7 спеки блоков):

```php
$questions['items']   // list: ['id', 'anchor', 'categories' => [id…], ...поля записи от источника]
$questions['groups']  // list: ['id', 'title', 'items' => [id…]] — для фильтра; пусто без filter
$questions['filter']  // bool
```

Что именно лежит в элементе, решает источник: у FAQ это `question` и `answer` (HTML), у Team —
фото, имя, должность. Общие у всех только `id`, `anchor` и `categories`.

**Порядок:** выбрана одна категория — её порядок (`item_position`); ни одной или несколько —
общий (`position`), без повторов. Группы фильтра — выбранные категории в их порядке, а без выбора
— все видимые категории источника, в которых есть хоть одна показанная запись.

### 3.2. `CollectionSource` в `module-admin`

```php
namespace WebxUi\Admin\Collections;

interface CollectionSource
{
    public function key(): string;              // 'faq' — то, что пишут в props.source
    public function title(): string;            // переведённое, для подписи поля
    public function categories(): ?string;      // путь API категорий (как props.source у wx-categories), null — без категорий
    public function supportsMarkup(): bool;

    public function items(Selection $selection, string $locale): Collection; // элементы §3.1, уже по порядку
}
```

- Реестр `CollectionSources` — синглтон по образцу `LinkSources` и `CategorySources`; модуль
  регистрирует источник из своего провайдера (не из файла маршрутов — `route:cache`).
- `Selection` — разобранное значение поля (`categories`, `limit`, `filter`, `markup` с уже
  применённым умолчанием).
- Видимость решает источник: неопубликованное, удалённое и не переведённое на `$locale` в
  `items()` не попадает (решение 9 — правило FAQ, а не контракта).
- Разметку источник пишет сам, если `$selection->markup`: контракт не знает про `module-seo` и
  знать не должен.

### 3.3. Тип поля `wx-collection`

php — `Screens\Types\CollectionType`:

- `rules()` — `categories` существуют у источника, `limit` — целое 1…100 или `null`, флаги —
  булевы; неизвестный источник в `props.source` — отказ с понятным сообщением.
- `store()` — нормализует и сортирует id категорий, выкидывает то, чего источник не умеет
  (`markup` у источника без разметки).
- `resolve()` — на чтении сайта раскрывает значение в `items`/`groups`/`filter`. **Только на
  чтении сайта:** панель читает значение сырым, как `MediaValues` (CLAUDE.md §4, «Переведено
  бывает и то, на чём нет `localized`»). Источника нет (модуль удалён) — пустой список, а не
  исключение: блок рисуется пустым, страница живёт.

npm — компонент поля в `@webx-ui/module-admin`: выбор категорий берёт список по
`categories()` источника (тот же запрос, что у `wx-categories`), лимит, два переключателя; слова —
ключи `webx-admin::collections.*`. Описание источников панель берёт из
`GET /api/cms/collections` (`key`, `title`, `categories`, `markup`) — только тех, чьи права есть у
администратора.

Правило «значения идут через тип поля» проверяется **на обеих дверях** (CLAUDE.md §4): панель
(`PageForm::save`) и агент (`BlockTools::writeContent`).

### 3.4. Типы блоков, которые приносит модуль

Типы блоков заводятся в панели, из кода их не регистрируют (§8 спеки блоков), и так остаётся.
Но блок FAQ без готового типа — это инструкция «соберите аккордеон сами», поэтому модуль
**предлагает** тип, а сайт **ставит** его один раз:

- `BlockOffers` в `module-blocks` — реестр «модуль → документы типов» (тот же формат, что у
  `webx:blocks:export`, лежит в `resources/blocks/*.json` модуля).
- `webx:blocks:offered` показывает предложенное и что из этого уже стоит; `--install` ставит
  недостающее и публикует. **Тип с таким `slug` не трогает никогда** — сайт мог его переделать,
  ровно как у `BlocksDemo`.
- `webx:setup` зовёт установку для модулей, которые выбрал человек; на сайте, где модуль ставят
  в существующую панель, это одна команда из README модуля (`webx:panel --sync` сюда не
  расширяем — он и так не трогает существующее, CLAUDE.md §4).
- Экран «Блоки» про предложенное пока не знает (§1 «Снаружи»).

### 3.5. Одна разметка на страницу

На странице могут стоять два блока FAQ, и один вопрос может оказаться в обоих. `Seo::push()`
складывает блоки подряд, поэтому получилось бы два `FAQPage` с повтором. В `module-seo`
появляется `Seo::put(string $key, array $block)` — блок под ключом, последний выигрывает; `head()`
печатает их вместе с `push()`. Источник FAQ копит вопросы запроса у себя (по id, без повторов) и
каждый раз кладёт весь `FAQPage` под ключом `faq`.

Работает, потому что содержимое страницы рисуется раньше layout'а, в котором стоит `@webxSeo`
(так уже живут `push()` у рубрик). Сайт, чей layout печатает `<head>` раньше блоков, разметки не
получит — это записать в гайд, а не лечить.

### 3.6. Плейграунд

Предпросмотр страницы в плейграунде рисует блоки своим `renderTemplate()` (CLAUDE.md §4 «У
предпросмотра страницы в плейграунде свой рендерер»), поэтому раскрытие `wx-collection` из фикстур
кладётся туда же, через что идут и страница, и конструктор.

## 4. Этап 2 — `module-faq`

### 4.1. Схема

```
faq_questions
  id
  question     json nullable      -- переводимый
  answer       json nullable      -- переводимый, wx-rich-text
  anchor       string(96) unique  -- решение 10
  published    bool default false
  position     int default 0
  extra        json nullable
  softDeletes, timestamps

faq_categories        -- $table->category(); slug остаётся пустым
faq_category_question -- $table->categoryLinks('faq_questions', 'faq_categories')
```

Миграции — `2026_01_01_*` для своих таблиц; на чужие таблицы модуль не ссылается (CLAUDE.md §4 о
сортировке миграций).

### 4.2. Модели

- `Question` — `HasCategories`, `HasExtra`, `HasTranslations` (`question`, `answer`),
  `SoftDeletes`. `visibleIn(string $locale)` — опубликован и оба поля есть на языке. Якорь — при
  `creating`: слаг вопроса на языке по умолчанию, при столкновении — `-2`, `-3`; пустой вопрос —
  `q-<id>` после вставки.
- `FaqCategory` — `IsCategory`; `CategoryKind` без адреса и без SEO (решение 2), права
  `faq.categories.manage`.
- Новый вопрос встаёт в конец общего порядка (`position = max + 1`), в категорию — по общему
  порядку (решение 5 спеки услуг).

### 4.3. Источник `faq`

`FaqSource implements CollectionSource`: элемент — `id`, `anchor`, `categories`, `question`
(строка), `answer` (HTML, ключи картинок раскрыты в адреса тем же кодом, что у `wx-rich-text` на
чтении). Разметка — `FAQPage` с `mainEntity` из `Question`/`acceptedAnswer`, текст ответа без
тегов; через `Seo::put('faq', …)` (§3.5), только если `module-seo` установлен.

### 4.4. Тип блока «FAQ»

`resources/blocks/faq.json`, предлагается через `BlockOffers`:

- Схема: заголовок (переводимый, необязательный), `questions` — `wx-collection` с
  `source: faq`.
- Шаблон: `<details>`/`<summary>` на каждый вопрос, `id` — якорь; фильтр — кнопки по
  `groups`, печатается только при `filter`. Без JS аккордеон работает, фильтр — нет, и это
  допустимо.
- Скрипт: фильтр по группам (скрывает вопросы не своей группы, «Все» — сбрасывает) и открытие
  вопроса по `#якорю` при загрузке и при `hashchange`. Значения — из `data-wx-values` на корне
  (CLAUDE.md §4, «`values` в скрипте блока приезжают только через `data-wx-values`»).
- Стили — не на `--wx-*`: это вёрстка сайта, а не панели, и токенов панели на сайте нет.
  Стили нейтральные, на `currentColor` и `em`, чтобы встать в чужой сайт без спора.

### 4.5. Панель

- Раздел «FAQ» — группа меню со своей иконкой (проверить имя по набору — `icons.test.ts`),
  пункты «Вопросы» и «Категории».
- **Вопросы — `WxListDetail`**: слева список с перетаскиванием и фильтром по категории
  (`useItemOrder`, как у услуг), справа форма описанного экрана `faq.form`: вопрос, ответ
  (`wx-rich-text`), категории (`wx-categories`, `main: false`), «Опубликован», якорь только для
  чтения с копированием, карточка `project-fields`. На телефоне форма уходит в ящик и сама
  рисует «назад» (CLAUDE.md §4, «Панель `WxListDetail` на телефоне не закрывается сама»).
  Создание — строка «Новый вопрос» в списке, а не диалог.
- **Категории — общие страницы** (`categoryRoutes`), экран `faq.category-form`: название,
  видимость, `project-fields`.
- Права: `faq.view`, `faq.manage`, `faq.categories.manage`.
- Сохранение — кнопкой и Ctrl+S, вопрос при уходе с несохранённым, как у категорий.

### 4.6. API панели

```
GET    /api/cms/faq/questions            ?category=&trashed=1&search=
POST   /api/cms/faq/questions            { values }
GET    /api/cms/faq/questions/{id}       → { question, values }
PUT    /api/cms/faq/questions/{id}       { values }            422 под именем поля
DELETE /api/cms/faq/questions/{id}
POST   /api/cms/faq/questions/{id}/restore
POST   /api/cms/faq/questions/reorder    { ids, category? }     CategoryRoutes::items()
       /api/cms/faq/categories/*         общие маршруты категорий
```

Список — без пагинации, как у услуг: вопросов десятки, а не тысячи.

### 4.7. MCP

`faq_list`, `faq_get`, `faq_create`, `faq_update`, `faq_delete`, `faq_reorder` — через те же
`Panel\*`, что и панель; `faq_categories_*` — общий `CategoryTools` (префикс из id модуля
`faq-categories`). Ресурс `faq://catalog`: категории с вопросами в их порядке, вопросы без
категории в конце, неопубликованные с пометкой, у каждого — на каких языках он виден. Создание
в транзакции: отказ не оставляет голый вопрос (у `services_create` такое нашлось на выпуске
услуг).

### 4.8. Демо

`FaqDemo`: три категории, десять вопросов на двух языках демо, один вопрос в двух категориях,
один неопубликованный, один без перевода. Ставит предложенный тип блока, если его нет. Если стоит
`module-pages` — страница `/faq` с блоком «все категории, с фильтром»; если стоит
`module-services` — блок «FAQ по оплате» в содержимом одной демо-услуги (это и есть проверка
решения 8: разметка на `/faq` есть, на услуге — нет).

### 4.9. Регистрации

Всё из CLAUDE.md §4 «Новый composer-пакет надо прописать в `php/` четыре раза» и «Новый раздел
панели регистрируется в четырёх местах»: `php/composer.json` (`require`, `autoload-dev`),
`phpunit.xml.dist`, `phpstan.neon.dist`, `Setup\Catalogue`, `extra.webx.npm`/`extra.webx.panel`,
`apps/playground/src/panel/main.ts`, `scripts/packages.mjs` в `webx-cms.local` (в сессии выпуска).
И строка в реестре `WEBX_UI_COMPOSER_PACKAGES.md` переезжает из «Запланированы».

### 4.10. Тесты, которые обязательны

- Вопрос без перевода не попадает в `items()` на этом языке, с переводом — попадает.
- Порядок: одна категория — её порядок; две — общий, без повторов.
- Якорь: уникальность, стабильность после правки вопроса.
- `FAQPage`: два блока с общим вопросом — одна разметка, вопрос в ней один раз; `markup: null`
  с категориями — разметки нет, без категорий — есть.
- `wx-collection` через **настоящий путь записи** обеих дверей (панель и `blocks_edit_content`),
  а не только на чистой функции (CLAUDE.md §4, «Валидатор, который собирает объект заново»).
- `webx:blocks:offered --install` не трогает тип с тем же `slug`.
- Удалённый модуль: блок с `wx-collection` рисуется пустым, страница отвечает 200.

## 5. Как на этом сядут Team и Reviews

Проверка формы контракта, а не план: источник `team` отдаёт `photo` (раскрытый `wx-media`),
`name`, `position`, `text`, `socials`; `reviews` — `author`, `text`, `rating`, `date`. Разметка —
`supportsMarkup()` у отзывов (`Review`, `AggregateRating`), у команды — нет. Ни одному не нужно
в контракте ничего, кроме того, что в §3.2. Если при их спеке понадобится выбор отдельных
записей (§1 «Снаружи»), это новое поле `Selection` (`ids`), а не новый тип.

## 6. Пошаговый план

Все сессии — **на одной ветке `feat/module-faq`**, в одном worktree, пушатся в `claude` без PR.
PR открывает только F6, и CI гоняется один раз. Каждая сессия гонит локальный гейт своей
половины (php — `composer lint && composer analyse && composer test` из `php/` на PHP 8.4;
npm — точечно `npx vitest run`, `npx vue-tsc` в пакете, из worktree бинарниками напрямую —
CLAUDE.md §4 про pnpm в worktree), полный гейт — только F6.

| Сессия | Что                                                                           |
| ------ | ----------------------------------------------------------------------------- |
| **F1** | php: `CollectionSource`, `CollectionType`, `BlockOffers`, `Seo::put()`        |
| **F2** | npm: поле `wx-collection`, `/api/cms/collections`, плейграунд, гайд контракта |
| **F3** | php: `module-faq` — схема, модели, источник, тип блока, API, экраны, права    |
| **F4** | npm: `@webx-ui/module-faq` — вопросы в `WxListDetail`, категории, плейграунд  |
| **F5** | MCP, демо, гайд, README                                                       |
| **F6** | выпуск целиком, оба демо, проверка на телефоне и в Rich Results Test          |

Промпты ниже самодостаточны. Каждая сессия в конце дописывает сюда «Итог Fn» — что следующей
надо знать сверх промпта, — и строку в память `custom-modules-workflow`.

### F1 — контракт, php

```
Сессия F1 из §6 docs/architecture/WEBX_UI_MODULE_FAQ.md: контракт «вставить блоком», php-половина.

Начало: git worktree add ../webx-ui-module-faq -b feat/module-faq от свежего main (git fetch
claude); первым коммитом — эта спека, если её ещё нет на ветке. Дальше вся работа этапов 1–2 на
этой ветке, PR не открывать.

Прочитать: §§2,3 спеки; docs/architecture/WEBX_UI_MODULE_BLOCKS.md §§5,7,8,9,17 и сессию G;
php/packages/module-admin/src/{Screens/FieldType.php,Screens/FieldTypes.php,Screens/ScreenValues.php,
Links/LinkSources.php,Categories/*}; php/packages/module-media/src/Screens/MediaValues.php — как
значение раскрывается только на чтении сайта; php/packages/module-blocks/src/{Rendering/*,
Demo/BlocksDemo.php,Console/ImportCommand.php,Mcp/BlockTools.php}; php/packages/module-seo/src/
Rendering/Seo.php — push().

Сделать: WebxUi\Admin\Collections\{CollectionSource,CollectionSources,Selection}; тип
wx-collection (CollectionType: rules/store/resolve, пустой список при пропавшем источнике);
GET /api/cms/collections с фильтром по правам; BlockOffers и webx:blocks:offered [--install] в
module-blocks, вызов из webx:setup для выбранных модулей; Seo::put() в module-seo и печать в
head(); тесты на фикстурном источнике — в том числе запись wx-collection через PageForm::save и
через blocks_edit_content; changeset на @webx-ui/php.

Не делать: npm (F2), module-faq (F3). В конце — «Итог F1» в §6 спеки, коммит, пуш в claude.
```

### F2 — контракт, npm

```
Сессия F2 из §6 docs/architecture/WEBX_UI_MODULE_FAQ.md: поле wx-collection в панели.

Работать в worktree ../webx-ui-module-faq на ветке feat/module-faq (не в основном чекауте —
там другая сессия). Прочитать: §3 спеки и «Итог F1»; packages/module-admin/src/categories/ —
как wx-categories берёт список по source; apps/docs/guide/categories.md;
apps/playground/server/panel/ и renderTemplate() в предпросмотре блоков плейграунда.

Сделать: компонент поля wx-collection в @webx-ui/module-admin (категории источника, лимит,
«фильтр», «разметка» только если source.markup; подсказка про умолчание разметки из решения 8),
регистрация типа; ключи webx-admin::collections.* в php/packages/module-admin/lang/* на все
языки панели; мок /api/cms/collections и раскрытие wx-collection в renderTemplate() плейграунда
на фикстурном источнике; гайд apps/docs/guide/collections.md для авторов модулей (контракт,
BlockOffers, одна разметка на страницу и её ограничение из §3.5) и ссылка в сайдбаре; vitest;
changeset на @webx-ui/module-admin.

Проверить в браузере на плейграунде: поле в конструкторе блока, выбор сохраняется, предпросмотр
перерисовывается; 375 px. В конце — «Итог F2» в спеке, коммит, пуш в claude.
```

### F3 — `module-faq`, php

```
Сессия F3 из §6 docs/architecture/WEBX_UI_MODULE_FAQ.md: composer-пакет webx-ui/module-faq.

Worktree ../webx-ui-module-faq, ветка feat/module-faq. Прочитать: §§2,4 спеки, «Итог F1» и
«Итог F2»; php/packages/module-services — образец во всём (провайдер, CategoryKind, экраны,
Panel\*, права, routes/api.php, регистрации); docs/architecture/WEBX_UI_MODULE_SERVICES.md §6
итоги A и B.

Сделать: php/packages/module-faq — composer.json с extra.webx, провайдер, конфиг, миграции §4.1,
Question и FaqCategory §4.2, FaqSource §4.3 с FAQPage через Seo::put, resources/blocks/faq.json
§4.4 в BlockOffers, API §4.6, экраны faq.form и faq.category-form, права и модули панели §4.5,
lang/* на все языки панели, README, LICENSE; регистрации §4.9 кроме плейграунда и сайта; тесты
§4.10 кроме MCP; changeset на @webx-ui/php.

Не делать: npm (F4), MCP и демо (F5). В конце — «Итог F3», коммит, пуш в claude.
```

### F4 — `module-faq`, npm

```
Сессия F4 из §6 docs/architecture/WEBX_UI_MODULE_FAQ.md: npm-пакет @webx-ui/module-faq.

Worktree ../webx-ui-module-faq, ветка feat/module-faq. Прочитать: §4.5 спеки и итоги F1–F3;
packages/module-services/src/* — модуль, api, список с useItemOrder; packages/module-menu/src/
MenusPage.vue и packages/module-inbox/src/InboxPage.vue — WxListDetail в панели; хвост про
WxListDetail в CLAUDE.md §6 (схлопывание у порога) и §4 про ящик без «назад».

Сделать: packages/module-faq — модуль панели, «Вопросы» в WxListDetail (список с перетаскиванием
и фильтром категории, форма faq.form, «Новый вопрос» строкой, якорь с копированием ссылки, Ctrl+S
и вопрос при уходе), категории через categoryRoutes; i18n; плейграунд /panel/faq на фикстурах
(apps/playground/server/panel/faq.ts, модуль в main.ts) плюс блок FAQ на одной из страниц
фикстур; vitest (вкладки — mousedown, фильтры таблицы — после открытия воронки); changeset.
Версия пакета 0.0.0 — первую публикацию делает человек в F6.

Проверить в браузере на плейграунде: создать, перетащить с фильтром и без, 375 px и тёмная тема.
В конце — «Итог F4», коммит, пуш в claude.
```

### F5 — MCP, демо, доки

```
Сессия F5 из §6 docs/architecture/WEBX_UI_MODULE_FAQ.md: MCP, демо, гайд.

Worktree ../webx-ui-module-faq, ветка feat/module-faq. Прочитать: §§4.7,4.8 спеки и итоги
F1–F4; php/packages/module-services/src/{Mcp/*,Demo/*} и resources/demo; apps/docs/guide/
services.md; CLAUDE.md §4 про mcp:start (только трубой).

Сделать: FaqTools и faq://catalog §4.7 (создание в транзакции), FaqDemo §4.8 с установкой
предложенного блока и страницей /faq при module-pages; apps/docs/guide/faq.md (общая страница —
это страница с блоком; разметка и её флаг; патч с полем проекта) и ссылка в сайдбаре; README
npm-пакета; тесты MCP; changeset.

Проверить живьём инструменты через cat … | php artisan mcp:start webx на webx-cms.local в
local-режиме на этом worktree (MONOREPO=… packages.mjs local); сайт после проверки вернуть как
был и ничего там не коммитить — это F6. В конце — «Итог F5», коммит, пуш в claude.
```

### F6 — выпуск

```
Сессия F6 из §6 docs/architecture/WEBX_UI_MODULE_FAQ.md: выпуск контракта и module-faq, оба демо.

Прочитать: итоги F1–F5; CLAUDE.md §5 целиком — «Первую версию нового npm-пакета публикует
человек», «Ручная публикация замораживает диапазоны», «CI на релизном PR ждёт ручного
подтверждения», «Тег php-пакетов ставится до публикации»; docs/architecture/WEBX_UI_PHP_RELEASE.md;
§6 D в WEBX_UI_MODULE_SERVICES.md — тот же выпуск, прошедший 23.09.2026; память
webx-cms-local-demo-site и webx-cms-homelab-deploy.

До релиза (руками пользователя, сессия напоминает и проверяет): репозиторий-зеркало
webx-ui/module-faq на GitHub.

Сделать: погасить dev-серверы; влить свежий main в ветку; полный гейт npm и php/ (PHP 8.4);
scripts/php-smoke.sh против MariaDB; PR, зелёный CI, мерж; одобрить прогон релизного PR; снять
changeset-release/main в отдельный worktree, pnpm install, dist, pnpm pack @webx-ui/module-faq и
проверить диапазоны @webx-ui/* в тарболе; первая публикация — пользователь из своего терминала с
2FA, затем Trusted Publishing (webx-ui / webx-ui / release.yml); мерж релизного PR; npm view
всех поднятых пакетов и тег php-v<версия>; webx-ui/module-faq на Packagist.

Демо: webx-cms.local — module-faq в scripts/packages.mjs, link-panel.sh, composer require,
импорт и ...faq() в resources/js/admin.ts руками (webx:panel --sync не трогает существующий
файл), migrate, webx:blocks:offered --install, cache:clear (словарь), демо по §4.8, npx vite
build; хомлаб — то же в registry, npm ls @webx-ui/module-admin — одна версия, коммит и пуш в
Gitea. Строку реестра в WEBX_UI_COMPOSER_PACKAGES.md и CLAUDE.md §§2,6 — отдельным docs-PR.

Проверить живьём на обоих: /faq — фильтр, ссылка на #якорь открывает вопрос, FAQPage в <head>
один; услуга с «FAQ по оплате» — вопросы есть, FAQPage нет; вопрос без перевода не виден на
втором языке; список с перетаскиванием и форма на настоящем телефоне; Rich Results Test на /faq.
```

## 7. Отложено

- Выбор отдельных записей в `wx-collection` (`Selection::ids`) — когда попросят «эти три отзыва».
- Экран «предложенные типы блоков» в разделе «Блоки» — пока хватает команды и `webx:setup`.
- Вставка коллекции без блока (`@collection('faq', …)` в Blade сайта).
- Поиск по вопросам на сайте, «помог ли ответ».
- Разметка `FAQPage` на странице, чей layout печатает `<head>` раньше содержимого (§3.5).

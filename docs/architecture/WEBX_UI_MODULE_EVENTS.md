# `webx-ui/module-events` — спецификация и план реализации

Статус: спроектирован 25.09.2026, промпты сессий EV1–EV4 — в §6, ни одна не начата. Пакеты —
`webx-ui/module-events` (composer) и `@webx-ui/module-events` (npm).

События — мастер-классы, встречи, вебинары: запись с датой и временем, местом, ценой и внешней
ссылкой на запись. У события свой адрес и **страница жёсткой структуры, без блоков**, как у
рецепта: её рисует вьюха модуля, сайт меняет вид, публикуя вьюху. События лежат в плоских
категориях со своими страницами и **по желанию связаны с услугами** общим механизмом связей
`module-admin`.

Каркас целиком взят у `module-recipes` (`WEBX_UI_MODULE_RECIPES.md`): черновик и версии, SEO,
категории, приставка, выключаемый индекс, связи. Нового общего контракта модуль не приносит —
своё у него только поля события, деление на будущие и прошедшие, `.ics` и разметка `Event`.

Образец — `omnivitality.local`: модели `app/Models/Event.php` и `EventFormat.php` (формат — это
категория со своей страницей), `EventsController`, миграции `2026_09_08_120000_events` и
`2026_09_23_140000_add_structured_fields_to_events_table`.

## 1. Границы

**Внутри:** события и их категории, адреса, индекс и страница категории (будущие), страница
события, прошедшие через хелпер, `.ics`, черновик, публикация и история, «Дублировать», SEO и
разметка `Event`, связь с услугами, хелпер `events()`, экраны панели, API, MCP, демо.

**Снаружи:** предложенный тип блока и источник `wx-collection` (решение 1); повторяющиеся события
как сущность (каждая дата — своя запись, решение 9); запись на событие формой `module-inbox`
(решение 5); оформительские секции омни — eyebrow и заголовки частей, полоса бронирования с
кнопкой, `price_info`, `group_size` и CTA формата (это `extra` и опубликованная вьюха сайта);
импорт со старого сайта (§7).

## 2. Принятые решения (не переоткрывать)

Все приняты в обсуждении 25.09.2026.

1. **Блоков пока нет** — ни на странице события, ни предложенного типа блока, ни
   `CollectionSource`. Сайт выводит события хелпером `events()` (§5.8), как `recipes()`.
   Блоки «Ближайшие события» и «Прошедшие события» — следующим шагом (§7); хелпер для них уже
   даёт всё нужное.
2. **Модуль вне очереди** — седьмой пункт «Запланированы» реестра, берётся раньше `solutions` и
   `team`.
3. **Дата необязательна.** Событие без `starts_at` — «даты уточняются» или «каждую субботу»
   словами в `date_note`. Оно всегда среди будущих и стоит **в начале** списка; прошедшим не
   становится никогда. Разметки `Event` у него нет (`startDate` обязателен).
4. **Порядок — по дате, ручного нет.** Будущие — от ближайшего, прошедшие — от последнего.
   Колонки `position` нет, перетаскивания в панели нет.
5. **Запись — внешняя ссылка** `booking_url`. `module-inbox` не трогаем.
6. **Прошедшие не попадают в общие списки.** Индекс и страница категории показывают только
   будущие; прошедшие сайт выводит отдельно, `events()->past()`. Страница прошедшего события
   остаётся (200, в карте сайта): на неё ведут ссылки, и это фотоотчёт. На ней нет кнопки
   записи, есть пометка «Событие прошло».
7. **Прошедшее** — у которого конец в прошлом: `coalesce(ends_at, starts_at) < now()`. Без
   `ends_at` событие уходит в прошедшие с момента начала. Одно выражение, одинаковое на sqlite,
   MariaDB и PostgreSQL.
8. **Цена — текст для людей и число для машин.** `price` — переводимая строка, её и печатает
   страница («HK$480 с человека», «Индивидуальный расчёт»). `price_amount` — необязательное
   число только для разметки `offers`; валюта одна на сайт — `webx-events.currency`. Нет числа
   или валюты — в разметке нет цены. Ноль — `isAccessibleForFree`.
9. **Повторы — «Дублировать».** Действие в панели и инструмент MCP: копия черновиком со всеми
   полями, категориями и связями, слаг с суффиксом. Сущности «серия» нет.
10. **Связь с услугами — необязательная**, общим механизмом `webx_relations` (роль `services`,
    цель `service`). Без `module-services` поля нет, остальное работает. Модуль регистрирует и
    свою цель `event` — отзывам и FAQ пригодится.
11. **Каркас — как у рецептов:** черновик и версии (`HasDraft`, `HasVersions`), SEO обязательно,
    категории плоские «многие ко многим» (первая — главная, крошки), свой адрес, `extra`.
12. **Адреса — `/{приставка}/{категория}` и `/{приставка}/{событие}`** на одном уровне; приставка
    `webx-events.prefix` (по умолчанию `events`), пустой не бывает. Индекс `/{приставка}`
    выключается `webx-events.index` — адрес уходит странице `module-pages`.
13. **Фото — галерея** (`wx-gallery`), первая — обложка, как у рецептов: у прошедшего события это
    фотоотчёт.
14. **«Чего ожидать» — в ядре модуля**, повторителем из заголовка и текста: на старом сайте это
    единственная секция, которая описывает само событие, а не вёрстку.
15. **Время — момент, а не строка.** `starts_at`/`ends_at` — `datetime` в поясе приложения,
    по API — `toAtomString()` (CLAUDE.md §4 про `Carbon` и пояса, `Panel\Instant` у блога).
    Флаг `all_day` — событие на день (дни), время не печатается, в разметке только даты.
16. **Формат участия** — `offline | online | mixed` (`attendance`): место печатается у
    `offline`/`mixed`, в разметке — `eventAttendanceMode` и `Place`/`VirtualLocation`. Ссылки на
    трансляцию у события нет: её отдают записавшимся, а не всем.

## 3. Схема

```
events
  id
  title          json nullable      -- переводимый
  slug           json nullable      -- переводимый
  lead           json nullable      -- переводимый, без разметки: карточки, description
  gallery        json nullable      -- список значений wx-gallery; первая — обложка
  starts_at      datetime nullable  -- в поясе приложения; index
  ends_at        datetime nullable  -- не раньше starts_at
  all_day        boolean default false
  date_note      json nullable      -- переводимый: «каждую субботу», «даты уточняются»
  attendance     string(8) default 'offline'   -- offline | online | mixed
  venue          json nullable      -- переводимый: «Studio Kitchen»
  address        json nullable      -- переводимый: улица, город
  map_url        string nullable
  description    json nullable      -- переводимый HTML (wx-rich-text)
  highlights     json nullable      -- «Чего ожидать»: [{ title: {ru,en}, text: {ru,en} }]
  price          json nullable      -- переводимый текст
  price_amount   decimal(10,2) nullable   -- только для разметки; 0 — бесплатно
  booking_url    string nullable
  extra          json nullable      -- поля проекта
  draft()                           -- module-admin
  softDeletes, timestamps

event_categories          category(); lead json (wx-rich-text), cover json (wx-media)
event_category_event      categoryLinks('event', 'event_categories')

-- услуги — webx_relations, своих таблиц нет
```

- Все миграции — `2026_01_01_*`: ссылок на чужие таблицы нет (CLAUDE.md §4 о сортировке).
- `attendance` — `string(8)` при самом длинном значении в семь символов: дефолт длиннее колонки
  MariaDB не создаёт (CLAUDE.md §4).
- `ends_at` раньше `starts_at` — 422 под `ends_at`; `ends_at` без `starts_at` — тоже 422.
- `highlights` — переводимые **поля внутри строки**, а не строки на каждом языке: порядок и число
  карточек у всех языков одни. **Первым делом EV1 проверяет**, что `localized` у ребёнка
  `wx-repeater` проходит `ScreenValues` и `WxScreenRepeater` туда и обратно (чип языка в строке,
  значение `{ru,en}` на ключе). Не проходит — это правка ядра экранов в `module-admin` тем же
  EV1 (и EV2 для npm), до модуля: она же понадобится команде и тарифам. Разбор по языку для сайта
  — у модели (`highlights(string $locale): list<array{title: string, text: string}>`), строка
  без заголовка и текста на этом языке пропускается.

## 4. Модуль

### 4.1. Модели

- `Event` — `HasCategories`, `HasRelations` (`services`), `HasDraft`, `HasVersions`, `HasSeo`,
  `HasBreadcrumbs`, `HasStructuredData`, `HasExtra`, `HasTranslations` (`title`, `slug`, `lead`,
  `date_note`, `venue`, `address`, `description`, `price`), `SoftDeletes`. Скоупы `upcoming()`
  (решения 3, 7: без даты — первыми, потом `starts_at` по возрастанию) и `past()`
  (`starts_at` по убыванию). `isPast(): bool`. Отношение `categories` — не `hidden`-подобные
  имена колонок (CLAUDE.md §4 про `hidden`/`visible` у модели).
- `EventCategory` — `IsCategory`, адрес, SEO, как `RecipeCategory`.

### 4.2. Зависимости

`require`: `module-admin`, `module-media`, `module-seo`, `routing`, `localization`, `mcp`.
`suggest`: `module-services` (поле «Услуги»), `module-blocks` (предпросмотр по токену),
`module-pages` (страница на месте выключенного индекса). `require-dev`: все три — ради тестов и
демо.

### 4.3. Адреса

| Тип              | Форматтер                        | Пример                          |
| ---------------- | -------------------------------- | ------------------------------- |
| `event`          | `Prefixed($prefix, Slug::class)` | `events/spring-cooking-class`   |
| `event-category` | `Prefixed($prefix, Slug::class)` | `events/cooking-demonstrations` |

Всё как у рецептов (`WEBX_UI_MODULE_RECIPES.md` §5.3): `OnConflict::Fail`, пустая приставка —
исключение в `boot()`, индекс `webx.events.index` в `SitemapRoutes` только при
`webx-events.index = true`, смена приставки — `webx:routes:rebuild --type=event
--type=event-category`, оба типа — `LinkSource`, `event` — цель связей.

**Календарь** — маршрут `{prefix}/{path}.ics` (`webx.events.ics`): путь без `.ics` ищется в
реестре адресов и должен принадлежать видимому событию с датой; иначе 404. Файл — один
`VEVENT`: `UID` из id и хоста, `DTSTART`/`DTEND` (у `all_day` — `VALUE=DATE`, конец —
следующий день после последнего, как требует RFC 5545), `SUMMARY`, `DESCRIPTION` из `lead`,
`LOCATION` из места и адреса, `URL` страницы. Строки режутся по 75 октетов, спецсимволы
экранируются — это проверяется тестом, а не на глаз. Без внешней библиотеки.

### 4.4. Публичная часть

- **Индекс** `{prefix}` (если включён) — будущие события, `webx-events.per-page` (24) на
  страницу, `?page=`; категории — ссылками на свои страницы. Вне диапазона страниц — 404.
- **Категория** — вступление, обложка и будущие события этой категории, так же постранично.
- **Событие** — §4.5.

Индекс и категория рисуют общий фрагмент `partials/list.blade.php` (сетка карточек и пагинация);
карточка — `partials/card.blade.php`. Вьюхи — из конфига с фолбэком на пакет, публикуются в
`resources/views/vendor/webx-events`; макет — общий шов. Видимость: событие — опубликовано, не в
корзине, заголовок есть на языке страницы; категория — `is_visible`; событие в скрытой категории
видно по своему адресу. Пустой индекс или категория — не 404, а страница со словами «Ближайших
событий нет».

### 4.5. Страница события

`event.blade.php`, части — отдельными `@include`:

1. галерея (первая крупно, остальные лентой);
2. заголовок и `lead`; у прошедшего — пометка «Событие прошло»;
3. факты: когда (§4.6), где (место, адрес, ссылка на карту; у `online` — «Онлайн»), цена,
   категории ссылками;
4. кнопка записи на `booking_url` (нет ссылки или событие прошло — нет кнопки) и ссылка
   «Добавить в календарь» на `.ics` (нет даты — нет ссылки);
5. описание (HTML как есть — `store()` у `wx-rich-text` чистит разметку);
6. «Чего ожидать» — карточки из `highlights` на языке страницы; пусто — части нет;
7. услуги — карточки видимых связанных услуг (есть `module-services` и связи).

Всё, что сайт выводит своё (eyebrow, заголовки частей, полоса бронирования омни), — строки в
опубликованной вьюхе и поля проекта в `extra`.

### 4.6. Как печатается дата

Один помощник `Rendering\When` — и страница, и карточки, и MCP-каталог говорят одинаково.
Месяцы — `Carbon::translatedFormat()` на языке страницы; время — `H:i`.

| Случай                             | Печать                                            |
| ---------------------------------- | ------------------------------------------------- |
| есть `date_note`                   | `date_note` (перебивает всё)                      |
| нет даты                           | ничего                                            |
| один день, время                   | 12 октября 2026, 10:00–12:30                      |
| один день, без конца               | 12 октября 2026, 10:00                            |
| `all_day`, один день               | 12 октября 2026                                   |
| несколько дней (`all_day` или нет) | 12–14 октября 2026 / 30 сентября – 2 октября 2026 |

`date_note` перебивает печать, но не данные: у события с датой и `date_note` сортировка, «прошло»,
`.ics` и разметка идут по дате.

### 4.7. SEO и разметка

- `HasSeo` у события и категории, карточка `wx-seo` патчем от `module-seo`.
- Крошки: событие — индекс → главная категория → событие; категория — индекс → категория;
  «индекс» — то, что реестр отдаёт по пути `{prefix}` (маршрут модуля или страница
  `module-pages`), нет ничего — звено пропускается. Как у рецептов.
- **`Event`** (`HasStructuredData`), только у события с `starts_at`: `name`, `description`
  (`lead`), `image` — вся галерея, `url`, `startDate`/`endDate` (ISO со смещением; у `all_day` —
  только дата), `eventStatus` — `EventScheduled`, `eventAttendanceMode` по `attendance`,
  `location` — `Place` (`name` — `venue`, `address` — строкой) и/или `VirtualLocation` (`url` —
  `booking_url`, иначе адрес страницы), `offers` — `Offer` с `url` (`booking_url`) и, если есть
  `price_amount` и валюта, `price`/`priceCurrency`; ноль — `isAccessibleForFree: true`;
  `organizer` — `@id` `Organization` из настроек SEO. Проверять на validator.schema.org **и** в
  Rich Results Test (события Google показывает любому сайту).
- Индекс и категория — `ItemList` будущих через `Seo::push()`.
- Карта сайта — все видимые события, и прошедшие тоже (решение 6).

### 4.8. Хелпер `events()`

`Rendering\EventQuery` по образцу `RecipeQuery`: `upcoming()` (по умолчанию), `past()`, `all()`,
`in($categories)`, `relatedTo('service', $ids)`, `only()`, `except()`, `take()`, `locale()`,
`get()`, `first()`. Карточка (`Rendering\Cards`): `id`, `url`, `title`, `lead`, `cover`,
`gallery`, `starts_at`, `ends_at` (ISO), `all_day`, `when` (строка §4.6), `past`, `attendance`,
`venue`, `price`, `booking_url`, `ics_url`, `categories` (id), `fields`. Строка `events()` — в
`webx:doctor` (`Doctor\Checks\Helpers`), как `recipes()`.

### 4.9. Панель

Группа меню «Events»: **Events · Categories**.

**Список событий** — **с пагинацией** (перетаскивания нет, а прошедшие копятся годами). Строка:
обложка, название со слагом, когда (§4.6, в подсказке — точное значение `WxDate`), категории
чипами, статус, `WxRowMenu` (открыть, дублировать, снять с публикации, в корзину). Фильтры:
**когда** — будущие (по умолчанию) · прошедшие · все, категория, услуга (если стоит), статус,
поиск. Прошедшие в режиме «все» — приглушённой строкой.

**Редактор** — экран `events.form`, вкладки **Event · Settings · SEO · History**:

- **Event** — карточка «Когда» (`starts_at`, `ends_at` — `wx-date-picker` с
  `valueFormat: "yyyy-MM-dd'T'HH:mm:ssXXX"`, `all_day`, `date_note` с подсказкой «перебивает
  печать даты»); «Где» (`attendance` — `wx-segmented`, `venue`, `address`, `map_url`; место
  прячется у `online` через `visible`); «Запись» (`price`, `price_amount` с подсказкой «только
  для поисковиков, валюта — настройка сайта», `booking_url`); «Фото» (`wx-gallery`); «Описание»
  (`wx-rich-text`, `localized`); «Чего ожидать» (`wx-repeater`: `title`, `text` — `localized`).
- **Settings** — заголовок, адрес, `lead` со счётчиком, категории (`wx-categories`), услуги
  (`wx-relations`, target `service`), карточка `project-fields`.
- **SEO**, **History** — как у рецептов.

Панель действий, ревизия (409), автосейв в черновик, предпросмотр по токену (есть
`module-blocks`) — как у рецептов. Категории — `categoryRoutes`, экран `events.category-form`
(название, адрес, видимость, вступление, обложка, SEO, поля проекта).

Права: `events.view`, `events.manage`, `events.categories.manage`. Id модулей панели: `events`,
`event-categories`.

### 4.10. API панели

Формы зафиксированы заранее, чтобы EV1 и EV2 шли параллельно:

```
GET    /api/cms/events             ?when=upcoming|past|all&category=&service=&status=&q=&trashed=1&page=
  → LengthAwarePaginator: { data: [{ id, title, slug, path, url, cover: { thumb } | null,
                                     starts_at, ends_at, all_day, when, past, status,
                                     categories: [{ id, title }], published_at, updated_at,
                                     deleted_at, revision }],
                            links, meta,
                            filters: { categories: [{id,title}], services: [{id,title}] | null } }
POST   /api/cms/events             { title, slug? }        → 201 { data: { event, values, revision, prefix, preview_url } }
GET    /api/cms/events/{id}        → { data: { event, values, revision, prefix, preview_url } }
PUT    /api/cms/events/{id}        { values, revision }    → то же; 409 на устаревшей, 422 под полем
POST   /api/cms/events/{id}/discard                         → то же
POST   /api/cms/events/{id}/duplicate                       → 201 то же — форма копии
POST   /api/cms/events/{id}/publish | unpublish | restore   → { data: event }
DELETE /api/cms/events/{id}
GET    /api/cms/events/{id}/versions
POST   /api/cms/events/{id}/versions/{number}/restore       → форма целиком
       /api/cms/events/categories/*                          общие маршруты категорий
       /api/cms/relations/{target}                           как у рецептов
```

- `status` — `draft | published | modified | unpublished`, как у услуг и рецептов. Фильтр
  `?status=published` — всё, что на сайте, с правками и без (просьба EV2); `modified` — только с
  правками. `when` по умолчанию — `upcoming`; корзина — `trashed=1`, `when` при ней не действует.
  Страница — `page`, размер — `per_page` (5–100, по умолчанию 20); `links` — `first`, `last`,
  `prev`, `next`, `meta` — `current_page`, `from`, `last_page`, `path`, `per_page`, `to`, `total`.
- `event` в ответе формы — та же строка, что в списке (`EventResource`); `preview_url` — `null`
  без `module-blocks`; `prefix` — для `wx-slug`.
- `starts_at`/`ends_at` в строке и в `values` — `toAtomString()` или `null`; принимаются с
  любым смещением и приводятся к поясу приложения до записи.
- `values` — всё с экрана: переводимые картами языков, `gallery`, даты, `all_day`, `attendance`,
  `map_url`, `price_amount` (число или `null`), `booking_url`, `highlights`, `categories`,
  `services` (нет цели `service` — нет и ключа), `seo`, поля проекта. Категории и услуги ждут в
  черновике и применяются публикацией. Сохранение, создание и дублирование — одна транзакция.
- **Дублирование:** копия — черновик, ни разу не опубликованный; все поля, категории и услуги;
  заголовок тот же, слаг — со следующим свободным суффиксом `-2`, `-3` на каждом языке; SEO
  карточки копируется; история — пустая.

### 4.11. MCP

`events_list` (`when` — `upcoming` по умолчанию), `events_get`, `events_create`, `events_update`,
`events_duplicate`, `events_publish`, `events_unpublish`, `events_delete` — через те же `Panel\*`;
`event_categories_*` — общий `CategoryTools`. Услуги — id или адресом, как у рецептов. Даты — ISO
8601; строка без смещения читается в поясе приложения, и описание инструмента это говорит.
Ресурс `events://catalog`: категории, будущие события (у каждого адрес, `when`, `written_in`) и
число прошедших по категориям.

### 4.12. Демо

`EventsDemo`, `resources/demo/events.json` (en и ru): три категории — завтраки-встречи,
кулинарные мастер-классы, частные события (как форматы омни); шесть событий, даты — **от момента
посева** (`+10 days` и т. п.), иначе демо само уходит в прошлое: одно через неделю с временем,
одно на три дня `all_day`, одно онлайн, одно без даты с `date_note`, одно прошедшее с галереей,
одно черновиком; одно в двух категориях, у одного цена числом, у одного бесплатное. `requires()`:
`media`, плюс `services`, если стоит (связать два события с услугами из журнала демо).

### 4.13. Регистрации

Всё из CLAUDE.md §4 («прописать в `php/` четыре раза», «раздел панели — в четырёх местах»):
`php/composer.json`, `phpunit.xml.dist`, `phpstan.neon.dist`, `Setup\Catalogue`,
`extra.webx.npm`/`extra.webx.panel`, `apps/playground/src/panel/main.ts`, `scripts/packages.mjs`
в `webx-cms.local` и строка в `scripts/php-smoke.sh` (EV4); иконка группы — из набора
— `calendar` (есть в наборе, `icons.test.ts`).

### 4.14. Тесты, которые обязательны

- `upcoming()`/`past()`: без даты — первым среди будущих и никогда не прошлое; идущее событие с
  `ends_at` в будущем — будущее; без `ends_at` — прошлое с момента начала; порядок обоих списков.
- Пояса: `2026-10-12T10:00:00+03:00` при `app.timezone = UTC` записан как 07:00 и отдан тем же
  моментом; тест с поясом приложения **не** UTC (CLAUDE.md §4: в UTC всё сходится).
- Печать даты — все строки таблицы §4.6 на двух языках.
- Разметка: нет даты — нет `Event`; `all_day` — даты без времени; `offers` с ценой и без,
  ноль — `isAccessibleForFree`; `online` — `VirtualLocation`, `mixed` — оба.
- `.ics`: `VALUE=DATE` и конец +1 день у `all_day`; перенос длинных строк и экранирование
  `,;\`; событие без даты и невидимое — 404.
- Индекс и категория — только будущие, пагинация, `index = false` — маршрута нет, страница
  `module-pages` по пути `{prefix}` встаёт в крошки события; пустая приставка — отказ.
- `highlights`: переводимые поля внутри строки проходят экран туда и обратно; строка пустая на
  языке страницы — не печатается.
- `ends_at` раньше `starts_at` и `ends_at` без `starts_at` — 422 под полем.
- Дублирование: копия — черновик с категориями и услугами, слаги с суффиксом на всех языках,
  отказ посередине не оставляет строки.
- Без `module-services`: поля «Услуги» нет, `services` в values не роняет сохранение.

## 5. Отличия от рецептов — коротко

Для того, кто пишет по `module-recipes` как по образцу:

| У рецептов                                    | У событий                                   |
| --------------------------------------------- | ------------------------------------------- |
| `position`, перетаскивание, `reorder`         | нет; порядок по дате, список постранично    |
| источники — второй вид категорий              | нет                                         |
| похожие (`related`, подбор `Similar`)         | нет                                         |
| тип блока, `RecipesSource`, фрагмент каталога | нет (решение 1); общий фрагмент списка есть |
| пищевая ценность, ингредиенты, время, порции  | даты, место, цена, запись, «Чего ожидать»   |
| —                                             | `upcoming`/`past`, `.ics`, «Дублировать»    |
| разметка `Recipe`                             | разметка `Event`                            |

## 6. Пошаговый план

**Выпуск один, в конце** (EV4), до него ни PR, ни ожидания CI. Ветка `feat/module-events`
(worktree `../webx-ui-module-events`, спека уже там), параллельная сессия — на своей ветке, её
сливает следующая.

| Сессия  | Ветка / worktree                                | Что                                              |
| ------- | ----------------------------------------------- | ------------------------------------------------ |
| **EV1** | `feat/module-events`                            | php `module-events`: §§3–4 кроме MCP и демо      |
| **EV2** | `feat/events-panel` / `../webx-ui-events-panel` | npm `module-events`: панель по §4.10, плейграунд |
| **EV3** | `feat/module-events`                            | слияние EV2, MCP, демо, гайд, README, doctor     |
| **EV4** | `feat/module-events`                            | выпуск, оба демо                                 |

EV1 ∥ EV2 (API — §4.10). В конце каждой сессии — «Итог EVn» сюда и строка в память
`custom-modules-workflow`.

Общее для всех: gh не в PATH — `"C:\Program Files\GitHub CLI\gh.exe"`; пушить в `claude`, не в
`origin`; php-гейт — `composer lint && composer analyse && composer test` из `php/` на
`C:\Work\OSPanel\modules\PHP-8.4\php.exe` (в свежем worktree сначала прогреть манифест Testbench
последовательно — CLAUDE.md §4 «И то же самое на пустом `vendor`»); npm — точечно
`npx vitest run <файлы> --pool=forks --poolOptions.forks.singleFork` **из корня worktree**,
`npx vue-tsc -p tsconfig.json --noEmit` в пакете, eslint и prettier на своих файлах. `pnpm` в
worktree с симлинком на `node_modules` не запускать (CLAUDE.md §4).

### EV1 — `module-events`, php

```
Сессия EV1 из §6 docs/architecture/WEBX_UI_MODULE_EVENTS.md: composer-пакет webx-ui/module-events.
Идёт параллельно с EV2.

Worktree ../webx-ui-module-events, ветка feat/module-events (спека уже там). Первым делом:
git fetch claude; git merge claude/main. В php/: composer install, прогреть манифест Testbench.
PR не открывать.

Прочитать: §§2–5 спеки; php/packages/module-recipes целиком — образец почти во всём (адреса,
выключаемый индекс, черновик, версии, SEO, крошки, разметка, Cards/RecipeQuery/helpers, Views,
Panel, связи с услугами, lang на десять языков и тест паритета); module-blog Panel\Instant —
приведение моментов к поясу приложения; CLAUDE.md §4 про Carbon и пояса, про «Главная не
получает адрес» (Reserved), про string-колонку и длину дефолта, про hidden/visible у модели.

Сначала: проверить, что localized у ребёнка wx-repeater проходит ScreenValues туда и обратно
(§3, highlights). Не проходит — починить в module-admin тем же коммитом, с тестом, и крупно
сказать в итоге: EV2 нужна та же правка в WxScreenRepeater.

Сделать: php/packages/module-events — composer.json с extra.webx и autoload files, провайдер,
конфиг (prefix обязателен, index, per-page, currency, views), миграции §3, модели §4.1 со
скоупами upcoming/past, адреса, индекс и .ics §4.3, публичная часть и вьюхи §§4.4–4.5 (части —
@include, общий фрагмент списка), Rendering\When §4.6, SEO и разметка Event §4.7, EventQuery,
Cards, events() §4.8; API §4.10 (формы — ровно как там, по ним параллельно пишется панель; одна
транзакция на сохранение, создание и дублирование); экраны events.form и events.category-form с
карточкой project-fields; права и модули панели §4.9; цель связей event; все слова
webx-events::* на десять языков — серверные и нужные панели; строка events() в webx:doctor;
README, LICENSE; регистрации §4.13 кроме плейграунда, сайта и smoke; тесты §4.14; changeset на
@webx-ui/php.

Не делать: npm (EV2), MCP и демо (EV3), блоки (решение 1). Если форма ответа API должна
отличаться от §4.10 — поправить §4.10 тем же коммитом и сказать об этом в итоге крупно. В конце
— «Итог EV1», коммит, пуш в claude.
```

#### Итог EV1 (25.09.2026)

**`localized` внутри `wx-repeater` правки не потребовал** — ни в `module-admin`, ни (по итогу EV2)
в `WxScreenRepeater`: `RepeaterType` проверяет и хранит локализованного ребёнка по языкам, и
`highlights` едут `{ru,en}` на ключе туда и обратно (`PanelTest`, `PageTest`). **§4.10 не
менялся по форме**, только дописан пункт про `status`/`when`/пагинацию (ниже по тексту): фильтр
`status=published` — «всё на сайте», с правками и без, как просил EV2.

- **`php/packages/module-events`** по §§3–4 без MCP и демо: миграции (`2026_01_01_*`,
  `attendance string(8)`), `Event` и `EventCategory`, скоупы `upcoming`/`past`/`byDate`,
  `isPast()`, адреса `event`/`event-category` под приставкой, индекс (выключаемый) и
  `{prefix}/{slug}.ics` (`webx.events.ics`, остаётся и без индекса; путь ищется в реестре,
  алиасы тоже), вьюхи частями и общий `partials/list`, `Rendering\When`, `EventMarkup`,
  `EventQuery`/`Cards`/`events()`, `EventPage`, API §4.10 с `duplicate`, экраны, права, группа
  «Events» (`calendar`), цель связей `event`, `LinkSource` событий и категорий.
- **Экран `events.form` — раскладка EV2 как есть** (два пикера на одно `name`: `starts-at`/
  `starts-on`, `ends-at`/`ends-on`, переключаются `visible` по `all_day`). `ScreenValues` такое
  принимает: оба узла одного типа, значение одно и то же. Место прячется при `online`.
- **Слова**: `module`, `panel`, `event`, `category` — ключ в ключ с `messages.ts` EV2 (английский —
  его же строки), плюс `screen`, `site`, `errors`, `relations`; десять языков, тест паритета.
- **Пояса.** Моменты пишутся мутатором модели (`Support\Moment`) в пояс приложения — любой дверью.
  **Событие на дни** хранится целыми днями: начало — полночь первого дня, конец — `23:59:59`
  последнего (`settleDays()` на `saving`), поэтому «прошло» — одно выражение и для него. Дни
  берутся **в смещении, с которым пришло значение**, а не в поясе приложения: полночь 12 октября
  в +03:00 — это ещё 11 октября в UTC (`Moment::day()`, тест в `PanelTest`). Тесты с поясом
  `Asia/Hong_Kong` ставят и `date_default_timezone_set` — Eloquent читает колонку в поясе PHP, а
  Testbench выставляет его до `defineEnvironment()`.
- **Для EV3 — крупно: дата-пикер «только дата» и пояс.** Сервер отдаёт день события на дни как
  `2026-10-12T00:00:00+08:00` (пояс приложения). Пикер `type: date` в браузере западнее
  приложения покажет **11 октября**, если читает момент, а не дату строки. Проверить на
  плейграунде с реальным сервером; лечится на npm-стороне (брать дату из строки как есть).
- Проверки 422 под полем: `ends_at` раньше `starts_at` или без него; `map_url`/`booking_url` —
  только `http(s)://`. Дублирование — одна транзакция, слаг `-2`, `-3` без обрезки хвостовых
  цифр (`class-2` → `class-2-2`), категории и услуги сразу строками (копия не на сайте), SEO
  копируется, истории нет.
- Регистрации: `php/composer.json` (require, карта версий, autoload-dev), `phpunit.xml.dist`,
  `phpstan.neon.dist`, `Setup\Catalogue`, `Doctor\Checks\Helpers` (+`DoctorTest`), патчи SEO на
  `events.form`/`events.category-form` в `module-seo`, `extra.webx` (npm `^0.1.0`). Changeset
  minor на `@webx-ui/php`. Не сделано (EV3/EV4): MCP, демо, гайд, плейграунд, smoke, сайт.
- Гейт на PHP 8.4: pint, phpstan (после сброса кеша и манифеста Testbench), phpunit — 1712 тестов
  зелёные (из них 52 — `module-events`). `composer lint/test` из скретчпада не запускаются
  (скрипты зовут `php` по имени) — бинарники вызывались напрямую.

### EV2 — `module-events`, npm

```
Сессия EV2 из §6 docs/architecture/WEBX_UI_MODULE_EVENTS.md: npm-пакет @webx-ui/module-events.
Идёт параллельно с EV1, php не трогает.

Начало: git fetch claude; git worktree add ../webx-ui-events-panel -b feat/events-panel
claude/feat/module-events; pnpm install --frozen-lockfile; собрать dist у tokens, core, schema,
module-admin (тесты соседних пакетов видят module-admin из dist — CLAUDE.md §4). PR не открывать.

Прочитать: §§2,4.6,4.9,4.10 спеки; packages/module-recipes целиком — образец (редактор с
вкладками, автосейв, ревизия, история, предпросмотр, создание); packages/module-services — список;
apps/playground/server/panel/recipes* — образец мока; CLAUDE.md §4 про context.can() булево или
computed, про вкладки в тестах (mousedown), про фильтры таблицы, про WxDate, про календарь и
язык (dateLocaleKey), про valueFormat у wx-date-picker.

Сделать: packages/module-events (версия 0.0.0) — модуль панели §4.9: список с пагинацией
(LengthAwarePaginator как есть), фильтр «когда» по умолчанию «будущие», прошедшие приглушённо,
«Дублировать» в меню строки и в панели действий редактора (открывает копию); редактор с
вкладками Event · Settings · SEO · History; категории через categoryRoutes; i18n с английским
полом и тестом паритета (ключи заводит EV1 в php/packages/module-events/lang; чего не хватило —
дописать туда же и сказать в итоге); плейграунд: apps/playground/server/panel/events.ts по
формам §4.10 (даты фикстуры — от текущего момента), модуль в main.ts, экраны — пока свои копии,
если EV1 ещё не положил файлы (EV3 уберёт); предпросмотр страницы события; vitest; changeset
(minor). Если EV1 сообщит, что localized внутри wx-repeater требовал правки, — та же правка в
WxScreenRepeater здесь.

Проверить в браузере на плейграунде (фоновый npx vite --port 5187, preview_start с url): создать
событие, заполнить все вкладки, даты с разными поясами браузера не сдвигаются после сохранения и
перезагрузки, all_day прячет время, online прячет место, «Чего ожидать» на двух языках,
дублирование, фильтр «когда», пагинация, 375 px и тёмная тема. В конце — «Итог EV2» в §6 спеки
на своей ветке, коммит, пуш в claude.
```

#### Итог EV2 (25.09.2026)

Ветка `feat/events-panel` (worktree `../webx-ui-events-panel`, от `claude/feat/module-events` на
`ee70bcf8` — итога EV1 к концу сессии на ветке ещё не было, поэтому формы — ровно по §4.10).

- **`packages/module-events`** (0.0.0, changeset minor): `events()` — два модуля панели
  (`events`, `event-categories`), категории — `categoryRoutes(eventCategoriesOptions())`, счётчик
  `events_count`, ссылка «показать события» ведёт в `?category=<id>&view=all`. Список —
  `WxTable` по образцу статей блога (пагинатор как есть, карточки ниже 640 px). **«Когда» —
  вкладками**: Upcoming (по умолчанию) · Past · All · Bin; состояние, категория и услуга — за
  воронкой. Прошедшие в All — приглушённая строка (`rowClass` → `is-past`). Колонка «When» —
  строка `when` сервера, в подсказке — точные моменты в поясе читателя; без даты и без
  `date_note` — «No date». Редактор — как у рецептов (автосейв, ревизия, 409, 422 под полем,
  предпросмотр, история), под названием — `when` сервера, у прошедшего — бейдж «Over».
  «Duplicate» — в меню строки и в `WxActionBar` редактора (сначала сохраняет, потом открывает
  копию); на узком экране кнопка иконочная, иначе состояние бара уезжает на свою строку.
- **API-клиент шлёт `when` всегда** (`?when=upcoming` и т. д.), корзина — `when=all&trashed=1`,
  страница — `page`/`per_page` (таблица шлёт `per_page=15`). Фильтр `status=published` мок
  понимает как «обе зелёные» (published + modified) — серверу стоит так же.
- **`localized` внутри `wx-repeater` работает без правки** `WxScreenRepeater`: чип языка в строке,
  значение `{ru,en}` на ключе туда и обратно — проверено тестом и в браузере.
- **`all_day` прячет время двумя узлами на одно имя**: `starts-at`/`ends-at` (`type: datetime`,
  `visible: {when: all_day, not: true}`) и `starts-on`/`ends-on` (`type: date`, `visible: {when:
all_day, is: true}`), оба с `valueFormat: "yyyy-MM-dd'T'HH:mm:ssXXX"`. Одинаковые `name` схема
  допускает (запрещены только одинаковые `id`); время при переключении не теряется. Место
  (`venue`, `address`, `map_url`) — `visible: {when: attendance, not: online}`.
- **Плейграунд**: `apps/playground/server/panel/events.ts` — «сервер» в поясе Asia/Hong_Kong
  (+08:00), даты фикстуры от текущего момента, 29 событий (6 показательных + 22 прошедших
  завтрака на две страницы Past + одно в корзине), `when` по §4.6, 422 на `ends_at`, дублирование
  со слагом `-2`, предпросмотр `/preview/event/<id>` по §4.5. Картинки — папка «События» в
  `media.ts`. Модуль в `main.ts`, алиас в `vite.config.ts`, зависимость в `package.json` и lock.
- **Копии экранов** — `apps/playground/server/panel/events/{form,category-form}.json` и SEO-патчи
  `seo.events.*.json`, подключены в `screens.ts`. Слова `webx-events::*` до прихода php-половины
  отдаёт `lang.ts` из `messages.ts` пакета и `INTERIM_SCREEN_WORDS` в `events.ts` — только когда
  файлов `php/packages/module-events/lang` нет.
- Тесты: 14 (`EventsPage`, `EventEditorPage`), `messages.test.ts` (паритет) — `describe.skipIf`,
  пока на ветке нет `php/packages/module-events/lang/en`. `vue-tsc`, eslint, prettier, `vite
build` пакета — чисто.
- В браузере (5187): список, вкладки, пагинация Past и All, приглушённые прошедшие; создание;
  даты настоящим пикером при поясе браузера Europe/Kiev — 14:00 в поле = `19:00+08:00` на
  сервере, после перезагрузки те же 14:00; 422 под «Ends»; `all_day` меняет оба пикера на даты и
  обратно без потери времени; Online прячет место; «Чего ожидать» на RU и EN в одной строке;
  дублирование из редактора (черновик, `-2`, категории и услуги на месте); Settings, SEO,
  History, предпросмотр прошедшего (пометка, без кнопки); 375 px светлая и тёмная — без
  горизонтальной прокрутки.

**EV1 должен завести в `php/packages/module-events/lang/*`** группы `module`, `panel`, `event`,
`category` — ключ в ключ с `packages/module-events/src/messages.ts` (английский оттуда же), и
группу `screen` для своих экранов; если EV1 берёт мою раскладку `events.form`, ключи `screen.*` и
английский — в `INTERIM_SCREEN_WORDS`. Права, которые проверяет панель: `events.manage`,
`events.categories.manage`; манифест: группа `events` (иконка `calendar`), модули `events` и
`event-categories`.

**EV3:** экраны в `screens.ts` перевести на `php/packages/module-events/resources/screens/*` и
SEO-патчи `module-seo`, удалить `server/panel/events/`, `INTERIM_SCREEN_WORDS` и запасную ветку
в `lang.ts`, снять `skipIf` в `messages.test.ts`. Попутно найдено: у списка статей блога
(`ArticlesPage.vue`) `watch(() => [..] as const)` срабатывает на каждую смену адреса и
перечитывает страницу второй раз без `per_page` — у событий исправлено источниками по одному.

### EV3 — слияние, MCP, демо, доки

```
Сессия EV3 из §6 docs/architecture/WEBX_UI_MODULE_EVENTS.md: MCP, демо, гайд.

Worktree ../webx-ui-module-events, ветка feat/module-events. Первым делом: git fetch claude;
git merge claude/feat/events-panel (конфликты — спека и lang/*: объединить); worktree
../webx-ui-events-panel удалить (сначала погасить его dev-сервер, симлинки — find -type l
-delete), ветку оставить до выпуска.

Прочитать: §§4.11,4.12 спеки и итоги EV1, EV2; php/packages/module-recipes/src/{Mcp,Demo}/* с
resources/demo; apps/docs/guide/recipes.md; CLAUDE.md §4 про mcp:start (только трубой), про
возврат webx-cms.local после local-режима из копий и про новый пакет в local-режиме.

Сделать: EventsTools и events://catalog §4.11 (создание, сохранение и дублирование в
транзакции; в описании — даты ISO и пояс), EventsDemo §4.12 с датами от момента посева и
динамическим requires(); apps/docs/guide/events.md — адреса и приставка, выключенный индекс и
страница на его месте, будущие и прошедшие, хелпер events() с примером блока «Прошедшие» во
вьюхе сайта, .ics, разметка Event и как её проверить, цена текстом и числом, «Дублировать»,
патч с полем проекта; ссылка в сайдбаре; README npm-пакета, разделы MCP и демо в README
composer-пакета; убрать копии экранов из плейграунда, если EV2 их заводил; тесты MCP и демо;
changeset. Гейт php-половины и vitest/vue-tsc npm.

Проверить живьём: инструменты через cat … | php artisan mcp:start webx на webx-cms.local в
local-режиме на этом worktree (до переключения — копии composer.json, composer.lock,
package.json, package-lock.json, database/database.sqlite в скретчпад; после — назад и composer
install); индекс, категория, событие и .ics curl'ом — разметка Event в ответе. Ничего на сайте не
коммитить — это EV4. В конце — «Итог EV3», коммит, пуш в claude.
```

### EV4 — выпуск

```
Сессия EV4 из §6 docs/architecture/WEBX_UI_MODULE_EVENTS.md: выпуск module-events, оба демо.

Прочитать: итоги EV1–EV3; CLAUDE.md §5 целиком — особенно «gh pr merge в очередь не ставит»,
«Первую версию нового npm-пакета публикует человек», «Ручная публикация замораживает
диапазоны», «Тег php-пакетов ставится до публикации», «Composer после релиза может минут десять
не видеть новую версию»; итог RC6 в docs/architecture/WEBX_UI_MODULE_RECIPES.md — тот же выпуск;
память webx-cms-local-demo-site, webx-cms-homelab-deploy, release-speed.

До релиза (руками пользователя, сессия напоминает и проверяет): репозиторий-зеркало
webx-ui/module-events на GitHub.

Сделать: погасить dev-серверы; полный гейт npm и php/ (PHP 8.4); module-events в
scripts/php-smoke.sh рядом с module-recipes и smoke против MariaDB (базы пустыми — CLAUDE.md §4);
PR, зелёный CI, в очередь мутацией; релизный PR — снять changeset-release/main в отдельный
worktree, pnpm install, dist, pnpm pack @webx-ui/module-events и проверить диапазоны @webx-ui/*
в тарболе; первая публикация — пользователь из своего терминала с 2FA, затем Trusted Publishing
(webx-ui / webx-ui / release.yml); мерж релизного PR; npm view и тег php-v<версия>;
webx-ui/module-events на Packagist (пользователь).

Демо: webx-cms.local — module-events в scripts/packages.mjs, link-panel.sh, composer require в
два шага, импорт и ...events() в resources/js/admin.ts руками, migrate, cache:clear, демо §4.12
тинкером со своим журналом, npx vite build; хомлаб — то же в registry, npm ls
@webx-ui/module-admin — одна версия, коммит и пуш в Gitea. omnivitality-v2.local — только если
пользователь скажет. Строку реестра в WEBX_UI_COMPOSER_PACKAGES.md (из «Запланированы» в
«Модули») и CLAUDE.md §§2,6 — отдельным docs-PR.

Проверить живьём на обоих: индекс и категория — только будущие, без даты первым; страница
прошедшего — пометка, нет кнопки; .ics открывается календарём; Event на validator.schema.org и в
Rich Results Test; телефон — пользователь.
```

## 7. Отложено

- **Блоки** «Ближайшие события» и «Прошедшие события» — `EventsSource` для `wx-collection` и
  предложенный тип блока с видом `upcoming | past`, как витрина у рецептов. Хелпер и фрагмент
  списка к этому готовы.
- **Импорт со старого `omnivitality`** — скрипт в том сайте: `EventFormat` → категории
  (`introductory_paragraph` → `lead`, `price_info`/`group_size`/CTA → `extra`), `category_type`
  → первая категория, `date` + строка `time` → `starts_at`/`ends_at` (разбор той же регуляркой,
  что `Event::timeParts()`), `date_text` → `date_note`, `location` → `venue`, `location_url` →
  `map_url`, `expect_items` → `highlights` (`kicker` — в `extra` или теряется), секции
  описания → `description`, полоса бронирования → `extra`.
- Повторяющиеся события как сущность (серия с правилом повтора) — если «Дублировать» окажется
  мало.
- Отзывы и FAQ → события тем же механизмом связей (цель `event` уже будет).
- Статусы `EventCancelled`/`EventPostponed` — поле, когда понадобится отменять, не снимая
  страницу.

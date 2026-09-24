# `webx-ui/module-reviews` — спецификация и план реализации

Статус: спроектирован 24.09.2026; php-половина написана в R1 того же дня. Пакеты —
`webx-ui/module-reviews` (composer) и `@webx-ui/module-reviews` (npm).

Отзывы — записи с фото, именем, должностью, оценкой и текстом, разложенные по плоским
категориям. Своей страницы нет ни у отзыва, ни у модуля: отзывы попадают на сайт **блоком**,
как вопросы FAQ, — «отзывы клиники» сеткой на главной, «отзывы об имплантации» слайдером на
странице услуги.

Контракт «вставить блоком» (`CollectionSource`, `wx-collection`, `BlockOffers`) уже выпущен с
`module-faq`, а хелпер шаблона — с `services()` (#276). Отзывы — третий потребитель первого и
второй — второго, поэтому своего у модуля немного: данные, источник, хелпер `reviews()` и тип
блока с несколькими видами отображения. Весь ядерный код — по образцу, который уже работает.

## 1. Границы

**Внутри:** отзывы и их категории, два порядка, переводы, источник `reviews` для
`wx-collection`, хелпер `reviews()`, тип блока «Отзывы» с видами «один», «сетка», «слайдер»,
«бегущая строка», экраны панели, API, MCP, демо.

**Снаружи:** своя страница `/reviews` (ни маршрутом, ни конфигом — решение 2); разметка
schema.org (решение 3); приём отзывов с сайта формой и модерация (inbox с отзывами не связан);
выбор отдельных отзывов руками (`Selection::ids`, §7 спеки FAQ — «на главную вот эти три» — это
категория «На главную»); ответ компании на отзыв; импорт из Google/Яндекса; общий
`RecordQuery` для `services()` и `reviews()` (§7).

## 2. Принятые решения (не переоткрывать)

Решения 1–11 приняты в обсуждении 24.09.2026.

1. **Модуль делается раньше `module-solutions` и `module-team`** — он нужен сейчас. Порядок в
   «Запланированы» реестра пакетов поправлен.
2. **Своего адреса нет, страницы нет, конфига под страницу тоже нет.** Отзывы видны только
   блоком (и хелпером в шаблонах сайта). `LinkSource` модуль не регистрирует, в реестре адресов
   его нет, в карте сайта — тоже.
3. **Разметки нет:** `supportsMarkup() = false`. Звёзды в выдаче даёт только `AggregateRating`,
   а на отзывы организации о самой себе на её же сайте Google их не показывает с 2019 года
   («self-serving reviews»); разметка без выгоды — это только риск ручных санкций.
4. **Поля отзыва:** фото (`wx-media`), имя, должность и текст (переводимые), оценка (`wx-rate`,
   целое 1–5, необязательная), дата, ссылка на профиль, категории, «Опубликован», поля проекта
   (`extra`). **Дата — просто дата:** её показывает шаблон, если хочет, ни на видимость, ни на
   порядок она не влияет.
5. **Категории плоские, «многие ко многим», без адреса, SEO и блоков** — как у FAQ:
   `review_categories` и `review_category_review` на ядре категорий `module-admin`, `slug`
   пустой, `CategoryKind` без `prefix`. Отзыв без главной категории (`wx-categories`,
   `main: false`).
6. **Порядок ручной, два:** общий (`position`) и внутри категории (`item_position` в связи),
   перетаскивают в том списке, который видят. Выбрана одна категория — её порядок; ни одной или
   несколько — общий, без повторов (правило `Selection::apply()`).
7. **Отзыв без текста на языке страницы не показывается.** Подстановки языка по умолчанию для
   текста нет. Имя и должность без перевода берутся с языка по умолчанию: имя человека обычно
   одно на всех языках, и требовать его перевода — значит прятать отзыв из-за формальности.
8. **Две дороги в шаблон, одна карточка.** Блок с `wx-collection` получает `$reviews['items']`
   уже отфильтрованными; шаблон, которому нужно своё, зовёт `reviews()->in(...)`. Обе отдают
   одну и ту же карточку (§4.3), как у `services()` и источника `services`.
9. **Вид отображения — поля блока, а не модуль.** Модуль предлагает один тип блока с полем
   `layout` и зависимыми от него полями (§4.5); новый вид — это новая опция и новая ветка в
   шаблоне, сайт может переделать предложенный тип у себя.
10. **Черновиков и версий у отзыва нет** — только `published`, как у вопроса FAQ. Корзина есть.
11. **Панель — `WxListDetail`, как у FAQ:** слева список (фото, имя, звёзды), справа форма.

## 3. Схема

```
reviews
  id
  name         json nullable        -- переводимое
  job_title    json nullable        -- переводимое («CEO, Acme»): должность
  text         json nullable        -- переводимое, простой текст (textarea), не rich-text
  rating       unsignedTinyInteger nullable   -- 1…5
  reviewed_on  date nullable        -- решение 4: просто дата
  profile_url  string(2048) nullable
  photo        json nullable        -- значение wx-media, как у обложки услуги
  published    bool default false
  position     int default 0        -- общий порядок, как у услуг и FAQ
  extra        json nullable
  softDeletes, timestamps

review_categories        -- $table->category(); slug остаётся пустым
review_category_review   -- $table->categoryLinks('reviews', 'review_categories')
```

- Должность — `job_title` и в базе, и в имени поля экрана, и в карточке: `position` у всех
  модулей на общем коде категорий — колонка порядка (`Selection::apply()`, `CategoryRoutes`), и
  занимать это имя должностью значило бы спорить с общим кодом ради слова.
- Текст — `textarea`, а не `wx-rich-text`: отзыв — это абзацы слов человека, а не вёрстка;
  шаблон печатает его `nl2br(e(...))`.
- `profile_url` — только `http(s)://`, проверяет тип поля/форма; на сайте ссылка печатается с
  `rel="nofollow noopener"` (предложенный шаблон так и делает).
- Миграции — `2026_01_01_*` для своих таблиц; на чужие таблицы модуль не ссылается (`photo` —
  json, а не внешний ключ, как у обложек), CLAUDE.md §4 о сортировке миграций.

## 4. Модуль

### 4.1. Модели

- `Review` — `HasCategories`, `HasExtra`, `HasTranslations` (`name`, `job_title`, `text`),
  `SoftDeletes`. `scopeVisibleIn(string $locale)` — опубликован, не в корзине, `text` есть на
  языке (решение 7). Новый отзыв встаёт в конец общего порядка (`max + 1`).
- `ReviewCategory` — `IsCategory`; `CategoryKind` без `prefix` и без SEO, права
  `reviews.categories.manage`; `slug` обнуляется на каждом `saving`, как у `FaqCategory`
  (общий `CategoryForm::create()` делает его из названия).

### 4.2. Зависимости

`require`: `module-admin`, `module-blocks`, `module-media` (фото — `wx-media`, и карточка
раскрывает его тем же `MediaValues`, что у услуг), `localization`, `mcp`.
`require-dev`: `module-pages`, `module-services` — ради демо и `DemoTest`.

### 4.3. Карточка

`Rendering\Cards` — отзыв так, как его читает шаблон, по образцу `WebxUi\Services\Rendering\Cards`:

```php
[
    'id' => 12,
    'anchor' => 'review-12',       // стабильный якорь для ссылки #review-12
    'categories' => [3, 5],        // id категорий отзыва
    'name' => 'Anna Petrova',      // на языке страницы, иначе на языке по умолчанию
    'initials' => 'AP',            // первые буквы двух первых слов имени — вместо фото (R1)
    'job_title' => 'CEO, Acme',    // то же; '' если нет
    'text' => "…\n…",              // простой текст на языке страницы (без него карточки нет)
    'rating' => 5,                 // int 1…5 или null
    'date' => '2026-09-20',        // Y-m-d или null
    'profile' => 'https://…',      // или null
    'photo' => [ 'url' => …, 'thumb' => …, 'width' => …, 'height' => …, 'alt' => … ], // или null
    'fields' => [ 'city' => 'Kyiv' ], // поля проекта по имени
]
```

Список любой длины — те же несколько запросов (`RELATIONS` с предзагрузкой, как у услуг).

### 4.4. Хелпер `reviews()` и источник `reviews`

`Rendering\ReviewQuery` — по образцу `ServiceQuery`, те же шаги и та же семантика:

```blade
@foreach (reviews()->in($categories)->take($limit ?: 6) as $review) … @endforeach
```

| Шаг                  | Что делает                                                       |
| -------------------- | ---------------------------------------------------------------- |
| `in($categories)`    | id, категория или список; одна категория — её порядок            |
| `in(null)`, `in([])` | Без фильтра: так приходит нетронутое поле редактора, и это «все» |
| `only([12, 7])`      | Только эти, в этом порядке                                       |
| `except($review)`    | Кроме этих                                                       |
| `take(6)`            | Не больше шести; null или ноль — все                             |
| `locale('uk')`       | Язык карточек; по умолчанию тот, на котором рисуется страница    |
| `categories()`       | Каталог: видимые категории, у каждой `reviews`; пустые выпадают  |
| `get()`, `first()`   | Список карточек или одна; сам запрос можно перебирать и считать  |

Слагов у категорий отзывов нет, поэтому `in()` принимает id и модели, а строку — только
цифрами. Лимит считается **после** видимости (в php, как у FAQ): «первые шесть» на русском — это
шесть русских отзывов, а не шесть вообще минус непереведённые.

Хелпер объявляется за `function_exists('reviews')`, и `webx:doctor` (`Doctor\Checks\Helpers`)
говорит, чей он, — строка рядом с `menu()` и `services()`.

`Collections\ReviewsSource implements CollectionSource` — `key() = 'reviews'`,
`categories() = 'reviews/categories'`, `supportsMarkup() = false`, `permission() = 'reviews.view'`,
`items()` — это `(new ReviewQuery)->in($selection->categories)->take($selection->limit)->locale($locale)->get()`.

**Как шаблон узнаёт выбранные категории** (вопрос из обсуждения): никак, и ему не нужно.
Блок на `wx-collection` получает `$reviews['items']` уже отфильтрованными по выбору редактора
(пусто — все). Шаблон, который хочет звать хелпер сам, заводит в схеме своё поле категорий —
`{ "id": "categories", "type": "wx-categories", "props": { "source": "reviews/categories", "main": false } }`
— и получает `$categories` списком id (`[]` — ничего не выбрано) для `reviews()->in($categories)`.
Так же устроен вариант с `services()` в гайде услуг.

### 4.5. Тип блока «Отзывы»

`resources/blocks/reviews.json`, предлагается через `BlockOffers` (`webx:blocks:offered --install
--module=reviews`). Схема:

```json
[
  { "id": "title", "type": "wx-input", "label": "Heading", "localized": true },
  {
    "id": "reviews",
    "type": "wx-collection",
    "label": "Reviews",
    "props": { "source": "reviews" }
  },
  {
    "id": "layout",
    "type": "wx-segmented",
    "label": "Layout",
    "props": {
      "options": [
        { "value": "single", "label": "One" },
        { "value": "grid", "label": "Grid" },
        { "value": "slider", "label": "Slider" },
        { "value": "marquee", "label": "Marquee" }
      ]
    }
  },
  {
    "id": "columns",
    "type": "wx-input-number",
    "label": "Columns",
    "props": { "min": 1, "max": 6 },
    "visible": { "when": "layout", "in": ["grid", "slider"] }
  },
  {
    "id": "autoplay",
    "type": "wx-switch",
    "label": "Autoplay",
    "visible": { "when": "layout", "is": "slider" }
  },
  {
    "id": "speed",
    "type": "wx-slider",
    "label": "Speed",
    "props": { "min": 10, "max": 120 },
    "visible": { "when": "layout", "is": "marquee" }
  },
  {
    "id": "all_label",
    "type": "wx-input",
    "label": "The filter button that shows every review",
    "help": "Printed only when the filter is on. Empty is “All”.",
    "localized": true
  }
]
```

- **Пустой `layout` — это `grid`**, пустые `columns` — 3: шаблон держит умолчания сам, потому
  что блок хранит только то, что редактор тронул (итог F1 спеки FAQ, `ResolvesMissing`).
- **Шаблон** — одна карточка (`<figure>`: фото или инициалы в кружке, звёзды, текст в
  `<blockquote>`, `<figcaption>` с именем, должностью, датой и ссылкой на профиль) и четыре
  обёртки по `$layout`. Звёзды — пять символов с шириной закраски от `--rating` и
  `aria-label="4 / 5"`; нет оценки — нет звёзд.
- **Без JS всё читается:** сетка — CSS grid, слайдер — горизонтальная лента со `scroll-snap`,
  бегущая строка — лента с прокруткой. Скрипт добавляет: кнопки «назад/вперёд» и автопрокрутку
  слайдера, клон ленты (`aria-hidden`) и CSS-анимацию бегущей строки с паузой на наведении, и
  фильтр по категориям, как у FAQ (по DOM-атрибутам, а не `data-wx-values` — итог F3 спеки FAQ).
  `prefers-reduced-motion` выключает и автопрокрутку, и бег.
- **Стили нейтральные**, на `currentColor` и `em`, без `--wx-*` — это вёрстка сайта (§4.4 спеки
  FAQ). `[hidden]` объявлен явно у всего, чему дан `display` (CLAUDE.md §4).
- **`visible` с условием в схеме блока** — проверить в R2 на настоящем конструкторе: в схемах
  экранов работает (§7 `WEBX_UI_SCREENS.md`), автокомплит блоков его предлагает, но блок
  нормализует `id` → `name` (CLAUDE.md §4, «Узел экрана опознаётся по `name`»), и условие должно
  видеть соседа. Не работает — чинить в `module-blocks`, это общий дефект, не частный.

### 4.6. Панель

- Раздел «Отзывы» — группа меню со своей иконкой (имя проверить по набору — `icons.test.ts`),
  пункты «Отзывы» и «Категории».
- **Отзывы — `WxListDetail`**, копия устройства FAQ (итог F4 спеки FAQ): один маршрут
  `/reviews`, открытый отзыв — `?review=<id|new>`, рядом `category`, `q`, `view=trashed`;
  «Новый отзыв» строкой, `POST` на первом сохранении; перетаскивание с фильтром и без; Ctrl+S и
  вопрос при уходе только при смене `review`. Строка списка — фото-миниатюра (или инициалы), имя,
  звёзды, «виден на: ru, en» и признак «опубликован, но не виден нигде».
- Форма — описанный экран `reviews.form`: фото, имя, должность, текст (`wx-textarea`,
  `localized`), оценка (`wx-rate`), дата (`wx-date-picker`, только дата), ссылка на профиль,
  категории (`wx-categories`, `main: false`), «Опубликован», карточка `project-fields`.
- Категории — общие страницы (`categoryRoutes`), экран `reviews.category-form`: название,
  видимость, `project-fields`.
- Права: `reviews.view`, `reviews.manage`, `reviews.categories.manage`. Id модулей панели:
  `reviews` и `review-categories` (отсюда MCP-префикс `review_categories_*`).

### 4.7. API панели

Формы ответов зафиксированы здесь заранее, чтобы R1 и R2 шли параллельно (§6):

```
GET    /api/cms/reviews                  ?category=&trashed=1&search=
  → { data: [{ id, name, job_title, rating, photo: { thumb } | null, published, position, locales,
               categories: [{ id, title }], updated_at, deleted_at }],
      filters: { categories: [{ id, title }] } }                          без meta и пагинации
POST   /api/cms/reviews                  { values }             → 201 { data: { review, values } }
GET    /api/cms/reviews/{id}             → { data: { review, values } }
PUT    /api/cms/reviews/{id}             { values }             422 под именем поля
DELETE /api/cms/reviews/{id}
POST   /api/cms/reviews/{id}/restore     → голый ресурс строки списка
POST   /api/cms/reviews/reorder          { ids, category? }     CategoryRoutes::items()
       /api/cms/reviews/categories/*     общие маршруты категорий
```

- `name` в списке — на языке панели, иначе на языке по умолчанию, иначе `#id`.
- `locales` — языки, на которых есть `text` (опубликованность не учитывается — отдельный флаг,
  чтобы список мог сказать «опубликован, но не виден нигде»).
- `review` в ответе формы — `{ id, name, published, deleted_at }`; `values` — `name`, `job_title`,
  `text` картами языков, `rating`, `reviewed_on` (`Y-m-d`), `profile_url`, `photo` (значение
  `wx-media`), `published`, `categories` (id по порядку) и поля проекта.
- `POST` и `PUT` — одна `ReviewForm::save()` в транзакции: отказ не оставляет строку.

### 4.8. MCP

`reviews_list`, `reviews_get`, `reviews_create`, `reviews_update`, `reviews_delete`,
`reviews_reorder` — через те же `Panel\*`, что и панель; `review_categories_*` — общий
`CategoryTools`. Ресурс `reviews://catalog`: категории с отзывами в их порядке, без категории — в
конце, у каждого `visible_in` и `written_in`. Отзыв называется id; категория — id или названием
на любом языке. Строка без языка в `create`/`update` — язык по умолчанию (итог F5 спеки FAQ).
Фото агент ставит ключом файла библиотеки, как обложку услуги.

### 4.9. Демо

`ReviewsDemo`, данные в `resources/demo/reviews.json` (en и ru): две категории, восемь отзывов,
один в обеих, один неопубликованный, один без русского текста, оценки разные, один без оценки,
один со ссылкой на профиль; фото нет — шаблон рисует инициалы, и демо это заодно проверяет.
Ставит предложенный тип блока, если его нет. `requires()` динамический, как у FAQ: `blocks` и
ещё `pages`/`services`, если стоят. При `module-pages` — страница `/reviews` (ребёнок главной) с
блоком «все, сетка, с фильтром»; при `module-services` — блок «слайдер, одна категория» в
демо-услуге из журнала этого прогона. Это заодно проверка двух видов из четырёх на живом сайте.

### 4.10. Регистрации

Всё из CLAUDE.md §4 «Новый composer-пакет надо прописать в `php/` четыре раза» и «Новый раздел
панели регистрируется в четырёх местах»: `php/composer.json` (`require`, `autoload-dev`),
`phpunit.xml.dist`, `phpstan.neon.dist`, `Setup\Catalogue`, `extra.webx.npm`/`extra.webx.panel`,
`apps/playground/src/panel/main.ts`, `scripts/packages.mjs` в `webx-cms.local` (в R4), строка
в `scripts/php-smoke.sh` рядом с `module-faq` (R4).

### 4.11. Тесты, которые обязательны

- Отзыв без текста на языке не попадает в карточки; без имени на языке — имя с языка по
  умолчанию.
- Порядок: одна категория — её; две — общий, без повторов; лимит после видимости.
- `reviews()`: каждая строка таблицы §4.4, включая `in([])` = все и `only()` в своём порядке.
- `wx-collection` с `source: reviews` через **настоящий путь записи** обеих дверей (панель и
  `blocks_edit_content`), как у FAQ.
- Предложенный блок рисуется на своём `sample` во всех четырёх `layout` (иначе
  `webx:blocks:offered` оставит его черновиком — итог F1 спеки FAQ).
- `profile_url` не `http(s)` — 422 под полем; `rating` вне 1…5 — 422.
- Удалённый модуль: блок рисуется пустым, страница отвечает 200.

## 5. Слова

Все ключи `webx-reviews::*` на все десять языков панели заводит **R1** — и серверные, и те, что
нужны панели: модуль и группа меню, список (`all`, `new`, `trashed`, `visible-nowhere`, `no-text`,
`restore`, поиск), форма (подписи полей экрана, `choose`, `choose-help`, `cancel`, уход с
несохранённым), категории, отказы. R2 держит английский пол в `messages.ts` и тест паритета;
чего не хватило — дописывает сам в те же файлы (это единственное место, где половины
пересекаются, §6).

## 6. Пошаговый план

**Выпуск один, в самом конце** (R4); до него ни PR, ни ожидания CI. Каждая сессия гонит
локальный гейт своей половины (php — `composer lint && composer analyse && composer test` из
`php/` на PHP 8.4; npm — точечно `npx vitest run … --pool=forks --poolOptions.forks.singleFork`
из корня worktree, `npx vue-tsc` в пакете, eslint и prettier на своих файлах). Полный гейт —
только R4.

**R1 и R2 идут параллельно**, в двух worktree на двух ветках: php-половина и npm-половина не
пересекаются по файлам, а API, из-за которого F4 ждал F3, зафиксирован в §4.7. R3 сливает ветку
R2 в ветку R1 и дальше идёт по ней.

| Сессия | Ветка / worktree                                         | Что                                                             |
| ------ | -------------------------------------------------------- | --------------------------------------------------------------- |
| **R1** | `feat/module-reviews` / `../webx-ui-module-reviews`      | php: пакет целиком — схема, модели, хелпер, источник, блок, API |
| **R2** | `feat/module-reviews-panel` / `../webx-ui-reviews-panel` | npm: панель, плейграунд, проверка `visible` в схеме блока       |
| **R3** | `feat/module-reviews`                                    | слияние R2, MCP, демо, гайд, README, `webx:doctor`              |
| **R4** | `feat/module-reviews`                                    | выпуск, оба демо                                                |

Промпты ниже самодостаточны. Каждая сессия в конце дописывает сюда «Итог Rn» — что следующей
надо знать сверх промпта, — и строку в память `custom-modules-workflow`.

### R1 — `module-reviews`, php

```
Сессия R1 из §6 docs/architecture/WEBX_UI_MODULE_REVIEWS.md: composer-пакет webx-ui/module-reviews.

Worktree ../webx-ui-module-reviews, ветка feat/module-reviews (спека уже на ней). git fetch claude
и влить свежий main, если ветка отстала. PR не открывать.

Прочитать: §§2–5 спеки; docs/architecture/WEBX_UI_MODULE_FAQ.md §§3,4 и итоги F1, F3, F5 — это
образец почти во всём; php/packages/module-faq целиком; php/packages/module-services/src/
{Rendering/*,Collections/ServicesSource.php,helpers.php} и tests/HelperTest.php — образец хелпера и
карточки; php/packages/module-admin/src/{Collections/*,Categories/*,Doctor/Checks/Helpers.php}.

Сделать: php/packages/module-reviews — composer.json с extra.webx и autoload files для
helpers.php, провайдер, конфиг, миграции §3, Review и ReviewCategory §4.1, Cards и ReviewQuery и reviews() §§4.3–4.4, ReviewsSource,
resources/blocks/reviews.json §4.5 (все четыре вида, скрипт, стили) в BlockOffers, API §4.7
(формы ответов — ровно как там: по ним параллельно пишется панель), экраны reviews.form и
reviews.category-form, права и модули панели §4.6, все слова §5 на десять языков, строка reviews()
в webx:doctor, README, LICENSE; регистрации §4.10 кроме плейграунда, сайта и smoke; тесты §4.11
кроме MCP; changeset на @webx-ui/php.

Не делать: npm (R2), MCP и демо (R3). Если форма ответа API всё-таки должна отличаться от §4.7 —
поправить §4.7 в спеке тем же коммитом и сказать об этом в итоге крупно: R2 пишет мок по §4.7.
В конце — «Итог R1» в §6 спеки, коммит, пуш в claude.
```

#### Итог R1 — сделано 24.09.2026

Всё из промпта на ветке; гейт php-половины зелёный (pint, phpstan, полный phpunit на PHP 8.4), у
модуля 40 тестов. **Формы ответов API — ровно §4.7, ничего не менялось**; мок R2 пишется по нему
как есть. Что следующим сессиям надо знать сверх §§3–5:

- **В карточке появился `initials`** (§4.3 поправлен): первые буквы двух первых слов имени,
  `mb_*`. Шаблон рисует их вместо фото, и мок плейграунда (R2) должен отдавать это поле, иначе
  кружок пустой. Резать имя по символам в самом шаблоне — значит однажды получить половину
  кириллической буквы.
- **Что шаблон блока требует от `renderTemplate()` плейграунда (R2):** одна лента карточек, а вид
  — атрибутом `data-reviews-layout` на корне и CSS по нему, а не четыре копии разметки. В шаблоне
  есть `@if`/`@else`, `@foreach` с `$loop->first` (вид «один» печатает только первую), `?:`
  (`$layout ?: 'grid'`, `$columns ?: 3`, `$speed ?: 40`, `$all_label ?: 'All'`), тернарник
  (`$autoplay ? 'on' : 'off'`), `!==` и `&&` в условиях, `implode(' ', …)` и
  `{!! nl2br(e($review['text'])) !!}`. Стрелки слайдера печатаются только при `layout ===
'slider'`, фильтр — при `filter && groups` и не для «одного».
- **Поля блока, которые не трогали, в шаблоне `null`** (итог F1), поэтому умолчания — в шаблоне;
  тест рисует блок без значений и проверяет `grid`, `3`, `40`, `off`. Правила на содержимом блоков
  не запускаются, `store()` у `wx-input-number`/`wx-slider` только приводит к числу — мусорный
  `columns` доедет до CSS и там просто не сработает.
- **Скорость бегущей строки — пиксели в секунду** (10…120, по умолчанию 40): скрипт делит половину
  ширины удвоенной ленты на неё и пишет `--reviews-duration`. Клон ленты — без `id` и с
  `aria-hidden`, ссылки в нём `tabIndex = -1`. Автопрокрутка слайдера — шаг раз в 5 с, пауза на
  наведении и фокусе, с конца — на начало.
- **`rating: 0` — это «без оценки»**, а не отказ: `WxRate` при очистке шлёт ноль, `RateType`
  пропускает 0…5, форма пишет `null`. Отказ 422 — на 6, −1, 2.5 и не-числе (`RateType`).
- **`profile_url` проверяет `ReviewForm::save()`** (`http`/`https` + `FILTER_VALIDATE_URL`), отказ
  под `profile_url` словом `webx-reviews::errors.profile-url`; общего типа поля «адрес» в ядре нет,
  заводить его ради одного поля не стал. Пробелы по краям срезаются.
- **`reviewed_on`** приходит от `wx-date-picker` с `valueFormat: "yyyy-MM-dd"`; момент с временем
  тоже примется — берётся дата в том смещении, в каком пришла.
- **`in('clinic')` — фильтр, который никто не проходит**, а не «все»: слагов у категорий нет, и
  опечатка не должна превращать «отзывы об имплантации» во все отзывы. Пустые `null`/`''`/`[]` —
  «все», как в §4.4.
- **Видимость — `scopeVisibleIn()` плюс проверка в php:** запрос отсекает по наличию ключа
  (`text->ru` не null), `writtenIn()` — по тому, что там не одни пробелы. Лимит — после второго.
- **Порядок меню:** группа `reviews` — 600 (после FAQ с 500), иконка группы `star`, модули `list`
  и `folder`. MCP у модулей нет (`ProvidesMcpTools` — R3), демо тоже.
- **Сама панель ещё не собрана:** экран `reviews.form` рисует только стандартные поля ядра, своих
  узлов (как `wx-faq-anchor` у FAQ) нет — R2 регистрировать ничего не должен.
- **Слова для панели уже лежат:** `webx-reviews::review.*` (список, форма, корзина, уход с
  несохранённым, `visible-nowhere`, `no-text`, `no-rating`, `seen-in`), `screen.*`, `category.*`,
  `module.*`, `errors.*` на все десять языков. Сгенерированы из одного словаря — паритет ключей
  проверяет `TranslationsTest`.
- Worktree: `php/vendor` поставлен (`composer.phar` — в скретчпаде сессии), `vendor/webx-ui/*` —
  junction'ы на `php/packages`. **Прогрев манифеста Testbench через `phpunit --filter` здесь не
  помог**: phpstan всё равно переписывал `services.php` из каждого воркера и падал на `rename():
Access is denied`. Помог ровно рецепт CLAUDE.md — `php vendor/orchestra/testbench-core/laravel/artisan
package:discover`, убрать `*.tmp`, `phpstan clear-result-cache`, и только потом `analyse`
  (`.env` при этом не появился).

### R2 — `module-reviews`, npm

```
Сессия R2 из §6 docs/architecture/WEBX_UI_MODULE_REVIEWS.md: npm-пакет @webx-ui/module-reviews.
Идёт параллельно с R1, php-половину не трогает.

Начало: git fetch claude; git worktree add ../webx-ui-reviews-panel -b feat/module-reviews-panel
claude/feat/module-reviews (спека там); pnpm install --frozen-lockfile в этом worktree (каталог
обычный, node_modules будет свой — CLAUDE.md §4 про pnpm в worktree); собрать dist у tokens, core,
schema, module-admin. PR не открывать.

Прочитать: §§2,4.5–4.7,5 спеки; итоги F2 и F4 в docs/architecture/WEBX_UI_MODULE_FAQ.md;
packages/module-faq целиком — образец; apps/playground/server/panel/faq.ts и renderTemplate() в
предпросмотре блоков плейграунда.

Сделать: packages/module-reviews (версия 0.0.0) — модуль панели по §4.6, WxListDetail как у FAQ,
категории через categoryRoutes, i18n с английским полом и тестом паритета (ключи — §5; чего нет в
php/packages/module-reviews/lang, R1 заводит сам, а R2 дописывает только отсутствующее и пишет это
в итог); плейграунд: apps/playground/server/panel/reviews.ts по формам §4.7, модуль в main.ts,
тип блока из §4.5 и страница с ним в фикстурах (renderTemplate научить тому, что понадобится для
@if по $layout); vitest (вкладки — mousedown, фильтры таблицы — после открытия воронки); changeset.
Проверить в конструкторе блока плейграунда, что поля с visible по layout показываются и прячутся;
если нет — починить в module-blocks и записать в итог.

Проверить в браузере на плейграунде: создать, перетащить с фильтром и без, все четыре вида блока в
предпросмотре, 375 px и тёмная тема. Плейграунд worktree — фоновым npx vite --port 5186 в
apps/playground, открыть preview_start с url. В конце — «Итог R2» в §6 спеки (на своей ветке),
коммит, пуш в claude.
```

#### Итог R2 — сделано 24.09.2026

Всё из промпта на ветке; vitest (`module-reviews` — 15, плюс `module-faq` и `module-blocks`
целиком), `vue-tsc` пакета, eslint и prettier зелёные. **Ветка R1 (`fb7678a2`) уже влита в эту**
— конфликтов не было, и R3 сливает R2 в R1 без них: экраны, словарь и блок на ветке R2 — ровно
файлы R1. Проверено в браузере на плейграунде worktree (`/panel/reviews`, `/panel/blocks/11`,
`/preview/page/18`): создание строкой и Ctrl+S, 422 под `profile_url` без созданной строки, порядок
без фильтра и в категории (клавиатурой на ручке), 375 px (ящик с «назад», без горизонтального
скролла ни в панели, ни на странице), тёмная тема. Что следующим сессиям надо знать:

- **Слова R2 не дописывал ни одного:** английский пол `reviewsMessages` собран из
  `lang/en/*.php` R1 и совпадает с ним дословно (паритет — `messages.test.ts`, группы `module`,
  `review`, `screen`, `category`). Звёзды в строке списка подписаны `4 / 5` без перевода — это не
  слово. `seen-in` — `aria-label` у языков строки, `no-text` и `no-rating` списку не понадобились.
- **`visible` по `layout` в конструкторе работает** — `module-blocks` чинить не пришлось:
  `formSchema()` ставит `name` из `id`, и условие видит соседа (проверено: сетка — Columns,
  слайдер — Columns и Autoplay, бегущая строка — Speed). Одно «но»: у нетронутого поля значения
  нет вовсе, и условие `in: ["grid", "slider"]` его не узнаёт, хотя шаблон рисует сетку. Спасает
  `sample` с `layout: "grid"` — новый блок начинается с него; блок, созданный агентом без
  `layout`, покажет сетку без поля Columns. Лечить — `same(null, undefined)` в `schema/visible.ts`
  плюс `null` в списке условия; не делал, это правка ядра схем ради одного случая.
- **Рендерер предпросмотра плейграунда стал маленьким Blade** — `apps/playground/server/panel/
blade.ts`: дерево `@if/@elseif/@else`, `@foreach` с `$k => $v` и `$loop->first`, условия внутри
  цикла, выражения (`?:`, `??`, тернарник, `===`/`!==`, `&&`/`||`, `.`, индексы, массивы) и
  короткий список функций (`e`, `nl2br`, `mb_substr`, `implode`, `in_array`, `count`…). Прежний
  регулярками не видел `@if` по элементу цикла и не знал `@else`. Все прежние типы фикстур
  рисуются как раньше; `@marker` у черновика «Карта» по-прежнему остаётся в выводе сломанным.
- **Тип блока «Отзывы» в плейграунде — сам `resources/blocks/reviews.json` R1**, читаемый с диска
  на каждое обращение (`offered()` в `blocks.ts`), а не копия: правка шаблона в пакете видна в
  конструкторе и на странице без перезапуска. Страница `/reviews` (id 18, последней) — четыре
  блока: все с фильтром сеткой, «Имплантация» слайдером с автопрокруткой, шесть бегущей строкой,
  один из скрытой категории «На главную».
- **Фикстура `reviews.ts`** — восемь отзывов по §4.9 (два с фото из новой папки библиотеки
  «Отзывы», один только по-русски, один без оценки, черновик, опубликованный без текста, имя только
  по-русски) и три категории, одна скрыта. Карточка мока отдаёт `initials`, как `Cards` R1.
- **Предложение R1/R3 по экрану:** `photo` в `reviews.form` с `aspect: "1/1"` растягивает портрет
  на всю ширину карточки формы (на 1280 — квадрат ~400 px). Для аватара уместнее `aspect: null`
  и `height: 160`. Файл — R1, я его не трогал.
- **Скрытая панель браузера** не даёт увидеть сам бег строки и автопрокрутку: `ResizeObserver` и
  `rAF` во фрейме молчат (проверено), CSS-анимация стоит. Клоны, `is-running` и длительность
  проверены замером; движение и `prefers-reduced-motion` — на живом сайте в R4.
- Worktree: `node_modules` свой; `dist` собраны у `tokens`, `core`, `schema`, `module-admin`;
  плейграунд — фоновым `npx vite --port 5186` в `apps/playground`. `README` npm-пакета — R3.

### R3 — слияние, MCP, демо, доки

```
Сессия R3 из §6 docs/architecture/WEBX_UI_MODULE_REVIEWS.md: MCP, демо, гайд.

Worktree ../webx-ui-module-reviews, ветка feat/module-reviews. Первым делом: git fetch claude;
git merge claude/feat/module-reviews-panel (конфликт возможен только в спеке — итоги R1 и R2 оба
нужны, и в lang/* — объединить ключи); после этого worktree ../webx-ui-reviews-panel больше не
нужен (git worktree remove, ветку оставить до выпуска).

Прочитать: §§4.8,4.9 спеки и итоги R1, R2; php/packages/module-faq/src/{Mcp/*,Demo/*} и
resources/demo; apps/docs/guide/faq.md и services.md (раздел про services() в блоке); CLAUDE.md §4
про mcp:start (только трубой) и про возврат webx-cms.local после local-режима из копий.

Сделать: ReviewsTools и reviews://catalog §4.8 (создание в транзакции), ReviewsDemo §4.9;
apps/docs/guide/reviews.md (две дороги в шаблон §4.4 и как узнать выбранные категории, виды блока и
как добавить свой, почему нет разметки — решение 3, патч с полем проекта) и ссылка в сайдбаре;
README npm-пакета, разделы MCP и демо в README composer-пакета; wx-rate и reviews в автокомплите
шаблона, если их там нет; тесты MCP и демо; changeset. Гейт php-половины и vitest/vue-tsc npm.

Проверить живьём инструменты через cat … | php artisan mcp:start webx на webx-cms.local в
local-режиме на этом worktree (MONOREPO=… packages.mjs local); до переключения скопировать в
скретчпад composer.json, composer.lock, package.json, package-lock.json и database/database.sqlite,
после — положить назад и composer install. Ничего на сайте не коммитить — это R4.
В конце — «Итог R3», коммит, пуш в claude.
```

#### Итог R3 — сделано 24.09.2026

Всё из промпта на ветке (`80213c9d` и итог следом); ветка R2 влилась fast-forward'ом, конфликтов не
было. Гейт php-половины зелёный (pint, phpstan, полный phpunit — 1542 теста, у модуля 56), vitest
`module-reviews` и `module-blocks`, `vue-tsc` обоих, eslint и prettier на своих файлах. Worktree
`../webx-ui-reviews-panel` снят из git, но **каталог остался**: в нём ещё работал dev-сервер
(`esbuild.exe` занят) — удалить руками, когда его погасят. Что R4 надо знать:

- **MCP проверен живьём** трубой через `mcp:start webx` на `webx-cms.local` в local-режиме на этом
  worktree: 12 инструментов в `tools/list`, создание с фото из настоящей библиотеки, категории по
  названию на любом языке, отказы (оценка 6, `javascript:`, чужой ключ файла, отзыв не из категории,
  категория с отзывами, имя вместо id), `dry_run`, корзина, `reviews://catalog`. Сайт возвращён из
  копий: `check` — registry, `git status` чистый, база — прежняя.
- **Фото с ключом, которого нет в библиотеке, отказывает сам инструмент**, а не тип поля: `wx-media`
  такой ключ пропускает нарочно («нечего проверять» — панель предлагает только то, что есть), и
  опечатка агента молча оставила бы инициалы вместо фото.
- **Отзыв называется только id** — у него нет ни слага, ни якоря своей работы, а имена повторяются.
  `"#12"` тоже принимается. Категория — id или название на любом языке, как у FAQ.
- **`composer require` модуля в local-режиме падает** на частичном обновлении («fixed to v0.37.0 …
  by a partial update»): новый пакет тянет `^0.39` у соседей, а те заперты lock'ом. Работает в два
  шага: `require "webx-ui/module-reviews:*" --no-update`, затем `update "webx-ui/*"` (записано в
  CLAUDE.md §4).
- **Демо** — `reviews.json`: «Сайты» и «Дизайн», восемь отзывов; Ольга в обеих (третья в «Сайтах»,
  вторая в «Дизайне»), Елена — черновик, Давид — только английский текст, Софья — имя только
  по-английски (на `/ru` видна под ним), Ирина — без оценки, Анна — со ссылкой на профиль. Страница
  `/reviews` — сетка, 3 колонки, фильтр; в `company-website` — слайдер «Сайтов», 2 колонки,
  автопрокрутка. На сайте FAQ тоже кладёт блок в `company-website` — там их станет два, это
  нормально. В R4 демо ставится тинкером со своим журналом, как у FAQ.
- **Автокомплит шаблона** знает карточку записи у `faq`, `services` и `reviews`
  (`SOURCE_ITEMS` в `completions.ts`, changeset patch на `module-blocks`); `wx-rate` там уже был
  (`coreTypes`).
- **Гайд** — `apps/docs/guide/reviews.md`, в сайдбаре после FAQ; ссылка на него есть и в
  `categories.md`. `docs:build` не гонял — это полный гейт R4.
- `BlocksField.test.ts` один раз дал unhandled rejection (`insertBefore` в портале тултипа) в общем
  прогоне с `module-reviews` — отдельно и на `main` не воспроизводится; к правке отношения не имеет.

### R4 — выпуск

```
Сессия R4 из §6 docs/architecture/WEBX_UI_MODULE_REVIEWS.md: выпуск module-reviews, оба демо.

Прочитать: итоги R1–R3; CLAUDE.md §5 целиком — «Первую версию нового npm-пакета публикует
человек», «Ручная публикация замораживает диапазоны», «CI на релизном PR ждёт ручного
подтверждения», «Тег php-пакетов ставится до публикации»; docs/architecture/WEBX_UI_PHP_RELEASE.md;
итог F6 в docs/architecture/WEBX_UI_MODULE_FAQ.md — тот же выпуск 24.09.2026;
docs/architecture/WEBX_UI_RELEASE_SPEED.md — релиз с 24.09.2026 идёт иначе; память
webx-cms-local-demo-site, webx-cms-homelab-deploy и release-speed.

До релиза (руками пользователя, сессия напоминает и проверяет): репозиторий-зеркало
webx-ui/module-reviews на GitHub.

Сделать: погасить dev-серверы; полный гейт npm и php/ (PHP 8.4); module-reviews в
scripts/php-smoke.sh рядом с module-faq и smoke против MariaDB; PR, зелёный CI, gh pr merge — PR
встаёт в очередь мержа, main вливать руками не нужно (впервые: проверить, ставит ли в очередь
команда с --squash, и записать в CLAUDE.md §5); релизный PR открывает App — проверить, что его CI
стартовал сам, без approve (первый раз после WEBX_UI_RELEASE_SPEED.md, записать итог); снять changeset-release/main в отдельный worktree, pnpm
install, dist, pnpm pack @webx-ui/module-reviews и проверить диапазоны @webx-ui/* в тарболе;
первая публикация — пользователь из своего терминала с 2FA, затем Trusted Publishing (webx-ui /
webx-ui / release.yml); мерж релизного PR; npm view всех поднятых пакетов и тег php-v<версия>;
webx-ui/module-reviews на Packagist. Удалить ветку feat/module-reviews-panel.

Демо: webx-cms.local — module-reviews в scripts/packages.mjs, link-panel.sh, composer require,
импорт и ...reviews() в resources/js/admin.ts руками (webx:panel --sync не трогает существующий
файл), migrate, webx:blocks:offered --install --module=reviews, cache:clear (словарь), демо §4.9
тинкером со своим журналом (как FAQ — webx:demo насеял бы второй набор всех модулей), npx vite
build; хомлаб — то же в registry, npm ls @webx-ui/module-admin — одна версия, коммит и пуш в Gitea.
Строку реестра в WEBX_UI_COMPOSER_PACKAGES.md и CLAUDE.md §§2,6 — отдельным docs-PR.

Проверить живьём на обоих: страница /reviews — сетка, фильтр, звёзды, инициалы без фото; услуга
со слайдером — кнопки, автопрокрутка выключается при prefers-reduced-motion; бегущая строка на
временно переключённом блоке; отзыв без русского текста не виден на /ru; в <head> ни одного
Review; список с перетаскиванием и форма на настоящем телефоне (делает пользователь).
```

## 7. Отложено

- Общий `RecordQuery` в `module-admin` для `services()`, `reviews()` и дальше `team()` —
  выносить на третьем потребителе (`module-team`), когда видно, что у трёх общего, а не
  угадывать по двум.
- Своя страница `/reviews` — когда попросят; конфиг и маршрут заводить тогда же, не впрок.
- Разметка `Review`/`AggregateRating` — если появится источник отзывов не от самой организации
  (импорт с площадок) или сниппеты вернут.
- Выбор отдельных отзывов в `wx-collection` (`Selection::ids`).
- Приём отзывов с сайта и модерация.

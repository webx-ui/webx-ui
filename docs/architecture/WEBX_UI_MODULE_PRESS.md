# `webx-ui/module-press` — спецификация и план реализации

Статус: спроектирован 27.09.2026, промпты сессий P1–P4 — в §6. Пакеты — `webx-ui/module-press`
(composer) и `@webx-ui/module-press` (npm).

«Пресса о нас» — издания (журнал, газета, портал) и материалы в них: статья, интервью, колонка,
комментарий эксперта. Издание — это логотип, название, короткое описание и ссылка на сайт
издания; у издания своя страница со списком материалов. Материал ведёт наружу: на статью по
адресу или на PDF из библиотеки.

Образец — `client-site.test`: модель `app/Models/MediaSource.php` (материалы там — JSON-массив
`publications` внутри издания), `app/Http/Controllers/ClientSite/MediaSourcesController.php`,
вьюхи `resources/views/client-site/pages/media/show.blade.php`, `components/media-section.blade.php`
(полоса логотипов «Featured in») и `components/media-catalog.blade.php` (каталог двумя группами по
`contribution_type`), экран панели `packages/webx/templates/MediaSources/MediaSourceEdit.vue`.

Каркас — у уже выпущенных модулей: адрес под приставкой и SEO — как у рецептов и событий
(`WEBX_UI_MODULE_RECIPES.md` §5.3, `WEBX_UI_MODULE_EVENTS.md` §4.3), список с перетаскиванием и
`WxListDetail` — как у отзывов (`WEBX_UI_MODULE_REVIEWS.md` §4.6), предложенные блоки — как у
FAQ и отзывов (`BlockOffers`). Нового общего контракта модуль не приносит.

## 1. Границы

**Внутри:** издания и материалы, виды материала, ручной порядок изданий и материалов внутри
издания, переводы, страница издания под приставкой, SEO и разметка, хелпер `press()`, три
предложенных типа блока (логотипы, каталог изданий, лента материалов), экраны панели, API, MCP,
демо.

**Снаружи:** индекс `/{приставка}` маршрутом модуля (решение 6 — это страница `module-pages` с
блоком); категории изданий (решение 3); черновики и версии (решение 9); страница материала на
сайте (материал всегда ведёт наружу); загрузка статьи с чужого сайта по ссылке, скриншоты и
превью ссылок; импорт со старого клиента (§7).

## 2. Принятые решения (не переоткрывать)

Решения 1–5 приняты пользователем 27.09.2026, остальные предложены в том же обсуждении и не
оспорены.

1. **Имя — `module-press`, id модуля `press`**, в панели «Пресса» / «Press». Слово «media»
   занято библиотекой файлов (`module-media`, MCP `media_*`).
2. **Сущности — издание (`outlet`) и материал (`article`).** Слово «публикация» не
   используется нигде — ни в коде, ни в интерфейсе: оно значит и издание, и статью, и
   «опубликовано».
3. **Категорий нет.** Вместо `contribution_type` клиента — **вид материала** (`kind`) у материала,
   а не у издания: в одном журнале бывают и авторская колонка, и цитата. Виды — список ключей в
   конфиге `webx-press.kinds`, по умолчанию `mention` (упоминание), `interview` (интервью),
   `expert_comment` (комментарий эксперта), `authored` (авторский материал). Подписи — слова
   `webx-press::kinds.<ключ>`; свой вид сайт добавляет строкой в конфиг и словом в
   `lang/vendor/webx-press`. Вид необязателен; ключ, которого больше нет в конфиге, при чтении —
   «без вида», при записи — 422.
4. **Мультиязычность заложена целиком:** название и описание издания, заголовок и краткое
   описание материала — переводимые. Какой язык клиент заполнил, тот и будет. Правила видимости —
   решение 7.
5. **Одно описание у издания — `summary`.** `intro_heading` и `intro_text` клиента не переносятся:
   заголовки частей страницы — слова опубликованной вьюхи сайта.
6. **Порядок ручной, два:** изданий — общий (`position`, перетаскивание в списке панели),
   материалов — внутри издания (`position`, порядок строк в форме). Лента материалов из всех
   изданий (блок, `press()->articles()`) идёт **по дате**, от новых; без даты — в конце.
7. **Видимость на языке страницы.**
   - Материал виден, если у издания он есть и у него есть заголовок на этом языке. Подстановки
     языка по умолчанию для заголовка нет — непереведённая статья на этой версии сайта не
     показывается. Краткое описание без перевода просто не печатается.
   - Издание видно, если оно опубликовано и у него есть **хотя бы один видимый материал** на
     этом языке. Название издания без перевода берётся **с любого языка, где оно есть** (сначала
     язык по умолчанию): это имя собственное, и прятать издание из-за формальности нельзя — на
     этом же споткнулись отзывы (открытый вопрос итога R4 спеки отзывов).
   - Страница издания без видимых материалов на языке — 404 на этом языке и нет в карте сайта и
     hreflang для него.
8. **Ссылка материала — адрес или PDF.** `url` (только `http(s)://`) и `file` (`wx-file`,
   `accept: application/pdf`, файл библиотеки). Нужно хотя бы одно — иначе 422 под `url`. Есть
   оба — ведёт `url`, PDF печатается второй ссылкой. Подпись ссылки («Read article») — слово
   вьюхи, не поле материала.
9. **Черновиков и версий нет** — `published` у издания и корзина, как у отзыва и вопроса FAQ.
   Материал отдельной публикации не имеет: он виден, когда видно издание (решение 7). Спрятать
   один материал, не удаляя, — **колонка `is_hidden`**, а не `hidden` (CLAUDE.md §4 — `hidden` у
   модели Eloquent занят).
10. **Дата материала — дата и точность.** `published_on` (`date`) и `date_precision`
    (`day | month | year`, по умолчанию `day`). Печатает `Rendering\When` на языке страницы:
    «12 августа 2023», «август 2023», «2023». Переводимого «Aug, 2023» нет.
11. **У издания своя страница, `/{приставка}/{слаг}`.** Приставка `webx-press.prefix`, по
    умолчанию `press`, пустой не бывает. Страницы изданий выключаются `webx-press.pages = false`:
    тогда нет ни маршрута, ни `LinkSource`, ни карты сайта, а логотипы в блоках ведут на сайт
    издания. Слаг — переводимый, как у событий.
12. **Индекса у модуля нет** — ни маршрута, ни конфига под него. `/{приставка}` — страница
    `module-pages` с блоком «Каталог изданий» (как `/faq`); крошки издания берут её из реестра
    адресов, нет её — звено пропускается (как у рецептов).
13. **«Показывать в полосе логотипов» — флаг `featured` у издания.** Блок логотипов выбирает
    «все» или «только отмеченные». Выбор отдельных изданий руками в блоке — нет (§7).
14. **Разметка — `ItemList` материалов на странице издания** (`HasStructuredData` у издания —
    итог P1), у каждого —
    `Article` с `headline`, `url`, `datePublished` (только при точности `day`) и `publisher` —
    `Organization` издания (`name`, `url`, `logo`). Rich results от этого не ждём и Rich Results
    Test не проверяем: смысл — связать сайт с изданиями для поиска и агентов. «about Person Katia»
    клиента — специфика сайта, это его опубликованная вьюха.
15. **Панель — `WxListDetail`, как у отзывов:** слева издания (логотип, название, число
    материалов, «виден на»), справа форма издания вкладками **General · Articles · SEO**.
    Материалы — повторитель (`wx-repeater`) на вкладке Articles: карточки сворачиваются, строка
    свёрнутой карточки — «#1 · заголовок», как на скриншотах клиента. Сохраняется издание целиком,
    одной кнопкой.
16. **Материалы в базе — своя таблица, а не JSON в издании.** Повторитель в форме — только вид
    редактирования: сервер сверяет строки по `id` и пишет таблицу (§4.6). Таблица нужна ленте по
    дате через все издания, MCP и разметке.

## 3. Схема

```
press_outlets
  id
  title          json nullable       -- переводимое
  slug           json nullable       -- переводимый; пустой при pages = false не требуется
  summary        json nullable       -- переводимое, простой текст
  logo           json nullable       -- значение wx-media
  website_url    string(2048) nullable
  featured       boolean default false
  published      boolean default false
  position       int default 0
  extra          json nullable       -- поля проекта
  softDeletes, timestamps

press_articles
  id
  outlet_id      foreignId → press_outlets, cascadeOnDelete
  title          json nullable       -- переводимое
  excerpt        json nullable       -- переводимое, простой текст
  kind           string(32) nullable -- ключ из webx-press.kinds
  published_on   date nullable
  date_precision string(5) default 'day'   -- day | month | year
  url            string(2048) nullable
  file           json nullable       -- значение wx-file (PDF)
  is_hidden      boolean default false
  position       int default 0       -- порядок внутри издания
  extra          json nullable
  timestamps                         -- без softDeletes: материал живёт и умирает с изданием
```

- Все миграции — `2026_01_01_*`: внешний ключ только на свою таблицу (CLAUDE.md §4 о сортировке
  миграций). `date_precision` — `string(5)` при самом длинном значении в пять символов, дефолт
  `day` короче колонки (CLAUDE.md §4 про MariaDB).
- Издание в корзине материалы не удаляет (у материала нет `softDeletes`, и он невидим, потому что
  невидимо издание); окончательное удаление уносит их каскадом. Тест включает
  `foreign_key_constraints` у sqlite (CLAUDE.md §4).
- Индекс `press_articles (outlet_id, position)` и `(published_on)` — лента по дате.

## 4. Модуль

### 4.1. Модели

- `Outlet` — `HasSeo`, `HasBreadcrumbs`, `HasStructuredData`, `HasExtra`, `HasTranslations`
  (`title`, `slug`, `summary`), `SoftDeletes`. `articles()` — `hasMany` по `position`.
  `scopeVisibleIn(string $locale)` — решение 7 (через `whereHas` видимых материалов). Новое
  издание встаёт в конец (`max + 1`). `displayTitle(string $locale)` — решение 7, любой язык.
- `Article` — `HasExtra`, `HasTranslations` (`title`, `excerpt`). `scopeVisibleIn($locale)` —
  не `is_hidden`, заголовок на языке. `target(): ?string` — `url`, иначе адрес PDF.
- Namespace `WebxUi\Press\Models`, классы `Outlet` и `Article` без префикса `Press`; у таблиц
  префикс `press_`, как у остальных модулей.

### 4.2. Зависимости

`require`: `module-admin`, `module-media` (логотип — `wx-media`, PDF — `wx-file`), `module-seo`,
`routing`, `localization`, `mcp`, `module-blocks` (предложенные блоки). `suggest`:
`module-pages` (страница `/{приставка}` с каталогом). `require-dev`: `module-pages` — демо и
тесты крошек.

### 4.3. Адреса

| Тип            | Форматтер                        | Пример              |
| -------------- | -------------------------------- | ------------------- |
| `press-outlet` | `Prefixed($prefix, Slug::class)` | `press/tatler-asia` |

Как у событий: `OnConflict::Fail`, пустая приставка — исключение в `boot()`, смена приставки —
`webx:routes:rebuild --type=press-outlet`, тип — `LinkSource` (меню может сослаться на
издание). При `pages = false` тип не регистрируется вовсе. Адрес есть только у издания, видимого
на этом языке (решение 7): форматтер спрашивает видимость, а наблюдатель материалов обновляет
строку реестра издания, когда меняется набор его видимых материалов.

### 4.4. Публичная часть

**Страница издания** `outlet.blade.php` (вьюха из конфига с фолбэком на пакет, публикуется в
`resources/views/vendor/webx-press`, макет — общий шов), части — `@include`:

1. логотип (нет — название текстом), название, `summary`;
2. «N материалов» и ссылка на сайт издания (`rel="noopener"`, новое окно);
3. материалы в своём порядке — `partials/article.blade.php`: заголовок ссылкой на `target`
   (новое окно, `rel="noopener"`), вид (слово), дата (§4.5), краткое описание, у PDF — пометка
   «PDF» и вторая ссылка, если есть и адрес.

Страница рисует тело до головы (`@webxPartAssets`, CLAUDE.md §4 про `@webxBlocks`), чтобы блоки,
вставленные в опубликованную вьюху, приносили свои стили.

### 4.5. Как печатается дата

`Rendering\When` — один помощник для страницы, карточек и MCP-каталога.
`Carbon::translatedFormat()` на языке страницы:

| Точность | Печать (ru / en)                  |
| -------- | --------------------------------- |
| `day`    | 12 августа 2023 / August 12, 2023 |
| `month`  | август 2023 / August 2023         |
| `year`   | 2023                              |
| нет даты | ничего                            |

`month` в русском — **именительный** падеж («август», не «августа»): проверить тестом, формат
`F` у Carbon для `ru` это даёт не во всех версиях — тогда `LLLL`-подобный `isoFormat('MMMM YYYY')`.

### 4.6. Форма издания и материалы

Экран `press.outlet-form`, вкладки:

- **General** — логотип (`wx-media`), название (`localized`), `published`, `featured` («В полосе
  логотипов»), адрес (`wx-slug`, нет при `pages = false`), сайт издания, `summary`
  (`wx-textarea`, `localized`), карточка `project-fields`.
- **Articles** — `wx-repeater` с именем `articles`; поля строки: `id` (невидимое, см. ниже),
  `title` и `excerpt` (`localized`), `kind` (`wx-select`, варианты из конфига — сервер кладёт
  их в `props.options` при отдаче экрана), `published_on` (`wx-date-picker`, только дата),
  `date_precision` (`wx-segmented`), `url`, `file` (`wx-file`, `accept: document` — поле библиотеки знает виды, а не mime; «только PDF»
  проверяет `OutletForm`, итог P1),
  `is_hidden` («Не показывать»). Заголовок свёрнутой карточки — «#N · заголовок на языке панели».
- **SEO** — карточка `wx-seo` патчем от `module-seo`.

**Строке нужен `id`, а повторитель хранит только объявленные ключи** (`RepeaterType::store()`).
**P1 первым делом** проверяет, переживает ли значение узла с `visible: false` путь
`ScreenValues` туда и обратно. Нет — завести в `module-admin` тип поля `wx-hidden` (значение
как есть, ничего не рисует; npm-половина — P2), с тестом: он же пригодится любому повторителю,
который пишет строки в таблицу.

**Сохранение** — `Panel\OutletForm::save()` в транзакции: строки с `id` своего издания
обновляются, без `id` — создаются, отсутствующие — удаляются; `position` — порядок строк; чужой
`id` — 422 под `articles`. Ошибки строки — под `articles.<n>.<поле>` (так их ищет
`WxScreenRepeater`; проверить с полем `title.en` — CLAUDE.md §4 про ошибку переводимого поля).

### 4.7. SEO и разметка

- `HasSeo` у издания; дефолты — `title` из названия, `description` из `summary`, `og:image` —
  логотип.
- Крошки: страница `/{приставка}` из реестра (если есть) → издание.
- Разметка — решение 14.
- Карта сайта — видимые издания на тех языках, где они видимы; hreflang — только эти языки.

### 4.8. Хелпер `press()` и предложенные блоки

`Rendering\PressQuery`, по образцу `ReviewQuery`/`EventQuery`:

| Шаг                   | Что делает                                                         |
| --------------------- | ------------------------------------------------------------------ |
| `press()->outlets()`  | Издания в своём порядке (по умолчанию)                             |
| `press()->articles()` | Материалы всех изданий по дате, от новых                           |
| `featured()`          | Только отмеченные для полосы логотипов (для изданий)               |
| `kind('authored')`    | Материалы этого вида; издания — у которых есть материал этого вида |
| `only([3, 7])`        | Только эти, в этом порядке                                         |
| `except($outlet)`     | Кроме этих                                                         |
| `take(6)`             | Не больше; null или ноль — все; считается после видимости          |
| `locale('uk')`        | Язык карточек; по умолчанию язык страницы                          |
| `get()`, `first()`    | Список карточек или одна                                           |

Карточка издания: `id`, `url` (страница издания или `null` при `pages = false`), `link` (куда
ведёт логотип: `url`, иначе сайт издания), `title`, `summary`, `logo`, `website`, `featured`,
`count` (видимых материалов), `kinds` (ключи видов его видимых материалов), `fields`. Карточка
материала: `id`, `outlet` (`id`, `title`, `url`, `logo`), `title`, `excerpt`, `kind`,
`kind_label`, `date` (`Y-m-d`), `when` (§4.5), `target`, `url`, `pdf`, `fields`. Строка `press()`
— в `webx:doctor`.

Предложенные типы блоков (`BlockOffers`, `webx:blocks:offered --install --module=press`); блоки
зовут хелпер сами, `wx-collection` не нужен — у модуля нет категорий:

1. **`press-logos` — «Нас читают».** Поля: заголовок (`localized`), `featured`
   («только отмеченные», по умолчанию вкл.), `limit`. Сетка логотипов ссылками (`link`
   карточки). Замена `media-section` клиента.
2. **`press-outlets` — каталог изданий.** Поля: заголовок, `group` (`wx-switch`, «группами по
   виду материала»), `kinds` (`wx-select multiple` — какие виды и в каком порядке группы; пусто —
   все из конфига). Без групп — сетка карточек изданий; с группами — секция на вид, в секции —
   издания, у которых есть материал этого вида (издание бывает в двух секциях). Заголовки секций
   — `kind_label`. Замена `media-catalog` клиента (две группы — это `kinds: [authored,
expert_comment]`).
3. **`press-articles` — лента материалов.** Поля: заголовок, `kinds`, `limit` (по умолчанию 6).
   Материалы по дате с логотипом издания.

Варианты `kinds` в схеме блока — ключи конфига, **подставляются при установке предложения**
(`BlockOffers` отдаёт документ, собранный из `resources/blocks/*.json` и конфига). Сменил виды
после установки — правит тип блока в панели; README это говорит. Пустое поле блока — умолчание
шаблона (итог F1 спеки FAQ, `ResolvesMissing`). Стили нейтральные, на `currentColor` и `em`, без
`--wx-*`; `[hidden]` объявлен явно у всего, чему дан `display`.

### 4.9. Панель

- Один пункт меню «Press» без своей группы — пункт у модуля один (у отзывов группа, потому что
  пунктов два). Иконка из набора (`icons.test.ts`; кандидат — `newspaper`, нет — ближайшая из
  имеющихся).
- `WxListDetail`, копия устройства отзывов: маршрут `/press`, открытое издание —
  `?outlet=<id|new>`, рядом `q`, `view=trashed`; «Новое издание» строкой, `POST` на первом
  сохранении; перетаскивание изданий; Ctrl+S и вопрос при уходе. Строка списка — логотип-миниатюра
  (нет — первая буква), название, «N материалов», «виден на: ru, en», признак «опубликовано, но
  не видно нигде» (нет ни одного видимого материала).
- Права: `press.view`, `press.manage`. Id модуля панели — `press` (MCP-префикс `press_`).

### 4.10. API панели

Формы зафиксированы заранее, чтобы P1 и P2 шли параллельно:

```
GET    /api/cms/press                ?trashed=1&search=
  → { data: [{ id, title, logo: { thumb } | null, published, featured, position, locales,
               articles_count, updated_at, deleted_at }] }                 без meta и пагинации
POST   /api/cms/press                { values }            → 201 { data: { outlet, values, prefix } }
GET    /api/cms/press/{id}           → { data: { outlet, values, prefix } }
PUT    /api/cms/press/{id}           { values }            422 под именем поля
DELETE /api/cms/press/{id}
POST   /api/cms/press/{id}/restore   → голый ресурс строки списка
POST   /api/cms/press/reorder        { ids }
GET    /api/cms/screens/press.outlet-form                  как у всех описанных экранов
```

- `title` в списке — на языке панели, иначе на любом, где есть, иначе `#id`.
- `locales` — языки, на которых издание видимо по решению 7 без учёта `published` (отдельный
  флаг, чтобы список мог сказать «опубликовано, но не видно нигде»).
- `outlet` в ответе формы — `{ id, title, published, deleted_at, url }`; `prefix` — для
  `wx-slug` (`null` при `pages = false`).
- `values` — `title`, `slug`, `summary` картами языков, `logo`, `website_url`, `featured`,
  `published`, `articles` (строки в своём порядке: `id`, `title`, `excerpt` картами языков,
  `kind`, `published_on` `Y-m-d`, `date_precision`, `url`, `file`, `is_hidden`, поля проекта
  материала), `seo`, поля проекта.
- `POST` и `PUT` — одна `OutletForm::save()` в транзакции (§4.6): отказ не оставляет строк.

### 4.11. MCP

`press_list`, `press_get`, `press_create`, `press_update`, `press_delete`, `press_reorder` — через
те же `Panel\*`; материалы — отдельными инструментами, чтобы агент не пересылал весь список ради
одной статьи: `press_articles_add` (в конец или на `position`), `press_articles_update`,
`press_articles_delete`, `press_articles_move`. Издание называется id или названием на любом
языке; материал — id. Вид — ключ из конфига, описание инструмента перечисляет допустимые. PDF
агент ставит ключом файла библиотеки. Ресурс `press://catalog`: издания в своём порядке, у
каждого материалы с `visible_in`, `written_in`, `kind`, `when`, `target`. Строка без языка —
язык по умолчанию (итог F5 спеки FAQ).

### 4.12. Демо

`PressDemo`, `resources/demo/press.json` (en и ru): четыре издания с логотипами-SVG (кладутся в
библиотеку демо-файлами), восемь материалов всех четырёх видов, один — PDF, один — PDF и адрес,
один скрыт, один только по-английски, у двух точность `month`, у одного `year`; одно издание
неопубликованное, одно без `featured`. При `module-pages` — страница `/{приставка}` (ребёнок
главной): полоса логотипов, под ней каталог группами. `requires()` динамический: `media`,
`blocks`, `pages` — если стоит.

### 4.13. Регистрации

Всё из CLAUDE.md §4 («прописать в `php/` четыре раза», «раздел панели — в четырёх местах»):
`php/composer.json` (`require`, `autoload-dev`), `phpunit.xml.dist`, `phpstan.neon.dist`,
`Setup\Catalogue`, `extra.webx.npm`/`extra.webx.panel`, `apps/playground/src/panel/main.ts`,
`scripts/packages.mjs` в `webx-cms.local` и строка в `scripts/php-smoke.sh` рядом с
`module-events` (P4).

### 4.14. Тесты, которые обязательны

- Видимость (решение 7): материал без заголовка на языке не виден; издание без видимых
  материалов не видно, его страница — 404 на этом языке и нет в карте сайта и hreflang; название
  издания без перевода берётся с любого языка; `is_hidden` прячет материал.
- Сохранение формы через **настоящий путь записи**: строки с `id` обновляются, новые создаются,
  пропавшие удаляются, порядок — порядок строк, чужой `id` — 422, отказ посередине не оставляет
  строк; `id` строки переживает `ScreenValues` (§4.6).
- Ошибка `articles.0.title.en` доходит до поля (не только голое имя).
- `url` не `http(s)` и материал без `url` и `file` — 422; `file` не PDF — 422; вид не из конфига
  — 422.
- `When` — все строки §4.5 на ru и en, `month` в именительном.
- `press()`: каждая строка таблицы §4.8; лента по дате, без даты — в конце; `take` после
  видимости; `kind()` у изданий — «есть материал этого вида».
- Разметка: `ItemList` из `Article` с `publisher`; `datePublished` только при `day`.
- `pages = false`: нет маршрута, нет `LinkSource`, `link` карточки — сайт издания.
- Каждый предложенный блок рисуется на своём `sample` (иначе `webx:blocks:offered` оставит его
  черновиком — итог F1 спеки FAQ), `press-outlets` — и с группами, и без.
- Окончательное удаление издания уносит материалы (sqlite с `foreign_key_constraints`).

## 5. Слова

Все ключи `webx-press::*` на десять языков панели заводит **P1** — серверные и нужные панели:
модуль и меню, список (`new`, `trashed`, `visible-nowhere`, `articles-count` — счёт в конце
строки, CLAUDE.md §4 про `:count` на единице), форма (подписи полей экрана, `date-precision.*`,
`hidden-help`), виды (`kinds.*`), отказы, слова вьюх (`read`, `pdf`, `visit`, «N материалов»). P2
держит английский пол в `messages.ts` и тест паритета; чего не хватило — дописывает сам.

## 6. Пошаговый план

**Выпуск один, в конце** (P4), до него ни PR, ни ожидания CI. Ветка `feat/module-press`
(worktree `../webx-ui-module-press`, спека уже там), параллельная сессия — на своей ветке, её
сливает следующая.

| Сессия | Ветка / worktree                              | Что                                                    |
| ------ | --------------------------------------------- | ------------------------------------------------------ |
| **P1** | `feat/module-press`                           | php `module-press`: §§3–5 кроме MCP и демо             |
| **P2** | `feat/press-panel` / `../webx-ui-press-panel` | npm `module-press`: панель по §4.10, плейграунд, блоки |
| **P3** | `feat/module-press`                           | слияние P2, MCP, демо, гайд, README, doctor            |
| **P4** | `feat/module-press`                           | выпуск, оба демо                                       |

P1 ∥ P2 (API — §4.10). В конце каждой сессии — «Итог Pn» сюда и строка в память
`custom-modules-workflow`.

Общее для всех: gh не в PATH — `"C:\Program Files\GitHub CLI\gh.exe"`; пушить в `claude`, не в
`origin`; php-гейт — `composer lint && composer analyse && composer test` из `php/` на
`C:\Work\OSPanel\modules\PHP-8.4\php.exe` (в свежем worktree сначала прогреть манифест Testbench
последовательно — CLAUDE.md §4 «И то же самое на пустом `vendor`»); npm — точечно
`npx vitest run <файлы> --pool=forks --poolOptions.forks.singleFork` **из корня worktree**,
`npx vue-tsc -p tsconfig.json --noEmit` в пакете, eslint и prettier на своих файлах. `pnpm` в
worktree с симлинком на `node_modules` не запускать (CLAUDE.md §4).

### P1 — `module-press`, php

```
Сессия P1 из §6 docs/architecture/WEBX_UI_MODULE_PRESS.md: composer-пакет webx-ui/module-press.
Идёт параллельно с P2.

Worktree ../webx-ui-module-press, ветка feat/module-press (спека уже там). Первым делом:
git fetch claude; git merge claude/main. В php/: composer install, прогреть манифест Testbench.
PR не открывать.

Прочитать: §§2–5 спеки; php/packages/module-reviews целиком — образец списка, порядка, формы,
Cards/Query/helpers и предложенного блока; php/packages/module-events — адрес под приставкой,
переводимый слаг, SEO, крошки, разметка через Seo::push, Rendering\When; module-admin
Screens/Types/RepeaterType.php и ScreenValues; module-media FileFieldType (accept); CLAUDE.md §4
про hidden/visible у модели, длину дефолта string-колонки, foreign_key_constraints у sqlite,
ошибку переводимого поля, «Главная не получает адрес».

Сначала: проверить, переживает ли id строки wx-repeater путь ScreenValues туда и обратно
(§4.6). Нет — тип wx-hidden в module-admin с тестом, тем же коммитом, и крупно сказать в итоге:
P2 нужна npm-половина типа.

Сделать: php/packages/module-press — composer.json с extra.webx и autoload files, провайдер,
конфиг (prefix обязателен, pages, kinds, views), миграции §3, модели §4.1, адреса §4.3,
страница издания §4.4, When §4.5, экран press.outlet-form и OutletForm::save §4.6 (kind —
варианты из конфига в props.options при отдаче экрана), SEO и разметка §4.7, PressQuery, Cards,
press() и три предложенных блока §4.8 (kinds в схеме — из конфига при установке), API §4.10
(формы — ровно как там, по ним параллельно пишется панель), права и модуль панели §4.9, все
слова §5 на десять языков, строка press() в webx:doctor, README, LICENSE; регистрации §4.13
кроме плейграунда, сайта и smoke; тесты §4.14; changeset на @webx-ui/php.

Не делать: npm (P2), MCP и демо (P3). Если форма ответа API должна отличаться от §4.10 —
поправить §4.10 тем же коммитом и сказать об этом в итоге крупно. В конце — «Итог P1», коммит,
пуш в claude.
```

### P2 — `module-press`, npm

```
Сессия P2 из §6 docs/architecture/WEBX_UI_MODULE_PRESS.md: npm-пакет @webx-ui/module-press.
Идёт параллельно с P1, php-половину не трогает.

Начало: git fetch claude; git worktree add ../webx-ui-press-panel -b feat/press-panel
claude/feat/module-press (спека там); pnpm install --frozen-lockfile в этом worktree (каталог
обычный, node_modules будет свой — CLAUDE.md §4 про pnpm в worktree); собрать dist у tokens,
core, schema, module-admin. PR не открывать.

Прочитать: §§2,4.6,4.8–4.10,5 спеки; packages/module-reviews целиком — образец (WxListDetail,
перетаскивание, форма описанным экраном, i18n и тест паритета); итоги R2 и EV2 в спеках отзывов и
событий; WxScreenRepeater в module-admin; apps/playground/server/panel/reviews.ts и
renderTemplate() в предпросмотре блоков плейграунда.

Сделать: packages/module-press (версия 0.0.0) — модуль панели по §4.9, форма издания вкладками
General · Articles · SEO, свёрнутая карточка материала — «#N · заголовок» (если WxScreenRepeater
так не умеет — научить его подписи строки по полю, в module-admin, с тестом); i18n с английским
полом и тестом паритета (ключи — §5; недостающее дописать в php/packages/module-press/lang и
сказать в итоге); плейграунд: apps/playground/server/panel/press.ts по формам §4.10 (экран
собирается из php/packages/module-press/resources/screens, если P1 уже его запушил, иначе своя
копия в фикстуре и пометка в итоге), модуль в main.ts, три типа блока §4.8 и страница /press с
каталогом в фикстурах; vitest (вкладки — mousedown); changeset. Если P1 скажет про wx-hidden —
npm-половина типа в module-admin.

Проверить в браузере на плейграунде: создать издание с тремя материалами (адрес, PDF, оба),
свернуть и развернуть, переставить материалы и издания, сохранить, перезагрузить — порядок и
значения на месте; 422 под полем материала; три блока в предпросмотре; 375 px и тёмная тема.
Плейграунд worktree — фоновым npx vite --port 5187 в apps/playground, открыть preview_start с
url. В конце — «Итог P2» в §6 спеки (на своей ветке), коммит, пуш в claude.
```

#### Итог P2 (27.09.2026)

Ветка `feat/press-panel` (worktree `../webx-ui-press-panel`, от `claude/feat/module-press` на
`6f7713b0`). **P1 к концу сессии не запушил ничего**, поэтому формы — ровно по §4.10, а экран,
блоки и слова — своими копиями, которые уступают место файлам P1, как только те появятся на ветке.

- **`packages/module-press`** (0.0.0, changeset minor): `press()` — один модуль панели `press`,
  без группы, иконка `newspaper`. `WxPressPage` — копия устройства отзывов без категорий:
  `WxListDetail`, `?outlet=<id|new>`, `q`, `view=trashed`, «New outlet» строкой, `POST` на первом
  сохранении, перетаскивание изданий (`useItemOrder`, порядок только общий), Ctrl+S, вопрос при
  уходе. Строка — логотип в своём боксе 64×32 (в квадратном аватаре широкий логотип становился
  полоской в 11 px), без логотипа — инициалы; звезда у `featured`; «Articles: N» / «No articles
  yet»; языки; «опубликовано, но не видно нигде» — предупреждением. `WxOutletPane` — описанный
  экран `press.outlet-form`, отдаёт `wx-slug` префикс через `provideRecordAddress` (путь — из
  `outlet.url` формы), в шапке «Open on the site», когда `url` есть.
- **Подпись свёрнутой карточки «#N · заголовок» учить не пришлось:** `WxRepeater` умеет это
  давно — `props.itemLabel: "title"` в узле, и переводимый заголовок берётся на языке правки.
- **Зато пришлось научить повторитель ошибкам строки — в core и schema, не в module-admin**
  (changeset: core minor, schema patch). Раньше `articles.0.title.en` рендерер клал только на
  весь повторитель, а поле `title` внутри строки искало `form.errors['title']` — то есть
  показывало ошибку **названия издания**, во всех строках сразу, а у отказанной строки не было
  ничего. Теперь у `WxRepeater` проп `rowErrors` (ошибки по позиции): каждая строка получает
  свой контекст формы (`internal/FormScope.ts`), отказанная строка раскрывается сама и краснеет
  заголовком. `WxScreenRepeater` сам раскладывает `articles.<n>.<поле>[.<язык>]` по строкам — P1
  ничего настраивать не нужно, достаточно имён ровно как в §4.6. Сообщение по-прежнему видно ещё и
  под всем списком (рендерер кладёт его на `articles`) — это оставлено нарочно: по этому ключу
  `ScreenTabs` переключается на вкладку с ошибкой. Страница доков повторителя дополнена.
- **`id` строки в панели доживает до сервера без `wx-hidden`**: повторитель сливает правку в
  объект строки, а не пересобирает его, так что `id` едет туда и обратно, даже когда узла под
  него нет. В копии экрана узел `id` — `wx-input` с `visible: false`; как его переживёт
  `ScreenValues`, решает P1 (§4.6). Если P1 заведёт `wx-hidden` — нужна npm-половина типа, и её
  здесь **нет**.
- **`accept` у `wx-file` — это вид медиа, а не MIME:** `MediaValues::accept()` принимает только
  значения `MediaType::all()`. В копии экрана `props.accept: "document"` (пикер показывает только
  документы — проверено), а «только PDF» должен проверять `OutletForm` сам — в §4.6 написано
  `accept: application/pdf`, так не сработает.
- **Слова:** английский пол — `pressMessages`, группы `module`, `outlet`, `screen`, `kinds`;
  тест паритета — `describe.skipIf`, пока нет `php/packages/module-press/lang/en`. Ключи мои, а
  не согласованные с P1: **P3 сводит их по тесту паритета.** Что понадобилось панели сверх §5:
  `outlet.featured`, `outlet.no-articles`, `outlet.open`, `outlet.address-moving`, `outlet.seen-in`,
  подписи экрана `screen.*` (включая `date-precision-{day,month,year}`, `kind-none`,
  `hidden-help`). В `lang` P1 я ничего не писал — только отметил здесь.
- **Плейграунд:** `server/panel/press.ts` — пять изданий по §4.12 (одно неопубликованное, одно
  без `featured`, одно «опубликовано, но нигде», материал только по-английски, скрытый, PDF,
  PDF + адрес, точности `month` и `year`), формы §4.10, 422 под `articles.<n>.url|file|kind|
date_precision|title.<язык>` и `articles` за чужой `id`, ничего не записав. Логотипы и PDF —
  папка «Пресса» в `media.ts` (PDF — настоящий, открывается). Варианты `kind` кладёт в
  `props.options` сервер при отдаче экрана. `screens.ts` и `blocks.ts` берут файлы P1, если они
  есть (`resources/screens/outlet-form.json`, `module-seo/.../press.outlet-form.json`,
  `resources/blocks/press-*.json`), иначе копии в `server/panel/press/`; словарь `webx-press` до
  прихода `lang` P1 — из `messages.ts` пакета (`lang.ts`), русского там пока нет.
- **Три блока §4.8 — копиями в `server/panel/press/blocks/`**, страница `/press` (id 20) —
  полоса отмеченных, каталог группами `[authored, expert_comment]`, лента. Шаблоны зовут
  `press()->outlets()->featured()->take($limit)->get()`, `config('webx-press.kinds')` и
  `__('webx-press::kinds.'.$kind)`; для этого маленький Blade плейграунда научился вызывать
  методы через `->` и `array_filter`. **Для P1:** `kind()` у меня принимает и строку, и список
  (пустой — все виды): блоки передают туда поле `kinds` целиком. Лента — по дате, без даты в
  конце, «март 2025» / «5 июня 2024» / «2023» через `Intl` без «г.».
- Тесты: `PressPage.test.ts` — 10 (список, порядок, поиск без ручек, новое издание с материалом
  одним `POST`, свёртка «#N · …» и сохранение с `id` строк, 422 `articles.1.title.en` под полем
  второй строки, «Open on the site», вопрос при уходе, корзина, только чтение), плюс по тесту на
  `rowErrors` в core и schema. Прогнаны `module-press`, `core/Repeater`, `schema`,
  `module-admin`, `-events`, `-reviews`, `-blocks`, `-recipes`, `-blog`, `-pages`, `-faq`,
  `-services`, `-seo` — зелёные (трём редакторам в свежем worktree нужен `dist` у
  `module-blocks`). `vue-tsc` у core, schema, module-press и плейграунда, eslint, prettier —
  чисто.
- В браузере (5187): создано издание с тремя материалами (адрес, PDF, оба — PDF выбран в
  пикере), 422 на адресе без схемы — под «Link» первой строки, свёрнутая строка раскрылась,
  издание не создалось; после исправления — `?outlet=6`; материалы переставлены клавиатурой на
  ручке, издание поднято наверх, после перезагрузки порядок и значения на месте, `id` строк те же
  (обновлены, а не пересозданы). 375 px — ящик с «назад», поля столбиком, горизонтального
  скролла нет; тёмная тема — список и форма. Три блока — в предпросмотре `/preview/page/20`.

### P3 — слияние, MCP, демо, доки

```
Сессия P3 из §6 docs/architecture/WEBX_UI_MODULE_PRESS.md: MCP, демо, гайд.

Worktree ../webx-ui-module-press, ветка feat/module-press. Первым делом: git fetch claude;
git merge claude/feat/press-panel (конфликт возможен в спеке — нужны оба итога — и в lang/* —
объединить ключи); после этого worktree ../webx-ui-press-panel больше не нужен (git worktree
remove, ветку оставить до выпуска).

Прочитать: §§4.11,4.12 спеки и итоги P1, P2; php/packages/module-reviews/src/{Mcp/*,Demo/*} и
resources/demo; apps/docs/guide/reviews.md и events.md; CLAUDE.md §4 про mcp:start (только
трубой) и про возврат webx-cms.local после local-режима из копий.

Сделать: PressTools и press://catalog §4.11 (всё в транзакции), PressDemo §4.12;
apps/docs/guide/press.md (издания и материалы, видимость по языку — решение 7, виды и как
добавить свой, три блока и как пересобрать виды в блоке, press() в шаблоне, pages = false) и
ссылка в сайдбаре; README npm-пакета, разделы MCP и демо в README composer-пакета; тесты MCP и
демо; changeset. Гейт php-половины и vitest/vue-tsc npm.

Проверить живьём инструменты через cat … | php artisan mcp:start webx на webx-cms.local в
local-режиме на этом worktree (MONOREPO=… packages.mjs local); до переключения скопировать в
скретчпад composer.json, composer.lock, package.json, package-lock.json и database/database.sqlite,
после — положить назад и composer install. Ничего на сайте не коммитить — это P4.
В конце — «Итог P3», коммит, пуш в claude.
```

### P4 — выпуск

```
Сессия P4 из §6 docs/architecture/WEBX_UI_MODULE_PRESS.md: выпуск module-press, оба демо.

Прочитать: итоги P1–P3; CLAUDE.md §5 целиком — очередь мержа (enqueuePullRequest, не
gh pr merge), «Первую версию нового npm-пакета публикует человек», «Ручная публикация
замораживает диапазоны», «Тег php-пакетов ставится до публикации»; docs/architecture/
WEBX_UI_PHP_RELEASE.md; итог EV4 в спеке событий — последний такой выпуск; память
webx-cms-local-demo-site, webx-cms-homelab-deploy и release-speed.

До релиза (руками пользователя, сессия напоминает и проверяет): репозиторий-зеркало
webx-ui/module-press на GitHub.

Сделать: погасить dev-серверы; полный гейт npm и php/ (PHP 8.4); module-press в
scripts/php-smoke.sh рядом с module-events и smoke против MariaDB; PR, зелёный CI, очередь
мержа; снять changeset-release/main в отдельный worktree, pnpm install, dist, pnpm pack
@webx-ui/module-press и проверить диапазоны @webx-ui/* в тарболе; первая публикация —
пользователь из своего терминала с 2FA, затем Trusted Publishing (webx-ui / webx-ui /
release.yml); мерж релизного PR; npm view всех поднятых пакетов и тег php-v<версия>;
webx-ui/module-press на Packagist. Удалить ветку feat/press-panel.

Демо: webx-cms.local — module-press в scripts/packages.mjs, link-panel.sh, composer require,
импорт и press() в resources/js/admin.ts руками (webx:panel --sync не трогает существующий
файл), migrate, webx:blocks:offered --install --module=press, cache:clear (словарь), демо §4.12
тинкером со своим журналом, npx vite build; хомлаб — то же в registry, npm ls
@webx-ui/module-admin — одна версия, коммит и пуш в Gitea. Строку реестра в
WEBX_UI_COMPOSER_PACKAGES.md и CLAUDE.md §§2,6 — отдельным docs-PR.

Проверить живьём на обоих: /press — каталог группами; страница издания — материалы в своём
порядке, PDF открывается, дата с точностью month по-русски в именительном; материал только
по-английски не виден на /ru, издание без русских материалов на /ru — 404 и нет в sitemap;
ItemList в <head> проходит validator.schema.org; полоса логотипов. Телефон — пользователь.
```

### Итог P1 (27.09.2026)

Composer-пакет `php/packages/module-press` написан целиком, кроме MCP и демо; гейт php-половины
зелёный (pint, phpstan, phpunit — 56 тестов пакета плюс весь прогон).

- **§4.6: `wx-hidden` не нужен, npm-половина типа P2 не нужна.** `id` строки переживает
  `ScreenValues` в обе стороны, если его узел — `wx-input-number` с `visible: false`:
  `RepeaterType::store()` держит ключи, названные детьми, а число — ровно то, что берёт этот тип
  (`wx-input` не годится: его правила хотят строку и отвечают 422 на целое). На клиенте
  `WxRepeater` пишет поле копией строки (`{ ...item, ...patch }`), так что ключ `id` едет с
  сервера и обратно, ничего не рисуя. Тест — `FormTest::the_id_of_a_row_survives_the_screen_both_ways`.
- **API §4.10 — ровно как написано, отличий нет.** `restore` отвечает `{ data: <строка списка> }`,
  как у отзывов. Строки формы — со всеми ключами §4.10; ошибки строк — `articles.<n>.<поле>`, у
  переводимого — `articles.<n>.title.<язык>`, чужой `id` — `articles` («Row N is an article of
  another outlet»). Клиентский `WxScreenRenderer` сводит всё это к полю `articles`; показать
  ошибку под полем строки — дело `WxScreenRepeater` (P2).
- **`accept` у `wx-file` — вид (`document`), а не mime.** `MediaValues::accept()` знает только
  виды библиотеки, и `application/pdf` молча не проверялся бы вовсе. PDF проверяет
  `OutletForm` (`articles.<n>.file`); файл, которого в библиотеке больше нет, пропускается, как
  везде у `wx-file`.
- **`kind` в `props.options` кладёт провайдер при регистрации экрана** (`Panel\OutletScreen`), а
  не при каждой отдаче: экран — массив, собранный на boot из json и конфига. Этим же путём
  при `pages = false` из экрана уходит `slug`. Слова вариантов — `trans::webx-press::kinds.*`.
- **Виды в предложенных блоках** — новый третий аргумент `BlockOffers::offer($module, $path,
$shape)` в `module-blocks`: замыкание над документом при чтении, с тестом в `OffersTest`. Поле
  `kinds` получает варианты из конфига, подписи — английские слова `webx-press::kinds.*`.
- **Разметка — через `HasStructuredData` издания, не `Seo::push()`.** Список о сущности, а не о
  странице, и так он печатается везде, где стоит `@webxSeo($outlet)`, — в том числе в
  переписанной вьюхе сайта. Решение 14 поправлено.
- **Слаг языка, у которого есть материалы, а своего слага нет, берётся с другого языка**
  (`Outlet::fillSlugs()`: язык по умолчанию → любой → `Str::slug` названия). Иначе русский
  материал, добавленный агентом или тинкером, не давал изданию русской страницы: форма
  заполняет пустое, а у двери мимо формы заполнять некому. Работает в `syncAddresses()`, то есть
  на любой двери.
- **Адрес издания** — `hasUrlIn()`: слаг на языке **и** видимый на нём материал (без
  `published`, как у черновика страницы). Материал при создании, удалении и смене `title`,
  `is_hidden`, `outlet_id` пересчитывает строку реестра издания; форма собирает это в один
  проход (`Outlet::holdingAddresses()`). При `pages = false` события реестра у издания не
  зовутся вовсе (`bootHasUrl()` переопределён и спрашивает `RouteTypes`).
- **`When`**: день — `LL` языка («August 12, 2023», «12. August 2023»), у `ru`/`be` без хвоста
  « г.»; месяц — `MMMM YYYY`, Carbon сам даёт именительный («август 2023», «серпень 2023»).
- Слова §5 — на десять языков: `module`, `list`, `screen`, `kinds`, `errors`, `site`. Для панели
  заведены `screen.article-label` («#:number · :title») и `screen.article-untitled`, для списка —
  `list.visible-in`, `list.search`, `list.delete-confirm` и прочее; недостающее P2 дописывает сам.
- Регистрации: `php/composer.json` (require, versions, autoload-dev), `phpunit.xml.dist`,
  `phpstan.neon.dist`, `Setup\Catalogue`, `Doctor\Checks\Helpers` (`press()`), `extra.webx` в
  composer.json пакета (`@webx-ui/module-press` `^0.1.0`, `...press()`), патч SEO-вкладки в
  `module-seo`. Плейграунд, сайт и smoke — не здесь (P2, P4).
- Хвост для P3: MCP-инструменты пишут материалы через `Article` напрямую — адреса издания
  пересчитываются сами (наблюдатель), но несколько записей подряд стоит завернуть в
  `Outlet::holdingAddresses()`.

### Итог P3 (27.09.2026)

`feat/press-panel` слит в `feat/module-press` без конфликтов; worktree `../webx-ui-press-panel`
снят (git его отпустил, а каталог держал живой dev-сервер P2 на 5187 — погашен, каталог удалён;
ветка `feat/press-panel` остаётся до выпуска).

- **MCP (§4.11)** — `Mcp\PressTools` и `Mcp\PressResources`, подключены через `PressModule`
  (`ProvidesMcpTools`). Десять инструментов ровно по спеке. **Все записи материалов — это
  `OutletForm::save()` издания с одной строкой больше, меньше или изменённой**, то есть одна
  транзакция и те же отказы, что у панели; ключ отказа `articles.<n>.<поле>` переписывается в
  `article #<id> <поле>` / `the new article <поле>`. Исключение одно: `press_articles_move` в
  **другое** издание пишет `outlet_id` сам (в транзакции и в `Outlet::holdingAddresses()`) — форма
  одного издания строку другого не примет, а удалить-и-создать сменило бы id. `press_update` с
  ключом `articles` отказывает: список, прочитанный агентом, стёр бы материал, добавленный после.
  Издание по имени ищется сравнением в PHP, а не `LIKE`: sqlite не сворачивает регистр кириллицы.
- **Демо (§4.12)** — `Demo\PressDemo` и `resources/demo/press.json` + четыре логотипа-SVG и
  одностраничный PDF (`press-scan.pdf`, генерированный, латиница). Файлы — через `FileStore` в папку
  «Пресса»/«Press» библиотеки, издания — через `OutletForm`. Страница `/{приставка}` (ребёнок
  главной): `press-logos` (только отмеченные) + `press-outlets` группами. `requires()` —
  `media`, `blocks`, `pages` если стоит.
- **Слова сведены по тесту паритета:** панель говорит `outlet.*` (34 ключа) — сервер получил
  `lang/*/outlet.php` на десять языков вместо `list.php`, который серверный код не использовал
  нигде; `module.title` панели → `module.press` сервера; группа `screen` в `messages.ts` теперь
  повторяет серверный экран (он настоящий). Новое слово `screen.kind-none` на десять языков.
- **Экран P1 догнал проверенное P2 в браузере:** у повторителя `itemLabel: "title"` и
  `sortable: true` (без них нет «#N · заголовок» и ручек), у логотипа `aspect: null` (широкий
  логотип не режется в квадрат), у вида — подсказка `kind-none`. Копии экрана и блоков в
  `apps/playground/server/panel/press/` удалены, плейграунд читает файлы пакета.
- **Шаблоны трёх блоков переписаны без `@php`** — Blade плейграунда его не знает и печатал
  шаблоны P1 сырыми. Разметка и классы P1 те же; `Kinds::label()` → `__('webx-press::kinds.'.$kind)`,
  `Kinds::all()` → `config('webx-press.kinds', [])`. Плейграунд научился литералу
  `['count' => …]` и заменам в `__()`; карточки фикстуры приведены к `Cards` (у материала
  `outlet.logo` — строка).
- **Две настоящие поломки шва, найденные на слиянии:** `extra.webx.panel.register` был
  `...press()`, а `press()` на npm-стороне возвращает один модуль, не массив — `webx:panel --sync`
  вписал бы спред объекта; теперь `press()`. И плашка «адрес переедет» горела при открытии любого
  издания, если язык панели не язык по умолчанию: `outlet.url` приходит на языке панели
  (`/en/press/…`), а `wx-slug` сравнивает со слагом на языке правки. `OutletPane` теперь снимает
  языковой сегмент и отдаёт адрес полю только на его языке; тест в `PressPage.test.ts`.
- Гайд `apps/docs/guide/press.md` (всё из промпта P3, плюс API панели и MCP) и строка в
  сайдбаре; README npm-пакета; в README composer-пакета — MCP, демо, переводы. Changeset
  `module-press-mcp-demo.md` (`@webx-ui/php` и `@webx-ui/module-press` — minor).
- Тесты: `McpTest` (11), `DemoTest` (4); пакет — 71, весь php — зелёный; pint и phpstan чисто.
  npm: `module-press` 15, с `module-admin`, core `Repeater` и `schema` — 323; vue-tsc пакета и
  плейграунда, eslint, prettier — чисто. В этом worktree `node_modules` не было вовсе — сделан
  свой `pnpm install --frozen-lockfile` (обычный каталог, основной чекаут цел).
- **Живьём:** `webx-cms.local` в local-режиме на этом worktree (`--no-install`, только composer),
  `migrate`, одиннадцать запросов трубой в `mcp:start webx`: создание с материалами, материал только
  по-русски на позицию 1 (издание стало видно на `ru`), отказы «вид не из конфига» и «не PDF»
  названы по материалу, перенос в неопубликованное издание с сохранением id, порядок, каталог,
  `dry_run` удаления. `tools/list` отдаёт по 100 — `press_*` на второй странице. Сайт возвращён из
  копий (симлинки сняты, `composer install`), `git status` чист, главная 200.
- Для P4: на сайте в `resources/js/admin.ts` — `press()`, без спреда.

### Итог P4 (27.09.2026)

Выпущено в v0.44.0 (#319, релизный #318): `webx-ui/module-press` на Packagist (зеркало и
отправка — пользователь), `@webx-ui/module-press@0.1.0` на npm (первая версия — руками из тарбола
`changeset-release/main`, диапазоны `^0.34.0` / `^0.18.1` / `^0.6.2` проверены до публикации,
затем Trusted Publishing). Релиз поднял `core` минором (`rowErrors` у `WxRepeater`), значит и всем
модулям по патчу; расхождений между релизной веткой и npm нет. `php-split` прошёл, `v0.44.0` в
`webx-ui/module-press` есть. Ветка `feat/press-panel` удалена.

- **Гейт и smoke:** npm-гейт целиком, `php/` на 8.4 (1802 теста), `scripts/php-smoke.sh` против
  MariaDB OSPanel — `module-press` найден discovery, миграции прошли на mysql.
- **Packagist после релиза** снова отдавал composer'у v0.43.0 при уже видимом v0.44.0 — CDN,
  CLAUDE.md §5; прошло со второй попытки после удаления `provider-webx-ui~*`.
- **Демо на обоих сайтах — тинкером со своим журналом** (`storage/app/press-demo-journal.json`,
  `PressDemo::seed()`): четыре издания, логотипы и PDF в папке «Пресса», страница `/press`.
  `webx-cms.local` закоммичен в registry-состоянии (Gitea `c07bd46`), каркас панели в
  `node_modules` одной копией (0.18.1); на хомлабе блоки и демо — через `docker exec` от
  `www-data`.
- **`client-site.test`** поднят до v0.44.0 и получил `module-press` без содержимого:
  `webx:panel --sync` вписал `press()` сам — у этого сайта в `admin.ts` размеченные области.
- **Проверено curl'ом на обоих:** `/press` — полоса и каталог четырьмя группами; страница издания —
  материалы в своём порядке, PDF отдаётся `application/pdf`, «14 марта 2025» и «март 2025»;
  материал только по-английски на русской странице не виден и виден на `/en`; неопубликованное
  издание и издание без материалов на языке (`/uk/…`) — 404 и нет в sitemap. validator.schema.org
  по адресу хомлаба — `ItemList`, `BreadcrumbList`, `Organization`, `WebSite`, 0 ошибок и 0
  предупреждений. Телефон — за пользователем.
- **Хвост:** `Rendering\PressQuery` написан до `RecordQuery` (T1, `feat/module-team`, в `main`
  ещё нет) и на него не переведён. Перевести, когда T1 смержится: у press выборка по изданиям с
  видимостью через материалы, так что это не механическая замена.

## 7. Отложено

- Импорт со старого клиента (`media_sources`, `publications` JSON) на `client-site.test` —
  отдельной задачей сайта после выпуска, через MCP или тинкером: виды `commissioned` →
  `authored`, `expert_comment` → `expert_comment`; вид клиента стоит на издании — переносится на
  каждый его материал.
- Выбор отдельных изданий руками в блоке (`only()` в хелпере уже есть).
- Страница материала на сайте (свой пересказ статьи со ссылкой на оригинал) — когда попросят.
- Превью ссылки (`og:image` чужой статьи) — не тянуть чужое без спроса.

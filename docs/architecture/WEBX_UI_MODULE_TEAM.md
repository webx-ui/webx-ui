# `webx-ui/module-team` — спецификация и план реализации

Статус: спроектирован 27.09.2026, не начинался. Пакеты — `webx-ui/module-team` (composer) и
`@webx-ui/module-team` (npm).

Команда — люди организации: фото, имя, должность, короткий текст, соцсети, по желанию — услуги,
которые человек ведёт. Своей страницы нет ни у человека, ни у модуля: команда попадает на сайт
**блоком**, как отзывы, — «наши врачи» сеткой на странице «О клинике», «кто делает» списком на
странице услуги.

Всё ядро уже выпущено: контракт «вставить блоком» (`CollectionSource`, `wx-collection`,
`BlockOffers`) — с `module-faq`, связи между записями (`HasRelations`, `wx-relations`, фильтр по
связи в `wx-collection`) — с `module-recipes`. Своего у модуля немного. Зато он — третий
потребитель хелпера шаблона после `services()` и `reviews()` (четвёртый и пятый — `recipes()` и
`events()`), и §7 спеки отзывов обещал вынести общий `RecordQuery` именно на нём. Поэтому
первая сессия — не модуль, а этот вынос (§4).

## 1. Границы

**Внутри:** `RecordQuery` в `module-admin` и перевод на него четырёх существующих хелперов;
люди, один ручной порядок, переводы, соцсети по списку из конфига, связь с услугами (если
стоит `module-services`), источник `team` для `wx-collection`, хелпер `team()`, тип блока
«Команда» с видами «сетка», «слайдер», «список», экран панели, API, MCP, демо.

**Снаружи:** категории (решение 1); своя страница человека, разметка `Person` и полная
биография (решение 2); стаж, образование, сертификаты, дни приёма, email и телефон — это
`extra` сайта патчем экрана (решение 7); связь отзывов с человеком («отзывы об этом враче») —
§7; выбор отдельных людей руками в блоке (`Selection::ids`, §7 спеки FAQ).

## 2. Принятые решения (не переоткрывать)

Решения 1–7 приняты в обсуждении 27.09.2026.

1. **Категорий нет** — ни таблиц, ни `wx-categories`, ни фильтра в блоке. Источник отвечает
   `categories() = null`, и поле `wx-collection` само прячет выбор категорий (так уже умеет).
   Понадобятся — добавляются как у отзывов, это не ломает ничего из написанного здесь.
2. **Страницы нет, конфига под неё тоже нет.** Люди видны только блоком и хелпером. `LinkSource`
   модуль не регистрирует, в реестре адресов и в карте сайта его нет, SEO-карточки нет,
   разметки нет (`supportsMarkup() = false`: `Person` без страницы человека не на что вешать).
3. **Связь с услугами необязательная.** Поле `wx-relations` с `target: "service"` в экране
   есть всегда, а `ScreenRegistry` убирает его сам, когда цели `service` никто не
   зарегистрировал (`RelationsType implements Withdraws`, как у рецептов и событий). Нет услуг —
   нет поля, нет фильтра «только связанные с» в блоке, нет `service_links` в карточке. Строки
   связей, записанные при услугах, при удалении модуля не трогаются.
4. **Соцсети — повторитель `{сеть, адрес}`**, список сетей — конфиг `webx-team.networks`
   (ключ → название). Сайт расширяет его своим конфигом; значки рисует шаблон блока (§5.5).
5. **Порядок один, общий, ручной** (`position`), как у рецептов: категорий нет — второго порядка
   быть не может.
6. **Общий `RecordQuery` делаем** — первой сессией, в `module-admin`, и на него переезжают
   `services()`, `reviews()`, `recipes()`, `events()` без изменения их публичного поведения
   (§4). `team()` пишется уже поверх.
7. **Стаж, образование, сертификаты и прочее — в `extra`.** В базу модуля идёт только то, что
   есть почти у каждой команды.

Решения, принятые при написании спеки (можно оспорить до T3):

8. **Никто не прячется из-за языка.** Человек виден, если опубликован и не в корзине. Имя и
   должность без перевода берутся с языка по умолчанию (как у отзывов), а текст — только на
   языке страницы, иначе пустой. Отзыв без текста бессмыслен, человек без биографии — нет:
   прятать врача с русской страницы из-за непереведённого абзаца значило бы терять его.
9. **Имя обязательно на языке по умолчанию** — единственное обязательное поле.
10. **Черновиков и версий нет** — только «Опубликован», как у отзыва. Корзина есть.
11. **Панель — `WxListDetail`**, как у отзывов, но без фильтра категорий: слева список (фото или
    инициалы, имя, должность), справа форма. Один пункт меню «Команда», без группы.
12. **Человек — цель связей** (`MemberTarget`, ключ `team-member`), как событие: цена — один
    класс, а «отзывы об этом враче» и «вопросы к этому специалисту» потом не потребуют правки
    модуля (§7). Сам модуль на себя не ссылается.

## 3. Схема

```
team_members
  id
  name         json nullable        -- переводимое; на языке по умолчанию обязательно (решение 9)
  job_title    json nullable        -- переводимое: должность
  text         json nullable        -- переводимое, простой текст (textarea), не rich-text
  photo        json nullable        -- значение wx-media, как у отзыва
  socials      json nullable        -- [{ "network": "instagram", "url": "https://…" }], по порядку
  published    bool default false
  position     int default 0        -- общий порядок (решение 5)
  extra        json nullable
  softDeletes, timestamps
```

- Имя модели — `Member`, таблица — `team_members`: `team` — это раздел, а не запись, и
  `teams` в таблице читалось бы как «команды».
- Должность — `job_title`, а не `position`, по той же причине, что у отзывов: `position` —
  колонка порядка у всего общего кода (`Ordering`, `orderedIn`).
- Связи с услугами — в общей `webx_relations` (роль `services`), своей таблицы нет.
- Соцсети — json, а не таблица: их пять, они всегда читаются вместе с человеком, и порядок —
  порядок массива.
- Миграция — `2026_01_01_*`; на чужие таблицы модуль не ссылается.

## 4. `RecordQuery` — общий хелпер шаблона (`module-admin`)

### 4.1. Что общего у четырёх

`ServiceQuery`, `ReviewQuery`, `RecipeQuery`, `EventQuery` — неизменяемые запросы с одной и той
же половиной, переписанной четыре раза почти слово в слово:

- шаги `only()`, `except()`, `take()`, `locale()` и их разбор (`ids()`, строка из цифр — id);
- выход `get()`, `first()`, `isEmpty()`, `count()`, `getIterator()`, `resolvedLocale()`;
- сам запрос: `whereIn`/`whereNotIn` по `only`/`except`, фильтр по категориям через пивот,
  фильтр по связи через `Relations::rows()`, порядок, затем **видимость на языке в php**, пересортировка по
  порядку `only()` и лимит после видимости;
- у троих из четырёх — сгруппированный каталог `categories()` с предзагрузкой через замыкание
  `item_position → position → id`.

Различаются: кого считать видимым (`writtenIn` у отзывов, `hasUrlIn` у услуг и рецептов), как
назвать категорию (слаг есть у услуг, рецептов и событий, у отзывов — только id), порядок (у
событий — по дате, плюс `upcoming()`/`past()`), дополнительные фильтры (`nutrients()` у
рецептов) и карточка.

### 4.2. Форма

`WebxUi\Admin\Collections\RecordQuery` — абстрактный класс; модульный запрос его наследует и
остаётся `final`:

```php
abstract class RecordQuery implements Countable, IteratorAggregate
{
    // Состояние — один массив шагов; with() возвращает копию (как у RecipeQuery::with()).
    // Публичные шаги, одинаковые у всех: only(), except(), take(), locale().
    // Публичный выход: get(), first(), isEmpty(), count(), getIterator(), models(), resolvedLocale().

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Что читатель может увидеть в SQL: опубликовано, не в корзине, не в будущем. */
    abstract protected function visible(Builder $query, string $locale): void;

    /** Что видно на языке, если это вопрос слов, а не колонок. По умолчанию — всё. */
    protected function shownIn(Model $record, string $locale): bool { return true; }

    /** @param list<Model> $records  @return list<array<string, mixed>> */
    abstract protected function cards(array $records, string $locale): array;

    /** Порядок. По умолчанию orderedIn(категория), а у модели без категорий — position, id. */
    protected function order(Builder $query, ?int $category): void;

    /** Свои фильтры модуля (nutrients у рецептов, upcoming/past у событий). */
    protected function narrow(Builder $query, string $locale): void {}

    // Для тех, у кого это есть, — защищённые шаги, которые модуль открывает своим публичным
    // методом со своей сигнатурой (тип модели категории у каждого свой):
    protected function withCategories(int|string|Model|iterable|null $categories): static;
    protected function withRelated(string $type, int|object|iterable $records): static;
    /** Как назвать категорию строкой: слаг на языке или ничего (отзывы). */
    protected function categoryBySlug(string $slug, string $locale): ?int { return null; }
    /** Каталог по категориям: общий движок, модуль отдаёт отношение и карточку группы. */
    protected function grouped(string $relation, callable $group): array;
}
```

Это эскиз, а не контракт: сигнатуры T1 выбирает сам. Обязательно только одно — **публичное
поведение четырёх хелперов не меняется ни в чём**, и доказывают это их нынешние тесты, которые
должны пройти без правки. Правка теста — это правка поведения, и о ней говорят в итоге крупно.

Правила, которые движок держит за всех (и которые сейчас держит каждый по-своему):

- **Пустой фильтр категорий — «все», пустой список после разбора — «ничего».** Нетронутое поле
  редактора присылает пустоту и значит «всё»; строка, которая не нашлась ни слагом, ни id, —
  фильтр, который никто не проходит (опечатка не превращает «отзывы об имплантах» во все
  отзывы).
- **`relatedTo()` с пустым списком — ничего**, а не всё («рецепты никакой услуги»).
- **Лимит — после видимости на языке**, в php: «первые шесть» на русском — шесть русских.
- **`only()` задаёт порядок**, и он побеждает любой другой.

### 4.3. `Selection` без категорий

`Selection::apply()` сейчас бросает `InvalidArgumentException`, если у модели нет
`categoryLinks()`, — на команде это падение каждого блока. Правка: без выбранных категорий
модель может их не иметь; порядок — `orderedIn`, если такой скоуп есть, иначе `position`, `id`.
Выбранные категории у модели без категорий — по-прежнему ошибка: это не то, что может прислать
поле, у которого выбора категорий нет. `Ordering::move()` без категории уже работает с любой
моделью.

### 4.4. Ссылки на связанные записи

`service_links` в карточке рецепта и события собираются одинаковым кодом
(`Cards::serviceLinks()` и предзагрузка связей списком в три запроса). У команды это третья
копия, поэтому T1 выносит и её — `WebxUi\Admin\Relations\RelatedLinks`:
`RelatedLinks::load(list<Model> $owners, string $role)` один раз на список и
`links(Model $owner, string $role, string $locale): list<array{id, title, url}>` на карточку;
пусто, если цель роли не зарегистрирована. Если при выносе окажется, что у рецептов и событий
код расходится по делу, — не натягивать: оставить как есть, команда копирует рецептный, а
расхождение записать в итог.

### 4.5. Что переезжает

- `ServiceQuery`, `ReviewQuery`, `RecipeQuery`, `EventQuery` наследуют `RecordQuery`; классы,
  хелперы и их публичные методы остаются под своими именами — сайт, который пишет
  `reviews()->in(3)->take(6)`, ничего не замечает.
- `Cards` рецептов и событий берут `RelatedLinks`.
- Changeset: `@webx-ui/php` minor (новый публичный класс в `module-admin`). npm не меняется.

## 5. Модуль

### 5.1. Модели и зависимости

- `Member` — `HasExtra`, `HasTranslations` (`name`, `job_title`, `text`), `HasRelations`
  (`relationKey() = 'team-member'`), `SoftDeletes`. `scopeVisible()` — опубликован и не в
  корзине (решение 8). Новый человек встаёт в конец порядка (`max + 1`).
- `Relations\MemberTarget` — цель связей для других модулей (решение 12): пикер показывает имя,
  должность и миниатюру фото.
- `require`: `module-admin`, `module-blocks`, `module-media`, `localization`, `mcp`.
  `require-dev`: `module-pages`, `module-services` — ради демо и тестов связи.

### 5.2. Карточка

`Rendering\Cards` — человек так, как его читает шаблон:

```php
[
    'id' => 12,
    'anchor' => 'member-12',        // стабильный якорь
    'categories' => [],             // контракт CollectionSource требует ключ; категорий нет
    'name' => 'Anna Petrova',       // на языке страницы, иначе на языке по умолчанию
    'initials' => 'AP',             // как у отзыва — вместо фото
    'job_title' => 'Orthodontist',  // то же; '' если нет
    'text' => "…\n…",               // на языке страницы, иначе '' (решение 8)
    'photo' => [ 'url' => …, 'thumb' => …, 'width' => …, 'height' => …, 'alt' => … ], // или null
    'socials' => [                  // в порядке редактора; сеть, которой нет в конфиге, выпадает
        [ 'network' => 'instagram', 'label' => 'Instagram', 'url' => 'https://…' ],
    ],
    'service_links' => [ [ 'id' => 3, 'title' => 'Braces', 'url' => '/braces' ] ], // [] без услуг
    'fields' => [ 'experience' => '12' ], // поля проекта по имени
]
```

Сеть, убранная из конфига, из карточки выпадает, а из базы нет: вернули в конфиг — вернулась
на сайт. Список любой длины — несколько запросов, а не по запросу на человека.

### 5.3. Хелпер `team()` и источник `team`

`Rendering\TeamQuery extends RecordQuery`:

| Шаг                   | Что делает                                                      |
| --------------------- | --------------------------------------------------------------- |
| `relatedTo($t, $ids)` | Только связанные с этими записями (`'service'`, id или модель)  |
| `only([12, 7])`       | Только эти, в этом порядке                                      |
| `except($member)`     | Кроме этих                                                      |
| `take(6)`             | Не больше шести; null или ноль — все                            |
| `locale('uk')`        | Язык карточек; по умолчанию тот, на котором рисуется страница   |
| `get()`, `first()`    | Список карточек или одна; сам запрос можно перебирать и считать |

`in()` и `categories()` у команды нет (решение 1). Хелпер — за `function_exists('team')`,
строка в `Doctor\Checks\Helpers`.

`Collections\TeamSource implements CollectionSource` — `key() = 'team'`, `categories() = null`,
`relations() = ['service']` (контроллер коллекций сам отбрасывает незарегистрированные цели),
`supportsMarkup() = false`, `permission() = 'team.view'`, `items()` — `team()` с лимитом, языком
и `relatedTo()` из `$selection->related()`, включая `related.current` (как у `RecipesSource`).
Так «кто делает эту услугу» — это блок на странице услуги с «только связанные с — текущая».

### 5.4. Соцсети

```php
// config/webx-team.php
return [
    'networks' => [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'x' => 'X',
        'telegram' => 'Telegram',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
    ],
];
```

- В экране `team.form` поле `socials` — `wx-repeater` из `wx-select` (`network`) и `wx-input`
  (`url`). Опций у селекта в JSON нет: провайдер в `boot()` кладёт их патчем
  `Screens::extend('team.form', [{ op: set, … props.options … }])` из конфига. Так сайт
  расширяет список одной строкой конфига, а проверка значения остаётся той, что уже есть у
  `wx-select` на сервере (`OptionType` читает `props.options` из дерева — с патчем).
  **Проверить в T3:** что `set` патча доходит до узла внутри `wx-repeater` и что `OptionType`
  внутри повторителя видит опции; нет — чинить в `module-admin`, это общий дефект.
- Названия сетей — имена брендов, не переводятся. Сайт, которому нужна «Личная страница»,
  добавляет сеть со своим названием на своём языке.
- `url` — только `http(s)://`, 422 под `socials.<n>.url`. Пустая строка повторителя
  (ни сети, ни адреса) выбрасывается на записи, наполовину заполненная — 422.
- Первая версия поля — названия сетей текстом; значков в панели нет, в наборе иконок ядра брендов
  нет, и заводить их ради селекта не надо.

### 5.5. Тип блока «Команда»

`resources/blocks/team.json`, предлагается через `BlockOffers`:

```json
[
  { "id": "title", "type": "wx-input", "label": "Heading", "localized": true },
  { "id": "team", "type": "wx-collection", "label": "People", "props": { "source": "team" } },
  {
    "id": "layout",
    "type": "wx-segmented",
    "label": "Layout",
    "props": {
      "options": [
        { "value": "grid", "label": "Grid" },
        { "value": "slider", "label": "Slider" },
        { "value": "list", "label": "List" }
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
  { "id": "show_text", "type": "wx-switch", "label": "Show the text" }
]
```

- **Пустой `layout` — `grid`**, пустые `columns` — 4, пустой `show_text` — показывать: блок
  хранит только тронутое (итог F1 спеки FAQ).
- **Виды:** сетка — карточки фото сверху; слайдер — та же карточка в ленте со `scroll-snap` и
  кнопками; список — фото слева, имя, должность, текст и услуги справа (это вид для «наши
  врачи» с биографиями и для «кто делает» на странице услуги).
- **Карточка** — `<article id="member-12">`: фото или инициалы в круге, имя, должность, текст
  (`nl2br(e())`), ссылки на услуги (если есть), соцсети.
- **Значки соцсетей** — инлайновые SVG для семи сетей конфига по умолчанию прямо в шаблоне
  (Simple Icons, CC0 — источник и лицензия комментарием в шаблоне); сеть без значка — ссылка
  с названием текстом. Сайт, добавивший сеть, добавляет значок в свою копию типа блока. Ссылка —
  `rel="noopener"` и `target="_blank"`, `aria-label` — название сети и имя человека.
- Без JS всё читается; скрипт только у слайдера (кнопки, автопрокрутка,
  `prefers-reduced-motion`) — взять из блока отзывов. Стили нейтральные, без `--wx-*`,
  `[hidden]` объявлен явно.

### 5.6. Панель

- Пункт меню «Команда» верхнего уровня, иконка `users` (проверить по набору — `icons.test.ts`),
  модуль панели `team`, права `team.view`, `team.manage`.
- `WxListDetail` по образцу отзывов, без категорий: маршрут `/team`, открытый человек —
  `?member=<id|new>`, рядом `q`, `view=trashed`; «Новый человек» строкой, `POST` на первом
  сохранении; перетаскивание за ручку (одно, общее); Ctrl+S; вопрос при уходе только при смене
  `member`. Строка — миниатюра или инициалы, имя, должность, признак «не опубликован».
- Экран `team.form`: фото, имя, должность, текст (`wx-textarea`, `localized`), соцсети
  (`wx-repeater`, §5.4), услуги (`wx-relations`, `target: service`, исчезает без услуг —
  решение 3), «Опубликован», карточка `project-fields` для `extra`.

### 5.7. API панели

Формы ответов фиксированы здесь, чтобы T2 шёл параллельно с T1 и T3:

```
GET    /api/cms/team                  ?trashed=1&search=
  → { data: [{ id, name, job_title, initials, photo: { thumb } | null, published, position,
               updated_at, deleted_at }] }                          без meta, пагинации и filters
POST   /api/cms/team                  { values }             → 201 { data: { member, values } }
GET    /api/cms/team/{id}             → { data: { member, values } }
PUT    /api/cms/team/{id}             { values }             422 под именем поля
DELETE /api/cms/team/{id}
POST   /api/cms/team/{id}/restore     → голый ресурс строки списка
POST   /api/cms/team/reorder          { ids }                Ordering::move(Member::class, $ids)
```

- `name` и `job_title` в списке — на языке панели, иначе на языке по умолчанию; имя, иначе `#id`.
- `member` в ответе формы — `{ id, name, published, deleted_at }`; `values` — `name`,
  `job_title`, `text` картами языков, `photo` (значение `wx-media`), `socials`
  (`[{ network, url }]`), `services` (значение `wx-relations`, только когда цель есть),
  `published` и поля проекта.
- `POST` и `PUT` — одна `MemberForm::save()` в транзакции: отказ не оставляет строку.

### 5.8. MCP

`team_list`, `team_get`, `team_create`, `team_update`, `team_delete`, `team_reorder` — через те же
`Panel\*`, что и панель. Человек называется id. Строка без языка в `create`/`update` — язык по
умолчанию. Фото — ключ файла библиотеки, неизвестный ключ — отказ инструмента (как у отзывов).
`socials` — список `{ network, url }`, сеть не из конфига — отказ со списком допустимых.
`services` — id или адреса услуг (как у рецептов; без `module-services` аргумента в схеме нет).
Ресурс `team://catalog`: люди по порядку, у каждого `published`, `written_in` (языки текста),
`services` и допустимые сети конфига в шапке.

### 5.9. Демо

`TeamDemo`, данные — `resources/demo/team.json` (en и ru): шесть человек, один
неопубликованный, один без русского текста (виден на `/ru` без текста — решение 8), у троих
соцсети, у одного — сеть, которой нет в конфиге (выпадает — §5.2). Фото нет: шаблон рисует
инициалы. `requires()` динамический: `media`; `blocks` и `pages` парой; `services`, если стоит.
При `module-pages` — страница `/team` с блоком «все, сетка»; при `module-services` — связи с
демо-услугами из журнала и блок «список, только связанные с текущей» в одной услуге.

### 5.10. Регистрации

CLAUDE.md §4 «Новый composer-пакет надо прописать в `php/` четыре раза» и «Новый раздел панели
регистрируется в четырёх местах»: `php/composer.json` (`require`, `autoload-dev`),
`phpunit.xml.dist`, `phpstan.neon.dist`, `Setup\Catalogue`, `extra.webx.npm`/`extra.webx.panel`,
`apps/playground/src/panel/main.ts`, `scripts/packages.mjs` в `webx-cms.local` (T5), строка в
`scripts/php-smoke.sh` (T5), `team()` в `Doctor\Checks\Helpers`.

### 5.11. Тесты, которые обязательны

- Без текста на языке человек виден с пустым текстом; без имени на языке — имя с языка по
  умолчанию; неопубликованный и из корзины — не виден.
- `team()`: каждая строка таблицы §5.3, `only()` в своём порядке, `relatedTo('service', [])` —
  пусто, лимит после видимости.
- Без `module-services`: поля `services` в экране нет, `service_links` пусто, `relations` у
  источника в ответе `/collections` пусто, запись с `services` в `values` — отказ, как любое
  неизвестное поле.
- Соцсети: опции селекта из конфига (сеть, добавленная конфигом сайта, принимается), чужая сеть —
  422, не `http(s)` — 422, пустая строка повторителя выброшена.
- `wx-collection` с `source: team` через настоящий путь записи обеих дверей (панель и
  `blocks_edit_content`), включая `related.current` на странице услуги.
- Предложенный блок рисуется на своём `sample` во всех трёх `layout`.
- Удалённый модуль: блок рисуется пустым, страница отвечает 200.

## 6. Слова

Все ключи `webx-team::*` на десять языков панели заводит **T3** — и серверные, и нужные панели:
модуль, список (`all`, `new`, `trashed`, `unpublished`, `restore`, поиск), форма (подписи полей
экрана, соцсети — «Сеть», «Адрес», «Добавить», уход с несохранённым), отказы. T2 держит
английский пол в `messages.ts` и тест паритета; чего не хватило — дописывает в те же файлы и
пишет это в итог.

## 7. Отложено

- Категории — как у отзывов, когда попросят (решение 1).
- Страница человека, `Person`, полная биография — вместе, когда попросят (решение 2); конфиг и
  маршрут заводить тогда же.
- «Отзывы об этом враче»: `wx-relations` с `target: team-member` в экране отзывов и
  `relations() = ['team-member']` у `ReviewsSource` — цель уже будет (решение 12).
- Выбор отдельных людей в `wx-collection` (`Selection::ids`) — «на главную вот эти трое». Без
  категорий это чувствуется сильнее, чем у отзывов: сейчас «трое на главную» — это первые трое
  по порядку. Первый кандидат на следующий шаг, если спросят.
- Значки соцсетей в панели.

## 8. Пошаговый план

**Выпуск один, в самом конце** (T5); до него ни PR, ни ожидания CI. Каждая сессия гонит
локальный гейт своей половины (php — `composer lint && composer analyse && composer test` из
`php/` на PHP 8.4; npm — точечно `npx vitest run … --pool=forks --poolOptions.forks.singleFork`
из корня worktree, `npx vue-tsc` в пакете, eslint и prettier на своих файлах). Полный гейт —
только T5.

**T2 идёт параллельно с T1 и T3**: npm-половина с php не пересекается по файлам, а API
зафиксирован в §5.7. T1 и T3 — последовательно на одной ветке: `TeamQuery` наследует то, что
делает T1. T4 сливает ветку T2 и дальше идёт по основной.

| Сессия | Ветка / worktree                              | Что                                                                                     |
| ------ | --------------------------------------------- | --------------------------------------------------------------------------------------- |
| **T1** | `feat/module-team` / `../webx-ui-module-team` | php: `RecordQuery`, `Selection` без категорий, `RelatedLinks`, перевод четырёх хелперов |
| **T2** | `feat/team-panel` / `../webx-ui-team-panel`   | npm: панель, плейграунд                                                                 |
| **T3** | `feat/module-team`                            | php: пакет целиком — схема, модель, хелпер, источник, блок, API                         |
| **T4** | `feat/module-team`                            | слияние T2, MCP, демо, гайд, README                                                     |
| **T5** | `feat/module-team`                            | выпуск, оба демо                                                                        |

Промпты ниже самодостаточны. Каждая сессия в конце дописывает сюда «Итог Tn» — что следующей
надо знать сверх промпта, — и строку в память `custom-modules-workflow`.

### T1 — `RecordQuery`, php

```
Сессия T1 из §8 docs/architecture/WEBX_UI_MODULE_TEAM.md: общий RecordQuery в module-admin.

Начало: git fetch claude; git worktree add ../webx-ui-module-team -b feat/module-team
claude/main, если docs-PR спеки уже смержен, иначе от claude/docs/plan-team. PR не открывать.

Прочитать: §§2,4 спеки; §7 docs/architecture/WEBX_UI_MODULE_REVIEWS.md; четыре запроса и их
Cards — php/packages/module-{services,reviews,recipes,events}/src/Rendering/*, их источники
src/Collections/* и tests/*Query*|*Helper*; php/packages/module-admin/src/{Collections/*,
Relations/*,Categories/{HasCategories,Ordering}.php}.

Сделать: WebxUi\Admin\Collections\RecordQuery §4.2; Selection::apply() для модели без
категорий §4.3 (с тестом на модели-фикстуре без HasCategories); RelatedLinks §4.4, если код
рецептов и событий действительно общий; перевести ServiceQuery, ReviewQuery, RecipeQuery,
EventQuery и Cards рецептов и событий §4.5; тесты самого RecordQuery на фикстуре; changeset
minor на @webx-ui/php.

Главное условие: существующие тесты четырёх модулей проходят без правки. Если какой-то тест
пришлось поменять — это изменение поведения, записать в итоге крупно, почему. Не делать:
module-team (T3). В конце — «Итог T1» в §8 спеки (какие хуки получились на деле: T3 пишет
TeamQuery по ним), коммит, пуш в claude.
```

### T2 — `module-team`, npm

```
Сессия T2 из §8 docs/architecture/WEBX_UI_MODULE_TEAM.md: npm-пакет @webx-ui/module-team.
Идёт параллельно с T1 и T3, php-половину не трогает.

Начало: git fetch claude; git worktree add ../webx-ui-team-panel -b feat/team-panel
claude/main (или claude/docs/plan-team, если docs-PR спеки ещё не смержен); pnpm install
--frozen-lockfile в этом worktree (каталог обычный, node_modules будет свой — CLAUDE.md §4 про
pnpm в worktree); собрать dist у tokens, core, schema, module-admin. PR не открывать.

Прочитать: §§2,5.4–5.7,6 спеки; итоги R2 и R3 в docs/architecture/WEBX_UI_MODULE_REVIEWS.md;
packages/module-reviews целиком — образец (без категорий); apps/playground/server/panel/
{reviews.ts,recipes.ts,blade.ts} — мок, связи, предпросмотр блока.

Сделать: packages/module-team (версия 0.0.0) — модуль панели §5.6 (один пункт меню, без
categoryRoutes), i18n с английским полом и тестом паритета (ключи §6; каталог lang заводит T3 —
чего нет, дописать в php/packages/module-team/lang/en и записать в итог); плейграунд:
apps/playground/server/panel/team.ts по формам §5.7, свою копию экрана team.form и типа блока
§5.5 на время, пока их нет на диске от T3 (как RC4 — T4 копии убирает), модуль в main.ts,
страница с блоком во всех трёх видах и связь с фикстурными услугами; vitest; changeset minor.

Проверить в браузере на плейграунде: создать, перетащить, соцсети (добавить, убрать, чужой
адрес — ошибка под полем), связь с услугой, блок «только связанные с текущей» в услуге, все три
вида в предпросмотре, 375 px и тёмная тема. Плейграунд — фоновым npx vite --port 5187 в
apps/playground, открыть preview_start с url. В конце — «Итог T2» в §8 спеки (на своей ветке),
коммит, пуш в claude.
```

### T3 — `module-team`, php

```
Сессия T3 из §8 docs/architecture/WEBX_UI_MODULE_TEAM.md: composer-пакет webx-ui/module-team.

Worktree ../webx-ui-module-team, ветка feat/module-team (T1 уже на ней). git fetch claude и
влить свежий main, если ветка отстала. PR не открывать.

Прочитать: §§2,3,5,6 спеки и «Итог T1»; php/packages/module-reviews целиком — образец почти во
всём; php/packages/module-recipes/src/{Relations/*,Collections/RecipesSource.php} и экран
recipes.form (wx-relations с target service) — образец связи; php/packages/module-admin/src/
Screens/{ScreenRegistry.php,Types/OptionType.php,Types/RelationsType.php}.

Сделать: php/packages/module-team — composer.json с extra.webx и autoload files для helpers.php,
провайдер, config/webx-team.php §5.4, миграция §3, Member и MemberTarget §5.1, Cards и
TeamQuery (на RecordQuery) и team() §§5.2–5.3, TeamSource, опции соцсетей патчем из конфига
§5.4 (проверить set внутри wx-repeater), resources/blocks/team.json §5.5 со значками и скриптом
слайдера, API §5.7 (формы ответов — ровно как там: по ним параллельно пишется панель), экран
team.form, права и модуль панели §5.6, все слова §6 на десять языков, строка team() в
webx:doctor, README, LICENSE; регистрации §5.10 кроме плейграунда, сайта и smoke; тесты §5.11
кроме MCP; changeset на @webx-ui/php.

Не делать: npm (T2), MCP и демо (T4). Если форма ответа API должна отличаться от §5.7 — поправить
§5.7 тем же коммитом и сказать об этом в итоге крупно: T2 пишет мок по §5.7. В конце — «Итог T3»
в §8 спеки, коммит, пуш в claude.
```

### T4 — слияние, MCP, демо, доки

```
Сессия T4 из §8 docs/architecture/WEBX_UI_MODULE_TEAM.md: слияние панели, MCP, демо, гайд.

Worktree ../webx-ui-module-team, ветка feat/module-team. Первым делом: git fetch claude;
git merge claude/feat/team-panel (конфликт возможен в спеке — нужны все итоги — и в lang/en —
объединить ключи); убрать из плейграунда копии экрана и типа блока, если T2 их завёл, —
читать с диска файлы T3; worktree ../webx-ui-team-panel удалить (git worktree remove; если
каталог держит dev-сервер T2 — погасить его), ветку оставить до выпуска.

Прочитать: §§5.8,5.9 спеки и итоги T1–T3; php/packages/module-reviews/src/{Mcp/*,Demo/*} и
resources/demo; php/packages/module-recipes/src/Mcp/* (services как id или адрес);
apps/docs/guide/reviews.md; CLAUDE.md §4 про mcp:start (только трубой) и про возврат
webx-cms.local после local-режима из копий.

Сделать: TeamTools и team://catalog §5.8 (создание в транзакции), TeamDemo §5.9;
apps/docs/guide/team.md (team() и блок, «кто делает» на странице услуги, как добавить сеть —
конфиг плюс значок в своей копии блока, поля проекта патчем экрана, почему нет страницы и
категорий) и ссылка в сайдбаре; раздел про RecordQuery для авторов модулей — в
apps/docs/guide/collections.md; README npm-пакета, разделы MCP и демо в README
composer-пакета; team в автокомплите шаблона блоков; тесты MCP и демо; changeset. Гейт
php-половины и vitest/vue-tsc npm.

Проверить живьём инструменты через cat … | php artisan mcp:start webx на webx-cms.local в
local-режиме на этом worktree (MONOREPO=… packages.mjs local); до переключения скопировать в
скретчпад composer.json, composer.lock, package.json, package-lock.json и database/database.sqlite,
после — положить назад и composer install. Ничего на сайте не коммитить — это T5.
В конце — «Итог T4», коммит, пуш в claude.
```

### T5 — выпуск

```
Сессия T5 из §8 docs/architecture/WEBX_UI_MODULE_TEAM.md: выпуск module-team, оба демо.

Прочитать: итоги T1–T4; CLAUDE.md §5 целиком (очередь мержа — мутацией enqueuePullRequest, не
gh pr merge; первая публикация npm-пакета — человеком из changeset-release/main; тег
php-пакетов до публикации); docs/architecture/WEBX_UI_PHP_RELEASE.md; итог EV4 в
docs/architecture/WEBX_UI_MODULE_EVENTS.md — последний такой выпуск; память
webx-cms-local-demo-site, webx-cms-homelab-deploy и release-speed.

До релиза (руками пользователя, сессия напоминает и проверяет): репозиторий-зеркало
webx-ui/module-team на GitHub.

Сделать: погасить dev-серверы; полный гейт npm и php/ (PHP 8.4); module-team в
scripts/php-smoke.sh и smoke против MariaDB; PR, зелёный CI, постановка в очередь мутацией;
релизный PR от App; снять changeset-release/main в отдельный worktree, pnpm install, dist,
pnpm pack @webx-ui/module-team и проверить диапазоны @webx-ui/* в тарболе; первая публикация —
пользователь с 2FA, затем Trusted Publishing (webx-ui / webx-ui / release.yml); мерж релизного
PR; npm view всех поднятых пакетов и тег php-v<версия>; webx-ui/module-team на Packagist.
Удалить ветку feat/team-panel.

Демо: webx-cms.local — module-team в scripts/packages.mjs, link-panel.sh, composer require,
импорт и ...team() в resources/js/admin.ts руками (webx:panel --sync не трогает существующий
файл), migrate, webx:blocks:offered --install --module=team, cache:clear (словарь), демо §5.9
тинкером со своим журналом, npx vite build; хомлаб — то же в registry, npm ls
@webx-ui/module-admin — одна версия, коммит и пуш в Gitea. Строку реестра в
WEBX_UI_COMPOSER_PACKAGES.md (пункт 6 «Запланированы» зачеркнуть) и CLAUDE.md §§2,6 — отдельным
docs-PR.

Проверить живьём на обоих: страница /team — сетка, инициалы, значки соцсетей; услуга со
списком «кто делает»; человек без русского текста на /ru виден без текста; хелперы services(),
reviews(), recipes(), events() на страницах демо отвечают как до выпуска (T1 их переписал);
список с перетаскиванием и форма на настоящем телефоне (делает пользователь).
```

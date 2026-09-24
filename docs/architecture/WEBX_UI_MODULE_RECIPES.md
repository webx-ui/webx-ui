# `webx-ui/module-recipes` — спецификация и план реализации

Статус: спроектирован 24.09.2026, промпты сессий RC1–RC6 — в §6; RC1 (связи, php) сделан. Пакеты — `webx-ui/module-recipes` (composer) и
`@webx-ui/module-recipes` (npm).

Рецепты — записи с галереей, ингредиентами, способом приготовления, пищевой ценностью, временем и
порциями. У рецепта свой адрес и **страница жёсткой структуры, без блоков**: её рисует вьюха
модуля, а сайт меняет вид, публикуя вьюху. Рецепт лежит в плоских категориях (у них свой адрес,
как у категорий услуг), помечается «источниками» («железо», «клетчатка» — второй словарь, без
адреса) и **связан с услугами так же, как с категориями**. На другие страницы рецепты попадают
блоком (`wx-collection`), как вопросы FAQ и отзывы.

Образец — модель `Recipe` старого `omnivitality.local` (`app/Models/Recipe.php`): там же похожие
рецепты руками с подбором по совпадению и CTA на услугу.

Своего у модуля — данные, страница, разметка `Recipe` и подбор похожих. Главное новое для всей
системы — **общий механизм связей между сущностями** в `module-admin` (§3): рецепт → услуги и
рецепт → рецепты — два первых его потребителя, отзывы, FAQ и команда — следующие.

## 1. Границы

**Внутри этапа 1 (`module-admin`):** связи между сущностями на обеих половинах — таблица,
трейт, реестр целей, тип поля `wx-relations`, фильтр по связи в `wx-collection`; второй вид
категорий у одной модели.

**Внутри этапа 2 (`module-recipes`):** рецепты, их категории и источники, адреса, индекс,
страницы категории и рецепта, черновик, публикация и история, SEO и разметка `Recipe`, похожие
рецепты, тип блока «Рецепты», хелпер `recipes()`, экраны панели, API, MCP, демо.

**Снаружи:** блоки на странице рецепта (решение 2); пересчёт ингредиентов на число порций
(ингредиенты — текст, решение 6); порядок внутри категории и внутри услуги (решение 4); свои
поля omnivitality — «комментарий Кати», CTA (это `extra` и опубликованная вьюха сайта);
рейтинг и отзывы к рецепту; видео; импорт со старого сайта (§7).

## 2. Принятые решения (не переоткрывать)

Все приняты в обсуждении 24.09.2026.

1. **Модуль вне очереди, следующим** — третий пункт «Запланированы» реестра пакетов.
2. **Страница рецепта — жёсткая структура, не блоки.** Колонки `blocks` нет, зависимость от
   `module-blocks` — только ради предложенного блока-витрины (§5.8). Порядок частей задаёт вьюха
   (§5.5); сайт переставляет их в опубликованной копии.
3. **Черновик и версии**, как у услуг (`HasDraft`, `HasVersions`): «живой с правками»,
   предпросмотр, история. Булева «Опубликован» нет.
4. **Порядок только общий** (`position`). Ни `item_position` в категориях, ни порядка внутри
   услуги: список, отфильтрованный категорией, перетаскивать нельзя, и подсказка говорит почему.
5. **Категории плоские, как у услуг:** «многие ко многим», первая — главная (крошки), свой адрес,
   SEO, `extra`.
6. **Ингредиенты и способ приготовления — `wx-rich-text`, переводимые.** Не репитер. Разметка
   берёт из них `<li>` (нет списка — абзацы), поэтому подсказка под полем просит набирать списком.
7. **Пищевая ценность — текст, переводимый, фиксированный набор ключей**, одна JSON-колонка:
   `calories`, `protein`, `fat`, `carbohydrates`, `fiber` — ровно набор старого сайта. Пустое не
   печатается. schema.org и так хочет строки, поэтому текст идёт в разметку как есть.
8. **«Источник» — редактор пополняет список сам:** второй вид категорий у рецепта, без адреса,
   SEO и блоков (как категории FAQ), «многие ко многим», фильтр в списке панели.
9. **Фото — галерея**: список `wx-media` в одной JSON-колонке, **первая картинка — обложка**
   (карточки, витрина, `og:image`), в разметку уходят все.
10. **SEO обязательно:** карточка `wx-seo`, карта сайта, hreflang, крошки, разметка.
11. **`lead` и `servings` — колонками.** Время — `total_minutes`, целое.
12. **Связь с услугами — общим механизмом `module-admin`** (§3), а не своей таблицей: он же
    держит похожие рецепты и пригодится отзывам, FAQ, команде. `module-services` не обязателен —
    без него поля «Услуги» нет, остальное работает.
13. **Похожие рецепты:** выбранные руками, а если их нет — подбор (§5.6). Ручные — тем же
    механизмом связей (рецепт → рецепт).
14. **Адреса — `/{приставка}/{категория}` и `/{приставка}/{рецепт}`**, на одном уровне, как у
    услуг. Приставка настраивается (`webx-recipes.prefix`, по умолчанию `recipes`) и пустой не
    бывает. **Индекс `/{приставка}` отключается отдельно** (`webx-recipes.index`, по умолчанию
    включён): выключен — адрес свободен под страницу `module-pages` с блоками, в которую
    каталог вставлен блоком (решение 16).
15. **Слово «источник» в коде — `nutrient`** (`recipe_nutrients`, «Rich in» / «Источник» в
    интерфейсе): `source` уже занято контрактами (`CollectionSource`, `LinkSources`), и третье
    значение этого слова в одном модуле читалось бы как ошибка.
16. **Каталог — и страницей, и блоком.** Индекс по умолчанию — 24 на страницу с пагинацией, и
    тот же каталог (с пагинацией и фильтром по источникам) вставляется блоком на любую страницу;
    рядом остаётся витрина — «N рецептов» без пагинации. Индекс модуля рисует тот же фрагмент,
    что и блок: одна вёрстка, один запрос (§5.8).

## 3. Этап 1 — связи между сущностями (`module-admin`)

### 3.1. Что это и чем не является

**Связь** — запись одного модуля указывает на записи другого (или своего): рецепт → услуги,
рецепт → похожие рецепты, потом отзыв → услуги, вопрос FAQ → услуги. Это не категории (у
категории нет своей жизни вне модуля) и не ссылка `wx-link` (та — один адрес для перехода, а не
данные). Имя в коде — **`Relations`**: `Links` уже занято пикером адресов.

Почему одна общая таблица, а не связь на каждую пару модулей, хотя у категорий решили наоборот
(§2.3 спеки услуг): **цель связи — чужой модуль, и его может не быть.** Внешний ключ из
`recipes_services` в `services` не создать, пока услуг нет, а поставленные потом услуги
потребовали бы миграцию у рецептов. Общая таблица без внешних ключей не зависит ни от порядка
установки, ни от порядка миграций (CLAUDE.md §4), а обратный вопрос «какие рецепты у этой
услуги» задаётся одинаково для любой пары.

### 3.2. Схема

```
webx_relations                       -- миграция module-admin, 2026_01_01_*: ссылок на чужое нет
  id
  owner_type   string(64)            -- ключ владельца в реестре (§3.3): 'recipe'
  owner_id     bigint unsigned
  role         string(32)            -- имя связи у владельца: 'services', 'related'
  target_type  string(64)            -- ключ цели в реестре: 'service', 'recipe'
  target_id    bigint unsigned
  position     int default 0         -- порядок выбранного у владельца (как его расставили в поле)
  timestamps
  unique (owner_type, owner_id, role, target_type, target_id)
  index  (target_type, target_id)    -- обратный вопрос
```

- Типы — **ключи реестра**, а не имена классов: класс переименуют, данные останутся. Это те же
  имена, что у типов адресов в `routing` (`service`, `recipe`), чтобы у сущности было одно имя.
- Порядок внутри связи — порядок выбора в поле; он нужен шаблону («сначала основная услуга»), а
  не решение 4 (то про порядок записей в списках).

### 3.3. Реестр целей

`Relations\RelationTargets` — по образцу `CategorySources` и `CollectionSources`: модуль
регистрирует из своего провайдера (не из маршрутов — `route:cache`, итог K2):

```php
$targets->register(new RelationTarget(
    key: 'service',
    model: Service::class,
    permission: 'services.view',        // кто может видеть кандидатов в пикере
    label: 'webx-services::relations.service',
));
```

Цель отвечает за: поиск кандидатов для пикера (`candidates(string $q, string $locale)` —
id, название на языке панели, подпись, миниатюра), названия выбранных по id одним запросом,
видимость на сайте (`Visible` §17.2 спеки SEO — невидимая цель на сайте выпадает, в панели
остаётся с пометкой). По умолчанию всё это берётся из модели: переводимый `title`, обложка, если
модуль сказал, какая колонка, и `Visible`; модуль переопределяет, что нужно.

**Удаление.** `module-admin` вешает `forceDeleted` на модель каждой зарегистрированной цели и
на модель владельца (`HasRelations`, §3.4) и удаляет строки с обеих сторон. Мягкое удаление
строки не трогает: восстановленная услуга возвращается со своими рецептами. Модуль-цель при этом
ничего не пишет у себя, кроме регистрации.

**Модуль-цель снят** — строки остаются (ключ цели ни с чем не сопоставлен), чтение их пропускает,
`webx:doctor` говорит, сколько таких и чьи. Вернули модуль — связи на месте.

### 3.4. Модель владельца

`Relations\HasRelations` (трейт):

- `relationKey(): string` — ключ владельца в реестре (`recipe`).
- `related(string $role): Collection<Model>` — цели по порядку, только те, чей модуль стоит.
  Предзагрузка списком — `Relations::load($records, 'services')`: один запрос на связь и один на
  тип цели, а не запрос на строку (карточки списка, итог про `RELATIONS` у услуг).
- `relatedIds(string $role): list<int>`, `syncRelated(string $role, string $targetType, list<int> $ids)`.
- `scopeRelatedTo(Builder, string $role, string $targetType, int|list<int> $ids)` — «рецепты этой
  услуги»; `Relations::owners('recipe', 'services', $service)` — то же с другой стороны.

У сущности с `HasDraft` выбор идёт в черновик вместе с колонками и применяется публикацией —
ровно как `categories` у услуги (`ScreenRecord` раскладывает `wx-relations` в «связи», §3.4
спеки услуг). Поэтому «привязал рецепт к услуге» на сайте появляется по «Опубликовать».

### 3.5. Тип поля `wx-relations`

```json
{
  "id": "services",
  "name": "services",
  "type": "wx-relations",
  "label": "Services",
  "props": { "target": "service", "max": null }
}
```

- **Сервер** (`Screens\Types\RelationsType`): значение — список id по порядку; `store()`
  отбрасывает несуществующие и повторы; цели нет в реестре — узел **снимается с экрана**
  (`ResolvesMissing`/скрытие пустого, как пустые контейнеры у рендерера, итог K2), и значение не
  трогается. Так «Услуги» пропадают из формы рецепта на сайте без `module-services`.
- **Панель** (`WxRelationsField`): выбранные строками с перетаскиванием и удалением, «Добавить» —
  поиск по `GET /api/cms/relations/{target}?q=` (§3.7). Выбранная, но невидимая на сайте цель —
  с пометкой («снята», «в корзине»).
- Роль связи — `name` узла: поле `services` пишет роль `services`. Цель `recipe` у самого рецепта
  — это похожие рецепты; себя пикер не предлагает.

### 3.6. Фильтр по связи в `wx-collection`

`Selection` получает третий фильтр — `related: { type: 'service', ids: [7] }` рядом с
`categories`: «рецепты этой услуги» в блоке на странице услуги. Источник, который связи не
поддерживает, его не видит (`CollectionSource::relations(): list<string>` — какие типы целей
у его записей можно выбрать; пустой — фильтра нет, как `categories() = null`).

В поле `wx-collection` это ещё один выбор под категориями: «Только связанные с: [услуга]».
**«Связанные с той записью, на странице которой стоит блок»** — отдельный флаг `related.current`
(сделан в RC1: рендер блока свою сущность знает).

Значение, как его хранит `store()` — форма, которую пишет панель (RC2), сервер (RC1) ей совпадает:

```json
{
  "categories": [],
  "limit": null,
  "filter": false,
  "markup": null,
  "related": { "type": "service", "ids": [3, 7] }
}
```

- Ключей всегда пять; `related` — `null`, пока ничего не сужает: `type` без `ids` — тоже `null`,
  у «не сужено» одно написание.
- `ids` — по возрастанию, без повторов, как `categories`.
- «Связанные с этой страницей» — `{ "type": "service", "ids": [], "current": true }`: ключ
  `current` пишется только включённым, `ids` при нём всегда `[]`. Панель его пока не рисует (RC5).
- Разметка по умолчанию выключена и при `related`: сужено связью — та же «часть коллекции»,
  что и категория.
- `type` не из `relations()` источника — `related` выбрасывается; правило поля отвечает
  `webx-admin::collections.unknown-relation`, несуществующий id — `…unknown-related`.
- `current` на странице записи того же типа — её id; на странице записи другого типа — ничего;
  на `sample` блока (конструктор, публикация типа) — фильтра нет, блок виден целиком.

`GET /api/cms/collections` несёт у источника `relations: [{ key, title }]` — только цели, которые
на сайте кто-то зарегистрировал, `title` — `label` цели на языке панели; пусто — выбора
«связанные с» в поле нет.

### 3.7. API

```
GET /api/cms/relations/{target}?q=&except[]=  → { data: [{ id, title, subtitle, thumb, visible, trashed }] }
GET /api/cms/relations/{target}?ids[]=        → то же, в порядке ids
```

`ids[]` — названия уже выбранных (форма открывается с id): в порядке запроса, **корзина
включена** (`trashed: true`, `visible: false`), удалённых насовсем в ответе нет — поле рисует их
«больше не существует». Без `ids[]` — кандидаты: до 20, корзины нет, `except[]` — кого не
предлагать (сама запись, когда цель — её же тип: похожие рецепты). `visible: false` без
`trashed` — «не на сайте» (снята с публикации, скрыта). Права — `permission` цели; без них 403,
и поле рисуется только списком выбранного, без «Добавить»; неизвестная цель — 404.
`trashed` и `except[]` добавлены в RC1.

### 3.8. Второй вид категорий у одной модели

`HasCategories` сейчас знает одну связь (`categoryRelation()`). У рецепта их две: категории и
источники. Трейт получает имя связи параметром там, где его сейчас берёт из
`categoryRelation()` (`syncCategories(array $ids, ?string $relation = null)`,
`scopeInCategory(..., ?string $relation = null)`), а `categoryRelation()` остаётся связью «по
умолчанию» — главной категорией, крошками. Поле `wx-categories` уже различает виды по `source`
(`recipes/categories`, `recipes/nutrients`), `CategoryForm` и `CategoryRoutes` — по модели.
Порядок внутри категории у рецепта не используется (решение 4), но колонку `item_position`
макрос `categoryLinks()` всё равно заводит — общий код её пишет, и выкидывать её ради одного
модуля значит ветвить общий код.

### 3.9. Готово, когда

- `module-services` регистрирует цель `service`; тесты `module-admin` на фикстурах двух моделей
  проходят все пункты §3.3–§3.6, включая `forceDeleted` с обеих сторон и снятый модуль-цель;
- `wx-relations` на плейграунде: выбрать, переставить, убрать, пометка невидимой цели, 375 px;
- `wx-collection` с фильтром по связи через **настоящий путь записи** обеих дверей (панель и
  `blocks_edit_content`), как у FAQ.

## 4. Схема `module-recipes`

```
recipes
  id
  title          json nullable      -- переводимый
  slug           json nullable      -- переводимый
  lead           json nullable      -- переводимый, без разметки: карточки, description
  gallery        json nullable      -- список значений wx-media; первая — обложка (решение 9)
  ingredients    json nullable      -- переводимый HTML (wx-rich-text)
  method         json nullable      -- переводимый HTML (wx-rich-text)
  nutrition      json nullable      -- { calories: {ru,en}, protein: …, fat: …, carbohydrates: …, fiber: … }
  total_minutes  unsignedSmallInteger nullable
  servings       unsignedTinyInteger nullable
  position       int default 0      -- общий порядок (решение 4)
  extra          json nullable      -- поля проекта
  draft()                           -- module-admin
  softDeletes, timestamps

recipe_categories         category(); lead json (wx-rich-text), cover json (wx-media)
recipe_category_recipe    categoryLinks('recipe', 'recipe_categories')
recipe_nutrients          category(); slug остаётся пустым
recipe_nutrient_recipe    categoryLinks('recipe', 'recipe_nutrients')

-- услуги и похожие рецепты — webx_relations (§3), своих таблиц нет
```

- `gallery` и `cover` — json, а не внешний ключ: ссылок на чужие таблицы нет, все миграции на
  `2026_01_01_*` (CLAUDE.md §4 о сортировке).
- `nutrition` — одна колонка с картой языков **на каждом ключе**, а не карта ключей на каждом
  языке: форма рисует одно поле на ключ с чипом языка, как у остальных переводимых полей, и
  `HasTranslations` здесь не годится (он переводит колонку целиком). Разбор по языку — у модели
  (`nutrition(string $locale): array<string, string>`), неизвестные ключи `store()` отбрасывает.
- `servings` — число: подпись «Порций: 4», счёт в конце строки (CLAUDE.md §4 про `:count`).

## 5. Модуль

### 5.1. Модели

- `Recipe` — `HasCategories` (две связи, §3.8), `HasRelations` (`services`, `related`),
  `HasDraft`, `HasVersions`, `HasSeo`, `HasBreadcrumbs`, `HasStructuredData`, `HasExtra`,
  `HasTranslations` (`title`, `slug`, `lead`, `ingredients`, `method`), `SoftDeletes`. Новый
  рецепт встаёт в конец общего порядка.
- `RecipeCategory` — `IsCategory`, адрес, SEO, как `ServiceCategory` (без блоков).
- `RecipeNutrient` — `IsCategory` без адреса; `slug` обнуляется на каждом `saving`, как у
  `FaqCategory`.

### 5.2. Зависимости

`require`: `module-admin`, `module-media`, `module-seo`, `routing`, `localization`, `mcp`.
`suggest`: `module-blocks` (блок-витрина), `module-services` (поле «Услуги»), `module-pages`.
`require-dev`: `module-blocks`, `module-services`, `module-pages` — ради тестов и демо.
Блок предлагается через `BlockOffers` за `class_exists`, как разметка у FAQ.

### 5.3. Адреса

| Тип               | Форматтер                        | Пример                        |
| ----------------- | -------------------------------- | ----------------------------- |
| `recipe`          | `Prefixed($prefix, Slug::class)` | `recipes/ovsyanka-s-yagodami` |
| `recipe-category` | `Prefixed($prefix, Slug::class)` | `recipes/zavtraki`            |

`OnConflict::Fail`. Приставка обязательна: пустая — отказ `webx:doctor` и исключение при
загрузке конфига, потому что плоские адреса рецептов в корне сайта спорили бы с деревом страниц.
Индекс — маршрут `{prefix}` с именем `webx.recipes.index` в `SitemapRoutes`, **только при
`webx-recipes.index = true`**; выключен — маршрута нет, и `Reserved` отдаёт путь `{prefix}`
странице (CLAUDE.md §4: «Главная не получает адрес, пока у сайта есть свой маршрут»). Смена
приставки — `webx:routes:rebuild --type=recipe --type=recipe-category`, старые адреса остаются
алиасами с 301. Оба типа регистрируются и как `LinkSource` (меню, `wx-link`), и как цели связей
(§3.3) — `recipe` для похожих.

### 5.4. Публичная часть

- **Индекс** `{prefix}` (если включён) — фрагмент каталога §5.8 на всю ширину:
  `webx-recipes.per-page` (24) на страницу, `?page=`. Категории — ссылками на свои страницы,
  источники — фильтром на месте (`?nutrient=<id>`: адреса у источника нет). Отфильтрованная и
  не первая страница — `canonical` на себя без фильтра, `noindex` у фильтра.
- **Категория** — вступление, обложка и тот же фрагмент каталога, ограниченный категорией.
- **Рецепт** — §5.5.

Вьюхи — из конфига с фолбэком на вьюхи пакета, публикуются в `resources/views/vendor/webx-recipes`;
макет — общий шов (`WEBX_UI_NEW_SITE.md`). Видимость: рецепт — опубликован и не в корзине;
категория — `is_visible`; рецепт в скрытой категории виден по своему адресу.

### 5.5. Страница рецепта

Одна вьюха `recipe.blade.php`, части — отдельными `@include`, чтобы сайт мог переопределить одну
часть, не публикуя остальное:

1. галерея (первая крупно, остальные лентой; одна — без ленты);
2. заголовок и `lead`;
3. факты: время (`45 min` / «1 h 15 min»), порции, категории ссылками, источники чипами;
4. ингредиенты (HTML как есть — `store()` у `wx-rich-text` уже чистит разметку);
5. способ приготовления;
6. пищевая ценность — таблица из непустых ключей, нет ни одного — нет таблицы;
7. услуги — карточки видимых связанных услуг (есть `module-services` и связи);
8. похожие рецепты — §5.6.

Всё, что сайт выводит своё (комментарий автора, CTA), — поля проекта в `extra` и строки в
опубликованной вьюхе: `{{ $recipe->extra('author-note') }}`.

### 5.6. Похожие рецепты

`Recipe::similar(int $limit)` — `webx-recipes.similar` (3):

1. **Выбраны руками** (роль `related`) — они, видимые, по порядку выбора; даже если их меньше
   лимита, подбор их **не добивает** (выбрал два — значит два).
2. **Не выбраны** — подбор среди видимых, кроме самого рецепта: общая категория — 2 очка, общая
   услуга — 2, общий источник — 1; ноль очков не берётся; при равенстве — общий порядок.
   Один запрос на кандидатов с подсчётом совпадений в SQL, а не перебор всех рецептов в php, как
   на старом сайте.

### 5.7. SEO и разметка

- `HasSeo` у рецепта и категории, карточка `wx-seo` патчем от `module-seo`.
- Крошки: рецепт — индекс → главная категория → рецепт; категория — индекс → категория.
  «Индекс» — то, что реестр адресов отдаёт по пути `{prefix}`: маршрут модуля или страница
  `module-pages`, которая встала на его место (решение 14). Нет ни того ни другого — звено
  пропускается.
- **`Recipe`** (`HasStructuredData`): `name`, `description` (`lead`), `image` — все картинки
  галереи, `url`, `totalTime` (`PT45M`), `recipeYield` (`servings`), `recipeCategory` (главная
  категория), `recipeIngredient` — `<li>` ингредиентов текстом (нет списка — абзацы),
  `recipeInstructions` — `HowToStep` на каждый `<li>` (нет списка — абзацы),
  `nutrition` — `NutritionInformation` из непустых ключей (`calories`, `proteinContent`,
  `fatContent`, `carbohydrateContent`, `fiberContent`), `author` — `@id` `Organization` из
  настроек SEO. Проверять на validator.schema.org **и** в Rich Results Test (рецепты Google
  показывает любому сайту, в отличие от FAQ — CLAUDE.md §4).
- Категория и индекс — `ItemList` через `Seo::push()`.

### 5.8. Хелпер `recipes()`, источник `recipes`, блок «Рецепты»

- `Rendering\RecipeQuery` по образцу `ServiceQuery`: `in($categories)`, `nutrients($ids)`,
  `relatedTo('service', $ids)`, `only()`, `except()`, `take()`, `locale()`, `get()`, `first()`.
  Карточка (`Rendering\Cards`): `id`, `url`, `title`, `lead`, `cover`, `gallery`, `minutes`,
  `servings`, `categories` (id), `nutrients` (`[{id, title}]`), `fields`.
- `Collections\RecipesSource` — `key() = 'recipes'`, `categories() = 'recipes/categories'`,
  `relations() = ['service']`, `supportsMarkup() = false` (разметка `Recipe` — у страницы рецепта,
  не у витрины), `permission() = 'recipes.view'`.
- **Фрагмент каталога** `partials/catalog.blade.php` — сетка карточек, строка фильтра по
  источникам, пагинация. Его рисуют индекс, страница категории и блок; сайт, опубликовав
  фрагмент, меняет все три сразу.
- Тип блока `resources/blocks/recipes.json` через `BlockOffers`: заголовок, `wx-collection`
  (категории, услуги, лимит), **вид** `mode` — `showcase` (витрина: лимит, без пагинации,
  кнопка «все рецепты» на то, что стоит по пути `{prefix}`) или `catalog` (каталог:
  `per_page`, пагинация `?page=`, фильтр по источникам); колонки (пусто — 3). Пустой `mode` —
  `showcase`: блок хранит только тронутое (`ResolvesMissing`, итог F1 спеки FAQ). Без JS
  читается целиком — фильтр и пагинация ссылками.
- **Два каталога на одной странице** делят `?page=` и `?nutrient=`. Параметры с ключом блока
  — усложнение ради случая, которого на сайтах не бывает; в гайде это сказано одной строкой.

### 5.9. Панель

Группа меню «Recipes»: **Recipes · Categories · Rich in**.

**Список рецептов** — как у услуг, без пагинации (перетаскивание по страницам не работает): при
сотнях рецептов это медленнее, но честно; если окажется тяжело — см. §7. Строка: обложка,
название со слагом, категории чипами, время, статус, `WxRowMenu`. Фильтры: категория, источник,
услуга (если стоит), статус, поиск. Перетаскивание — **только без фильтров** (решение 4).

**Редактор** — экран `recipes.form`, вкладки **Recipe · Settings · SEO · History**:

- **Recipe** — галерея (`wx-media`, список), ингредиенты и способ приготовления (`wx-rich-text`,
  `localized`, подсказка «списком»), пищевая ценность — пять `wx-input` с `localized` в одной
  карточке (узел `wx-recipe-nutrition` или просто пять полей с именами `nutrition.calories` —
  решить в RC3 по тому, что проще отдаёт `ScreenRecord`; имена с точкой буквальны, CLAUDE.md §4).
- **Settings** — заголовок и адрес, `lead` со счётчиком, время, порции, категории
  (`wx-categories`), источники (`wx-categories`, `main: false`), услуги (`wx-relations`, target
  `service`), похожие (`wx-relations`, target `recipe`, подсказка «пусто — подберутся сами»),
  карточка `project-fields`.
- **SEO**, **History** — как у услуг.

Панель действий, ревизия (409), автосейв в черновик, предпросмотр по токену — как у услуг.
Категории и источники — общие страницы (`categoryRoutes`), экраны `recipes.category-form`
(название, адрес, видимость, вступление, обложка, SEO, поля проекта) и `recipes.nutrient-form`
(название, видимость, поля проекта).

Права: `recipes.view`, `recipes.manage`, `recipes.categories.manage` (им же — источники). Id
модулей панели: `recipes`, `recipe-categories`, `recipe-nutrients`.

### 5.10. API панели

Формы зафиксированы заранее, чтобы RC3 и RC4 шли параллельно:

```
GET    /api/cms/recipes            ?category=&nutrient=&service=&status=&search=&trashed=1
  → { data: [{ id, title, slug, url, cover: { thumb } | null, minutes, status, position,
               categories: [{ id, title }], updated_at, deleted_at }],
      filters: { categories: [{id,title}], nutrients: [{id,title}], services: [{id,title}] | null } }
POST   /api/cms/recipes            { title, slug? }                   → 201 { data: { recipe, values } }
GET    /api/cms/recipes/{id}       → { data: { recipe, values, revision, preview_url } }
PUT    /api/cms/recipes/{id}       { values, revision }                409 на устаревшей, 422 под полем
POST   /api/cms/recipes/{id}/discard | publish | unpublish | restore
DELETE /api/cms/recipes/{id}
GET    /api/cms/recipes/{id}/versions
POST   /api/cms/recipes/{id}/versions/{number}/restore
POST   /api/cms/recipes/reorder    { ids }                            без category (решение 4)
       /api/cms/recipes/categories/*, /api/cms/recipes/nutrients/*    общие маршруты категорий
       /api/cms/relations/{target}                                    §3.7
```

`status` — `draft | live | live-changed | unpublished`, как у услуг. `recipe` в ответе формы —
`{ id, title, url, status, deleted_at }`; `values` — всё с экрана: переводимые картами языков,
`gallery` (список значений `wx-media`), `nutrition` картой ключей, `categories`, `nutrients`,
`services`, `related` (id по порядку), поля проекта. Сохранение — одна транзакция (урок
`services_create`: отказ не оставляет голую строку).

### 5.11. MCP

`recipes_list`, `recipes_get`, `recipes_create`, `recipes_update`, `recipes_publish`,
`recipes_unpublish`, `recipes_delete`, `recipes_reorder` — через те же `Panel\*`;
`recipe_categories_*`, `recipe_nutrients_*` — общий `CategoryTools`. Услуги и похожие агент пишет
id в `recipes_update` (`services`, `related`); услугу можно назвать и адресом, как в
`services_*`. Ресурс `recipes://catalog`: категории с рецептами, источники, у каждого рецепта
адрес и `written_in`. Ингредиенты и способ — HTML-список; в описании инструмента это сказано,
иначе разметка останется без `recipeIngredient`.

### 5.12. Демо

`RecipesDemo`, `resources/demo/recipes.json` (en и ru): три категории, пять источников, шесть
рецептов — один в двух категориях, один черновиком, один без русского, у одного похожие руками,
у одного пищевая ценность пустая; картинки — те же, что демо услуг берёт из библиотеки, или без
галереи (карточка без обложки тоже проверка). `requires()` динамический: `media`, плюс
`services` (связать два рецепта с демо-услугами из журнала), `blocks` + `pages` (страница
`/recipes-showcase` с витриной «три рецепта категории» и каталогом ниже — оба вида блока на
живом сайте).

### 5.13. Регистрации

Всё из CLAUDE.md §4 («прописать в `php/` четыре раза», «раздел панели — в четырёх местах»):
`php/composer.json`, `phpunit.xml.dist`, `phpstan.neon.dist`, `Setup\Catalogue`,
`extra.webx.npm`/`extra.webx.panel`, `apps/playground/src/panel/main.ts`, `scripts/packages.mjs`
в `webx-cms.local` и строка в `scripts/php-smoke.sh` (RC6); `module-services` регистрирует цель
`service` (RC1); иконка группы — по набору (`icons.test.ts`).

### 5.14. Тесты, которые обязательны

- Черновик: связи и категории применяются публикацией, а не сохранением.
- Похожие: ручные не добиваются подбором; подбор по очкам §5.6; невидимые выпадают.
- Разметка: `recipeIngredient` и `HowToStep` из `<li>` и из абзацев; пустая пищевая ценность —
  нет `nutrition`; `totalTime` для 45 и 75 минут.
- Без `module-services`: поля «Услуги» нет на экране, сохранение рецепта с `services` в values
  не падает и значение не трогает.
- `nutrition`: неизвестный ключ отброшен, пустой язык не печатается.
- Индекс с пагинацией и фильтром; `index = false` — маршрута нет, страница `module-pages` по
  пути `{prefix}` получает адрес и встаёт в крошки рецепта; пустая приставка — отказ.
- Блок в виде `catalog`: вторая страница, фильтр по источнику, `canonical`/`noindex`.
- Блок-витрина на своём `sample` и с фильтром по услуге — через настоящий путь записи.

## 6. Пошаговый план

**Выпуск один, в конце** (RC6), до него ни PR, ни ожидания CI — как у FAQ и отзывов. Каждая
сессия гонит локальный гейт своей половины; полный — только RC6. Одна ветка
`feat/module-recipes` (worktree `../webx-ui-module-recipes`), параллельные сессии — на своих
ветках, их сливает следующая.

| Сессия  | Ветка / worktree                                      | Что                                                                |
| ------- | ----------------------------------------------------- | ------------------------------------------------------------------ |
| **RC1** | `feat/module-recipes`                                 | php `module-admin`: связи §3 целиком, §3.8; цель `service`         |
| **RC2** | `feat/relations-panel` / `../webx-ui-relations-panel` | npm: `wx-relations`, фильтр по связи в `wx-collection`, плейграунд |
| **RC3** | `feat/module-recipes`                                 | php `module-recipes`: §§4–5 кроме MCP и демо                       |
| **RC4** | `feat/recipes-panel` / `../webx-ui-recipes-panel`     | npm `module-recipes`: панель по §5.10, плейграунд                  |
| **RC5** | `feat/module-recipes`                                 | слияние RC2 и RC4, MCP, демо, гайд, README, doctor                 |
| **RC6** | `feat/module-recipes`                                 | выпуск, оба демо                                                   |

RC1 ∥ RC2 (API поля — §3.7), затем RC3 ∥ RC4 (API — §5.10). Промпты ниже самодостаточны. В конце
каждой сессии — «Итог RCn» сюда (что следующей надо знать сверх промпта) и строка в память
`custom-modules-workflow`.

Общее для всех: gh не в PATH — `"C:\Program Files\GitHub CLI\gh.exe"`; пушить в `claude`, не в
`origin`; php-гейт — `composer lint && composer analyse && composer test` из `php/` на
`C:\Work\OSPanel\modules\PHP-8.4\php.exe` (в свежем worktree сначала прогреть манифест Testbench
последовательно — CLAUDE.md §4 «И то же самое на пустом `vendor`»); npm — точечно
`npx vitest run <файлы> --pool=forks --poolOptions.forks.singleFork` **из корня worktree**,
`npx vue-tsc -p tsconfig.json --noEmit` в пакете, eslint и prettier на своих файлах. `pnpm` в
worktree с симлинком на `node_modules` не запускать (CLAUDE.md §4).

### RC1 — связи, php (`module-admin`)

```
Сессия RC1 из §6 docs/architecture/WEBX_UI_MODULE_RECIPES.md: общий механизм связей в
module-admin, php-половина. Идёт параллельно с RC2.

Начало: git fetch claude; git worktree add ../webx-ui-module-recipes -b feat/module-recipes
claude/main (если PR #284 со спекой ещё не смержен — от claude/docs/plan-recipes). В php/ этого
worktree: composer install, прогреть манифест Testbench (CLAUDE.md §4). PR не открывать.

Прочитать: §§2,3 спеки целиком; php/packages/module-admin/src/{Categories/*,Collections/*,
Links/LinkSources.php,Screens/ScreenRecord.php,Screens/Types/*} — образцы реестра, типа поля и
раскладки значений; docs/architecture/WEBX_UI_MODULE_SERVICES.md §3 и итоги K1, K2 — как
выносили категории; итог F1 в docs/architecture/WEBX_UI_MODULE_FAQ.md (ResolvesMissing, Selection);
php/packages/module-services/src/ServicesServiceProvider.php.

Сделать в php/packages/module-admin: миграция webx_relations §3.2; Relations\RelationTarget,
RelationTargets (синглтон, регистрация из провайдера), HasRelations §3.4 с предзагрузкой
Relations::load() и обратным Relations::owners(); forceDeleted с обеих сторон §3.3;
Screens\Types\RelationsType (wx-relations) §3.5 — узел снимается с экрана, если цели нет в
реестре, значение при этом не трогается; ScreenRecord раскладывает wx-relations в связи, у HasDraft
— в черновик (как categories у услуги); маршрут GET /api/cms/relations/{target} §3.7 с правами
цели; Selection + CollectionSource::relations() §3.6 (фильтр related, нормализация, источник без
связей его не видит); HasCategories со второй связью §3.8 (имя связи параметром, по умолчанию —
categoryRelation()); строка в webx:doctor про связи на снятые модули; слова webx-admin::relations.*
на десять языков. В php/packages/module-services — регистрация цели service (+ слово в lang).
Тесты на фикстурах двух моделей: всё из §3.9 php-стороны, включая forceDeleted с обеих сторон,
мягкое удаление не трогает строки, снятый модуль-цель, черновик применяет связи публикацией,
wx-collection с related через настоящий путь записи обеих дверей. Changeset на @webx-ui/php
(minor).

Выяснить и записать в итог: знает ли рендер блока сущность, на странице которой стоит
(module-blocks, контекст рендера — Stage, Renderer, то, что передаёт module-pages/module-services).
Знает — сделать related.current в Selection (§3.6); не знает — не делать и дописать в §7.

Не делать: npm (RC2), module-recipes (RC3). Если форма ответа §3.7 или значение поля всё-таки
должны отличаться — поправить спеку тем же коммитом и сказать об этом в итоге крупно: RC2 пишет мок
по ней. В конце — «Итог RC1», коммит, пуш в claude.
```

#### Итог RC1 — сделано 24.09.2026

Всё из промпта на ветке `feat/module-recipes`; гейт php-половины зелёный (pint, phpstan, все
тесты на PHP 8.4). Что следующим сессиям надо знать сверх §3:

- **RC2 закончил раньше, и сервер подогнан под то, что пишет панель** (итог RC2 ниже):
  `/api/cms/collections` — `relations: [{ key, title }]`; значение `wx-collection` — пять ключей,
  `related: { type, ids } | null`; разметка по умолчанию выключена и при `related`; слова —
  `webx-admin::relations.*` ровно под именами RC2 (английский совпадает с `messages.ts`). **Сверх
  RC2 на сервере — только добавления, панель их может не знать:** в ответе `/relations/{target}`
  есть `trashed` (корзину можно пометить отдельно от «не на сайте» — слово `field-trashed`),
  кандидатов режет необязательный `except[]` (панель исключает себя сама через
  `provideRelationOwner`), у `related` бывает `current: true` (§3.6; слова
  `collection-related-current` и `…-current-hint` уже в lang — рисовать в RC5). Отказ «больше
  `max`» сервер говорит словом панели `field-full`. **RC5:** `lang/*/relations.php` уже под
  ключами RC2 — сливать нечего; остаётся дописать в `messages.ts` три новых ключа
  (`field-trashed`, `collection-related-current`, `collection-related-current-hint`) и добавить
  группу `relations` в тест паритета.
- **Рендер блока свою сущность знает — `related.current` сделан.** `renderBlocks()` у страниц,
  услуг и статей отдаёт рендереру `$this`, но до типа поля она не доходила: `Values::resolve()`
  звал `resolve()` без неё. Теперь есть маркер `Screens\ResolvesForEntity` (`resolveFor()`), и
  `Rendering\Values` передаёт сущность только такому типу; вложенные блоки получают ту же. Сущность
  называется ключом цели (`RelationTargets::keyOf()`), поэтому «связанные с этой страницей» на
  странице `module-pages` не показывает ничего — страницы не цель.
- **Код** — `WebxUi\Admin\Relations\{RelationTarget, RelationTargets, HasRelations, Relations}`,
  тип `Screens\Types\RelationsType`, маркер `Screens\Withdraws`, `Http\Controllers\RelationController`,
  `Doctor\Checks\Relations`. Цель по умолчанию читает переводимый `title` (или `name`), `isVisible()`,
  корзину и `position`; модуль наследует и переопределяет `subtitle()`/`thumb()`/`query()` — так
  сделан `WebxUi\Services\Relations\ServiceTarget` (подпись — категории, миниатюра — обложка).
- **Как модуль пишет связи с экрана** — три строки, RC3 делает так же: `ScreenRecord::split()`
  сам уносит поля `wx-relations` в `$split->relations` (в `own`/`taken`/`extra` их не называть);
  после записи черновика — `$screenRecord->saveRelations($model, $split)`; в `values` формы —
  `...$screenRecord->relationValues($screen, $model)`. Писатель черновика должен строить его от
  `draftValues()`, как `ServiceWriter`: черновик, собранный с нуля, потеряет ключ связей. У модели с `HasDraft` выбор лежит в черновике под
  ключом `Relations::DRAFT` (`relations`), по роли, и применяется публикацией через мутатор
  `setRelationsAttribute()` + `saved`; `withDraft()` (предпросмотр) читает его. Роль, вернувшаяся
  к опубликованному, из черновика уходит — сохранение без изменений не делает запись «изменённой».
  **Спека говорила «ровно как categories у услуги» — у услуги категории как раз не черновые**,
  они пишутся сразу (`ServiceWriter`); у рецепта §5.14 требует категории публикацией — это RC3
  делает у себя (например, тем же ключом черновика), общий код для категорий этого не умеет.
- **Снятый модуль-цель** — узел `wx-relations` уходит из `ScreenRegistry::tree()` (не из кеша
  патчей: цели регистрируют другие провайдеры), значит и из ответа экрана, и из `validate()`:
  присланное значение не пишется ни в связи, ни в `extra`, строки в таблице не трогаются.
- **Удаление**: `RelationTargets::register()` вешает слушатель `eloquent.forceDeleted: <модель>`
  (или `deleted` у модели без мягкого удаления) через диспетчер, у владельца — `bootHasRelations()`.
  Корзина строк не трогает.
- **`HasCategories`** — `categoryLinks(?string $name)`, `syncCategories($ids, ?string $name)`,
  `scopeInCategory($q, $id, ?string $name)`; главная категория и `orderedIn` — по-прежнему
  связь по умолчанию.
- **Попутно починено:** панель услуг сохраняла блоки мимо типов полей (`storeBlocks()` не
  вызывался — грабля CLAUDE.md §4 «значения идут через тип поля»); у блога та же дыра, вынесена
  отдельной задачей. `CollectionSource` получил метод — все источники (`faq`, `reviews`, `services`
  и фикстуры) отвечают `relations(): []`.
- История версий связей не хранит (§7).
- Worktree: `php/vendor` поставлен, манифест Testbench прогрет; parallel phpstan на свежем
  манифесте падал «Access is denied» на `services.php` — помог один прогон `phpstan --debug` по
  одному каталогу (он однопроцессный) после удаления `bootstrap/cache/*`.

### RC2 — связи, npm (`module-admin`)

```
Сессия RC2 из §6 docs/architecture/WEBX_UI_MODULE_RECIPES.md: поле wx-relations и фильтр по связи
в wx-collection, npm-половина module-admin. Идёт параллельно с RC1, php не трогает.

Начало: git fetch claude; git worktree add ../webx-ui-relations-panel -b feat/relations-panel
<та же база, что у RC1: claude/main или claude/docs/plan-recipes>; pnpm install --frozen-lockfile
в этом worktree (каталог обычный — node_modules будет свой, CLAUDE.md §4); собрать dist у tokens,
core, schema. PR не открывать.

Прочитать: §§3.5–3.7,3.9 спеки; packages/module-admin/src/collections/* (WxCollectionField — итог
F2 спеки FAQ) и поле wx-categories — образцы; apps/playground/server/panel/ (faq.ts, services) —
как устроены фикстуры; CLAUDE.md §4 про перетаскивание в панели браузера (клавиатурой на ручке),
про фрагмент в корне компонента и :deep(), про 375 px.

Сделать в packages/module-admin: WxRelationsField (тип wx-relations): выбранные строками с
перетаскиванием (WxSortableList) и удалением, «Добавить» — поиск через GET
/api/cms/relations/{target}?q=, названия выбранных через ?ids[]=, пометка невидимой цели, без прав
(403) — только список; регистрация типа в реестре полей; в WxCollectionField — выбор «только
связанные с» для источника, у которого relations() не пуст (форма значения — как в Selection
§3.6). Слова — английский пол в messages.ts под ключами webx-admin::relations.* (RC1 заводит их в
lang; сверить имена по спеке, расхождение — в итог). Плейграунд: мок /api/cms/relations/service и
/relations/recipe по §3.7, поле на экране услуги или отдельной фикстуре — чтобы было где нажать.
vitest на поле и на новый выбор в WxCollectionField; changeset на @webx-ui/module-admin (minor).
Гайд: раздел «Связи» в apps/docs/guide/collections.md или свой relations.md со ссылкой в сайдбаре.

Проверить в браузере на плейграунде (фоновый npx vite --port 5186 в apps/playground, preview_start
с url): выбрать, переставить клавиатурой на ручке, убрать, пометка невидимой, 375 px и тёмная
тема. В конце — «Итог RC2» в §6 спеки на своей ветке, коммит, пуш в claude.
```

#### Итог RC2 (24.09.2026)

Сделано на `feat/relations-panel`: `WxRelationsField` (`packages/module-admin/src/relations/`),
тип `wx-relations` в `adminTypes` (`wide: true`), выбор «только связанные с» в
`WxCollectionField`, мок `/api/cms/relations/{service,recipe}` (`apps/playground/server/panel/relations.ts`),
поле «Recipes» на `services.form` патчем проекта плейграунда, гайд `apps/docs/guide/relations.md`
(в сайдбаре после Collections), changeset minor на `@webx-ui/module-admin`. Проверено в браузере
на `/panel/services/1` и в блоке FAQ на `/panel/pages/17`: выбор, перестановка клавиатурой на ручке,
удаление, сохранение (`values.recipes` → `[3, 4]`), пометка невидимой, 375 px, тёмная тема.

**Что RC1/RC3/RC5 должны знать — всё это форма, которую написала панель, и сервер обязан ей
совпасть:**

- **`GET /api/cms/collections` отдаёт `relations` объектами, а не строками:**
  `relations: [{ key: 'service', title: 'Услуги' }]` — `title` на языке панели, как у самого
  источника (берётся из `label` цели в `RelationTargets`). Без названия панели нечем подписать
  «Только связанные с “Услуги”». `CollectionSource::relations(): list<string>` остаётся как в §3.6 —
  название дописывает контроллер. Панель на всякий случай понимает и голые строки (подпись = ключ),
  и отсутствие ключа (`[]`) — то есть старый сервер не ломает поле.
- **Значение `wx-collection` — пять ключей:** `related: { type, ids } | null`; `ids` сортируются
  и чистятся, как `categories`; `type` без `ids` пишется как `null` — у «не сужено» одно написание.
  `Selection::normalise()` должен делать ровно это.
- **Разметка по умолчанию выключена и при `related`:** `defaultMarkup()` — «нет категорий **и**
  нет `related`». Сузить по связи — это та же «часть коллекции», что и категория. `Selection` на
  сервере должен считать дефолт так же, иначе переключатель покажет не то, что напечатает сайт.
- **`related.current` не сделан** — ждёт ответа RC1 (знает ли рендер блока свою сущность).
- **403 = только список:** ни поиска, ни удаления, ни перетаскивания; строки — `#id`, потому что
  `ids[]` отвечает тем же 403. Если RC1 решит отдавать названия выбранного и без прав — поле
  покажет их без правки, но удалять всё равно не даст.
- **«Себя не предлагать» — на стороне панели:** `provideRelationOwner({ type, id })` из
  `module-admin`; экран рецепта (RC4) обязан его вызвать, иначе «Похожие рецепты» предложат сам
  рецепт. Сервер `id` владельца не знает, и знать ему незачем.
- **Пометка одна — «Not on the site»**, а не «снята»/«в корзине» из §3.5: в ответе §3.7 есть
  только `visible`. Хотим различать — нужен `state` в ответе, пока не стал.
- **Поиск в моке не отдаёт корзину**, а `ids[]` отдаёт (с `visible: false`) — так и серверу.
- **Слова — `webx-admin::relations.*`**, группа `relations` (в `messages.ts`): `field-add`,
  `field-searching`, `field-nothing`, `field-empty`, `field-remove`, `field-drag`, `field-hidden`,
  `field-missing`, `field-full` (`:max`), `field-forbidden`, `collection-related`,
  `collection-related-to` (`:target`), `collection-related-type`, `collection-related-any`.
  Слова фильтра в `wx-collection` положены сюда же, а не в `collections.*`: тест паритета
  `messages.test.ts` сверяет `collections` с `lang/en/collections.php`, а php здесь не трогали.
  **RC5:** слить с тем, что RC1 завёл в `lang/*/relations.php`, и добавить `'relations'` в список
  групп теста паритета. Отказы сервера (`unknown-target` и т. п.) — ключи RC1, панель их не
  знает и не должна.
- Мок называет цели связей у источника `faq` (`relations: [service]`) — заранее, чтобы фильтру
  было где стоять; предпросмотр `related` игнорирует. Когда появится настоящий источник рецептов —
  убрать у `faq`.
- Попутно: `WxCollectionField` рисовал приглушённый текст несуществующим `--wx-text-secondary`;
  заменено на `--wx-text-muted`.

### RC3 — `module-recipes`, php

```
Сессия RC3 из §6 docs/architecture/WEBX_UI_MODULE_RECIPES.md: composer-пакет webx-ui/module-recipes.
Идёт параллельно с RC4.

Worktree ../webx-ui-module-recipes, ветка feat/module-recipes. Первым делом: git fetch claude;
git merge claude/feat/relations-panel (конфликт возможен только в спеке — итоги RC1 и RC2 оба
нужны, и в lang/* module-admin — объединить ключи); сразу пуш — RC4 ответвляется от этого
состояния. Worktree ../webx-ui-relations-panel после этого удалить (git worktree remove), ветку
оставить до выпуска. PR не открывать.

Прочитать: §§2,4,5 спеки и итоги RC1, RC2; php/packages/module-services целиком — образец почти во
всём (адреса, черновик, версии, SEO, крошки, разметка, Cards/ServiceQuery/helpers, Views, Panel);
php/packages/module-faq/src/Collections/* и resources/blocks — предложенный блок;
php/packages/module-seo — HasStructuredData, SitemapRoutes, Seo::push(); CLAUDE.md §4 про
«Главная не получает адрес, пока у сайта есть свой маршрут» (Reserved) и про wx-rich-text
(data-wx-path, store()).

Сделать: php/packages/module-recipes — composer.json с extra.webx и autoload files, провайдер,
конфиг (prefix обязателен, index, per-page, similar, views), миграции §4, модели §5.1 (две связи
категорий, HasRelations), адреса и индекс §5.3 (index = false — маршрута нет), публичная часть и
вьюхи §§5.4–5.5 (части — @include, фрагмент каталога общий для индекса, категории и блока),
похожие §5.6 (один запрос с очками в SQL), SEO и разметка Recipe §5.7 (ингредиенты и шаги из <li>,
иначе абзацы), крошки через то, что стоит по пути {prefix}; RecipeQuery, Cards, recipes(),
RecipesSource с relations() = ['service'], тип блока §5.8 с mode showcase|catalog в BlockOffers за
class_exists; API §5.10 (формы ответов — ровно как там: по ним параллельно пишется панель, одна
транзакция на сохранение); экраны recipes.form, recipes.category-form, recipes.nutrient-form с
карточкой project-fields; права и модули панели §5.9; цель связей recipe (для похожих); все слова
webx-recipes::* на десять языков — и серверные, и нужные панели; строка recipes() в webx:doctor;
README, LICENSE; регистрации §5.13 кроме плейграунда, сайта и smoke; тесты §5.14 кроме MCP;
changeset на @webx-ui/php.

Не делать: npm (RC4), MCP и демо (RC5). Если форма ответа API всё-таки должна отличаться от §5.10
— поправить §5.10 тем же коммитом и сказать об этом в итоге крупно. В конце — «Итог RC3», коммит,
пуш в claude.
```

### RC4 — `module-recipes`, npm

```
Сессия RC4 из §6 docs/architecture/WEBX_UI_MODULE_RECIPES.md: npm-пакет @webx-ui/module-recipes.
Идёт параллельно с RC3, php не трогает.

Начало: git fetch claude; git worktree add ../webx-ui-recipes-panel -b feat/recipes-panel
claude/feat/module-recipes (после того, как RC3 влил RC2 и запушил — иначе wx-relations в
module-admin нет); pnpm install --frozen-lockfile; собрать dist у tokens, core, schema,
module-admin (тесты соседних пакетов видят module-admin из dist — CLAUDE.md §4). PR не открывать.

Прочитать: §§2,5.8–5.10 спеки и итоги RC1, RC2; packages/module-services целиком — образец (список
без пагинации, редактор с вкладками, автосейв, ревизия, история, предпросмотр); итог RC2 —
wx-relations; apps/playground/server/panel/services* — образец мока; CLAUDE.md §4 про context.can()
булево или computed, про вкладки в тестах (mousedown), про фильтры таблицы, про WxDate.

Сделать: packages/module-recipes (версия 0.0.0) — модуль панели §5.9: список (перетаскивание
только без фильтров, подсказка почему), редактор с вкладками Recipe · Settings · SEO · History,
категории и источники через categoryRoutes; i18n с английским полом и тестом паритета (ключи
заводит RC3 в php/packages/module-recipes/lang; чего не хватило — дописать туда же и сказать в
итоге); плейграунд: apps/playground/server/panel/recipes.ts по формам §5.10, модуль в main.ts,
тип блока recipes (оба mode) и страница с ним в фикстурах, предпросмотр страницы рецепта (свой
рендерер плейграунда — CLAUDE.md §4 про renderTemplate); vitest; changeset (minor).

Проверить в браузере на плейграунде (фоновый npx vite --port 5186, preview_start с url): создать
рецепт, заполнить все вкладки, галерея из трёх картинок и смена обложки перетаскиванием, услуги и
похожие через wx-relations, перетаскивание в списке без фильтра и подсказка с фильтром, блок в
обоих видах в предпросмотре, 375 px и тёмная тема. В конце — «Итог RC4» в §6 спеки на своей
ветке, коммит, пуш в claude.
```

#### Итог RC4 (24.09.2026)

Сделано на `feat/recipes-panel`: пакет `packages/module-recipes` (0.0.0, changeset minor) —
`recipes()` отдаёт три модуля (`recipes`, `recipe-categories`, `recipe-nutrients`), список,
редактор `recipes.form` с узлом `wx-recipe-history`, диалог создания, категории и источники через
`categoryRoutes` (`recipeCategoriesOptions()`, `recipeNutrientsOptions()`); 22 теста, включая
паритет слов. Плейграунд: мок `/api/cms/recipes*` (`apps/playground/server/panel/recipes.ts`),
цель связей `recipe` на настоящей фикстуре, источник коллекции `recipes` с `relations: [service]`,
тип блока `recipes` в обоих видах, страница «Рецепты — витрина» (`/panel/pages/19`), предпросмотр
`/preview/recipe/{id}`, папка «Рецепты» в медиатеке, поле проекта «Author's note». Проверено в
браузере: создание, галерея из трёх и смена обложки перестановкой, ингредиенты списком, пищевая
ценность, время и порции, категории и источники, услуга и похожий рецепт (себя пикер не предлагает,
неопубликованный — с пометкой), SEO, публикация и история, перетаскивание списка клавиатурой без
фильтра и подсказка с фильтром, блок-витрина с фильтром по услуге и каталог с пагинацией в
предпросмотре, 375 px (горизонтального скролла нет ни на одной вкладке), светлая и тёмная тема.

**С RC3 согласовано по ходу (сообщениями), панель написана под это:**

- Статусы — как у услуг: `draft | published | modified | unpublished` (RC3 правит §5.10).
- Форма — `{ recipe, values, revision, prefix, preview_url }`; `recipe` — строка списка плюс
  `path`, `published_at`, `revision`. Строка списка несёт и `path`, и `published_at`. `POST` —
  `{ data: { recipe, values } }`, панель берёт `recipe`.
- Поиск — `q=` (сервер понимает и `search=`). Фильтры — `category`, `nutrient`, `service`,
  `status`, `trashed=1`; `filters.services: null` без `module-services` — фильтра услуги нет.
- Галерея — тип `wx-gallery` (у `module-media` это и есть «список `wx-media`»), имя `gallery`.
  Пищевая ценность — пять полей с **буквальными** именами `nutrition.calories` … `nutrition.fiber`,
  в `values` лежат под этими ключами, не картой. Вкладки `recipe · settings · seo · history`,
  карточка `project-fields` на `settings`. Счётчик у обоих видов категорий — `recipes_count`.
- Слова `panel`, `recipe`, `category`, `nutrient` и `module` — английский пол в
  `php/packages/module-recipes/lang/en/*.php` сгенерирован из `messages.ts` на этой ветке; RC3
  берёт эти файлы байт в байт и переводит на остальные девять языков, поэтому при слиянии en —
  одинаковое добавление. Группы `screen`, `errors`, `relations`, `site` — RC3. Иконки в
  манифесте: группа `heart`, рецепты `file-text`, категории `folder`, источники `tag`.

**RC5 при слиянии:**

- Плейграунд читает экраны `recipes.form`, `recipes.category-form`, `recipes.nutrient-form` из
  `php/packages/module-recipes/resources/screens/` и падает на копии в
  `apps/playground/server/panel/recipes/*.json`, пока файлов RC3 нет; SEO — патч
  `module-seo/…/recipes.form.json`, иначе патч услуг (он заменяет тот же `seo-placeholder`). После
  слияния копии удалить и `Source[]` в `screens.ts` свести к строкам — и проверить, что у RC3 те
  же имена полей.
- Тип блока `recipes` в плейграунде — **всегда заглушка** `RECIPES_BLOCK` из `recipes-site.ts`, а
  не `resources/blocks/recipes.json`: шаблон RC3 — это `@include('webx-recipes::partials.catalog',
…)`, общий фрагмент индекса, категории и блока (§5.8), а маленький Blade плейграунда не умеет ни
  `@include`, ни пагинатор. Заглушка повторяет то, что RC3 назвал: схема `title`, `recipes`,
  `mode`, `per_page`, `columns` и его `sample`; внутри корня `.b-recipes[data-wx-block]` — разметка
  фрагмента (`.wx-recipes` > `__filter` с `__chip` и `aria-current`, `__grid` > `__card` >
  `__link` с `__cover` и `__name`, `__time`, `nav` > `__pages`, `__more` в витрине). Линты
  конструктора на ней чистые. Поменяется фрагмент у RC3 — поправить заглушку, а не Blade.
- `relations` у источника `faq` в моке убран (итог RC2 просил это сделать, когда появится
  настоящий источник рецептов); поле «Recipes» на `services.form` (патч проекта из RC2) оставлено.
- Мелочь, найденная глазами: пустой список у поля «Rich in» говорит общим словом поля категорий
  «In no category yet» — у `wx-categories` нет пропа для своего слова.
- В панели браузера скриншот после клика часто на шаг отстаёт от DOM (папка медиатеки «показывала»
  файлы предыдущей) — сначала `javascript_tool`/`get_page_text`, потом верить картинке.

### RC5 — слияние, MCP, демо, доки

```
Сессия RC5 из §6 docs/architecture/WEBX_UI_MODULE_RECIPES.md: MCP, демо, гайд.

Worktree ../webx-ui-module-recipes, ветка feat/module-recipes. Первым делом: git fetch claude;
git merge claude/feat/recipes-panel (конфликты — спека и lang/*: объединить); worktree
../webx-ui-recipes-panel удалить, ветку оставить до выпуска.

Прочитать: §§5.11,5.12 спеки и итоги RC1–RC4; php/packages/module-services/src/{Mcp,Demo}/* и
php/packages/module-reviews/src/{Mcp,Demo}/* с resources/demo; apps/docs/guide/services.md и
reviews.md; CLAUDE.md §4 про mcp:start (только трубой), про возврат webx-cms.local после
local-режима из копий и про новый пакет в local-режиме (require --no-update, потом update).

Сделать: RecipesTools и recipes://catalog §5.11 (создание в транзакции; в описании инструментов —
ингредиенты и способ HTML-списком), связи services и related в recipes_update (услуга — id или
адресом); RecipesDemo §5.12 с динамическим requires(); apps/docs/guide/recipes.md — адреса и
приставка, выключенный индекс и страница с блоком-каталогом на его месте, два вида блока и общий
фрагмент каталога, что публиковать ради своей вёрстки, похожие рецепты, разметка Recipe и как её
проверить, связи с услугами, патч с полем проекта (комментарий автора), два каталога на одной
странице делят ?page=; раздел про связи в гайде module-admin, если RC2 его не сделал; ссылки в
сайдбаре; README npm-пакета, разделы MCP и демо в README composer-пакета; recipes в автокомплите
шаблона блоков (ключи карточки); тесты MCP и демо; changeset. Гейт php-половины и vitest/vue-tsc
npm.

Проверить живьём: инструменты через cat … | php artisan mcp:start webx на webx-cms.local в
local-режиме на этом worktree (до переключения — копии composer.json, composer.lock, package.json,
package-lock.json, database/database.sqlite в скретчпад; после — назад и composer install);
страница рецепта, индекс и категория curl'ом — разметка Recipe в ответе. Ничего на сайте не
коммитить — это RC6. В конце — «Итог RC5», коммит, пуш в claude.
```

### RC6 — выпуск

```
Сессия RC6 из §6 docs/architecture/WEBX_UI_MODULE_RECIPES.md: выпуск module-recipes и связей,
оба демо.

Прочитать: итоги RC1–RC5; CLAUDE.md §5 целиком — особенно «gh pr merge в очередь не ставит»
(ставит graphql-мутация enqueuePullRequest), «Первую версию нового npm-пакета публикует человек»,
«Ручная публикация замораживает диапазоны», «Тег php-пакетов ставится до публикации», «Composer
после релиза может минут десять не видеть новую версию»; итог R4 в
docs/architecture/WEBX_UI_MODULE_REVIEWS.md — тот же выпуск; память webx-cms-local-demo-site,
webx-cms-homelab-deploy, release-speed.

До релиза (руками пользователя, сессия напоминает и проверяет): репозиторий-зеркало
webx-ui/module-recipes на GitHub.

Сделать: погасить dev-серверы; полный гейт npm и php/ (PHP 8.4); module-recipes в
scripts/php-smoke.sh рядом с module-reviews и smoke против MariaDB (миграции webx_relations и
recipes на настоящей СУБД — длины дефолтов, CLAUDE.md §4); PR, зелёный CI, в очередь мутацией;
релизный PR — снять changeset-release/main в отдельный worktree, pnpm install, dist, pnpm pack
@webx-ui/module-recipes и проверить диапазоны @webx-ui/* в тарболе; первая публикация —
пользователь из своего терминала с 2FA, затем Trusted Publishing (webx-ui / webx-ui / release.yml);
мерж релизного PR; npm view всех поднятых пакетов и тег php-v<версия>; webx-ui/module-recipes на
Packagist (пользователь). Удалить ветки feat/relations-panel и feat/recipes-panel.

Демо: webx-cms.local — module-recipes в scripts/packages.mjs, link-panel.sh, composer require в два
шага, импорт и ...recipes() в resources/js/admin.ts руками, migrate,
webx:blocks:offered --install --module=recipes, cache:clear, демо §5.12 тинкером со своим
журналом, npx vite build; хомлаб — то же в registry, npm ls @webx-ui/module-admin — одна версия,
коммит и пуш в Gitea. omnivitality-v2.local — только если пользователь скажет (там он ведёт шаги
сам). Строку реестра в WEBX_UI_COMPOSER_PACKAGES.md (из «Запланированы» в «Модули») и CLAUDE.md
§§2,6 — отдельным docs-PR.

Проверить живьём на обоих: индекс с пагинацией и фильтром по источнику; выключенный индекс и
страница module-pages по пути /{prefix} с блоком-каталогом, она же в крошках рецепта; страница
рецепта со всеми частями, похожие руками и подбором, услуги; витрина в демо-услуге с фильтром по
этой услуге; Recipe на validator.schema.org и в Rich Results Test; телефон — пользователь.
```

## 7. Отложено

- **Импорт со старого `omnivitality`** — скрипт в том сайте, а не в модуле: `ingredients` и
  `method` переносятся как есть, `nutrients` → `nutrition`, строковая `category` → категории,
  `recipe_service` → связи, `recipe_related` → роль `related`, `katia_comment` и CTA → `extra`.
- Порядок внутри категории и внутри услуги (решение 4) — если понадобится, `item_position` уже в
  таблице категорий, а у связей есть `position`.
- Пагинация списка в панели, если сотни рецептов без неё окажутся тяжёлыми.
- Отзывы, FAQ, команда → услуги тем же механизмом.
- Связи в истории версий: снимок публикации — колонки, связей в нём нет, поэтому откат к старой
  версии связи не возвращает (итог RC1).

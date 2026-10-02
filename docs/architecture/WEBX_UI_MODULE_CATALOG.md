# `webx-ui/module-catalog` — спецификация ядра каталога

Выпущено в v0.50.0 (npm `@webx-ui/module-catalog`); спека — справочник того, что есть.
Архитектура семейства, контракты и решения 1–26 — [`WEBX_UI_CATALOG.md`](WEBX_UI_CATALOG.md);
здесь они не повторяются, только уточняются. Журнал изменений, на который ядро опирается, —
[`WEBX_UI_HISTORY.md`](WEBX_UI_HISTORY.md).

## 1. Границы

**Ядро — это:** товары, категории деревом, цена, единица измерения, галерея товара, три
состояния товара и раздел «Удалённые», реестры для спутников (§7), движок каталога с `SqlEngine`
и очередью индексации (§8), популярность и сортировки (§9), витрина в шаблонах (§10), массовые
действия (§11.4), обмен.

**Не ядро:** свойства, наличие, бренды, метки, связи, посадочные, Manticore — спутники (§3 и §10
архитектуры). Обмен (конвейер, CSV и XLSX) живёт в ядре, но описан отдельно —
[`WEBX_UI_MODULE_CATALOG_EXCHANGE.md`](WEBX_UI_MODULE_CATALOG_EXCHANGE.md); здесь — только
колонки ядра (§7.6). Видео в галерее — [`WEBX_UI_CATALOG_VIDEO.md`](WEBX_UI_CATALOG_VIDEO.md).

Пакеты: composer `webx-ui/module-catalog` (namespace `WebxUi\Catalog`) и npm
`@webx-ui/module-catalog`. Раздел панели один — `catalog` (§19); категории (`catalog-categories`)
своих прав и инструментов не имеют. Группа меню «Каталог», в которую встают спутники. Вкладка
«Фильтры» категории включается флагом `webx-catalog.fields.facets` и по умолчанию выключена: без
свойств расставлять нечего.

## 2. Принятые решения (не переоткрывать)

1. **Все поля с текстом переводимые** — JSON-колонки через `HasTranslations`. Не переводятся
   артикул, штрихкод, цена, единица, числа и флаги.
2. **У товара одна основная категория и сколько угодно дополнительных.** Основная — колонкой, от
   неё крошки и 301 после удаления; дополнительные — связью, основной в ней нет.
3. **Товар может жить без категории,** но опубликовать его без основной нельзя. В списке панели —
   фильтр «Категория не задана» и счётчик таких товаров.
4. **Три состояния товара** — опубликован, снят, удалён (§5). Удаление — soft delete навсегда:
   на товар будут ссылаться заказы и личный кабинет. Удалённые живут в своём разделе, как корзина.
5. **Артикул уникален с учётом удалённых** и может отсутствовать. Ошибка называет занявший его
   товар и ведёт на него.
6. **Штрихкод — опциональное поле рядом с артикулом**, выключается конфигом; не уникален.
7. **Единица измерения — в ядре,** строкой из справочника в конфиге.
8. **Ручной `priority` и автоматическая популярность живут вместе** (§9). Ручного порядка товаров
   внутри категории нет.
9. **Категории — дерево с плоскими слагами** (решение 23 архитектуры), soft delete, удалить можно
   только пустую. Снятая категория прячет своё поддерево (§6.3).
10. **Настройка фасетов у категории — по желанию.** Не настроена — берётся у ближайшего
    настроенного предка, нет такого — все фасеты в порядке реестра.
11. **Одно поле текста у категории.** Тексты посадочных — забота `module-catalog-landings` и
    `module-seo`.
12. **Витрина — только шаблоны**, переопределяемые сайтом. Ни блоков, ни правки карточки товара
    из панели.
13. **`SqlEngine` — для каталогов до пары тысяч товаров.** Дальше — Manticore, и `webx:doctor`
    об этом говорит.
14. **Без черновиков и версий,** но с журналом изменений: кто, когда, откуда, какое поле с чего на
    что.
15. **Массовые действия — очередью с прогрессом,** включая «всё, что подходит под фильтр».

## 3. Схема

Индексы — на всём, по чему ищут и соединяют, с первой миграции.

### `catalog_products`

| Колонка                                  | Тип                           | Примечание                                     |
| ---------------------------------------- | ----------------------------- | ---------------------------------------------- |
| `id`                                     | bigint                        | ещё и суффикс адреса                           |
| `sku`                                    | string(64), null, **unique**  | уникальность включает удалённые                |
| `barcode`                                | string(64), null, index       | колонка есть всегда, конфиг прячет             |
| `external_id`                            | string(64), null, unique      | ключ учётной системы (GUID 1С) для интегратора |
| `name`                                   | json                          | переводимое                                    |
| `slug`                                   | json                          | переводимое; из названия, не уникально (§4)    |
| `summary`                                | json, null                    | короткий текст без разметки: сетка, фиды, meta |
| `description`                            | json, null                    | rich-text                                      |
| `category_id`                            | FK `catalog_categories`, null | основная; `restrictOnDelete` — категории soft  |
| `price`, `old_price`                     | decimal(12,2), null           | колонки есть всегда, конфиг прячет             |
| `unit`                                   | string(16), null              | ключ из `webx-catalog.units`                   |
| `priority`                               | int, default 0, index         | ручной вес для сортировки по умолчанию         |
| `is_published`                           | bool, index                   |                                                |
| `deleted_at`, `created_at`, `updated_at` |                               | index на `deleted_at` и `created_at`           |

SEO — `HasSeo` из `module-seo` (своя таблица), как у всех сущностей.

### Остальные таблицы

- `catalog_category_product` — `category_id`, `product_id`, первичный ключ по паре, index на
  `product_id`. Только дополнительные категории.
- `catalog_product_images` — `id`, `product_id` (index), `path`, `alt` и `title` (json,
  переводимые), `width`, `height`, `size`, `position`, плюс колонки ролика (спека видео). Главная
  картинка — первая по `position`. Файлы — §10.4.
- `catalog_categories` — `id`, `nestedSet()` (`parent_id`, `lft`, `rgt`, `depth`); `name`
  (json); `slug` (json, плоский, уникален на сайте на своём языке); `description` (json, null,
  rich-text, одно поле); `cover_id` (FK `media_files`, null — плитка категории из библиотеки);
  `is_published`; `deleted_at`, timestamps. `IsCategory` из `module-admin` не подходит: он для
  плоских списков и ставит `max(position) + 1`.
- `catalog_category_facets` — `category_id`, `facet_key`, `is_visible`, `position`; уникальный
  ключ по паре. Нет строк — настройка унаследована (§6.2).
- `catalog_filter_aliases` — старые написания кодов и слагов фильтра (§7.1).
- `catalog_index_queue` — `product_id` (первичный ключ), `queued_at`. Пишется только когда движку
  нужен индекс (§8.3).
- `catalog_product_popularity` — `product_id` (первичный ключ), `views` (decimal, с затуханием),
  `score` (decimal, index), `computed_at`. Своя таблица, чтобы ночной пересчёт не переписывал
  `catalog_products` целиком (§9).
- `catalog_bulk_runs` — `id`, `admin_id`, `admin_name`, `action`, `params` (json), `total`,
  `done`, `failed`, `errors` (json, первые сто), `status`, timestamps; `catalog_bulk_run_items` —
  id товаров прогона (§11.4).

## 4. Адреса

Решения 23–26 архитектуры, в терминах `routing`:

| Что             | Адрес                       | Строка реестра                                              |
| --------------- | --------------------------- | ----------------------------------------------------------- |
| категория       | `/{slug}`                   | тип `catalog.category`, `acceptsTail: true` — хвост фильтра |
| товар           | `/{slug}-{id}`              | тип `catalog.product`, `acceptsTail: false`                 |
| корень каталога | `/{catalog.root}`           | маршрут Laravel, только если включён; хвост фильтра тоже    |
| поиск           | `/{catalog.root}/search?q=` | маршрут Laravel, не реестр; всегда `noindex`                |

- **Корень главнее страницы с тем же адресом.** Он маршрут, а маршрут отвечает раньше
  `Route::fallback()` реестра. Новую страницу `catalog` (и всё под ней) `Reserved` не сохранит;
  страницу, которая была до включения корня, корень молча прячет — её называют `webx:doctor`
  (`RootCheck`) и `webx:routes:check`. Выход — другой адрес странице или
  `WEBX_CATALOG_ROOT_PREFIX`. Строкой реестра корень не стал: сущности у него нет, а спор за адрес
  при сохранении `Reserved` и так решает.
- **Слаг товара не уникален:** `-{id}` делает адрес уникальным. Пустой слаг модель берёт из
  названия. Любое другое написание — 301 на каноническое, в том числе после смены слага. Это
  делает не обработчик (до него доходит только точное совпадение из реестра), а `CatalogMisses` —
  обработчик промахов `routing` (`Misses`, §8 `WEBX_UI_ROUTING.md`). Совпадение со страницей
  `name-12` — редкость, тип товара на нём берёт суффикс (`OnConflict::Suffix`), а не 422: импорт
  не должен вставать.
- **Слаг категории** проверяет `routing` при сохранении — занятый адрес даёт 422 с тем, кто его
  держит; `_` в слаге — 422 формы (им отмечен фильтр). Пустой слаг из названия подставляет форма
  (`CategoryForm`), а не модель: код в обход формы (демо, сиды) заполняет его сам — иначе у
  категории нет адреса.
- Хвост категории разбирает сериализатор фильтра (§7.7): сегмент с `_` — фильтр, остальное — 404.
  Фасет, скрытый в категории, в её адресе — тоже 404. Языковая приставка — как у всех сущностей.

## 5. Состояния товара

| Состояние     | Каталог, поиск, фасеты | Прямая ссылка                                                                                      | Купить              |
| ------------- | ---------------------- | -------------------------------------------------------------------------------------------------- | ------------------- |
| опубликован\* | да                     | полная страница                                                                                    | по `Purchasability` |
| снят          | нет                    | урезанная страница, 200, `noindex`                                                                 | нет                 |
| удалён        | нет                    | 301 на основную категорию, если она видна, иначе на первую видимую дополнительную; нет такой — 410 | нет                 |

\* И виден: хотя бы одна его категория опубликована вместе со всеми предками (§6.3).
Опубликованный, но невидимый товар ведёт себя как снятый.

**Урезанная страница** — название, картинка, артикул, плашка «снят с продажи» и точка
`@webxPart('catalog.product.unavailable')` (для замен из будущего `module-catalog-links`).

**Удалённый** товар уходит из реестра `routing` как канон и отвечает 301 обработчиком ядра;
восстановленный возвращает свой адрес. Удалить навсегда из панели нельзя: ссылки из заказов
должны оставаться живыми; когда можно (товар не был ни в одном заказе) — решит commerce.

## 6. Категории

### 6.1. Дерево

`nested-set`, перенос перетаскиванием, адреса при переносе не меняются. В дереве панели —
счётчик товаров (основная и дополнительные, с потомками) одним запросом по `lft/rgt` и адрес на
сайте без слэша в конце, как его отдаёт сайт.

### 6.2. Фасеты категории

Вкладка «Фильтры» формы категории: переключатель «Своя настройка». Выключен — показано, откуда
унаследовано («как у Ноутбуков» или «все фасеты по умолчанию»). Включён — список фасетов реестра
с видимостью и порядком перетаскиванием; хранится строками `catalog_category_facets`. Фасет,
зарегистрированный позже настройки, в настроенной категории скрыт до явного включения — иначе
новый модуль молча меняет чужие фильтры. Разрешение — ближайший предок со строками, одним
запросом по предкам, с кешем на категорию, который сбрасывает сохранение любой настройки.

### 6.3. Снятие и удаление

- **Снятая** категория отвечает 404 и прячет своё поддерево. Товар с другой видимой категорией
  остаётся виден через неё и в поиске. Снятие и публикация помечают затронутые товары одним
  запросом (`Catalog::touchCategory()`).
- **Удалить** можно категорию без живых товаров (основных и дополнительных) и без подкатегорий;
  иначе 422 с числом. Удалённая уходит в «Удалённые» (вкладка «Категории»), её адрес — 410.
  Восстановление возвращает адрес; товар, чья основная категория удалена, восстанавливается без
  категории и снятым.

## 7. Реестры ядра

### 7.1. `Facets`

Контракт `Facet`: `key()`; `code(string $locale)` — код в адресе на языке страницы,
`[a-z0-9-]`, уникален среди фасетов этого языка (проверка при регистрации; `AbstractFacet` берёт
его из `webx-catalog.facet_codes`, иначе `baseCode()` — §4.1 `WEBX_UI_CATALOG_PROPERTIES.md`);
`kind()` — `Terms | Range | Toggle | Tree`; `label()`; `indexable()` — может ли первый уровень
быть открыт; `field(): IndexField`; `labels($values, $locale)`, `slugs($values, $locale)`,
`resolveSlugs($slugs, $locale)` — пачкой; `normalise(FacetValue)` — одно написание выбора (у
дерева предок поглощает потомков); `applySql()`; `sqlValues(QueryBuilder $products)` — пары
`(product_id, value)` для `SqlEngine`, дерево отдаёт значение со всеми предками. У `Tree` ещё
`TreeFacet::parents($values)` — чтобы фильтр нарисовал дерево.

Необязательные: `ContextualIndexing::indexableIn(FilterContext)` — открыт ли первый уровень на
этой странице (свойства — только в категории); `TitledFacet::filterTitle($where, $label,
$locale)` — свой заголовок первого уровня вместо «{категория} {значение}»;
`SwatchedFacet::swatches($values)` — цвет или картинка значения в фильтре (§8.2
`WEBX_UI_CATALOG_PROPERTIES.md`); `OrderedFacet` — термы в порядке, в котором ответил `labels()`
(метки и наличие — по `position` справочника), без маркера фильтр сортирует их по алфавиту.

Значения — строки (id или код), слаги живут только в адресе. `AbstractFacet` даёт умолчания:
индексируемый — у `Terms` и `Tree`, значение — сам себе слаг и подпись. Фасеты из базы
(свойства) не регистрируются, а приходят источником — `Facets::source(FacetSource)`; какие из
них уместны на странице, решает источник (`RelevantFacets`), невыбранные считаются пачкой
(`BatchCountedFacet`). Старые написания — `FilterAliases` (`catalog_filter_aliases`), §4 и §5.2
`WEBX_UI_CATALOG_PROPERTIES.md`.

Ядро регистрирует `category` (Tree) и `price` (Range, если цена включена). Панель использует те
же фасеты в фильтре списка, плюс свои фильтры, которых на сайте нет: «опубликован», «категория
не задана».

### 7.2. `Sorts`

`key()`, `label()`, `applySql(Builder)`, `indexField()` + направление. Ядро: `default` (ступени
из конфига, §9), `price_asc`, `price_desc`, `name`, `new`, `popular`. Список на витрине и его
порядок — конфиг; владелец страницы может открыть её в своём порядке (`HasDefaultSort`).
Сортировка — единственный GET-параметр каталога (`?sort=`); `?sort=` и `?page=` — `noindex` с
каноникалом на первую страницу без них, страница за последней — 404.

### 7.3. `DocumentContributor`

§4.2 архитектуры. Ядро (`CoreDocument`) кладёт: `id`, опубликован, удалён, виден (§5),
категории с предками, основную категорию, цену, `priority`, `score`, `created_at`, название и
артикул/штрихкод для поиска — по полю на язык. Слова спутника `SqlEngine` находит через
`SearchContributor`.

### 7.4. `ProductParts` и `ProductColumns`

- `ProductParts` — реестр `ProductPart` (§11 архитектуры): `key()` (`[a-z0-9-]`, он же
  приставка полей на экране), `describe(): PartSchema` (для MCP, §12.2: `PartField` — имя, тип,
  подпись, правила, допустимые значения, `source` справочника или ссылка на инструмент),
  `rules()` (ключи без приставки), `read(Eloquent\Collection $products): array<id, array<поле,
значение>>`, `write(Product, array $input): list<{ field, from, to }>`. Изменения идут в журнал
  одной записью с изменениями ядра; `rules()` всех частей проверяются до транзакции.
- `ProductColumns` — колонка списка панели: ключ, подпись, `values(Collection $products)`
  пачкой, сортируемая ли (тогда через `Sorts`). Колонки из базы (свойства) — источником
  `ColumnSource`, спрашивается на каждом чтении.

Вкладки и поля спутника в форме — патчем экрана `catalog.product-form`; `ProductParts` — только
чтение и запись.

### 7.5. `Purchasability`

§4.4 архитектуры. Ответ — `yes` или `no` с кодом причины и подписью; кнопку («Купить» или
«Узнать цену» формой `module-inbox`) рисует шаблон. «Снят с продажи» (товар не виден, §5) ядро
ставит первым, «цена по запросу» — `register($rule, last: true)`, после всех спутников, когда бы
они ни зарегистрировались.

### 7.6. `ExchangeColumns`

Контракт — в спеке обмена. Колонки ядра: `id`, `sku`, `barcode`\*, `external_id`, `name`,
`slug`, `summary`, `description`, `category` (путь или `#id`), `categories`, `price`\*,
`old_price`\*, `unit`, `priority`, `is_published`, `images` (адреса, загрузка по адресу).
\* — не объявляются, если выключены.

### 7.7. `FilterUrls` — адреса фильтра и переписчики

Сериализатор §8.1 архитектуры за интерфейсом: `parse(string $tail): FilterState` и
`build(FilterContext, FilterState): string` — последний **пачкой** (`buildMany`), потому что
фильтр рисует сотни ссылок. Реестр `FilterUrlRewriter`:

```php
interface FilterUrlRewriter
{
    /** Called once per page render with every link the filter is about to show. */
    public function prepare(FilterContext $context, array $states): void;

    public function rewrite(FilterContext $context, FilterState $state): ?RewrittenUrl;
}
```

`RewrittenUrl` — базовый путь и остаток состояния, который дописывается сегментами (решение 26
архитектуры); `$indexable` — переписчик открывает состояние, которое забирает целиком. У ядра
переписчиков нет; первый — `module-catalog-landings`. `Storefront::listing()` принимает базовое
состояние, к которому добавляет хвост: «ничего не выбрано» значит «ничего поверх него».

«Один раз на рендер» держится так: адрес самой страницы строится в той же пачке, что и ссылки её
фильтра, и обработчик сравнивает с ним запрошенный — другое написание, выбранная подкатегория,
набор, забранный посадочной, дают 301 без второго `prepare()`. Одна подкатегория на странице
категории — переход (§8.3 архитектуры), и его делает сериализатор: `/noutbuki/category_igrovye`
пишется как `/igrovye`. Диапазон без скрипта — форма `?range[price][from]=…&range[price][to]=…`,
отвечающая 302 на адрес с сегментом.

### 7.8. `PopularitySignals`

`key()`, `values(array $productIds): array` пачкой. Ядро: `views`. Веса — конфиг (§9).

## 8. Движок

### 8.1. Запрос и ответ

`CatalogQuery`: контекст (`category`, `root`, `search`, `panel`, `brand` и прочие — строкой и id,
ядро про бренды не знает); `scope` — где стоит страница (категория страницы, бренд страницы
бренда), сужает и список, и все счётчики; `facets` — что выбрал читатель, свой фасет не сужает;
`count` — какие фасеты считать; текст поиска, сортировка, страница, размер страницы, язык,
`withUnpublished` (панель), `onlyTrashed` («Удалённые»), `state` — фильтры панели
(`published|unpublished|no-category`).

`CatalogResult`: `ids` страницы в порядке движка, `total`, `facets` — по видимым фасетам:
значения со счётчиками (`Terms`), min/max (`Range`), число (`Toggle`), счётчики с потомками
(`Tree`). Витрина поднимает товары одним `whereIn` и сохраняет порядок `ids`.

### 8.2. `SqlEngine`

1. Базовый запрос по `catalog_products` с контекстом; категория — через `lft/rgt` по основной и
   дополнительным (`exists`), так что родитель включает всё поддерево.
2. Каждый выбранный фасет — `Facet::applySql()`.
3. Страница — `select id … order by … limit`; итог — `count(*)`.
4. Счётчики — `group by` на видимый фасет, со всеми фильтрами, кроме его собственного. N фасетов
   — N + 2 запроса (фасеты источника — пачкой).
5. Текст — `like` по названию на языке запроса, артикулу и штрихкоду.

`index()` и `remove()` пустые, `needsIndex()` — `false`: база и есть индекс. Порог —
`sql_engine_limit` (2000 живых товаров), считает `Catalog::outgrown()`: выше — предупреждение в
`webx:doctor` и над списком товаров в панели (поле `outgrown` ответа списка, рядом с «индекс не
отвечает»). Над списком, а не на дашборде: дашборда у панели нет, корень `/cms` уводит в раздел
(`AdminLanding`).

### 8.3. Очередь индексации

- `Catalog::touch(array $ids)` — `insert ignore` в `catalog_index_queue`, если
  `engine()->needsIndex()`; иначе ничего. `touchQuery(Builder)` — `insert … select` одним
  запросом: для справочников и категорий (§6.3).
- Помечает сама модель (`saved`, `deleted`, `restored` у `Product`): любая дверь в таблицу —
  форма, импорт, спутник — помечает товар, никто не обязан помнить.
- `webx:catalog:index` раз в минуту: пачки по 500, документ у всех вкладчиков одним проходом.
  Пачка вынимается из очереди **до** обращения к движку и возвращается, если движок отказал: при
  обратном порядке правка, сделанная, пока пачка строилась, терялась бы — её `insert ignore`
  ложился на строку, которую сейчас удалят. Повтор безвреден. После пачки — событие
  `ProductsIndexed`. `--rebuild` — полная перестройка со схемой. В индекс идут и удалённые
  (`is_deleted`) — «Удалённые» ищут тем же движком; товара, которого нет в таблице совсем,
  движок лишается (`remove`).
- Упавший движок не роняет сохранение: очередь копится, `webx:doctor` показывает её длину и
  возраст первой записи.

## 9. Популярность и сортировка по умолчанию

- **Просмотры.** Страница товара (не бот по простому списку user-agent) — инкремент в кеше.
  `webx:catalog:flush-views` раз в пять минут сбрасывает накопленное в
  `catalog_product_popularity.views` двумя запросами на пачку — `insert ignore` недостающих строк
  и `update … case` — вместо `on duplicate key update`, который MySQL и sqlite пишут по-разному.
  Кеш — одна запись под блокировкой; не дождался блокировки — просмотр не посчитан, страница не
  ждёт. Включается `popularity.views`.
- **Затухание.** Ночью `webx:catalog:popularity`: `views = views × decay`, затем `score = Σ вес ×
сигнал` пачками по всем `PopularitySignals`. Товары, чей `score` сдвинулся больше
  `touch_threshold`, — в `touch()`.
- **Своя формула** — класс `PopularityFormula` в контейнере, проект подменяет.
- **Сортировка по умолчанию** — ступени из конфига (`priority desc, score desc, created_at
desc`). «Популярные» — только `score`, без `priority`.

## 10. Витрина

### 10.1. Шаблоны

Blade в `resources/views/vendor/webx-catalog/…` у сайта переопределяет любой шаблон пакета.
Шаблоны ядра: `category`, `root`, `search`, `product`, `product-unavailable`, `filter` (и по
партиалу на вид фасета, `filter.swatch`), `grid`, `card`, `sort`, `pagination`, `breadcrumbs`.

Точки для спутников (`@webxPart`): `catalog.card.badges`, `catalog.card.meta`,
`catalog.product.aside`, `catalog.product.tabs`, `catalog.product.unavailable`; на странице
списка — `catalog.listing.top` над сеткой и `catalog.listing.bottom` под пагинацией, обе получают
`CatalogPage` как `page`. Тексты над списком и под пагинацией шаблон берёт у владельца страницы
(`HasListingTexts`), а не у категории: у категории — описание сверху.

`@webxPart` — один компонент на точку, который сайт переопределяет целиком; писать в точку
нескольким спутникам позволяет реестр `StorefrontParts`: спутник регистрирует `StorefrontPart`
(точка, вьюха, `prepare(Collection $products)` — один запрос на страницу), а запасной партиал
точки (`webx-catalog::points.<точка>`) печатает все части по порядку.

Страница сущности спутника, которая есть выдача каталога (бренд), собирается тем же путём, что
категория: `Storefront::listing()` со своим контекстом, scope и вьюхой, а
`FilterContext::$subject` (`ListingSubject`) называет страницу, её крошки и SEO вместо категории
([`WEBX_UI_CATALOG_DICTIONARIES.md`](WEBX_UI_CATALOG_DICTIONARIES.md) §12).

### 10.2. Фильтр

Ссылки, а не форма: у каждого значения — готовый адрес из `FilterUrls::buildMany()`, с
`rel="nofollow"` там, где адрес закрыт. Работает без JS. Пустые значения (счётчик 0) — серые без
ссылки. Скрипта в ядре нет: длинный список и «Ещё фильтры» сворачивает `<details>`, цену
принимает форма (§7.7), ползунок — забота сайта.

### 10.3. SEO

- Категория, товар, корень — `HasSeo` и шаблоны `module-seo`.
- Первый уровень фильтра — шаблон «{категория} {значение}», закрытые комбинации —
  `noindex, follow`, `?sort=` и `?page=` — как в §7.2.
- Разметка: `Product` + `Offer` (если цена включена, указана и задана валюта
  `webx-catalog.price.currency`), `BreadcrumbList`, `ItemList` на категории.
- Карта сайта — видимые товары, категории, непустые первые уровни; товары — пачками, `lastmod`
  по `updated_at`. Товары и категории — строки реестра, их карта берёт сама; первые уровни —
  файл `catalog-filters` через `SitemapSources` из `module-seo` (адрес, забранный посадочной,
  пропускается — у посадочной своя строка).
- SEO страницы списка говорит `ListingSource` (60): на чистой категории — её карточка, на первом
  уровне — шаблон, на остальном — `noindex, follow`; правило для адреса (100) бьёт всё.

### 10.4. Галерея товара

Загрузка прямо на диск `webx-catalog.images.disk` (по умолчанию `public`) в
`catalog/{id div 1000}/{id}/{hash}.{ext}` — папки не растут до сотен тысяч файлов. При импорте —
загрузка по адресу в очереди. Превью — `Thumbnails::variantOf()` из `module-media` от `(disk,
path)`, а не от `MediaFile`. Удалённая картинка удаляет файлы сразу; удалённый товар файлы
сохраняет. К картинке прикрепляется ролик — свой файл или YouTube
([`WEBX_UI_CATALOG_VIDEO.md`](WEBX_UI_CATALOG_VIDEO.md)).

## 11. Панель

### 11.1. Экраны

Все — экраны-описания ([`WEBX_UI_SCREENS.md`](WEBX_UI_SCREENS.md)), спутники меняют их патчами.
Раздел один: «Категории» — кнопка в шапке списка, «Удалённые» и «Обмен» — в её `···`.

| Экран                   | Что                                                                            |
| ----------------------- | ------------------------------------------------------------------------------ |
| `catalog.products`      | список: поиск, фасеты-фильтры, колонки `ProductColumns`, массовые действия     |
| `catalog.product-form`  | вкладки «Основное», «Описание», «Картинки», «SEO», «История»                   |
| `catalog.categories`    | дерево со счётчиками, перетаскивание, публикация                               |
| `catalog.category-form` | «Основное», «Фильтры» (§6.2), «SEO», «История»                                 |
| `catalog.deleted`       | вкладки «Товары» и «Категории»: список с поиском, «Восстановить», без фильтров |

«Основное» у товара: название, слаг, артикул, штрихкод\*, основная и дополнительные категории,
цена и старая цена\*, единица, приоритет, публикация. Картинки — перетаскивание порядка, `alt` и
`title` на каждом языке. Кнопка «Открыть на сайте» — и у снятого (урезанная страница).

Список ищет и фильтрует движком (решение 14 архитектуры) — на `SqlEngine` это та же база.

### 11.2. API панели

```
GET    /api/cms/catalog/products              ?q&facets&sort&page&per_page&state=published|unpublished|no-category
POST   /api/cms/catalog/products              { values } → 201, как GET одного
GET    /api/cms/catalog/products/{id}         → { data: { product, values, images } }
PUT    /api/cms/catalog/products/{id}         { values } — одна транзакция: ядро + write() частей + журнал + touch
DELETE /api/cms/catalog/products/{id}         soft → 204
POST   /api/cms/catalog/products/{id}/restore → { data: product }
POST   /api/cms/catalog/products/{id}/images  multipart file | { url } → 201 { data: image }
PUT    /api/cms/catalog/products/{id}/images  { images: [{ id, alt?, title? }] } — вся галерея по порядку → { data: [image] }
DELETE /api/cms/catalog/products/{id}/images/{image} → 204, файлы сразу
GET    /api/cms/catalog/deleted               ?type=products|categories&q&page&per_page
GET    /api/cms/catalog/categories            → { data: [узел дерева] }
GET    /api/cms/catalog/categories/{id}       → { data: { category, values } }
POST   /api/cms/catalog/categories            { values, parent_id? } → 201, как GET одной
PUT    /api/cms/catalog/categories/{id}       { values }
POST   /api/cms/catalog/categories/{id}/move  { parent_id, before_id } → { data: [узел дерева] } — всё дерево
DELETE /api/cms/catalog/categories/{id}       204; не пуста — 422 с meta { products, children }
POST   /api/cms/catalog/categories/{id}/restore → { data: category }
GET    /api/cms/catalog/facets                реестр: ключ, код, вид, подпись
GET    /api/cms/catalog/bulk                  действия, которые этому администратору можно запускать
POST   /api/cms/catalog/bulk                  { action, params, selection: { ids } | { query } } → 200 run (сразу) | 202 run (очередь)
GET    /api/cms/catalog/bulk/{run}            прогресс
```

Ролик картинки — `POST`/`DELETE …/images/{image}/video` (спека видео); обмен —
`/api/cms/catalog/exchange/*` (спека обмена).

Список — формат `->paginate()` как есть, ищет и считает движок, и рядом `counts: { no_category }`
(решение 3), `facets` — по ключу фасета `{ key, kind, values: [{ value, label, count }] }` для
`terms`/`tree`, `{ min, max }` для `range`, `{ count }` для `toggle`, — `columns: [{ key, label,
sort }]` колонок спутников (у каждой строки их значения под `columns`) и `outgrown` (§8.2).
Выбор фасетов — `facets[category][]=3&facets[price][min]=100`. Фильтр и сортировка — белый
список, произвольные колонки отклоняются; `sort` — ключ реестра `Sorts`, неизвестный — 422.
`GET /facets` отвечает `data: [{ key, code, kind, label, indexable }]` и `meta.sorts: [{ key,
label }]`. `GET /categories/{id}` несёт ещё `facets_from: { id, name } | null` — откуда
унаследованы фасеты, если своих нет (`null` и при своих, и при «все по умолчанию»). Занятый
артикул — 422 с `errors.sku` и `meta.taken_by: { id, name, url, deleted }`, `url` — адрес товара
в панели `/{webx-admin.path}/catalog/products/{id}`, форма показывает ссылку.

Как это читает панель: `kind` — в любом регистре, панель приводит к нижнему; значения
`terms`-фасета для выпадашки — из `facets` ответа списка (с числом товаров), у `range` — границы
оттуда же подсказкой в полях; сортировки — только из `meta.sorts`, неизвестную панель не шлёт.
`toggle` включается как `facets[<key>][]=1` — список, как у всех фасетов. `PUT /products/{id}` и
`PUT /categories/{id}` принимают **часть** полей экрана: список и дерево публикуют и снимают одним
`{ values: { is_published } }`.

Формы — экраны-описания в php-пакете (`resources/screens/product-form.json`,
`category-form.json`), и API пишет **ровно по ним**: `values` — поля экрана по `name`, как у
services. Переводимые — картой языков, которая накладывается на имеющиеся. Поле части спутника на
экране называется `<ключ части>.<поле>` (`stock.status`) и в `values` приезжает так же — один
ключ с точкой, не вложенность. Поле экрана, которое не взяло ни ядро, ни часть, — ошибка
разработчика, громко. Выключенные цена и штрихкод снимаются с экрана патчем в провайдере, поэтому
их нет ни в форме, ни в записи, ни в ответе.

- **`product`** (строка списка и шапка формы): `id, name` (на языке панели), `sku, barcode*,
price*, old_price*` (числа), `unit, priority, is_published, state`
  (`published|unpublished|deleted`), `visible` (§5), `category: { id, name, deleted } | null`,
  `image: { id, url, thumb } | null`, `url` (адрес на сайте, у снятого тоже — урезанная
  страница; у удалённого `null`), `created_at, updated_at, deleted_at`.
- **`values` товара**: `name, slug, summary, description` — карты языков; `sku, barcode,
category_id, categories` (дополнительные, без основной), `price, old_price, unit, priority,
is_published`, `seo`; плюс `read()` каждой части под `<ключ>.<поле>`.
- **`image`**: `id, path, url, thumb, alt, title` (карты языков), `width, height, size, position`.
- **Узел дерева**: `id, parent_id, name, slug, depth, is_published, visible, products_count`
  (живые, основная и дополнительные, с потомками, каждый товар один раз), `url, children`.
- **`category`**: `id, parent_id, name, slug, depth, is_published, visible, products_count, url,
created_at, updated_at, deleted_at`; **`values`**: `name, slug, description` (карты),
  `cover: { path } | null`, `is_published`, `facets` (`null` — унаследовано, иначе `[{ key,
visible }]` по порядку), `seo`.
- **`deleted`**: `->paginate()`; строка товара `{ id, name, sku, category: { id, name, deleted }
| null, deleted_at }`, категории `{ id, name, slug, parent: { id, name, deleted } | null,
deleted_at }`.
- **Действие** (`GET /bulk`): `{ key, label, permission, trashed, params: [PartField] }` —
  `trashed` у восстановления (действует на «Удалённые»), `params` — что спросить до запуска
  (`type: category` — дерево категорий; `source` — справочник). Список уже отфильтрован по правам
  вызывающего.
- **Прогон** (`POST /bulk`, `GET /bulk/{run}`): `{ id, action, label, status:
queued|running|done|failed, total, done, failed, errors: [{ id, name, message }] (первые сто),
history_id, created_at, finished_at }`. До `bulk.sync_limit` товаров — сделано в запросе, `200`
  и `id: null`; больше — `202`, панель опрашивает. `selection.query` — `{ q, state, facets }` как
  у списка; `selection.ids` — не больше 10 000.

Узлы экранов: `wx-catalog-category` (одна категория, `props.multiple` — список),
`wx-catalog-facets` (вкладка «Фильтры»), `wx-catalog-gallery` (галерея — отдельные запросы выше,
не значение формы); `wx-history` — из `module-admin`.

### 11.3. Журнал

Товар и категория пишут журнал ([`WEBX_UI_HISTORY.md`](WEBX_UI_HISTORY.md)); вкладка «История»
— узел `wx-history` в обеих формах (редактор отдаёт id через `provideHistorySubject`). Сохранение
формы — одна запись: модели копят изменения и отдают их форме (`takeHistoryChanges()`), туда же
идут изменения всех `ProductParts`; сменилась публикация — `published`/`unpublished` вместо
`updated`. Импорт и массовое действие — одна запись на прогон со строками по товарам. Правки из
панели всех модулей пишутся с источником `panel` и автором. Список имён журнал показывает через
запятую (`historyValue` в `module-admin`, для любого модуля); списки объектов остаются JSON.

### 11.4. Массовые действия

Реестр `BulkActions`, контракт `BulkAction`: `key`, `label`, `permission`, `trashed`,
`params(): list<PartField>`, `rules`, `apply(Product, params): changes`. Ядро: `publish`,
`unpublish`, `set-category`, `add-category`, `remove-category`, `delete`, `restore`; спутники —
свои (метка, статус наличия, бренд). Выбор — список id или запрос (фильтр списка целиком —
«выбрано 40 312»), который превращается в id в момент запуска.

До `sync_limit` (50) товаров — синхронно, без прогона. Больше — очередью пачками по 500: id
прогона лежат в `catalog_bulk_run_items`, пачка идёт от курсора (последний id) под блокировкой
строки прогона, поэтому повтор пачки ничего не делает дважды; каждая пачка — транзакция и один
`touch`, товар — точка сохранения внутри неё, так что ошибка в товаре не роняет соседей. Задание —
`ProcessBulkChunk`, одна пачка на задание, следующая ставится за ней. **Нужна живая очередь**
(`queue:work`; в скелете — supervisord), иначе прогон больше `sync_limit` стоит в `queued`.

Журнал: запись прогона открывается при запуске, строки каждой пачки пишутся под неё от имени
запустившего (`admin_id` и `admin_name` в прогоне — у воркера никто не вошёл), `summary` при
закрытии получает `rows`, `done`, `errors`. После прогона с отказами панель оставляет выбранными
именно отказавшие товары.

### 11.5. Права

`catalog.view` — разделы и списки; `catalog.manage` — всё, что пишет, кроме удаления и
восстановления; `catalog.delete` — удаление и «Удалённые». Категории — теми же правами.

## 12. MCP

### 12.1. Инструменты и права

Изменяющие — с `dry_run`. Право — по правилу [`WEBX_UI_MCP_ACCESS.md`](WEBX_UI_MCP_ACCESS.md) §6:
скоуп токена даёт `catalog:read` / `catalog:write`, ограничивает право подключившего
администратора. Удаление названо явно — `Tool::mutating(..., permission: 'catalog.delete')`: без
этого дефолт вывел бы `catalog.manage`, и агент редактора удалял бы товары, которые сам редактор
удалить не может.

| Инструмент                                                        | Право                                                              |
| ----------------------------------------------------------------- | ------------------------------------------------------------------ |
| `catalog_products_list` (фасеты и поиск, как в панели)            | `catalog.view`                                                     |
| `catalog_products_get` (с частями спутников)                      | `catalog.view`                                                     |
| `catalog_categories_tree`                                         | `catalog.view`                                                     |
| `catalog_products_create` / `update` (с частями спутников)        | `catalog.manage`                                                   |
| `catalog_products_publish` / `unpublish`                          | `catalog.manage`                                                   |
| `catalog_categories_create` / `update` / `move`                   | `catalog.manage`                                                   |
| `catalog_bulk` (с `dry_run` — число затронутых и первые двадцать) | `catalog.manage`; действия `delete` и `restore` — `catalog.delete` |
| `catalog_products_delete` / `restore`                             | `catalog.delete`                                                   |
| `catalog_categories_delete` / `restore`                           | `catalog.delete`                                                   |

Галерея — шесть `catalog_products_gallery*` ([`WEBX_UI_CATALOG_VIDEO.md`](WEBX_UI_CATALOG_VIDEO.md));
обмен — `catalog_import`, `catalog_export`, `catalog_exchange_columns`, `catalog_exchange_run`,
`catalog_exchange_profiles` и ресурс `catalog://exchange`
([`WEBX_UI_MODULE_CATALOG_EXCHANGE.md`](WEBX_UI_MODULE_CATALOG_EXCHANGE.md) §9). Спутник, чьи
инструменты принадлежат каталогу по имени, регистрирует их через `SatelliteTools`.

Записи агента попадают в журнал с источником `mcp`; читает журнал агент инструментами
`module-admin` ([`WEBX_UI_HISTORY.md`](WEBX_UI_HISTORY.md) §6).

### 12.2. Ресурсы

- `catalog://facets` — реестр фасетов (ключ, код в адресе, вид, подпись) и сортировки;
- `catalog://fields` — единицы и включённые поля (цена, штрихкод, видео), чтобы агент не
  предлагал выключенное;
- `catalog://addresses` — правила слагов и адресов (§4);
- `catalog://categories` — дерево кратко (id, название, слаг, родитель, опубликована);
- `catalog://product-parts` — части формы из `ProductParts` (`describe()`, §7.4): ключ, подпись,
  модуль и поля с типом, правилами и допустимыми значениями (для справочников — где их взять).
  Ядро собирает ресурс из реестра и про спутники не знает; `catalog_products_update` принимает
  части ровно по этим ключам, неизвестный ключ — ошибка с перечнем известных;
- `catalog://bulk-actions` — что умеет `catalog_bulk` и что каждое действие спрашивает.

## 13. Конфиг `webx-catalog.php`

```php
return [
    'root' => ['enabled' => false, 'prefix' => 'catalog'],  // WEBX_CATALOG_ROOT, WEBX_CATALOG_ROOT_PREFIX
    'price' => ['enabled' => true, 'currency' => null],    // ISO 4217; без неё нет Offer в разметке
    'fields' => ['barcode' => true, 'facets' => false, 'video' => true],
    'units' => ['pcs', 'kg', 'g', 'm', 'm2', 'm3', 'l', 'pack', 'set'], 'default_unit' => 'pcs',
    'facet_codes' => [],                                   // ['price' => ['ru' => 'cena']]
    'engine' => 'sql',                                     // 'manticore' — из module-catalog-manticore
    'sql_engine_limit' => 2000,
    'per_page' => 24,
    'sorts' => ['default', 'price_asc', 'price_desc', 'popular', 'new', 'name'],
    'default_sort' => ['priority' => 'desc', 'score' => 'desc', 'created_at' => 'desc'],
    'popularity' => ['views' => true, 'decay' => 0.9, 'weights' => ['views' => 1], 'touch_threshold' => 0.05],
    'images' => ['disk' => 'public', 'max_size_kb' => 10240],
    'videos' => ['max_size_mb' => 2048, 'types' => ['video/mp4', 'video/webm']],
    'bulk' => ['chunk' => 500, 'sync_limit' => 50],
    'exchange' => [/* спека обмена */],
    'layout' => env('WEBX_CATALOG_LAYOUT'),                // Blade-компонент лейаута витрины
    'middleware' => ['web', 'webx.locale'],               // корня и поиска — маршрутов, а не строк реестра
];
```

Подписи единиц — в словаре модуля (`webx-catalog::units.pcs` → «шт.»).

## 14. Регистрации

Как у каждого раздела (CLAUDE.md §4): `Setup\Catalogue`, `extra.webx.npm` и `extra.webx.panel`,
плейграунд (`apps/playground/src/panel/main.ts` и фикстуры сервера), `scripts/packages.mjs` в
`webx-cms.local`; composer-пакет — четыре раза в `php/`. Типы `routing`: `catalog.category`,
`catalog.product`.

- `LinkSource` для меню — свои `CategoryLinkSource` и `ProductLinkSource` (не рамочный
  `CategoryLinkSource`: у каталога дерево с наследуемой видимостью, «доступно» — видимо по §5).
- `RecordQuery`: `products()` — `ProductQuery`, категория с поддеревом, сортировка ключом `Sorts`
  (`products()->category('slug')->sort('popular')->take(8)`); спутники добавляют шаги макросами.
- `CollectionSource` — `ProductsSource` (`products`) без выбора категорий в поле: у каталога
  дерево со своим API, а `wx-categories` читает плоский список; полка одной категории —
  `products()->category(…)` в шаблоне блока.
- В других пакетах ядро завело общие швы: `Misses` в `routing`, `Thumbnails::variantOf()` в
  `module-media`, `DoctorChecks` в `module-admin`, `SitemapSources` в `module-seo`.

## 15. Демо

`webx:demo`: дерево из трёх уровней (~15 категорий), ~150 товаров с картинками, ценами,
несколькими снятыми и удалёнными, пара без категории — чтобы фильтр был не пуст. Фикстуры
плейграунда — то же в памяти.

## 16. Что стерегут тесты

Адреса (301 на канон, 301/410 удалённого, урезанная страница снятого, 422 занятого слага с
держателем); видимость поддерева снятой категории; `taken_by` артикула; `SqlEngine` (поддерево,
счётчик без собственного фильтра, порядок `ids`); наследование фасетов категории и скрытый новый
фасет; `FilterUrls` (однозначный разбор, одно написание, `prepare` один раз на рендер); откат
формы при упавшей части и одна запись журнала; очередь (ничего при `SqlEngine`, `touchQuery` —
один запрос, повтор пачки безвреден); массовые действия по запросу; выключенные цена и штрихкод
нигде не появляются; популярность (затухание, `touch` только сдвинувшихся); все фасеты в ответе
списка (`RegistriesTest`).

## 17. Слова

Английские в коде и словаре по умолчанию, русские — в `lang/ru` модуля: «Каталог», «Товары»,
«Категории», «Удалённые»; «Опубликован», «Снят», «Удалён»; «Категория не задана»; «Снят с
продажи»; «Цена по запросу»; «Артикул занят товаром :name». Выбор строк в списках — пропсы
`selectRowLabel` / `selectAllLabel` у `WxTable`, переводы — в `filters.*` `module-admin`; выбор
категории в массовом действии говорит «Выберите категорию».

## 18. Отложено

Типы цен и валюты, вес и габариты (commerce), удаление навсегда, ручной порядок внутри категории,
«покупали вместе», многосайтовость. Спутники и их состояние — §10 архитектуры.

## 19. Как легло в код

Решения, которые не видны из разделов выше:

- **Один раздел панели** (`catalog`): сервер регистрирует один модуль, навигация — запись на
  модуль. «Категории» — кнопка в шапке списка, «Удалённые» и «Обмен» — в её `···`.
- **Журнал формы — одна запись на сохранение** (§11.3), массовое действие — одна родительская
  запись на прогон (§11.4).
- **Корень каталога главнее страницы** (§4), порог `SqlEngine` — над списком товаров (§8.2).
- **Очередь массовых действий нужна живая** (§11.4).

Открыто:

- Гайд `apps/docs/guide/catalog.md` описывает только галерею и обмен; ядра целиком (каталог,
  фильтр, адреса, спутники) в нём нет.

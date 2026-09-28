# `webx-ui/module-banners` — спецификация и план реализации

Статус: спроектирован 28.09.2026 (B0), B1–B4 впереди. Пакеты — `webx-ui/module-banners`
(composer) и `@webx-ui/module-banners` (npm).

Баннеры — картинка (и отдельная под телефон), по желанию видео, заголовок, текст и до трёх
кнопок. Стоят они в **именованных местах**, как пункты меню: шаблон просит `banners('hero')`,
редактор кладёт в место баннеры и двигает их руками. Своей страницы нет ни у баннера, ни у
модуля: на сайт баннеры попадают **блоком** (`wx-collection` с местом) или хелпером из шаблона
— одной картинкой, случайной или слайдером.

Ядро почти всё готово: «вставить блоком» (`CollectionSource`, `wx-collection`, `BlockOffers`) —
с `module-faq`, `RecordQuery` — с `module-team`, ссылка (`Link`, `wx-link`) — с `module-menu`,
`wx-media` с `props.accept` — с `module-media`, переводимые поля внутри `wx-repeater` — с
`module-press`. Своего в ядре одно: необязательный интерфейс `DescribesCategories` (§4) — место
вместо категории, и пустой выбор значит «ничего».

## 1. Границы

**Внутри:** места (объявленные конфигом и свои), баннеры с медиа, словами, кнопками,
выключателем и ручным порядком внутри места, корзина; `DescribesCategories` в `module-admin` и
его поддержка в `CollectionField`; источник `banners`, хелперы `banners()` и `banners_layout()`,
тип блока «Баннеры» с видами `single`, `random`, `slider`; конфиг видов и параметров; экраны
панели, API, MCP, демо.

**Снаружи:** расписание показа (решение 5); связи с записями (решение 6); своя страница и
разметка schema.org; черновики и версии (решение 8); Blade-компонент (решение 10); статистика
кликов и A/B; ссылка на весь баннер, отдельное видео под телефон (§7).

## 2. Принятые решения (не переоткрывать)

Решения 1–6 приняты пользователем в обсуждении 28.09.2026.

1. **Места именованные, как меню** (`WEBX_UI_MODULE_MENU.md` §2, решение 10). Конфиг объявляет
   места, строка в `banner_places` создаётся при первом сохранении баннера в место — ни записи
   на загрузке, ни команды синхронизации. Администратор может завести своё место и удалить своё;
   удалить можно только пустое место. Объявленное место не удаляется и не переименовывается:
   шаблон просит его по ключу.
2. **Из чего состоит баннер:**
   - картинка и отдельная картинка под телефон;
   - видео, при нём картинка — постер и замена;
   - заголовок и текст;
   - кнопки: повторитель, максимум 3. У каждой свой текст (переводимый, любой), `wx-link` и
     вариант оформления из конфига;
   - «Включён» и ручной порядок внутри места.
3. **Медиа не переводятся**, слова переводятся. `alt` и `title` внутри значения `wx-media`
   по-прежнему по языкам (`MediaValues::resolve()`).
4. **Виды: `single`, `random`, `slider`.** Основные параметры живут в конфиге: интервал,
   автопрокрутка, цикл, стрелки, точки, пауза при наведении, пропорции, порог телефона, видео на
   телефоне. У объявленного места можно переопределить вид и параметры (§5.4).
5. **Расписания нет.** Баннер показывается, пока включён.
6. **Связей нет** — ни `HasRelations`, ни цели для других модулей, ни фильтра по связи в блоке.

Решения, принятые при обсуждении и при написании спеки (можно оспорить до B1):

7. **Видимость по языку.** Слова баннера — заголовок и текст. Баннер без слов виден везде.
   Баннер со словами виден только на языках, где написан заголовок или текст. Кнопка без подписи
   на языке выпадает, а решать видимость кнопки не могут. Запасного языка нет: чужой язык на
   картинке хуже, чем её отсутствие.
8. **Корзина есть, черновиков и версий нет.** Выключатель «Включён» — единственное «не на
   сайте». Новый баннер выключен, пока его не включат: иначе первое же сохранение
   недоделанного баннера — это баннер на главной.
9. **Блок — `wx-collection` с `source: banners`**, места в нём играют роль категорий. Для этого
   в ядре появляется необязательный интерфейс `DescribesCategories` (§4):
   `categoriesLabel()` («Место») и `categoriesRequired()`. Пустой выбор места значит «ничего», а
   не «всё». Места, у которых ещё нет строки, в поле не показываются: баннеров в них всё равно
   нет.
10. **Хелперы `banners('hero')`** на `RecordQuery` и **`banners_layout($place, $layout)`**,
    который отдаёт слитые параметры вида для шаблона. Blade-компонента нет: шаблон блока хранится
    в базе, а вьюха в пакете была бы второй копией. Разметки schema.org и своей страницы нет.
11. **Экраны панели.** `/banners` — `WxListDetail`, как у меню: слева места, справа баннеры
    места с перетаскиванием. Баннер открывается отдельным экраном `banners.form` (описанный
    экран, чтобы проект мог добавлять поля в `extra`) с панелью действий. Место баннера меняется
    в форме, и баннер встаёт в конец нового места.
12. **Картинка обязательна** — единственное обязательное поле. Раскладка всех трёх видов
    держится на картинке (пропорции, постер видео, замена без JS); полоса из одного текста — это
    другой тип блока, а не баннер без картинки. Видео без картинки — отказ под `image`.
13. **Место — колонка записи, а не поле экрана.** Список мест живой (свои места появляются и
    исчезают), а опции селекта в описанном экране кладутся патчем при `boot()` (§5.4 спеки
    команды) — живой список туда не ложится. Поэтому место выбирается `WxSelect` вне экрана и
    едет рядом с `values`: `PUT { values, place }` (§5.7).
14. **`random` выбирается в браузере, а не на сервере.** Шаблон печатает все баннеры места,
    кроме первого — с `hidden`, скрипт показывает случайный. Без JS виден первый. Так выбор
    случаен на каждый показ, даже если сайт кеширует HTML страницы, а скрытые картинки с
    `loading="lazy"` не грузятся.
15. **Несколько мест в одном блоке можно** (`wx-collection` — мультиселект): баннеры идут местами
    по возрастанию id места (так `Selection` хранит выбор), внутри места — по `position`. Вид при
    пустом `layout` берётся у первого места.
16. **Вариант кнопки, убранный из конфига, не запирает баннер** (урок T3 у команды): сохранённый
    вариант проходит назад как открыт, новая ссылка на такой вариант — 422. На сайте кнопка с
    таким вариантом получает первый вариант конфига, а не выпадает: кнопка важнее оформления.

## 3. Схема

```
banner_places
  id
  key          string(64) unique   -- то, чем место просят: hero, promo
  title        json nullable       -- переводимое название своего места; у объявленного не читается
  timestamps

banners
  id
  place_id     FK → banner_places, cascadeOnDelete
  image        json nullable       -- значение wx-media (path, alt, title); обязательна на записи
  image_mobile json nullable       -- то же, под телефон
  video        json nullable       -- значение wx-media с accept: video
  title        json nullable       -- переводимое
  text         json nullable       -- переводимое, простой текст (textarea)
  buttons      json nullable       -- [{ "label": {"en": "…"}, "link": {Link}, "variant": "primary" }]
  enabled      bool default false  -- решение 8
  position     int default 0       -- порядок внутри места
  extra        json nullable
  softDeletes, timestamps

index (place_id, position)
```

- **Дефолтов у строковых колонок нет вовсе** — считать нечего, и это нарочно: `enabled` и
  `position` числовые. Если B1 захочет дефолт у строки (скажем, `variant` отдельной колонкой) —
  считать символы против длины (CLAUDE.md §4, MariaDB).
- `image` в базе `nullable`, хотя обязательна: обязательность — правило формы (решение 12), а
  не колонки. Картинку, удалённую из библиотеки, база не держит внешним ключом (`wx-media`
  хранит путь, а не id), и строку это не ломает: карточка без картинки на сайт не выходит
  (§5.2).
- Имя модели места — `Place`, баннера — `Banner`; колонка `enabled`, а не `visible`/`hidden`
  (CLAUDE.md §4: `hidden` и `visible` у модели — свойства Eloquent, а не атрибуты).
- Каскад на `place_id` — страховка, а не механизм: API удаляет только пустое место (§5.7).
- Обе миграции — `2026_01_01_*`: чужих таблиц нет, внешний ключ только свой.

## 4. `DescribesCategories` — место вместо категории (`module-admin`)

```php
namespace WebxUi\Admin\Collections;

/**
 * A source whose categories are not "categories" to an editor: the places of banners. The field
 * names them the source's way, and an empty choice means nothing rather than everything.
 */
interface DescribesCategories
{
    /** The label of the field's category picker, in the panel's language: "Place". */
    public function categoriesLabel(): string;

    /** Whether nothing chosen shows nothing. A block of every banner there is is a trap. */
    public function categoriesRequired(): bool;
}
```

- **Необязательный** — отдельный интерфейс рядом с `CollectionSource`, а не новые методы в нём:
  пять существующих источников не меняются.
- `CollectionController` добавляет к каждому источнику `categories_label` (`null`, если
  интерфейса нет) и `categories_required` (`false`).
- `CollectionField.vue`: подпись выбора — `categories_label ?? t('collections.field-categories')`;
  плейсхолдер пустого выбора при `categories_required` — `t('collections.field-none')` («Не
  выбрано — блок пуст»), а не «Все». Плейсхолдер говорит, что значит пустой выбор, — так он
  работает и сейчас, и потому здесь не «Выберите место»: подпись над полем уже говорит «Место».
  Переключатель «фильтр по категориям на сайте» (`filter`) при `categories_required` не
  рисуется: вкладки мест над баннерами смысла не имеют.
- `Selection` не меняется: пустой список категорий он хранит как пустой, а «пусто — ничего»
  отвечает источник (§5.3). Правило в одном месте — у того, кто объявил обязательность.
- Слово `collections.field-none` — в `module-admin` на десяти языках (B1) и английским полом в
  `messages.ts` (B2).

## 5. Модуль

### 5.1. Модели и зависимости

- `Place` — `HasTranslations` (`title`), `hasMany(Banner)`. Ключ — `[a-z0-9-]{1,64}`, начинается
  с буквы. `Places` (не модель, сервис) — объединение конфига и таблицы, как у меню:
  объявленное место без строки существует, строка создаётся `Places::row($key)` в той же
  транзакции, что и первый баннер. Объявленное место, убранное из конфига при живой строке,
  становится своим: видно, удаляется, если пусто.
- `Banner` — `HasExtra`, `HasTranslations` (`title`, `text`), `SoftDeletes`,
  `belongsTo(Place)`. `scopeVisible()` — включён и не в корзине. Новый баннер и баннер,
  перенесённый в другое место, встают в конец места (`max(position) + 1` в месте).
- `require`: `module-admin`, `module-blocks`, `module-media`, `localization`, `mcp`. `routing`
  не нужен: адреса кнопок считает `wx-link` (`LinkType::resolve()`). `require-dev`:
  `module-pages` — ради демо и тестов блока на странице.

### 5.2. Карточка

`Rendering\Cards` — баннер так, как его читает шаблон:

```php
[
    'id' => 5,
    'anchor' => 'banner-5',
    'categories' => [3],              // контракт CollectionSource: id места
    'place' => 'hero',                // ключ места — для banners_layout()
    'title' => 'Spring sale',         // на языке страницы, иначе ''
    'text' => "…",                    // то же; шаблон печатает nl2br(e())
    'image' => [                      // MediaValues::resolve() на языке страницы
        'url' => …, 'thumb' => …, 'width' => 1920, 'height' => 720,
        'alt' => '…', 'title' => '…', 'mime' => 'image/jpeg',
    ],
    'image_mobile' => [ … ] | null,   // null — шаблон берёт image
    'video' => [ 'url' => …, 'mime' => 'video/mp4' ] | null,
    'buttons' => [                    // в порядке редактора; без подписи на языке — выпадает
        [
            'label' => 'Book now',
            'url' => '/booking',      // LinkType::resolve(): префикс языка, адрес сущности
            'new_tab' => false,
            'rel' => 'nofollow' | null,
            'variant' => 'primary',   // вне конфига — первый вариант конфига (решение 16)
        ],
    ],
    'fields' => [ 'badge' => 'New' ], // поля проекта по имени
]
```

- **Кнопка выпадает** без подписи на языке (решение 7) и со ссылкой, у которой
  `available = false` (сущность в черновике или в корзине): кнопка на 404 хуже, чем её нет.
- **Баннер выпадает**, если его картинка не находится в библиотеке (`url === null`): баннер без
  картинки — дыра в слайдере. Видео, которого нет, просто `null` — остаётся постер.
- Список любой длины — несколько запросов (библиотека пачкой, ссылки пачкой), а не по запросу на
  баннер.

### 5.3. Хелперы и источник

`Rendering\BannerQuery extends RecordQuery`:

| Шаг                | Что делает                                                             |
| ------------------ | ---------------------------------------------------------------------- |
| `in('hero')`       | Только из этих мест — ключ, id, модель или список; неизвестное — пусто |
| `only([5, 2])`     | Только эти, в этом порядке                                             |
| `except($banner)`  | Кроме этих                                                             |
| `take(3)`          | Не больше трёх; null или ноль — все                                    |
| `locale('uk')`     | Язык карточек; по умолчанию тот, на котором рисуется страница          |
| `get()`, `first()` | Список карточек или один; сам запрос можно перебирать и считать        |

- `banners(string|int|iterable|null $places = null): BannerQuery` — `banners('hero')` это
  `banners()->in('hero')`. Без места — все включённые баннеры сайта (для шаблона это осмысленно,
  для блока — нет, там пусто значит ничего).
- **Место — не категория `RecordQuery`.** Движок фильтрует категории через пивот
  `categoryLinks()`, а у баннера место — колонка. Поэтому `in()` — свой шаг (`withStep`), место
  по **ключу**, а не по переводимому слагу (`categoryModel()` остаётся `null`), фильтр —
  в `narrow()` (`whereIn('place_id', …)`), порядок — в `order()`: место по возрастанию id (или
  в порядке, названном в `in()`), затем `position`, `id`. `withCategories()` не вызывается
  никогда.
- `shownIn()` — решение 7. `newQuery()` — `visible()` плюс предзагрузка места.
- `banners_layout(string|int|null $place = null, ?string $layout = null): array` — слитые
  параметры §5.4 в порядке «пакет ← `options` конфига ← место ← `$layout` блока», вид
  проверен по списку (`single|random|slider`, иное — вид места). Каждый ключ читается с
  дефолтом пакета, а не из слияния (`mergeConfigFrom` сливает на один уровень — CLAUDE.md §4).
- Оба хелпера — за `function_exists`, строки в `Doctor\Checks\Helpers`.

`Collections\BannersSource implements CollectionSource, DescribesCategories`:

- `key() = 'banners'`, `categories() = 'banners/place-options'`, `relations() = []`,
  `supportsMarkup() = false`, `permission() = 'banners.view'`, `categoriesLabel()` —
  «Место», `categoriesRequired() = true`.
- `items()`: пустые `$selection->categories` — `[]` (решение 9); иначе
  `banners()->in($selection->categories)->take($selection->limit)->locale($locale)->get()`.
  **Не `selected()`**: он зовёт `withCategories()`, а модель без `categoryLinks()` на выбранных
  категориях — исключение (итог T1 спеки команды).

### 5.4. Конфиг

```php
// config/webx-banners.php
return [
    // Places a site's templates ask for by key. Declared ones cannot be deleted or renamed in the
    // panel; an administrator adds their own there. A title is a string or a translation key.
    'places' => [
        'hero' => [
            'title' => 'webx-banners::places.hero',
            'layout' => 'slider',
        ],
        'promo' => [
            'title' => 'webx-banners::places.promo',
            'layout' => 'single',
            'options' => ['ratio' => '3/1', 'ratio_mobile' => '3/2'],
        ],
    ],

    // The layout of a place that names none: single, random or slider.
    'layout' => 'slider',

    // Everything a layout reads. A place overrides any of these under its own `options`.
    'options' => [
        'interval' => 6000,          // ms between slides
        'autoplay' => true,          // off under prefers-reduced-motion regardless
        'loop' => true,              // after the last slide, the first
        'arrows' => true,
        'dots' => true,
        'pause_on_hover' => true,    // and on focus inside the slider
        'ratio' => '16/6',           // aspect-ratio of the frame on wide screens
        'ratio_mobile' => '4/5',     // below the breakpoint
        'breakpoint' => 768,         // px: below it image_mobile, ratio_mobile and no video
        'video_on_mobile' => false,  // below the breakpoint the poster rather than the video
    ],

    // Button looks: key => label for the panel. The first one is the fallback (decision 16).
    'variants' => [
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'link' => 'Link',
    ],
];
```

- Названия объявленных мест — через `__()`: ключ перевода панель показывает на своём языке,
  простая строка приходит как есть (`__()` отвечает ею же). У пакета свои два — в
  `lang/*/places.php`.
- Варианты кнопок — в экран `banners.form` патчем `Screens::extend('banners.form', …)` при
  `boot()`, как соцсети у команды (итог T3: `set` доходит внутрь `wx-repeater`, `OptionType` там
  видит опции). Названия вариантов — простые строки или ключи перевода, как у мест.
- Незнакомые ключи в `options` не ошибка: `banners_layout()` отдаёт их шаблону как есть —
  своя копия типа блока может читать своё.

### 5.5. Тип блока «Баннеры»

`resources/blocks/banners.json`, предлагается через `BlockOffers`:

```json
[
  {
    "id": "banners",
    "type": "wx-collection",
    "label": "Banners",
    "props": { "source": "banners" }
  },
  {
    "id": "layout",
    "type": "wx-segmented",
    "label": "Layout",
    "props": {
      "options": [
        { "value": "place", "label": "As the place" },
        { "value": "single", "label": "One" },
        { "value": "random", "label": "Random" },
        { "value": "slider", "label": "Slider" }
      ]
    }
  }
]
```

- **Пустой `layout` и `place` — вид места из конфига** (`banners_layout($items[0]['place'],
null)`): блок хранит только тронутое (итог F1 спеки FAQ). Параметров вида в блоке нет — они в
  конфиге и у места (решение 4).
- **Пустое место в блоке — пустой блок:** ни обёртки, ни заголовка, страница отвечает 200.
- **Разметка.** `<section class="wx-banners wx-banners--{layout}" data-wx-banners='@json($layout)'>`
  — параметры скрипт читает с атрибута, иначе у него их нет (CLAUDE.md §4 про `data-wx-values`).
  Кадр — `aspect-ratio` из `ratio`/`ratio_mobile` CSS-переменными в `style`. Картинка —
  `<picture>` с `<source media="(max-width: {breakpoint − 1}px)">` под `image_mobile`, `<img>` с
  `width`/`height`/`alt`; первый баннер `loading="eager"`, остальные `lazy`. Заголовок, текст
  (`nl2br(e())`), кнопки — `<a class="wx-banners__button wx-banners__button--{variant}">`,
  новая вкладка — `target="_blank"` и `rel="noopener"` плюс `rel` ссылки.
- **Видео.** `<video muted playsinline loop preload="none" poster="{image.url}" data-src="…">`
  поверх картинки. Адрес ставит скрипт — только на широком экране (или при `video_on_mobile`) и
  не при `prefers-reduced-motion`. Без JS, на телефоне и при reduced motion видна картинка:
  постер и замена — одно и то же (решение 2).
- **Виды.** `single` — первый баннер, остальные не печатаются. `random` — все, кроме первого, с
  `hidden`; скрипт открывает случайный (решение 14). `slider` — лента со `scroll-snap`, стрелки,
  точки, автопрокрутка с интервалом, пауза при наведении и фокусе, цикл; `prefers-reduced-motion`
  выключает автопрокрутку. Скрипт слайдера — от отзывов и команды, с параметрами из атрибута.
  Один баннер в слайдере — без стрелок, точек и прокрутки.
- Стили нейтральные, без `--wx-*`, `[hidden]` объявлен явно (CLAUDE.md §4 про `display: flex`
  и `[hidden]`). Синтаксис шаблона — подмножество Blade плейграунда: `@if`, `@foreach`, `{{ }}`,
  без `@php` (CLAUDE.md §4); `banners_layout` плейграунд объявляет `defineFunction`.

### 5.6. Панель

- Пункт меню «Баннеры» верхнего уровня, иконка `image` (есть в наборе, `icons.test.ts`),
  `order() = 660` — после команды, модуль панели `banners`, права `banners.view`,
  `banners.manage`.
- **`/banners` — `WxListDetail`**, как меню (`WEBX_UI_MODULE_MENU.md` §9). Слева места: название,
  ключ, число баннеров, замок у объявленного. «Новое место» в шапке списка — диалог с ключом и
  названием; своё место переименовывается и удаляется из `WxRowMenu` строки (удаление непустого
  — отказ с числом, кнопка при `count > 0` выключена с подсказкой). Открытое место — `?place=<key>`.
- **Справа баннеры места** — `WxSortableList` с ручкой (порядок клавиатурой на ручке — тот же
  `reorder`): миниатюра картинки, заголовок на языке панели (иначе на языке по умолчанию, иначе
  `#id`), значок видео, приглушение выключенного, `WxRowMenu` — открыть, включить/выключить,
  в корзину. Над списком «Новый баннер» и переключатель корзины (`view=trashed`), в корзине —
  «Восстановить». На телефоне правая колонка уезжает в ящик, и слот `detail` сам рисует «назад»
  по `back` (CLAUDE.md §4: ящик `closable: false`).
- **Баннер — отдельный маршрут** `/banners/{id}` и `/banners/new?place=<key>` с `WxScreenHead`
  («назад» — к месту) и `WxActionBar`; Ctrl+S; вопрос при уходе с несохранённым (`confirm` из
  `onBeforeRouteLeave`). `POST` на первом сохранении, потом замена адреса на `/banners/{id}`.
- **Экран `banners.form`** (описанный, вкладок нет — одна колонка карточек):
  - «Медиа» — `image` (`wx-media`, `accept: image`), `image_mobile` (то же), `video`
    (`wx-media`, `accept: video`, подсказка «картинка станет постером»);
  - «Слова» — `title` (`wx-input`, `localized`), `text` (`wx-textarea`, `localized`);
  - «Кнопки» — `buttons` (`wx-repeater`, `max: 3`, `sortable`, `itemLabel: "label"`): `label`
    (`wx-input`, `localized`), `link` (`wx-link`), `variant` (`wx-select`, опции патчем §5.4);
  - «Настройки» — `enabled` (`wx-switch`);
  - `project-fields` для `extra`.

  Место — `WxSelect` над экраном, вне `values` (решение 13): опции — места из `GET places`
  (объявленные тоже, даже без строки), смена места отмечает форму изменённой.

### 5.7. API панели

Формы ответов фиксированы здесь, чтобы B2 шёл параллельно с B1:

```
GET    /api/cms/banners/places                 → { data: [{ id|null, key, title, declared, layout, count }] }
POST   /api/cms/banners/places                 { key, title } → 201 { data: place }
PUT    /api/cms/banners/places/{key}           { title }            только своё
DELETE /api/cms/banners/places/{key}           только своё и пустое; иначе 422 с числом
GET    /api/cms/banners/place-options          → { data: [{ id, name }] }   для wx-collection
GET    /api/cms/banners/places/{key}/banners   ?trashed=1 → { data: [{ id, title, thumb, video, enabled, position, updated_at, deleted_at }] }
POST   /api/cms/banners/places/{key}/banners   { values } → 201 { data: { banner, values } }
POST   /api/cms/banners/places/{key}/reorder   { ids }
GET|PUT|DELETE /api/cms/banners/{id}           { data: { banner, values } }
POST   /api/cms/banners/{id}/restore
```

- **Место в списке:** `id` — `null` у объявленного без строки; `title` — на языке панели
  (объявленное — `__()` названия из конфига, своё — перевод, иначе язык по умолчанию, иначе
  ключ); `declared` — есть ли ключ в конфиге; `layout` — вид места после слияния; `count` —
  баннеры вне корзины. Порядок: объявленные в порядке конфига, затем свои по названию.
- `POST places` — ключ `[a-z0-9-]`, до 64, с буквы, не занят ни строкой, ни конфигом; 422 под
  `key`. `title` обязателен на языке по умолчанию — 422 под `title.<язык>`.
- `PUT`/`DELETE` объявленного места — 403. `DELETE` непустого — 422 под `place` с текстом,
  который называет число (корзина считается: каскад снёс бы и её), плюс поле `count` в теле
  ответа. Удачный `DELETE` — 204.
- `place-options` — только места со строкой (решение 9), `name` на языке панели.
- **Баннеры места:** неизвестный ключ — 404; объявленное место без строки — `{ data: [] }`.
  `title` строки — на языке панели, иначе на языке по умолчанию, иначе `#id`; `thumb` — миниатюра
  картинки или `null`; `video` — `true`/`false`. `?trashed=1` — только корзина.
- `POST places/{key}/banners` — объявленное место без строки получает её в той же транзакции;
  баннер встаёт в конец. `reorder` — `{ ids }`, 204; не-список — 422 под `ids`; чужие id — 422.
- **Форма:** `banner` — `{ id, place, title, enabled, deleted_at }` (`place` — ключ); `values` —
  `image`, `image_mobile`, `video` (значения `wx-media`), `title`, `text` картами языков,
  `buttons` (`[{ label: {…}, link: {Link}, variant }]`), `enabled` и поля проекта.
- `PUT /banners/{id}` — `{ values, place? }`: `place` — ключ, неизвестный — 422 под `place`;
  смена места ставит баннер в конец нового (решение 11). `POST` и `PUT` — одна
  `BannerForm::save()` в транзакции: отказ не оставляет строк — ни баннера, ни ленивого места.
- **Где лежат 422:** нет картинки — `image` (в том числе «видео без картинки», решение 12);
  не тот вид файла — `image`/`image_mobile`/`video` текстом `MediaValues`; кнопок больше трёх —
  `buttons`; строка кнопки — `buttons.<n>.link` (ссылки нет или она кривая),
  `buttons.<n>.variant` (не из конфига и не сохранённый — решение 16), где `n` — номер строки
  так, как её видит редактор (`rowErrors` у `WxRepeater`, итог P2 спеки прессы). Пустая строка
  кнопки (ни подписи, ни ссылки) выбрасывается на записи.
- `DELETE /banners/{id}` — в корзину, 204. `restore` — голый ресурс строки списка; место к
  этому моменту всегда есть (удалить можно только пустое, корзина считается).

### 5.8. MCP

`banners_places`, `banners_place_create`, `banners_place_delete`, `banners_list`, `banners_get`,
`banners_create`, `banners_update`, `banners_delete`, `banners_reorder` — через те же `Panel\*`,
что и панель. Место называется ключом, баннер — id. Медиа — ключ файла библиотеки, неизвестный —
отказ инструмента (как у отзывов и команды), не тот вид файла — отказ. Кнопки —
`{ label, link, variant }`: `label` — строка (язык по умолчанию) или карта языков, `link` — адрес
или сущность в той же форме, что у `menu_add_link`; вариант не из конфига — отказ со списком
ключей. `banners_place_delete` непустого места — отказ с числом. Ресурс `banners://catalog`:
варианты кнопок и виды в шапке; места с `declared`, `layout` и баннерами по порядку, у каждого
`enabled`, `written_in` (языки заголовка и текста), `has_video`.

### 5.9. Демо

`BannersDemo`, данные — `resources/demo/banners.json` (en и ru). `requires()`: `media` (картинки
— `demo-wide.jpg` и `demo-square.jpg` из `MediaDemo`, через журнал; видео в демо библиотеки нет
— баннера с видео тогда нет, а файл, если сайт положит его сам, демо не ищет); `blocks` и
`pages` парой.

- `hero` (объявлено, слайдер): три баннера — два с обеими картинками и кнопками (одна кнопка
  на страницу демо, одна своим адресом), один **без русских слов** (на `/ru` не виден — решение
  7); плюс четвёртый **выключенный**.
- `promo` (объявлено, `single`): один баннер с одной кнопкой.
- При `module-pages` — страница `/banners` с тремя блоками: `hero` видом места (слайдер), `hero`
  с `random`, `promo` видом места. Главную демо не трогает (CLAUDE.md §4 про
  `Reserved::taken('')`).

### 5.10. Регистрации

CLAUDE.md §4 «Новый composer-пакет надо прописать в `php/` четыре раза» и «Новый раздел панели
регистрируется в четырёх местах»: `php/composer.json` (`require`, `autoload-dev`, карта версий),
`phpunit.xml.dist`, `phpstan.neon.dist`, `Setup\Catalogue` (`banners`),
`extra.webx.npm`/`extra.webx.panel` (`register` — `banners()`, один модуль, не спред: итог T4 у
команды), `apps/playground/src/panel/main.ts` (B2), `scripts/packages.mjs` в `webx-cms.local`
(B4), строка в `scripts/php-smoke.sh` (B4), `banners()` и `banners_layout()` в
`Doctor\Checks\Helpers`, `banners` в `SOURCE_ITEMS` автокомплита шаблона `module-blocks` (B3).

### 5.11. Тесты, которые обязательны

- `DescribesCategories`: `/collections` отдаёт `categories_label` и `categories_required` у
  баннеров и `null`/`false` у остальных источников; `CollectionField` рисует подпись источника и
  плейсхолдер «Не выбрано — блок пуст», без переключателя `filter` (vitest).
- Видимость (решение 7): баннер без слов виден на любом языке; со словами на `en` — не виден на
  `ru`; кнопка без подписи на языке выпала, баннер остался; выключенный и из корзины — не виден.
- `banners()`: каждая строка таблицы §5.3, `in()` по ключу и по id, неизвестный ключ — пусто,
  несколько мест — места по порядку, внутри `position`; `only()` в своём порядке; лимит после
  видимости.
- `banners_layout()`: пакет ← конфиг ← место ← блок, вид не из списка — вид места,
  опубликованный конфиг с одним ключом в `options` не теряет остальных.
- Места: объявленное без строки видно в списке и получает строку с первым баннером; отказ
  формы не оставляет ни баннера, ни строки места; своё место создаётся, переименовывается,
  удаляется пустым; непустое (в том числе только с корзиной) — 422 с числом; объявленное — 403.
- Форма: нет картинки — 422 `image`; видео без картинки — 422 `image`; видео картинкой — 422
  `video`; четвёртая кнопка — 422 `buttons`; вариант не из конфига — 422
  `buttons.<n>.variant`, а сохранённый и убранный из конфига — проходит; смена места — конец
  нового места.
- `wx-collection` с `source: banners` через настоящий путь записи обеих дверей (панель и
  `blocks_edit_content`); пустой выбор — пустой блок, страница 200.
- Предложенный блок рисуется на своём `sample` во всех трёх видах и видом места.
- Удалённый модуль: блок рисуется пустым, страница отвечает 200.

## 6. Слова

Все ключи `webx-banners::*` на десять языков панели заводит **B1** — серверные и нужные панели:
`module` (название раздела), `places` (названия двух объявленных мест, список мест, «Новое
место», ключ и его подсказка, «Объявлено в конфиге», переименовать, удалить, отказ «В месте
:count баннеров — сначала уберите их» — счёт в конце строки или отдельная строка на единицу,
CLAUDE.md §4 про `:count`), `banner` (список: «Новый баннер», корзина, «Восстановить»,
«Выключен», «Видео», «В этом месте пока нет баннеров»; форма: «Место», уход с несохранённым),
`screen` (подписи `banners.form`: медиа, картинка, под телефон, видео, слова, заголовок, текст,
кнопки, подпись, ссылка, вариант, «Добавить кнопку», настройки, «Включён» и их `-help`),
`errors` (картинка обязательна, видео без картинки, ссылка кнопки, вариант, ключ места, место
занято, место не пусто, объявленное место), `sources` (название источника и «Место» для
`categoriesLabel()`). В `module-admin` — `collections.field-none` (§4). B2 держит английский пол
в `messages.ts` и тест паритета; чего не хватило — дописывает в `lang/en` и пишет в итог.

## 7. Отложено

- **Ссылка на весь баннер** — клик по картинке, а не по кнопке. Попросят — поле `link`
  (`wx-link`) у баннера и обёртка `<a>` в шаблоне; с кнопками внутри ссылки это вложенные `<a>`,
  значит тогда же решать, что побеждает.
- **Видео под телефон** — отдельный файл `video_mobile`. Сейчас телефон видит постер
  (`video_on_mobile: false`) или то же видео.
- **Blade-компонент** `<x-webx-banners place="hero">` — если попросят шаблоны без блоков; вьюха
  тогда — вызов типа блока компонентом (`<x-webx-block type="banners">`), а не вторая копия
  разметки.
- **Статистика кликов и показов**, A/B.
- **Расписание** (решение 5): `starts_at`/`ends_at` и вопрос о кеше страницы — вместе.
- Параметры вида в самом блоке (переопределить интервал на одной странице).

## 8. Пошаговый план

**Выпуск один, в самом конце** (B4); до него ни PR, ни ожидания CI. Каждая сессия гонит
локальный гейт своей половины (php — `composer lint && composer analyse && composer test` из
`php/` на PHP 8.4; npm — точечно `npx vitest run … --pool=forks --poolOptions.forks.singleFork`
из корня worktree, `npx vue-tsc` в пакете, eslint и prettier на своих файлах). Полный гейт —
только B4.

**B2 идёт параллельно с B1**: npm-половина с php не пересекается по файлам, а API
зафиксирован в §5.7. B3 сливает ветку B2 и дальше идёт по основной.

| Сессия | Ветка / worktree                                    | Что                                                          |
| ------ | --------------------------------------------------- | ------------------------------------------------------------ |
| **B0** | `docs/plan-banners`                                 | спека, docs-PR                                               |
| **B1** | `feat/module-banners` / `../webx-ui-module-banners` | php: `DescribesCategories` в ядре и пакет целиком            |
| **B2** | `feat/banners-panel` / `../webx-ui-banners-panel`   | npm: `CollectionField`, панель, плейграунд; параллельно с B1 |
| **B3** | `feat/module-banners`                               | слияние B2, MCP, демо, гайд                                  |
| **B4** | `feat/module-banners`                               | выпуск, оба демо                                             |

Промпты ниже самодостаточны. Каждая сессия в конце дописывает сюда «Итог Bn» — что следующей
надо знать сверх промпта, — и строку в память `custom-modules-workflow`.

### B1 — php

```
Сессия B1 из §8 docs/architecture/WEBX_UI_MODULE_BANNERS.md: DescribesCategories в
module-admin и composer-пакет webx-ui/module-banners.

Начало: git fetch claude; git worktree add ../webx-ui-module-banners -b feat/module-banners
claude/main. PR не открывать.

Прочитать: спеку целиком (решения §2, схема §3, ядро §4, модуль §5, слова §6);
php/packages/module-team — образец почти во всём (RecordQuery, Cards, Source, форма, API, тип
блока со слайдером), его итоги T1 и T3 в WEBX_UI_MODULE_TEAM.md; module-menu — объявленные места
и ленивая строка (WEBX_UI_MODULE_MENU.md §§2(10),4,5); module-press — localized-поля внутри
wx-repeater и rowErrors; module-admin/src/Collections/*, Links/Link.php,
Screens/Types/LinkType.php; module-media/src/Screens/MediaValues.php (accept).

Сделать: DescribesCategories + categories_label/categories_required в ответе /collections
(§4, тест в module-admin, слово collections.field-none на десяти языках); пакет — composer.json
с extra.webx, провайдер, конфиг §5.4, миграции §3, Place, Places и Banner §5.1, Cards §5.2,
BannerQuery на RecordQuery (место — свой шаг по ключу в narrow(), не withCategories; §5.3),
banners() и banners_layout(), BannersSource (items() без selected()), API §5.7 ровно по формам
спеки, экран banners.form §5.6 (wx-media accept image / video, повторитель кнопок: label
localized, wx-link, wx-select варианта с опциями патчем из конфига, как соцсети у team), тип
блока banners.json §5.5 во всех трёх видах со скриптом (видео muted playsinline с data-src,
prefers-reduced-motion → постер, random в браузере), права banners.view/manage, модуль панели,
слова §6 на десять языков, doctor-хелперы, README, LICENSE, регистрации §5.10 кроме плейграунда,
сайта, smoke и автокомплита; тесты §5.11 кроме MCP и vitest; changeset на @webx-ui/php. Гейт php
на 8.4.

Если форма ответа API расходится со спекой — поправить §5.7 тем же коммитом и сказать об этом
в итоге крупно: B2 пишет мок по ней. В конце — «Итог B1» в §8, коммит, пуш в claude.
```

### B2 — npm

```
Сессия B2 из §8 docs/architecture/WEBX_UI_MODULE_BANNERS.md: @webx-ui/module-banners и
CollectionField под DescribesCategories. Параллельно с B1, php не трогать (кроме lang/en пакета,
если B1 его ещё не завёл — записать в итог).

Начало: git fetch claude; git worktree add ../webx-ui-banners-panel -b feat/banners-panel
claude/main; pnpm install --frozen-lockfile (каталог обычный); собрать dist tokens, core,
schema, module-admin. PR не открывать.

Прочитать: спеку (решения §2, ядро §4, панель §5.6, API §5.7, блок §5.5, слова §6); итог T2 в
WEBX_UI_MODULE_TEAM.md; packages/module-menu (WxListDetail мест) и packages/module-team (экран
формы, i18n, паритет); packages/module-admin/src/collections/CollectionField.vue;
apps/playground/server/panel/{menus.ts,team.ts,blocks.ts,blade.ts}.

Сделать: CollectionField — categories_label вместо «Категории», при categories_required
плейсхолдер collections.field-none вместо «Все» и без переключателя filter (§4, тест);
packages/module-banners (0.0.0) — /banners WxListDetail (места слева, баннеры справа с
перетаскиванием, миниатюра, значок видео, приглушение выключенных, своё место —
создать/переименовать/удалить пустое), экран баннера отдельным маршрутом с WxActionBar, место
селектом вне экрана (решение 13) и уходом с несохранённым (§5.6); i18n с английским полом и
тестом паритета; плейграунд — banners.ts по API §5.7, копии экрана и типа блока до появления
файлов B1 (ownOr/existsSync, как у team), banners_layout через defineFunction в blade.ts, модуль
в main.ts, страница с блоком во всех трёх видах; changeset minor (module-banners, module-admin).

Проверить в браузере на плейграунде: создать баннер с картинкой, картинкой под телефон, видео
и двумя кнопками, перенести в другое место, порядок клавиатурой на ручке, выключить, корзина,
блок с местом и без места, 375 px и тёмная тема. Плейграунд — фоновым npx vite --port 5188,
preview_start с url. В конце — «Итог B2» в §8 на своей ветке, коммит, пуш в claude.
```

### B3 — слияние, MCP, демо, доки

```
Сессия B3 из §8 docs/architecture/WEBX_UI_MODULE_BANNERS.md.

Worktree ../webx-ui-module-banners, ветка feat/module-banners. git fetch claude; git merge
claude/feat/banners-panel (спека — нужны все итоги; lang/en — объединить ключи, messages.ts
пересобрать из lang/en B1); убрать копии экрана и типа блока из плейграунда и развилки; удалить
worktree ../webx-ui-banners-panel (погасить его dev-сервер), ветку оставить.

Прочитать: §§5.8–5.10 спеки и итоги B1–B2; php/packages/module-team/src/{Mcp/*,Demo/*},
resources/demo; module-menu/src/Mcp/* (места по ключу, ссылка в menu_add_link);
module-media/src/Demo/MediaDemo.php (демо-картинки); apps/docs/guide/team.md; CLAUDE.md §4 про
mcp:start (только трубой) и про возврат webx-cms.local из копий.

Сделать: MCP §5.8 — banners_places, banners_place_create, banners_place_delete, banners_list,
banners_get, banners_create, banners_update, banners_delete, banners_reorder (медиа — ключ
файла библиотеки, неизвестный — отказ; кнопки — { label, link, variant }, вариант не из
конфига — отказ со списком; место — ключ), ресурс banners://catalog; BannersDemo §5.9;
apps/docs/guide/banners.md + сайдбар (banners(), banners_layout(), конфиг и переопределения
места, почему нет расписания и компонента, «место вместо категории» в collections.md для
авторов модулей); README обеих половин; banners в автокомплите шаблона module-blocks; тесты MCP
и демо; changeset. Гейты php и точечно npm.

Живьём инструменты — cat … | php artisan mcp:start webx на webx-cms.local в local-режиме
(копии манифестов и базы в скретчпад до, назад после, composer install). Ничего на сайте не
коммитить. В конце — «Итог B3», коммит, пуш в claude.
```

### B4 — выпуск

```
Сессия B4 из §8 docs/architecture/WEBX_UI_MODULE_BANNERS.md: выпуск module-banners, оба демо.

Прочитать: итоги B1–B3; CLAUDE.md §5 целиком (очередь мержа — мутацией enqueuePullRequest;
первая публикация npm-пакета — человеком из changeset-release/main; тег php-пакетов до
публикации); итог T5 в WEBX_UI_MODULE_TEAM.md — последний такой выпуск; память
webx-cms-local-demo-site, webx-cms-homelab-deploy, release-speed.

До релиза руками пользователя (напомнить и проверить): репозиторий-зеркало
webx-ui/module-banners на GitHub.

Сделать: погасить dev-серверы; полный гейт npm и php/ (8.4); module-banners в
scripts/php-smoke.sh и smoke против MariaDB; PR, зелёный CI, enqueuePullRequest; релизный PR;
changeset-release/main в отдельный worktree, pnpm install, dist, pnpm pack
@webx-ui/module-banners, диапазоны @webx-ui/* в тарболе; первая публикация — пользователь с
2FA, затем Trusted Publishing; мерж релизного PR; npm view поднятых пакетов, тег php-v<версия>,
Packagist. Удалить ветку feat/banners-panel.

Демо: webx-cms.local — scripts/packages.mjs, link-panel.sh, composer require, импорт и
banners() в resources/js/admin.ts руками, migrate, webx:blocks:offered --install
--module=banners, cache:clear, демо §5.9 тинкером со своим журналом, npx vite build; хомлаб — то
же в registry, npm ls @webx-ui/module-admin — одна версия, коммит и пуш в Gitea. Docs-PR:
WEBX_UI_COMPOSER_PACKAGES.md (п. 9 «Запланированы» зачеркнуть), CLAUDE.md §§2,6.

Проверить живьём на обоих: слайдер на странице демо /banners, баннер с видео (если видео
положено в библиотеку руками), баннер без русских слов на /ru не виден, выключенный не виден;
блок без места пуст и страница 200; на телефоне форма и перетаскивание — пользователь.
```

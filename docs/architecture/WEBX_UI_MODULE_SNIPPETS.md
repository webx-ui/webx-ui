# `webx-ui/module-snippets` — коды внешних сервисов в `<head>` и `<body>` — спецификация

Статус: спроектировано, не начато; решения по §15 принимает человек. Пакеты —
`php/packages/module-snippets` (namespace `WebxUi\Snippets`) и `packages/module-snippets`
(`@webx-ui/module-snippets`). Идентификатор модуля — `snippets`, в панели раздел «Коды» (Codes),
таблицы — `snippets_*`. Эталон устройства пакета — `module-faq` ([`WEBX_UI_MODULE_FAQ.md`](WEBX_UI_MODULE_FAQ.md)).
Соседние спеки: темы — [`WEBX_UI_THEMES.md`](WEBX_UI_THEMES.md) (§7.1 цепочка, §16.1 правило 21,
§17 `webx:theme:check`), виджеты — [`WEBX_UI_WIDGETS.md`](WEBX_UI_WIDGETS.md) (§9 согласие на
cookie), баннеры — [`WEBX_UI_MODULE_BANNERS.md`](WEBX_UI_MODULE_BANNERS.md) (места из конфига),
журнал — [`WEBX_UI_HISTORY.md`](WEBX_UI_HISTORY.md), экраны — [`WEBX_UI_SCREENS.md`](WEBX_UI_SCREENS.md).

Владелец сайта сам вставляет код внешнего сервиса — менеджер тегов, счётчик, онлайн-чат,
пиксель, проверку прав на домен — без правки темы и без разработчика. Код живёт в базе, выводится
в объявленном месте страницы с учётом охвата, языка, окружения и согласия посетителя на cookie.
Всё, что умеет панель, умеет и MCP.

**Произвольный HTML/JS — это XSS по определению.** Каждый, кто может сохранить код, может
исполнить что угодно в браузере любого посетителя и любого администратора, открывшего сайт.
Отсюда отдельное право, доступное по умолчанию только суперадмину (§8), прямые слова об этом в
описаниях инструментов MCP (§9) и запрет вывода в панели и превью (§5.6).

## 1. Границы

В модуле:

- запись «Код» с местом, категорией согласия, охватом, языками и флагом «и на dev» (§3);
- три встроенных места и свои места из конфига (§4);
- вывод Blade-компонентом `<x-webx-snippets place="…" />` с кешем (§5);
- раздел панели: список по местам, порядок перетаскиванием, редактор с подсветкой, черновик,
  версии, журнал (§7);
- MCP: шесть инструментов и два ресурса (§9);
- диагностика потерянных и необъявленных мест в `webx:theme:check`, `webx:doctor` и панели (§10).

Не в модуле:

- **управление местами из панели** — мест ровно столько, сколько объявил конфиг (§4);
- **песочница для кода** (iframe-изоляция, CSP по коду) — код исполняется в странице сайта, иначе
  менеджер тегов не видит страницу; ограничение — правом, а не техникой;
- **разбор и проверка кода** — только предупреждение о `<html>`/`<head>`/`<body>` (§7.4) и лимит
  длины; валидности HTML модуль не требует;
- **серверные интеграции** сервисов (Measurement Protocol, серверный GTM) — не код страницы;
- **журнал согласий** — у виджетов его нет (WIDGETS §9.2), модуль его не заводит.

## 2. Принятые решения (не переоткрывать)

1. **Места — ключи из конфига, без таблицы.** Три встроенных (`head`, `body_start`, `body_end`)
   есть всегда; свои объявляет `config/webx-snippets.php` → `places` (§4). В отличие от баннеров
   (BANNERS §2, решение 1) строки места в базе нет: у места нет ни вида, ни параметров, только
   подпись.
2. **Место выводит компонент модуля**, а не директива и не хук `finish()`: тема видит, где стоит
   место, и `webx:theme:check` может это проверить статически (§10.1).
3. **Согласие — механизм виджетов, а не свой.** Код не-`necessary` печатается так же, как
   `<x-webx-consent category="…">` (WIDGETS §9.3): в `<template data-webx-consent>`, который
   распаковывает скрипт согласия. Своего баннера, cookie и API модуль не заводит; недостающее
   дописывается в `widgets` (§11.3).
4. **Черновики, версии и журнал — механизмами `module-admin`**: `HasDraft`, `HasVersions`,
   `RecordsHistory` (§3.2). Своей истории модуль не пишет.
5. **Вне `production` коды не выводятся**, кроме отмеченных «и на dev/stage» (§5.5).
6. **В панели, её API, превью и сцене блоков коды не выводятся никогда** — по пути запроса, а не
   по виду (§5.6).
7. **Отдельное право `snippets.manage`**, никакой роли оно не выдаётся при установке (§8).
8. **Место убрано из конфига — коды остаются**, не выводятся и подсвечены в панели и доктором
   (§4.3). Удаление данных — только руками.

## 3. Запись «Код»

### 3.1 Схема

Таблица `snippets_codes` (раздел — под своим именем, CLAUDE.md §1; одна таблица, но префикс
оставляет место для `snippets_*` следующих версий — §15, вопрос 8):

```
snippets_codes
  id            bigint pk
  name          string(160)            -- для администратора, на сайт не выходит
  place         string(64) index       -- ключ места (§4); не FK: мест в базе нет
  code          mediumText             -- сырой HTML/JS; лимит проверяет приложение (§3.3)
  consent       string(16)             -- necessary | statistics | marketing (§6, §15 вопрос 1)
  only_paths    json null              -- маски «только на»: ["/blog/*", "/"]; null/[] — весь сайт
  except_paths  json null              -- маски «кроме»: ["/blog/drafts/*"]
  locales       json null              -- коды языков; null/[] — все языки сайта
  on_stage      boolean default false  -- выводить и вне production (§5.5)
  position      unsigned int default 0 -- порядок внутри места
  draft         json null              -- HasDraft: $table->draft()
  published_at  timestamp null         -- HasDraft: null — код выключен (§3.2)
  deleted_at    timestamp null         -- корзина, как у FAQ (faq_delete → корзина, faq_purge)
  timestamps
  index (place, position)
```

### 3.2 Модель и механизмы `module-admin`

`WebxUi\Snippets\Models\Snippet` — `HasDraft`, `HasVersions`, `RecordsHistory`, `SoftDeletes`
(`php/packages/module-admin/src/Versions/HasDraft.php`, `.../Versions/HasVersions.php`,
`.../History/RecordsHistory.php`):

- **Колонки — то, что на сайте; `draft` — то, что готовится.** Сохранение в панели —
  `saveDraft()`, «Опубликовать» — `publish(authorId)`: копия черновика в колонки и номерная версия.
  Откат — `restoreVersion()` в черновик и обычная публикация (`HasVersions`, «Rolling back is not
  restore»). Версия — снимок `name`, `code`, `consent`, `only_paths`, `except_paths`, `locales`,
  `on_stage`.
- **«Вкл/выкл» — это `published_at`** (`publish()` / `unpublish()`,
  `HasDraft::unpublish()` ставит `null`). Отдельного флага `enabled` нет: выключенный код — код,
  чьи колонки не на сайте; у новой записи колонки пусты, черновик полон (рецепт HasDraft «a record
  that was never published works by the same rule»). Альтернатива — §15, вопрос 4.
- **`place` и `position` — структура, применяются сразу**, мимо черновика (как дерево у страниц:
  `HasVersions::unversionedAttributes()` → `['place', 'position', ...]`). Перенос кода в другое
  место — перетаскивание в списке, а не правка черновика.
- **Журнал** — `RecordsHistory` + `HistoryTypes::register()` в провайдере, публикация —
  `History::record($snippet, 'published')`. Поле `code` в журнале — не значение целиком, а
  «изменён, N → M байт» через `historyValue()`: журнал читает каждый редактор, а 64 КБ на строку
  журнала — не история, а дамп.
- Авто-копии черновика (`Editing\DraftCopies`) и присутствие (`Editing\Presence`) — как у
  остальных черновиков, без своего кода.

### 3.3 Правила значений

- `name` — обязательно, ≤160 символов.
- `place` — объявленное место (§4); необъявленное — отказ под `place`. Существующая запись с
  осиротевшим местом сохраняется (иначе её нельзя ни выключить, ни перенести), но публикация в
  необъявленное место — отказ.
- `code` — обязательно, ≤65 536 байт UTF-8 (`strlen`, не `mb_strlen`: лимит про объём страницы).
  `mediumText`, а не `text`: `TEXT` MySQL — те же 65 535 байт, и многобайтный код упирался бы в
  колонку раньше, чем в правило.
- `consent` — одна из категорий §6; по умолчанию `necessary`.
- Маска — путь от корня сайта **без языковой приставки**, с `*` (`Str::is`, как у
  `Admin\Gate\Openings::paths()` и `Admin\Links\SiteRoutes`): `/`, `/blog/*`, `/contacts`.
  Начинается с `/`; без схемы, хоста и `?`. До 50 масок в каждом списке.
- `locales` — коды из `Localization\Locales::codes()`; неизвестный — отказ.

## 4. Места

### 4.1 Встроенные

| Ключ         | Где в `components/layout` `theme-default`                    | Зачем                                  |
| ------------ | ------------------------------------------------------------ | -------------------------------------- |
| `head`       | сразу после `<meta charset>` и `<meta name=viewport>`        | менеджер тегов, проверки прав на домен |
| `body_start` | сразу после `<body …>`, до ссылки «Skip to content»          | `<noscript>`-часть менеджера тегов     |
| `body_end`   | после `@stack('scripts')`, последней строкой перед `</body>` | чаты, виджеты обратного звонка         |

Встроенные места нельзя убрать или переименовать из конфига: `Places` (§4.2) добавляет их всегда,
ключ встроенного места в `places` конфига переопределяет только подпись.

**Порядок в `<head>` сейчас** (`php/packages/theme-default/views/components/layout.blade.php`):
`<meta charset>`, `<meta viewport>`, `@webxTheme` (токены → шрифты → `HeadPart` виджетов →
CSS модулей → CSS слоёв, `php/packages/themes/src/ThemeHead.php`), затем слот `head` модуля или
`@webxSeo` + `@webxBlocks`, затем `@stack('head')`. `@webxSeo` (`SeoServiceProvider::registerRendering()`,
`Seo::head()`) печатает `<title>`, мету, canonical и `hreflang` — в слоте модуля или в запасной
ветке лэйаута, то есть **после** `@webxTheme`. Место `head` встаёт выше всех трёх: код менеджера
тегов не ждёт стилей (внешняя таблица стилей перед скриптом задерживает его исполнение до CSSOM).

**Ловушка Consent Mode.** `consent default` Google печатают виджеты: `Widgets::head()` отдаёт
маркер `<!--webx-widgets-->` внутри `@webxTheme`, и `Widgets::finish()` (по `RequestHandled`,
`WidgetsServiceProvider`) кладёт на место маркера `Consent::googleDefault()`
(`php/packages/widgets/src/Widgets.php`, `finish()`). Default обязан стоять **раньше** тега
Google (WIDGETS §9.3), а место `head` — выше `@webxTheme`. Без правки виджетов менеджер тегов
из места `head` стартует до `consent default` — правка в §11.3, п. 1.

### 4.2 Свои места — через конфиг

```php
// config/webx-snippets.php
return [
    'places' => [
        // Своё место: ключ → подпись, строка или ключ перевода.
        'before-footer' => 'theme::snippets.before_footer',
        'product-card'  => 'Product card, under the price',

        // Встроенное можно только переподписать; убрать или переименовать — нельзя.
        // 'head' => 'Head, as high as possible',
    ],
];
```

- Ключ — `[a-z0-9][a-z0-9_-]{0,63}` (как ключ места баннеров, `banner_places.key string(64)`).
- Подпись — строка или ключ перевода; переводится в языке панели (`__()` при отдаче экрана).
  Подписи встроенных — словарь модуля `snippets::places.head` и т. п.
- `WebxUi\Snippets\Places` — объединение встроенных и конфига: `all()`, `has($key)`,
  `label($key)`, `builtIn($key)`. Читает конфиг в момент вызова, без кеша: `config:cache` уже
  кеширует.
- Тема или сайт выводит своё место тем же компонентом: `<x-webx-snippets place="before-footer" />`.
- Интерфейса управления местами нет; в форме кода «Место» — `wx-select` из `Places::all()`
  (§7.3). Конфиг статичен на процесс, поэтому опции можно класть патчем экрана при `boot()` —
  ограничение баннеров (BANNERS §2, решение 9: «живой список туда не ложится») здесь не мешает.

### 4.3 Место объявлено, но не выводится; место убрано, а коды остались

- **Объявлено, не выводится** — тема или сайт не печатает компонент с этим ключом. Коды места
  молча не попадают на страницу. Предупреждение `webx:theme:check` и `webx:doctor` (§10).
- **Убрано из конфига, коды остались** (осиротевшее место) — коды не удаляются и не выводятся
  (компонент с таким ключом печатает пусто, `Places::has()` ложно). В панели — отдельная группа
  «Место не объявлено: `<key>`» с `WxAlert` и переносом кодов в объявленное место; в `webx:doctor`
  — предупреждение «N кодов в необъявленном месте `<key>`» (§10.2). MCP `snippets_list` отдаёт
  их с `place_declared: false`.

## 5. Вывод на сайте

### 5.1 Компонент

`<x-webx-snippets place="head" />` — класс `WebxUi\Snippets\View\Components\Place`, зарегистрирован
как `webx-snippets` (`Blade::component`). Печатает коды места по `position`, каждый после фильтров
§5.2–§5.6, каждый — как есть (`necessary`) или в обёртке согласия (§6). Пустое место — пустая
строка, без маркеров и пробелов. Обёртки вокруг кодов нет: код в `<head>` не может стоять в
`<div>`.

**В `theme-default` — по имени и за `view()->exists()`**, как `webx-seo::faq` в том же лэйауте:
`theme-default` не требует `module-snippets`, а `<x-webx-snippets>` без зарегистрированного
компонента — ошибка компиляции Blade. Форма для `theme-default` — §11.1; варианты — §15,
вопрос 3.

### 5.2 Охват

Код выводится, если путь запроса без языковой приставки (`SiteUrl::prefix()` из `routing`
снимается с начала пути) подходит хотя бы под одну маску `only_paths` (или список пуст) и ни под
одну маску `except_paths`. Сравнение — `Str::is`, без учёта `?query`. Страницы ошибок сайта
(404 по цепочке, `themes/src/ErrorPages.php`) охват видят по своему пути, как любая страница.

### 5.3 Языки

`locales` пусто — все языки; иначе код выводится, только если `app()->getLocale()` в списке.
Язык, которого у сайта больше нет, в записи не мешает и в панели подсвечивается.

### 5.4 Порядок

Внутри места — `position`, затем `id`. Между местами порядок задаёт тема. Внутри одного кода
порядок скриптов — как в разметке; для кода, ждущего согласия, — то же после распаковки
(§11.3, п. 2).

### 5.5 Окружение

- «Прод» — `app()->environment('production')` (`APP_ENV`). Скелет сайта приходит с
  `APP_ENV=local` (`php/site/.env.example`); сайты платформы, подключённые к MCP, отвечают
  `production` в `site_info` (`mcp/src/Server/Site.php::environment()`, по `app.env`). Тот же
  признак уже читают `module-audit` (`Checks/Config/Environment.php`, находка `config.env`) и
  `module-admin` (`Console/SnapshotRestoreCommand.php`).
- Вне `production` выводятся только коды с `on_stage = true`. Остальные не печатаются вовсе — не
  инертными, а никак: счётчик на dev-копии портит статистику прода.
- Стенд, который работает с `APP_ENV=production` (бывает у копий сайта на чужом домене), коды
  выведет. Аудит отличает стенд по хосту (`module-audit/src/Hosts/HostClassifier.php::looksLikeStand()`,
  AUDIT §5.6 «Стенд разработки»), но `module-snippets` от аудита не зависит — §15, вопрос 5.

### 5.6 Где не выводится никогда

По пути запроса, до любых фильтров (`WebxUi\Snippets\Exclusions`):

- панель и её JSON — под `config('webx-admin.path')` и `config('webx-admin.api_path')`
  (так же их узнаёт `Admin\Gate\Openings::under()`);
- превью и сцена блоков — под `config('webx-blocks.preview.path')` (по умолчанию `_preview`):
  `/_preview/{type}/{id}` (`Blocks\Http\Controllers\PreviewController`), `/_preview/region/{name}`
  (`RegionPreviewController`) и `/_preview/block-stage` (`StageController` — лэйаут сайта, на
  котором рисует редактор блоков); плюс запасной признак `Blocks\Preview\PreviewGrant::of($request)
!== null` для превью, смонтированного иначе;
- **страницы ошибок панели**: 404 по адресу под `/cms/…` рисуется лэйаутом сайта
  (`ErrorPages::render()` отдаёт `errors.404` цепочки любому HTML-запросу), поэтому исключение —
  по пути, а не по виду: вид у него тот же, что у 404 сайта;
- ответы не-HTML и рендер вне запроса (письма, `blocks_render` MCP, `Renderer::preview(true)`):
  компонент без текущего запроса печатает пусто.

Пути читаются в момент запроса (как `Openings`), а не при загрузке. Пустой путь панели — «ничего»,
а не «всё» (то же правило `Openings::under()`).

### 5.7 Кеш

- Ключ `webx-snippets.codes` — все опубликованные коды объявленных мест, сгруппированные по месту,
  с полями §3.1 без `draft`; хранилище — кеш приложения (на платформе — Redis с `REDIS_PREFIX`
  сайта), без срока.
- Сброс — `saved`, `deleted`, `restored`, `forceDeleted` модели и перестановка (reorder пишет
  пачкой — сброс один раз после транзакции). Публикация и выключение — это `save()`, ловятся тем
  же событием.
- Фильтры §5.2–§5.6 — на запрос, в PHP, по закешированному списку: путь и язык у каждого запроса
  свои, кешировать их сочетания — раздуть кеш без выигрыша.
- Смена конфига мест кеш не трогает: необъявленное место отсекается при чтении.

## 6. Согласие на cookie

### 6.1 Что есть в `widgets`

Факты из `php/packages/widgets` (WIDGETS §9):

- Категории — `Consent::CATEGORIES` = `necessary`, `preferences`, `statistics`, `marketing`,
  `media` (`src/Consent.php`). **Категории `analytics` нет** — аналитика у виджетов зовётся
  `statistics` и в Consent Mode идёт в `analytics_storage`.
- Сервер знает ответ: `Consent::has($category)` читает cookie `webx_consent`; при выключенном
  баннере `has()` — всегда `true` («a site that turned it off says there is nothing to agree to»).
- `<x-webx-consent category="…">` (`src/View/Components/ConsentGate.php`,
  `resources/views/components/consent.blade.php`): с согласием — код как есть, без — в
  `<template data-webx-consent="…">`.
- Скрипт `resources/js/consent.js`: `release()` распаковывает `template` (скрипты внутри
  пересоздаются `revive()`, чтобы исполнились), включает `script[type=text/plain][data-webx-consent]`
  и `iframe[data-webx-consent][data-src]`; новое согласие — распаковка без перезагрузки и событие
  `webx:consent`; **отзыв — `location.reload()`** («a script that ran cannot be unloaded»).
- Баннер показывает категорию, если её просит страница (`data-webx-consent="<категория>"` в
  отрисованном HTML) — `template` кода это даёт сам.
- Скрипт согласия и баннер кладёт `Widgets::finish()` только на страницу с маркером `@webxTheme`
  (`$styled`) и опубликованным `consent.js` (`webx:theme:sync`).

### 6.2 Как выводит модуль

- `necessary` — код как есть, сразу.
- Остальные — через тот же путь, что `ConsentGate`: согласие есть (или баннер выключен) — как
  есть; нет — `<template data-webx-consent="<категория>">код</template>`. Модуль зовёт вид
  `webx-widgets::components.consent` (или компонент `ConsentGate`), а не повторяет разметку.
- `<template>` допустим и в `<head>`, и в `<body>`; содержимое шаблона браузер не исполняет и не
  грузит — ни `<script src>`, ни `<img>`, ни `<iframe>`. После распаковки грузится всё: внешние
  `src`, `<iframe>`, пиксели `<img>`.
- `<noscript>` в коде, ждущем согласия: без JS шаблон не распакуется никогда, и `<noscript>`-часть
  менеджера тегов не покажется — это правильно: без JS нет и баннера, согласия не дали. В коде
  `necessary` `<noscript>` работает как обычно.
- Отзыв согласия — перезагрузка страницы виджетами; повторное согласие — распаковка без
  перезагрузки. Своего кода у модуля на это нет.

### 6.3 Чего не хватает — см. §11.3

1. Consent Mode default — позже места `head` (§4.1).
2. `revive()` теряет `type` скриптов из `template`: тип берётся только из `data-webx-type`, а у
   скрипта из шаблона атрибута нет — `<script type="module">` станет классическим, а
   `<script type="application/ld+json">` исполнится как JS и упадёт.
3. Порядок внутри кода: внешние скрипты получают `async = false`, но встроенный скрипт после
   внешнего исполняется сразу при вставке, не дожидаясь загрузки внешнего. Типичный код чата
   (`<script src="…/widget.js"></script><script>Widget.init(…)</script>`) из шаблона ломается.
4. Сайт без темы (`@webxWidgets` без `@webxTheme`) скрипта согласия не получает: коды
   не-`necessary` там не распакуются никогда. Модулю это не чинить, а сказать (§10.2).
5. `document.write` в коде, распакованном после загрузки страницы, не работает (браузер
   игнорирует или стирает документ). Не чинится — строка в гайде и подсказка в редакторе.

## 7. Панель

### 7.1 Раздел

Раздел «Коды» (Codes), иконка — `code`, группа — «Настройки» (или своя; §15, вопрос 7). Модуль
`WebxUi\Snippets\Panel\SnippetsModule extends AbstractModule`, `permissions()` —
`['snippets.manage']` (§8). Регистрация — `Setup\Catalogue` (§12).

### 7.2 Список

- Сгруппирован по местам в порядке `Places::all()` (встроенные первыми), в группе — по `position`.
  Строка: название, категория согласия (бейдж), охват кратко («весь сайт» / «только на 2 адреса» /
  «кроме 1»), языки, «и на dev», переключатель вкл/выкл (публикация/выключение), признак
  неопубликованного черновика.
- Перетаскивание — `vue-draggable-plus` внутри группы и между группами (смена места, §3.2);
  сохраняется место целиком, нумерация с нуля (как `PlaceEditor::reorder` баннеров).
- Осиротевшие места — группы в конце с `WxAlert` «место не объявлено в config/webx-snippets.php,
  коды не выводятся» (§4.3).
- Объявленное, но не встреченное на страницах место — подсказка у заголовка группы (§10.3).
- Вне `production` — `WxAlert` вверху: «окружение `<env>`: на сайте только коды с „и на dev"».

### 7.3 Форма (экран `snippets.form`)

Экран JSON по WEBX_UI_SCREENS.md:

- `name` — `wx-input`;
- `place` — `wx-select`, опции — `Places::all()` патчем экрана при `boot()` (§4.2); значение
  осиротевшего места показывается с пометкой «не объявлено»;
- `code` — **`wx-code-editor`** с `language: "html"`: компонент ядра есть
  (`packages/core/src/components/CodeEditor`, CodeMirror, `@codemirror/lang-html` с вложенными
  JS и CSS), тип поля экрана есть (SCREENS, `wx-code-editor` → string). Нового компонента не нужно;
- `consent` — `wx-segmented` / `wx-radio-group`: «Необходимые» / «Аналитика» / «Маркетинг»;
- `only_paths`, `except_paths` — `wx-tags-input` (список строк), подсказка с примером `/blog/*`;
- `locales` — `wx-checkbox-group` из языков сайта, пусто — «все»;
- `on_stage` — `wx-switch` «Показывать и на dev/stage»;
- узлы черновика, версий и журнала — те же, что у страниц и услуг (`wx-history`,
  WEBX_UI_HISTORY.md).

### 7.4 Предупреждение о `<html>`, `<head>`, `<body>`

При сохранении код ищется регуляркой на открывающие и закрывающие теги `html`, `head`, `body`
(вне комментариев не разбирается — это подсказка, не парсер). Найдено — сохранение проходит, а
ответ несёт `warnings`; панель показывает `WxAlert` над редактором и тост «Код содержит `<head>`:
вставляйте только содержимое, которое сервис просит поставить внутрь». В MCP — то же полем
`warnings` в ответе `create`/`update` (§9.3), не отказом.

### 7.5 API панели

Под `config('webx-admin.api_path')/snippets`, все — `cms.can:snippets.manage`:
`GET /` (список по местам + `places` + `environment`), `GET {id}`, `POST /`, `PUT {id}`
(черновик), `POST {id}/publish`, `POST {id}/unpublish`, `POST {id}/discard`, `DELETE {id}`
(корзина), `POST {id}/restore`, `DELETE {id}/purge`, `POST reorder` (`{ place, ids }`),
`GET {id}/versions`, `POST {id}/versions/{n}/restore`. Отказы валидации — 422 под полем,
неизвестное место — под `place`.

## 8. Права

- Одно право `snippets.manage` (просмотр без правки смысла не имеет: чтение кода — это и чтение
  чужих ключей сервисов). `CmsUser::hasPermission()` пропускает суперадмина всегда
  (`module-auth/src/Models/CmsUser.php`), а ролей с правами по умолчанию `module-auth` не
  заводит — значит, **после установки раздел видит только суперадмин**, ничего дописывать не надо.
- Тест держит это: свежая роль «Редактор» с правами всех модулей, выданными через «выбрать всё»
  в форме роли, `snippets.manage` не получает — §15, вопрос 6.
- В описании права (словарь `snippets::permissions.manage`) прямо: «Даёт исполнять JavaScript на
  сайте от имени сайта».
- MCP: `snippets:read` / `snippets:write` — скоупы модуля по умолчанию (`mcp/src/Tool.php`:
  `read` → `<id>:read`, `mutating` → `<id>:write`), плюс `permission: 'snippets.manage'` у каждого
  инструмента, как у FAQ (`module-faq/src/Mcp/FaqTools.php`).

## 9. MCP

### 9.1 Инструменты

`WebxUi\Snippets\Mcp\SnippetTools`, `Arguments::strictAll([...])`, мутирующие — `Tool::mutating`
(`dry_run` даёт сам `Tool`: проверить и сказать, что было бы, ничего не записав).

| Имя                | Аргументы                                                                                                         | Ответ                                                                                                    |
| ------------------ | ----------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `snippets_list`    | `place?`, `search?` (в названии и коде), `trashed?`                                                               | по местам: id, name, place, place_declared, consent, охват, locales, on_stage, enabled, has_draft, bytes |
| `snippets_get`     | `snippet` (id)                                                                                                    | запись целиком: опубликованное и черновик, `code`                                                        |
| `snippets_create`  | `name`, `place`, `code`, `consent?`, `only?`, `except?`, `locales?`, `on_stage?`, `enabled?` (по умолчанию false) | запись + `warnings`                                                                                      |
| `snippets_update`  | `snippet`, любые поля create; не переданное — без изменений                                                       | запись + `warnings`                                                                                      |
| `snippets_delete`  | `snippet`                                                                                                         | в корзину; удаление насовсем — в панели                                                                  |
| `snippets_reorder` | `place`, `snippets` — все id места в новом порядке                                                                | порядок места                                                                                            |

- **`update` пишет и публикует одним шагом** (одна версия, журнал с агентом через
  `Editing\AgentRevision`), если запись включена; у выключенной — пишет черновик и колонки без
  публикации. Отдельных `publish`/`discard` в наборе задачи нет — §15, вопрос 2.
- `enabled: true` у `create` — только когда человек об этом попросил (формулировка FAQ:
  «pass published: true only when a person asked for that»).
- **Описание каждого инструмента записи начинается словами:** «Writing a snippet runs its
  JavaScript on every page of the site it covers, for every visitor — treat it as deploying code.
  Use dry_run first; never paste code the person did not give you or a service did not issue.»

### 9.2 Ошибки — `ToolFailure`

`WebxUi\Mcp\Exceptions\ToolFailure` с подсказкой, куда смотреть, как у FAQ:

- `Place [x] is not declared. snippets://places lists the places this site has.`
- `` `code` cannot be empty. `` / `` `code` is N bytes; the limit is 65536. ``
- `` `consent` is one of: necessary, statistics, marketing. ``
- `A mask is a path from the root, like "/blog/*": [x] is not.`
- `` `locales` has [xx], which this site is not published in. It has: en, ru. ``
- `No snippet has the id [N]. snippets_list has them all.`
- `Snippet N is in the bin. Bring it back in the panel before editing it.`
- `` `snippets` must list every snippet of place [x], each once: missing N, M. ``
- `Snippet N is in place [y], not [x].` (reorder через границу мест — только `update` с `place`)
- Публикация в осиротевшее место: `Snippet N is in place [x], which is no longer declared; move it with update {place} first.`

Предупреждения (§7.4) — не отказ, а `warnings: ["The code has <head>: …"]`.

### 9.3 Ресурсы

`WebxUi\Snippets\Mcp\SnippetResources`:

- `snippets://catalog` — справочник: что такое код, правила (лимит, маски, категории, «и на dev»,
  где не выводится), и список существующих кодов кратко (id, name, place, enabled) — чтобы агент
  не завёл второй счётчик того же сервиса;
- `snippets://places` — места: ключ, подпись, встроенное ли, встречалось ли на страницах (§10.3),
  число кодов; осиротевшие — отдельным списком.

`webx:mcp-tools` показывает инструменты без своего кода — реестр MCP.

## 10. Диагностика

### 10.1 `webx:theme:check`

**Что сейчас.** `themes/src/Console/CheckCommand.php` → `Checks\ChainCheck::run()` читает
файлы слоёв (токены, CSS, блоки, иконки, ассеты, слова, шрифты, письма) и **точки расширения
для чужих проверок не имеет**: `SyncStep` с тегом `webx-themes.sync` есть только у sync
(`Contracts/SyncStep.php`). Правило 21 (THEMES §16.1: лэйаут держит `@webxTheme`, `@webxSeo`,
`@webxBlocks` и зоны) помечено «check», но `ChainCheck` лэйаут не читает.

**Предлагается.** В `themes` — контракт `Contracts\ThemeCheckPart` с тегом `webx-themes.check`:
`check(ThemeChain $chain, array $viewPaths): list<{file, line, message}>` — находки идут в общий
вывод и код возврата. `module-snippets` регистрирует `Checks\PlacesCheck`.

Как искать место — два способа:

| Способ                                | Как                                                                                                                                                                                                                       | Плюсы                                                                        | Минусы                                                                                                                   |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| **Статический поиск** (рекомендуется) | по всем `*.blade.php` видов сайта (`resources/views`) и слоёв цепочки ищется `<x-webx-snippets … place="<key>"` и `component="webx-snippets" … place="<key>"`; встроенные — обязательно, свои — каждое из `Places::all()` | без базы, маршрутов и запроса; работает в CI и на теме по пути; говорит файл | не видит `:place="$var"` и место, выведенное из PHP; найдёт компонент в виде, который никто не подключает                |
| Рендер страницы                       | внутренний запрос главной, поиск маркера места в HTML                                                                                                                                                                     | видит то, что реально напечатано                                             | нужна база и данные; одна страница не скажет за все; `theme:check` по пути работает без установки — там рендерить нечего |

Находки (предупреждения, но с кодом 1 — как остальные находки check, §17):

- `components/layout.blade.php (<слой>) — no <x-webx-snippets place="head">: codes of the place
"head" are never printed (SNIPPETS §4.1)` — для каждого встроенного места, если его нет ни в
  одном виде цепочки и сайта;
- `config/webx-snippets.php — place "before-footer" is declared but no view prints it` — свои;
- динамический `:place=` — не находка, а строка «place set by an expression, not checked».

Без `module-snippets` проверки нет (тег никто не регистрирует). Правило 21 THEMES §16.1
дополняется: «и три места кодов, если стоит `module-snippets`».

### 10.2 `webx:doctor`

`Doctor\Checks\SnippetPlaces` в `module-snippets`, регистрация — `callAfterResolving(DoctorChecks::class, …)`,
как `FontsCheck` тем (`themes/src/ThemeServiceProvider.php`, `Doctor/FontsCheck.php`):

- объявленное место, которого нет в видах (тот же статический поиск, §10.1) — `warn`;
- коды в необъявленном месте — `warn`: «3 snippets are in place "promo", which config/webx-snippets.php no longer declares — they are not printed»;
- есть включённые коды не-`necessary`, а скрипта согласия на сайте не будет (нет темы в
  `webx-themes.theme`, или `consent.js` не опубликован) — `warn` (§6.3, п. 4);
- окружение не `production` и есть включённые коды без «и на dev» — `info`: почему их не видно.

### 10.3 Встречалось ли место на страницах

Компонент, напечатав место, отмечает ключ в кеше `webx-snippets.seen` (как кеш
`webx-widgets.consent.seen` в `Consent` — «the categories something on the site has asked for»): одна запись на ключ,
обновляется не чаще раза в сутки. Панель и `snippets://places` показывают «не встречалось на
страницах» у объявленного места без отметки — это ловит и `:place="$var"`, и место из PHP,
которые статический поиск пропускает. Доктор этот признак не использует: сразу после деплоя
отметок ещё нет.

## 11. Что меняется в чужих пакетах

### 11.1 `theme-default`

`views/components/layout.blade.php` — три места, по имени и за `view()->exists()` (как
`webx-seo::faq` там же):

```blade
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@if (view()->exists('webx-snippets::place'))<x-dynamic-component component="webx-snippets" place="head" />@endif

@webxTheme
…
<body class="site">
@if (view()->exists('webx-snippets::place'))<x-dynamic-component component="webx-snippets" place="body_start" />@endif
<a class="site-skip" …>
…
@stack('scripts')
@if (view()->exists('webx-snippets::place'))<x-dynamic-component component="webx-snippets" place="body_end" />@endif
</body>
```

Статический поиск §10.1 узнаёт обе формы. Вид `webx-snippets::place` модуль кладёт ради этой
проверки (компонент-класс рисует его). `errors/404.blade.php` и `mail/layout.blade.php` не
меняются: 404 идёт через `<x-layout>`, письмам коды не нужны.

### 11.2 `themes`

- `Contracts\ThemeCheckPart` + тег `webx-themes.check`, вызов в `CheckCommand::handle()` после
  `ChainCheck` (§10.1). Без зарегистрированных частей вывод не меняется.
- THEMES §16.1, правило 21 и §17 — дописать места кодов.

### 11.3 `widgets`

1. **Consent Mode default раньше места `head`.** Компонент места `head` печатает перед кодами
   маркер `<!--webx-widgets:early-->` (константа `Widgets::EARLY`); `Widgets::finish()` кладёт
   `googleDefault()` на этот маркер, если он есть, иначе — как сейчас, на `MARKER`. Сайт без
   модуля не меняется. Варианты — §15, вопрос 9.
2. **`revive()` сохраняет тип скрипта из `template`**: для скриптов распакованного шаблона
   атрибут `type` не снимается (снимается только у `script[type=text/plain]`, где настоящий тип —
   `data-webx-type`). JSON-LD и прочие не-JS типы остаются данными.
3. **Порядок внутри распакованного шаблона**: скрипты вставляются по одному, следующий — после
   `load`/`error` предыдущего внешнего; встроенные между ними ждут так же. Тест в
   `resources/js/consent.test.js`: «внешний + встроенный init» исполняются по порядку.
4. Категорий, API активации и отзыва достаточно (§6.1) — новых не нужно, если по §15, вопрос 1
   принята рекомендация.

Changeset — `@webx-ui/php` (общий для composer-пакетов) и, если тронут JS-бандл виджетов, он
собирается в `dist/` пакета `webx-ui/widgets` — отдельного npm-пакета у него нет.

### 11.4 `module-admin`

Нового механизма не нужно: `HasDraft`, `HasVersions`, `RecordsHistory`, `DoctorChecks`,
`Setup\Catalogue`, `ProvidesDemo` — как есть. Меняется только `Setup\Catalogue` (строка модуля).

### 11.5 Сайты со своим лэйаутом

Ничего не ломается: компонент добавочный, без модуля не вызывается. Сайт, перекрывший
`components/layout` (локальная тема или `resources/views/components/layout.blade.php`), мест не
получит, пока не впишет их; до тех пор коды не выводятся, а `webx:theme:check` и `webx:doctor`
это говорят (§10). Гайд `apps/docs/guide/snippets.md` даёт три строки для своего лэйаута.

## 12. Регистрации

- `php/composer.json` — `require`, `autoload-dev.psr-4`; `php/phpunit.xml.dist`;
  `php/phpstan.neon.dist`; версия path-репозитория — `node scripts/sync-php-version.mjs`.
- `module-admin/src/Setup/Catalogue.php` — `'snippets' => ['package' => 'webx-ui/module-snippets',
'npm' => '@webx-ui/module-snippets', …]`, не по умолчанию для нового сайта.
- `composer.json` пакета — `extra.webx.npm`, `extra.webx.panel`.
- Плейграунд — `apps/playground/src/panel/main.ts` + фикстура в `apps/playground/server/panel/`.
- `scripts/packages.mjs` репозитория демо-сайта.
- `RouteType` нет — `SiteBar::entity()` не нужен (`SiteBarCoverageTest` не задет).
- `AGENTS.md` пакета ≤150 строк (`AgentDocsCoverageTest`), гайд `apps/docs/guide/snippets.md`.

## 13. Демо

`WebxUi\Snippets\Demo\SnippetsDemo` (`ProvidesDemo`, как `module-faq/src/Demo/FaqDemo.php`):
один выключенный код в `body_end`, `consent: statistics`, без реальных идентификаторов сервисов:

```html
<!-- Example: an analytics tag. Replace with the code your service gives you. -->
<script>
  console.info('webx snippets: example code, not connected to any service')
</script>
```

## 14. Тесты

- Вывод: встроенные места в `theme-default`, своё место из конфига, порядок, пустое место — пусто.
- Охват: `only`/`except`, приставка языка снимается, `?query` не мешает; языки.
- Окружение: `local` — только `on_stage`; `production` — все.
- Согласие: `necessary` как есть; `statistics` без cookie — `template`, с cookie — как есть;
  баннер выключен — как есть.
- Исключения: `/cms/…`, `/api/cms/…`, `/_preview/…`, сцена блоков, 404 под `/cms/…`.
- Кеш: сохранение, публикация, выключение, перестановка, удаление сбрасывают.
- Черновик/версии: правка не видна до публикации, откат через черновик.
- MCP: каждый инструмент, `dry_run` не пишет, `ToolFailure` на каждый отказ §9.2, `warnings`.
- Необъявленное место: не выводится, видно в `snippets_list`, доктор предупреждает.
- `PlacesCheck`: лэйаут без места — находка, обе формы компонента находятся.
- Права: без `snippets.manage` — 403 в API и отказ в MCP; суперадмин — да.
- JS виджетов (§11.3): тип из шаблона сохраняется, порядок внешнего и встроенного.

## 15. Открытые вопросы

1. **Категории согласия: `analytics` из задачи или `statistics` виджетов?**
   а) хранить имена виджетов (`necessary`, `statistics`, `marketing`), в интерфейсе подписать
   `statistics` как «Аналитика»; б) завести в `widgets` синоним `analytics` → `statistics`;
   в) дать все пять категорий виджетов, включая `preferences` и `media`.
   **Рекомендация: а**, плюс `media` четвёртой (встраиваемые карты и видео из кода — это `media`,
   и баннер уже умеет её предлагать). Синоним (б) — два имени одного в cookie и Consent Mode.
2. **MCP: публикация в `update` или отдельные `snippets_publish`/`snippets_discard`?**
   а) `update` включённой записи публикует сразу (§9.1); б) `update` пишет черновик, плюс два
   инструмента, как у страниц (`pages_publish`, `pages_discard`).
   **Рекомендация: б** — черновик — механизм задачи, и агент, правящий живой код, должен иметь
   шаг «посмотреть перед выходом». Набор инструментов тогда восемь, а не шесть.
3. **Как `theme-default` ставит места без зависимости от модуля?**
   а) `x-dynamic-component` за `view()->exists()` (§11.1, как `webx-seo::faq`); б) пустой
   компонент `webx-snippets` в `theme-default`, который модуль перекрывает; в) `theme-default`
   требует `module-snippets`.
   **Рекомендация: а** — проверенный приём того же файла; б зависит от порядка регистрации
   провайдеров, в — модуль на каждом сайте.
4. **«Вкл/выкл» — `published_at` или отдельный флаг `enabled`?**
   а) `published_at` (§3.2): выключить — `unpublish()`, черновик живёт отдельно; б) флаг
   `enabled` рядом с публикацией: опубликованный, но выключенный код.
   **Рекомендация: а** — одно состояние «на сайте», а не два, которые надо объяснять.
5. **«Прод» — только `APP_ENV` или ещё хост?**
   а) только `app()->environment('production')` (§5.5); б) плюс список окружений в конфиге
   (`webx-snippets.environments`, по умолчанию `['production']`); в) плюс признак стенда по хосту,
   как у аудита (`HostClassifier::looksLikeStand()`) — вынести его из `module-audit` в общий пакет.
   **Рекомендация: а + б** — просто и настраиваемо; в — отдельная задача.
6. **Защита от выдачи права «по невнимательности».** Нужна ли в форме роли пометка «опасное»
   у `snippets.manage` и исключение его из «выбрать всё»?
   **Рекомендация: да**, если форма роли умеет группы прав; иначе — только текст права (§8).
7. **Группа в меню панели:** «Настройки» рядом с SEO и cookie или верхний уровень?
   **Рекомендация: «Настройки»** — вещь редкая и техническая.
8. **Имя таблицы:** `snippets_codes` (§3.1, буквально «`snippets_*`») или `snippets` (как
   `banners`)? **Рекомендация: `snippets_codes`** — так сказано в задаче; спорить не о чем.
9. **Где Consent Mode default относительно места `head`** (§4.1, §11.3 п. 1):
   а) маркер `Widgets::EARLY`, который печатает компонент места `head`; б) место `head` ниже
   `@webxTheme` — без правки виджетов, но код менеджера тегов ждёт CSS; в) default — сразу после
   `<meta charset>` всегда, через `finish()` по регулярке.
   **Рекомендация: а** — явный шов, сайт без модуля не меняется.
10. **Аудит:** регистрировать коды как источник контента `module-audit`, чтобы находка
    `widgets.before_consent` («счётчик грузится сразу») на коде `necessary` вела в форму кода и
    исправлялась сменой категории? **Рекомендация: да, вторым заходом** — не входит в критерии.
11. **Вывод для вошедшего администратора** (панель на сайте, SITE_BAR): выводить коды как всем
    или нет (счётчики считают визиты редакторов)? **Рекомендация: как всем** — иначе
    администратор не видит, что сломал; отсев своих визитов — дело сервиса аналитики.

## 16. Выпуск

Не начато. Этапы задачи: спека → пакет PHP (модель, вывод, MCP, доктор, правки чужих пакетов)
→ панель и npm-пакет → гайд, плейграунд, демо-сайт. Первую версию `@webx-ui/module-snippets` в npm
публикует человек (`docs/pitfalls/release-and-ci.md`).

# `webx-ui/module-snippets` — коды внешних сервисов в `<head>` и `<body>` — спецификация

Статус: спроектировано, решения по §15 приняты человеком 16.10.2026; PHP-ядро — этап 2 задачи. Пакеты —
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
- раздел панели: список по местам, порядок перетаскиванием, редактор с подсветкой, вкл/выкл и
  журнал с полным текстом кода «было/стало» (§7);
- MCP: шесть инструментов и два ресурса (§9);
- диагностика потерянных и необъявленных мест в `webx:theme:check`, `webx:doctor` и панели (§10).

Не в модуле:

- **управление местами из панели** — мест ровно столько, сколько объявил конфиг (§4);
- **песочница для кода** (iframe-изоляция, CSP по коду) — код исполняется в странице сайта, иначе
  менеджер тегов не видит страницу; ограничение — правом, а не техникой;
- **разбор и проверка кода** — только предупреждение о `<html>`/`<head>`/`<body>` (§7.4) и лимит
  длины; валидности HTML модуль не требует;
- **серверные интеграции** сервисов (Measurement Protocol, серверный GTM) — не код страницы;
- **журнал согласий** — у виджетов его нет (WIDGETS §9.2), модуль его не заводит;
- **черновики, версии и кнопка отката** — «Сохранить» сразу на сайте; старый код возвращают руками
  из журнала (§3.2);
- **коды как источник контента аудита** — не в этой задаче (§15, решение 10).

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
4. **Без черновиков и версий; журнал — механизмом `module-admin`.** Ни `HasDraft`, ни
   `HasVersions`: сохранение сразу на сайте, вкл/выкл — флаг `enabled`. Журнал — `RecordsHistory`
   с полным текстом кода до и после (точка расширения `historyKeepsFull()`, §3.2). Своей истории
   модуль не пишет.
5. **Вне `production` коды не выводятся**, кроме отмеченных «и на dev/stage» (§5.5).
6. **В панели, её API, превью и сцене блоков коды не выводятся никогда** — по пути запроса, а не
   по виду (§5.6).
7. **Отдельное право `snippets.manage`**, никакой роли оно не выдаётся при установке (§8).
8. **Место убрано из конфига — коды остаются**, не выводятся и подсвечены в панели и доктором
   (§4.3). Удаление данных — только руками.

## 3. Запись «Код»

### 3.1 Схема

Таблица `snippets_codes` (раздел — под своим именем, CLAUDE.md §1; одна таблица, но префикс
оставляет место для `snippets_*` следующих версий — §15, решение 8):

```
snippets_codes
  id            bigint pk
  name          string(160)            -- для администратора, на сайт не выходит
  place         string(64) index       -- ключ места (§4); не FK: мест в базе нет
  code          mediumText             -- сырой HTML/JS; лимит проверяет приложение (§3.3)
  consent       string(16)             -- necessary | statistics | marketing | media (§6)
  only_paths    json null              -- маски «только на»: ["/blog/*", "/"]; null/[] — весь сайт
  except_paths  json null              -- маски «кроме»: ["/blog/drafts/*"]
  locales       json null              -- коды языков; null/[] — все языки сайта
  on_stage      boolean default false  -- выводить и вне production (§5.5)
  position      unsigned int default 0 -- порядок внутри места
  enabled       boolean default false  -- выключенный код не выводится (§3.2)
  deleted_at    timestamp null         -- корзина, как у FAQ (faq_delete → корзина, faq_purge)
  timestamps
  index (place, position)
```

### 3.2 Модель и механизмы `module-admin`

`WebxUi\Snippets\Models\Snippet` — `RecordsHistory`, `SoftDeletes`
(`php/packages/module-admin/src/History/RecordsHistory.php`):

- **Черновиков и версий нет.** «Сохранить» в панели и `snippets_update` в MCP пишут колонки — код
  сразу на сайте. Ни `HasDraft`, ни `HasVersions`, ни кнопки отката.
- **«Вкл/выкл» — булев флаг `enabled`**, по умолчанию `false`: новый код не выходит на сайт, пока
  его не включили. Выключенный код хранит всё, что было, и включается тем же переключателем.
- **Журнал** — `RecordsHistory` + `HistoryTypes::register('snippets', Snippet::class, …)` в
  провайдере: кто, когда, что изменил. **Поле `code` пишется целиком — «было» и «стало»**, чтобы
  старый код можно было вернуть вручную. Общий порог длинных значений
  (`webx-admin.history.long_value`, 500 символов; HISTORY §4) превратил бы код счётчика в отметку
  «изменён, N → M символов», поэтому `RecordsHistory` получил точку расширения
  `historyKeepsFull(): list<string>` — поля, которые журнал не сокращает никогда; `Snippet`
  возвращает `['code']`. Строка журнала — до ~128 КБ (было + стало), колонка `changes` — JSON
  (в MariaDB это `LONGTEXT`). Глобальный порог не меняется, остальные модули — тоже.
- `place` и `position` — обычные поля: перенос в другое место и перестановка пишутся сразу, как
  любая правка, и попадают в журнал.

### 3.3 Правила значений

- `name` — обязательно, ≤160 символов.
- `place` — объявленное место (§4); необъявленное — отказ под `place`. Существующая запись с
  осиротевшим местом сохраняется (иначе её нельзя ни выключить, ни перенести), но **включённой**
  в необъявленном месте она не сохраняется — отказ под `place`: выключить или перенести можно.
- `code` — обязательно, ≤65 536 байт UTF-8 (`strlen`, не `mb_strlen`: лимит про объём страницы).
  `mediumText`, а не `text`: `TEXT` MySQL — те же 65 535 байт, и многобайтный код упирался бы в
  колонку раньше, чем в правило.
- `consent` — `necessary`, `statistics`, `marketing` или `media` (имена виджета согласия, §6); по
  умолчанию `necessary`.
- Маска — путь от корня сайта **без языковой приставки**, с `*` (`Str::is`, как у
  `Admin\Gate\Openings::paths()` и `Admin\Links\SiteRoutes`): `/`, `/blog/*`, `/contacts`.
  Начинается с `/`; без схемы, хоста и `?`. До 50 масок в каждом списке.
- `locales` — коды из `Localization\Locales::codes()`; неизвестный — отказ.

## 4. Места

### 4.1 Встроенные

| Ключ         | Где в `components/layout` `theme-default`             | Зачем                                  |
| ------------ | ----------------------------------------------------- | -------------------------------------- |
| `head`       | сразу после `<meta charset>` и `<meta name=viewport>` | менеджер тегов, проверки прав на домен |
| `body_start` | сразу после `<body …>`, до ссылки «Skip to content»   | `<noscript>`-часть менеджера тегов     |
| `body_end`   | после `@stack('scripts')`, последней строкой лэйаута  | чаты, виджеты обратного звонка         |

Баннер согласия и скрипты виджетов `Widgets::finish()` дописывает перед `</body>` уже после рендера,
то есть после места `body_end`.

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
компонента — ошибка компиляции Blade. Форма для `theme-default` — §11.1 (§15, решение 3).

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
  AUDIT §5.6 «Стенд разработки»), но `module-snippets` от аудита не зависит и стенд по хосту не
  определяет (§15, решение 5). Список «прод»-окружений — `webx-snippets.environments`, по умолчанию
  `['production']`.

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
  компонент без текущего запроса печатает пусто. «Вне запроса» — консоль, кроме тестов
  (`runningInConsole() && ! runningUnitTests()`): очередь и команды кодов не получают.

Пути читаются в момент запроса (как `Openings`), а не при загрузке. Пустой путь панели — «ничего»,
а не «всё» (то же правило `Openings::under()`).

### 5.7 Кеш

- Ключ `webx-snippets.codes` — все включённые коды, сгруппированные по месту, с полями §3.1; хранилище — кеш приложения (на платформе — Redis с `REDIS_PREFIX`
  сайта), без срока.
- Сброс — `saved`, `deleted`, `restored`, `forceDeleted` модели и перестановка (reorder пишет
  пачкой — сброс один раз после транзакции). Включение и выключение — это `save()`, ловятся тем
  же событием.
- Фильтры §5.2–§5.6 — на запрос, в PHP, по закешированному списку: путь и язык у каждого запроса
  свои, кешировать их сочетания — раздуть кеш без выигрыша.
- Смена конфига мест кеш не трогает: необъявленное место отсекается при чтении.

## 6. Согласие на cookie

### 6.1 Что есть в `widgets`

Факты из `php/packages/widgets` (WIDGETS §9):

- Категории — `Consent::CATEGORIES` = `necessary`, `preferences`, `statistics`, `marketing`,
  `media` (`src/Consent.php`). **Категории `analytics` нет** — аналитика у виджетов зовётся
  `statistics` и в Consent Mode идёт в `analytics_storage`. Модуль хранит имена виджета:
  `necessary`, `statistics` (в интерфейсе — «Аналитика»), `marketing`, `media` (§15, решение 1).
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

Раздел «Коды» (Codes), иконка — `file-html` (своей `code` в наборе ядра нет), группа — «Настройки»: в панели это группа `system` («Система»), там же настройки и SEO (§15,
решение 7). Модуль
`WebxUi\Snippets\Panel\SnippetsModule extends AbstractModule`, `permissions()` —
`['snippets.manage']` (§8). Регистрация — `Setup\Catalogue` (§12).

### 7.2 Список

- Сгруппирован по местам в порядке `Places::all()` (встроенные первыми), в группе — по `position`.
  Строка: название, категория согласия (бейдж), охват кратко («весь сайт» / «только на 2 адреса» /
  «кроме 1»), языки, «и на dev», переключатель вкл/выкл (`enabled`, пишется сразу).
- Перетаскивание — `vue-draggable-plus` внутри группы и между группами (смена места);
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
- `consent` — `wx-segmented` / `wx-radio-group`: «Необходимые» / «Аналитика» / «Маркетинг» /
  «Медиа»;
- `only_paths`, `except_paths` — `wx-tags-input` (список строк), подсказка с примером `/blog/*`;
- `locales` — `wx-checkbox-group` из языков сайта, пусто — «все»;
- `on_stage` — `wx-switch` «Показывать и на dev/stage»;
- `enabled` — `wx-switch` «Включён»: «Сохранить» включённого кода — сразу на сайте;
- журнал — узел `wx-history` (WEBX_UI_HISTORY.md) с полным кодом «было/стало»; кнопки отката нет,
  старый код копируют из журнала.

### 7.4 Предупреждение о `<html>`, `<head>`, `<body>`

При сохранении код ищется регуляркой на открывающие и закрывающие теги `html`, `head`, `body`
(вне комментариев не разбирается — это подсказка, не парсер). Найдено — сохранение проходит, а
ответ несёт `warnings`; панель показывает `WxAlert` над редактором и тост «Код содержит `<head>`:
вставляйте только содержимое, которое сервис просит поставить внутрь». В MCP — то же полем
`warnings` в ответе `create`/`update` (§9.3), не отказом.

### 7.5 API панели

Под `config('webx-admin.api_path')/snippets`, все — `cms.can:snippets.manage`:
`GET /` (список по местам + `places` + `environment`), `GET {id}`, `POST /`, `PUT {id}` (сразу на
сайте; ответ несёт `warnings`, §7.4), `DELETE {id}` (корзина), `POST {id}/restore`,
`DELETE {id}/purge`, `POST reorder` (`{ place, ids }`). Отказы валидации — 422 под полем,
неизвестное место — под `place`.

## 8. Права

- Одно право `snippets.manage` (просмотр без правки смысла не имеет: чтение кода — это и чтение
  чужих ключей сервисов). `CmsUser::hasPermission()` пропускает суперадмина всегда
  (`module-auth/src/Models/CmsUser.php`), а ролей с правами по умолчанию `module-auth` не
  заводит — значит, **после установки раздел видит только суперадмин**, ничего дописывать не надо.
- Пометки «опасное» и исключения из «выбрать всё» нет: формы роли с «выбрать всё» в панели нет —
  роли только читаются (`module-auth/src/Http/Controllers/RoleController.php`). Защита — текст
  права и то, что ни одна роль не получает его при установке (§15, решение 6).
- В описании права (словарь `snippets::permissions.manage`) прямо: «Даёт исполнять JavaScript на
  сайте от имени сайта».
- MCP: `snippets:read` / `snippets:write` — скоупы модуля по умолчанию (`mcp/src/Tool.php`:
  `read` → `<id>:read`, `mutating` → `<id>:write`), плюс `permission: 'snippets.manage'` у каждого
  инструмента, как у FAQ (`module-faq/src/Mcp/FaqTools.php`).

## 9. MCP

### 9.1 Инструменты

`WebxUi\Snippets\Mcp\SnippetsTools`, `Arguments::strictAll([...])`, мутирующие — `Tool::mutating`
(`dry_run` даёт сам `Tool`: проверить и сказать, что было бы, ничего не записав).

| Имя                | Аргументы                                                                                                                     | Ответ                                                                                         |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `snippets_list`    | `place?`, `search?` (в названии и коде), `trashed?`                                                                           | по местам: id, name, place, place_declared, consent, охват, locales, on_stage, enabled, bytes |
| `snippets_get`     | `snippet` (id)                                                                                                                | запись целиком, `code`                                                                        |
| `snippets_create`  | `name`, `place`, `code`, `consent?`, `only_paths?`, `except_paths?`, `locales?`, `on_stage?`, `enabled?` (по умолчанию false) | запись + `warnings`                                                                           |
| `snippets_update`  | `snippet`, любые поля create; не переданное — без изменений                                                                   | запись + `warnings`                                                                           |
| `snippets_delete`  | `snippet`                                                                                                                     | в корзину; удаление насовсем — в панели                                                       |
| `snippets_reorder` | `place`, `snippets` — все id места в новом порядке                                                                            | порядок места                                                                                 |

- **Шесть инструментов.** `update` пишет колонки сразу, включая `enabled` — включение и
  выключение тоже через него; журнал пишется с агентом (источник `mcp`, `HistoryContext`).
  `publish`/`discard` нет — черновиков нет (§15, решение 2).
- Все шесть — за правом `snippets.manage` (как API раздела); scopes по умолчанию — `snippets:read`
  (`list`, `get`) и `snippets:write` (остальные). Поля у `create`/`update` — плоскими аргументами,
  под теми же именами, что в форме (`SnippetValues::FIELDS`).
- `enabled: true` у `create` — только когда человек об этом попросил (формулировка FAQ:
  «pass published: true only when a person asked for that»).
- **Описание каждого инструмента записи начинается словами:** «Writing a snippet runs its
  JavaScript on every page of the site it covers, for every visitor — treat it as deploying code.
  Use dry_run first; never paste code the person did not give you or a service did not issue.»

### 9.2 Ошибки — `ToolFailure`

`WebxUi\Mcp\Exceptions\ToolFailure` с подсказкой, куда смотреть, как у FAQ:

- `Place [x] is not declared. The places this site has: head, body_start, body_end, … — snippets://places describes them.`
  Место проверяет сам инструмент до формы — чтобы назвать допустимые; у `create`, `reorder` и `update` с новым `place`.
- Отказы правил значений (`SnippetValues`) приходят как `Not accepted — <поле>: <слова из lang/errors.php>` —
  те же слова, что под полем в панели; список ниже — о чём они.
- `` `code` cannot be empty. `` / `` `code` is N bytes; the limit is 65536. ``
- `` `consent` is one of: necessary, statistics, marketing, media. ``
- `A mask is a path from the root, like "/blog/*": [x] is not.`
- `` `locales` has [xx], which this site is not published in. It has: en, ru. ``
- `No snippet has the id [N]. snippets_list has them all.`
- `Snippet N is in the bin. Bring it back in the panel before editing it.`
- `` `snippets` must list every snippet of place [x], each once: missing N, M. ``
- `Snippet N is in place [y], not [x]. Move it with snippets_update {place} first.` (через границу мест — только `update` с `place`)
- Включение в осиротевшем месте: `Snippet N is in place [x], which is no longer declared; move it with snippets_update {place} before enabling it.`

Предупреждения (§7.4) — не отказ, а `warnings: ["The code has <head>: …"]`.

### 9.3 Ресурсы

`WebxUi\Snippets\Mcp\SnippetsResources`:

- `snippets://catalog` — справочник: что такое код, правила (лимит, маски, категории, «и на dev»,
  где не выводится), и список существующих кодов кратко (id, name, place, enabled) — чтобы агент
  не завёл второй счётчик того же сервиса;
- `snippets://places` — места: `key`, `label`, `built_in`, `in_views` (статический поиск §10.1 по
  видам сайта и слоям темы; `null`, если искать не вышло), `last_seen_on_a_page` (§10.3),
  `snippets` и `enabled` — число кодов; осиротевшие — списком `undeclared`; `own_place` — как сайту
  добавить своё место.

Директории видов для доктора и ресурса — один класс `Checks\SiteViews`.

`webx:mcp-tools` показывает инструменты без своего кода — реестр MCP.

## 10. Диагностика

### 10.1 `webx:theme:check`

**Что сейчас.** `themes/src/Console/CheckCommand.php` → `Checks\ChainCheck::run()` читает
файлы слоёв (токены, CSS, блоки, иконки, ассеты, слова, шрифты, письма) и **точки расширения
для чужих проверок не имеет**: `SyncStep` с тегом `webx-themes.sync` есть только у sync
(`Contracts/SyncStep.php`). Правило 21 (THEMES §16.1: лэйаут держит `@webxTheme`, `@webxSeo`,
`@webxBlocks` и зоны) помечено «check», но `ChainCheck` лэйаут не читает.

**Сделано.** В `themes` — контракт `Contracts\ThemeCheckPart` с тегом `webx-themes.check`:
`check(ThemeChain $chain, array $viewPaths): list<{file, line, message, note?}>` — находки идут в
общий вывод и код возврата, строка с `note: true` печатается с пометкой `note:` и не считается.
`$viewPaths` — виды сайта (`view.paths`), затем слои цепочки; у темы, проверяемой по пути, — только
слои. `module-snippets` регистрирует `Checks\PlacesCheck` (поиск — `Checks\PlaceFinder`).

Как искать место — два способа:

| Способ                                | Как                                                                                                                                                                                                                                                                                   | Плюсы                                                                        | Минусы                                                                                                                   |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| **Статический поиск** (рекомендуется) | по `*.blade.php` видов сайта (`view.paths`) и слоёв цепочки — только файлу, который прочитает Blade (перекрытый вид не в счёт), — ищется `<x-webx-snippets … place="<key>"` и `component="webx-snippets" … place="<key>"`; встроенные — обязательно, свои — каждое из `Places::all()` | без базы, маршрутов и запроса; работает в CI и на теме по пути; говорит файл | не видит `:place="$var"` и место, выведенное из PHP; найдёт компонент в виде, который никто не подключает                |
| Рендер страницы                       | внутренний запрос главной, поиск маркера места в HTML                                                                                                                                                                                                                                 | видит то, что реально напечатано                                             | нужна база и данные; одна страница не скажет за все; `theme:check` по пути работает без установки — там рендерить нечего |

Находки (предупреждения, но с кодом 1 — как остальные находки check, §17):

- `components/layout.blade.php (<слой>) — no <x-webx-snippets place="head">: codes of the place
"head" are never printed (SNIPPETS §4.1)` — для каждого встроенного места, если его нет ни в
  одном виде цепочки и сайта;
- `config/webx-snippets.php — place "before-footer" is declared but no view prints it` — свои;
- динамический `:place=` — не находка, а строка «place set by an expression, not checked».

Без `module-snippets` проверки нет (тег никто не регистрирует). Правило 21 THEMES §16.1
дополняется: «и три места кодов, если стоит `module-snippets`».

### 10.2 `webx:doctor`

`Doctor\SnippetPlaces` в `module-snippets`, регистрация — `callAfterResolving(DoctorChecks::class, …)`,
как `FontsCheck` тем (`themes/src/ThemeServiceProvider.php`, `Doctor/FontsCheck.php`):

- объявленное место, которого нет в видах (тот же статический поиск, §10.1) — `warn`;
- коды в необъявленном месте — `warn` о содержимом (`Diagnosis::content`: чинится в панели, `--strict` не роняет): «3 snippets are in place "promo", which config/webx-snippets.php no longer declares — not printed»;
- есть включённые коды не-`necessary`, а скрипта согласия на сайте не будет (нет темы в
  `webx-themes.theme`, или `consent.js` не опубликован — `Widgets::consentReady()`) — `warn`
  (§6.3, п. 4);
- окружение не из `webx-snippets.environments` и есть включённые коды без «и на dev» — строка `ok`
  с объяснением, почему их не видно.

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
   модуля не меняется (§15, решение 9).
2. **`revive()` сохраняет тип скрипта из `template`**: для скриптов распакованного шаблона
   атрибут `type` не снимается (снимается только у `script[type=text/plain]`, где настоящий тип —
   `data-webx-type`). JSON-LD и прочие не-JS типы остаются данными.
3. **Порядок внутри распакованного шаблона**: скрипты вставляются по одному, следующий — после
   `load`/`error` предыдущего внешнего; встроенные между ними ждут так же. Тест в
   `resources/js/consent.test.js`: «внешний + встроенный init» исполняются по порядку.
4. Категорий, API активации и отзыва достаточно (§6.1) — новых не нужно (§15, решение 1).

Changeset — `@webx-ui/php` (общий для composer-пакетов) и, если тронут JS-бандл виджетов, он
собирается в `dist/` пакета `webx-ui/widgets` — отдельного npm-пакета у него нет.

### 11.4 `module-admin`

- `Setup\Catalogue` — строка модуля.
- `RecordsHistory::historyKeepsFull(): list<string>` — поля, которые журнал пишет целиком, мимо
  порога `long_value`; `Journal::record()` принимает их списком, `Changes::entry()` для них
  правило `long` пропускает (§3.2). У остальных моделей список пуст, их журнал не меняется.
  HISTORY §4 дописан.
- `RecordsHistory`, `DoctorChecks`, `ProvidesDemo` — как есть.

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
- Строка `Catalogue` и `extra.webx.npm`/`panel` ставятся **вместе с npm-пакетом**, а не раньше:
  `ModulesCommandTest` требует, чтобы npm-половина строки каталога стояла в `extra.webx.npm`, а
  `scripts/sync-npm-pairs.mjs` (часть `pnpm version-packages`) роняет релиз на имени
  `@webx-ui/*`, которого в `packages/` нет. До тех пор `webx:module:add snippets` модуля не знает.
- Плейграунд — `apps/playground/src/panel/main.ts` + фикстура в `apps/playground/server/panel/`.
- `scripts/packages.mjs` репозитория демо-сайта.
- `RouteType` нет — `SiteBar::entity()` не нужен (`SiteBarCoverageTest` не задет).
- `AGENTS.md` пакета ≤150 строк (`AgentDocsCoverageTest`), гайд `apps/docs/guide/snippets.md`.

## 13. Демо

`WebxUi\Snippets\Demo\SnippetsDemo` (`ProvidesDemo`, как `module-faq/src/Demo/FaqDemo.php`):
один выключенный код в `body_end`, `consent: statistics`, `on_stage: false`, без реальных
идентификаторов сервисов (`resources/demo/snippets.json`, название — на языке сайта по умолчанию):

```html
<!-- Demo snippet: paste the code your service gives you here. It is switched off. -->
<script>
  console.log('webx snippets: the demo snippet ran')
</script>
```

Сайт, где уже есть хоть один код (и в корзине), демо не получает. `webx:demo --remove` удаляет
его по журналу демо.

## 14. Тесты

- Вывод: встроенные места в `theme-default`, своё место из конфига, порядок, пустое место — пусто.
- Охват: `only`/`except`, приставка языка снимается, `?query` не мешает; языки.
- Окружение: `local` — только `on_stage`; `production` — все.
- Согласие: `necessary` как есть; `statistics` без cookie — `template`, с cookie — как есть;
  баннер выключен — как есть.
- Исключения: `/cms/…`, `/api/cms/…`, `/_preview/…`, сцена блоков, 404 под `/cms/…`.
- Кеш: сохранение, включение, выключение, перестановка, удаление сбрасывают; выключенный код не
  выводится.
- Журнал: автор и полный код до/после; в `module-admin` — поле из `historyKeepsFull()` хранится
  целиком, остальные сокращаются как раньше.
- MCP: каждый инструмент, `dry_run` не пишет, `ToolFailure` на каждый отказ §9.2, `warnings`.
- Необъявленное место: не выводится, видно в `snippets_list`, доктор предупреждает.
- `PlacesCheck`: лэйаут без места — находка, обе формы компонента находятся.
- Права: без `snippets.manage` — 403 в API и отказ в MCP; суперадмин — да.
- JS виджетов (§11.3): тип из шаблона сохраняется, порядок внешнего и встроенного.

## 15. Решения

Приняты человеком 16.10.2026; вопросы с вариантами — в истории файла.

1. **Категории согласия — имена виджета:** `necessary`, `statistics` (в интерфейсе подпись
   «Аналитика»), `marketing`, `media`. Синонима `analytics` в `widgets` нет.
2. **MCP — шесть инструментов:** `list`, `get`, `create`, `update` (включая `enabled`), `delete`,
   `reorder`. `publish`/`discard` нет — черновиков нет.
3. **`theme-default` ставит места через `x-dynamic-component` за `view()->exists()`** (§11.1) —
   без модуля вёрстка не меняется.
4. **Вкл/выкл — булев флаг `enabled`**, без черновиков и версий: «Сохранить» — сразу на сайте.
   Журнал (`RecordsHistory`) хранит автора и полный код «было/стало»; кнопки отката нет.
5. **Прод — `APP_ENV` + конфиг `webx-snippets.environments`** (по умолчанию `['production']`);
   стенд по хосту не определяется.
6. **`snippets.manage` — опасное право**, ни одна роль не получает его при установке. Формы роли с
   «выбрать всё» в панели нет (роли только читаются) — пометки в форме нет, защита — текст права.
7. **Раздел — в группе меню «Настройки».**
8. **Таблица — `snippets_codes`.**
9. **Consent Mode default — выше места `head`:** маркер `Widgets::EARLY`, его печатает компонент
   места `head` (§11.3, п. 1).
10. **Коды как источник контента аудита — не в этой задаче.**
11. **Вошедшему администратору коды выводятся как всем.**

## 16. Выпуск

Этапы задачи: спека → PHP-ядро (модель, вывод, места, consent, диагностика, правки чужих пакетов)
→ MCP, демо, `AGENTS.md` → панель и npm-пакет, гайд, плейграунд, демо-сайт.

**PHP-ядро (этап 2) сделано:** пакет `php/packages/module-snippets` — таблица `snippets_codes`,
модель `Snippet` (флаг `enabled`, журнал с полным кодом), места из конфига (`Places`), правила
значений (`SnippetValues`), вывод `<x-webx-snippets>` с кешем, охватом, языками, окружением,
согласием и исключениями, раздел панели (`SnippetsModule`, API §7.5, экран `snippets.form`),
`PlacesCheck` в `webx:theme:check`, `SnippetPlaces` в `webx:doctor`; в `theme-default` — три
места; в `themes` — `ThemeCheckPart`; в `widgets` — `Widgets::EARLY`, `consentReady()`, тип и
порядок скриптов шаблона в `consent.js`; в `module-admin` — `historyKeepsFull()`.

**MCP, демо, `AGENTS.md` (этап 3) сделано:** `Mcp\SnippetsTools` (шесть инструментов §9.1, отказы
§9.2, `warnings`), `Mcp\SnippetsResources` (`snippets://catalog`, `snippets://places`),
`Demo\SnippetsDemo` (§13), `AGENTS.md` пакета; пакет снят с `PENDING` у `AgentDocsCoverageTest`.

Первую версию `@webx-ui/module-snippets` в npm
публикует человек (`docs/pitfalls/release-and-ci.md`).

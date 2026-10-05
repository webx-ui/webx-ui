# `webx-ui/module-vacancies` — спецификация

Статус: спроектирован и выпущен 28.09.2026 в v0.48.0 одним релизом с `module-tariffs`
(`WEBX_UI_MODULE_TARIFFS.md`), стоит на обоих демо (§8). Пакеты — `webx-ui/module-vacancies`
(composer) и `@webx-ui/module-vacancies` (npm).

Вакансии — «кого мы ищем»: должность, где и как работать, условия, зарплата словами и числами,
задачи, требования и что предлагаем. У вакансии свой адрес и **страница жёсткой структуры, без
блоков**, как у события: её рисует вьюха модуля, сайт меняет вид, публикуя вьюху. На странице —
разметка `JobPosting`, по которой Google показывает вакансию в поиске работы. Вакансии лежат в
плоских категориях («Разработка», «Продажи»), но у категорий **нет своих страниц**: это группы и
фильтр индекса и хелпера. Отклик — форма `module-inbox`, выбранная в вакансии.

Каркас взят у `module-events` (`WEBX_UI_MODULE_EVENTS.md`): черновик и версии, SEO, приставка,
выключаемый индекс, общие категории, `extra`, «Дублировать», хелпер на `RecordQuery`. Своего у
модуля — поля вакансии, «закрыта» вместо «прошло», разметка `JobPosting` и одна маленькая
правка в `module-inbox`: форма становится целью связей (§4.3). Ядро (`module-admin`) не
меняется.

## 1. Границы

**Внутри:** вакансии и их категории, адрес вакансии и индекс под приставкой, закрытые вакансии,
черновик, публикация и история, ручной порядок, «Дублировать», SEO и разметка `JobPosting`,
выбор формы отклика, валюты из конфига, хелпер `vacancies()`, экраны панели, API, MCP, демо.

**Снаружи:** страницы категорий и их адреса (решение 2, §7 п. 1); печать формы отклика на
странице вакансии (решение 3, §7 п. 2); предложенный тип блока и источник `wx-collection`
(решение 6); связь с услугами (решение 12); импорт вакансий с внешних площадок и выгрузка на них
(§7).

## 2. Принятые решения (не переоткрывать)

Решения 1–9 принял пользователь в обсуждении 28.09.2026; 10–20 — решения спеки.

1. **Своя страница под приставкой.** Вакансия — `/{приставка}/{вакансия}`, приставка
   `webx-vacancies.prefix` (по умолчанию `careers`), пустой не бывает. Индекс `/{приставка}`
   выключается `webx-vacancies.index` — адрес уходит странице `module-pages`, как у событий. На
   странице вакансии — разметка `JobPosting` (§4.7).
2. **Категории плоские, общие, «многие ко многим», без своих страниц и адресов.** Это группы и
   фильтр индекса и хелпера `vacancies()`. Построены на том же скелете, что у событий и рецептов
   (`IsCategory`, `category()`, `categoryLinks()`), чтобы страницы категорий можно было включить,
   когда попросит клиент; что для этого нужно и что хранится уже сейчас — §7 п. 1.
3. **Отклик — форма `module-inbox`, выбранная в вакансии.** Хранится только выбор; печать формы
   на странице вакансии — когда понадобится настоящему сайту (§7 п. 2). Без `module-inbox` поля
   нет, всё остальное работает. Как панель получает список форм — §4.3.
4. **Закрытая вакансия** уходит из списков, её страница остаётся (200) с пометкой «Вакансия
   закрыта» и **без** `JobPosting`. Механика — решение 13.
5. **Поля:** должность; где работать — город и адрес текстом или удалённо (`TELECOMMUTE`); вид
   занятости значениями schema.org; зарплата — переводимый текст для людей плюс необязательные
   числа «от / до», единица (`HOUR`…`YEAR`) и валюта для разметки; валюта — у каждой вакансии, из
   списка конфига `webx-vacancies.currencies` (код → символ, по умолчанию USD, EUR, UAH, PLN —
   **та же форма, что у тарифов**); описание (`wx-rich-text`); три повторителя — «Задачи»,
   «Требования», «Мы предлагаем» (переводимые строки); «действует до»; дата размещения. Ручной
   порядок (перетаскивание в панели), черновик и версии, SEO обязательно, `extra`.
6. **Блоков нет.** Сайт выводит вакансии хелпером `vacancies()` на `RecordQuery` (§4.8).
   Предложенный блок — потом (§7).
7. **Связь с услугами** — решает спека (решение 12).
8. **Параллельно с тарифами, выпуск общий.** Свои ветки и worktree, оба модуля вышли одним
   релизом (§8).
9. **Слова интерфейса — на десяти языках**, как у остальных модулей (`de en es fr it pl pt ru tr
uk`).

Решения спеки:

10. **Выбор формы — связь, а не колонка** (§4.3). `module-inbox` регистрирует цель связей
    `inbox-form`, а вакансия выбирает форму полем `wx-relations` с `max: 1` (роль `form`). Так
    всё, что нужно по решению 3, уже есть в ядре: пикер с поиском, поле само исчезает без цели
    (`RelationsType` — `Withdraws`), выбор ждёт в черновике и применяется публикацией, удаление
    формы убирает строку связи (`RelationTargets::register()` вешает слушателя). Колонка
    `form_slug` сломалась бы на переименовании слага (он редактируется), колонка `form_id` без
    внешнего ключа (пакет необязательный) — это та же связь, только без пикера, без очистки и без
    исчезновения поля.
11. **Слаг категории хранится и держится уникальным** уже сейчас: он — ключ фильтра индекса
    (`?category=development`) и хелпера (`vacancies()->in('development')`), а в будущем — адрес
    (§7 п. 1). Общий `CategoryForm::create()` делает его из названия сам; FAQ и отзывы его
    стирают, вакансии — нет.
12. **Связи с услугами нет.** Вакансия — про работу у организации, а не про то, что организация
    продаёт; «вакансии этой услуги» никто не просил. Своей цели связей `vacancy` модуль тоже не
    регистрирует. Понадобится — одна строка в экране и одна в провайдере (§7).
13. **«Закрыта» — выключатель или прошедший срок.** `is_closed` — «набор закончен раньше срока»;
    `valid_through` — **дата** (не момент): вакансия открыта весь этот день в поясе приложения.
    Закрыта = `is_closed or (valid_through is not null and valid_through < сегодня)` — одно
    выражение для SQL и php, как «прошло» у событий. Снять с публикации — другое: страница 404.
14. **Закрытая страница — ещё и `noindex`.** Страница остаётся ради ссылок с площадок и из
    соцсетей, но в поиске висеть как вакансия не должна: Google для истёкшей вакансии просит
    убрать разметку и либо 404, либо `noindex`. `noindex` кладёт источник SEO модуля (как
    `TagIndexing` у блога), правило редактора в `seo_urls` его перебивает; из карты сайта
    страница уходит сама — `module-seo` не кладёт туда страницы с `noindex`.
15. **Дата размещения — своя колонка `posted_at`**, а не `published_at`: `HasDraft::publish()`
    ставит `published_at` заново на каждой публикации, а `datePosted` — день, когда вакансию
    выставили впервые. Ставится первой публикацией, если пуста; редактор может поменять («снова
    открыли набор»).
16. **Где работать — `workplace`: `onsite | remote | hybrid`**, как `attendance` у событий. Место
    — `city` и `address` (переводимые), страна — ISO-код `country` у вакансии (по умолчанию из
    `webx-vacancies.country`): вакансии в UAH и PLN на одном сайте — это две страны. В разметке —
    `jobLocation` у `onsite`/`hybrid`, `jobLocationType: TELECOMMUTE` и
    `applicantLocationRequirements` у `remote`/`hybrid`.
17. **Вид занятости — список**, не одно значение: «полная или частичная» — обычное дело, и
    `employmentType` принимает массив. Значения — ровно список Google: `FULL_TIME`, `PART_TIME`,
    `CONTRACTOR`, `TEMPORARY`, `INTERN`, `VOLUNTEER`, `PER_DIEM`, `OTHER`.
18. **Повторители — строки общие, язык внутри строки**, как «Чего ожидать» у событий: у каждой
    строки одно поле `text` с `localized`, число и порядок строк у языков одни, строка без текста
    на языке страницы не печатается. У событий `localized` внутри `wx-repeater` проверен туда и
    обратно (итог EV1).
19. **`lead` есть** — короткий текст без разметки для карточки и запасного `description`, как у
    событий: описание вакансии длинное и с разметкой, в карточку его не положить.
20. **«Дублировать» есть** — как у событий: «та же должность во Львове» — копия, а не новая
    вакансия с нуля. Порядок — сразу после оригинала.

## 3. Схема

```
vacancies
  id
  title            json nullable      -- переводимое: должность
  slug             json nullable      -- переводимое
  lead             json nullable      -- переводимое, без разметки: карточки, description
  workplace        string(8) default 'onsite'   -- onsite | remote | hybrid
  city             json nullable      -- переводимое: «Київ»
  address          json nullable      -- переводимое: улица, район
  country          string(2) nullable -- ISO 3166-1 alpha-2, только для разметки
  employment_types json nullable      -- ["FULL_TIME", "PART_TIME"]
  salary           json nullable      -- переводимое: «від 60 000 ₴», «за результатами співбесіди»
  salary_min       decimal(12,2) nullable   -- только для разметки и карточки
  salary_max       decimal(12,2) nullable
  salary_unit      string(8) nullable -- HOUR | DAY | WEEK | MONTH | YEAR
  salary_currency  string(3) nullable -- ключ webx-vacancies.currencies
  description      json nullable      -- переводимый HTML (wx-rich-text)
  duties           json nullable      -- «Задачи»:       [{ text: {ru,en} }]
  requirements     json nullable      -- «Требования»:   [{ text: {ru,en} }]
  benefits         json nullable      -- «Мы предлагаем»: [{ text: {ru,en} }]
  is_closed        boolean default false
  valid_through    date nullable      -- последний день, в поясе приложения
  posted_at        date nullable      -- datePosted; ставится первой публикацией
  position         integer default 0  -- ручной порядок, общий
  extra            json nullable      -- поля проекта
  draft()                             -- module-admin: draft, published_at
  softDeletes, timestamps

index(position), index(valid_through)

vacancy_categories          category()      -- title, slug, position, is_visible, extra, …
vacancy_category_vacancy    categoryLinks('vacancy', 'vacancy_categories')

-- форма отклика — cms_relations (роль form, цель inbox-form), своей колонки нет
```

- Все три миграции — `2026_01_01_*`: на чужие таблицы модуль не ссылается (CLAUDE.md §4 о
  сортировке миграций), форма — строка `cms_relations`, а не внешний ключ.
- `workplace` — `string(8)` при самом длинном значении в шесть символов; `salary_unit` — пять
  (`MONTH`), дефолта у него нет: единица без чисел ничего не значит. Дефолт длиннее колонки
  MariaDB не создаёт (CLAUDE.md §4) — считать символы.
- `valid_through` и `posted_at` — `date`, а не `datetime`: это календарный день, и на нём не
  должно быть ни одной грабли с поясами из §4 CLAUDE.md. **Сравнивать через `whereDate()`**, а не
  строкой: Eloquent пишет `date` как `Y-m-d H:i:s`, и на sqlite `'2026-10-01 00:00:00' <
'2026-10-01'` — ложь.
- Имена колонок не совпадают со свойствами Eloquent (`hidden`, `visible` — CLAUDE.md §4):
  выключатель — `is_closed`.
- Повторители — поля внутри строки, а не строка на язык (решение 18). Пустая строка на записи
  выбрасывается. Разбор для сайта — у модели: `lines(string $field, string $locale): list<string>`.
- `vacancy_categories` — голый `category()`: ни `lead`, ни `cover`, ни SEO (§7 п. 1 говорит, что
  добавить, когда понадобятся страницы).

## 4. Модуль

### 4.1. Модели

- `Vacancy` — `HasCategories`, `HasRelations` (`relationKey() = 'vacancy'`, роль `form`),
  `HasDraft`, `HasVersions`, `HasSeo`, `HasBreadcrumbs`, `HasStructuredData`, `HasExtra`,
  `HasTranslations` (`title`, `slug`, `lead`, `city`, `address`, `salary`, `description`),
  `HasUrl`, `Visible`, `SoftDeletes`. Скоупы `open()` и `closed()` (решение 13, `whereDate` и
  «сегодня» в поясе приложения), порядок — `position`, `id`; `isClosed(): bool` и
  `closedReason(): 'manual'|'expired'|null`. `position` — в `unversionedAttributes()`, как у
  рецептов: восстановление старой версии не двигает вакансию. Новая встаёт в конец
  (`max + 1`, корзина считается), копия — сразу после оригинала.
- `VacancyCategory` — `IsCategory`, `HasExtra`, `HasTranslations` (`title`, `slug`),
  `SoftDeletes`; `categoryFields()` — `title`, `slug`, `is_visible`; `CategoryKind` без
  `prefix` (адреса нет). Слаг проверяется в `saving`: формат — `SlugType::checks()`, уникальность
  на каждом языке среди категорий вакансий, включая корзину; отказ — `ValidationException` под
  `slug.<язык>`. **Не в `created`/`updated`** — там строка уже записана (CLAUDE.md §4).

### 4.2. Зависимости

`require`: `module-admin`, `module-seo`, `routing`, `localization`, `mcp`. `module-media` не
нужен: картинок у вакансии нет, картинки внутри описания — забота `wx-rich-text` в
`module-admin`. `suggest`: `module-inbox` (поле «Форма отклика»), `module-blocks` (предпросмотр
по токену), `module-pages` (страница на месте выключенного индекса). `require-dev`: все три.

### 4.3. Форма отклика: цель связей в `module-inbox`

Единственная правка чужого пакета. Сейчас выбрать форму неоткуда: `GET /api/cms/inbox/forms`
закрыт правами `inbox.view`/`inbox.manage`, которых у редактора вакансий может не быть, а
`inbox_forms_list` — инструмент агента. Решение 10 делает это общим механизмом:

- `WebxUi\Inbox\Relations\FormTarget extends RelationTarget`: `key` — `inbox-form`, `model` —
  `Form`, `permission` — `null` (названия форм не секрет, а редактор вакансий не обязан читать
  заявки), `label` — `webx-inbox::relations.form`. `subtitle()` — слаг, `visible()` —
  `is_enabled`: выключенная форма остаётся выбранной и помечается в пикере, как услуга в
  корзине. Порядок — `position` (у форм он есть, `RelationTarget::order()` найдёт сам).
- Регистрация — в `InboxServiceProvider::boot()`, одна строка, как `EventTarget` у событий. Форма
  без мягкого удаления, поэтому слушатель встанет на `deleted`: удалённая форма уносит строки
  связей с собой. Форму с заявками `module-inbox` удалить не даёт и сейчас.
- В вакансии — узел `wx-relations` с `name: form`, `props: { target: inbox-form, max: 1 }`. Нет
  `module-inbox` — узел снят с экрана (`Withdraws`), `form` в `values` не приходит, сохранение не
  падает.
- Карточка вакансии и страница получают **слаг включённой формы или `null`** (`form` в §4.8):
  этого достаточно, чтобы сайт сам напечатал `<x-webx-inbox::form slug="…">`, пока пакет этого не
  делает (§7 п. 2). Вьюха модуля форму не печатает.
- Changeset этой правки — тот же `@webx-ui/php`: npm-половина `module-inbox` не меняется, пикер
  `wx-relations` в панели общий.

### 4.4. Адреса

| Тип       | Форматтер                        | Пример                         |
| --------- | -------------------------------- | ------------------------------ |
| `vacancy` | `Prefixed($prefix, Slug::class)` | `careers/senior-php-developer` |

Всё как у событий (`WEBX_UI_MODULE_EVENTS.md` §4.3): `OnConflict::Fail`, пустая приставка —
исключение в `boot()`, индекс `webx.vacancies.index` в `SitemapRoutes` только при
`webx-vacancies.index = true`, смена приставки — `webx:routes:rebuild --type=vacancy`, тип —
`LinkSource` (вакансию можно поставить в меню). Типа `vacancy-category` нет (решение 2).

### 4.5. Публичная часть

- **Индекс** `{prefix}` (если включён) — открытые вакансии **группами по категориям** в порядке
  категорий, внутри группы — в общем порядке; вакансия в двух категориях стоит в обеих (как
  каталог услуг, `ServiceQuery::categories()`); без видимой категории — последней группой
  «Другие вакансии»; пустая группа не печатается. Над группами — фильтр ссылками
  `?category=<слаг>`: с фильтром — одна группа. Неизвестный слаг — пустой список, не 404.
  Пагинации нет: порядок ручной, вакансий у сайта десятки, не тысячи (§7). Канонический адрес
  отфильтрованного индекса — сам индекс.
- **Вакансия** — §4.6.

Вьюхи — из конфига с фолбэком на пакет, публикуются в `resources/views/vendor/webx-vacancies`;
макет — общий шов (`webx-vacancies.layout`, CLAUDE.md §4 про верхний ключ `layout`). Карточка —
`partials/card.blade.php`, группа — `partials/group.blade.php`. Видимость: опубликована, не в
корзине, должность и слаг есть на языке страницы. Пустой индекс — не 404, а страница со словами
«Открытых вакансий сейчас нет».

### 4.6. Страница вакансии

`vacancy.blade.php`, части — отдельными `@include`:

1. должность и `lead`; у закрытой — пометка «Вакансия закрыта» (у истёкшей — тем же словом:
   читателю всё равно почему);
2. факты: где (город и адрес; у `remote` — «Удалённо», у `hybrid` — город и «можно удалённо»),
   вид занятости словами, зарплата (текст `salary`; нет текста, есть числа — собранная строка
   «40 000–60 000 ₴ в месяц» из чисел, символа валюты и единицы), «действует до», категории
   текстом (ссылок нет — некуда);
3. описание (HTML как есть — `store()` у `wx-rich-text` чистит разметку);
4. «Задачи», «Требования», «Мы предлагаем» — списками строк на языке страницы; пустой — части нет;
5. место под отклик: пусто в пакете (§7 п. 2); сайт, опубликовав часть `vacancy/apply`,
   печатает там форму по `$form` (слаг или `null`). У закрытой вакансии часть не рисуется вовсе.

Всё, что сайт выводит своё (контакт рекрутёра, «как проходит отбор»), — строки в опубликованной
вьюхе и поля проекта в `extra`.

### 4.7. SEO и разметка

- `HasSeo` у вакансии, карточка `wx-seo` патчем от `module-seo` на `vacancies.form`.
- Крошки: индекс → вакансия; «индекс» — то, что реестр отдаёт по пути `{prefix}` (маршрут модуля
  или страница `module-pages`), нет ничего — звено пропускается. Категории в крошки не входят.
- **`JobPosting`** (`HasStructuredData`), только у открытой вакансии (решение 4):
  - `title`; `description` — HTML: описание и три списка заголовками и `<ul>` на языке страницы
    (Google берёт полное описание только отсюда); `responsibilities`, `qualifications`,
    `jobBenefits` — те же списки строкой, через перевод строки;
  - `datePosted` — `posted_at`; `validThrough` — `valid_through` как `Y-m-dT23:59:59` со
    смещением пояса приложения, нет даты — нет свойства;
  - `employmentType` — список, пустой — нет свойства;
  - `hiringOrganization` — `{ "@id" }` `Organization` из настроек SEO
    (`DefaultsSource::organizationId()`, как `organizer` у событий); нет организации — **нет
    разметки вовсе**: Google без `hiringOrganization` вакансию не принимает, а страница важнее
    полуразметки;
  - место: `onsite`/`hybrid` — `jobLocation` `Place` с `PostalAddress` (`addressLocality` —
    `city`, `streetAddress` — `address`, `addressCountry` — `country`); `remote`/`hybrid` —
    `jobLocationType: TELECOMMUTE` и `applicantLocationRequirements` `Country` по `country`
    (нет страны — без него);
  - `baseSalary` — `MonetaryAmount`: `currency` и `value` `QuantitativeValue` (`minValue`,
    `maxValue` или `value`, если число одно, `unitText`); нет валюты, единицы или обоих чисел —
    нет свойства (текст `salary` в разметку не идёт);
  - `url`, `identifier` не печатаем (нет своего номера вакансии).
  - Проверять на validator.schema.org **и** в Rich Results Test: вакансии Google показывает любому
    сайту, в отличие от FAQ (CLAUDE.md §4). `hiringOrganization` только по `@id` Rich Results
    принимает — проверено на хомлабе при выпуске (§8), `name` рядом не нужен.
- Закрытая — `noindex` своим источником SEO (решение 14), из карты сайта уходит сама.
- Индекс — `ItemList` открытых через `Seo::push()`, как у событий.

### 4.8. Хелпер `vacancies()`

`Rendering\VacancyQuery extends RecordQuery`, по образцу `EventQuery`:

| Шаг                | Что делает                                                       |
| ------------------ | ---------------------------------------------------------------- |
| `open()`           | Только открытые — то, чем запрос является, пока не сказано иначе |
| `closed()`         | Только закрытые (вручную и истёкшие)                             |
| `all()`            | И те и другие                                                    |
| `in($categories)`  | Из этих категорий: id, слаг, модель или список; пусто — все      |
| `only([12, 7])`    | Только эти, в этом порядке                                       |
| `except($vacancy)` | Кроме этих                                                       |
| `take(6)`          | Не больше шести; null или ноль — все                             |
| `locale('uk')`     | Язык карточек; по умолчанию язык страницы                        |
| `get()`, `first()` | Список карточек или одна; запрос можно перебирать и считать      |
| `groups()`         | Каталог по категориям для индекса (§4.5), `take()` — на группу   |

`open`/`closed`/`all` — шаг `when` через `withStep()`, фильтр — в `narrow()`; порядок —
`order()` по умолчанию (`orderedIn` → `position`, `id`). `groups()` пишется по образцу
`ServiceQuery::categories()` плюс последняя группа без категории. Хелпер — за
`function_exists('vacancies')`, строка в `Doctor\Checks\Helpers`.

Карточка (`Rendering\Cards`):

```php
[
    'id' => 12,
    'url' => '/careers/senior-php-developer',
    'title' => 'Senior PHP developer',
    'lead' => '…',
    'workplace' => 'hybrid',
    'city' => 'Kyiv', 'address' => '',
    'employment_types' => ['FULL_TIME'],
    'employment' => ['Full-time'],           // словами на языке карточки
    'salary' => 'from 3 000 $',              // текст редактора; '' если нет
    'salary_range' => [ 'min' => 3000.0, 'max' => null, 'unit' => 'MONTH',
                        'currency' => 'USD', 'symbol' => '$' ],   // или null
    'valid_through' => '2026-11-30',         // или null
    'posted_at' => '2026-09-28',
    'closed' => false,
    'categories' => [3, 5],
    'category_names' => ['Development', 'Remote'],
    'form' => 'job-application',             // слаг включённой формы или null
    'fields' => [ 'recruiter' => 'Olena' ],  // поля проекта по имени
]
```

### 4.9. Конфиг

```php
// config/webx-vacancies.php
'prefix' => env('WEBX_VACANCIES_PREFIX', 'careers'),
'index' => (bool) env('WEBX_VACANCIES_INDEX', true),
'country' => env('WEBX_VACANCIES_COUNTRY'),     // ISO alpha-2 новой вакансии: 'UA'
'currencies' => [                               // код ISO 4217 => символ; первая — у новой вакансии
    'USD' => '$',
    'EUR' => '€',
    'UAH' => '₴',
    'PLN' => 'zł',
],
'views' => ['index' => …, 'vacancy' => …],
'layout' => env('WEBX_VACANCIES_LAYOUT'),       // шов макета публичных страниц
'breadcrumbs' => (bool) env('WEBX_VACANCIES_BREADCRUMBS', true),
'middleware' => ['web', 'webx.locale'],
```

- **`currencies` — ровно та же форма, что у тарифов** (`WEBX_UI_MODULE_TARIFFS.md`): код →
  символ, те же четыре по умолчанию. Общим конфигом это не становится, пока модулей двое (§7).
  Сайт, опубликовавший конфиг, держит свой список целиком (CLAUDE.md §4 про `mergeConfigFrom` —
  на один уровень).
- Валюта, убранная из конфига, **не запирает вакансию** (урок решения 11 у баннеров): сохранённая
  проходит назад, как открыта; выбрать её для другой — 422 под `salary_currency`. В разметке и
  карточке такая валюта остаётся кодом, символ — сам код.
- Опции селекта валюты — патчем экрана `vacancies.form` при `boot()` из конфига, как соцсети у
  команды: список статический.

### 4.10. Панель

Группа меню «Vacancies», иконка `briefcase` (есть в наборе, `icons.test.ts`): **Vacancies ·
Categories**. Права: `vacancies.view`, `vacancies.manage`, `vacancies.categories.manage`. Id
модулей панели: `vacancies`, `vacancy-categories`.

**Список вакансий** — как у рецептов, **без пагинации**: вкладки **Open** (по умолчанию) ·
**Closed** · **All** · **Bin**. Строка: должность со слагом, где (город или «Remote»), вид
занятости, «до» (`valid_through`), категории чипами, статус, у закрытой — бейдж «Closed» или
«Expired» (`closed_reason`), в All — приглушённой строкой; `WxRowMenu` — открыть, дублировать,
закрыть/открыть набор, снять с публикации, в корзину. Фильтры за воронкой: категория, статус,
поиск. **Перетаскивание — на Open и All без фильтров и поиска** (на Closed порядок не нужен), за
ручку, клавиатурой — тот же `reorder`.

**Редактор** — экран `vacancies.form`, вкладки **Vacancy · Settings · SEO · History**:

- **Vacancy** — карточка «Где» (`workplace` — `wx-segmented`; `city`, `address` — прячутся у
  `remote` через `visible`; `country` — короткий `wx-input` с подсказкой «ISO: UA, PL, только для
  поисковиков»); «Условия» (`employment_types` — `wx-checkbox-group`; `salary` — `localized`;
  `salary_min`, `salary_max` — `wx-input-number`; `salary_unit` — `wx-select`;
  `salary_currency` — `wx-select` из конфига; подсказка «числа — для поисковиков и карточки»);
  «Описание» (`wx-rich-text`, `localized`); «Задачи», «Требования», «Мы предлагаем» —
  `wx-repeater` с одним `text` (`wx-input`, `localized`), `sortable`.
- **Settings** — должность, адрес (`wx-slug` с приставкой), `lead` со счётчиком, категории
  (`wx-categories`), «Форма отклика» (`wx-relations`, `target: inbox-form`, `max: 1` — исчезает
  без `module-inbox`), «Набор закрыт» (`is_closed`, `wx-switch`), «Действует до»
  (`valid_through`, `wx-date-picker` `type: date`, `valueFormat: "yyyy-MM-dd"` — строка дня, без
  момента), «Дата размещения» (`posted_at`, то же; пусто — «поставится при публикации»), карточка
  `project-fields`.
- **SEO**, **History** — как у событий.

Панель действий, ревизия (409), автосейв в черновик, предпросмотр по токену (есть
`module-blocks`), «Дублировать» в меню строки и в `WxActionBar` — как у событий. Категории —
`categoryRoutes`, экран `vacancies.category-form`: название, **ключ** (слаг — `wx-input` с
подсказкой «в адресе фильтра: ?category=…»; `wx-slug` рисует адрес сайта, которого у категории
нет), видимость, поля проекта.

### 4.11. API панели

Формы ответов (по ним панель и сервер писались параллельно):

```
GET    /api/cms/vacancies          ?state=open|closed|all&category=&status=&q=&trashed=1
  → { data: [{ id, title, slug, path, url, workplace, city, employment_types,
               valid_through, posted_at, closed, closed_reason, status, position,
               categories: [{ id, title }], published_at, updated_at, deleted_at, revision }],
      filters: { categories: [{ id, title }] } }                     без meta и пагинации
POST   /api/cms/vacancies          { title, slug? }        → 201 { data: { vacancy, values, revision, prefix, preview_url } }
GET    /api/cms/vacancies/{id}     → { data: { vacancy, values, revision, prefix, preview_url } }
PUT    /api/cms/vacancies/{id}     { values, revision }    → то же; 409 на устаревшей, 422 под полем
POST   /api/cms/vacancies/{id}/discard                      → то же
POST   /api/cms/vacancies/{id}/duplicate                    → 201 то же — форма копии
POST   /api/cms/vacancies/{id}/publish | unpublish | restore → { data: vacancy }
DELETE /api/cms/vacancies/{id}
GET    /api/cms/vacancies/{id}/versions
POST   /api/cms/vacancies/{id}/versions/{number}/restore    → форма целиком
POST   /api/cms/vacancies/reorder  { ids }                  без category, как у рецептов
       /api/cms/vacancies/categories/*                       общие маршруты категорий
       /api/cms/relations/inbox-form                         общий пикер связей
```

- `state` по умолчанию — `open`; корзина — `trashed=1`, `state` при ней не действует. `status` —
  `draft | published | modified | unpublished`; `?status=published` — всё, что на сайте, с
  правками и без (как у событий). `city` в строке — на языке панели, иначе на языке по умолчанию,
  иначе `''`; `title` — так же, иначе `#id`. `closed_reason` — `manual | expired | null`
  (`manual` побеждает, если верно оба).
- `vacancy` в ответе формы — та же строка, что в списке (`VacancyResource`); `preview_url` —
  `null` без `module-blocks`; `prefix` — для `wx-slug`.
- `values` — всё с экрана: переводимые картами языков, `workplace`, `country`,
  `employment_types`, `salary_min`/`salary_max` (число или `null`), `salary_unit`,
  `salary_currency`, `duties`/`requirements`/`benefits` (`[{ text: {…} }]`), `is_closed`,
  `valid_through`/`posted_at` (`YYYY-MM-DD` или `null`), `categories`, `form` (список из одного
  id или пустой; нет цели `inbox-form` — нет и ключа), `seo`, поля проекта. Категории и форма
  ждут в черновике и применяются публикацией; `is_closed` — тоже (закрыть набор — это
  «опубликовать»; кнопка «Закрыть набор» в меню строки делает `PUT` + `publish` одной
  транзакцией на сервере — `POST {id}/close | reopen` → `{ data: vacancy }`, у вакансии с
  правками — 409 «сначала опубликуйте или отмените правки»).
- Новая вакансия: `salary_currency` — первая валюта конфига, `country` — `webx-vacancies.country`,
  `workplace` — `onsite`, `employment_types` — `["FULL_TIME"]`.
- **Где лежат 422:** `salary_max` меньше `salary_min` — `salary_max`; число без единицы —
  `salary_unit`; валюта не из конфига и не сохранённая — `salary_currency`; `country` не
  `^[A-Z]{2}$` — `country` (строчные приводятся к заглавным, не отказ); `valid_through` раньше
  `posted_at` — `valid_through`; вид занятости не из списка — `employment_types`
  (`OptionListType`); строка повторителя — `duties.<n>.text.<язык>` и т. д. (`rowErrors` у прессы: `WxScreenRepeater` раскладывает `<поле>.<n>.<поле строки>[.<язык>]`); форма,
  которой нет, — `form`; больше одной — `form` (`max: 1`). Пустая строка повторителя
  выбрасывается.
- **Что в `vacancy` черновое, а что — с сайта:** слова (`title`, `slug`, `workplace`, `city`,
  `employment_types`) и `categories` — из черновика, как у событий; `closed`, `closed_reason`,
  `valid_through`, `posted_at` — с сайта: по ним сортируют вкладки, и строка на Open, называющая
  себя закрытой из-за неопубликованной правки, стояла бы не на своей вкладке. Черновые даты — в
  `values`.
- **`close`/`reopen`:** 409 не только с правками, но и у вакансии, которой нет на сайте
  (иначе кнопка выложила бы её); тело — `{ message }`. `reopen` у истёкшей снимает и
  `valid_through`, иначе кнопка оставила бы её закрытой.
- **`reorder`:** названные id получают места, которые занимают сейчас, в новом порядке —
  закрытые между ними (вкладка Open) остаются на своих; id, которого нет, — 422 `ids`.
- **Публикация:** `posted_at` пуст — ставится сегодняшним днём в поясе приложения (решение 15).
- **Дублирование:** копия — черновик, ни разу не опубликованный; все поля, категории и форма;
  должность та же, слаг — со следующим свободным суффиксом `-2`, `-3` на каждом языке;
  `posted_at` — пусто, `is_closed` — `false`; SEO копируется; история пустая; позиция — сразу
  после оригинала. Сохранение, создание, дублирование, `close`/`reopen` — по одной транзакции.

### 4.12. MCP

`vacancies_list` (`state` — `open` по умолчанию), `vacancies_get`, `vacancies_create`,
`vacancies_update`, `vacancies_duplicate`, `vacancies_publish`, `vacancies_unpublish`,
`vacancies_close`, `vacancies_reopen`, `vacancies_delete`, `vacancies_reorder` — через те же
`Panel\*`; `vacancy_categories_*` — общий `CategoryTools`. Категории — id или слагом. Форма —
`form`: слаг или id формы `module-inbox` (`inbox_forms_list` их называет), неизвестная — отказ;
без `module-inbox` аргумента в схеме нет. Даты — `YYYY-MM-DD`. Вид занятости и единица — коды
schema.org, валюта — код из конфига, не из списка — отказ со списком допустимых. Строка без языка
в переводимом поле и внутри строк повторителей — язык по умолчанию. Ресурс `vacancies://catalog`:
категории со слагами, открытые вакансии по порядку (адрес, `workplace`, город, `written_in`,
статус, форма), число закрытых по категориям, валюты и страна по умолчанию.

### 4.13. Демо

`VacanciesDemo`, `resources/demo/vacancies.json` (en и ru): три категории — «Разработка»,
«Продажи», «Поддержка»; семь вакансий, даты — **от момента посева** (`+30 days` и т. п.), иначе
демо само истекает: офисная полная занятость с вилкой в UAH в месяц; удалённый подрядчик в USD
в час; гибрид с частичной занятостью и зарплатой только словами; закрытая вручную; истёкшая
(`valid_through` — позавчера); черновик; одна в двух категориях, одна без категории, одна без
русского текста. `requires()` динамический: `inbox`, если стоит, — тогда демо заводит свою форму
«Отклик на вакансию» (`job-application`: имя, почта, телефон, файл резюме, письмо) через журнал
и выбирает её в открытых вакансиях. Картинок нет, `media` не нужен.

### 4.14. Регистрации

CLAUDE.md §4 «Новый composer-пакет надо прописать в `php/` четыре раза» и «Новый раздел панели
регистрируется в четырёх местах»:

- `php/composer.json` — `require`, `autoload-dev`, карта версий path-репозитория
  (`node scripts/sync-php-version.mjs`); `phpunit.xml.dist`; `phpstan.neon.dist`;
- `Setup\Catalogue` (`vacancies`), `Doctor\Checks\Helpers` (`vacancies`), `extra.webx` в
  `composer.json` пакета: `npm` — `@webx-ui/module-vacancies: ^0.1.0`, `panel` — `import {
vacancies }`, стиль, `register: "...vacancies()"` (два модуля — спред, как у событий);
- `apps/playground/src/panel/main.ts`, алиас в `apps/playground/vite.config.ts`, зависимость в
  `apps/playground/package.json` и lock, `server/panel/{index,screens,relations}.ts`;
- `scripts/php-smoke.sh` — **три места**: строка `composer require`, список пакетов в цикле и
  карта провайдеров; `scripts/packages.mjs` в `webx-cms.local`;
- иконка `briefcase` — `packages/module-admin/src/icons.test.ts` проверит сам; сайдбар доков —
  `apps/docs/.vitepress/config.ts`.

### 4.15. Тесты, которые обязательны

- `open()`/`closed()`: `is_closed` — закрыта; `valid_through` сегодня — открыта весь день,
  вчера — закрыта; «сегодня» в поясе приложения **не** UTC (тест с `Asia/Hong_Kong` и
  `date_default_timezone_set`, как у событий); `closed_reason` — `manual` побеждает; одно и то же
  на sqlite через `whereDate` (строкой сравнение ломается — §3).
- Страница: открытая — 200 с `JobPosting`; закрытая и истёкшая — 200, пометка, без `JobPosting`,
  с `noindex`, не в карте сайта; черновик и корзина — 404; нет должности на языке — 404.
- Разметка: `remote` — `TELECOMMUTE` без `jobLocation`, `hybrid` — оба, без страны — без
  `applicantLocationRequirements`; `baseSalary` с двумя числами, с одним (`value`), без валюты или
  единицы — нет; `validThrough` со смещением пояса; нет организации — нет разметки; пустые списки
  — нет свойств.
- Индекс: только открытые, группы по категориям в их порядке, вакансия в двух группах, группа
  «Другие», `?category=` по слагу, неизвестный слаг — пусто и 200, `index = false` — маршрута нет,
  страница `module-pages` по пути `{prefix}` встаёт в крошки; пустая приставка — отказ.
- `vacancies()`: каждая строка таблицы §4.8, `in()` по слагу и id, `take()` после видимости,
  `groups()` с `take()` на группу.
- Категории: слаг из названия на создании, повтор слага на том же языке — 422 `slug.<язык>`
  (корзина считается), категория с вакансиями не удаляется (общий `IsCategory`).
- Форма отклика: цель `inbox-form` в пикере (`GET /api/cms/relations/inbox-form`) видна без прав
  `inbox.*`; выбор ждёт в черновике и едет публикацией; удалённая форма — строки связи нет,
  `form` в карточке `null`; выключенная — `null` в карточке, выбрана в панели. **Без
  `module-inbox`** (свой `TestCase`, как `WithoutServicesTest` у событий): поля нет, `form` в
  `values` не роняет сохранение.
- Валюта: не из конфига — 422; сохранённая и убранная из конфига — проходит; `salary_max <
salary_min` — 422; число без единицы — 422.
- Повторители: `localized` внутри строки туда и обратно, пустая на языке страницы — не печатается,
  пустая строка выброшена, ошибка — под `duties.<n>.text`.
- Публикация ставит `posted_at` один раз; вторая публикация его не меняет. Дублирование — черновик
  с категориями и формой, слаги с суффиксом, `posted_at` пуст, позиция после оригинала, отказ
  посередине не оставляет строки. `close`/`reopen` у вакансии с правками — 409.
- `reorder`: не-список — 422 `ids`.

## 5. Отличия от событий — коротко

Для того, кто пишет по `module-events` как по образцу:

| У событий                                 | У вакансий                                         |
| ----------------------------------------- | -------------------------------------------------- |
| порядок по дате, список постранично       | `position`, перетаскивание, `reorder`, без страниц |
| `upcoming`/`past` по моменту              | `open`/`closed` по выключателю и дню               |
| категории со страницами, лидом и обложкой | категории без адреса: группы и фильтр `?category=` |
| галерея, `.ics`, `booking_url`            | нет; форма отклика — связь с `inbox-form`          |
| связь с услугами, цель `event`            | нет (решение 12)                                   |
| прошедшее — в карте сайта, фотоотчёт      | закрытая — `noindex`, не в карте сайта             |
| разметка `Event`                          | разметка `JobPosting`                              |

## 6. Слова

Ключи `webx-vacancies::*` на десяти языках панели — серверные и нужные панели: `module` (раздел, группа, категории), `panel` (вкладки Open/Closed/All/Bin, «Новая
вакансия», «Дублировать», «Закрыть набор», «Открыть набор», бейджи Closed/Expired, «Удалённо»,
пустые списки, подсказка про перетаскивание с фильтром), `vacancy` (подписи фактов на сайте:
где, занятость, зарплата, «до», «Вакансия закрыта», «Задачи», «Требования», «Мы предлагаем»,
«Открытых вакансий сейчас нет», «Другие вакансии», «Все»; восемь видов занятости; пять единиц —
«в час» … «в год»; `workplace` — три), `category` (ключ и его подсказка), `screen` (подписи и
`-help` экранов), `site`, `errors` (вилка, единица, валюта, страна, срок раньше размещения,
категория занята, слаг категории занят, `close` с правками), `editor` (панель действий, диалоги, история — как `event.php` у событий).
Счёт — в конце строки или отдельная строка на единицу (CLAUDE.md §4 про `:count`). В
`module-inbox` — одна новая строка `relations.form` («Форма») на десяти языках. Английский пол
панели — `messages.ts`, паритет с `lang/en` держит его тест (перечисления — вложенными ключами
сервера: `vacancy.employment.FULL_TIME`, `vacancy.workplace.onsite`).

## 7. Отложено

1. **Страницы категорий** (решение 2). Что для этого понадобится — ровно скелет категорий
   событий (`EventCategory`):
   - модели — `HasUrl`, `Visible`, `HasSeo`, `HasBreadcrumbs`; в `CategoryKind` — `prefix`
     (замыкание на `webx-vacancies.prefix`); `categoryFields()` — плюс `seo` (и `lead`, `cover`,
     если захотят вступление и картинку — тогда миграция `2026_01_02_*` с двумя колонками);
   - тип адреса `vacancy-category` — `Prefixed($prefix, Slug::class)` на одном уровне с
     вакансиями, `OnConflict::Fail`, `LinkSource`, обработчик и вьюха `category.blade.php`,
     крошки «индекс → категория», у вакансии — «индекс → главная категория → вакансия»;
   - экран: ключ становится `wx-slug`, патч `wx-seo` от `module-seo` на
     `vacancies.category-form`;
   - индекс: фильтр ссылками на страницы категорий, `?category=<слаг>` — 301 на страницу;
   - включение на живом сайте — `webx:routes:rebuild --type=vacancy-category`; слаг категории,
     совпавший со слагом вакансии, откажет (`Fail`) — переименовать руками, команда называет
     какой.

   **Хранить уже сейчас нужно только слаг** — и он хранится (решение 11): колонка есть в
   `category()`, заполняется из названия и держится уникальной, поэтому включение — это код и
   `rebuild`, а не перенос данных. SEO живёт в `seo_meta` по сущности, строка появится с первым
   сохранением карточки; адрес — строка реестра, её пишет `HasUrl`. Больше ничего заранее
   хранить не нужно.

2. **Печать формы отклика на странице вакансии** (решение 3): часть `vacancy/apply` в пакете —
   `<x-webx-inbox::form>` по выбранной форме, у закрытой — нет; заявка должна знать, на какую
   вакансию откликнулись — скрытое поле `vacancy` (id и должность) в заявке и колонка в списке
   заявок, то есть правка `module-inbox` (`Meta` заявки или системное скрытое поле). Решать на
   первом сайте, где это понадобится.
3. **Предложенный блок** «Открытые вакансии» — `VacanciesSource` для `wx-collection` и тип блока,
   как витрина рецептов (решение 6); хелпер и `groups()` к этому готовы.
4. **Общий список валют** в `module-admin`, если к вакансиям и тарифам добавится третий модуль с
   ценой: сейчас два одинаковых ключа конфига — дешевле, чем общий контракт.
5. Связь с услугами и цель `vacancy` для других модулей (решение 12) — одна строка в экране
   (`wx-relations`, `target: service`) и одна в провайдере.
6. Пагинация индекса и списка панели — если у сайта окажутся сотни вакансий.
7. Выгрузка на площадки (XML-фид для work.ua, Indeed) и импорт оттуда.
8. Несколько мест у одной вакансии («Киев или Львов») — сейчас это две вакансии через
   «Дублировать».

## 8. Выпуск

Выпущен 28.09.2026 в **v0.48.0** одним релизом с тарифами (#334 вакансии, #336 тарифы,
релизный #335): `webx-ui/module-vacancies` на Packagist, `@webx-ui/module-vacancies@0.1.0` на npm
(первая версия — пользователем из `changeset-release/main`, затем Trusted Publishing); релиз
поднял `@webx-ui/module-blocks` до 0.10.5. Стоит на обоих демо: `/careers` с группами и фильтром,
открытая вакансия с `JobPosting`, закрытая с пометкой и `noindex`.

Что решилось по ходу и пригодится дальше:

- **Кнопке «Закрыть набор» кросс-проверки не нужны:** `Closing` пишет черновик мимо
  `VacancyWriter::check()`, иначе вакансию с `posted_at` позже `valid_through` нельзя было бы
  закрыть. Панель предлагает закрыть и открыть только опубликованную; истёкшую по дате открывает
  дата в редакторе, а не меню.
- **Валюта, убранная из конфига,** проходит так: `VacancyForm` снимает `salary_currency` с входа,
  если она совпадает с сохранённой. Несуществующая форма — 422 `form` в `VacancyForm` до экрана:
  `RelationsType::store()` неизвестные id молча выбрасывает.
- **Даты — дни, а не моменты:** `valueFormat: "yyyy-MM-dd"` у пикера, в списке день собирается из
  частей (`days.ts`), а не `new Date('YYYY-MM-DD')`; `useDates()`/`WxDate` для этих полей не
  годятся. Проверено тестом в четырёх поясах.
- MCP, контроллеры и демо идут через одни `VacancyForm`, `Duplicate`, `Closing`, `Panel\Reorder`
  и `VacancyForm::blank()`. У экрана `wx-categories` — `main: false`: главной категории нет.
- **«Какая вакансия» в заявке — без правки `module-inbox`:** `<x-webx-inbox::form
:values="['vacancy' => $title]">` заполняет скрытое поле; демо-форма `job-application` его
  носит. Это половина §7 п. 2.
- При слиянии двух веток счётчик рядом со списком (`array_fill` в `DoctorTest`) не сводится
  сам — сверять руками.

Открыто:

- Формы и перетаскивание на настоящем телефоне — проверка за пользователем.
- `CategoryTools` у категории без адреса убирает слаг из строк списка, поэтому
  `vacancy_categories_update` по слагу не принимает; понадобится — это правка `CategoryTools`
  (признак «слаг есть, адреса нет» в `CategoryKind`), не модуля.
- Отложенное — §7.

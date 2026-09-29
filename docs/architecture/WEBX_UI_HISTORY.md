# Журнал изменений — спецификация

Статус: согласовано 29.09.2026, сделано в H1 (29.09.2026, итог — §9). Живёт в `webx-ui/module-admin` и
`@webx-ui/module-admin`; первый потребитель — ядро каталога
([`WEBX_UI_MODULE_CATALOG.md`](WEBX_UI_MODULE_CATALOG.md)).

## 1. Зачем и где

Частый запрос клиента: кто и что сделал с записью. «Вася изменил цену со 100 на 120», «Петя снял
товар с публикации». Черновики и версии этого не заменяют: у каталога их нет, а версии блоков
отвечают на «как было», а не «кто поменял что».

Журнал — в `module-admin`, а не отдельной библиотекой: без панели читать его некому, а
`module-admin` уже требует каждый модуль, так что не нужен ни новый пакет, ни регистрация в
четырёх местах. Кандидат `activity-log` из реестра пакетов закрывается этим документом.

## 2. Решения

1. **Одна запись на одно сохранение**, а не на поле: кто, когда, откуда, над чем, и список
   изменений `{ поле, было, стало }`.
2. **Источник обязателен:** `panel`, `mcp`, `import`, `bulk`, `api`, `console`. Кто — администратор
   (для MCP — администратор, подключивший агента, и id гранта).
3. **Прогон — одна запись-родитель** (импорт, массовое действие) и строки по сущностям только с
   реально изменёнными полями. Иначе прайс на 40 тысяч строк даёт миллион записей в неделю.
4. **Срок хранения — конфиг,** чистка — команда по расписанию, как `PruneVersionsCommand`.
5. **Показ — узел экрана `history`**: вкладка «История» добавляется в любую форму одной строкой
   описания экрана или патчем проекта. Общий раздел «Журнал» — потом, схема его позволяет.

## 3. Схема

`admin_history`:

| Колонка        | Тип                     | Примечание                                                                                 |
| -------------- | ----------------------- | ------------------------------------------------------------------------------------------ |
| `id`           | bigint                  |                                                                                            |
| `parent_id`    | FK на себя, null, index | запись прогона для строк импорта и массовых                                                |
| `subject_type` | string(64)              | тип сущности (`catalog.product`), не класс                                                 |
| `subject_id`   | bigint, null            | null у записи прогона                                                                      |
| `event`        | string(32)              | `created`, `updated`, `deleted`, `restored`, `published`, `unpublished`, `run`             |
| `source`       | string(16)              | §2, п. 2                                                                                   |
| `admin_id`     | bigint, null            | без внешнего ключа, как `entity_notes.admin_id`: каркас не знает таблицу админов           |
| `admin_name`   | string, `''`            | снимок имени — удалённый админ не обезличивает журнал                                      |
| `grant_id`     | bigint, null            | подключение агента при `source = mcp` — тот же id, что в `mcp_calls.grant_id`              |
| `changes`      | json, null              | `[{ field, label?, from, to }]`; длинное — `{ field, long: true, from_length, to_length }` |
| `summary`      | json, null              | у прогона: что, сколько, профиль; по закрытии дописываются `rows` и `failed`               |
| `created_at`   |                         |                                                                                            |

Индексы: `(subject_type, subject_id, created_at)`, `created_at` (для чистки), `parent_id`. У
прогона `subject_type` — тип, над которым он шёл (по нему проверяется право), `subject_id` —
null. Внешнего ключа на администратора нет (было в первой редакции): пакет не знает, где живут
администраторы, а имя рядом — снимок.

## 4. Запись

- **Трейт `RecordsHistory`** (`WebxUi\Admin\History`) для обычных моделей: в `updated` берёт
  `getChanges()` и сравнивает `getRawOriginal()` с новым значением через каст модели (без
  аксессоров), так что `1` поверх `true` — не изменение. Пропускает `webx-admin.history.skip_fields`
  (`created_at`, `updated_at`, `deleted_at`, `lft`, `rgt`, `depth`, `password`, `remember_token`),
  ключ, `$hidden` и `encrypted`-касты; переводимые поля сравнивает по языкам (`name.ru`).
  `created`, `deleted`, `restored` — строки без изменений. Какие поля писать — `historyFields()`
  (список) или все, кроме `historySkipped()`; `historyValue(field, value)` — место, где id
  становится подписью. Модель без регистрации типа падает громко (`LogicException`).
  Опубликовать/снять — слово модуля, а не колонки: модуль пишет его сам через `History::record`.
- **`History::record(Model $subject, string $event, array $changes = [])`** — ручная запись
  (фасад `WebxUi\Admin\Facades\History` над `History\Journal`). `changes` — список
  `[{ field, label?, from, to }]` или `field => [from, to]`; `updated` без изменений не пишется
  и возвращает null. Её зовёт каталог из общей транзакции формы: изменения ядра и всех
  `ProductParts` — одной записью, а не по записи на модуль. Для типа без модели —
  **`History::recordFor(string $type, ?int $id, string $event, array $changes = [])`**.
- **`History::run(string $type, array $summary, Closure $work, ?string $source = null)`** —
  открывает запись прогона над типом; всё, что записано внутри, получает её `parent_id` и
  источник `$source` (`import`, `bulk`; по умолчанию — источник двери). `$work` получает запись
  прогона; результат `$work` возвращается. По закрытии в `summary` дописывается `rows`, при
  исключении — `failed` с текстом (и исключение летит дальше); сам прогон не откатывается —
  импорт, упавший на середине, сделал половину.
- **Источник и автор** — из `HistoryContext` (scoped): middleware `webx.history` (каркас
  дописывает его в конец `webx-admin.api_middleware` на `booting`, после `cms.auth`) ставит
  `panel` и администратора; `RegistryTool` в `webx-ui/mcp` на время обработчика ставит `mcp`,
  администратора и `grant_id`; без двери — `console` в консоли и `api` в HTTP (с пользователем
  гарда по умолчанию). Свой API сайта ставит на маршруты `webx.history:api`. Модули их не
  передают.
- **Тип сущности** — `HistoryTypes::register(type, model?, fields: [поле => подпись], permission:
право|[права], module?, label?)` в провайдере модуля. Подпись — ключ перевода или слова;
  переводится при чтении. Модуль по умолчанию — то, что до точки в типе.
- Длинные значения (rich-text) — не текстом целиком, а отметкой «изменено» с длиной (порог —
  `webx-admin.history.long_value`, 500 символов; массив меряется своим JSON); `from/to` у
  картинок и связей — подписи, не id.
- Запись журнала — внутри той же транзакции, что и изменение: откатилось сохранение — нет и
  записи.

## 5. Показ

- `GET /api/cms/history/{subject_type}/{id}?page&field&since` → `->paginate()` записей по 20,
  новые сверху, как есть (без обёртки `data`). Запись: `{ id, event, source, subject: { type,
id }, admin: { id, name } | null, grant_id, changes: [{ field, label, from, to, … }], run: {
id, summary } | null, created_at }`; подписи полей — из реестра на языке читателя. `field=name`
  находит и `name.ru`.
- `GET /api/cms/history/runs/{id}?page&search` → `{ run: запись + summary + rows, rows:
->paginate() }`; `search` — id сущности.
- Неизвестный тип — 404, нет права — 403 (слова — `webx-admin::history.*`).
- Узел экрана **`wx-history`** (имя по правилу реестра — полное имя компонента; компонент
  `WxHistory`, `kind: display`, `label` узла → заголовок): лента «кто — когда — откуда» и
  изменения «поле: было → стало». Дата — `WxDate`. Id записи узлу отдаёт редактор:
  `provideHistorySubject({ id })` (описание экрана одно на все записи, id в него не написать);
  или проп `id`. Пока id нет — «история начнётся с первого сохранения». Строка прогона —
  ссылка, открывающая прогон в диалоге с поиском по id записи. Вкладка «История» в форме:

  ```json
  {
    "id": "history",
    "type": "wx-tab",
    "label": "…",
    "children": [
      {
        "id": "history-feed",
        "type": "wx-history",
        "props": { "type": "catalog.product", "title": "" }
      }
    ]
  }
  ```

- Тип сущности регистрирует модуль вместе с подписями полей и правом на просмотр, чтобы
  журнал показывал «Цена», а не `price`, и знал, кому его показывать.

## 6. MCP

Агент пишет в журнал (источник `mcp`, автор — администратор, подключивший его, и id гранта) и
должен уметь его читать: «кто вчера поменял цену» — вопрос как раз к агенту. Инструменты
`module-admin`, только чтение:

- `history_get` — `subject_type`, `subject_id`, необязательные `since`, `field`, `page` →
  записи, новые сверху, с подписями полей;
- `history_runs` — прогоны (импорты, массовые действия) с фильтром по модулю, источнику и дате;
  с `run_id` — сам прогон и его строки с поиском по сущности.

Право — право на просмотр сущности, которое регистрирует модуль вместе с типом (§5): агент
редактора каталога видит историю товара, но не историю администраторов. Ресурс — список
зарегистрированных типов сущностей с подписями полей, чтобы агент знал, что спрашивать.

Как сделано: инструменты несёт модуль `history` (`History\Mcp\HistoryModule`, скоуп
`history:read`), который каркас регистрирует в момент первой регистрации типа и только если
установлен `webx-ui/mcp`; страницы у него нет, и меню его не показывает. На двери инструмент
стоит за любым правом какого-либо типа, а право конкретного типа проверяет обработчик (по
правилу `webx-ui/mcp`: пользователь без `HasPermissions` — stdio — не спрашивается).
`history_runs` фильтрует `module`, `source`, `since`, `until`; с `run_id` — прогон и строки,
`subject_id` — поиск. Ресурс — `history://types`.

## 7. Конфиг

`webx-admin.history`: `enabled` (`WEBX_HISTORY_ENABLED`), `retention_days` (365,
`WEBX_HISTORY_RETENTION_DAYS`), `prune_at` (`03:40`), `long_value` (500), `skip_fields`
глобально. Чистка — `webx:history:prune`, каркас сам ставит её в расписание ежедневно
(`onOneServer`, `withoutOverlapping`), пачками по 1000. Прогон уходит целиком и по своей дате:
его строки записаны после него, и по их датам у прогона, начатого у черты, отрезался бы хвост.

## 8. Тесты

Дифф по языкам; служебные поля не пишутся; запись откатывается вместе с сохранением; прогон
собирает строки под себя; источник `mcp` и автор-агент; удалённый администратор оставляет имя;
чистка удаляет старое пачками и не трогает свежие прогоны целиком; `history_get` без права на
просмотр сущности отказывает, `history_runs` показывает только прогоны доступных модулей.

## 9. Выпуск

Одна сессия H1, один PR (php + npm + changeset), до ядра каталога. Подключение трейта в
остальных модулях — по мере запросов, не в этой сессии. В конце сессия дописывает сюда «Итог H1».

### H1 — журнал в `module-admin`

```
Сессия H1 из §9 docs/architecture/WEBX_UI_HISTORY.md: журнал изменений в webx-ui/module-admin
и @webx-ui/module-admin. Первый потребитель — ядро каталога (WEBX_UI_MODULE_CATALOG.md, сессия
K1), его кода ещё нет.

Начало: git fetch claude; git worktree add ../webx-ui-history -b feat/admin-history claude/main.
Из worktree pnpm не запускать и preview_start по имени не звать (CLAUDE.md §4). PR открыть в
конце, в очередь не ставить.

Прочитать: эту спеку целиком; WEBX_UI_MCP_ACCESS.md §6 (право инструмента) и как пишется
mcp_calls — оттуда брать автора-агента и id гранта; WEBX_UI_SCREENS.md — новый тип узла;
docs/pitfalls/laravel-and-php.md и vue-and-tests.md. Образцы в module-admin:
Console/PruneVersionsCommand (чистка пачками по расписанию), Versions (запись в транзакции
сохранения), Notes (лента под сущностью), Doctor.

Сделать: миграция admin_history §3; RecordsHistory, History::record и History::run §4;
HistoryContext и его установка в middleware панели, в MCP и в консоли; реестр типов сущностей с
подписями полей и правом на просмотр §5; API §5; MCP-инструменты history_get и history_runs и
ресурс типов §6; конфиг и webx:history:prune §7; узел экрана history в npm (лента, «было →
стало», WxDate, ссылка на прогон) с тестами и демо на плейграунде — на фейковой сущности.
Тесты §8. Changeset на @webx-ui/php и @webx-ui/module-admin. Полный гейт CLAUDE.md §5 и
php-гейт.

Если сигнатуры разошлись со спекой — поправить спеку тем же коммитом: K1 пишет по ней. В
конце — «Итог H1» в §9, коммит по именам файлов, пуш в claude, PR.
```

### Итог H1

Сделано 29.09.2026 одним PR (php + npm + changeset на `@webx-ui/php` и `@webx-ui/module-admin`).

- **php, `module-admin`:** миграция `admin_history` (§3, с `grant_id` и без внешнего ключа на
  администратора); `History\HistoryEntry`, `HistoryContext`, `HistoryTypes`/`HistoryType`,
  `Journal` за фасадом `Facades\History`, трейт `RecordsHistory`, `Changes`, `HistoryReader`,
  `HistoryPresenter`, `HistoryPruner`; middleware `webx.history`; `HistoryController` на двух
  адресах §5; модуль `history` с `history_get`, `history_runs` и `history://types` (§6); конфиг и
  `webx:history:prune` в расписании (§7); словарь `history.php` на десяти языках.
- **php, `mcp`:** `RegistryTool` ставит `mcp`, администратора и грант на время обработчика.
- **npm, `@webx-ui/module-admin`:** `WxHistory` и тип узла `wx-history`, `createHistoryApi`,
  `provideHistorySubject`/`useHistorySubject`, `historyValue`, слова `history.*`.
- **Плейграунд:** раздел «Журнал (демо)» — фейковая запись `demo.product` с вкладкой «История»
  (`server/panel/history.ts`, `project/demo.product.json`): сохранения в панели, агента,
  импорт и массовое действие, длинный текст, переводимое название, удалённый автор.
- **Тесты:** `module-admin/tests/HistoryTest.php` (всё из §8 плюс API, длинные значения, прогон с
  ошибкой), `mcp/tests/HistoryTest.php` (сохранение из инструмента — `mcp` с грантом),
  `HistoryFeed.test.ts`.

Разошлось со спекой и поправлено выше: узел называется `wx-history`, а не `history`; у `run`
первый аргумент — тип (по нему право и фильтр модуля), источник — четвёртым; есть `recordFor`
для типа без модели; `admin_id` без внешнего ключа, добавлен `grant_id`.

Для K1: зарегистрировать `catalog.product` и `catalog.category` в провайдере
(`HistoryTypes::register`, подписи — ключи `webx-catalog::…`, право — `catalog.view` и
`catalog.manage`); форма товара пишет одну запись через `History::record($product, 'updated',
$changes)` из общей транзакции, а не трейтом на каждой модели частей; импорт и массовые действия
— внутри `History::run('catalog.product', [...], fn, 'import'|'bulk')`; редактор зовёт
`provideHistorySubject({ id })`. Ключи `summary` прогона показываются как есть — называть их
словами, которые не стыдно показать (`what`, `profile`), или положить подписи в значения.

Открыто: общий раздел «Журнал» (схема позволяет, §2 п. 5); трейт в остальных модулях — по мере
запросов.

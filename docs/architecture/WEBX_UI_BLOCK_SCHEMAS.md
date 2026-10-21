# Схемы блоков: кто их ставит и когда

Статус: **спека на утверждение** (webx-ui#40, этап 2). Реализация — этап 3. После выпуска документ
описывает то, что есть; §9 сжимается в «Выпуск».

Схема блока — это тип в таблице `blocks`: манифест, поля, шаблон, стили, скрипт и версии. Документ
отвечает на один вопрос: какая команда и когда пишет тип в базу сайта. Формат файла типа — §17
`WEBX_UI_MODULE_BLOCKS.md`, слои темы — §9 `WEBX_UI_THEMES.md`, предложения модулей — §10.3 там же.

## 1. Правило

1. **Деплой и обновление пакетов схем не пишут.** `webx:boot`, `webx:theme:sync`, `composer update`
   (`post-update-cmd`) и `webx:module:add` типы не создают и не обновляют. Флага, который разрешил бы это на деплое, нет: случайно включённый флаг затёр бы правки клиента.
2. **Типы ставят только явные команды.** Это `webx:setup` (создание нового сайта — dev),
   `webx:blocks:import`, `webx:blocks:offered --install` и `webx:demo`. Все они запускаются руками и
   все **не трогают тип, который на сайте уже есть**.
3. **Существующий тип перезаписывается только по имени.** Импорт показывает, чем тип на сайте
   отличается от файла, и оставляет его как есть. Перезапись — `--replace=<slug>`, по одному
   типу; массовой перезаписи нет. Перезапись пишет новую версию, откат — через историю версий.
4. **После переноса схема принадлежит сайту.** Клиент правит её в панели или по MCP, и обновление
   пакетов её не трогает. Удалённый в панели тип не возвращается: деплой его больше не ставит, и
   отдельный механизм «скрыть блок» не нужен.

## 2. Жизненный цикл схемы

```
пакет (тема, модуль)        dev-сайт                  git сайта               стейджинг / прод
blocks/*.json        ──►  webx:setup            ──►  webx:blocks:export  ──►  webx:blocks:import
resources/blocks/*.json   webx:blocks:import --theme   theme/blocks/*.json     (руками, один раз)
                          webx:blocks:offered --install   или resources/blocks        │
                          адаптация в панели                                           ▼
                                                                          зона клиента: панель, MCP
```

1. **Источник.** Тема несёт `blocks/*.json` в слоях, модуль предлагает `resources/blocks/*.json`
   через `BlockOffers`. Это заготовки, а не состояние сайта.
2. **Dev.** Разработчик ставит заготовки явно (`webx:setup` для нового сайта, потом
   `webx:blocks:import --theme`, `webx:blocks:offered --install`), смотрит их и адаптирует под
   дизайн в панели.
3. **Git.** Готовый тип выгружается: `webx:blocks:export --path=theme/blocks` (локальная тема) или
   в `resources/blocks`. Файл в git — предмет ревью и источник переноса.
4. **Перенос.** На стейджинге или проде человек один раз запускает `webx:blocks:import` с нужным
   источником. Новые типы создаются, существующие показываются расхождением.
5. **Сайт.** Дальше тип правит клиент. Следующий перенос того же типа — снова явный импорт с
   `--replace=<slug>`.

## 3. Команды после перехода

| Команда                                | Схемы блоков                                                                                             | Что меняется                                                                                                                               |
| -------------------------------------- | -------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `webx:boot`                            | **не пишет**                                                                                             | уходит шаг `webx:blocks:import --publish` (`Plan::blockTypes`); шаг `webx:theme:sync` остаётся, он теперь только про ассеты                |
| `webx:theme:sync`                      | **не пишет**                                                                                             | публикует сборки и ассеты слоёв, стили модулей, спрайт иконок. `LayerSync` из него уходит; `--force` — только файлы; `--adopt` уходит      |
| `post-update-cmd` сайта (`theme:sync`) | **не пишет**                                                                                             | строка в `composer.json` уже установленных сайтов остаётся и сама становится безопасной                                                    |
| `webx:module:add`                      | **не пишет**                                                                                             | после установки печатает подсказку, если модуль предлагает блоки: `php artisan webx:blocks:offered --install --module=<id>` (§8, вопрос 2) |
| `webx:setup`                           | создаёт недостающие: блоки темы, потом предложения выбранных модулей                                     | `syncTheme` = `theme:sync` (ассеты) + `webx:blocks:import --theme --publish`; `installOfferedBlocks` — как сейчас                          |
| `webx:setup` повторно                  | создаёт только то, чего нет                                                                              | существующие типы не трогает: импорт по правилу §4, предложения — `present`                                                                |
| `webx:blocks:import`                   | создаёт новые; существующие — только `--replace=<slug>`                                                  | §4                                                                                                                                         |
| `webx:blocks:export`                   | не пишет в базу                                                                                          | без изменений                                                                                                                              |
| `webx:blocks:offered [--install]`      | создаёт недостающие, существующий slug — `present`                                                       | без изменений. Slug, который несёт тема, — `carried`, подсказка: `webx:blocks:import --theme` вместо `theme:sync`                          |
| `webx:demo`                            | демо ставит недостающие типы (`BlockOffers::install`, `BlocksDemo::seed`), `--remove` убирает по журналу | без изменений, кроме подсказки про slug темы (та же замена `theme:sync` → `import --theme`)                                                |
| панель «Импорт» (`ExchangeController`) | создаёт новые; существующие — только отмеченные человеком                                                | §5                                                                                                                                         |

`webx:demo` и `offered --install` остаются dev-командами: на проде их никто не зовёт сам, а
вызванные руками они не трогают существующих типов.

## 4. `webx:blocks:import`

```
webx:blocks:import [slug…]
    {--path=      : каталог с файлами; resources/blocks по умолчанию}
    {--theme      : вместо файлов — блоки слоёв темы (итоговый набор LayerBlocks::of($chain))}
    {--replace=*  : перезаписать эти типы файлом (повторяется или через запятую)}
    {--publish    : опубликовать то, что создано или перезаписано и прошло проверки}
    {--dry-run    : план без записи; с --publish — репетиция в откатываемой транзакции}
```

- **Источники.** Файлы (`--path`) или слои темы (`--theme`); оба сразу — отказ. Слои читаются тем же
  `LayerBlocks`, что сейчас читает `LayerSync`: перекрытие верхним слоем целиком, проверка
  совместимости перекрытия (`SchemaCompatibility`) и находки `problems()` — строки `failed`.
  `slug…` сужает набор в обоих случаях.
- **Работа — `Panel\Importer`**, общий с панелью: правила `BlockInput`, порядок графа вызовов, круг —
  отказ до записи, смена `kind` — отказ.
- **Типа нет** — создаёт (версия `source = import`), с `--publish` публикует. Из `--theme` пишет ещё
  `theme_source` (слой) и `theme_hash` — их читает отладочная панель (`module-debug`), логика на
  них больше не держится.
- **Тип есть и совпадает** с файлом (по `TypeHash`, не по байтам) — `unchanged`.
- **Тип есть и отличается** — `differs`: ничего не пишет и печатает расхождение (§4.1). Не важно,
  кто его менял — клиент, модуль или старая версия темы.
- **`--replace=<slug>`** — для такого типа пишет новую версию из файла (`source = import`,
  заметка «Replaced from <файл или слой>»), с `--publish` публикует; строка `replaced`, в ней — с
  какой версии на какую. Предыдущая версия, включая открытый черновик, остаётся в истории.
- **Отказы `--replace`.** `--replace=*`, `--replace=all` и пустое значение — отказ без записи:
  «name each type». Slug, которого нет в наборе источника, — отказ без записи (опечатка не должна
  молча пройти). Slug, который `created` или `unchanged`, — не ошибка, флаг просто не нужен.
- **Код выхода.** `0`, если нет `failed` и отказов; `differs` — не ошибка. `1` — `failed` или
  отказ `--replace`.
- **Сравнение** — с версией, которую правят: черновик, если он открыт, иначе опубликованная. Это
  то же, с чем `Importer` сравнивает и сейчас. Открытый черновик называется в строке.

### 4.1 Вывод

```
  Block types — theme layers: theme-default, local

  hero ............................................. created · v1 · published
  text ............................................. unchanged
  cards ............................................ differs, kept
      fields    + subtitle  − badge  ~ items (repeater → list)  ~ title (localized)
      template  differs (+8 −2 lines)
      styles    differ (+3 −0 lines)
      row       title, icon
  contact .......................................... differs, kept — a draft is open (v5)
      script    differs (+1 −1 lines)
  media-text ....................................... replaced · v3 → v4 · published v4
  cta .............................................. failed — The template calls [x-button] that is not on this site.

  2 types differ from their files and were kept. To take a file's version, name it:
    php artisan webx:blocks:import --theme --replace=cards --replace=contact
```

Что сравнивается (новый класс `Panel\TypeDiff`, оба документа после нормализации `BlockInput`,
как в `TypeHash`):

| Строка     | Содержимое                                                                                                                                                                           |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `fields`   | поля схемы по ключу, раскладка (колонки, группы) не считается: `+` есть в файле и нет на сайте, `−` наоборот, `~` то же поле с другим типом или настройками (в скобках — что именно) |
| `template` | шаблон, счёт строк по `diff` строк                                                                                                                                                   |
| `styles`   | CSS                                                                                                                                                                                  |
| `script`   | скрипт                                                                                                                                                                               |
| `sample`   | образец (`sample`)                                                                                                                                                                   |
| `row`      | поля строки: `title`, `description`, `icon`, `group`, `allow`, `allowed_in` — из `BlockInput::values`                                                                                |

Строка показывается, только если отличается. `--dry-run` печатает то же, глаголы в будущем времени
(`would create`, `would replace`). Удалённое на сайте поле (`−`) с `--replace` вернётся вместе со
значениями: значения лишних полей хранятся (stray values, отчёт этапа 1, п.4), пока их не снёс
`webx:blocks:prune`.

## 5. Импорт в панели

`ExchangeController::import` идёт через тот же `Importer` и получает то же правило.

- **План** (`dry_run`): строки со статусом `differs` несут `diff` — те же группы, что §4.1, данными:
  `{ fields: { added: [], removed: [], changed: [{ key, what }] }, template, styles, script, sample, row: [] }`
  (для текста — `{ added, removed }` строк).
- **Подтверждение потипно.** В `BlockImportDialog` у строки `differs` — переключатель «Replace»,
  по умолчанию выключен, под строкой — свёрнутое расхождение. Массового «выбрать все» нет.
- **Импорт** — тот же файл и `replace: [slug, …]` — отмеченные строки. Неотмеченные `differs`
  остаются как есть. Сервер проверяет `replace` по §4: slug не из файла — 422 до записи.
- **Слова** — в словаре `module-blocks` (en, ru): статусы `differs`, `replaced`, подпись переключателя
  и подсказка «The site's version stays unless you replace it».
- **Права** не меняются: импорт — `blocks.manage` + `webx-blocks.editing`.

MCP-инструмента импорта нет; если появится — то же правило и параметр `replace`.

## 6. Переход

- **Уже установленным сайтам ничего делать не надо.** Типы у них уже стоят, строка `theme:sync` в
  `post-update-cmd` становится безопасной сама. Стартер (`map`) и сайт студии (`services`)
  перестают получать отказ `theme:sync` на деплое без правки.
- **Новые блоки темы и их обновления** больше не приходят сами. Разработчик ставит их на dev,
  смотрит, выгружает и переносит явным импортом (`webx:blocks:import --theme --dry-run` покажет,
  что нового и что отличается).
- **Сайты с `resources/blocks` в репозитории** больше не получают их при `webx:boot`. Перенос —
  `webx:blocks:import --publish` руками.
- **Вызовы `--adopt`** (скрипты, README сайтов) падают как «option does not exist» — заметка в
  changeset.
- **Релиз** — minor с заметкой в changeset `@webx-ui/php` («deploy no longer writes block types;
  import with `webx:blocks:import`») и, при правке диалога, `@webx-ui/module-blocks`.

## 7. План реализации (этап 3)

### 7.1 Код

1. `module-admin/src/Boot/Plan.php` — убрать `blockTypes()` и его вызов.
2. `themes/src/Console/SyncCommand.php` — убрать цикл по `SyncStep`, `--adopt`, слова про блоки в
   описании и `--force`. Контракт `Contracts\SyncStep` и тег оставить (публичный контракт, вопрос 3).
3. `module-blocks` — `BlocksServiceProvider` больше не тегирует `LayerSync`; `Themes\LayerSync`
   удалить, её проверки (`refusal`, словарь, `incompatible`) перенести в источник `--theme`
   импорта. `BlocksDemo::untouched` — удалить, если больше не нужен.
4. `module-blocks/src/Panel/Importer.php` — параметр `replace` (list of slugs), статусы `differs` и
   `replaced` вместо `updated`, `diff` в строке, проверка `replace` до записи, `theme_source` /
   `theme_hash` для документов из слоёв.
5. `module-blocks/src/Panel/TypeDiff.php` — новый, §4.1.
6. `module-blocks/src/Console/ImportCommand.php` — `--theme`, `--replace`, вывод §4.1, коды выхода.
7. `module-blocks/src/Http/Controllers/ExchangeController.php` — `replace` из запроса.
8. `packages/module-blocks` — `BlockImportDialog.vue` (переключатель, расхождение), `api.ts`,
   `types.ts` (`BlockImportStatus`, `diff`), словарь.
9. `module-admin/src/Console/SetupCommand.php` — `syncTheme`: `theme:sync`, затем
   `webx:blocks:import --theme --publish`; `syncAfterUpdates` без изменений.
10. `module-admin/src/Console/ModuleAddCommand.php` — подсказка про `offered --install`.
11. Подсказки `BlockOffers::CARRIED` (`OfferedCommand`) и `BlocksDemo` — `import --theme`.

### 7.2 Тесты

- `module-admin/tests/BootTest.php`: `it_brings_the_database_up_to_the_block_types_in_the_repository`
  (:153) — перевернуть в «boot не пишет типы, даже когда `resources/blocks` есть»; поправить :163,
  :183, :191.
- `module-admin/tests/SetupThemeTest.php` (:194, :222, :247): setup ставит блоки темы импортом до
  предложений; `post-update-cmd` по-прежнему `theme:sync`; повторный setup не трогает изменённый тип.
- `module-blocks/tests/ThemeSyncTest.php` → новый `ImportThemeTest`: создание, `unchanged`,
  изменённый в панели и по MCP — `differs` и не тронут, `--replace` пишет версию и прежняя
  восстанавливается, несовместимое перекрытие — `failed`, тип модуля под slug темы — `differs`
  (бывший отказ). Отдельно: `theme:sync` и `theme:sync --force` не меняют ни одного типа.
- `module-blocks/tests` импорт файлов (новый `ImportCommandTest` или существующий): `differs` не
  перезаписан, `--replace=<slug>`, `--replace=*` и неизвестный slug — exit 1 без записи, `--dry-run`
  с `--replace`, открытый черновик, вывод расхождения (`TypeDiffTest` — поля `+ − ~`, шаблон,
  стили, строка).
- `ThemeDefaultBlocksTest`: `an_untouched_demo_type_is_refused_with_the_way_out_and_adopted_when_asked`
  и `a_demo_type_somebody_changed_is_not_adopted` (:72, :239) — удалить вместе с `--adopt`;
  `an_offer_yields_to_a_slug_the_theme_carries` — оставить, сверить подсказку.
- `DemoTest` (:162, :185) — текст подсказки.
- `themes/tests/LayerBlocksTest` — `LayerBlocks` остаётся; тесты регистрации шага — по факту.
- Тест контроллера обмена: план с `diff`, `replace`, 422 на чужой slug. `BlockImportDialog.test.ts` —
  переключатель у `differs`, в запрос уходят только отмеченные.
- `TestCase` модулей (faq, team, menu, services, press, widgets, reviews, tariffs, recipes) ставят
  типы через `offered --install` — не меняются.
- `scripts/php-smoke.sh`: setup `--demo` и повторный setup проходят; добавить проверку, что вывод
  `webx:theme:sync` не содержит раздела «Block types».

### 7.3 Доки

- `WEBX_UI_THEMES.md` §9.2 (sync — только ассеты, блоки — `import --theme`), §10.3 (`carried`), упоминания
  `theme:sync` в §17; `WEBX_UI_MODULE_BLOCKS.md` §17 и §17.1 (правило §4–§5 отсюда); `WEBX_UI_NEW_SITE.md`.
- `apps/docs/guide`: `styles.md:336-337`, `blocks.md:541-544`, `themes.md:186`, `new-site.md:469-471`,
  `collections.md:240`, `widgets.md:395`, `layout-regions.md:243,327`; страницы модулей с
  `offered --install` (faq, reviews, press, team, tariffs, recipes, menu, services, blog) — сверить, что
  обещания «поставит деплой» нет.
- README модулей (faq, menu, recipes, reviews, services, tariffs, team, press, widgets:273,349,
  module-inbox:121) и `AGENTS.md`: module-blocks:45,69-70, theme-default:42, faq:47, press:46, blog, menu,
  team, reviews, tariffs, services, widgets.
- Этот документ: §9 «Выпуск», статус в `README.md`.

## 8. Открытые вопросы

1. **Имя флага слоёв темы.** Рекомендую `--theme` (булев): источник у слоёв один — итоговый набор
   цепочки. Альтернатива `--from=theme` имеет смысл, только если появятся другие источники
   (предложения модулей); для них уже есть `webx:blocks:offered`.
2. **`webx:module:add` и предложения модуля.** Рекомендую только печатать подсказку с командой: ставить
   самому — нарушение п.1, а модуль на проде добавляют тем же путём, что и на dev.
3. **Контракт `SyncStep` без реализаций.** Рекомендую оставить интерфейс и тег: это публичный контракт
   `themes`, удалять его в minor незачем, а другой пакет может публиковать через него файлы.
4. **Колонки `theme_source` / `theme_hash`.** Рекомендую оставить и заполнять при `import --theme`:
   `module-debug` показывает по `theme_source`, из какого слоя тип; миграции не нужно. Удалить можно
   позже, если не пригодятся.
5. **Предупреждение в `webx:doctor`** о блоках темы, которых нет на сайте или которые отличаются.
   Рекомендую не делать сейчас: то же показывает `webx:blocks:import --theme --dry-run`, а срок жёсткий.

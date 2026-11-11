# @webx-ui/module-catalog-feeds

## 0.1.1

### Patch Changes

- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
  - @webx-ui/module-admin@0.37.0
  - @webx-ui/core@0.48.0
  - @webx-ui/module-catalog@0.11.1
  - @webx-ui/schema@0.11.2

## 0.1.0

### Minor Changes

- cd801c7: Товарный фид можно писать PHP-классом: контракт `FeedFormat` и реестр `FeedFormats` для структур,
  которые шаблон не описывает. Отбор, отсев, переменные, запись потоком, журнал и доставка — те же, что
  у шаблона. Формат предлагается в «Новом фиде» как пресет, `driver` берётся только из него. Вкладка
  «Шаблон» такого фида показывает класс вместо редактора. Класс, которого больше нет на сайте, валит
  сборку с понятной ошибкой, а старый файл продолжает отдаваться по тому же адресу и с тем же типом.

  `GET meta` отдаёт ещё `drivers`. Новая колонка `catalog_feed_builds.content_type`. Адрес файла теперь
  принимает любое расширение из латиницы и цифр, неверное для фида уводит на верное редиректом.

- cd801c7: Панель товарных фидов: список с адресом и пересборкой, «Новый фид» из пресета и экран
  `catalog.feed-form` с вкладками «Основное», «Шаблон», «Отбор», «Исключения», «Маппинги», «Метки» и
  «Журнал». Редактор трёх частей подсказывает переменные своей части, предпросмотр рендерит шаблон в
  браузере на значениях с сервера, «Перевыпустить ключ» предупреждает, что старый адрес перестанет
  работать. У формата CSV форма предупреждает: значения не защищены от формул, файл рассчитан на
  чтение программами.

  Новые маршруты `catalog/feeds`: `GET variables`, `GET facets`, `POST count`, `POST sample`,
  `POST {feed}/rebuild`, `GET {feed}/builds`, `GET {feed}/builds/{build}`, `{feed}/exclusions`.
  `GET meta` отдаёт ещё валюту сайта и статусы склада.

  В `module-catalog` контракты `StockStatuses` (его даёт `module-catalog-stock`) и `TypedFacet` (его
  реализует текстовое свойство в `module-catalog-properties`).

- cd801c7: New satellite of the catalogue, `webx-ui/module-catalog-feeds` and `@webx-ui/module-catalog-feeds`: the tables of product feeds, the feeds' CRUD API under `catalog/feeds` and the section «Catalog» → «Feeds» in the panel, with an empty list for now. The address with the feed's key goes only to `catalog.manage`, and a feed is always in the site's currency (`WEBX_CATALOG_CURRENCY`). Building and serving a file come next. After updating: `php artisan migrate`.

### Patch Changes

- cd801c7: Покрытие справки панели включено: `AssertsHelp` и `HelpCoverageTest` в каждом пакете с экраном, патчем или разделом, `HelpPackagesCoverageTest`, `scripts/help-coverage.test.mjs`, команда `webx:help --check`, гайд `panel-help`. Всё, что ещё без текста, перечислено в `help-pending.json` и вычёркивается волнами наполнения. Пакеты регистрируют `resources/help`, у всех маршрутов панели есть `meta.help`, формы в панели списка передают ключ своей шапке. Кнопка появится, когда у экрана будет текст.
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
  - @webx-ui/core@0.47.0
  - @webx-ui/module-admin@0.36.0
  - @webx-ui/module-catalog@0.11.0
  - @webx-ui/schema@0.11.1

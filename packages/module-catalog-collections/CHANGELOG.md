# @webx-ui/module-catalog-collections

## 0.2.0

### Minor Changes

- 764d8dd: Every code somebody types is made, not refused (#125, stage 2). The trait `HasCodes` of `localization` brings a model's code columns to their shape on every save, and `Model::codeFor()` does the same for a request before its own check. That covers the panel, MCP, the import and a plain `save()`, and only an empty or a taken code is refused. `Slug::code()` takes `Slug::BOTH` / `Slug::BOTH_UNDERSCORED` for a key that takes `-` and `_`, and a `$lead` for a code that must start with a letter. `wx-code` and `WxCodeField` take `separator` `-_` / `_-`, `lead` and `localized`. Converted fields: label, property code and toggle slug, collection key, block slug, role slug, task project slug, menu key, banner place key, form slug, form field name, inbox status key, public order status code, checkout field key, user group code, delivery point kind, configurator key and the keys of its schema. «тормозные колодки» becomes `tormoznye-kolodki` in every one of them. `CodeCoverageTest` and `codeCoverage.test.ts` fail on a new code field that skips this.

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

- cd801c7: Раздел «Подборки товаров» в панели: список с поиском по заголовку и ключу, копированием тега,
  «Требует внимания», выключенными и корзиной; форма `catalog.collection-form` с ручным списком и
  перетаскиванием или с фильтром (пресет, категория, набор, «только со скидкой», сортировка) и живым
  предпросмотром на несохранённых значениях: в нём сказано, почему товар ручного списка не попадёт на
  сайт. API `/api/cms/catalog/collections`, модуль в `webx:setup`, поле блока `wx-catalog-collection`.
  Первую версию npm-пакета публикует человек.
- cd801c7: Подборки: окончательное удаление из вкладки «Корзина» с подтверждением, как в страницах и сниппетах: маршрут `DELETE {id}/purge` (право `catalog.manage`) и инструмент `catalog_collections_purge` с `dry_run`. Удалить навсегда можно только запись в корзине; её ключ освобождается, а вставки с этим ключом ничего не печатают.
- cd801c7: «Где используется» у подборок: в списке — «Мест: N», за словами — страницы и регионы, где подборка стоит блоком, с пометками «Только в черновике» и «Не опубликовано». Считаются блоки и регионы, панель прямо говорит, что вставки в текст и теги в шаблонах не видны. Счёт идёт одним обходом на кеше группы `block-usage`; без `webx-ui/module-blocks` нет ни счётчика, ни подписи. `Usage::valued($slug, $field)` в `webx-ui/module-blocks` — места типа блока по значению поля.

### Patch Changes

- cd801c7: Подборки: корзина — вкладкой «Корзина» над списком, как в других разделах, с действием «Восстановить»; отбор «В корзине» из фильтров убран.
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

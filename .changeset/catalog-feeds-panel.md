---
'@webx-ui/php': minor
'@webx-ui/module-catalog-feeds': minor
---

Панель товарных фидов: список с адресом и пересборкой, «Новый фид» из пресета и экран
`catalog.feed-form` с вкладками «Основное», «Шаблон», «Отбор», «Исключения», «Маппинги», «Метки» и
«Журнал». Редактор трёх частей подсказывает переменные своей части, предпросмотр рендерит шаблон в
браузере на значениях с сервера, «Перевыпустить ключ» предупреждает, что старый адрес перестанет
работать.

Новые маршруты `catalog/feeds`: `GET variables`, `GET facets`, `POST count`, `POST sample`,
`POST {feed}/rebuild`, `GET {feed}/builds`, `GET {feed}/builds/{build}`, `{feed}/exclusions`.
`GET meta` отдаёт ещё валюту сайта и статусы склада.

В `module-catalog` контракты `StockStatuses` (его даёт `module-catalog-stock`) и `TypedFacet` (его
реализует текстовое свойство в `module-catalog-properties`).

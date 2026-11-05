---
'@webx-ui/php': minor
'@webx-ui/module-admin': patch
---

Доставка товарного фида в `module-catalog-feeds`: постоянный адрес `/feeds/{key}.{ext}` с ETag,
Last-Modified и 304, перевыпуск ключа (`POST catalog/feeds/{id}/reissue-key`), сторож
`webx:catalog:feeds` с защитой от частых сборок, чистка `webx:catalog:feeds-prune` и проверка
`FeedsCheck` в `webx:doctor`. Файлы фида удаляются вместе с ним. Миграция `catalog_feeds`:
`php artisan migrate`. Сторож и чистка идут по расписанию сайта, нужен работающий `schedule:run`.

В журнале появилось событие `key_reissued` (`HistoryEntry::KEY_REISSUED`): ключ в строку не попадает.

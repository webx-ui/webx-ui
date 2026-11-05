---
'@webx-ui/php': minor
---

Маппинги товарного фида в `module-catalog-feeds`: значения склада, категории площадки с наследованием
от предка, импорт справочника Google по адресу (очередью) и файлом, пять источников
`custom_label_0…4` (метка, ценовой диапазон, свойство, константа, пусто), проверка источников `mpn` и
меток при сохранении. В журнале сборок появились `unmapped_category` и `label_values`. Миграция
`catalog_feed_taxonomies`: `php artisan migrate`.

В `module-catalog` новый необязательный контракт `WebxUi\Catalog\Variables\FirstValues`: переменная с
несколькими значениями отдаёт первое целиком. `module-catalog-properties` его реализует.

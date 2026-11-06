---
'@webx-ui/module-catalog': minor
'@webx-ui/module-catalog-landings': patch
'@webx-ui/php': patch
---

Поле набора фасетов стало общим компонентом `WxCatalogFacetSet` в `@webx-ui/module-catalog`: фасеты и
число товаров приходят пропсами, набор уходит через `v-model`, в API компонент не ходит. Там же
`cleanSet` и типы `BaseFacet`, `SetChoice`, `FacetSetValue`. Посадочная оставила обёртку с запросами и
предупреждением о дубликате, её экспорты прежние. Восемь строк поля переехали из группы `landing`
посадочных в `panel` каталога (`facet-set-*`): переопределения этих строк на сайте надо перенести.

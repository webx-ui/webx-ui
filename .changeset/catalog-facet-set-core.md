---
'@webx-ui/php': patch
---

Хранимый набор фасетов и список фасетов основы переехали из посадочных в ядро каталога:
`WebxUi\CatalogLandings\Catalog\LandingSet` теперь `WebxUi\Catalog\Filter\FacetSet`,
`WebxUi\CatalogLandings\Catalog\BaseFacets` — `WebxUi\Catalog\Filter\BaseFacets`. Спутник может брать
набор, не завися от посадочных. Поведение посадочных не изменилось.

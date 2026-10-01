---
'@webx-ui/module-catalog': minor
'@webx-ui/php': minor
---

The exchange of the catalogue in the panel (E3 of the exchange series). «Exchange» stands in the `···` of the products after «Deleted»: every import and export with its totals, the first hundred errors and all of them as CSV, the finished file of an export and what an import changed in the journal, with runs still going asked after every two seconds. Import is a wizard on `WxSteps` — a file uploaded in pieces or an address, its columns matched to the catalogue's with five rows of the file under each, then the settings — that checks the file without writing or runs it, and saves it all as a profile. Export writes the list's selection from «Actions» or the whole catalogue from «Exchange», by a profile or by columns chosen with their languages. Profiles have a list and a form. The settings of an import and the head of a profile are the described screens `catalog.exchange-import` and `catalog.exchange-profile`, registered by the server with their words in English and Russian. New exports: `createExchangeApi`, `useRunPolling`, `EXCHANGE_PURPOSE` and the exchange's types, and the pages as `WxCatalogExchange*`.

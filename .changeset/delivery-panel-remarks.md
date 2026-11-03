---
'@webx-ui/php': minor
'@webx-ui/module-delivery': minor
---

Delivery panel after review. Regions of a country have an order of their own (`geo_regions.position`, a new region goes last): dragged by a grip like the countries, kept by `PUT …/geography/regions/order`, and read in that order by the list, the locality form and the filters. A locality row has a `···` menu — edit, switch off or on (`POST …/geography/localities/{id}/deactivate`), delete by decision 42 (switched off when something holds it, the dialog says which beforehand). The locality search says «+ Add «typed»» inside its own list when nothing is found, opening the short form with that name; the locality of a point goes back to the one chosen before on Esc or «Cancel». The point and locality dialogs hold their fields without a card inside, latitude and longitude side by side once the dialog has room (a container query on the row). «New kind of points» is a link beside its select; the methods list keeps off the top of its card; the `···` of a country and the bin of a popular town stand off the row's edge.

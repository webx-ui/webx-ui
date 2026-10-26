---
'@webx-ui/php': minor
'@webx-ui/module-configurator': minor
---

A configurator and the catalogue, linked both ways from the configurator's side — the catalogue
knows nothing of it, and without the catalogue nothing changes.

- **A product opens a configurator.** The product's form gets a card «Configurator» with the key and
  where it starts (`configurator.key`, `configurator.preset`, also for `catalog_products_update`),
  kept in the configurator's own table `configurator_product`. Its page shows «Calculate in the
  configurator», leading to the configurator's new **page** setting with the preset in the address,
  which the server reads as `initial`.
- **An option shows a product «in real life».** `"product": "<SKU>"` on an option, picked by name
  or article number in the editor (`product.invalid`, `product.unknown`): while it is chosen, up to
  four pictures of the product's gallery and a link to it, under the configurator — nothing for a
  product the site does not show.
- **MCP:** `page` in `configurators_create`/`_update`; `configurators_get` answers the page, the
  options' products and the products that open it. The demo links its constructions to the
  catalogue's demo products when they are there.

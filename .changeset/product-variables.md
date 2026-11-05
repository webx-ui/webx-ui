---
'@webx-ui/php': minor
---

The catalogue gets `ProductVariables`, the registry of what a template may say about a product: the columns of the exchange by themselves, the computed values of a product feed (absolute addresses, pictures, prices, texts, `gtin`, `identifier_exists`, `product_type`) and the variables of the brands, labels, stock and properties satellites, `property.<code>` among them. Values are read for a page of products at a time. A site adds its own with `ProductVariable` or `ProductVariableProvider`; an exchange column that must stay out of templates implements `PrivateColumn`. `module-catalog` and the four satellites now require `webx-ui/templates`.

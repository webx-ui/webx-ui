---
'@webx-ui/php': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': minor
'@webx-ui/module-banners': minor
'@webx-ui/module-blocks': minor
'@webx-ui/module-catalog-collections': minor
'@webx-ui/module-configurator': minor
'@webx-ui/module-delivery': minor
'@webx-ui/module-inbox': minor
'@webx-ui/module-menu': minor
'@webx-ui/module-orders': minor
'@webx-ui/module-tasks': minor
'@webx-ui/module-users': minor
---

Every code somebody types is made, not refused (#125, stage 2). The trait `HasCodes` of `localization` brings a model's code columns to their shape on every save, and `Model::codeFor()` does the same for a request before its own check. That covers the panel, MCP, the import and a plain `save()`, and only an empty or a taken code is refused. `Slug::code()` takes `Slug::BOTH` / `Slug::BOTH_UNDERSCORED` for a key that takes `-` and `_`, and a `$lead` for a code that must start with a letter. `wx-code` and `WxCodeField` take `separator` `-_` / `_-`, `lead` and `localized`. Converted fields: label, property code and toggle slug, collection key, block slug, role slug, task project slug, menu key, banner place key, form slug, form field name, inbox status key, public order status code, checkout field key, user group code, delivery point kind, configurator key and the keys of its schema. «тормозные колодки» becomes `tormoznye-kolodki` in every one of them. `CodeCoverageTest` and `codeCoverage.test.ts` fail on a new code field that skips this.

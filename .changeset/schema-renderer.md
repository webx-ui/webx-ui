---
'@webx-ui/schema': minor
---

Screens as JSON, the client half: the node format from the specification (`id`, `type`, `name`, `label`, `help`, `localized`, `props`, `children`, `slot`, `visible`, `can`), `applyPatch` with add / remove / replace / move / set, `validateScreen` and `validatePatch`, conditional visibility, the `trans::` marker, a type registry built on the core components, and `WxScreenRenderer` that draws a tree against a model. The package now ships `schemas/screen.schema.json` and `schemas/patch.schema.json`. The earlier placeholder contracts (`SchemaNode`, action registries, `createComponentRegistry`) are gone; `DataAdapter`, `Paginated` and `ListQuery` stay for the adapter to come.

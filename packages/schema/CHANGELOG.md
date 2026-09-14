# @webx-ui/schema

## 0.1.1

### Patch Changes

- Updated dependencies [47d52ee]
  - @webx-ui/core@0.18.0

## 0.1.0

### Minor Changes

- 64a7895: Screens as JSON, the client half: the node format from the specification (`id`, `type`, `name`, `label`, `help`, `localized`, `props`, `children`, `slot`, `visible`, `can`), `applyPatch` with add / remove / replace / move / set, `validateScreen` and `validatePatch`, conditional visibility, the `trans::` marker, a type registry built on the core components, and `WxScreenRenderer` that draws a tree against a model. The package now ships `schemas/screen.schema.json` and `schemas/patch.schema.json`. The earlier placeholder contracts (`SchemaNode`, action registries, `createComponentRegistry`) are gone; `DataAdapter`, `Paginated` and `ListQuery` stay for the adapter to come.

## 0.0.2

### Patch Changes

- 16106cb: Bootstrap the monorepo: design tokens generated from JSON, `WxButton` / `WxInput` / `WxCard`, schema
  contracts, VitePress documentation and the CI / release / docs pipelines.

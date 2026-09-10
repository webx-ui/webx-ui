<div align="center">

# WebX UI

**Vue 3 design system for Laravel-backed admin panels.**

[Documentation](https://webx-ui.github.io/webx-ui/) · [npm](https://www.npmjs.com/org/webx-ui) · [Roadmap](https://webx-ui.github.io/webx-ui/guide/roadmap)

[![CI](https://github.com/webx-ui/webx-ui/actions/workflows/ci.yml/badge.svg)](https://github.com/webx-ui/webx-ui/actions/workflows/ci.yml)
[![npm](https://img.shields.io/npm/v/@webx-ui/core.svg)](https://www.npmjs.com/package/@webx-ui/core)
[![license](https://img.shields.io/badge/license-MIT-blue.svg)](./LICENSE)

</div>

## Packages

| Package                                | Version                                                                                                   | Description                                                        |
| -------------------------------------- | --------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| [`@webx-ui/tokens`](./packages/tokens) | [![npm](https://img.shields.io/npm/v/@webx-ui/tokens.svg)](https://www.npmjs.com/package/@webx-ui/tokens) | Design tokens as `--wx-*` CSS variables, light and dark            |
| [`@webx-ui/core`](./packages/core)     | [![npm](https://img.shields.io/npm/v/@webx-ui/core.svg)](https://www.npmjs.com/package/@webx-ui/core)     | Vue 3 components styled entirely through those tokens              |
| [`@webx-ui/schema`](./packages/schema) | [![npm](https://img.shields.io/npm/v/@webx-ui/schema.svg)](https://www.npmjs.com/package/@webx-ui/schema) | Contracts for rendering admin screens from JSON (work in progress) |

## Quick start

```bash
pnpm add @webx-ui/core @webx-ui/tokens
```

```ts
import { createApp } from 'vue'
import { WebxUI } from '@webx-ui/core'
import '@webx-ui/core/style.css'

createApp(App).use(WebxUI).mount('#app')
```

```vue
<template>
  <wx-card title="New page">
    <wx-input v-model="title" placeholder="Title" clearable />
    <template #footer>
      <wx-button type="primary" @click="save">Save</wx-button>
    </template>
  </wx-card>
</template>
```

Full guide: **https://webx-ui.github.io/webx-ui/**

## Principles

- Components read `--wx-*` variables — no hard-coded colours, so a project restyles the system by
  overriding tokens.
- Components never call an API: data comes in through props, changes leave as events.
- `WxTable` (planned) consumes Laravel's `->paginate()` payload as-is.
- Element Plus is a checklist, not a dependency. Headless behaviour leans on
  [Reka UI](https://reka-ui.com/).
- `vue` stays in `peerDependencies`.

## Repository

```
packages/tokens     @webx-ui/tokens
packages/core       @webx-ui/core
packages/schema     @webx-ui/schema
apps/docs           VitePress documentation site (not published)
apps/playground     Vite sandbox for local development (not published)
```

## Development

```bash
pnpm install
pnpm build          # build all packages
pnpm dev            # Vite playground on :5174
pnpm docs:dev       # documentation site
pnpm test           # Vitest
pnpm lint           # ESLint
pnpm typecheck      # vue-tsc / tsc across the workspace
```

See [CONTRIBUTING.md](./CONTRIBUTING.md) for conventions, changesets and the release process.

## License

[MIT](./LICENSE)

# @webx-ui/core

Vue 3 UI components for admin panels. Every component is styled through
[`@webx-ui/tokens`](../tokens) CSS variables — no hard-coded colours — and never talks to an API:
data comes in through props, changes go out through events.

## Install

```bash
pnpm add @webx-ui/core @webx-ui/tokens vue
```

## Usage

Global registration:

```ts
import { createApp } from 'vue'
import { WebxUI } from '@webx-ui/core'
import '@webx-ui/core/style.css'
import App from './App.vue'

createApp(App).use(WebxUI).mount('#app')
```

Named imports (tree-shakeable):

```vue
<script setup lang="ts">
import { WxButton, WxCard, WxInput } from '@webx-ui/core'
import '@webx-ui/core/style.css'

const title = ref('')
</script>

<template>
  <wx-card title="New page">
    <wx-input v-model="title" placeholder="Title" clearable />
    <template #footer>
      <wx-button type="primary">Save</wx-button>
    </template>
  </wx-card>
</template>
```

`@webx-ui/core/style.css` already includes the token variables, so importing
`@webx-ui/tokens/tokens.css` separately is optional.

## Implemented

`WxButton`, `WxInput`, `WxCard`. The full component roadmap lives in
[the docs](https://webx-ui.github.io/webx-ui/guide/roadmap.html).

## Conventions

- Components are declared as `WxButton` and used as `<wx-button>` in templates.
- Classes follow BEM with a `wx-` prefix: `.wx-button`, `.wx-button--primary`, `.wx-button__label`.
- Each component ships `Component.vue` + `types.ts` + `Component.test.ts` + a docs page.

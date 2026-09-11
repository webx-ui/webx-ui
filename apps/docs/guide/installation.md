# Installation

```bash
pnpm add @webx-ui/core @webx-ui/tokens
```

`vue` is a peer dependency (`^3.5`) — install it in the host app.

## Global registration

```ts
// main.ts
import { createApp } from 'vue'
import { WebxUI } from '@webx-ui/core'
import '@webx-ui/core/style.css'
import App from './App.vue'

createApp(App).use(WebxUI).mount('#app')
```

All components become available in templates as `<wx-button>`, `<wx-input>`, `<wx-card>`.

## Named imports

Prefer this when bundle size matters — unused components are tree-shaken away.

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxCard, WxInput } from '@webx-ui/core'
import '@webx-ui/core/style.css'

const title = ref('')
</script>

<template>
  <wx-card title="New page">
    <wx-input v-model="title" placeholder="Title" clearable />
    <template #footer>
      <wx-button type="primary" @click="save">Save</wx-button>
    </template>
  </wx-card>
</template>
```

`@webx-ui/core/style.css` bundles the token variables, so a separate
`import '@webx-ui/tokens/tokens.css'` is only needed when using the tokens without the components.

## The typeface

Out of the box the library asks for the platform's own UI font — Segoe UI on Windows, San Francisco
on macOS, Roboto on Android. That costs nothing to load and never looks foreign, but it does mean
your admin panel looks slightly different on every operating system.

One import changes that. It brings **Inter**, self-hosted, and points the type token at it:

```ts
import '@webx-ui/tokens/fonts.css'
```

The faces ship inside the package (`@fontsource-variable/inter`, SIL Open Font License) — no
request to Google, no `<link>` in your Blade layout, nothing to configure at the CDN. They are
variable and split by alphabet, so a page that shows Cyrillic downloads the Cyrillic file and
nothing else, and text paints immediately in the fallback while the file arrives.

Prefer your own typeface? Skip the import and set the token:

```css
:root {
  --wx-font-family-sans: 'Graphik', -apple-system, 'Segoe UI', Roboto, sans-serif;
}
```

## Using tokens alone

```ts
import '@webx-ui/tokens/tokens.css'
```

```css
.my-panel {
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-lg);
  padding: var(--wx-space-16);
}
```

## Inside a Laravel app

Laravel ships with Vite. Register the Vue plugin and import the styles from your admin entry point:

```js
// vite.config.js
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default {
  plugins: [laravel({ input: ['resources/js/admin.ts'], refresh: true }), vue()],
}
```

```ts
// resources/js/admin.ts
import { createApp } from 'vue'
import { WebxUI } from '@webx-ui/core'
import '@webx-ui/core/style.css'
import Admin from './Admin.vue'

createApp(Admin).use(WebxUI).mount('#admin')
```

<script setup>
import BadgeDemo from '../components/demos/BadgeDemo.vue'
</script>

# Badge

`WxBadge` is the small label that says what something is: a status, a section, a tag on a row.
For a count pinned to a button or an icon, use [Indicator](/components/indicator) instead.

<BadgeDemo />

## Usage

```vue
<template>
  <wx-badge type="success">Published</wx-badge>
  <wx-badge type="warning" variant="outline">Pending review</wx-badge>
  <wx-badge type="default" dot round>Draft</wx-badge>
</template>
```

## Statuses

A badge with `dot` reads as a state rather than a tag — the dot carries the colour, so the label
stays legible at a glance in a dense table:

```vue
<template>
  <wx-badge type="success" dot round>Published</wx-badge>
</template>
```

## Removable tags

`closable` adds a ×. The badge does not remove itself — it emits `close` and the list stays yours:

```vue
<script setup lang="ts">
import { ref } from 'vue'

const tags = ref(['News', 'Releases'])
</script>

<template>
  <wx-badge v-for="tag in tags" :key="tag" closable @close="tags = tags.filter((t) => t !== tag)">
    {{ tag }}
  </wx-badge>
</template>
```

## Props

| Prop         | Type                                                                     | Default     | Description                         |
| ------------ | ------------------------------------------------------------------------ | ----------- | ----------------------------------- |
| `type`       | `'default' \| 'primary' \| 'success' \| 'warning' \| 'danger' \| 'info'` | `'default'` | Semantic colour                     |
| `variant`    | `'soft' \| 'solid' \| 'outline'`                                         | `'soft'`    | Visual weight                       |
| `size`       | `'sm' \| 'md' \| 'lg'`                                                   | `'md'`      | Padding and text size               |
| `round`      | `boolean`                                                                | `false`     | Pill shape                          |
| `dot`        | `boolean`                                                                | `false`     | Coloured dot before the label       |
| `closable`   | `boolean`                                                                | `false`     | Adds a × that emits `close`         |
| `closeLabel` | `string`                                                                 | `'Remove'`  | Accessible name of the close button |

**Events:** `close` (`MouseEvent`).

**Slots:** `default` — the label; `icon` — sits before the label.

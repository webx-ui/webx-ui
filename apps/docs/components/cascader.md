<script setup>
import CascaderDemo from '../components/demos/CascaderDemo.vue'
</script>

# Cascader

`WxCascader` picks a value out of a tree, one column per level: a section inside a section, a
category, a region and its towns. The tree can be handed over whole or fetched level by level as
the user opens it.

<CascaderDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const section = ref([])
const options = [
  {
    label: 'Guide',
    value: 'guide',
    children: [
      { label: 'Disciplines', value: 'disciplines' },
      { label: 'Navigation', value: 'navigation' },
    ],
  },
  { label: 'Component', value: 'component', children: [{ label: 'Input', value: 'input' }] },
]
</script>

<template>
  <wx-cascader v-model="section" :options="options" placeholder="Pick a section" clearable />
</template>
```

The model holds the whole path — `['guide', 'navigation']` — which is what a backend needs to know
where a node sits. With `:emit-path="false"` it holds the last value alone, and the field still
shows the full path by finding it in the tree.

## Which levels may be picked

By default only a leaf can be chosen: clicking a parent opens its children. `check-strictly` lets
any level be picked, and keeps the panel open so the user can go deeper afterwards.

```vue
<template>
  <wx-cascader v-model="section" :options="options" check-strictly />
</template>
```

`expand-trigger="hover"` opens the next column on hover instead of on click.

## Levels from the backend

With `lazy`, the cascader asks `load` for a level when it is opened — the root on the first open,
then each node. Mark the nodes that have nothing under them with `leaf: true`, otherwise they keep
offering to expand:

```vue
<script setup lang="ts">
async function load(option) {
  const parent = option ? option.value : 'root'
  const response = await fetch(`/api/regions?parent=${parent}`)
  const rows = await response.json()
  return rows.map((row) => ({ label: row.name, value: row.id, leaf: !row.has_children }))
}
</script>

<template>
  <wx-cascader v-model="town" lazy :load="load" placeholder="Pick a town" />
</template>
```

A level is fetched once and kept, so walking back and forth costs no requests. While a level is in
flight the node shows a spinner.

## The field

`show-all-levels` decides whether the field reads `Guide / Navigation / Side Navigation` or just
`Side Navigation`; `separator` is what goes between the levels. With `name`, the value is also
posted through a hidden input — the path joined by commas — for a plain form submit.

## Keyboard

The trigger opens on <kbd>Enter</kbd> or <kbd>Space</kbd>. Inside the panel <kbd>↓</kbd> and
<kbd>↑</kbd> move within a column, <kbd>→</kbd> opens the node and steps into the next column,
<kbd>←</kbd> goes back to the parent, <kbd>Enter</kbd> picks, <kbd>Esc</kbd> closes.

## Props

| Prop            | Type                                               | Default          | Description                                     |
| --------------- | -------------------------------------------------- | ---------------- | ----------------------------------------------- |
| `modelValue`    | `(string \| number)[] \| string \| number \| null` | `null`           | The path, or the last value                     |
| `options`       | `CascaderOption[]`                                 | `[]`             | `{ label, value, children?, disabled?, leaf? }` |
| `expandTrigger` | `'click' \| 'hover'`                               | `'click'`        | How the next column opens                       |
| `checkStrictly` | `boolean`                                          | `false`          | Any level may be picked                         |
| `emitPath`      | `boolean`                                          | `true`           | Model holds the whole path                      |
| `showAllLevels` | `boolean`                                          | `true`           | Field shows the whole path                      |
| `separator`     | `string`                                           | `' / '`          | Between the levels in the field                 |
| `lazy`          | `boolean`                                          | `false`          | Fetch levels as they open                       |
| `load`          | `(option, path) => CascaderOption[] \| Promise`    | —                | Fetches one level; required with `lazy`         |
| `clearable`     | `boolean`                                          | `false`          | Button that empties the field                   |
| `emptyText`     | `string`                                           | `'Nothing here'` | Shown for an empty level                        |
| `teleport`      | `boolean`                                          | `true`           | Render the panel in a portal                    |
| `placeholder`   | `string`                                           | —                | Shown while nothing is picked                   |
| `size`          | `'sm' \| 'md' \| 'lg'`                             | `'md'`           | Control height                                  |
| `status`        | `'default' \| 'success' \| 'warning' \| 'error'`   | `'default'`      | Validation state                                |
| `disabled`      | `boolean`                                          | `false`          | Disables the control                            |
| `name`          | `string`                                           | —                | Posts the value through a hidden input          |
| `ariaLabel`     | `string`                                           | —                | Label when there is no visible one              |

**Events:** `update:modelValue`, `change`, `expand` (`CascaderOption[]`), `clear`, `open`, `close`.

**Slot:** `option` (`{ option, level }`) — replaces the label of a row.

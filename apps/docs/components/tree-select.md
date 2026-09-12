<script setup>
import TreeSelectDemo from '../components/demos/TreeSelectDemo.vue'
</script>

# TreeSelect

`WxTreeSelect` is [Tree](/components/tree) as a form field: a trigger that reads like every other
control, and a panel holding the tree itself. It is the "parent category" field, the "where does
this page live" field, and — with `multiple` — the set of sections a post belongs to.

<TreeSelectDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxTreeSelect, type TreeKey } from '@webx-ui/core'

const categories = [
  { id: 1, label: 'Engine', children: [{ id: 11, label: 'Pistons' }] },
  { id: 2, label: 'Brakes' },
]

const parent = ref<TreeKey | null>(null)
</script>

<template>
  <wx-tree-select v-model="parent" :nodes="categories" placeholder="Parent category" />
</template>
```

`nodes` is the tree — the same shape `WxTree` takes, with the same `node-key`, `label-key` and
`children-key` if your records call those fields something else. The model is a key, not a node: it
is what goes into `parent_id`. The node itself comes with the `change` event, since an id alone is
rarely what a form needs to show.

Clicking a node answers the question, so the panel closes. A branch is a legitimate answer — a
category with children is still a category; mark the ones that are not choosable with `disabled`.

## More than one

`multiple` puts a checkbox on every node, turns the model into an array of keys, and leaves the
panel open, because a set is rarely finished after one tick. Each chosen node becomes a tag in the
field, and a tag can be dismissed from there.

```vue
<wx-tree-select v-model="sections" :nodes="categories" multiple clearable />
```

A tick runs down the branch and back up into the parent, the way it does in the tree. That is right
for "everything under Brakes" and wrong for "exactly these three", which is what `check-strictly`
is for: the model then holds what was ticked and nothing else.

## Which node is it

Two categories called "Seals" under two different parents are one field showing "Seals" twice.
`show-path` spells the answer out — `Engine / Gaskets and seals` — and `separator` decides what
goes between the steps.

Opening the panel reveals what is already chosen: the branches leading to it are opened for you, so
a tree of five hundred nodes does not open on its first page with the answer somewhere below.

## Searching and fetching

`filterable` adds the search field above the tree, which filters as `WxTree` does — matches stay,
the branches leading to them stay and open, and the term is dropped when the panel closes.

`lazy` with `load` fetches a branch the first time it opens, which is how a catalogue with
thousands of categories becomes a field at all:

```vue
<wx-tree-select
  v-model="category"
  :nodes="roots"
  lazy
  :load="(node) => api.get(`/categories/${node.id}/children`)"
  filterable
/>
```

### A value the tree has not fetched

A form opens holding `city_id: 'ua-kyiv'`, and the tree knows only the countries. The field has no
node to name and no branch to open — an id is not a label, and nothing in the tree leads to it yet.
`selected-path` is the answer: the ancestors and the node itself, which is what the backend used to
find the record in the first place.

```vue
<wx-tree-select
  v-model="form.city_id"
  :nodes="countries"
  :selected-path="[
    { id: 'ua', label: 'Ukraine' },
    { id: 'ua-kyiv', label: 'Kyiv' },
  ]"
  lazy
  :load="load"
  show-path
/>
```

The field reads the label straight from the path, so it is right before anything is fetched.
Opening the panel then walks that path a level at a time — one `load` per level — and the chosen
node ends up in the tree, open, selected and scrolled to. With `multiple`, pass an array of paths.

Without it a lazy field shows the raw key, because a field holding a value must not look like a
field holding none.

A search over a lazy tree only sees what has been fetched. Where the catalogue is too big to hold,
let the backend answer the search instead — `filterable` off, and a
[`WxAutocomplete`](/components/autocomplete) beside the field for jumping straight to a category by
name.

## In a form

`WxFormItem` gives it the label, the size, the disabled state and the error, like any other
control:

```vue
<wx-form-item label="Parent" prop="parent_id" :errors="errors">
  <wx-tree-select v-model="form.parent_id" :nodes="categories" name="parent_id" clearable />
</wx-form-item>
```

`name` adds the hidden input a plain `<form>` post needs — one key, or several separated by commas.

## Props

| Prop                | Type                                             | Default          | Description                                                       |
| ------------------- | ------------------------------------------------ | ---------------- | ----------------------------------------------------------------- |
| `modelValue`        | `TreeKey \| TreeKey[] \| null`                   | `null`           | The chosen key, or keys with `multiple`                           |
| `nodes`             | `T[]`                                            | `[]`             | The tree to choose from                                           |
| `multiple`          | `boolean`                                        | `false`          | Checkboxes, and an array for a model                              |
| `checkStrictly`     | `boolean`                                        | `false`          | A tick stays where it was made                                    |
| `nodeKey`           | `string`                                         | `'id'`           | Field holding the identity                                        |
| `labelKey`          | `string`                                         | `'label'`        | Field holding the text                                            |
| `childrenKey`       | `string`                                         | `'children'`     | Field holding the children                                        |
| `disabledKey`       | `string`                                         | `'disabled'`     | Field that makes a node unchoosable                               |
| `leafKey`           | `string`                                         | `'leaf'`         | Field saying there is nothing to fetch                            |
| `defaultExpandAll`  | `boolean`                                        | `false`          | Open every branch in the panel                                    |
| `lazy`              | `boolean`                                        | `false`          | Children arrive when a branch opens                               |
| `selectedPath`      | `T[] \| T[][]`                                   | —                | Path to the value, for a tree that has not fetched it             |
| `load`              | `(node) => T[] \| Promise<T[]>`                  | —                | Fetches one branch                                                |
| `filterable`        | `boolean`                                        | `false`          | Search field above the tree                                       |
| `filterPlaceholder` | `string`                                         | `'Search'`       | Placeholder for it                                                |
| `showPath`          | `boolean`                                        | `false`          | Show the whole path in the field                                  |
| `separator`         | `string`                                         | `' / '`          | What goes between the steps of a path                             |
| `panelHeight`       | `number \| string`                               | `280`            | How tall the tree may get before it scrolls                       |
| `clearable`         | `boolean`                                        | `false`          | A button that empties the selection                               |
| `placeholder`       | `string`                                         | `'Select'`       | Shown with nothing chosen                                         |
| `emptyText`         | `string`                                         | `'Nothing here'` | Shown with nothing to choose from                                 |
| `teleport`          | `boolean`                                        | `true`           | Render the panel in a portal                                      |
| `disabled`          | `boolean`                                        | —                | Inherited from the form when not set                              |
| `size`              | `'sm' \| 'md' \| 'lg'`                           | `'md'`           | Size of the field                                                 |
| `status`            | `'default' \| 'error' \| 'success' \| 'warning'` | `'default'`      | Border state                                                      |
| `name`              | `string`                                         | —                | Adds the hidden input for a form post                             |
| `id`                | `string`                                         | generated        | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `ariaLabel`         | `string`                                         | —                | Accessible name when there is no `<label>`                        |

**Events:** `update:modelValue`; `change` (`value, nodes`); `clear`; `open`; `close`.

**Slots:** `node` (`{ node, depth, expanded, selected }`) — one node in the panel; `value`
(`{ nodes }`) — what the field shows instead of the labels.

## Accessibility

The field is one button with `aria-expanded`, so a keyboard opens the panel with `Enter` or
`Space`, and the tree inside takes it from there: arrows walk, `→` and `←` open and close a branch,
`Enter` chooses, `Space` ticks. `Escape` closes the panel and returns focus to the field.

Removing a tag is a pointer shortcut, not the only way: with `multiple`, the same node unticked in
the panel does it from the keyboard.

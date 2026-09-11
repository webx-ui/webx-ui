<script setup>
import DescriptionsDemo from '../components/demos/DescriptionsDemo.vue'
</script>

# Descriptions

`WxDescriptions` is a record read rather than edited: a list of label-and-value pairs, laid out in
columns. It is what a drawer shows before you press Edit.

<DescriptionsDemo />

## Usage

```vue
<template>
  <wx-descriptions title="Order WX-4100" :columns="2">
    <wx-descriptions-item label="Customer">{{ order.customer }}</wx-descriptions-item>
    <wx-descriptions-item label="Email">{{ order.email }}</wx-descriptions-item>
    <wx-descriptions-item label="Note" :span="2">{{ order.note }}</wx-descriptions-item>
  </wx-descriptions>
</template>
```

## A grid, not a table

The pairs are a **list**, not tabular data — nothing lines up down a column except by accident, and
`Customer` above `Placed` means nothing at all. So this is a `<dl>` laid out as a grid, which is
what the markup already says it is, and which a table cannot be talked into doing: a grid folds to
one column when the panel is narrow.

That folding is decided by the **panel**, not the window. A record in a 380px drawer on a wide
desktop is narrow, and the window has nothing to say about it.

Each pair is two grid items rather than a box holding two — that is what lets labels in different
rows share a column and line up. Wrapping a pair would end it.

## Spanning

`span` counts columns, not cells:

```vue
<wx-descriptions-item label="Note" :span="2">…</wx-descriptions-item>
```

## Bordered

`bordered` draws the lattice. It is the grid's own gaps opened to a pixel over a background in the
border colour — exact at any number of columns and under any span, where a border per cell doubles
up along every shared edge.

## Labels above their values

`layout="vertical"` stacks each label over its value, which suits short figures in many columns —
four or six facts across a card. The pairs become single grid items there, since there is nothing
left to line up.

## Descriptions

| Prop         | Type                         | Default        | Description                      |
| ------------ | ---------------------------- | -------------- | -------------------------------- |
| `title`      | `string`                     | —              | Heading above the list           |
| `columns`    | `number`                     | `2`            | Most pairs side by side          |
| `bordered`   | `boolean`                    | `false`        | Rules around every cell          |
| `size`       | `'sm' \| 'md' \| 'lg'`       | `'md'`         | Spacing and cell padding         |
| `layout`     | `'horizontal' \| 'vertical'` | `'horizontal'` | Label beside its value, or above |
| `labelWidth` | `string`                     | —              | Width of the label column        |

**Slots:** `title`; `extra` — beside the heading; `default` — the pairs.

## DescriptionsItem

| Prop    | Type     | Default | Description               |
| ------- | -------- | ------- | ------------------------- |
| `label` | `string` | —       | The name of the fact      |
| `span`  | `number` | `1`     | How many columns it takes |

**Slots:** `label`; `default` — the value.

A pair renders as a fragment — a `<dt>` and a `<dd>`, side by side in the grid — so it has no
single element of its own to take a `class`. Style the list, or the cells through it.

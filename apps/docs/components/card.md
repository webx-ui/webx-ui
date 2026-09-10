<script setup>
import CardDemo from '../components/demos/CardDemo.vue'
</script>

# Card

`WxCard` — a surface that groups a block of an admin screen: a form, a table, a summary.

<CardDemo />

## Usage

```vue
<template>
  <wx-card title="Page settings" shadow="always">
    <template #extra>Draft</template>

    <wx-input v-model="title" placeholder="Title" />

    <template #footer>
      <wx-button type="primary">Save</wx-button>
    </template>
  </wx-card>
</template>
```

The header is rendered only when `title`, `#header` or `#extra` is present; the footer only when
`#footer` is.

## Props

| Prop         | Type                             | Default   | Description                               |
| ------------ | -------------------------------- | --------- | ----------------------------------------- |
| `title`      | `string`                         | —         | Header text; ignored if `#header` is used |
| `shadow`     | `'never' \| 'hover' \| 'always'` | `'never'` | When the card casts a shadow              |
| `padding`    | `'none' \| 'sm' \| 'md' \| 'lg'` | `'md'`    | Padding of header, body and footer        |
| `borderless` | `boolean`                        | `false`   | Drops the border, keeps the surface       |

## Slots

| Slot      | Description                                    |
| --------- | ---------------------------------------------- |
| `default` | Card body                                      |
| `header`  | Replaces the `title`                           |
| `extra`   | Right-hand side of the header (badge, actions) |
| `footer`  | Footer, separated by a divider                 |

## Styling

`padding` sets a local `--wx-card-padding`, so a single card can be adjusted without touching the
global scale:

```vue
<template>
  <wx-card style="--wx-card-padding: 28px">Custom padding</wx-card>
</template>
```

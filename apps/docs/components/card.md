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

| Prop       | Type                             | Default    | Description                               |
| ---------- | -------------------------------- | ---------- | ----------------------------------------- |
| `title`    | `string`                         | —          | Header text; ignored if `#header` is used |
| `shadow`   | `'never' \| 'hover' \| 'always'` | `'always'` | When the card casts a shadow              |
| `padding`  | `'none' \| 'sm' \| 'md' \| 'lg'` | `'md'`     | Padding of header, body and footer        |
| `bordered` | `boolean`                        | `false`    | Adds an outline; cards separate by shadow |

## Slots

| Slot      | Description                                               |
| --------- | --------------------------------------------------------- |
| `default` | Card body                                                 |
| `header`  | Replaces the `title`                                      |
| `extra`   | Right-hand side of the header (badge, actions)            |
| `sidebar` | Narrow column beside the body — see below                 |
| `footer`  | Footer, separated from the body by the card's own padding |

## Sidebar

Filling `#sidebar` splits the body into a narrow column and the main content — the usual shape for
an entity screen with a preview, a status panel or a section menu next to the form.

```vue
<template>
  <wx-card title="Page">
    <template #sidebar>
      <wx-input model-value="" placeholder="Search sections" size="sm" />
    </template>

    <p>Main content.</p>
  </wx-card>
</template>
```

The columns stack when the card itself is narrower than 560px — a **container** query, not a
viewport one, so a card dropped into a narrow column collapses even on a wide screen.

Width is a variable, `240px` by default:

```vue
<template>
  <wx-card style="--wx-card-sidebar-width: 320px">…</wx-card>
</template>
```

## Styling

`padding` sets a local `--wx-card-padding`, so a single card can be adjusted without touching the
global scale:

```vue
<template>
  <wx-card style="--wx-card-padding: 28px">Custom padding</wx-card>
</template>
```

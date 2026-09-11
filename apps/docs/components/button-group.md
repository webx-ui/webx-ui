<script setup>
import ButtonGroupDemo from '../components/demos/ButtonGroupDemo.vue'
</script>

# ButtonGroup

`WxButtonGroup` joins buttons into one control — a segmented switch, a row of actions on a table
row, a stack of sections — and hands its look down to every button inside it.

<ButtonGroupDemo />

## Usage

```vue
<template>
  <wx-button-group type="primary" variant="outline" aria-label="Text alignment">
    <wx-button>Left</wx-button>
    <wx-button>Center</wx-button>
    <wx-button>Right</wx-button>
  </wx-button-group>
</template>
```

`type`, `variant` and `size` are defaults, not overrides: a button that sets its own wins, which is
how one button in a joined row turns red.

```vue
<template>
  <wx-button-group size="sm">
    <wx-button>Edit</wx-button>
    <wx-button type="danger">Delete</wx-button>
  </wx-button-group>
</template>
```

## Joined or separate

By default the buttons share one outline, rounded only at the ends. `:attached="false"` keeps each
button whole and puts a gap between them — the shape for "Cancel / Save":

```vue
<template>
  <wx-button-group :attached="false">
    <wx-button variant="text">Cancel</wx-button>
    <wx-button type="success">Save</wx-button>
  </wx-button-group>
</template>
```

## Accessibility

The group renders `role="group"`. Give it an `aria-label` whenever the buttons alone do not say
what the set is for — an icon-only row of actions especially.

## Props

| Prop        | Type                                                           | Default | Description                           |
| ----------- | -------------------------------------------------------------- | ------- | ------------------------------------- |
| `type`      | `'default' \| 'primary' \| 'success' \| 'warning' \| 'danger'` | —       | Colour for buttons that set none      |
| `variant`   | `'solid' \| 'outline' \| 'text'`                               | —       | Variant for buttons that set none     |
| `size`      | `'sm' \| 'md' \| 'lg'`                                         | —       | Size for buttons that set none        |
| `disabled`  | `boolean`                                                      | `false` | Disables every button in the group    |
| `vertical`  | `boolean`                                                      | `false` | Stacks the buttons                    |
| `attached`  | `boolean`                                                      | `true`  | Joins them into one segmented control |
| `ariaLabel` | `string`                                                       | —       | Accessible name of the group          |

**Slot:** `default` — the buttons.

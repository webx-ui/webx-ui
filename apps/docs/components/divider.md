<script setup>
import DividerDemo from '../components/demos/DividerDemo.vue'
</script>

# Divider

`WxDivider` draws the line between two parts of a screen — a rule across a form, or a hairline
between the links at the end of a table row.

<DividerDemo />

## Usage

```vue
<template>
  <p>General settings</p>
  <wx-divider />
  <p>Advanced settings</p>
</template>
```

## With a label

A label turns the rule into a quiet section heading. It sits in the middle by default, and `align`
moves it to either end:

```vue
<template>
  <wx-divider label="Advanced" />
  <wx-divider label="Danger zone" align="start" variant="dashed" />
</template>
```

Note what a label changes underneath: a plain rule is rendered with `role="separator"`, and one
carrying text is not — a separator with words inside it tells a screen reader two contradictory
things at once.

## Vertical

A vertical rule is one character tall and sits on the text baseline, which is what makes it the
right separator between inline actions:

```vue
<template>
  <wx-link href="/edit">Edit</wx-link>
  <wx-divider direction="vertical" />
  <wx-link href="/delete" type="danger">Delete</wx-link>
</template>
```

Vertical rules carry no label — there is no room for one, and one passed is ignored.

## Props

| Prop        | Type                              | Default        | Description                        |
| ----------- | --------------------------------- | -------------- | ---------------------------------- |
| `direction` | `'horizontal' \| 'vertical'`      | `'horizontal'` | Across the flow, or along the line |
| `variant`   | `'solid' \| 'dashed' \| 'dotted'` | `'solid'`      | Line style                         |
| `align`     | `'start' \| 'center' \| 'end'`    | `'center'`     | Where the label sits               |
| `spacing`   | `'none' \| 'sm' \| 'md' \| 'lg'`  | `'md'`         | Margin around the rule             |
| `label`     | `string`                          | —              | Label, instead of the default slot |

**Slots:** `default` — the label.

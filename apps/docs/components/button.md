<script setup>
import ButtonDemo from '../components/demos/ButtonDemo.vue'
</script>

# Button

`WxButton` — the standard action trigger. Renders a `<button>`, or an `<a>` when `href` is given.

<ButtonDemo />

## Usage

```vue
<template>
  <wx-button type="primary" @click="save">Save</wx-button>
  <wx-button type="danger" variant="outline" size="sm">Delete</wx-button>
  <wx-button :loading="saving" block>Publish</wx-button>
  <wx-button href="/docs" target="_blank">Documentation</wx-button>
</template>
```

## Props

| Prop         | Type                                                           | Default     | Description                              |
| ------------ | -------------------------------------------------------------- | ----------- | ---------------------------------------- |
| `type`       | `'default' \| 'primary' \| 'success' \| 'warning' \| 'danger'` | `'default'` | Semantic colour                          |
| `variant`    | `'solid' \| 'outline' \| 'text'`                               | `'solid'`   | Visual weight                            |
| `size`       | `'sm' \| 'md' \| 'lg'`                                         | `'md'`      | Control height and font size             |
| `disabled`   | `boolean`                                                      | `false`     | Blocks interaction                       |
| `loading`    | `boolean`                                                      | `false`     | Shows a spinner, blocks interaction      |
| `block`      | `boolean`                                                      | `false`     | Full width                               |
| `round`      | `boolean`                                                      | `false`     | Pill-shaped                              |
| `href`       | `string`                                                       | —           | Renders an `<a>` instead of a `<button>` |
| `target`     | `string`                                                       | —           | Link target, used with `href`            |
| `nativeType` | `'button' \| 'submit' \| 'reset'`                              | `'button'`  | `type` attribute of the `<button>`       |

Unknown attributes (`id`, `data-*`, `aria-*`, …) fall through to the root element.

## Events

| Event   | Payload      | Fires                                              |
| ------- | ------------ | -------------------------------------------------- |
| `click` | `MouseEvent` | On click, unless the button is disabled or loading |

## Slots

| Slot      | Description                                    |
| --------- | ---------------------------------------------- |
| `default` | Button label                                   |
| `icon`    | Leading icon; hidden while `loading` is `true` |

## Accessibility

- A disabled `<button>` gets the `disabled` attribute; a disabled link drops its `href`, gets
  `aria-disabled="true"` and `tabindex="-1"`.
- While loading, the root carries `aria-busy="true"`.
- Focus is shown with `--wx-ring-focus` on `:focus-visible` only.
- The spinner slows to 2s under `prefers-reduced-motion: reduce`.

## Styling

Built from `--wx-color-*`, `--wx-size-control-*`, `--wx-space-*`, `--wx-radius-md` and
`--wx-duration-fast`. To restyle every button, override the semantic tokens rather than the classes:

```css
:root {
  --wx-color-primary: #7c3aed;
  --wx-radius-md: 10px;
}
```

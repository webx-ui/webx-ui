<script setup>
import TokenSwatches from '../components/demos/TokenSwatches.vue'
</script>

# Tokens

All values below are live: switch the site theme (top right) and every swatch re-renders, because
they are `var(--wx-*)` references rather than copied hex codes.

## Semantic colours

The layer components actually use. Every accent colour ships the same five roles — base, `-hover`,
`-active`, `-disabled` and `-soft` — so a component never has to darken or lighten anything itself.

<TokenSwatches group="semantic" prefix="color-" />

### Backgrounds

`--wx-bg-fill` and `--wx-bg-fill-hover` are the neutral fills used by icon buttons and hovered rows.

<TokenSwatches group="semantic" prefix="bg-" />

### Text

<TokenSwatches group="semantic" prefix="text-" />

### Borders

<TokenSwatches group="semantic" prefix="border-" />

## Palette

Primitives. Components should not use these directly — map them to a semantic token first.

### Gray

<TokenSwatches group="palette" hue="gray" />

### Blue

<TokenSwatches group="palette" hue="blue" />

### Green

<TokenSwatches group="palette" hue="green" />

### Amber

<TokenSwatches group="palette" hue="amber" />

### Red

<TokenSwatches group="palette" hue="red" />

### Cyan

<TokenSwatches group="palette" hue="cyan" />

## Spacing

The scale is keyed by pixels: `--wx-space-16` is `16px`. No mental arithmetic, no re-basing when a
value has to change.

<TokenSwatches group="space" />

## Radii

`--wx-radius-control` is what every control (button, input, select) uses; `--wx-radius-md` is the
card and dialog radius.

<TokenSwatches group="radius" />

## Font sizes

<TokenSwatches group="font-size" />

### Controls have their own

A control is not a paragraph. `--wx-font-size-control-sm` (12px), `-md` (14px) and `-lg` (16px)
are what every input, select, checkbox and button reads at, and they are separate from the body
scale on purpose: an admin panel is mostly controls, and 16px — a size for reading prose — leaves
them shouting beside navigation at 14px.

Retune them in one place rather than per component:

```css
:root {
  --wx-font-size-control-md: 15px;
}
```

## Density

Controls are comfortable by default: `--wx-size-control-md` is `42px`. Tables, toolbars and dialogs
often want less. Add `wx-density-compact` (or `data-density="compact"`) to any element and every
control inside it shrinks:

```html
<div class="wx-density-compact">
  <wx-input placeholder="34px tall here" />
</div>
```

| Variable               | Default | Compact |
| ---------------------- | ------- | ------- |
| `--wx-size-control-sm` | 34px    | 28px    |
| `--wx-size-control-md` | 42px    | 34px    |
| `--wx-size-control-lg` | 50px    | 40px    |
| `--wx-radius-control`  | 10px    | 8px     |
| `--wx-radius-md`       | 16px    | 12px    |
| `--wx-font-size-md`    | 16px    | 14px    |

Compact changes heights and the body scale; control text is left alone, because it is already on a scale of its own.

## Other scales

| Group    | Variables                                                                                  |
| -------- | ------------------------------------------------------------------------------------------ |
| Shadows  | `--wx-shadow-none`, `--wx-shadow-sm`, `--wx-shadow-md`, `--wx-shadow-lg`                   |
| Controls | `--wx-size-control-sm`, `--wx-size-control-md`, `--wx-size-control-lg`                     |
| z-index  | `--wx-z-index-sticky` … `--wx-z-index-tooltip`                                             |
| Motion   | `--wx-duration-fast`, `--wx-duration-normal`, `--wx-duration-slow`, `--wx-easing-standard` |

Every floating panel — a dropdown, a select's list, a popover, a colour picker — is drawn on
`--wx-z-index-popover`, one shared layer. They are added to the document as they open, so the one
opened last is the one on top, and a select opened inside a popover covers it instead of vanishing
behind it. Keep your own transient panels on that layer too; `--wx-z-index-dialog` and above are
for surfaces that take over the page.

See [Theming](/guide/theming) for how to override them.

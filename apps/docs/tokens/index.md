<script setup>
import TokenSwatches from '../components/demos/TokenSwatches.vue'
</script>

# Tokens

All values below are live: switch the site theme (top right) and every swatch re-renders, because
they are `var(--wx-*)` references rather than copied hex codes.

## Semantic colours

The layer components actually use.

<TokenSwatches group="semantic" prefix="color-" />

### Backgrounds

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

## Spacing

<TokenSwatches group="space" />

## Radii

<TokenSwatches group="radius" />

## Font sizes

<TokenSwatches group="font-size" />

## Other scales

| Group    | Variables                                                                                  |
| -------- | ------------------------------------------------------------------------------------------ |
| Shadows  | `--wx-shadow-none`, `--wx-shadow-sm`, `--wx-shadow-md`, `--wx-shadow-lg`                   |
| Controls | `--wx-size-control-sm`, `--wx-size-control-md`, `--wx-size-control-lg`                     |
| z-index  | `--wx-z-index-sticky` … `--wx-z-index-tooltip`                                             |
| Motion   | `--wx-duration-fast`, `--wx-duration-normal`, `--wx-duration-slow`, `--wx-easing-standard` |

See [Theming](/guide/theming) for how to override them.

<script setup>
import StepsDemo from '../components/demos/StepsDemo.vue'
</script>

# Steps

`WxSteps` is a sequence with a place in it: what is done, where you are, and what is left.

<StepsDemo />

## Usage

```vue
<script setup lang="ts">
const current = ref(0)
</script>

<template>
  <wx-steps :current="current" aria-label="Checkout">
    <wx-step title="Details" description="Who it is for" />
    <wx-step title="Delivery" description="Where it goes" />
    <wx-step title="Payment" />
  </wx-steps>
</template>
```

It shows the place; it does not hold it. `current` is yours, and so is whatever is rendered
underneath — a wizard that owned its own position would fight the router the first time somebody
refreshed on step three.

## Going back, never forward

`clickable` lets a finished step be pressed to return to it, and `@change` reports which. Steps
**ahead** are never offered: they are the ones that have not been filled in, and offering them is a
promise the form cannot keep.

Only finished steps render as buttons at all, so there is nothing for a keyboard to land on that
would do nothing.

## Something went wrong

`error` marks the current step as failed without moving off it — a payment refused, a validation
the server rejected. The step keeps its place; only its colour and its glyph change.

## Numbering

The steps report themselves in the order they are written, while the component sets up rather than
after it mounts. That matters: a sequence that numbered itself one frame later would flicker on
every page carrying a wizard.

The rule between them is a pseudo-element chosen by `:last-child`, for the same reason — a step
that had to be told how many siblings it has cannot know on its first render.

## Steps

| Prop        | Type                         | Default        | Description                       |
| ----------- | ---------------------------- | -------------- | --------------------------------- |
| `current`   | `number`                     | `0`            | Which step, counting from zero    |
| `direction` | `'horizontal' \| 'vertical'` | `'horizontal'` | Across, or down the page          |
| `size`      | `'sm' \| 'md'`               | `'md'`         | Marker and title size             |
| `error`     | `boolean`                    | `false`        | The current step went wrong       |
| `clickable` | `boolean`                    | `false`        | Finished steps can be returned to |
| `ariaLabel` | `string`                     | —              | Accessible name of the sequence   |

**Events:** `change` (`index`) — a finished step was chosen.

## Step

| Prop          | Type       | Default | Description           |
| ------------- | ---------- | ------- | --------------------- |
| `title`       | `string`   | —       | What this step is     |
| `description` | `string`   | —       | What it involves      |
| `icon`        | `IconName` | —       | Instead of the number |

**Slots:** `title`; `default` — the description; `marker` — with `{ index, state }`.

## Accessibility

The sequence is an ordered list, which is what it is, and the current step carries
`aria-current="step"`. The state is never colour alone: a finished step shows a tick, a failed one
a cross, and the ones ahead their number.

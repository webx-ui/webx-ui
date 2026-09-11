<script setup>
import SkeletonDemo from '../components/demos/SkeletonDemo.vue'
</script>

# Skeleton

`WxSkeleton` holds the shape of what is coming, so the page does not jump when it lands.

<SkeletonDemo />

## Usage

```vue
<template>
  <wx-skeleton :loading="loading" avatar title :rows="3">
    <article>{{ post.body }}</article>
  </wx-skeleton>
</template>
```

The same markup covers both states: while `loading` the placeholder shows, and once it is off the
default slot renders. That is one `v-if` the caller does not write, and — more to the point — one
place where the two cannot drift apart.

## A shape, not a spinner

A spinner says _something is happening_. A skeleton says _something is happening, and it will be
this shape_ — so the reader's eye is already where the content will be, and nothing moves when it
arrives. Use a spinner for work with no shape to promise (a save, a delete); use this for content.

## Shape it like the thing

`rows`, `avatar` and `title` cover a paragraph with a face beside it. Anything else goes in
`#template`, built from `WxSkeletonItem`:

```vue
<wx-skeleton :loading="loading">
  <template #template>
    <wx-skeleton-item variant="image" />
    <wx-skeleton-item variant="title" />
    <wx-skeleton-item variant="text" width="70%" />
  </template>

  <product-card :product="product" />
</wx-skeleton>
```

The placeholder should be roughly the size of the real thing. One that is much shorter still lets
the page jump, which is the whole thing it was there to prevent.

## Do not flash

`delay` holds the placeholder back for a few hundred milliseconds. A request that answers in 80ms
should show nothing at all — a skeleton that appears and vanishes within one frame reads as a
glitch, not as progress.

```vue
<wx-skeleton :loading="loading" :delay="200">…</wx-skeleton>
```

The delay is only ever on **showing**. Content that has arrived is shown at once.

## Skeleton

| Prop       | Type      | Default | Description                                 |
| ---------- | --------- | ------- | ------------------------------------------- |
| `loading`  | `boolean` | `true`  | Whether the placeholder is showing          |
| `rows`     | `number`  | `3`     | Lines of text to stand in for               |
| `avatar`   | `boolean` | `false` | Adds a circle beside the lines              |
| `title`    | `boolean` | `false` | Draws the first line heavier                |
| `animated` | `boolean` | `true`  | The travelling sheen                        |
| `delay`    | `number`  | `0`     | How long before it appears, in milliseconds |

**Slots:** `default` — the real thing; `template` — a placeholder shaped like it.

## SkeletonItem

| Prop       | Type                                                               | Default  | Description           |
| ---------- | ------------------------------------------------------------------ | -------- | --------------------- |
| `variant`  | `'text' \| 'title' \| 'circle' \| 'square' \| 'image' \| 'button'` | `'text'` | What it stands in for |
| `width`    | `string \| number`                                                 | —        | Any CSS length        |
| `height`   | `string \| number`                                                 | —        | Any CSS length        |
| `animated` | `boolean`                                                          | `true`   | The sheen             |

## Accessibility

The placeholder carries `aria-busy` and `aria-live="polite"`, and every block inside it is
`aria-hidden` — there is nothing to read in a grey rectangle. Reduced motion stops the sheen and
keeps the blocks: a still placeholder still says _not yet_.

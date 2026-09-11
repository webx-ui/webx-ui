<script setup>
import LoadingDemo from '../components/demos/LoadingDemo.vue'
</script>

# Loading

`WxLoading` puts a veil over a region while it is being refreshed.

<LoadingDemo />

## Usage

```vue
<template>
  <wx-loading :loading="fetching" text="Fetching orders">
    <orders-table :rows="orders" />
  </wx-loading>
</template>
```

## Over, not instead of

The rows being refreshed are still the rows you were reading. Taking them away to say so loses your
place — twice, since they come back — so the veil goes over the top and what is underneath stays
where it is.

For content arriving for the **first** time, where there is nothing underneath to keep, use a
[Skeleton](/components/skeleton): it holds the shape of what is coming.

## It really blocks

The content under the veil is `inert` while it is up: nothing there can be clicked, tabbed into, or
read out. A veil that only _looks_ like it blocks is worse than none — it invites the second click
that sends the request twice.

## Do not flash

`delay` holds the veil back. A request that answers in 80 ms should show nothing; a spinner that
appears and vanishes inside one frame reads as a glitch.

```vue
<wx-loading :loading="fetching" :delay="200">…</wx-loading>
```

The delay is only on showing. The veil comes down the moment the work is done.

## Props

| Prop         | Type                   | Default     | Description                                 |
| ------------ | ---------------------- | ----------- | ------------------------------------------- |
| `loading`    | `boolean`              | `false`     | Whether the veil is up                      |
| `text`       | `string`               | —           | What is being waited for                    |
| `size`       | `'sm' \| 'md' \| 'lg'` | `'md'`      | Size of the spinner                         |
| `fullscreen` | `boolean`              | `false`     | Covers the window rather than the box       |
| `delay`      | `number`               | `0`         | How long before it appears, in milliseconds |
| `blur`       | `boolean`              | `false`     | Blurs what is underneath                    |
| `ariaLabel`  | `string`               | `'Loading'` | Accessible description of the wait          |

**Slots:** `default` — what is covered; `spinner` — replaces the spinner.

## Accessibility

The veil is a `status` region with a name, so the wait is announced without interrupting. Reduced
motion slows the spin rather than stopping it: the ring is the only thing on screen saying anything
is happening at all.

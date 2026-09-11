<script setup>
import EmptyDemo from '../components/demos/EmptyDemo.vue'
</script>

# Empty

`WxEmpty` is what a list says when it has nothing in it.

<EmptyDemo />

## Usage

```vue
<template>
  <wx-empty v-if="orders.length === 0" icon="cart" title="No orders yet">
    They will appear here as they come in.
  </wx-empty>
</template>
```

## Say which kind of empty it is

Two very different states get the same component and should not get the same words:

- **Nothing yet.** The list is new. The reader has done nothing wrong and there is usually
  something to offer them — a button that creates the first one.
- **Nothing matched.** There is plenty here; this filter found none of it. The way out is to change
  the filter, so say what was searched for and offer to clear it.

An empty state that says `No data` covers both and helps with neither.

```vue
<wx-empty icon="search" size="sm" :title="`Nothing matches “${term}”`">
  Try a shorter term, or clear the filters.
  <template #actions>
    <wx-button size="sm" variant="outline" @click="clear">Clear filters</wx-button>
  </template>
</wx-empty>
```

## Sizes

`sm` is a panel inside a screen — the column of a [ListDetail](/components/list-detail), a card.
`lg` is a whole page with nothing on it. `md` is the default and suits a table's body.

`plain` drops the glyph, for a single line where a picture would be too much ceremony.

## Props

| Prop          | Type                   | Default  | Description                     |
| ------------- | ---------------------- | -------- | ------------------------------- |
| `icon`        | `IconName`             | `'file'` | The glyph above the message     |
| `title`       | `string`               | —        | What is missing, in a few words |
| `description` | `string`               | —        | Why, or what to do about it     |
| `size`        | `'sm' \| 'md' \| 'lg'` | `'md'`   | How much room it takes          |
| `plain`       | `boolean`              | `false`  | Drops the glyph                 |

**Slots:** `icon` — an illustration instead of the glyph; `title`; `default` — the description;
`actions` — what to do about it.

## Accessibility

The glyph is `aria-hidden`: it is decoration, and the words underneath carry the message. Nothing
here is announced on its own — an empty state appears because a list rendered, and the list is what
a screen reader is already reading.

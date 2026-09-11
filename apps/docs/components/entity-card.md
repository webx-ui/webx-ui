<script setup>
import EntityCardDemo from '../components/demos/EntityCardDemo.vue'
</script>

# EntityCard

`WxEntityCard` is one record shown as a row: a small picture, the name, a few fields under it, and
the actions on the right. It is the shape of a list of pages, of clients, of orders — anywhere a
table would be too heavy.

<EntityCardDemo />

## Usage

```vue
<template>
  <wx-entity-card
    title="Alexey Sizintsev"
    subtitle="alexxgroup@gmail.com"
    image="/avatars/3.jpg"
    shape="circle"
  >
    <template #actions>
      <wx-button-group size="sm" :attached="false" aria-label="Client actions">
        <wx-button type="primary" variant="outline" aria-label="Edit">
          <template #icon><wx-icon name="edit" /></template>
        </wx-button>
        <wx-button type="danger" variant="outline" aria-label="Delete">
          <template #icon><wx-icon name="trash" /></template>
        </wx-button>
      </wx-button-group>
    </template>
  </wx-entity-card>
</template>
```

Clicks inside `#actions` stay there: the card's own `click` does not fire, so a row can be
clickable and still carry buttons.

## Fields under the title

`meta` is the row of small facts — the section a page belongs to, its tags, when it was last
touched. A fact with no `text` still shows its name, which is how an empty field reads:

```vue
<template>
  <wx-entity-card
    title="It is a long established fact…"
    image="/covers/12.jpg"
    :meta="[{ label: 'Sections', text: 'News' }, { label: 'Tags' }]"
  />
</template>
```

For anything richer than text — a badge, a link, a date with a tooltip — use the `meta` slot
instead of the prop.

## Without a picture

With no `image` and no `#media`, the first letter of the title stands in, so a list of rows stays
aligned whether or not every record has a cover.

## In a card, or on its own

`variant="card"` gives the row its own surface and shadow — right for a standalone list.
`variant="plain"` drops both, for a row that already sits inside a [Card](/components/card) or a
table cell.

## Props

| Prop        | Type                                | Default     | Description                      |
| ----------- | ----------------------------------- | ----------- | -------------------------------- |
| `title`     | `string`                            | —           | Name of the record               |
| `href`      | `string`                            | —           | Turns the title into a link      |
| `subtitle`  | `string`                            | —           | Second line                      |
| `image`     | `string`                            | —           | Thumbnail or avatar              |
| `imageAlt`  | `string`                            | `title`     | Alt text for the picture         |
| `shape`     | `'rounded' \| 'square' \| 'circle'` | `'rounded'` | Shape of the thumbnail           |
| `imageSize` | `number \| string`                  | by size     | Thumbnail size                   |
| `meta`      | `{ label?, text? }[]`               | `[]`        | Facts under the title            |
| `size`      | `'sm' \| 'md' \| 'lg'`              | `'md'`      | Padding, gap and thumbnail size  |
| `variant`   | `'card' \| 'plain'`                 | `'card'`    | Own surface, or none             |
| `bordered`  | `boolean`                           | `false`     | Adds an outline                  |
| `selected`  | `boolean`                           | `false`     | Marks the row as the current one |

**Events:** `click` (`MouseEvent`) — the row itself, never the actions.

**Slots:** `media`, `title`, `subtitle`, `meta`, `actions`, `default` (extra content under the meta row).

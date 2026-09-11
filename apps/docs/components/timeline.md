<script setup>
import TimelineDemo from '../components/demos/TimelineDemo.vue'
</script>

# Timeline

`WxTimeline` and `WxTimelineItem` show what happened and when: an order's history, an audit log,
the steps a page went through before it was published.

<TimelineDemo />

## Usage

```vue
<template>
  <wx-timeline>
    <wx-timeline-item timestamp="12.03.2026 09:12" datetime="2026-03-12T09:12" title="Created">
      Placed from the storefront by Maria Kovalenko.
    </wx-timeline-item>
    <wx-timeline-item timestamp="12.03.2026 09:40" title="Paid" type="success" icon="check">
      Card ending 4242.
    </wx-timeline-item>
    <wx-timeline-item title="Delivered" hollow>Waiting for the courier.</wx-timeline-item>
  </wx-timeline>
</template>
```

The list is a `<ul>` of `<li>`s, so it reads as a list. `timestamp` is whatever text you want to
show; `datetime` is the machine-readable value that goes on the `<time>` element.

Entries usually come from an array:

```vue
<template>
  <wx-timeline>
    <wx-timeline-item
      v-for="entry in order.history"
      :key="entry.id"
      :title="entry.title"
      :timestamp="entry.happened_at_human"
      :datetime="entry.happened_at"
      :type="entry.type"
    >
      {{ entry.body }}
    </wx-timeline-item>
  </wx-timeline>
</template>
```

## Marks

`type` colours the dot. `hollow` draws it as a ring — the shape for a step that has not happened
yet. `icon` puts an icon inside it, and the `dot` slot replaces the mark entirely, with an avatar
say; either way the dot grows and the text stays on the same axis.

```vue
<template>
  <wx-timeline-item title="Sent" type="primary" icon="upload" />
  <wx-timeline-item title="Delivered" hollow />
  <wx-timeline-item title="Commented">
    <template #dot><img class="avatar" src="/avatars/3.jpg" alt="" /></template>
  </wx-timeline-item>
</template>
```

## Order

The entries are drawn in the order they are written. For newest-first, reverse the array — the
markup order is what a screen reader follows, so it should match what is on screen.

## Timeline props

| Prop   | Type           | Default | Description                          |
| ------ | -------------- | ------- | ------------------------------------ |
| `size` | `'sm' \| 'md'` | `'md'`  | Spacing and dot size for every entry |

## TimelineItem props

| Prop                 | Type                                                                     | Default     | Description                         |
| -------------------- | ------------------------------------------------------------------------ | ----------- | ----------------------------------- |
| `timestamp`          | `string`                                                                 | —           | When it happened, pre-formatted     |
| `datetime`           | `string`                                                                 | —           | Machine-readable value for `<time>` |
| `timestampPlacement` | `'top' \| 'bottom'`                                                      | `'top'`     | Above the entry or under it         |
| `title`              | `string`                                                                 | —           | Headline                            |
| `type`               | `'default' \| 'primary' \| 'success' \| 'warning' \| 'danger' \| 'info'` | `'default'` | Colour of the dot                   |
| `hollow`             | `boolean`                                                                | `false`     | Draws the dot as a ring             |
| `icon`               | `string`                                                                 | —           | Icon inside the dot                 |
| `hideLine`           | `boolean`                                                                | `false`     | Drops the line under this entry     |

**Slots:** `default` — the body; `title`; `dot` — replaces the mark.

<script setup>
import ListDetailDemo from '../components/demos/ListDetailDemo.vue'
</script>

# ListDetail

`WxListDetail` is the screen an admin panel keeps coming back to: something to narrow the set on the
left, the records in the middle, the open one on the right. Inbox and messages, orders and an
order, tickets, products, users, invoices — the furniture is the same every time, and so is the
part nobody enjoys writing twice: what happens when the window is 900px wide.

It is layout only. What a row looks like, what the record shows, how the filters work — all of that
is yours, in slots.

<ListDetailDemo />

## Usage

```vue
<script setup lang="ts">
import { computed, ref } from 'vue'

const selectedId = ref<string | null>(orders[0].id)
const open = ref(true)

const selected = computed(() => orders.find((order) => order.id === selectedId.value))
</script>

<template>
  <wx-list-detail v-model:open="open">
    <template #filters>
      <wx-menu v-model="status">…</wx-menu>
    </template>

    <template #list>
      <wx-scrollbar>
        <order-row v-for="order in visible" :key="order.id" :order="order" @click="pick(order)" />
      </wx-scrollbar>
    </template>

    <template #detail="{ inline, back }">
      <order-details :order="selected" :show-back="!inline" @back="back" />
    </template>

    <template #empty>Pick an order</template>
  </wx-list-detail>
</template>
```

It fills the space it is given, so it belongs in a `WxMain` with no padding of its own — which is
exactly where a `<router-view />` sits in an admin shell.

Neither pane is inset by the component, on either side of the breakpoint: on a narrow screen the
detail becomes a sheet, and that sheet pads nothing either. What it holds is a screen rather than
a note, and a screen knows its own margins — two insets stacked would put the same content further
from the edge inside the sheet than it stands anywhere else.

```vue
<template>
  <wx-container viewport>
    <wx-header>…</wx-header>

    <wx-container direction="horizontal">
      <wx-aside v-if="showAside" :collapsed="collapsed">…</wx-aside>

      <wx-main padding="none">
        <router-view />
      </wx-main>
    </wx-container>
  </wx-container>
</template>
```

## One model, two jobs

`v-model:open` is not "the drawer is up" — it is **a record is open**. Beside the list that picks
the detail over the empty state; on a screen too narrow for a third column it is what raises the
record as a panel. The `back` handed to the `detail` slot closes it, and the same code serves both
shapes: what is a column on a desktop is a screen on a phone, written once.

Which record is open stays yours. The component never holds your selection — only whether there is
one.

## The thresholds are the widths you gave

There are no magic breakpoints here. The component measures **itself**, and adds up what it was
told:

- the filters column folds away below `filtersWidth + listWidth + detailMin`;
- the detail stops standing beside the list below `listWidth + detailMin`.

With the defaults that is 1040px and 800px — of the component's own width, not the window's. A
sidebar that collapses to a rail hands the screen 160px, and the filters column comes back on its
own; the same screen inside a 700px drawer behaves like a phone, because there it is one. A
viewport media query cannot tell those apart.

Widen the list and the thresholds move with it:

```vue
<template>
  <wx-list-detail :list-width="440" :detail-min="520">…</wx-list-detail>
</template>
```

## With no detail, the list is the screen

Leave the `detail` slot out and this is a list with a chooser in front of it — which form's
submissions, which folder's files — rather than a record standing beside one. The list then takes
the room the detail would have had, and the only threshold left is the chooser's:
`filtersWidth + detailMin`, because `detailMin` is the floor of whichever pane is the main one.

That is the shape to reach for when a record opens on a route of its own. What folds on a phone is
then the chooser rather than the records: the reader sees the list first and reaches the chooser
through the button the `list` slot is handed.

```vue
<template>
  <wx-list-detail :filters-width="270" filters-title="Forms">
    <template #filters="{ inline, close }">
      <form-list :heading="inline" @pick="close" />
    </template>

    <template #list="{ filtersInline, openFilters }">
      <submission-list :picker="!filtersInline" @pick="openFilters" />
    </template>
  </wx-list-detail>
</template>
```

## Where the filters go

When the column does not fit, the `filters` slot moves into a drawer — the same markup, no second
copy. The `list` slot is handed the button to open it:

```vue
<template #list="{ filtersInline, openFilters }">
  <wx-button v-if="!filtersInline" variant="outline" size="sm" @click="openFilters">
    Status
  </wx-button>
  …
</template>
```

A head that stands outside the pane — the screen's own, above the card — cannot be handed that
slot prop, so the component says it as an event instead: `filters-inline` fires whenever the column
appears or folds, and once at the start. Pair it with `v-model:filtersOpen` to raise the drawer
from up there:

```vue
<template>
  <wx-screen-head :actions="actions">
    <template v-if="!columnInline" #extra>
      <wx-button variant="outline" @click="filtersOpen = true">Forms</wx-button>
    </template>
  </wx-screen-head>

  <wx-list-detail v-model:filters-open="filtersOpen" @filters-inline="columnInline = $event">
    …
  </wx-list-detail>
</template>
```

Each slot knows where it is rendered — `inline` is `false` inside a drawer — so a heading that only
makes sense in the column can stand down:

```vue
<template #filters="{ inline, close }">
  <h2 v-if="inline">Orders</h2>
  <wx-menu v-model="status" @select="close">…</wx-menu>
</template>
```

## Props

| Prop           | Type               | Default     | Description                                          |
| -------------- | ------------------ | ----------- | ---------------------------------------------------- |
| `filtersWidth` | `number \| string` | `240`       | Width of the filters column                          |
| `listWidth`    | `number \| string` | `380`       | Width of the list column                             |
| `detailMin`    | `number`           | `420`       | Narrowest the main pane may be; sets both thresholds |
| `filtersTitle` | `string`           | `'Filters'` | Heading of the drawer the filters move into          |
| `detailLabel`  | `string`           | `'Details'` | Accessible name of the detail panel                  |

**Models:** `v-model:open` (`boolean`) — whether a record is open; `v-model:filtersOpen`
(`boolean`) — the filters drawer, if you want to drive it yourself.

**Events:** `filters-inline` (`boolean`) — whether the filters still have a column, said on every
change and once at the start.

## Slots

| Slot      | Props                          | Description                                                   |
| --------- | ------------------------------ | ------------------------------------------------------------- |
| `filters` | `inline`, `close`              | Views, folders, filters. Optional — no slot, no column        |
| `list`    | `filtersInline`, `openFilters` | The records                                                   |
| `detail`  | `inline`, `back`               | The open record. Optional — without it the list is the screen |
| `empty`   | —                              | Shown in the pane's place while nothing is open               |

## What it does not do

No selection, no data, no keyboard list navigation, and no draggable divider between the columns —
that one waits for [Splitter](/guide/roadmap). It is three regions and the rule for when there is
room for them.

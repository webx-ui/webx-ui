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
exactly where a `<router-view />` sits in an admin shell:

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

Each slot knows where it is rendered — `inline` is `false` inside a drawer — so a heading that only
makes sense in the column can stand down:

```vue
<template #filters="{ inline, close }">
  <h2 v-if="inline">Orders</h2>
  <wx-menu v-model="status" @select="close">…</wx-menu>
</template>
```

## Props

| Prop           | Type               | Default     | Description                                       |
| -------------- | ------------------ | ----------- | ------------------------------------------------- |
| `filtersWidth` | `number \| string` | `240`       | Width of the filters column                       |
| `listWidth`    | `number \| string` | `380`       | Width of the list column                          |
| `detailMin`    | `number`           | `420`       | Narrowest the detail may be; sets both thresholds |
| `filtersTitle` | `string`           | `'Filters'` | Heading of the drawer the filters move into       |
| `detailLabel`  | `string`           | `'Details'` | Accessible name of the detail panel               |

**Models:** `v-model:open` (`boolean`) — whether a record is open; `v-model:filtersOpen`
(`boolean`) — the filters drawer, if you want to drive it yourself.

## Slots

| Slot      | Props                          | Description                                            |
| --------- | ------------------------------ | ------------------------------------------------------ |
| `filters` | `inline`, `close`              | Views, folders, filters. Optional — no slot, no column |
| `list`    | `filtersInline`, `openFilters` | The records                                            |
| `detail`  | `inline`, `back`               | The open record                                        |
| `empty`   | —                              | Shown in the pane's place while nothing is open        |

## What it does not do

No selection, no data, no keyboard list navigation, and no draggable divider between the columns —
that one waits for [Splitter](/guide/roadmap). It is three regions and the rule for when there is
room for them.

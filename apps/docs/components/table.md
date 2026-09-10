<script setup>
import TableDemo from '../components/demos/TableDemo.vue'
</script>

# Table

`WxTable` renders rows and reports what the user did with them. It takes a Laravel paginator as it
arrives, so a controller that already returns `->paginate()` needs no reshaping.

<TableDemo />

## Usage

```vue
<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Paginated, RowKey, TableColumn, TableSort } from '@webx-ui/core'

interface Order {
  id: number
  customer: string
  total: number
}

const orders = ref<Paginated<Order> | null>(null)
const page = ref(1)
const sort = ref<TableSort | null>(null)
const selected = ref<RowKey[]>([])
const loading = ref(false)

const columns: TableColumn<Order>[] = [
  { key: 'id', label: '#', width: 80, sortable: true },
  { key: 'customer', label: 'Customer', sortable: true },
  { key: 'total', label: 'Total', align: 'right', formatter: (value) => `€${value}` },
]

async function load() {
  loading.value = true
  const query = new URLSearchParams({ page: String(page.value) })
  if (sort.value) query.set('sort', `${sort.value.order === 'desc' ? '-' : ''}${sort.value.key}`)
  orders.value = await fetch(`/api/orders?${query}`).then((response) => response.json())
  loading.value = false
}

watch([page, sort], load, { immediate: true })
</script>

<template>
  <wx-table
    v-model:sort="sort"
    v-model:selected="selected"
    :data="orders"
    :columns="columns"
    :loading="loading"
    selectable
    row-key="id"
  >
    <template #footer>
      <wx-pagination v-model:page="page" :paginator="orders" />
    </template>
  </wx-table>
</template>
```

`data` takes either the paginator or a plain array — the table only ever reads rows out of it.

## Sorting is reported, not applied

Clicking a sortable heading cycles ascending, descending, off, and each step lands in `sort` and in
`@sort-change`. The rows on screen do not move.

That is deliberate. What is on screen is one page out of an ordered query, so sorting it here would
shuffle fifteen rows and leave the other hundred where they were — the answer would be wrong and
would look right. Ordering belongs to whoever built the page, which for a paginated table is always
the backend. A small array that is not paginated is sorted the same way: in the caller, before it
reaches `data`.

## Selection

`v-model:selected` holds row **keys**, not rows, so a selection survives paging away and back. The
header checkbox works on the current page only and leaves keys picked elsewhere alone.

```vue
<wx-table
  v-model:selected="selected"
  :data="orders"
  :columns="columns"
  selectable
  row-key="id"
  :selectable-if="(row) => row.status !== 'refunded'"
/>
```

`@selection-change` hands back the keys and the rows behind them that are on this page.

Name a `rowKey` whenever rows can be selected. Without one the table falls back to the row's
position, which renders fine and then hands out the wrong keys after a sort.

## Columns

| Field         | Type                            | Description                                     |
| ------------- | ------------------------------- | ----------------------------------------------- |
| `key`         | `string`                        | Reads the value; a dotted path walks into a row |
| `label`       | `string`                        | Heading text, defaults to the key               |
| `width`       | `string \| number`              | Fixed width; a number is pixels                 |
| `minWidth`    | `string \| number`              | Lower bound before the table scrolls            |
| `align`       | `'left' \| 'center' \| 'right'` | Cell alignment                                  |
| `sortable`    | `boolean`                       | Adds the sort control                           |
| `formatter`   | `(value, row, index) => string` | Turns the raw value into cell text              |
| `headerClass` | `string`                        | Class on the `th`                               |
| `cellClass`   | `string`                        | Class on the `td`                               |
| `hidden`      | `boolean`                       | Leaves the column out                           |

A dotted `key` reads through an eager-loaded relation, so `user.name` lands in its own column
without a formatter. A path that goes nowhere renders as empty rather than as `undefined`.

## Slots

| Slot           | Props                             | What it replaces               |
| -------------- | --------------------------------- | ------------------------------ |
| `cell-<key>`   | `row`, `value`, `index`, `column` | The contents of that cell      |
| `header-<key>` | `column`                          | The heading text               |
| `empty`        | —                                 | The "nothing to show" line     |
| `loading`      | —                                 | The overlay's spinner and text |
| `footer`       | —                                 | A row across the whole table   |

```vue
<template #cell-status="{ value }">
  <wx-tag :type="value === 'paid' ? 'success' : 'warning'">{{ value }}</wx-tag>
</template>
```

A column whose `key` matches nothing in the row is a fine way to add an actions column: give it a
key of `actions` and fill it from `#cell-actions`.

## Props

| Prop           | Type                                  | Default             | Description                             |
| -------------- | ------------------------------------- | ------------------- | --------------------------------------- |
| `data`         | `T[] \| Paginated<T> \| null`         | `null`              | Rows, or a whole paginator              |
| `columns`      | `TableColumn<T>[]`                    | —                   | Required                                |
| `rowKey`       | `string \| (row, index) => RowKey`    | `'id'`              | Where a stable id comes from            |
| `loading`      | `boolean`                             | `false`             | Dims the table and marks it busy        |
| `emptyText`    | `string`                              | `'Nothing to show'` | Shown when there are no rows            |
| `stripe`       | `boolean`                             | `false`             | Alternating row background              |
| `bordered`     | `boolean`                             | `false`             | Vertical rules between columns          |
| `hover`        | `boolean`                             | `true`              | Highlight the row under the pointer     |
| `selectable`   | `boolean`                             | `false`             | Adds the checkbox column                |
| `selectableIf` | `(row) => boolean`                    | —                   | Rows that cannot be picked              |
| `maxHeight`    | `string \| number`                    | —                   | Scrolls the body under a sticky header  |
| `rowClass`     | `(row, index) => string \| undefined` | —                   | Extra class per row                     |
| `layout`       | `'auto' \| 'fixed'`                   | `'auto'`            | Let the content size columns, or do not |
| `size`         | `'sm' \| 'md' \| 'lg'`                | `'md'`              | Row height                              |
| `ariaLabel`    | `string`                              | —                   | Names the table for a screen reader     |

**Models:** `v-model:sort` (`TableSort | null`), `v-model:selected` (`RowKey[]`).

**Events:** `row-click` (`row, index, event`), `sort-change` (`TableSort | null`),
`selection-change` (`keys, rows`).

## Loading keeps the rows

The overlay sits over the table rather than replacing it, so a page being refreshed still says what
it said a moment ago. `aria-busy` goes on the `table` element, and the empty state waits until the
load finishes — a table that flashes "nothing to show" between two full pages is telling the user
something untrue.

## It brings its own table styles

A table is the component most exposed to whatever else the page loads. This documentation site is a
fair example: VitePress restyles every `table` into a scrolling block, puts a border on all four
sides of every cell and paints each `tr` opaque. That took the layout away from the table, the
stickiness away from the header and the stripes away from the rows — visible only once the
component was embedded somewhere real.

So the component states `display`, `overflow`, `margin`, `border` and the row background outright
rather than inheriting them. Dropping it into an admin that already loads Bootstrap or Tailwind's
preflight should look the same as it does here.

## The Laravel side

```php
public function index(Request $request)
{
    return Order::query()
        ->when($request->string('sort'), function ($query, $sort) {
            $descending = str_starts_with($sort, '-');
            $query->orderBy(ltrim($sort, '-'), $descending ? 'desc' : 'asc');
        })
        ->paginate($request->integer('per_page', 15));
}
```

The response goes straight into `data`. The keys the table reads — `data`, `current_page`,
`last_page`, `per_page`, `total`, `from`, `to` — are the ones the paginator already produces, and
`from` and `to` are used as sent rather than recalculated, so the count under the table matches the
one the database gave.

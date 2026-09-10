<script setup>
import TableDemo from '../components/demos/TableDemo.vue'
import TableSummaryDemo from '../components/demos/TableSummaryDemo.vue'
import TableFixedDemo from '../components/demos/TableFixedDemo.vue'
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

## Header and search

A `title` and `searchable` give the table a bar of its own: the heading on the left, the search
field and anything in `#actions` on the right.

```vue
<wx-table v-model:search="term" title="Orders" searchable @search="reload">
  <template #actions>
    <wx-button size="sm">Export</wx-button>
  </template>
</wx-table>
```

The field answers every keystroke; `@search` waits for the typing to settle first — 300 ms by
default, `:search-debounce="0"` to report immediately. So `v-model:search` is the text on screen
and `@search` is the moment to call the backend, which is the difference between one request and
one request per letter.

Searching is filtering, and filtering changes what page one is: reset `page` to 1 in the handler
before fetching, as the demo above does.

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

Name a `rowKey` whenever rows can be selected or expanded. Without one the table falls back to the
row's position, which renders fine and then hands out the wrong keys after a sort.

## Summary

<TableSummaryDemo />

`summary` is a list of lines under the table. Each has a `label` and figures keyed by column, and
the label spans every column ahead of the first figure — which is what a total wants: a caption on
the left, a number under its own column.

```vue
<script setup lang="ts">
const summary = computed(() => [
  { label: 'Sum', cells: { line: money(sum) } },
  { label: 'Discount', cells: { line: `−${money(discount)}` } },
  { label: 'Total', cells: { line: money(sum - discount) }, strong: true },
])
</script>

<template>
  <wx-table :data="items" :columns="columns" :summary="summary" />
</template>
```

It is a prop rather than a slot on purpose: figures are data, and data survives being written down
as JSON — which is what [`@webx-ui/schema`](/guide/#packages) will render an admin from. When a
figure needs markup, `summary-<key>` takes over that cell without giving up the structure.

Several lines stack in order, and `strong: true` marks the one that matters. The arithmetic stays
with the caller: a discount is not always a sum of a column, and a table that guesses at totals is
a table that is confidently wrong once a month.

`#footer` still works alongside it — that is where the pagination goes.

## Expandable rows

A row can open to show what does not fit in it, which is how a wide table stays narrow.

```vue
<wx-table v-model:expanded="open" :data="items" :columns="columns" expandable row-key="id">
  <template #expanded="{ row }">
    <p>{{ row.note }}</p>
  </template>
</wx-table>
```

`v-model:expanded` holds keys, like the selection, so several rows can be open at once and the set
is the caller's to control — open one by default by seeding the array. `expandable-if` decides
which rows have anything to show; the ones that do not get no chevron rather than an empty panel.

## Fixed columns, sticky header and footer

<TableFixedDemo />

`fixed: 'left'` or `'right'` on a column pins it while the rest scrolls sideways. The checkbox and
chevron columns are pinned along with it — a checkbox sliding under a frozen name column is worse
than no freezing at all.

```ts
const columns = [
  { key: 'date', label: 'Date', width: 130, fixed: 'left' },
  // ...
  { key: 'actions', label: '', width: 100, fixed: 'right' },
]
```

**A pinned column needs a `width`.** The resting place of each pinned column is the sum of the
widths declared before it, and a column of unknown width cannot say where the next one begins.

### Choosing the height

`max-height` is the only knob, and that is the recommendation: a number for pixels, or any CSS
length as a string.

```vue
<wx-table :data="rows" :columns="columns" :max-height="420" />
<wx-table :data="rows" :columns="columns" max-height="60vh" />
```

Setting it turns the header and the footer sticky and scrolls the rows between them, which is why
there is no separate `sticky` prop — a stuck header with nothing scrolling under it is decoration.

There is deliberately no `height`. A fixed height pads a short result with blank space and tells
the user the table failed to load; a maximum leaves three rows looking like three rows and only
takes over when there are forty. Use `60vh` when the table should follow the window, and pixels
when it sits in a panel whose size you already know.

## Columns

| Field         | Type                            | Description                                     |
| ------------- | ------------------------------- | ----------------------------------------------- |
| `key`         | `string`                        | Reads the value; a dotted path walks into a row |
| `label`       | `string`                        | Heading text, defaults to the key               |
| `width`       | `string \| number`              | Fixed width; a number is pixels                 |
| `minWidth`    | `string \| number`              | Lower bound before the table scrolls            |
| `align`       | `'left' \| 'center' \| 'right'` | Cell alignment                                  |
| `sortable`    | `boolean`                       | Adds the sort control                           |
| `fixed`       | `'left' \| 'right'`             | Pins the column; needs a `width`                |
| `formatter`   | `(value, row, index) => string` | Turns the raw value into cell text              |
| `headerClass` | `string`                        | Class on the `th`                               |
| `cellClass`   | `string`                        | Class on the `td`                               |
| `hidden`      | `boolean`                       | Leaves the column out                           |

A dotted `key` reads through an eager-loaded relation, so `user.name` lands in its own column
without a formatter. A path that goes nowhere renders as empty rather than as `undefined`.

## Slots

| Slot            | Props                             | What it replaces               |
| --------------- | --------------------------------- | ------------------------------ |
| `cell-<key>`    | `row`, `value`, `index`, `column` | The contents of that cell      |
| `header-<key>`  | `column`                          | The heading text               |
| `summary-<key>` | `row`, `value`                    | One figure in the summary      |
| `expanded`      | `row`, `index`                    | What an opened row shows       |
| `title`         | —                                 | The heading in the header bar  |
| `actions`       | —                                 | Buttons beside the search      |
| `empty`         | —                                 | The "nothing to show" line     |
| `loading`       | —                                 | The overlay's spinner and text |
| `footer`        | —                                 | A row across the whole table   |

```vue
<template #cell-status="{ value }">
  <wx-tag :type="value === 'paid' ? 'success' : 'warning'">{{ value }}</wx-tag>
</template>
```

A column whose `key` matches nothing in the row is a fine way to add an actions column: give it a
key of `actions` and fill it from `#cell-actions`.

## Props

| Prop                | Type                                  | Default             | Description                         |
| ------------------- | ------------------------------------- | ------------------- | ----------------------------------- |
| `data`              | `T[] \| Paginated<T> \| null`         | `null`              | Rows, or a whole paginator          |
| `columns`           | `TableColumn<T>[]`                    | —                   | Required                            |
| `rowKey`            | `string \| (row, index) => RowKey`    | `'id'`              | Where a stable id comes from        |
| `title`             | `string`                              | —                   | Heading in the header bar           |
| `searchable`        | `boolean`                             | `false`             | Adds the search field               |
| `searchPlaceholder` | `string`                              | `'Search'`          | Placeholder for it                  |
| `searchDebounce`    | `number`                              | `300`               | Wait before `search` fires, in ms   |
| `loading`           | `boolean`                             | `false`             | Dims the table and marks it busy    |
| `emptyText`         | `string`                              | `'Nothing to show'` | Shown when there are no rows        |
| `stripe`            | `boolean`                             | `false`             | Alternating row background          |
| `bordered`          | `boolean`                             | `false`             | Vertical rules between columns      |
| `hover`             | `boolean`                             | `true`              | Highlight the row under the pointer |
| `selectable`        | `boolean`                             | `false`             | Adds the checkbox column            |
| `selectableIf`      | `(row) => boolean`                    | —                   | Rows that cannot be picked          |
| `expandable`        | `boolean`                             | `false`             | Adds the chevron column             |
| `expandableIf`      | `(row) => boolean`                    | —                   | Rows with nothing to open           |
| `summary`           | `TableSummaryRow[]`                   | `[]`                | Lines under the table               |
| `maxHeight`         | `string \| number`                    | —                   | Scrolls rows under a stuck header   |
| `rowClass`          | `(row, index) => string \| undefined` | —                   | Extra class per row                 |
| `layout`            | `'auto' \| 'fixed'`                   | `'auto'`            | Let the content size columns or not |
| `size`              | `'sm' \| 'md' \| 'lg'`                | `'md'`              | Row height                          |
| `ariaLabel`         | `string`                              | —                   | Names the table for a screen reader |

**Models:** `v-model:sort` (`TableSort | null`), `v-model:selected` (`RowKey[]`),
`v-model:expanded` (`RowKey[]`), `v-model:search` (`string`).

**Events:** `row-click` (`row, index, event`), `sort-change` (`TableSort | null`),
`selection-change` (`keys, rows`), `expand-change` (`keys, rows`), `search` (`term`).

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

It also sets its own `min-width: 0`. A flex or grid item refuses to shrink below its content, and
the content here is a table that can be twice the width of the page — without it the inner scroller
never scrolls and the whole document does instead, which is the kind of thing that only shows up
inside somebody's layout.

## The Laravel side

```php
public function index(Request $request)
{
    return Order::query()
        ->when($request->string('search'), fn ($query, $term) => $query->where('customer', 'like', "%{$term}%"))
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

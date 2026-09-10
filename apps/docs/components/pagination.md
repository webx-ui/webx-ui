<script setup>
import PaginationDemo from '../components/demos/PaginationDemo.vue'
</script>

# Pagination

`WxPagination` moves between pages. Hand it a Laravel paginator and it needs nothing else.

<PaginationDemo />

## Usage

```vue
<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Paginated } from '@webx-ui/core'

const orders = ref<Paginated<Order> | null>(null)
const page = ref(1)

watch(
  page,
  async (value) => {
    orders.value = await fetch(`/api/orders?page=${value}`).then((r) => r.json())
  },
  { immediate: true },
)
</script>

<template>
  <wx-pagination v-model:page="page" :paginator="orders" />
</template>
```

The current page, the page size and the totals all come out of the paginator. `v-model:page` is
there for state the caller would rather own — a query parameter, a store — and takes precedence
when it is bound.

Without a paginator, `total` and `per-page` are enough:

```vue
<wx-pagination v-model:page="page" :total="42" :per-page="10" />
```

## Page size

```vue
<wx-pagination
  v-model:page="page"
  v-model:per-page="perPage"
  :paginator="orders"
  :per-page-options="[15, 30, 50]"
/>
```

Changing the size returns to the first page. A larger page can put the current position past the
end, and asking the backend for a page that is not there is a worse answer than starting over.

The control is left out entirely unless `per-page-options` is given.

## Props

| Prop             | Type                   | Default        | Description                             |
| ---------------- | ---------------------- | -------------- | --------------------------------------- |
| `paginator`      | `Paginated \| null`    | `null`         | A page as `->paginate()` sends it       |
| `total`          | `number`               | —              | Rows in total, without a paginator      |
| `lastPage`       | `number`               | —              | Worked out from `total` when missing    |
| `siblings`       | `number`               | `1`            | Page buttons either side of the current |
| `perPageOptions` | `number[]`             | `[]`           | Offers the page-size control            |
| `showTotal`      | `boolean`              | `true`         | Shows the "31–45 of 128" line           |
| `disabled`       | `boolean`              | `false`        | Blocks every control                    |
| `size`           | `'sm' \| 'md' \| 'lg'` | `'md'`         | Button height                           |
| `ariaLabel`      | `string`               | `'Pagination'` | Names the nav landmark                  |

**Models:** `v-model:page` (`number`), `v-model:per-page` (`number`).

**Events:** `change` (`{ page, perPage }`) — fires once per move, after both models settle.

**Slots:** `total`, with `from`, `to` and `total`, for wording the count differently.

## What gets folded away

The first and last pages are always reachable, the current page keeps `siblings` neighbours, and
what is skipped becomes an ellipsis — except a gap of exactly one page, which is spelled out. An
ellipsis standing in for a single number costs a click and saves nothing.

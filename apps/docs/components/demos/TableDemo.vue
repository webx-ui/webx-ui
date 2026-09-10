<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { WxPagination, WxTable } from '@webx-ui/core'
import type { Paginated, RowKey, TableColumn, TableSort } from '@webx-ui/core'

interface Order extends Record<string, unknown> {
  id: number
  customer: string
  status: 'paid' | 'pending' | 'refunded'
  total: number
  created_at: string
}

const NAMES = [
  'Ada Lovelace',
  'Grace Hopper',
  'Alan Turing',
  'Katherine Johnson',
  'Edsger Dijkstra',
  'Barbara Liskov',
  'Donald Knuth',
]

const STATUSES: Order['status'][] = ['paid', 'pending', 'refunded']

/** Stands in for the database. In an admin this lives behind an HTTP call. */
const table: Order[] = Array.from({ length: 47 }, (_, index) => ({
  id: 1000 + index,
  customer: NAMES[index % NAMES.length],
  status: STATUSES[index % STATUSES.length],
  total: Math.round((40 + ((index * 37) % 900)) * 100) / 100,
  created_at: new Date(2026, 0, 1 + index * 3).toISOString().slice(0, 10),
}))

const page = ref(1)
const perPage = ref(5)
const sort = ref<TableSort | null>({ key: 'created_at', order: 'desc' })
const selected = ref<RowKey[]>([])
const search = ref('')
const loading = ref(false)

/** What a controller does: filter, order, then `->paginate()`. */
function query(): Paginated<Order> {
  const term = search.value.trim().toLowerCase()
  const rows = table.filter(
    (order) =>
      !term || order.customer.toLowerCase().includes(term) || String(order.id).includes(term),
  )

  if (sort.value) {
    const { key, order } = sort.value
    rows.sort((a, b) => {
      const left = a[key] as string | number
      const right = b[key] as string | number
      const result = left > right ? 1 : left < right ? -1 : 0
      return order === 'asc' ? result : -result
    })
  }

  const total = rows.length
  const lastPage = Math.max(1, Math.ceil(total / perPage.value))
  const current = Math.min(page.value, lastPage)
  const start = (current - 1) * perPage.value
  const data = rows.slice(start, start + perPage.value)

  return {
    data,
    current_page: current,
    last_page: lastPage,
    per_page: perPage.value,
    total,
    from: total === 0 ? null : start + 1,
    to: total === 0 ? null : start + data.length,
  }
}

const result = ref<Paginated<Order>>(query())

const columns: TableColumn<Order>[] = [
  { key: 'id', label: '#', width: 80, sortable: true },
  { key: 'customer', label: 'Customer', minWidth: 170, sortable: true },
  { key: 'status', label: 'Status', width: 120 },
  {
    key: 'total',
    label: 'Total',
    width: 120,
    align: 'right',
    sortable: true,
    formatter: (value) => `€${Number(value).toFixed(2)}`,
  },
  { key: 'created_at', label: 'Created', width: 130, sortable: true },
]

let timer: ReturnType<typeof setTimeout> | undefined

/** The round trip a real admin would make, slowed down enough to see. */
function reload() {
  loading.value = true
  clearTimeout(timer)
  timer = setTimeout(() => {
    result.value = query()
    loading.value = false
  }, 450)
}

watch([page, perPage, sort], reload)
onBeforeUnmount(() => clearTimeout(timer))

/** The search event has already waited for the typing to settle. */
function onSearch() {
  page.value = 1
  reload()
}

const STATUS_COLOUR: Record<Order['status'], string> = {
  paid: 'var(--wx-color-success)',
  pending: 'var(--wx-color-warning)',
  refunded: 'var(--wx-text-muted)',
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-table
      v-model:sort="sort"
      v-model:selected="selected"
      v-model:search="search"
      title="Orders"
      searchable
      search-placeholder="Customer or number"
      :data="result"
      :columns="columns"
      :loading="loading"
      selectable
      stripe
      row-key="id"
      aria-label="Orders"
      @search="onSearch"
    >
      <template #cell-status="{ value }">
        <span class="demo-pill" :style="{ color: STATUS_COLOUR[value as Order['status']] }">
          {{ value }}
        </span>
      </template>

      <template #footer>
        <wx-pagination
          v-model:page="page"
          v-model:per-page="perPage"
          :paginator="result"
          :per-page-options="[5, 10, 25]"
        />
      </template>
    </wx-table>

    <p class="demo-note">
      Sort: <code>{{ sort ? `${sort.key} ${sort.order}` : 'none' }}</code> &middot; selected:
      <code>{{ selected.length }}</code>
      <template v-if="selected.length"> ({{ selected.join(', ') }})</template>
    </p>

    <div>
      <span class="wx-demo__label">Nothing to show</span>
      <wx-table :data="[]" :columns="columns" empty-text="No orders in this period" />
    </div>
  </div>
</template>

<style scoped>
.demo-pill {
  font-size: 13px;
  font-weight: 600;
  text-transform: capitalize;
}

.demo-note {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: 13px;
}
</style>

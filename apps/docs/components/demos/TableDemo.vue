<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { WxTable } from '@webx-ui/core'
import type { Paginated, RowKey, TableColumn, TableState } from '@webx-ui/core'

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

const selected = ref<RowKey[]>([])
const loading = ref(false)
const lastState = ref<TableState | null>(null)

/** What a controller does: filter, order, then `->paginate()`. */
function query(state: TableState): Paginated<Order> {
  const term = state.search.trim().toLowerCase()
  const rows = table.filter(
    (order) =>
      !term || order.customer.toLowerCase().includes(term) || String(order.id).includes(term),
  )

  if (state.sort) {
    const { key, order } = state.sort
    rows.sort((a, b) => {
      const left = a[key] as string | number
      const right = b[key] as string | number
      const result = left > right ? 1 : left < right ? -1 : 0
      return order === 'asc' ? result : -result
    })
  }

  const total = rows.length
  const lastPage = Math.max(1, Math.ceil(total / state.perPage))
  const current = Math.min(state.page, lastPage)
  const start = (current - 1) * state.perPage
  const data = rows.slice(start, start + state.perPage)

  return {
    data,
    current_page: current,
    last_page: lastPage,
    per_page: state.perPage,
    total,
    from: total === 0 ? null : start + 1,
    to: total === 0 ? null : start + data.length,
  }
}

const result = ref<Paginated<Order> | null>(null)

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

/** One entry point: page, size, sort and search all arrive together. */
function load(state: TableState) {
  lastState.value = state
  loading.value = true
  clearTimeout(timer)
  timer = setTimeout(() => {
    result.value = query(state)
    loading.value = false
  }, 450)
}

onBeforeUnmount(() => clearTimeout(timer))

const STATUS_COLOUR: Record<Order['status'], string> = {
  paid: 'var(--wx-color-success)',
  pending: 'var(--wx-color-warning)',
  refunded: 'var(--wx-text-muted)',
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-table
      v-model:selected="selected"
      title="Orders"
      searchable
      search-placeholder="Customer or number"
      :data="result"
      :columns="columns"
      :loading="loading"
      :per-page="5"
      :per-page-options="[5, 10, 25]"
      pagination
      selectable
      stripe
      row-key="id"
      persist="docs-orders"
      aria-label="Orders"
      @state-change="load"
    >
      <template #cell-status="{ value }">
        <span class="demo-pill" :style="{ color: STATUS_COLOUR[value as Order['status']] }">
          {{ value }}
        </span>
      </template>
    </wx-table>

    <p class="demo-note">
      Asked for:
      <code>{{ lastState ? JSON.stringify(lastState) : '—' }}</code>
      &middot; selected: <code>{{ selected.length }}</code>
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
  overflow-wrap: anywhere;
}
</style>

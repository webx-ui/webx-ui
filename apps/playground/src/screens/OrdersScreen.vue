<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import type { Paginated, RowKey, TableColumn, TableState, TableSummaryRow } from '@webx-ui/core'

/**
 * The screen most admin routes actually are: a table with a filter bar over it, a
 * toolbar that wakes up when rows are ticked, and a record that opens in a panel.
 * Everything here is filler, but the plumbing is the real thing — one `state-change`
 * carries page, size, sort and search to what stands in for the controller.
 */
type Status = 'new' | 'paid' | 'shipped' | 'refunded'

interface Order extends Record<string, unknown> {
  id: number
  number: string
  customer: string
  email: string
  status: Status
  items: number
  total: number
  created_at: string
}

const CUSTOMERS = [
  ['Ганна Роут', 'hanna@example.com'],
  ['Тарас Кемп', 'taras@example.com'],
  ['Nova Build', 'office@novabuild.example'],
  ['Олег Мороз', 'oleh@example.com'],
  ['Lehmann GmbH', 'einkauf@lehmann.example'],
  ['Марія Цвях', 'maria@example.com'],
]

const STATUSES: Status[] = ['new', 'paid', 'shipped', 'refunded']

const LABELS: Record<
  Status,
  { text: string; type: 'warning' | 'primary' | 'success' | 'default' }
> = {
  new: { text: 'Нове', type: 'warning' },
  paid: { text: 'Оплачено', type: 'primary' },
  shipped: { text: 'Відправлено', type: 'success' },
  refunded: { text: 'Повернення', type: 'default' },
}

/** Stands in for the database. In an admin this lives behind an HTTP call. */
const rows: Order[] = Array.from({ length: 84 }, (_, index) => {
  const [customer, email] = CUSTOMERS[index % CUSTOMERS.length]
  return {
    id: 4100 - index,
    number: `WX-${4100 - index}`,
    customer,
    email,
    status: STATUSES[index % STATUSES.length],
    items: 1 + (index % 5),
    total: Math.round((120 + ((index * 317) % 9400)) * 100) / 100,
    created_at: new Date(2026, 8, 11 - Math.floor(index / 2)).toISOString().slice(0, 10),
  }
})

/* --------------------------------------------------------------- filtering --- */

const status = ref<Status | 'all'>('all')
const period = ref<string[] | null>(null)
const onlyBig = ref(false)

const statusOptions = [
  { label: 'Усі статуси', value: 'all' },
  ...STATUSES.map((name) => ({ label: LABELS[name].text, value: name })),
]

const filtersOn = computed(
  () => status.value !== 'all' || period.value !== null || onlyBig.value === true,
)

function resetFilters() {
  status.value = 'all'
  period.value = null
  onlyBig.value = false
  reload()
}

/* ------------------------------------------------------------------ loading --- */

const result = ref<Paginated<Order> | null>(null)
const loading = ref(false)
const selected = ref<RowKey[]>([])
const state = ref<TableState | null>(null)

/** What a controller does: filter, order, then `->paginate()`. */
function query(request: TableState): Paginated<Order> {
  const term = request.search.trim().toLowerCase()

  const found = rows.filter((order) => {
    if (status.value !== 'all' && order.status !== status.value) return false
    if (onlyBig.value && order.total < 3000) return false
    if (period.value) {
      const [from, to] = period.value
      if (from && order.created_at < from) return false
      if (to && order.created_at > to) return false
    }
    if (!term) return true
    return `${order.number} ${order.customer} ${order.email}`.toLowerCase().includes(term)
  })

  if (request.sort) {
    const { key, order } = request.sort
    found.sort((a, b) => {
      const left = a[key] as string | number
      const right = b[key] as string | number
      const result = left > right ? 1 : left < right ? -1 : 0
      return order === 'asc' ? result : -result
    })
  }

  const total = found.length
  const lastPage = Math.max(1, Math.ceil(total / request.perPage))
  const current = Math.min(request.page, lastPage)
  const start = (current - 1) * request.perPage
  const data = found.slice(start, start + request.perPage)

  return {
    data,
    current_page: current,
    last_page: lastPage,
    per_page: request.perPage,
    total,
    from: total === 0 ? null : start + 1,
    to: total === 0 ? null : start + data.length,
  }
}

let timer: ReturnType<typeof setTimeout> | undefined

function load(request: TableState) {
  state.value = request
  loading.value = true
  clearTimeout(timer)
  /* The delay is here on purpose: the loading state is part of the screen. */
  timer = setTimeout(() => {
    result.value = query(request)
    loading.value = false
  }, 350)
}

function reload() {
  if (state.value) load(state.value)
}

onBeforeUnmount(() => clearTimeout(timer))

/* ------------------------------------------------------------------ columns --- */

const money = (value: unknown) =>
  `€${Number(value).toLocaleString('uk-UA', { minimumFractionDigits: 2 })}`

const columns: TableColumn<Order>[] = [
  { key: 'number', label: '№', width: 110, sortable: true, fixed: 'left' },
  { key: 'customer', label: 'Клієнт', minWidth: 220, sortable: true },
  { key: 'status', label: 'Статус', width: 140 },
  { key: 'items', label: 'Позицій', width: 100, align: 'center' },
  { key: 'total', label: 'Сума', width: 140, align: 'right', sortable: true, formatter: money },
  { key: 'created_at', label: 'Створено', width: 130, sortable: true },
  { key: 'actions', label: '', width: 108, align: 'right', fixed: 'right' },
]

const summary = computed<TableSummaryRow[]>(() => {
  const page = result.value?.data ?? []
  if (page.length === 0) return []
  return [
    {
      label: `На сторінці — ${page.length}`,
      cells: {
        items: page.reduce((sum, order) => sum + order.items, 0),
        total: money(page.reduce((sum, order) => sum + order.total, 0)),
      },
      strong: true,
    },
  ]
})

/* ------------------------------------------------------------------- record --- */

const open = ref(false)
const current = ref<Order | null>(null)

function show(order: Order) {
  current.value = order
  open.value = true
}
</script>

<template>
  <div class="orders">
    <wx-breadcrumb>
      <wx-breadcrumb-item href="#">Головна</wx-breadcrumb-item>
      <wx-breadcrumb-item>Замовлення</wx-breadcrumb-item>
    </wx-breadcrumb>

    <header class="orders__head">
      <div class="orders__title">
        <wx-heading :level="1" size="lg">Замовлення</wx-heading>
        <wx-badge v-if="result" size="sm" round>{{ result.total }}</wx-badge>
      </div>

      <wx-space size="sm">
        <wx-button variant="outline" size="sm">
          <template #icon><wx-icon name="download" /></template>
          Експорт
        </wx-button>
        <wx-button type="primary" size="sm">
          <template #icon><wx-icon name="plus" /></template>
          Нове замовлення
        </wx-button>
      </wx-space>
    </header>

    <wx-card padding="sm" bordered shadow="never">
      <div class="orders__filters">
        <wx-select
          v-model="status"
          :options="statusOptions"
          size="sm"
          aria-label="Статус"
          style="width: 180px"
          @change="reload"
        />
        <wx-date-range-picker
          v-model="period"
          size="sm"
          placeholder="Період"
          clearable
          class="orders__period"
          @change="reload"
        />
        <wx-checkbox v-model="onlyBig" size="sm" label="Від €3 000" @change="reload" />
        <wx-button v-if="filtersOn" variant="text" size="sm" @click="resetFilters">
          <template #icon><wx-icon name="close" /></template>
          Скинути
        </wx-button>
      </div>
    </wx-card>

    <!-- The toolbar only exists while something is ticked; an empty one is furniture. -->
    <wx-alert v-if="selected.length > 0" type="info" class="orders__bulk">
      Вибрано {{ selected.length }}
      <template #actions>
        <wx-space size="sm">
          <wx-button size="sm" variant="outline">Позначити оплаченими</wx-button>
          <wx-button size="sm" variant="outline">Експортувати</wx-button>
          <wx-button size="sm" variant="text" type="danger">Видалити</wx-button>
        </wx-space>
      </template>
    </wx-alert>

    <wx-table
      v-model:selected="selected"
      searchable
      search-placeholder="Номер, клієнт або пошта"
      :data="result"
      :columns="columns"
      :loading="loading"
      :per-page="10"
      :per-page-options="[10, 25, 50]"
      :summary="summary"
      pagination
      selectable
      hover
      bordered
      row-key="id"
      empty-text="Замовлень за цими умовами немає"
      aria-label="Замовлення"
      @state-change="load"
      @row-click="show"
    >
      <template #cell-customer="{ row }">
        <div class="orders__customer">
          <wx-text size="sm" weight="medium">{{ row.customer }}</wx-text>
          <wx-text size="xs" tone="muted">{{ row.email }}</wx-text>
        </div>
      </template>

      <template #cell-status="{ value }">
        <wx-badge :type="LABELS[value as Status].type" size="sm">
          {{ LABELS[value as Status].text }}
        </wx-badge>
      </template>

      <template #cell-actions="{ row }">
        <wx-actions size="sm" @click.stop>
          <wx-action type="details" @click="show(row)" />
          <wx-action type="edit" />
          <wx-action type="remove" />
        </wx-actions>
      </template>
    </wx-table>

    <wx-drawer v-model:open="open" side="right" :size="460" closable :title="current?.number">
      <div v-if="current" class="orders__record">
        <wx-badge :type="LABELS[current.status].type">{{ LABELS[current.status].text }}</wx-badge>

        <dl class="orders__facts">
          <dt>Клієнт</dt>
          <dd>{{ current.customer }}</dd>
          <dt>Пошта</dt>
          <dd>{{ current.email }}</dd>
          <dt>Позицій</dt>
          <dd>{{ current.items }}</dd>
          <dt>Сума</dt>
          <dd>{{ money(current.total) }}</dd>
          <dt>Створено</dt>
          <dd>{{ current.created_at }}</dd>
        </dl>

        <wx-tabs variant="line" size="sm">
          <wx-tab value="items" label="Позиції">
            <wx-timeline>
              <wx-timeline-item v-for="n in current.items" :key="n" :title="`Позиція ${n}`">
                Lorem ipsum dolor sit amet — {{ money(current.total / current.items) }}
              </wx-timeline-item>
            </wx-timeline>
          </wx-tab>
          <wx-tab value="history" label="Історія">
            <wx-text size="sm" tone="muted">
              Consectetur adipiscing elit. Integer posuere erat a ante venenatis dapibus posuere
              velit aliquet.
            </wx-text>
          </wx-tab>
        </wx-tabs>
      </div>

      <template #footer>
        <wx-button variant="outline" @click="open = false">Закрити</wx-button>
        <wx-button type="primary">Відкрити повністю</wx-button>
      </template>
    </wx-drawer>
  </div>
</template>

<style scoped>
.orders {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.orders__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
}

.orders__title {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
}

.orders__filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.orders__period {
  width: 260px;
}

.orders__customer {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.orders__record {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.orders__facts {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: var(--wx-space-6) var(--wx-space-16);
  margin: 0;
  font-size: var(--wx-font-size-sm);
}

.orders__facts dt {
  color: var(--wx-text-muted);
}

.orders__facts dd {
  margin: 0;
}
</style>

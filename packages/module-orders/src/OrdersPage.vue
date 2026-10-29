<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  createModal,
  toast,
  WxBadge,
  WxButton,
  WxDatePicker,
  WxEntityCard,
  WxFormItem,
  WxIcon,
  WxSelect,
  WxTable,
  WxText,
  WxTooltip,
  useElementWidth,
  type DatePickerModelValue,
  type TabItem,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxFilterChips,
  WxListScreen,
  WxRowMenu,
  type AppliedFilter,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import CancelDialog from './CancelDialog.vue'
import { NAMESPACE, useOrdersMessages } from './i18n'
import StatusDialog from './StatusDialog.vue'
import { toneOf } from './tones'
import type {
  MassCancelAnswer,
  MassStatusAnswer,
  OrderQuery,
  OrderRow,
  OrdersMeta,
  OrdersPage,
  OrdersView,
  PublicStatusGroup,
} from './types'

/**
 * «Orders» (spec §11.2): the number and the date, the buyer, what was bought in short, the money,
 * the internal status with the public one under it, the marks, delivery and payment. Four views
 * over it — new, awaiting payment, payment failed, all — and the filters behind the funnel; all
 * of it in the address, so coming back from a card lands where the list was left. The selection
 * moves to a status or cancels, each with a preview of what it does before it does it.
 */
defineOptions({ name: 'WxOrdersPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/orders' })

const admin = useAdmin()
const api = createOrdersApi(admin)
const route = useRoute()
const router = useRouter()
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const message = useErrorText()

const canManage = computed(() => admin.can('orders.manage'))

/* The statuses and the settings of the section, for whoever may change them (§11.4); the settings
 * also for whoever edits only their tab «Checkout» (§31.1). */
const headActions = computed<ScreenAction[]>(() => [
  ...(admin.can('orders.statuses')
    ? [
        {
          key: 'statuses',
          label: t('panel.to-statuses'),
          run: () => void router.push(`${props.base}/statuses`),
        },
      ]
    : []),
  ...(admin.can('orders.statuses') || admin.can('orders.checkout.settings')
    ? [
        {
          key: 'settings',
          label: t('panel.to-settings'),
          run: () => void router.push(`${props.base}/settings`),
        },
      ]
    : []),
])

const page = ref<OrdersPage | null>(null)
const meta = ref<OrdersMeta>({
  statuses: [],
  methods: { delivery: [], payment: [] },
  initial_status: null,
  visitors: false,
})
const loading = ref(true)
const selected = ref<(string | number)[]>([])

const statusDialog = createModal<
  MassStatusAnswer,
  { statuses: PublicStatusGroup[]; ids: number[] }
>(StatusDialog)
const cancelDialog = createModal<MassCancelAnswer, { ids: number[] }>(CancelDialog)

const text = (key: string): string | null => (route.query[key] ? String(route.query[key]) : null)
const flag = (key: string): boolean | null =>
  route.query[key] === '1' ? true : route.query[key] === '0' ? false : null

const VIEWS: OrdersView[] = ['new', 'awaiting', 'failed', 'all']
const view = ref<OrdersView>(
  VIEWS.includes(text('view') as OrdersView) ? (text('view') as OrdersView) : 'all',
)
const search = ref(String(route.query.q ?? ''))
const pageNumber = ref(Number(route.query.page ?? 1) || 1)
const status = ref<string | null>(text('status'))
const publicStatus = ref<string | null>(text('public'))
const paid = ref<boolean | null>(flag('paid'))
const received = ref<boolean | null>(flag('received'))
const cancelled = ref<boolean | null>(flag('cancelled'))
const refunded = ref<boolean | null>(flag('refunded'))
const visitor = ref<string | null>(text('visitor'))
const delivery = ref<string | null>(text('delivery'))
const payment = ref<string | null>(text('payment'))
const source = ref<string | null>(text('source'))
const from = ref<string | null>(text('from'))
const to = ref<string | null>(text('to'))
const sort = ref<string>(text('sort') ?? '-placed_at')
let perPage = 25

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'orders')?.title ??
    t('module.orders'),
)

const views = computed<TabItem[]>(() =>
  VIEWS.map((value) => ({
    value,
    label: t(`panel.view-${value}`),
    badge: value === 'all' ? undefined : page.value?.counts[value] || undefined,
  })),
)

const yesNo = computed(() => [
  { value: '1', label: t('panel.yes') },
  { value: '0', label: t('panel.no') },
])

/** Every status, the deleted ones marked: an order may still stand in one (§5.6). */
const statusOptions = computed(() =>
  meta.value.statuses.flatMap((group) =>
    group.statuses.map((one) => ({
      value: String(one.id),
      label: `${group.name} · ${one.name}${one.deleted ? ` (${t('panel.deleted')})` : ''}`,
    })),
  ),
)

const publicOptions = computed(() =>
  meta.value.statuses.map((group) => ({
    value: String(group.id),
    label: group.deleted ? `${group.name} (${t('panel.deleted')})` : group.name,
  })),
)

const visitorOptions = computed(() => [
  { value: 'guest', label: t('panel.guest') },
  { value: 'visitor', label: t('panel.visitor') },
])

/** Where an order may come from; a source of the site's own code is still found by the address. */
const SOURCES = ['checkout', 'quick', 'api', 'mcp'] as const

const sourceOptions = computed(() =>
  SOURCES.map((value) => ({ value, label: t(`panel.source-${value}`) })),
)

const methodOptions = (block: 'delivery' | 'payment') =>
  meta.value.methods[block].map((one) => ({ value: one, label: one }))

const sorts = computed(() => [
  { value: '-placed_at', label: t('panel.sort-newest') },
  { value: 'placed_at', label: t('panel.sort-oldest') },
  { value: '-total', label: t('panel.sort-total-desc') },
  { value: 'total', label: t('panel.sort-total-asc') },
])

const labelOf = (options: { value: string; label: string }[], value: string | null) =>
  options.find((one) => one.value === value)?.label ?? value ?? ''

const yesNoLabel = (value: boolean) => t(value ? 'panel.yes' : 'panel.no')

/** The yes-or-no filters of the milestones, drawn alike. */
const FLAGS = [
  ['paid', paid],
  ['received', received],
  ['cancelled', cancelled],
  ['refunded', refunded],
] as const

const flagFilters = computed(() =>
  FLAGS.map(([key, model]) => ({
    key,
    value: model.value === null ? null : model.value ? '1' : '0',
    set: (value: unknown) => (model.value = value == null ? null : value === '1'),
  })),
)

const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []
  const chip = (key: string, label: string, clear: () => void) => chips.push({ key, label, clear })

  if (status.value !== null) {
    chip(
      'status',
      `${t('panel.filter-status')}: ${labelOf(statusOptions.value, status.value)}`,
      () => (status.value = null),
    )
  }

  if (publicStatus.value !== null) {
    chip(
      'public',
      `${t('panel.filter-public')}: ${labelOf(publicOptions.value, publicStatus.value)}`,
      () => (publicStatus.value = null),
    )
  }

  for (const [key, model] of FLAGS) {
    if (model.value !== null) {
      chip(
        key,
        `${t(`panel.filter-${key}`)}: ${yesNoLabel(model.value)}`,
        () => (model.value = null),
      )
    }
  }

  if (visitor.value !== null) {
    chip('visitor', labelOf(visitorOptions.value, visitor.value), () => (visitor.value = null))
  }

  if (delivery.value !== null) {
    chip(
      'delivery',
      `${t('panel.filter-delivery')}: ${delivery.value}`,
      () => (delivery.value = null),
    )
  }

  if (payment.value !== null) {
    chip('payment', `${t('panel.filter-payment')}: ${payment.value}`, () => (payment.value = null))
  }

  if (source.value !== null) {
    chip(
      'source',
      `${t('panel.filter-source')}: ${labelOf(sourceOptions.value, source.value)}`,
      () => (source.value = null),
    )
  }

  if (from.value !== null)
    chip('from', `${t('panel.filter-from')}: ${from.value}`, () => (from.value = null))
  if (to.value !== null) chip('to', `${t('panel.filter-to')}: ${to.value}`, () => (to.value = null))

  return chips
})

function clearFilters(): void {
  status.value = null
  publicStatus.value = null
  paid.value = null
  received.value = null
  cancelled.value = null
  refunded.value = null
  visitor.value = null
  delivery.value = null
  payment.value = null
  source.value = null
  from.value = null
  to.value = null
}

function query(state?: TableState): OrderQuery {
  return {
    view: view.value === 'all' ? undefined : view.value,
    search: state?.search ?? search.value,
    status: status.value ?? undefined,
    public: publicStatus.value ?? undefined,
    paid: paid.value ?? undefined,
    received: received.value ?? undefined,
    cancelled: cancelled.value ?? undefined,
    refunded: refunded.value ?? undefined,
    visitor: (visitor.value as OrderQuery['visitor']) ?? undefined,
    delivery: delivery.value ?? undefined,
    payment: payment.value ?? undefined,
    source: source.value ?? undefined,
    from: from.value ?? undefined,
    to: to.value ?? undefined,
    sort: sort.value,
    page: state?.page ?? pageNumber.value,
    per_page: state?.perPage ?? perPage,
  }
}

let asked = 0

async function load(state?: TableState): Promise<void> {
  const mine = ++asked

  loading.value = true

  try {
    const answer = await api.list(query(state))

    if (mine === asked) page.value = answer
  } catch (error) {
    toast.danger(message(error))
  } finally {
    if (mine === asked) loading.value = false
  }
}

function remember(): void {
  const wanted: LocationQueryRaw = {}
  const q = query()

  if (q.view) wanted.view = q.view
  if (q.search) wanted.q = q.search
  if (q.page && q.page > 1) wanted.page = String(q.page)
  if (q.sort && q.sort !== '-placed_at') wanted.sort = q.sort

  for (const key of [
    'status',
    'public',
    'visitor',
    'delivery',
    'payment',
    'source',
    'from',
    'to',
  ] as const) {
    if (q[key]) wanted[key] = q[key]
  }

  for (const key of ['paid', 'received', 'cancelled', 'refunded'] as const) {
    if (q[key] !== undefined) wanted[key] = q[key] ? '1' : '0'
  }

  void router.replace({ query: wanted })
}

function onState(state: TableState): void {
  perPage = state.perPage
  void load(state)
}

watch(
  [
    view,
    status,
    publicStatus,
    paid,
    received,
    cancelled,
    refunded,
    visitor,
    delivery,
    payment,
    source,
    from,
    to,
    sort,
  ],
  () => {
    pageNumber.value = 1
    selected.value = []
    remember()
    void load()
  },
)

watch([search, pageNumber], remember)

/* Narrow, an order is one entity: who bought, the number and the money, the status beside it. */
const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const asCards = computed(() => width.value > 0 && width.value < 640)

const columns = computed<TableColumn<OrderRow>[]>(() => {
  if (asCards.value) return [{ key: 'card', label: '' }]

  return [
    { key: 'number', label: t('panel.column-number'), width: 150 },
    { key: 'customer', label: t('panel.column-customer') },
    { key: 'items', label: t('panel.column-items'), width: 200, hideBelow: 1100 },
    { key: 'total', label: t('panel.column-total'), width: 130, align: 'right' },
    { key: 'status', label: t('panel.column-status'), width: 190 },
    { key: 'marks', label: t('panel.column-marks'), width: 120, hideBelow: 900 },
    { key: 'blocks', label: t('panel.column-blocks'), width: 180, hideBelow: 1280 },
    { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
  ]
})

function open(row: OrderRow): void {
  void router.push(`${props.base}/${row.id}`)
}

function numberOf(row: OrderRow): string {
  return row.number ?? `#${row.id}`
}

function itemsOf(row: OrderRow): string {
  if (!row.items.first) return '—'
  if (row.items.more === 0) return row.items.first

  return t('panel.items-more', { first: row.items.first, count: row.items.more })
}

function actionsFor(row: OrderRow): RowAction[] {
  const actions: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(row) },
  ]

  if (canManage.value) {
    actions.push({
      key: 'status',
      icon: 'refresh',
      label: t('panel.status-change'),
      run: () => void moveSelected([row.id]),
    })
  }

  return actions
}

const ids = computed(() => selected.value.map((key) => Number(key)))

async function moveSelected(chosen = ids.value): Promise<void> {
  const answer = await statusDialog({ statuses: meta.value.statuses, ids: chosen })

  if (!answer) return

  toast.success(t('panel.mass-moved', { count: answer.moved }))
  if (answer.refused.length > 0)
    toast.warning(t('panel.mass-refused', { count: answer.refused.length }))
  selected.value = []
  void load()
}

async function cancelSelected(): Promise<void> {
  const answer = await cancelDialog({ ids: ids.value })

  if (!answer) return

  toast.success(t('panel.mass-cancelled', { count: answer.cancelled }))
  if (answer.refused.length > 0)
    toast.warning(t('panel.mass-refused', { count: answer.refused.length }))
  selected.value = []
  void load()
}

const emptyText = computed(() =>
  applied.value.length > 0 || search.value || view.value !== 'all'
    ? t('panel.nothing-found')
    : t('module.empty'),
)

const day = (value: DatePickerModelValue): string | null =>
  typeof value === 'string' && value !== '' ? value.slice(0, 10) : null

onMounted(async () => {
  try {
    meta.value = await api.meta()
  } catch {
    // The list works without its filters' choices; the error of the list itself says enough.
  }
})
</script>

<template>
  <div ref="root" class="wx-orders">
    <wx-list-screen v-model:view="view" :title="title" :views="views" :actions="headActions">
      <wx-table
        v-model:search="search"
        v-model:page="pageNumber"
        v-model:selected="selected"
        :loading-text="panel('filters.loading')"
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        clickable
        hover
        flush
        :selectable="canManage"
        :select-row-label="panel('filters.select-row')"
        :select-all-label="panel('filters.select-all')"
        :loading="loading"
        layout="fixed"
        :cards-below="asCards ? Number.POSITIVE_INFINITY : 0"
        :filters-count="applied.length"
        :filters-label="panel('filters.title')"
        :search-placeholder="t('panel.search')"
        :empty-text="emptyText"
        :aria-label="title"
        @row-click="open"
        @state-change="onState"
      >
        <template v-if="selected.length > 0" #actions>
          <div class="wx-orders__mass">
            <wx-text size="sm" weight="medium">
              {{ t('panel.selected', { count: selected.length }) }}
            </wx-text>
            <wx-button size="sm" type="primary" @click="moveSelected()">
              {{ t('panel.status-change') }}
            </wx-button>
            <wx-button size="sm" variant="outline" type="danger" @click="cancelSelected">
              {{ t('panel.cancel-order') }}
            </wx-button>
          </div>
        </template>

        <template #filters>
          <wx-form-item :label="t('panel.sort')">
            <wx-select v-model="sort" :options="sorts" size="sm" />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-status')">
            <wx-select
              v-model="status"
              :options="statusOptions"
              :placeholder="t('panel.any')"
              :empty-text="panel('filters.nothing-found')"
              filterable
              clearable
              size="sm"
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-public')">
            <wx-select
              v-model="publicStatus"
              :options="publicOptions"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
            />
          </wx-form-item>
          <wx-form-item
            v-for="filter in flagFilters"
            :key="filter.key"
            :label="t(`panel.filter-${filter.key}`)"
          >
            <wx-select
              :model-value="filter.value"
              :options="yesNo"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
              @update:model-value="filter.set"
            />
          </wx-form-item>
          <wx-form-item v-if="meta.visitors" :label="t('panel.filter-visitor')">
            <wx-select
              v-model="visitor"
              :options="visitorOptions"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
            />
          </wx-form-item>
          <wx-form-item v-if="meta.methods.delivery.length > 0" :label="t('panel.filter-delivery')">
            <wx-select
              v-model="delivery"
              :options="methodOptions('delivery')"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
            />
          </wx-form-item>
          <wx-form-item v-if="meta.methods.payment.length > 0" :label="t('panel.filter-payment')">
            <wx-select
              v-model="payment"
              :options="methodOptions('payment')"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-source')">
            <wx-select
              v-model="source"
              :options="sourceOptions"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-from')">
            <wx-date-picker
              :model-value="from"
              clearable
              size="sm"
              @update:model-value="(value: DatePickerModelValue) => (from = day(value))"
            />
          </wx-form-item>
          <wx-form-item :label="t('panel.filter-to')">
            <wx-date-picker
              :model-value="to"
              clearable
              size="sm"
              @update:model-value="(value: DatePickerModelValue) => (to = day(value))"
            />
          </wx-form-item>

          <wx-button v-if="applied.length > 0" variant="text" size="sm" block @click="clearFilters">
            <template #icon><wx-icon name="close" /></template>
            {{ panel('filters.reset') }}
          </wx-button>
        </template>

        <template #applied>
          <wx-filter-chips :filters="applied" />
        </template>

        <template #cell-number="{ row }">
          <div class="wx-orders__stack">
            <span class="wx-orders__text wx-orders__number">{{ numberOf(row) }}</span>
            <wx-date class="wx-orders__muted" :value="row.placed_at" compact size="sm" />
          </div>
        </template>

        <template #cell-customer="{ row }">
          <div class="wx-orders__stack">
            <span class="wx-orders__title">
              <span class="wx-orders__text">{{ row.customer.name }}</span>
              <wx-badge v-if="meta.visitors && row.guest" size="sm" variant="outline">
                {{ t('panel.guest') }}
              </wx-badge>
              <wx-badge v-if="row.source === 'quick'" size="sm" variant="outline">
                {{ t('panel.source-quick') }}
              </wx-badge>
            </span>
            <span class="wx-orders__text wx-orders__muted">
              {{ row.customer.phone ?? row.customer.email ?? '' }}
            </span>
          </div>
        </template>

        <template #cell-items="{ row }">
          <span class="wx-orders__text">{{ itemsOf(row) }}</span>
        </template>

        <template #cell-total="{ row }">
          <div class="wx-orders__stack wx-orders__money">
            <span class="wx-orders__text">{{ row.total }}</span>
            <span v-if="row.payable !== row.total" class="wx-orders__text wx-orders__muted">
              {{ t('panel.payable-short', { amount: row.payable }) }}
            </span>
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="wx-orders__stack">
            <span class="wx-orders__title">
              <wx-badge :type="toneOf(row.status?.color)" dot size="sm">
                {{ row.status?.name ?? '—' }}
              </wx-badge>
            </span>
            <span class="wx-orders__text wx-orders__muted">{{ row.status?.public?.name }}</span>
          </div>
        </template>

        <template #cell-marks="{ row }">
          <div class="wx-orders__marks">
            <wx-tooltip
              v-if="row.awaiting_payment"
              :content="t('module.milestones.awaiting_payment')"
            >
              <wx-icon class="is-warning" name="clock" size="sm" />
            </wx-tooltip>
            <wx-tooltip v-if="row.payment_failed" :content="t('panel.payment-failed')">
              <wx-icon class="is-danger" name="warning" size="sm" />
            </wx-tooltip>
            <wx-tooltip v-if="row.paid_at" :content="t('module.milestones.paid')">
              <wx-icon class="is-success" name="check-circle" size="sm" />
            </wx-tooltip>
            <wx-tooltip v-if="row.received_at" :content="t('module.milestones.received')">
              <wx-icon class="is-success" name="map-pin" size="sm" />
            </wx-tooltip>
            <wx-tooltip v-if="row.cancelled_at" :content="t('module.milestones.cancelled')">
              <wx-icon class="is-danger" name="close" size="sm" />
            </wx-tooltip>
            <wx-tooltip v-if="row.refunded" :content="t('panel.has-refund')">
              <wx-icon class="is-warning" name="arrow-left" size="sm" />
            </wx-tooltip>
          </div>
        </template>

        <template #cell-blocks="{ row }">
          <div class="wx-orders__stack">
            <span class="wx-orders__text">{{ row.delivery ?? '—' }}</span>
            <span v-if="row.payment" class="wx-orders__text wx-orders__muted">{{
              row.payment
            }}</span>
          </div>
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="numberOf(row)" />
        </template>

        <template #cell-card="{ row }">
          <!-- The buyer by a slot, not `title`: no order has a picture, and an initial in front of
               every row is a column of letters that says nothing. -->
          <wx-entity-card variant="plain" :subtitle="itemsOf(row)">
            <template #title>{{ row.customer.name }}</template>
            <template #meta>
              <wx-badge :type="toneOf(row.status?.color)" dot size="sm">
                {{ row.status?.name ?? '—' }}
              </wx-badge>
              <wx-badge v-if="row.source === 'quick'" size="sm" variant="outline">
                {{ t('panel.source-quick') }}
              </wx-badge>
              <wx-text size="sm" weight="medium">{{ row.total }}</wx-text>
              <wx-text size="sm" tone="muted">{{ numberOf(row) }}</wx-text>
              <wx-date :value="row.placed_at" compact size="sm" />
            </template>
          </wx-entity-card>
        </template>

        <template #card-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="numberOf(row)" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-orders {
  min-width: 0;
}

.wx-orders__stack {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-orders__title {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-orders__text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-orders__number {
  font-weight: var(--wx-font-weight-medium);
  font-variant-numeric: tabular-nums;
}

.wx-orders__muted {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-orders__money {
  align-items: flex-end;
  font-variant-numeric: tabular-nums;
}

.wx-orders__marks {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
}

.wx-orders__marks .is-success {
  color: var(--wx-color-success);
}

.wx-orders__marks .is-warning {
  color: var(--wx-color-warning);
}

.wx-orders__marks .is-danger {
  color: var(--wx-color-danger);
}

.wx-orders__mass {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}
</style>

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
import ContactLink from './ContactLink.vue'
import OrderGlanceRow from './OrderGlanceRow.vue'
import OrdersStats from './OrdersStats.vue'
import { NAMESPACE, useOrdersMessages } from './i18n'
import StatusDialog from './StatusDialog.vue'
import { toneOf } from './tones'
import type {
  MassCancelAnswer,
  MassStatusAnswer,
  OrderBalanceFilter,
  OrderGlance,
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
 *
 * Opened without a tab, the list is on «New» while it holds any order and on «All» otherwise
 * (decision 92) — the server decides by the counts it sends anyway, so it is one request and no
 * tab is shown before the answer says which. Over the list stand the figures (decision 93); a row
 * opens to what the order holds and who it is for (decision 94).
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

/* «New order» for whoever works orders; the statuses and the settings of the section, for whoever
 * may change them (§11.4); the settings also for whoever edits only their tab «Checkout» (§31.1). */
const headActions = computed<ScreenAction[]>(() => [
  // An order taken by phone (§29.2) — working orders, so `orders.manage`.
  ...(canManage.value
    ? [
        {
          key: 'new',
          label: t('panel.new-order'),
          primary: true,
          run: () => void router.push(`${props.base}/new`),
        },
      ]
    : []),
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
const addressed = VIEWS.includes(text('view') as OrdersView) ? (text('view') as OrdersView) : null
/** The open tab; null until the server's first answer says which one, for a list opened without. */
const view = ref<OrdersView | null>(addressed)
/** A tab in the address or chosen since is kept there; the one the server chose is not. */
const explicit = ref(addressed !== null)

/*
 * The tabs are told nothing until the tab is known: given no value, the strip would open its first
 * tab by itself and show «New» for a moment on a list that turns out to be «All». A write before
 * then is that strip's, not the reader's, and is not taken.
 */
const tab = computed<OrdersView | ''>({
  get: () => view.value ?? '',
  set: (value) => {
    if (view.value !== null && value !== '') view.value = value
  },
})
const search = ref(String(route.query.q ?? ''))
const pageNumber = ref(Number(route.query.page ?? 1) || 1)
const status = ref<string | null>(text('status'))
const publicStatus = ref<string | null>(text('public'))
const paid = ref<boolean | null>(flag('paid'))
const received = ref<boolean | null>(flag('received'))
const cancelled = ref<boolean | null>(flag('cancelled'))
const refunded = ref<boolean | null>(flag('refunded'))
/** «Payment differs» (§42.4): waiting for a top-up, or paid over. */
const balance = ref<OrderBalanceFilter | null>(
  ((value) => (value === 'due' || value === 'over' ? value : null))(text('balance')),
)
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

const balanceOptions = computed(() => [
  { value: 'due', label: t('panel.balance-filter-due') },
  { value: 'over', label: t('panel.balance-filter-over') },
])

/** The badge under the total: «Top-up 15.00», «Give back 20.00». */
const balanceBadge = (row: OrderRow): string | null =>
  row.balance
    ? t(row.balance.kind === 'over' ? 'panel.badge-over' : 'panel.badge-topup', {
        amount: row.balance.amount,
      })
    : null

const visitorOptions = computed(() => [
  { value: 'guest', label: t('panel.guest') },
  { value: 'visitor', label: t('panel.visitor') },
])

/** Where an order may come from; a source of the site's own code is still found by the address. */
const SOURCES = ['checkout', 'quick', 'panel', 'api', 'mcp'] as const

/** The contact a row shows under the buyer: the phone, or the e-mail where there is none. */
function contactOf(row: OrderRow): { kind: 'phone' | 'email'; value: string } | null {
  if (row.customer.phone) return { kind: 'phone', value: row.customer.phone }
  if (row.customer.email) return { kind: 'email', value: row.customer.email }

  return null
}

/* An order that did not come through the checkout says so beside the buyer: a one-click order and
 * one a manager took by phone (§29.1, §29.2) both wait for delivery and payment to be filled in. */
const sourceMark = (value: unknown): string | null =>
  value === 'quick'
    ? t('panel.source-quick')
    : value === 'panel'
      ? t('panel.source-panel-mark')
      : null

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

  if (balance.value !== null) {
    chip(
      'balance',
      `${t('panel.filter-balance')}: ${labelOf(balanceOptions.value, balance.value)}`,
      () => (balance.value = null),
    )
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
  balance.value = null
  visitor.value = null
  delivery.value = null
  payment.value = null
  source.value = null
  from.value = null
  to.value = null
}

function query(state?: TableState): OrderQuery {
  return {
    view: view.value === null ? 'auto' : view.value === 'all' ? undefined : view.value,
    search: state?.search ?? search.value,
    status: status.value ?? undefined,
    public: publicStatus.value ?? undefined,
    paid: paid.value ?? undefined,
    received: received.value ?? undefined,
    cancelled: cancelled.value ?? undefined,
    refunded: refunded.value ?? undefined,
    balance: balance.value ?? undefined,
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

    if (mine === asked) {
      page.value = answer
      // The tab the server chose for a list opened without one.
      if (view.value === null) view.value = answer.view ?? 'all'
    }
  } catch (error) {
    toast.danger(message(error))
  } finally {
    if (mine === asked) loading.value = false
  }
}

function remember(): void {
  const wanted: LocationQueryRaw = {}
  const q = query()

  if (explicit.value && view.value !== null) wanted.view = view.value
  if (q.search) wanted.q = q.search
  if (q.page && q.page > 1) wanted.page = String(q.page)
  if (q.sort && q.sort !== '-placed_at') wanted.sort = q.sort

  for (const key of [
    'status',
    'public',
    'balance',
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

/* The tab the server chose comes with its rows already; a tab chosen since is a new question. */
watch(view, (_, before) => {
  if (before === null) return

  explicit.value = true
  pageNumber.value = 1
  selected.value = []
  remember()
  void load()
})

watch(
  [
    status,
    publicStatus,
    paid,
    received,
    cancelled,
    refunded,
    balance,
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
    { key: 'total', label: t('panel.column-total'), width: 150, align: 'right' },
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

/*
 * An opened row asks the server once and keeps the answer for the page: the list itself stays as
 * light as it was, and a manager who opens three rows of twenty-five pays for three. After a mass
 * change the answers are stale, so they go.
 */
const expanded = ref<(string | number)[]>([])
const glances = ref<Record<number, OrderGlance | 'failed'>>({})

async function glanceAt(id: number): Promise<void> {
  if (glances.value[id] !== undefined) return

  try {
    const answer = await api.glance(id)
    glances.value = { ...glances.value, [id]: answer }
  } catch {
    glances.value = { ...glances.value, [id]: 'failed' }
  }
}

function onExpand(keys: (string | number)[]): void {
  for (const key of keys) void glanceAt(Number(key))
}

function glanceOf(row: OrderRow): OrderGlance | null {
  const answer = glances.value[row.id]

  return answer === undefined || answer === 'failed' ? null : answer
}

function forget(): void {
  glances.value = {}
  expanded.value = []
}

const ids = computed(() => selected.value.map((key) => Number(key)))

async function moveSelected(chosen = ids.value): Promise<void> {
  const answer = await statusDialog({ statuses: meta.value.statuses, ids: chosen })

  if (!answer) return

  toast.success(t('panel.mass-moved', { count: answer.moved }))
  if (answer.refused.length > 0)
    toast.warning(t('panel.mass-refused', { count: answer.refused.length }))
  selected.value = []
  forget()
  void load()
}

async function cancelSelected(): Promise<void> {
  const answer = await cancelDialog({ ids: ids.value })

  if (!answer) return

  toast.success(t('panel.mass-cancelled', { count: answer.cancelled }))
  if (answer.refused.length > 0)
    toast.warning(t('panel.mass-refused', { count: answer.refused.length }))
  selected.value = []
  forget()
  void load()
}

const emptyText = computed(() =>
  applied.value.length > 0 || search.value || (view.value !== null && view.value !== 'all')
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
    <wx-list-screen v-model:view="tab" :title="title" :views="views" :actions="headActions">
      <template #before>
        <orders-stats />
      </template>

      <wx-table
        v-model:search="search"
        v-model:page="pageNumber"
        v-model:selected="selected"
        v-model:expanded="expanded"
        :expandable="!asCards"
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
        @expand-change="onExpand"
      >
        <template #expanded="{ row }">
          <order-glance-row :glance="glanceOf(row)" :failed="glances[row.id] === 'failed'" />
        </template>

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
          <wx-form-item :label="t('panel.filter-balance')">
            <wx-select
              v-model="balance"
              :options="balanceOptions"
              :placeholder="t('panel.any')"
              clearable
              size="sm"
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
              <wx-badge v-if="sourceMark(row.source)" size="sm" variant="outline">
                {{ sourceMark(row.source) }}
              </wx-badge>
            </span>
            <span v-if="contactOf(row)" class="wx-orders__text wx-orders__muted">
              <contact-link v-bind="contactOf(row)!" />
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
            <wx-badge
              v-if="balanceBadge(row)"
              :type="row.balance?.kind === 'over' ? 'danger' : 'warning'"
              size="sm"
              data-test="row-balance"
            >
              {{ balanceBadge(row) }}
            </wx-badge>
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
              <wx-badge v-if="sourceMark(row.source)" size="sm" variant="outline">
                {{ sourceMark(row.source) }}
              </wx-badge>
              <wx-text size="sm" weight="medium">{{ row.total }}</wx-text>
              <wx-badge
                v-if="balanceBadge(row)"
                :type="row.balance?.kind === 'over' ? 'danger' : 'warning'"
                size="sm"
              >
                {{ balanceBadge(row) }}
              </wx-badge>
              <wx-text size="sm" tone="muted">{{ numberOf(row) }}</wx-text>
              <wx-date :value="row.placed_at" compact size="sm" />
              <!-- A phone in the hand calls the buyer from here; the rest of the card opens the order. -->
              <wx-text v-if="contactOf(row)" size="sm" class="wx-orders__text">
                <contact-link v-bind="contactOf(row)!" />
              </wx-text>
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

/* The balance badge keeps its money whole: in a narrow column it wraps rather than cut the sum. */
.wx-orders__money :deep(.wx-badge),
.wx-orders__money :deep(.wx-badge__label) {
  white-space: normal;
  text-align: right;
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

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  toast,
  WxButton,
  WxCheckbox,
  WxFormItem,
  WxDatePicker,
  WxInput,
  WxSelect,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxListScreen, type ScreenAction } from '@webx-ui/module-admin'
import { createPaymentsApi } from './api'
import EventDialog from './EventDialog.vue'
import EventList from './EventList.vue'
import { usePaymentsLines, usePaymentsWords } from './i18n'
import type { EventLine, ExchangeFilters, ExchangePage, Option } from './types'

/**
 * The exchange journal (spec §13.2 `payments.exchange`, §20.7): every event newest first, older
 * ones a page at a time by the id of the last one (keyset — the journal only grows), filtered by
 * gateway, method, attempt or order, kind, direction, «signature errors», «errors» and period.
 * Nothing here changes or removes an event.
 *
 * The filters are in the address, so the tab of an order links to «all events of the order».
 */
const admin = useAdmin()
const api = createPaymentsApi(admin)
const route = useRoute()
const router = useRouter()
const t = usePaymentsWords()
const w = usePaymentsLines()
const message = useErrorText()

const KEYS = [
  'gateway',
  'method',
  'payment',
  'order',
  'order_id',
  'kind',
  'direction',
  'from',
  'to',
] as const
const FLAGS = ['signature', 'errors'] as const

type Key = (typeof KEYS)[number]
type Flag = (typeof FLAGS)[number]

const text = (key: string): string => {
  const raw = route.query[key]

  return typeof raw === 'string' ? raw : ''
}

const filters = reactive(
  Object.fromEntries(KEYS.map((key) => [key, text(key)])) as Record<Key, string>,
)
const flags = reactive(
  Object.fromEntries(FLAGS.map((key) => [key, route.query[key] === '1'])) as Record<Flag, boolean>,
)
const events = ref<EventLine[]>([])
const next = ref<number | null>(null)
const options = ref<ExchangePage['filters'] | null>(null)
const loading = ref(false)
const openId = ref<number | null>(null)
const opened = ref(false)

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'payments-exchange')?.title ??
    t('sections.exchange'),
)

const asked = computed<ExchangeFilters>(() => {
  const out: Record<string, unknown> = {}

  for (const key of KEYS) if (filters[key]) out[key] = filters[key]
  for (const key of FLAGS) if (flags[key]) out[key] = true

  return out as ExchangeFilters
})

const count = computed(() => Object.keys(asked.value).length)

async function load(more = false): Promise<void> {
  loading.value = true

  try {
    const page = await api.exchange({
      ...asked.value,
      before: more && next.value !== null ? next.value : undefined,
    })

    events.value = more ? [...events.value, ...page.data] : page.data
    next.value = page.next
    options.value = page.filters
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

let timer: ReturnType<typeof setTimeout> | undefined

watch(
  () => [{ ...filters }, { ...flags }],
  () => {
    clearTimeout(timer)
    // The typed ones (an attempt id, an order number) wait for the hand to stop.
    timer = setTimeout(() => {
      const query: Record<string, string> = {}

      for (const [key, value] of Object.entries(asked.value))
        query[key] = value === true ? '1' : String(value)
      void router.replace({ query })
      void load()
    }, 300)
  },
)

void load()

const choices = (list: Option[] | undefined, any: string): Option[] => [
  { value: '', label: any },
  ...(list ?? []),
]

const kinds = computed(() =>
  choices(
    options.value?.kinds.map((value) => ({ value, label: w(`exchange.kinds.${value}`) })),
    t('exchange.any-kind'),
  ),
)

const directions = computed(() =>
  choices(
    options.value?.directions.map((value) => ({
      value,
      label: w(`exchange.directions.${value}`),
    })),
    t('exchange.any-direction'),
  ),
)

function reset(): void {
  for (const key of KEYS) filters[key] = ''
  for (const key of FLAGS) flags[key] = false
}

function open(line: EventLine): void {
  openId.value = line.id
  opened.value = true
}

const actions = computed<ScreenAction[]>(() => [
  { key: 'refresh', label: t('common.refresh'), icon: 'refresh', run: () => void load() },
])
</script>

<template>
  <wx-list-screen :title="title" :actions="actions">
    <template #before>
      <wx-text class="wx-payments-intro" tone="muted" size="sm">{{ t('exchange.intro') }}</wx-text>
    </template>

    <div class="wx-payments-exchange">
      <div class="wx-payments-exchange__filters" data-test="exchange-filters">
        <wx-form-item :label="t('exchange.gateway')">
          <wx-select
            v-model="filters.gateway"
            size="sm"
            :options="choices(options?.gateways, t('exchange.any-gateway'))"
          />
        </wx-form-item>
        <wx-form-item :label="t('exchange.method')">
          <wx-select
            v-model="filters.method"
            size="sm"
            :options="choices(options?.methods, t('exchange.any-method'))"
          />
        </wx-form-item>
        <wx-form-item :label="t('exchange.kind')">
          <wx-select v-model="filters.kind" size="sm" :options="kinds" />
        </wx-form-item>
        <wx-form-item :label="t('exchange.direction')">
          <wx-select v-model="filters.direction" size="sm" :options="directions" />
        </wx-form-item>
        <wx-form-item :label="t('exchange.order')">
          <wx-input v-model="filters.order" size="sm" clearable />
        </wx-form-item>
        <wx-form-item :label="t('exchange.payment')">
          <wx-input v-model="filters.payment" size="sm" clearable />
        </wx-form-item>
        <wx-form-item :label="t('common.from')">
          <wx-date-picker v-model="filters.from" size="sm" clearable />
        </wx-form-item>
        <wx-form-item :label="t('common.to')">
          <wx-date-picker v-model="filters.to" size="sm" clearable />
        </wx-form-item>
        <div class="wx-payments-exchange__flags">
          <wx-checkbox v-model="flags.signature" data-test="filter-signature">
            {{ t('exchange.signature-errors') }}
          </wx-checkbox>
          <wx-checkbox v-model="flags.errors" data-test="filter-errors">
            {{ t('exchange.errors') }}
          </wx-checkbox>
          <wx-button v-if="count > 0" size="sm" variant="text" @click="reset">
            {{ t('common.cancel') }}
          </wx-button>
        </div>
      </div>

      <event-list :events="events" :loading="loading" context @open="open" />

      <div v-if="next !== null" class="wx-payments-exchange__more">
        <wx-button size="sm" variant="outline" :loading="loading" @click="load(true)">
          {{ t('common.more') }}
        </wx-button>
      </div>
    </div>

    <event-dialog :id="openId" v-model:open="opened" />
  </wx-list-screen>
</template>

<style scoped>
.wx-payments-intro {
  display: block;
  max-width: 760px;
}

.wx-payments-exchange {
  display: flex;
  flex-direction: column;
  min-width: 0;
  container-type: inline-size;
}

/*
 * As many filters to a row as the screen has room for; one under the other on a phone. The card
 * already pads its content, so the filters add none above and keep the form's gap below — the
 * table does not start right under the checkboxes.
 */
.wx-payments-exchange__filters {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: var(--wx-space-12);
  padding: 0 0 var(--wx-space-16);
  margin-bottom: var(--wx-space-16);
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-payments-exchange__flags {
  display: flex;
  flex-wrap: wrap;
  grid-column: 1 / -1;
  align-items: center;
  gap: var(--wx-space-8) var(--wx-space-16);
}

.wx-payments-exchange__more {
  display: flex;
  justify-content: center;
  padding: var(--wx-space-12);
}
</style>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  WxBadge,
  WxFormItem,
  WxDatePicker,
  WxSegmented,
  WxSelect,
  WxTable,
  WxText,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxDate, WxListScreen } from '@webx-ui/module-admin'
import { createPaymentsApi } from './api'
import AttemptDialog from './AttemptDialog.vue'
import { statusTones } from './format'
import { usePaymentsLines, usePaymentsWords } from './i18n'
import type { AttemptRow, AttemptsPage } from './types'

/**
 * The journal of attempts (spec §13.2 `payments.index`): every attempt to pay an order, newest
 * first, a page at a time — «All», «In progress», «Failed», «Problems» — by method and period,
 * searched by the order's number, the attempt's id or the gateway's reference. A row opens the
 * attempt with its events of the exchange.
 */
const admin = useAdmin()
const api = createPaymentsApi(admin)
const t = usePaymentsWords()
const w = usePaymentsLines()
const message = useErrorText()

const page = ref<AttemptsPage | null>(null)
const loading = ref(false)
const tab = ref('all')
const method = ref('')
const from = ref('')
const to = ref('')
const openId = ref<number | null>(null)
const opened = ref(false)

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'payments-attempts')?.title ??
    t('sections.attempts'),
)

const tabs = computed(() => [
  { value: 'all', label: t('attempts.tabs.all') },
  { value: 'pending', label: t('attempts.tabs.pending') },
  { value: 'failed', label: t('attempts.tabs.failed') },
  {
    value: 'problems',
    label: page.value?.filters.problems
      ? `${t('attempts.tabs.problems')} · ${page.value.filters.problems}`
      : t('attempts.tabs.problems'),
  },
])

const columns = computed<TableColumn<AttemptRow>[]>(() => [
  { key: 'created_at', label: t('attempts.date'), width: 120 },
  { key: 'order', label: t('attempts.order'), width: 120 },
  { key: 'method_title', label: t('attempts.method'), minWidth: 160, hideBelow: 560 },
  { key: 'amount', label: t('attempts.amount'), width: 130, align: 'right' },
  // The problem stands beside the status: an empty «Problem» on every card would say nothing.
  { key: 'status', label: t('attempts.status'), width: 220 },
])

let last: TableState = { page: 1, perPage: 25, sort: null, search: '' }

async function load(next: TableState = last): Promise<void> {
  last = next
  loading.value = true

  try {
    page.value = await api.attempts({
      page: next.page,
      per_page: next.perPage,
      search: next.search,
      tab: tab.value === 'all' ? undefined : tab.value,
      method: method.value,
      from: from.value,
      to: to.value,
    })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch([tab, method, from, to], () => void load({ ...last, page: 1 }))

function open(row: AttemptRow): void {
  openId.value = row.id
  opened.value = true
}
</script>

<template>
  <wx-list-screen :title="title">
    <template #before>
      <wx-text class="wx-payments-intro" tone="muted" size="sm">{{ t('attempts.intro') }}</wx-text>
    </template>

    <wx-table
      :data="page"
      :columns="columns"
      row-key="id"
      searchable
      clickable
      hover
      flush
      :loading="loading"
      :search-placeholder="t('attempts.search-placeholder')"
      :empty-text="t('attempts.empty')"
      :filters-count="(method ? 1 : 0) + (from ? 1 : 0) + (to ? 1 : 0)"
      @row-click="open"
      @state-change="load"
    >
      <!-- The tabs of the list on the line of the search, not a strip of their own above it. -->
      <template #title>
        <div class="wx-payments-attempts__tabs">
          <wx-segmented v-model="tab" size="sm" :options="tabs" data-test="attempt-tabs" />
        </div>
      </template>

      <template #filters>
        <wx-form-item :label="t('attempts.method')">
          <wx-select
            v-model="method"
            size="sm"
            :options="[
              { value: '', label: t('attempts.any-method') },
              ...(page?.filters.methods ?? []),
            ]"
          />
        </wx-form-item>
        <wx-form-item :label="t('common.from')">
          <wx-date-picker v-model="from" size="sm" clearable />
        </wx-form-item>
        <wx-form-item :label="t('common.to')">
          <wx-date-picker v-model="to" size="sm" clearable />
        </wx-form-item>
      </template>

      <template #cell-created_at="{ row }">
        <wx-date :value="row.created_at" compact />
      </template>
      <template #cell-order="{ row }">{{ row.order_number ?? row.order_id }}</template>
      <template #cell-amount="{ row }">
        <span class="wx-payments-attempts__money">{{ row.amount_formatted }}</span>
      </template>
      <template #cell-status="{ row }">
        <span class="wx-payments-attempts__status">
          <wx-badge :type="statusTones[row.status as AttemptRow['status']]" size="sm">
            {{ w(`statuses.statuses.${row.status}`) }}
          </wx-badge>
          <wx-badge v-if="row.problem" :type="row.resolved ? 'default' : 'danger'" size="sm">
            {{ w(`statuses.problems.${row.problem}`) }}
          </wx-badge>
        </span>
      </template>
    </wx-table>

    <attempt-dialog :id="openId" v-model:open="opened" @changed="load()" />
  </wx-list-screen>
</template>

<style scoped>
.wx-payments-intro {
  display: block;
  max-width: 760px;
}

.wx-payments-attempts__tabs {
  max-width: 100%;
  overflow-x: auto;
}

.wx-payments-attempts__status {
  display: inline-flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
}

.wx-payments-attempts__money {
  font-variant-numeric: tabular-nums;
}
</style>

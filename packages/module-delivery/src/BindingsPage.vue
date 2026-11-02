<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxFormItem,
  WxSelect,
  WxSpace,
  WxTable,
  type RowKey,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxListScreen } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import LocalitySearch from './LocalitySearch.vue'
import type { BindingRow, BindingsPage, LocalityBrief } from './types'

/**
 * The binding queue (§4.4, §14.2): the records of the carriers no rule placed — as the carrier
 * sent them, why they wait, and the localities that came close, with their distance. A decision is
 * made for the rows ticked, or for every row of the filter at once: «Bind» to a locality found by
 * search, «Make a shared locality», «Do not bind».
 */
const context = useAdmin()
const api = createDeliveryApi(context)
const t = useDeliveryWords()
const message = useErrorText()

const page = ref<BindingsPage | null>(null)
const loading = ref(false)
// The carriers screen links here narrowed to one carrier.
const carrier = ref(
  typeof useRoute().query.carrier === 'string' ? String(useRoute().query.carrier) : '',
)
const reason = ref('')
const selected = ref<RowKey[]>([])
const working = ref(false)
const binding = ref(false)
const target = ref<LocalityBrief | null>(null)
const wholeFilter = ref(false)

const canManage = computed(() => context.can('delivery.manage'))
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'delivery-bindings')?.title ??
    t('sections.bindings'),
)

const columns = computed<TableColumn<BindingRow>[]>(() => [
  { key: 'record', label: t('binding.record'), minWidth: 200 },
  { key: 'carrier', label: t('binding.carrier'), width: 140, hideBelow: 640 },
  { key: 'reason', label: t('binding.reason'), width: 160 },
  { key: 'candidates', label: t('binding.candidates'), minWidth: 220, hideBelow: 760 },
])

const carrierOptions = computed(() => [
  { value: '', label: t('binding.any-carrier') },
  ...(page.value?.filters.carriers ?? []).map((one) => ({
    value: one.value,
    label: `${one.label} (${one.count})`,
  })),
])

const reasonOptions = computed(() => [
  { value: '', label: t('binding.any-reason') },
  ...(page.value?.filters.reasons ?? []).map((one) => ({
    value: one.value,
    label: `${t(`binding.reasons.${one.value}`)} (${one.count})`,
  })),
])

let last: TableState = { page: 1, perPage: 25, sort: null, search: '' }

async function load(next: TableState = last): Promise<void> {
  last = next
  loading.value = true

  try {
    page.value = await api.bindings({
      page: next.page,
      per_page: next.perPage,
      carrier: carrier.value,
      reason: reason.value,
    })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch([carrier, reason], () => {
  selected.value = []
  void load({ ...last, page: 1 })
})

const count = computed(() => (wholeFilter.value ? (page.value?.total ?? 0) : selected.value.length))

async function apply(
  action: 'bind' | 'create' | 'ignore',
  locality?: LocalityBrief,
): Promise<void> {
  if (count.value === 0) {
    toast.info(t('errors.nothing_chosen'))

    return
  }

  working.value = true

  try {
    const result = await api.batch({
      action,
      ...(wholeFilter.value
        ? { all: true, carrier: carrier.value, reason: reason.value }
        : { ids: selected.value.map(Number) }),
      ...(locality ? { locality_id: locality.id } : {}),
    })

    toast.success(t('binding.done', { count: result.done }))
    if (result.failed.length > 0) toast.danger(t('binding.failed', { count: result.failed.length }))
    if (result.left > 0) toast.info(t('binding.left', { count: result.left }))

    selected.value = []
    binding.value = false
    target.value = null
    await load()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

function distance(km: number | null): string {
  return km === null ? '' : t('binding.km', { distance: km.toFixed(1) })
}
</script>

<template>
  <wx-list-screen :title="title">
    <div v-if="canManage" class="wx-delivery-bindings__bar">
      <span class="wx-delivery-bindings__count">
        {{ t('binding.selected', { count: selected.length }) }}
      </span>
      <wx-checkbox v-model="wholeFilter" data-test="whole-filter">
        {{ t('binding.whole-filter', { count: page?.total ?? 0 }) }}
      </wx-checkbox>
      <wx-space size="sm" wrap>
        <wx-button size="sm" :disabled="count === 0 || working" @click="binding = true">
          {{ t('binding.bind') }}
        </wx-button>
        <wx-button
          size="sm"
          :disabled="count === 0"
          :loading="working"
          data-test="batch-create"
          @click="apply('create')"
        >
          {{ t('binding.create') }}
        </wx-button>
        <wx-button
          size="sm"
          variant="outline"
          :disabled="count === 0"
          :loading="working"
          data-test="batch-ignore"
          @click="apply('ignore')"
        >
          {{ t('binding.ignore') }}
        </wx-button>
      </wx-space>
    </div>

    <wx-table
      v-model:selected="selected"
      :data="page"
      :columns="columns"
      row-key="id"
      :selectable="canManage && !wholeFilter"
      flush
      :loading="loading"
      :empty-text="t('binding.empty')"
      :filters-count="(carrier ? 1 : 0) + (reason ? 1 : 0)"
      @state-change="load"
    >
      <template #filters>
        <wx-form-item :label="t('binding.carrier')">
          <wx-select v-model="carrier" :options="carrierOptions" size="sm" />
        </wx-form-item>
        <wx-form-item :label="t('binding.reason')">
          <wx-select v-model="reason" :options="reasonOptions" size="sm" />
        </wx-form-item>
      </template>

      <template #cell-record="{ row }">
        <span class="wx-delivery-bindings__name">{{ row.record.name }}</span>
        <span class="wx-delivery-bindings__muted">
          {{
            [row.record.district, row.record.region, row.record.kind, row.external_id]
              .filter(Boolean)
              .join(' · ')
          }}
        </span>
      </template>
      <template #cell-carrier="{ row }">{{ row.carrier_title }}</template>
      <template #cell-reason="{ row }">
        <wx-badge v-if="row.reason" type="warning" size="sm">{{
          t(`binding.reasons.${row.reason}`)
        }}</wx-badge>
      </template>
      <template #cell-candidates="{ row }">
        <span v-for="one in row.candidates" :key="one.id" class="wx-delivery-bindings__candidate">
          {{ one.label }}
          <span class="wx-delivery-bindings__muted">{{ distance(one.distance_km) }}</span>
        </span>
      </template>
    </wx-table>

    <wx-dialog v-model:open="binding" :title="t('binding.bind-to')" :width="520">
      <wx-alert type="info" :description="t('binding.selected', { count })" />
      <locality-search class="wx-delivery-bindings__search" @pick="target = $event" />
      <p v-if="target" class="wx-delivery-bindings__target">{{ target.label }}</p>

      <template #footer>
        <wx-space size="sm">
          <wx-button variant="outline" @click="binding = false">{{ t('common.cancel') }}</wx-button>
          <wx-button
            type="primary"
            :disabled="!target"
            :loading="working"
            @click="target && apply('bind', target)"
          >
            {{ t('binding.bind') }}
          </wx-button>
        </wx-space>
      </template>
    </wx-dialog>
  </wx-list-screen>
</template>

<style scoped>
.wx-delivery-bindings__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8) var(--wx-space-16);
  margin-bottom: var(--wx-space-12);
}

.wx-delivery-bindings__count {
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-bindings__name {
  display: block;
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-bindings__muted {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-bindings__candidate {
  display: block;
}

.wx-delivery-bindings__search {
  margin-top: var(--wx-space-12);
}

.wx-delivery-bindings__target {
  margin: var(--wx-space-8) 0 0;
  font-weight: var(--wx-font-weight-medium);
}
</style>

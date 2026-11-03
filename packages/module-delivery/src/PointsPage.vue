<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  confirm,
  toast,
  WxBadge,
  WxFormItem,
  WxIcon,
  WxSelect,
  WxTable,
  WxText,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  WxListScreen,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import PointDialog from './PointDialog.vue'
import type { Option, PointRow, PointsPage } from './types'

/**
 * The shop's own points (§14.2): stores and pick-up points a page at a time, by kind and state,
 * searched by name and address, sorted by any column but the address. A row opens its form, its
 * `···` offers the same and the deletion — the table every list of the panel is (Blog → Tags).
 */
const context = useAdmin()
const api = createDeliveryApi(context)
const t = useDeliveryWords()
const message = useErrorText()

const page = ref<PointsPage | null>(null)
const kinds = ref<Option[]>([])
const loading = ref(false)
const kind = ref('')
const state = ref('')
const editing = ref<number | null>(null)
const opened = ref(false)

const canManage = computed(() => context.can('delivery.manage'))
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'delivery-points')?.title ??
    t('sections.points'),
)

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new', label: t('point.new'), primary: true, run: () => edit(null) }]
    : [],
)

const columns = computed<TableColumn<PointRow>[]>(() => [
  { key: 'title', label: t('point.titles'), minWidth: 180, sortable: true },
  { key: 'kind', label: t('point.kind'), width: 150, hideBelow: 560, sortable: true },
  { key: 'locality', label: t('point.locality'), minWidth: 160, sortable: true },
  { key: 'address', label: t('point.address'), minWidth: 180, hideBelow: 760 },
  { key: 'today', label: t('point.today'), width: 140, hideBelow: 900 },
  { key: 'active', label: t('point.active'), width: 110, sortable: true },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const stateOptions = computed(() => [
  { value: '', label: t('common.any-state') },
  { value: 'active', label: t('common.active') },
  { value: 'inactive', label: t('common.inactive') },
])

let last: TableState = { page: 1, perPage: 25, sort: null, search: '' }

async function load(next: TableState = last): Promise<void> {
  last = next
  loading.value = true

  try {
    page.value = await api.points({
      page: next.page,
      per_page: next.perPage,
      q: next.search,
      kind: kind.value,
      state: state.value,
      sort: next.sort ? `${next.sort.order === 'desc' ? '-' : ''}${next.sort.key}` : undefined,
    })
    kinds.value = page.value.filters.kinds
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch([kind, state], () => void load({ ...last, page: 1 }))

function edit(id: number | null): void {
  editing.value = id
  opened.value = true
}

async function remove(row: PointRow): Promise<void> {
  const agreed = await confirm({
    title: t('point.delete-title', { name: row.title || t('point.title') }),
    message: t('point.delete-text'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removePoint(row.id)
    toast.success(t('point.deleted'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(row: PointRow): RowAction[] {
  if (!canManage.value) return []

  return [
    { key: 'edit', icon: 'edit', label: t('common.edit'), run: () => edit(row.id) },
    {
      key: 'delete',
      icon: 'trash',
      label: t('common.delete'),
      danger: true,
      run: () => void remove(row),
    },
  ]
}
</script>

<template>
  <wx-list-screen :title="title" :actions="actions">
    <!-- What the screen is, in full: a heading's subtitle is one line and cuts this short. -->
    <template #before>
      <wx-text class="wx-delivery-intro" tone="muted" size="sm">{{ t('point.intro') }}</wx-text>
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
      :search-placeholder="t('common.search')"
      :empty-text="t('point.empty')"
      :filters-count="(kind ? 1 : 0) + (state ? 1 : 0)"
      @row-click="(row: PointRow) => edit(row.id)"
      @state-change="load"
    >
      <template #filters>
        <wx-form-item :label="t('point.kind')">
          <wx-select
            v-model="kind"
            :options="[{ value: '', label: t('point.any-kind') }, ...kinds]"
            size="sm"
          />
        </wx-form-item>
        <wx-form-item :label="t('point.active')">
          <wx-select v-model="state" :options="stateOptions" size="sm" />
        </wx-form-item>
      </template>

      <template #cell-title="{ row }">{{ row.title || '—' }}</template>
      <template #cell-kind="{ row }">{{ row.kind_label }}</template>
      <template #cell-locality="{ row }">
        <span v-if="row.locality">
          {{ row.locality.label }}
          <wx-icon v-if="!row.locality.active" name="warning" size="sm" />
        </span>
      </template>
      <template #cell-today="{ row }">{{ row.today ?? '' }}</template>
      <template #cell-active="{ row }">
        <wx-badge :type="row.active ? 'success' : 'default'" dot>
          {{ row.active ? t('common.active') : t('common.inactive') }}
        </wx-badge>
      </template>
      <template #cell-actions="{ row }">
        <wx-row-menu :actions="actionsFor(row)" :label="row.title" />
      </template>
    </wx-table>

    <point-dialog :id="editing" v-model:open="opened" @saved="load()" @removed="load()" />
  </wx-list-screen>
</template>

<style scoped>
/* A paragraph and not a line: read once, it has to fit without running the whole width. */
.wx-delivery-intro {
  display: block;
  max-width: 760px;
}
</style>

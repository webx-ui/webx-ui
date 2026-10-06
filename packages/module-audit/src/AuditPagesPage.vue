<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAdmin, useDates, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  WxBadge,
  WxButton,
  WxCheckboxGroup,
  WxInput,
  WxPopover,
  WxSelect,
  WxTable,
  WxText,
  type SelectValue,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import AuditLayout from './AuditLayout.vue'
import AuditPageCard from './AuditPageCard.vue'
import { createAuditApi } from './api'
import {
  COMPUTED_FIELDS,
  DEFAULT_COLUMNS,
  OPERATIONS,
  PAGE_FIELDS,
  SOURCES,
  WITH_VALUE,
} from './fields'
import AuditStatus from './AuditStatus.vue'
import { useAuditMessages } from './i18n'
import type {
  AuditFieldFilter,
  AuditFilterOp,
  AuditPage,
  AuditPageField,
  AuditPageQuery,
  AuditPageRow,
  AuditRun,
} from './types'

/**
 * Every address the last full run crawled (§8, «Pages»): any field of the snapshot as a column,
 * a filter on any of them, the list as CSV, and a row that opens the page's card.
 */
const props = defineProps<{ base: string }>()

const api = createAuditApi(useAdmin())
useAuditMessages()

const t = useTranslate('webx-audit')
const panel = useTranslate('webx-admin')
const message = useErrorText()
const dates = useDates()

const STORAGE = 'webx-audit.pages.columns'

const run = ref<AuditRun | null>(null)
const ready = ref(false)
const page = ref<AuditPage<AuditPageRow> | null>(null)
const loading = ref(false)
const opened = ref<number | null>(null)
const titles = ref<Record<string, string>>({})

const status = ref<string | null>(null)
const indexable = ref<string | null>(null)
const check = ref<string | null>(null)
const filters = ref<AuditFieldFilter[]>([])
let last: TableState = { page: 1, perPage: 50, sort: null, search: '' }

const visible = ref<AuditPageField[]>(restore())

function restore(): AuditPageField[] {
  try {
    const saved: unknown = JSON.parse(window.localStorage.getItem(STORAGE) ?? 'null')

    if (Array.isArray(saved)) {
      const known = saved.filter((key): key is AuditPageField => key in PAGE_FIELDS)

      if (known.length) return known
    }
  } catch {
    // A private window or a broken value: the default columns, as for anyone else.
  }

  return [...DEFAULT_COLUMNS]
}

watch(visible, (keys) => {
  try {
    window.localStorage.setItem(STORAGE, JSON.stringify(keys))
  } catch {
    // Not remembered — still shown.
  }
})

const fields = Object.keys(PAGE_FIELDS) as AuditPageField[]

const columnOptions = computed(() =>
  fields.map((key) => ({ value: key, label: t(`page.field-${key}`) })),
)

const filterFields = computed(() =>
  fields
    .filter((key) => !COMPUTED_FIELDS.includes(key))
    .map((key) => ({ value: key, label: t(`page.field-${key}`) })),
)

const columns = computed<TableColumn<AuditPageRow>[]>(() =>
  /* The picker keeps the order things were ticked in; the table keeps the card's order. */
  fields
    .filter((key) => visible.value.includes(key))
    .map((key) => ({
      key,
      label: t(`page.field-${key}`),
      sortable: !COMPUTED_FIELDS.includes(key),
      minWidth: PAGE_FIELDS[key] === 'url' || PAGE_FIELDS[key] === 'text' ? 220 : undefined,
      align: PAGE_FIELDS[key] === 'number' ? 'right' : undefined,
    })),
)

const statusOptions = computed(() => [
  ...['2xx', '3xx', '4xx', '5xx'].map((value) => ({ value, label: value })),
  { value: 'none', label: t('page.status-none') },
])

const indexableOptions = computed(() => [
  { value: '1', label: t('page.indexable-yes') },
  { value: '0', label: t('page.indexable-no') },
])

const checkOptions = computed(() =>
  Object.entries(titles.value).map(([value, label]) => ({ value, label })),
)

const filtersCount = computed(
  () =>
    [status.value, indexable.value, check.value].filter(Boolean).length +
    filters.value.filter(complete).length,
)

const query = computed<AuditPageQuery>(() => ({
  status: status.value,
  indexable: indexable.value === null ? null : indexable.value === '1',
  check: check.value,
  filters: filters.value.filter(complete),
}))

function complete(filter: AuditFieldFilter): boolean {
  return !WITH_VALUE.includes(filter.op) || (filter.value ?? '').trim() !== ''
}

function pick(value: SelectValue | SelectValue[] | null | undefined): string | null {
  return typeof value === 'string' && value !== '' ? value : null
}

function operations(field: AuditPageField) {
  return OPERATIONS[PAGE_FIELDS[field]].map((op) => ({ value: op, label: t(`page.op-${op}`) }))
}

function addFilter(): void {
  filters.value = [...filters.value, { field: 'title', op: 'empty' }]
}

function setField(index: number, value: SelectValue | SelectValue[] | null | undefined): void {
  const field = pick(value) as AuditPageField | null

  if (!field) return

  filters.value = filters.value.map((filter, at) =>
    at === index ? { field, op: OPERATIONS[PAGE_FIELDS[field]][0]!, value: '' } : filter,
  )
}

function setOp(index: number, value: SelectValue | SelectValue[] | null | undefined): void {
  const op = pick(value) as AuditFilterOp | null

  if (op)
    filters.value = filters.value.map((filter, at) => (at === index ? { ...filter, op } : filter))
}

function setValue(index: number, value: string): void {
  filters.value = filters.value.map((filter, at) => (at === index ? { ...filter, value } : filter))
}

function removeFilter(index: number): void {
  filters.value = filters.value.filter((_, at) => at !== index)
}

function setColumns(keys: unknown[]): void {
  const next = keys.filter((key): key is AuditPageField => String(key) in PAGE_FIELDS)

  /* A table without columns is a blank card; the address stays at least. */
  visible.value = next.length ? next : ['url']
}

function text(value: unknown): string {
  return value === null || value === undefined || value === '' ? '—' : String(value)
}

async function load(state: TableState = last): Promise<void> {
  last = state

  if (run.value === null) return

  loading.value = true

  try {
    page.value = await api.pages(run.value.id, {
      ...query.value,
      search: state.search,
      sort: state.sort ? `${state.sort.order === 'desc' ? '-' : ''}${state.sort.key}` : null,
      page: state.page,
      per_page: state.perPage,
    })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

function download(): void {
  if (run.value === null) return

  window.location.assign(
    api.pagesFile(
      run.value.id,
      { ...query.value, search: last.search },
      columns.value.map((column) => column.key),
    ),
  )
}

watch(query, () => void load({ ...last, page: 1 }), { deep: true })

onMounted(async () => {
  try {
    run.value = (await api.latest()).crawled

    if (run.value !== null) {
      const checks = await api.checks(run.value.id)
      titles.value = Object.fromEntries(checks.map((row) => [row.id, row.title]))
    }
  } catch (error) {
    toast.danger(message(error))
  } finally {
    ready.value = true
  }

  void load()
})
</script>

<template>
  <audit-layout :base="props.base" current="pages">
    <wx-text v-if="ready && run === null" tone="muted" class="wx-audit-pages__none">{{
      t('page.pages-none')
    }}</wx-text>

    <wx-table
      v-else
      :data="page"
      :columns="columns"
      row-key="id"
      searchable
      clickable
      hover
      flush
      :loading="loading || !ready"
      :per-page-options="[50, 100, 200]"
      :search-placeholder="t('page.search-url')"
      :filters-count="filtersCount"
      :filters-label="panel('filters.title')"
      :filters-width="420"
      :empty-text="t('page.empty')"
      @state-change="load"
      @row-click="(row) => (opened = row.id)"
    >
      <template #title>
        <wx-text v-if="run" size="sm" tone="muted">{{
          t('page.pages-run', { date: dates.short(run.finished_at) })
        }}</wx-text>
      </template>

      <template #actions>
        <wx-popover :title="t('page.columns')" width="300">
          <template #trigger>
            <wx-button size="sm" variant="text" icon="sliders">{{ t('page.columns') }}</wx-button>
          </template>
          <wx-checkbox-group
            :model-value="visible"
            :options="columnOptions"
            size="sm"
            class="wx-audit-pages__picker"
            @update:model-value="setColumns"
          />
        </wx-popover>
        <wx-button size="sm" variant="text" icon="download" :disabled="!run" @click="download">{{
          t('page.export')
        }}</wx-button>
      </template>

      <template #filters>
        <div class="wx-audit-pages__filters">
          <wx-select
            :model-value="status"
            :options="statusOptions"
            :placeholder="t('page.any-status')"
            clearable
            @update:model-value="status = pick($event)"
          />
          <wx-select
            :model-value="indexable"
            :options="indexableOptions"
            :placeholder="t('page.any-indexable')"
            clearable
            @update:model-value="indexable = pick($event)"
          />
          <wx-select
            :model-value="check"
            :options="checkOptions"
            :placeholder="t('page.any-check')"
            clearable
            filterable
            @update:model-value="check = pick($event)"
          />

          <div v-for="(filter, index) in filters" :key="index" class="wx-audit-pages__filter">
            <wx-select
              :model-value="filter.field"
              :options="filterFields"
              filterable
              size="sm"
              @update:model-value="setField(index, $event)"
            />
            <wx-select
              :model-value="filter.op"
              :options="operations(filter.field)"
              size="sm"
              @update:model-value="setOp(index, $event)"
            />
            <wx-select
              v-if="WITH_VALUE.includes(filter.op) && PAGE_FIELDS[filter.field] === 'source'"
              :model-value="filter.value ?? null"
              :options="SOURCES.map((value) => ({ value, label: t(`page.source-${value}`) }))"
              size="sm"
              @update:model-value="setValue(index, pick($event) ?? '')"
            />
            <wx-input
              v-else-if="WITH_VALUE.includes(filter.op)"
              :model-value="filter.value ?? ''"
              :placeholder="t('page.value')"
              size="sm"
              @update:model-value="setValue(index, String($event ?? ''))"
            />
            <wx-button
              size="sm"
              variant="text"
              icon="close"
              :aria-label="t('page.remove-filter')"
              @click="removeFilter(index)"
            />
          </div>

          <wx-button size="sm" variant="text" icon="plus" @click="addFilter">{{
            t('page.add-filter')
          }}</wx-button>
        </div>
      </template>

      <template v-for="key in visible" :key="key" #[`cell-${key}`]="{ row }">
        <audit-status
          v-if="PAGE_FIELDS[key] === 'status' && row[key] !== null"
          :code="Number(row[key])"
        />
        <wx-text v-else-if="PAGE_FIELDS[key] === 'status'" size="sm" tone="danger">{{
          t('page.status-none')
        }}</wx-text>
        <template v-else-if="PAGE_FIELDS[key] === 'bool'">{{
          row[key] ? t('page.yes') : t('page.no')
        }}</template>
        <template v-else-if="PAGE_FIELDS[key] === 'source'">{{
          t(`page.source-${row[key]}`)
        }}</template>
        <wx-badge v-else-if="key === 'issues' && row.issues > 0" type="warning" size="sm">{{
          row.issues
        }}</wx-badge>
        <span v-else :class="{ 'wx-audit-pages__long': PAGE_FIELDS[key] === 'url' }">{{
          text(row[key])
        }}</span>
      </template>
    </wx-table>

    <audit-page-card
      v-if="run"
      :run="run.id"
      :base="props.base"
      :page-id="opened"
      :titles="titles"
      @close="opened = null"
    />
  </audit-layout>
</template>

<style scoped>
.wx-audit-pages__none {
  display: block;
  padding: var(--wx-space-24);
}

.wx-audit-pages__filters {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-audit-pages__filter {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr) auto;
  gap: var(--wx-space-4);
  align-items: center;
}

.wx-audit-pages__picker {
  max-height: 360px;
  overflow-y: auto;
}

.wx-audit-pages__long {
  overflow-wrap: anywhere;
}
</style>

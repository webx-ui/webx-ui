<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import {
  toast,
  WxBadge,
  WxSelect,
  WxTable,
  WxText,
  type BadgeType,
  type RowKey,
  type SelectValue,
  type TableColumn,
} from '@webx-ui/core'
import AuditHostPlaces from './AuditHostPlaces.vue'
import AuditLayout from './AuditLayout.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditHostClass, AuditHostRow, AuditHosts } from './types'

/**
 * «Outgoing» (§8): every host the site points at — in the pages of the last full run and in the
 * fields of the database — by class, development stands on top and in red. A row opens into
 * where the host stands: the pages with their anchors, the records with the field and the way to
 * the editor.
 */
const props = defineProps<{ base: string }>()

const api = createAuditApi(useAdmin())
useAuditMessages()

const t = useTranslate('webx-audit')
const panel = useTranslate('webx-admin')
const message = useErrorText()

const answer = ref<AuditHosts | null>(null)
const loading = ref(false)
const search = ref('')
const hostClass = ref<AuditHostClass | null>(null)
const expanded = ref<RowKey[]>([])

const classes: Record<AuditHostClass, BadgeType> = {
  dev: 'danger',
  own_mirror: 'warning',
  external: 'info',
  own: 'success',
}

const columns = computed<TableColumn<AuditHostRow>[]>(() => [
  { key: 'host', label: t('page.host'), minWidth: 220 },
  { key: 'links', label: t('page.links'), width: 90, align: 'right' },
  { key: 'pages', label: t('page.pages'), width: 90, align: 'right', hideBelow: 560 },
  { key: 'broken', label: t('page.broken'), width: 100, align: 'right', hideBelow: 640 },
  { key: 'fields', label: t('page.fields'), width: 120, align: 'right' },
  { key: 'first_seen', label: t('page.first-seen'), minWidth: 140, hideBelow: 760 },
])

const classOptions = computed(() =>
  (Object.keys(classes) as AuditHostClass[]).map((value) => ({
    value,
    label: `${t(`page.host-${value}`)}${answer.value?.classes[value] ? ` · ${answer.value.classes[value]}` : ''}`,
  })),
)

function pick(value: SelectValue | SelectValue[] | null | undefined): AuditHostClass | null {
  return typeof value === 'string' && value in classes ? (value as AuditHostClass) : null
}

async function load(): Promise<void> {
  loading.value = true

  try {
    answer.value = await api.hosts({ class: hostClass.value, search: search.value })
    expanded.value = []
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch([hostClass, search], () => void load())

onMounted(load)
</script>

<template>
  <audit-layout :base="props.base" current="hosts">
    <wx-table
      v-model:expanded="expanded"
      v-model:search="search"
      :data="answer?.hosts ?? []"
      :columns="columns"
      row-key="host"
      expandable
      flush
      searchable
      :search-placeholder="t('page.search-host')"
      :loading="loading"
      :filters-count="hostClass ? 1 : 0"
      :filters-label="panel('filters.title')"
      :row-class="(row: AuditHostRow) => (row.class === 'dev' ? 'is-stand' : undefined)"
      :empty-text="t('page.hosts-empty')"
    >
      <template #filters>
        <wx-select
          :model-value="hostClass"
          :options="classOptions"
          :placeholder="t('page.any-class')"
          clearable
          @update:model-value="hostClass = pick($event)"
        />
      </template>

      <template #cell-host="{ row }">
        <span class="wx-audit-hosts__host">
          <wx-badge :type="classes[row.class]" size="sm">{{
            t(`page.host-${row.class}`)
          }}</wx-badge>
          <wx-text size="sm" :tone="row.class === 'dev' ? 'danger' : undefined" weight="medium">{{
            row.host
          }}</wx-text>
        </span>
      </template>
      <template #cell-broken="{ row }">
        <wx-text size="sm" :tone="row.broken ? 'danger' : 'muted'">{{ row.broken }}</wx-text>
      </template>
      <template #cell-fields="{ row }">
        <wx-text size="sm" :tone="row.fields && row.class === 'dev' ? 'danger' : undefined">{{
          row.fields
        }}</wx-text>
      </template>
      <template #cell-first_seen="{ row }">
        <wx-date v-if="row.first_seen" :value="row.first_seen" />
      </template>

      <template #expanded="{ row }">
        <audit-host-places :host="row.host" />
      </template>
    </wx-table>
  </audit-layout>
</template>

<style scoped>
.wx-audit-hosts__host {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  overflow-wrap: anywhere;
}

/* A stand is the one class that is always a mistake: a red edge, like an error in a list. */
:deep(.is-stand > td:first-child) {
  box-shadow: inset 3px 0 0 var(--wx-color-danger);
}
</style>

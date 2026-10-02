<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  WxBadge,
  WxButton,
  WxPopover,
  WxSelect,
  WxTable,
  WxText,
  type BadgeType,
  type SelectValue,
  type TableColumn,
} from '@webx-ui/core'
import AuditIssueList from './AuditIssueList.vue'
import AuditLayout from './AuditLayout.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type { AuditCheckRow, AuditSeverity } from './types'

/**
 * The findings of the last finished run (§8): one row per check, worst first; a row opens into
 * its addresses. The «?» beside a check says what was found, why it matters and how to fix it —
 * the three texts every check carries.
 */
const props = defineProps<{ base: string; settingsPath: string }>()

const api = createAuditApi(useAdmin())
useAuditMessages()

const t = useTranslate('webx-audit')
const panel = useTranslate('webx-admin')
const message = useErrorText()

const run = ref<number | null>(null)
const checks = ref<AuditCheckRow[] | null>(null)
const loading = ref(false)
const expanded = ref<(string | number)[]>([])

const severity = ref<AuditSeverity | null>(null)
const group = ref<string | null>(null)
const state = ref<'new' | 'persisting' | null>(null)

const query = computed(() => ({ severity: severity.value, group: group.value, state: state.value }))
const filtersCount = computed(
  () => [severity.value, group.value, state.value].filter(Boolean).length,
)

const types: Record<AuditSeverity, BadgeType> = {
  error: 'danger',
  warning: 'warning',
  notice: 'info',
}

const columns = computed<TableColumn<AuditCheckRow>[]>(() => [
  { key: 'severity', label: t('page.severity'), width: 130 },
  { key: 'title', label: t('page.check') },
  { key: 'count', label: t('page.count'), width: 110, align: 'right' },
])

const severityOptions = computed(() =>
  (['error', 'warning', 'notice'] as const).map((value) => ({
    value,
    label: t(`page.severity-${value}`),
  })),
)

const groupOptions = computed(() =>
  [
    'config',
    'host',
    'hosts',
    'indexing',
    'page',
    'content',
    'links',
    'images',
    'a11y',
    'structure',
  ].map((value) => ({ value, label: t(`page.group-${value}`) })),
)

const stateOptions = computed(() =>
  (['new', 'persisting'] as const).map((value) => ({ value, label: t(`page.state-${value}`) })),
)

/* The selects hand back `string | number`; the filters only ever hold the values offered. */
function pick<T extends string>(value: SelectValue | SelectValue[] | null | undefined): T | null {
  return typeof value === 'string' && value !== '' ? (value as T) : null
}

async function load(): Promise<void> {
  loading.value = true

  try {
    if (run.value === null) {
      run.value = (await api.latest()).done?.id ?? null
    }

    checks.value = run.value === null ? [] : await api.checks(run.value, query.value)
    expanded.value = []
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch(query, () => void load())

onMounted(load)
</script>

<template>
  <audit-layout :base="props.base" :settings-path="props.settingsPath" current="issues">
    <wx-table
      v-model:expanded="expanded"
      :data="checks ?? []"
      :columns="columns"
      row-key="id"
      expandable
      flush
      :loading="loading"
      :filters-count="filtersCount"
      :filters-label="panel('filters.title')"
      :empty-text="run === null ? t('page.never') : t('page.empty')"
    >
      <template #filters>
        <div class="wx-audit-findings__filters">
          <wx-select
            :model-value="severity"
            :options="severityOptions"
            :placeholder="t('page.any-severity')"
            clearable
            @update:model-value="severity = pick<AuditSeverity>($event)"
          />
          <wx-select
            :model-value="group"
            :options="groupOptions"
            :placeholder="t('page.any-group')"
            clearable
            @update:model-value="group = pick($event)"
          />
          <wx-select
            :model-value="state"
            :options="stateOptions"
            :placeholder="t('page.any-state')"
            clearable
            @update:model-value="state = pick<'new' | 'persisting'>($event)"
          />
        </div>
      </template>

      <template #cell-severity="{ row }">
        <wx-badge :type="types[row.severity]" dot>{{
          t(`page.severity-${row.severity}`)
        }}</wx-badge>
      </template>

      <template #cell-title="{ row }">
        <span class="wx-audit-findings__title">
          <wx-text size="sm">{{ row.title }}</wx-text>
          <wx-badge v-if="row.new > 0" type="primary" size="sm">{{ t('page.state-new') }}</wx-badge>
          <wx-popover :title="row.title" width="360">
            <template #trigger>
              <wx-button
                size="sm"
                variant="text"
                icon="question"
                :aria-label="t('page.explain')"
                @click.stop
              />
            </template>
            <dl class="wx-audit-findings__texts">
              <dt>{{ t('page.found') }}</dt>
              <dd>{{ row.found }}</dd>
              <dt>{{ t('page.why') }}</dt>
              <dd>{{ row.why }}</dd>
              <dt>{{ t('page.fix') }}</dt>
              <dd>{{ row.fix }}</dd>
            </dl>
          </wx-popover>
        </span>
      </template>

      <template #expanded="{ row }">
        <audit-issue-list
          v-if="run !== null"
          :run="run"
          :query="{ ...query, check: row.id }"
          :fixable="(row.fixes ?? []).length > 0"
        />
      </template>
    </wx-table>
  </audit-layout>
</template>

<style scoped>
.wx-audit-findings__filters {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-audit-findings__title {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-audit-findings__texts {
  display: grid;
  gap: var(--wx-space-4);
  margin: 0;
  font-size: var(--wx-font-size-sm);
}

.wx-audit-findings__texts dt {
  color: var(--wx-text-muted);
}

.wx-audit-findings__texts dd {
  margin: 0 0 var(--wx-space-8);
}
</style>

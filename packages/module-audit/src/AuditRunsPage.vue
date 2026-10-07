<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import {
  confirm,
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxTable,
  WxText,
  type BadgeType,
  type RowKey,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import AuditCompareIssues from './AuditCompareIssues.vue'
import AuditLayout from './AuditLayout.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type {
  AuditComparison,
  AuditComparisonRow,
  AuditPage,
  AuditRun,
  AuditRunStatus,
  AuditSeverity,
} from './types'

/**
 * The history (§8 «Runs»): every run kept, newest first, with its health and counts. Ticking a
 * run compares it with the one it was analysed against; ticking two compares those two — by
 * fingerprint (decision 8): what is new in the later one, what both have, what is gone. `?to=`
 * opens on one run already ticked — the way the page card's recheck leads here. Below, for whoever
 * manages the section, the way to clear every run at once, behind a warning.
 */
const props = defineProps<{ base: string }>()

const context = useAdmin()
const api = createAuditApi(context)
const route = useRoute()
useAuditMessages()

const t = useTranslate('webx-audit')
const message = useErrorText()

const runs = ref<AuditPage<AuditRun> | null>(null)
const loading = ref(false)
const selected = ref<RowKey[]>([])
const comparison = ref<AuditComparison | null>(null)
const comparing = ref(false)
const expanded = ref<RowKey[]>([])
const clearing = ref(false)

// A boolean, not a computed: the permissions do not change while the screen is open.
const canManage = context.can('audit.manage')

const statuses: Record<AuditRunStatus, BadgeType> = {
  queued: 'info',
  running: 'primary',
  done: 'success',
  failed: 'danger',
  cancelled: 'warning',
}

const severities: Record<AuditSeverity, BadgeType> = {
  error: 'danger',
  warning: 'warning',
  notice: 'info',
}

const columns = computed<TableColumn<AuditRun>[]>(() => [
  { key: 'id', label: t('page.run-id'), width: 90 },
  { key: 'created_at', label: t('page.started-at'), minWidth: 160 },
  { key: 'scope', label: t('page.scope'), width: 120 },
  { key: 'status', label: t('page.status'), width: 130 },
  { key: 'health', label: t('page.health'), width: 100, align: 'right' },
  { key: 'severity', label: t('page.severity'), minWidth: 170, hideBelow: 640 },
  { key: 'started_by', label: t('page.started-by'), minWidth: 130, hideBelow: 760 },
])

const compareColumns = computed<TableColumn<AuditComparisonRow>[]>(() => [
  { key: 'severity', label: t('page.severity'), width: 130 },
  { key: 'title', label: t('page.check') },
  { key: 'new', label: t('page.kind-new'), width: 90, align: 'right' },
  { key: 'fixed', label: t('page.kind-fixed'), width: 100, align: 'right' },
  { key: 'persisting', label: t('page.kind-persisting'), width: 120, align: 'right' },
])

/* What the selection means: one run against its own previous, or two against each other. */
const pair = computed<{ to: number; from: number | null } | null>(() => {
  const ids = selected.value.map(Number).sort((a, b) => a - b)

  if (ids.length === 0) return null

  return ids.length === 1
    ? { to: ids[0]!, from: null }
    : { to: ids[ids.length - 1]!, from: ids[0]! }
})

const scopesDiffer = computed(
  () => comparison.value !== null && comparison.value.from.scope !== comparison.value.to.scope,
)

function who(run: AuditRun): string {
  if (run.started_by === 'schedule') return t('page.by-schedule')
  if (run.started_by === 'mcp') return t('page.by-mcp')
  if (run.started_by === 'console') return t('page.by-console')

  return run.started_by ? t('page.by-person') : ''
}

async function load(state?: TableState): Promise<void> {
  loading.value = true

  try {
    runs.value = await api.runs({ page: state?.page ?? 1, per_page: state?.perPage ?? 20 })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

async function compare(): Promise<void> {
  const current = pair.value

  comparison.value = null
  expanded.value = []

  if (!current) return

  comparing.value = true

  try {
    comparison.value = await api.compare(current.to, current.from)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    comparing.value = false
  }
}

async function clear(): Promise<void> {
  const agreed = await confirm({
    title: t('page.clear-title'),
    message: t('page.clear-text'),
    confirmText: t('page.clear-confirm'),
    cancelText: t('page.clear-cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  clearing.value = true

  try {
    await api.clear()
    selected.value = []
    toast.success(t('page.cleared'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    clearing.value = false
  }
}

onMounted(() => {
  const to = Number(route.query.to)

  if (Number.isInteger(to) && to > 0) selected.value = [to]
})

watch(selected, (keys) => {
  /* Two at most: a third tick replaces the oldest one. */
  if (keys.length > 2) selected.value = keys.slice(-2)
  else void compare()
})
</script>

<template>
  <audit-layout :base="props.base" current="runs" :card="false">
    <div class="wx-audit-runs">
      <wx-card>
        <wx-table
          v-model:selected="selected"
          :data="runs"
          :columns="columns"
          row-key="id"
          selectable
          :selectable-if="(row: AuditRun) => row.status === 'done'"
          flush
          :loading="loading"
          :empty-text="t('page.runs-empty')"
          @state-change="load"
        >
          <template #cell-created_at="{ row }"><wx-date :value="row.created_at" /></template>
          <template #cell-scope="{ row }">{{ t(`page.scope-${row.scope}`) }}</template>
          <template #cell-status="{ row }">
            <wx-badge :type="statuses[row.status]" dot>{{
              t(`page.status-${row.status}`)
            }}</wx-badge>
          </template>
          <template #cell-health="{ row }">{{
            row.counts ? `${row.counts.health}%` : ''
          }}</template>
          <template #cell-severity="{ row }">
            <span v-if="row.counts" class="wx-audit-runs__counts">
              <template v-for="(type, key) in severities" :key="key">
                <wx-badge v-if="row.counts.severity[key]" :type="type" size="sm">{{
                  row.counts.severity[key]
                }}</wx-badge>
              </template>
            </span>
          </template>
          <template #cell-started_by="{ row }">{{ who(row) }}</template>
        </wx-table>
      </wx-card>

      <wx-text v-if="!pair" size="sm" tone="muted">{{ t('page.compare-help') }}</wx-text>

      <wx-card v-else :title="t('page.compare')">
        <wx-text v-if="comparison" size="sm" tone="muted" class="wx-audit-runs__pair">
          {{ t('page.compare-from') }} #{{ comparison.from.id }} ·
          <wx-date :value="comparison.from.created_at" /> → {{ t('page.compare-to') }} #{{
            comparison.to.id
          }}
          · <wx-date :value="comparison.to.created_at" />
        </wx-text>

        <wx-alert
          v-if="scopesDiffer"
          type="info"
          :description="t('page.compare-scopes')"
          class="wx-audit-runs__note"
        />

        <wx-table
          v-model:expanded="expanded"
          :data="comparison?.checks ?? []"
          :columns="compareColumns"
          row-key="check"
          expandable
          flush
          :pagination="false"
          :loading="comparing"
          :empty-text="t('page.compare-none')"
        >
          <template #cell-severity="{ row }">
            <wx-badge :type="severities[row.severity]" dot>{{
              t(`page.severity-${row.severity}`)
            }}</wx-badge>
          </template>
          <template #cell-new="{ row }">
            <wx-text :tone="row.new ? 'danger' : 'muted'" size="sm">{{ row.new }}</wx-text>
          </template>
          <template #cell-fixed="{ row }">
            <wx-text :tone="row.fixed ? 'success' : 'muted'" size="sm">{{ row.fixed }}</wx-text>
          </template>
          <template #expanded="{ row }">
            <audit-compare-issues
              v-if="comparison"
              :from="comparison.from.id"
              :to="comparison.to.id"
              :row="row"
            />
          </template>
        </wx-table>
      </wx-card>

      <div v-if="canManage && runs?.total" class="wx-audit-runs__danger">
        <wx-button
          type="danger"
          variant="outline"
          icon="trash"
          :loading="clearing"
          @click="clear"
          >{{ t('page.clear') }}</wx-button
        >
      </div>
    </div>
  </audit-layout>
</template>

<style scoped>
.wx-audit-runs {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-audit-runs__counts {
  display: inline-flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
}

.wx-audit-runs__pair {
  display: block;
  margin-bottom: var(--wx-space-12);
}

.wx-audit-runs__danger {
  display: flex;
  justify-content: flex-end;
}

.wx-audit-runs__note {
  margin-bottom: var(--wx-space-12);
}
</style>

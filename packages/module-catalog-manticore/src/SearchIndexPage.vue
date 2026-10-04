<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxListScreen,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  toast,
  WxAlert,
  WxBadge,
  WxCard,
  WxDescriptions,
  WxDescriptionsItem,
  WxProgress,
  WxTable,
  WxText,
  type BadgeType,
  type TableColumn,
} from '@webx-ui/core'
import { createSearchIndexApi } from './api'
import { useCatalogManticoreMessages } from './i18n'
import type { IndexReport, IndexTable, RebuildProgress, TableState } from './types'

/**
 * «System → Search index» (decision 27 of the Manticore spec).
 *
 * Opened when something is wrong — a product is not found, the list says it comes from the
 * database — so the page answers in that order: does the server answer, is every table of the
 * schema the catalogue writes now and as full as the database, and is the queue moving. A table
 * out of date has «Rebuild», which is a job on the queue: the page follows it by asking again,
 * and a closed tab does not stop it.
 */
const POLL = 2000

const context = useAdmin()
useCatalogManticoreMessages()

const t = useTranslate('webx-catalog-manticore')
const message = useErrorText()
const api = createSearchIndexApi(context)

const report = ref<IndexReport | null>(null)
const loading = ref(true)
const failed = ref(false)
const starting = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

const canManage = computed(() => context.can('search-index.manage'))
const rebuild = computed<RebuildProgress | null>(() => report.value?.rebuild ?? null)

/** Waiting or running, and still heard from: no second rebuild is offered. */
const underway = computed(
  () =>
    rebuild.value !== null &&
    (rebuild.value.state === 'queued' || rebuild.value.state === 'running') &&
    !rebuild.value.stalled,
)

/**
 * The console's `--rebuild` holds the lock the panel's rebuild takes too, and writes no progress
 * of its own: while it runs, the button is held back and the page says why. A lock outlives a
 * killed process by an hour at most, unlike a table left beside, which would hold it forever.
 */
const fromConsole = computed(() => (report.value?.locked ?? false) && !underway.value)

/**
 * The page keeps asking while a rebuild is underway — or while a table is filled beside a live
 * one: a rebuild started from the console writes no progress, only the table.
 */
const filling = computed(
  () =>
    underway.value ||
    fromConsole.value ||
    (report.value?.tables.some((table) => table.rebuilding) ?? false),
)

const actions = computed<ScreenAction[]>(() => {
  const list: ScreenAction[] = []

  if (canManage.value && report.value !== null) {
    list.push({
      key: 'rebuild',
      label: report.value.outdated ? t('panel.rebuild') : t('panel.rebuild-again'),
      icon: 'refresh',
      // The section exists for it only when a table is out of date; otherwise it is a rare act.
      primary: report.value.outdated,
      menu: !report.value.outdated,
      disabled: underway.value || fromConsole.value || !report.value.connection.available,
      loading: starting.value,
      run: () => void start(),
    })
  }

  list.push({
    key: 'refresh',
    label: t('panel.refresh'),
    icon: 'refresh',
    menu: true,
    run: () => void load(),
  })

  return list
})

const columns = computed<TableColumn<IndexTable>[]>(() => [
  { key: 'locale', label: t('panel.language'), width: 96 },
  { key: 'table', label: t('panel.table'), minWidth: 200, hideBelow: 640 },
  { key: 'documents', label: t('panel.in-index'), align: 'right', width: 120 },
  { key: 'products', label: t('panel.in-database'), align: 'right', width: 120 },
  { key: 'state', label: t('panel.state'), minWidth: 180 },
])

const STATE: Record<TableState, BadgeType> = {
  ready: 'success',
  stale: 'warning',
  missing: 'danger',
}

const progressLine = computed(() => {
  const progress = rebuild.value

  if (progress === null) return ''

  switch (progress.state) {
    case 'queued':
      return t('panel.rebuild-queued')
    case 'running':
      return t('panel.rebuild-running', {
        done: count(progress.done),
        total: count(progress.total),
      })
    case 'done':
      return t('panel.rebuild-done', { done: count(progress.done) })
    case 'failed':
      return t('panel.rebuild-failed')
    default:
      return ''
  }
})

function count(value: number | null): string {
  return value === null ? '—' : value.toLocaleString(context.i18n.state.locale)
}

async function load(): Promise<void> {
  clearTimeout(timer)

  try {
    report.value = await api.report()
    failed.value = false
  } catch {
    failed.value = report.value === null
  } finally {
    loading.value = false
  }

  if (filling.value) timer = setTimeout(() => void load(), POLL)
}

async function start(): Promise<void> {
  const agreed = await confirm({
    title: t('panel.rebuild-title'),
    message: t('panel.rebuild-text'),
    confirmText: t('panel.rebuild'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed || report.value === null) return

  starting.value = true

  try {
    report.value = { ...report.value, rebuild: await api.rebuild() }
    toast.success(t('panel.rebuild-started'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    starting.value = false
  }

  await load()
}

onMounted(() => void load())
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <wx-list-screen :title="t('panel.title')" :actions="actions" :card="false">
    <div class="wx-search-index">
      <wx-text tone="muted">{{ t('panel.lead') }}</wx-text>

      <wx-alert v-if="failed" type="danger">{{ t('panel.load-failed') }}</wx-alert>

      <template v-if="report">
        <wx-alert v-if="!report.connection.available" type="danger" :title="t('panel.unavailable')">
          {{ report.connection.error }}
        </wx-alert>

        <wx-alert
          v-else-if="report.outdated && !underway && !fromConsole"
          type="warning"
          :title="t('panel.outdated')"
        >
          {{ t('panel.outdated-text') }}
        </wx-alert>

        <wx-alert v-if="fromConsole" type="info">{{ t('panel.rebuild-console') }}</wx-alert>

        <!-- The rebuild started from here: what it is doing, or how it ended. -->
        <wx-card
          v-if="rebuild && rebuild.state !== 'idle'"
          bordered
          class="wx-search-index__rebuild"
        >
          <div class="wx-search-index__rebuild-head">
            <wx-text weight="medium">{{ progressLine }}</wx-text>
            <wx-date
              v-if="rebuild.finished_at ?? rebuild.started_at ?? rebuild.queued_at"
              :value="rebuild.finished_at ?? rebuild.started_at ?? rebuild.queued_at"
            />
          </div>
          <wx-progress
            v-if="rebuild.state === 'running' || rebuild.state === 'queued'"
            :value="rebuild.done"
            :max="Math.max(rebuild.total, 1)"
            :indeterminate="rebuild.state === 'queued' || rebuild.total === 0"
            :aria-label="progressLine"
          />
          <wx-text v-if="rebuild.stalled" size="sm" tone="warning">
            {{ t('panel.rebuild-stalled') }}
          </wx-text>
          <wx-text v-if="rebuild.state === 'failed' && rebuild.error" size="sm" tone="danger">
            {{ rebuild.error }}
          </wx-text>
        </wx-card>

        <div class="wx-search-index__grid">
          <wx-card bordered :title="t('panel.connection')">
            <wx-descriptions :columns="1" label-width="140px">
              <wx-descriptions-item :label="t('panel.address')">
                <code>{{ report.connection.address }}</code>
              </wx-descriptions-item>
              <wx-descriptions-item :label="t('panel.prefix')">
                <code v-if="report.connection.prefix">{{ report.connection.prefix }}</code>
                <span v-else>—</span>
              </wx-descriptions-item>
              <wx-descriptions-item :label="t('panel.version')">
                {{ report.connection.version ?? '—' }}
              </wx-descriptions-item>
              <wx-descriptions-item :label="t('panel.state')">
                <wx-badge :type="report.connection.available ? 'success' : 'danger'">
                  {{ report.connection.available ? t('panel.available') : t('panel.unavailable') }}
                </wx-badge>
              </wx-descriptions-item>
            </wx-descriptions>
          </wx-card>

          <wx-card bordered :title="t('panel.queue')">
            <wx-descriptions :columns="1" label-width="180px">
              <wx-descriptions-item :label="t('panel.waiting')">
                {{ count(report.queue.waiting) }}
              </wx-descriptions-item>
              <wx-descriptions-item v-if="report.queue.oldest" :label="t('panel.oldest')">
                <wx-date :value="report.queue.oldest" />
              </wx-descriptions-item>
            </wx-descriptions>
            <wx-text v-if="report.queue.waiting === 0" size="sm" tone="muted">
              {{ t('panel.queue-empty') }}
            </wx-text>
          </wx-card>
        </div>

        <wx-card bordered :title="t('panel.tables')">
          <wx-table
            :data="report.tables"
            :columns="columns"
            row-key="table"
            flush
            :cards-below="560"
            :empty-text="t('panel.no-tables')"
            :aria-label="t('panel.tables')"
          >
            <template #cell-table="{ row }">
              <code>{{ row.table }}</code>
            </template>
            <template #cell-documents="{ row }">
              {{ count(row.documents) }}
            </template>
            <template #cell-products>
              {{ count(report.products) }}
            </template>
            <template #cell-state="{ row }">
              <div class="wx-search-index__state">
                <wx-badge :type="STATE[row.state]">{{ t(`panel.state-${row.state}`) }}</wx-badge>
                <wx-text v-if="row.reason" size="xs" tone="muted">{{ row.reason }}</wx-text>
                <wx-text v-if="row.rebuilding" size="xs" tone="muted">
                  {{
                    row.filled == null
                      ? t('panel.filling')
                      : t('panel.filled', {
                          done: count(row.filled),
                          total: count(report.products),
                        })
                  }}
                </wx-text>
              </div>
            </template>
          </wx-table>
        </wx-card>
      </template>
    </div>
  </wx-list-screen>
</template>

<style scoped>
.wx-search-index {
  container-type: inline-size;
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-search-index__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-16);
}

@container (min-width: 720px) {
  .wx-search-index__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.wx-search-index__rebuild :deep(.wx-card__body) {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-search-index__rebuild-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.wx-search-index__state {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-4);
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-search-index code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}
</style>

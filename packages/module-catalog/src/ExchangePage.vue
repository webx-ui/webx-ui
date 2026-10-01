<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxListScreen,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  createModal,
  toast,
  WxBadge,
  WxIcon,
  WxProgress,
  WxTable,
  WxText,
  type Paginated,
  type RowKey,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import ExchangeExportDialog from './ExchangeExportDialog.vue'
import ExchangeRunDetail from './ExchangeRunDetail.vue'
import {
  createExchangeApi,
  isRunning,
  useRunPolling,
  type ExchangeProfile,
  type ExchangeRun,
} from './exchange'
import { runStatus, runTotals } from './exchangeWords'
import { useCatalogMessages } from './i18n'
import type { BulkSelection } from './types'

/**
 * «Exchange» (§8.1 of the exchange spec): every import and export with what it did, newest first,
 * and the two ways in — the import wizard and the export dialog. A row opens in place: its
 * totals, its errors, its file, its run of the journal.
 *
 * A run that is still going is asked after every two seconds, the way the bulk actions are; the
 * row moves on its own while the list is open. A run started elsewhere — the wizard, the export
 * of the product list — arrives here as `?run=<id>`, opened and followed.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const context = useAdmin()
const api = createExchangeApi(context)
const route = useRoute()
const router = useRouter()
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const exporter = createModal<ExchangeRun, { selection?: BulkSelection | null }>(
  ExchangeExportDialog,
)

const page = ref<Paginated<ExchangeRun> | null>(null)
const loading = ref(true)
const expanded = ref<RowKey[]>([])
const profiles = ref<ExchangeProfile[]>([])

const canImport = computed(() => context.can('catalog.manage'))

const polling = useRunPolling(api, (fresh) => {
  if (page.value === null) return

  page.value = {
    ...page.value,
    data: page.value.data.map((one) => (one.id === fresh.id ? fresh : one)),
  }
})

const columns = computed<TableColumn<ExchangeRun>[]>(() => [
  { key: 'run', label: t('panel.exchange-column-run'), minWidth: 200 },
  { key: 'status', label: t('panel.exchange-column-status'), width: 130 },
  { key: 'result', label: t('panel.exchange-column-result'), width: 240, hideBelow: 780 },
  { key: 'started', label: t('panel.exchange-column-started'), width: 120, hideBelow: 560 },
])

function profileName(id: number | null): string | null {
  return id === null ? null : (profiles.value.find((one) => one.id === id)?.name ?? null)
}

let asked = 0

async function load(state?: TableState): Promise<void> {
  const mine = ++asked

  loading.value = true

  try {
    const answer = await api.runs({ page: state?.page ?? page.value?.current_page ?? 1 })

    if (mine !== asked) return

    page.value = answer

    for (const run of answer.data) if (isRunning(run)) polling.watch(run.id)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    if (mine === asked) loading.value = false
  }
}

onMounted(async () => {
  void api
    .profiles()
    .then((found) => (profiles.value = found))
    .catch(() => undefined)

  await load()

  // A run handed over by the wizard or the export: open, and followed even if it is not on
  // the first page any more (a run of the past, linked from somewhere).
  const wanted = Number(route.query.run)

  if (Number.isInteger(wanted) && wanted > 0) open(wanted)
})

async function open(id: number): Promise<void> {
  if (!expanded.value.includes(id)) expanded.value = [...expanded.value, id]

  if (page.value !== null && !page.value.data.some((one) => one.id === id)) {
    try {
      const run = await api.run(id)

      page.value = { ...page.value, data: [run, ...page.value.data] }
    } catch {
      return
    }
  }

  polling.watch(id)
}

function toggle(run: ExchangeRun): void {
  expanded.value = expanded.value.includes(run.id)
    ? expanded.value.filter((one) => one !== run.id)
    : [...expanded.value, run.id]
}

async function exportAll(): Promise<void> {
  const run = await exporter({ selection: null })

  if (!run) return

  toast.success(t('panel.exchange-export-started'))
  await load({ page: 1 } as TableState)
  void open(run.id)
}

const actions = computed<ScreenAction[]>(() => [
  ...(canImport.value
    ? [
        {
          key: 'import',
          label: t('panel.exchange-import'),
          icon: 'upload' as const,
          primary: true,
          run: () => void router.push(`${props.base}/exchange/import`),
        },
      ]
    : []),
  {
    key: 'export',
    label: t('panel.exchange-export'),
    icon: 'download',
    run: () => void exportAll(),
  },
  {
    key: 'profiles',
    label: t('panel.exchange-profiles'),
    icon: 'sliders',
    menu: true,
    run: () => void router.push(`${props.base}/exchange/profiles`),
  },
])

function title(run: ExchangeRun): string {
  return `${t(`panel.exchange-${run.direction}`)} #${run.id}`
}

function progress(run: ExchangeRun): number {
  return run.rows_total > 0 ? Math.round((run.rows_done / run.rows_total) * 100) : 0
}
</script>

<template>
  <div class="wx-catalog-exchange">
    <wx-list-screen
      :title="t('panel.exchange-title')"
      :subtitle="t('panel.exchange-help')"
      :back="`${props.base}/products`"
      :back-label="t('module.products')"
      :actions="actions"
    >
      <wx-table
        v-model:expanded="expanded"
        :data="page"
        :columns="columns"
        row-key="id"
        expandable
        :cards-below="0"
        flush
        layout="fixed"
        :loading="loading"
        :empty-text="t('panel.exchange-empty')"
        :aria-label="t('panel.exchange-title')"
        @state-change="load"
        @row-click="toggle"
      >
        <template #cell-run="{ row }">
          <div class="wx-catalog-exchange__run">
            <span class="wx-catalog-exchange__title">
              <wx-icon
                :name="row.direction === 'import' ? 'upload' : 'download'"
                size="sm"
                class="wx-catalog-exchange__direction"
              />
              {{ title(row) }}
              <wx-badge v-if="row.dry_run" size="sm" round>{{
                t('panel.exchange-check')
              }}</wx-badge>
            </span>
            <span v-if="profileName(row.profile_id) || row.source" class="wx-catalog-exchange__sub">
              {{ profileName(row.profile_id) ?? row.source }}
            </span>
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="wx-catalog-exchange__status">
            <wx-badge :type="runStatus(row, t).type" dot>{{ runStatus(row, t).label }}</wx-badge>
            <template v-if="isRunning(row) && row.rows_total > 0">
              <wx-progress
                :value="progress(row)"
                size="sm"
                :aria-label="
                  t('panel.exchange-progress', {
                    done: row.rows_done,
                    total: row.rows_total,
                  })
                "
              />
              <wx-text size="xs" tone="muted">
                {{ t('panel.exchange-progress', { done: row.rows_done, total: row.rows_total }) }}
              </wx-text>
            </template>
          </div>
        </template>

        <template #cell-result="{ row }">
          <wx-text size="sm" :tone="row.failed > 0 ? undefined : 'muted'" truncate>
            {{
              runTotals(row, t)
                .map((one) => one.text)
                .join(' · ')
            }}
          </wx-text>
        </template>

        <template #cell-started="{ row }">
          <wx-date :value="row.started_at ?? row.created_at" compact />
        </template>

        <template #expanded="{ row }">
          <exchange-run-detail
            :run="row"
            :base="props.base"
            :profile-name="profileName(row.profile_id)"
          />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-catalog-exchange {
  min-width: 0;
}

.wx-catalog-exchange__run {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-catalog-exchange__title {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  font-weight: var(--wx-font-weight-medium);
  min-width: 0;
}

.wx-catalog-exchange__direction {
  flex: none;
  color: var(--wx-text-muted);
}

.wx-catalog-exchange__sub {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-exchange__status {
  display: grid;
  justify-items: start;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-catalog-exchange__status :deep(.wx-progress) {
  justify-self: stretch;
}
</style>

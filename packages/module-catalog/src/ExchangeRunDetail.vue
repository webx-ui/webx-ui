<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import {
  createModal,
  toast,
  WxAlert,
  WxButton,
  WxTable,
  WxText,
  type TableColumn,
} from '@webx-ui/core'
import ExchangeJournalDialog from './ExchangeJournalDialog.vue'
import { createExchangeApi, isRunning, type ExchangeRun, type ExchangeRunError } from './exchange'
import { useCatalogMessages } from './i18n'
import { runTotals } from './exchangeWords'

/**
 * A run opened in the list of «Exchange» (§8.1): what it read and who started it, its totals,
 * the first hundred errors with all of them a click away as CSV, the finished file of an
 * export, and the run of the journal that holds what an import changed.
 *
 * The errors are asked for again when the run's count of them moves, which is how a run still
 * going fills its table while it is open.
 */
const props = defineProps<{ run: ExchangeRun; base: string; profileName?: string | null }>()

const admin = useAdmin()
const api = createExchangeApi(admin)
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const journal = createModal<void, { runId: number; base: string }>(ExchangeJournalDialog)

const errors = ref<ExchangeRunError[]>([])
const more = ref(false)
const loading = ref(false)

async function loadErrors(): Promise<void> {
  if (props.run.failed === 0) {
    errors.value = []
    more.value = false

    return
  }

  loading.value = true

  try {
    const page = await api.errors(props.run.id)

    errors.value = page.data
    more.value = page.last_page > 1
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.run.id, props.run.failed],
  () => void loadErrors(),
  { immediate: true },
)

const totals = computed(() => runTotals(props.run, t))

const columns = computed<TableColumn<ExchangeRunError>[]>(() => [
  { key: 'row', label: t('panel.exchange-error-row'), width: 72, align: 'right' },
  { key: 'column', label: t('panel.exchange-error-column'), width: 150 },
  { key: 'value', label: t('panel.exchange-error-value'), width: 180, hideBelow: 560 },
  { key: 'message', label: t('panel.exchange-error-message'), minWidth: 200 },
])

const fileGone = computed(
  () =>
    props.run.direction === 'export' && props.run.status === 'done' && props.run.file_url === null,
)
</script>

<template>
  <div class="wx-catalog-run">
    <dl class="wx-catalog-run__facts">
      <div v-if="run.source" class="wx-catalog-run__fact">
        <dt>{{ t('panel.exchange-source') }}</dt>
        <dd class="wx-catalog-run__source">{{ run.source }}</dd>
      </div>
      <div v-if="profileName" class="wx-catalog-run__fact">
        <dt>{{ t('panel.exchange-profile') }}</dt>
        <dd>{{ profileName }}</dd>
      </div>
      <div v-if="run.admin_name" class="wx-catalog-run__fact">
        <dt>{{ t('panel.exchange-who') }}</dt>
        <dd>{{ run.admin_name }}</dd>
      </div>
      <div v-if="run.started_at" class="wx-catalog-run__fact">
        <dt>{{ t('panel.exchange-column-started') }}</dt>
        <dd><wx-date :value="run.started_at" /></dd>
      </div>
    </dl>

    <wx-alert
      v-if="run.dry_run && !isRunning(run)"
      type="info"
      :title="t('panel.exchange-check-help')"
    />
    <wx-alert
      v-if="run.status === 'stopped'"
      type="warning"
      :title="t('panel.exchange-stopped-help')"
    />
    <wx-alert
      v-if="run.status === 'failed'"
      type="danger"
      :title="t('panel.exchange-failed-help')"
    />

    <ul v-if="totals.length > 0" class="wx-catalog-run__totals">
      <li v-for="total in totals" :key="total.key" :class="`is-${total.tone}`">
        {{ total.text }}
      </li>
    </ul>

    <div class="wx-catalog-run__actions">
      <wx-button
        v-if="run.direction === 'export' && run.file_url"
        size="sm"
        type="primary"
        icon="download"
        :href="api.file(run.id)"
      >
        {{ t('panel.exchange-file') }}
      </wx-button>
      <wx-text v-else-if="fileGone" size="sm" tone="muted">
        {{ t('panel.exchange-file-gone') }}
      </wx-text>
      <wx-button
        v-if="run.history_id !== null && !run.dry_run"
        size="sm"
        variant="outline"
        icon="clock"
        @click="journal({ runId: run.history_id!, base })"
      >
        {{ t('panel.exchange-journal') }}
      </wx-button>
      <wx-button
        v-if="run.failed > 0"
        size="sm"
        variant="outline"
        icon="download"
        :href="api.errorsFile(run.id)"
      >
        {{ t('panel.exchange-errors-csv') }}
      </wx-button>
    </div>

    <template v-if="run.failed > 0">
      <wx-table
        :data="errors"
        :columns="columns"
        :loading="loading"
        :clickable="false"
        :hover="false"
        :pagination="false"
        :cards-below="420"
        :max-height="360"
        layout="fixed"
        size="sm"
        bordered
        :aria-label="t('panel.exchange-errors')"
      >
        <template #cell-column="{ row }">
          <code v-if="row.column" class="wx-catalog-run__code">{{ row.column }}</code>
          <wx-text v-else size="sm" tone="muted">{{ t('panel.exchange-error-whole-row') }}</wx-text>
        </template>
        <template #cell-value="{ row }">
          <span class="wx-catalog-run__value" :title="row.value ?? undefined">{{ row.value }}</span>
        </template>
      </wx-table>
      <wx-text v-if="more" size="sm" tone="muted">{{ t('panel.exchange-errors-more') }}</wx-text>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-run {
  display: grid;
  gap: var(--wx-space-12);
  min-width: 0;
  padding: var(--wx-space-4) 0 var(--wx-space-8);
}

.wx-catalog-run__facts {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8) var(--wx-space-24);
  margin: 0;
  font-size: var(--wx-font-size-sm);
}

.wx-catalog-run__fact {
  display: grid;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-catalog-run__fact dt {
  color: var(--wx-text-muted);
}

.wx-catalog-run__fact dd {
  margin: 0;
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-catalog-run__source {
  font-family: var(--wx-font-family-mono);
}

.wx-catalog-run__totals {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8) var(--wx-space-16);
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: var(--wx-font-size-sm);
  font-variant-numeric: tabular-nums;
}

.wx-catalog-run__totals .is-success {
  color: var(--wx-color-success);
}

.wx-catalog-run__totals .is-danger {
  color: var(--wx-color-danger);
}

.wx-catalog-run__totals .is-muted {
  color: var(--wx-text-muted);
}

.wx-catalog-run__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-catalog-run__actions:empty {
  display: none;
}

.wx-catalog-run__code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
}

.wx-catalog-run__value {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

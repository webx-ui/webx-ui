<script setup lang="ts">
import { computed } from 'vue'
import { WxBadge, WxTable, WxText, WxTooltip, type TableColumn } from '@webx-ui/core'
import { WxDate } from '@webx-ui/module-admin'
import { codeTone, outcomeTone } from './format'
import { usePaymentsLines, usePaymentsWords } from './i18n'
import type { EventLine } from './types'

/**
 * Events of the exchange by time (spec §20.7): direction and kind, the code, the signature, the
 * decision and how long it took. A line is never a body: a click opens the event, where the
 * headers and bodies are — for whoever may read them.
 */
const props = withDefaults(
  defineProps<{
    events: EventLine[]
    /** The id of the event open, drawn as the current line. */
    current?: number | null
    /** The order and the attempt of each line: the general journal shows them, a tab does not. */
    context?: boolean
    loading?: boolean
    emptyText?: string
  }>(),
  { current: null, context: false, loading: false, emptyText: undefined },
)

const emit = defineEmits<{ open: [event: EventLine] }>()

const t = usePaymentsWords()
const w = usePaymentsLines()

const columns = computed<TableColumn<EventLine>[]>(() => [
  { key: 'at', label: t('exchange.time'), width: 170 },
  { key: 'kind', label: t('exchange.event'), minWidth: 180 },
  ...(props.context
    ? [{ key: 'order', label: t('attempts.order'), width: 130, hideBelow: 720 }]
    : []),
  { key: 'status', label: t('exchange.code'), width: 90 },
  { key: 'signature', label: t('exchange.signature'), width: 150, hideBelow: 560 },
  { key: 'outcome', label: t('exchange.outcome'), width: 140, hideBelow: 460 },
  { key: 'duration', label: t('exchange.duration'), width: 100, align: 'right', hideBelow: 840 },
])

function kind(row: EventLine): string {
  return w(`exchange.kinds.${row.kind}`)
}

function direction(row: EventLine): string {
  return w(`exchange.directions.${row.direction}`)
}

function signatureTone(row: EventLine) {
  return row.signature === 'invalid' ? 'danger' : row.signature === 'valid' ? 'success' : 'default'
}
</script>

<template>
  <wx-table
    class="wx-payments-events"
    :data="events"
    :columns="columns"
    row-key="id"
    :pagination="false"
    clickable
    hover
    flush
    size="sm"
    :loading="loading"
    :empty-text="emptyText ?? t('exchange.empty')"
    :row-class="(row: EventLine) => (row.id === current ? 'is-current' : undefined)"
    @row-click="(row: EventLine) => emit('open', row)"
  >
    <template #cell-at="{ row }">
      <wx-date :value="row.at" />
    </template>
    <template #cell-kind="{ row }">
      <span class="wx-payments-events__kind">
        <span class="wx-payments-events__name">{{ kind(row) }}</span>
        <wx-text size="sm" tone="muted" class="wx-payments-events__direction">
          {{ direction(row) }}<template v-if="row.message"> · {{ row.message }}</template>
        </wx-text>
      </span>
    </template>
    <template #cell-order="{ row }">
      <span v-if="row.order_number">{{ row.order_number }}</span>
    </template>
    <template #cell-status="{ row }">
      <wx-tooltip v-if="row.transport_error" :content="row.transport_error">
        <wx-badge type="danger" size="sm">{{ t('exchange.transport-error') }}</wx-badge>
      </wx-tooltip>
      <wx-badge v-else-if="row.status !== null" :type="codeTone(row)" size="sm">
        {{ row.status }}
      </wx-badge>
    </template>
    <template #cell-signature="{ row }">
      <wx-badge v-if="row.signature" :type="signatureTone(row)" size="sm" dot>
        {{ w(`exchange.signature.${row.signature}`) }}
      </wx-badge>
    </template>
    <template #cell-outcome="{ row }">
      <wx-badge v-if="row.outcome" :type="outcomeTone(row.outcome)" size="sm">
        {{ w(`exchange.outcomes.${row.outcome}`) }}
      </wx-badge>
    </template>
    <template #cell-duration="{ row }">
      <span v-if="row.duration_ms !== null" class="wx-payments-events__number">
        {{ t('exchange.ms', { ms: row.duration_ms }) }}
      </span>
    </template>
  </wx-table>
</template>

<style scoped>
.wx-payments-events__kind {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-payments-events__name {
  font-weight: var(--wx-font-weight-medium);
}

.wx-payments-events__direction {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-payments-events__number {
  font-variant-numeric: tabular-nums;
}

.wx-payments-events :deep(tr.is-current) > td {
  background: var(--wx-bg-fill);
}
</style>

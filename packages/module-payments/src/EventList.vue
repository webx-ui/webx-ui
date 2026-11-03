<script setup lang="ts">
import { computed } from 'vue'
import { WxBadge, WxTable, WxText, WxTooltip, type TableColumn } from '@webx-ui/core'
import { useDates, WxDate } from '@webx-ui/module-admin'
import { codeTone, foldKeys, localTimes, orderLink, outcomeTone, type FoldedLine } from './format'
import { usePaymentsLines, usePaymentsWords } from './i18n'
import type { EventLine } from './types'

/**
 * Events of the exchange by time (spec §20.7): direction and kind, the code, the signature, the
 * decision and how long it took. A line is never a body: a click opens the event, where the
 * headers and bodies are — for whoever may read them.
 *
 * The widths are fixed and the event's column takes the rest, its words wrapping: the list never
 * scrolls sideways, in a dialog or on a tab. A signing key fetched for a request is folded under
 * it; a notification that named the attempt under a bad signature says so in red.
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
const dates = useDates()

const lines = computed(() => foldKeys(props.events))

const columns = computed<TableColumn<FoldedLine>[]>(() => [
  { key: 'at', label: t('exchange.time'), width: 88 },
  { key: 'kind', label: t('exchange.event') },
  ...(props.context
    ? [{ key: 'order', label: t('attempts.order'), width: 96, hideBelow: 720 }]
    : []),
  { key: 'status', label: t('exchange.code'), width: 96 },
  { key: 'signature', label: t('exchange.signature'), width: 168, hideBelow: 600 },
  { key: 'outcome', label: t('exchange.outcome'), width: 120, hideBelow: 480 },
  { key: 'duration', label: t('exchange.duration'), width: 96, align: 'right', hideBelow: 840 },
])

function kind(row: EventLine): string {
  return w(`exchange.kinds.${row.kind}`)
}

function direction(row: EventLine): string {
  return w(`exchange.directions.${row.direction}`)
}

function message(row: EventLine): string {
  return row.message ? localTimes(row.message, (value) => dates.short(value)) : ''
}

function signatureTone(row: EventLine) {
  return row.signature === 'invalid' ? 'danger' : row.signature === 'valid' ? 'success' : 'default'
}
</script>

<template>
  <wx-table
    class="wx-payments-events"
    :data="lines"
    :columns="columns"
    layout="fixed"
    row-key="id"
    :pagination="false"
    clickable
    hover
    flush
    size="sm"
    :loading="loading"
    :empty-text="emptyText ?? t('exchange.empty')"
    :row-class="(row: FoldedLine) => (row.id === current ? 'is-current' : undefined)"
    @row-click="(row: FoldedLine) => emit('open', row)"
  >
    <template #cell-at="{ row }">
      <wx-date :value="row.at" compact class="wx-payments-events__time" />
    </template>
    <template #cell-kind="{ row }">
      <span class="wx-payments-events__kind">
        <span class="wx-payments-events__name">{{ kind(row) }}</span>
        <wx-text size="sm" tone="muted" class="wx-payments-events__direction">
          {{ direction(row) }}<template v-if="row.message"> · {{ message(row) }}</template>
        </wx-text>
        <wx-badge v-if="row.claimed" type="danger" size="sm" class="wx-payments-events__claimed">
          {{ t('exchange.claimed') }}
        </wx-badge>
        <button
          v-for="key in row.keys"
          :key="key.id"
          type="button"
          class="wx-payments-events__key"
          data-test="event-key"
          @click.stop="emit('open', key)"
        >
          + {{ t('exchange.signing-key') }}
          <template v-if="key.status !== null"> · {{ key.status }}</template>
        </button>
      </span>
    </template>
    <template #cell-order="{ row }">
      <router-link
        v-if="row.order_id"
        :to="orderLink(row.order_id, row.payment_uuid)"
        class="wx-payments-events__order"
        data-test="event-order"
        @click.stop
      >
        {{ row.order_number ?? row.order_id }}
      </router-link>
    </template>
    <template #cell-status="{ row }">
      <wx-tooltip v-if="row.transport_error" :content="row.transport_error">
        <wx-badge type="danger" size="sm" class="wx-payments-events__badge">{{
          t('exchange.transport-error')
        }}</wx-badge>
      </wx-tooltip>
      <wx-badge v-else-if="row.status !== null" :type="codeTone(row)" size="sm">
        {{ row.status }}
      </wx-badge>
    </template>
    <template #cell-signature="{ row }">
      <wx-badge
        v-if="row.signature"
        :type="signatureTone(row)"
        size="sm"
        dot
        class="wx-payments-events__badge wx-payments-events__signature"
      >
        {{ w(`exchange.signature.${row.signature}`) }}
      </wx-badge>
    </template>
    <template #cell-outcome="{ row }">
      <wx-badge
        v-if="row.outcome"
        :type="outcomeTone(row.outcome)"
        size="sm"
        class="wx-payments-events__badge"
      >
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
.wx-payments-events__time {
  white-space: nowrap;
}

.wx-payments-events__kind {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-payments-events__name {
  font-weight: var(--wx-font-weight-medium);
}

/* A decision is read whole: it wraps rather than ends in «…». */
.wx-payments-events__direction {
  max-width: 100%;
  overflow-wrap: anywhere;
}

/* A badge that does not fit its column goes to a second line instead of out of the cell. */
.wx-payments-events__badge,
.wx-payments-events__claimed {
  max-width: 100%;
  height: auto;
  white-space: normal;
  text-align: start;
}

/* A signature is read whole («Not signed — status asked from the gateway»): it wraps like the
   other badges, and its dot stays by the first line. */
.wx-payments-events__signature {
  align-items: flex-start;
}

.wx-payments-events__signature :deep(.wx-badge__dot) {
  margin-block-start: calc((1lh - 6px) / 2);
}

.wx-payments-events__key {
  padding: 0;
  border: 0;
  background: none;
  color: var(--wx-text-muted);
  font: inherit;
  font-size: var(--wx-font-size-xs);
  cursor: pointer;
}

.wx-payments-events__key:hover {
  color: var(--wx-text-link);
  text-decoration: underline;
}

.wx-payments-events__order {
  color: var(--wx-text-link);
  text-decoration: none;
  white-space: nowrap;
}

.wx-payments-events__order:hover {
  text-decoration: underline;
}

.wx-payments-events__number {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.wx-payments-events :deep(tr.is-current) > td {
  background: var(--wx-bg-fill);
}
</style>

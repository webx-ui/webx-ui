<script setup lang="ts">
import { computed } from 'vue'
import {
  WxBadge,
  WxButton,
  WxCard,
  WxEmpty,
  WxText,
  WxTimeline,
  WxTimelineItem,
} from '@webx-ui/core'
import { useDates, useTranslate } from '@webx-ui/module-admin'
import type { TimelineItemType } from '@webx-ui/core'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { authorOf } from './sources'
import { toneOf } from './tones'
import type { OrderDelivery, TimelineLine } from './types'

/**
 * `wx-orders-timeline` — «History» of the card (spec §8): one timeline out of the moves, the
 * notes and the rest of the history, the milestones and the refunds, oldest first, as the server
 * merged them. Who did it is the administrator's name where one is known; otherwise the source in
 * the panel's words — «Site API», «Agent» — or as the module that wrote it named itself. A refund
 * recorded by mistake is reversed from its line (decision 98); its reversal is a line of its own.
 *
 * Under it, with the notifications module there, «Notifications»: what its channels sent about
 * the order, newest first, in that module's own words — and why a row was not sent.
 */
defineOptions({ name: 'WxOrdersTimeline' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const dates = useDates()
const card = useOrderCard()
const lines = computed(() => card?.order.value?.timeline ?? [])
const deliveries = computed(() => card?.order.value?.notifications ?? null)
const reversible = computed(() => Boolean(card?.order.value?.actions.refund_reverse))

function deliveryTone(row: OrderDelivery): 'danger' | 'success' | 'warning' | 'default' {
  if (row.status === 'failed') return 'danger'
  if (row.status === 'skipped') return 'warning'
  // Sent into a trap is not delivered: the label says so, and the colour must not contradict it.
  if (row.status === 'sent' && row.trap) return 'warning'
  if (row.status === 'sent') return 'success'

  return 'default'
}

function who(line: TimelineLine): string {
  return authorOf(t, line.source, line.admin)
}

function title(line: TimelineLine): string {
  switch (line.kind) {
    case 'status':
      return line.from
        ? t('panel.timeline-status', { from: line.from, to: line.to ?? '—' })
        : t('panel.timeline-first-status', { to: line.to ?? '—' })
    case 'milestone':
      return t('panel.timeline-milestone', { milestone: line.milestone?.label ?? '' })
    case 'refund':
      return t('panel.timeline-refund', { amount: line.amount ?? '' })
    case 'refund_reversed':
      return t('panel.timeline-refund_reversed', { amount: line.amount ?? '' })
    case 'milestone_removed':
      return t('panel.timeline-milestone_removed', { milestone: line.milestone?.label ?? '' })
    case 'block':
      return t('panel.timeline-block', { block: line.note ?? '' })
    default:
      return t(`panel.timeline-${line.kind}`)
  }
}

function tone(line: TimelineLine): TimelineItemType {
  if (line.kind === 'status') return toneOf(line.to_color)
  if (line.kind === 'payment_failed') return 'danger'
  if (line.kind === 'refund') return line.reversed ? 'default' : 'warning'
  if (line.kind === 'refund_reversed' || line.kind === 'milestone_removed') return 'warning'
  if (line.kind === 'milestone') {
    return line.milestone?.key === 'cancelled'
      ? 'danger'
      : line.milestone?.key === 'refunded'
        ? 'warning'
        : 'success'
  }

  return 'default'
}

/**
 * The reason of a cancellation and of a refund: said with its label on a plate of its own, not
 * as one more grey line under the title.
 */
function reason(line: TimelineLine): string | null {
  const cancels = line.kind === 'milestone' && line.milestone?.key === 'cancelled'

  return (cancels || line.kind === 'refund' || line.kind === 'refund_reversed') && line.note
    ? line.note
    : null
}

/** The line under the title: the note, the reference, the units — what the row has. */
function details(line: TimelineLine): string[] {
  const parts: string[] = []

  if (line.public) parts.push(t('panel.timeline-public', { status: line.public }))
  if (line.kind === 'refund' && line.reversed) parts.push(t('panel.timeline-refund-reversed'))
  if (line.kind === 'refund' && line.units) {
    parts.push(t('panel.timeline-refund-units', { count: line.units }))
  }
  if (line.reference) parts.push(t('panel.timeline-reference', { reference: line.reference }))
  // A block's line names the block in its title already; the note is that name.
  if (line.note && line.kind !== 'block' && reason(line) === null) parts.push(line.note)

  return parts
}
</script>

<template>
  <div class="wx-orders-history">
    <wx-card class="wx-orders-timeline">
      <wx-empty v-if="lines.length === 0" :title="t('panel.timeline-empty')" size="sm" />

      <wx-timeline v-else>
        <wx-timeline-item
          v-for="line in lines"
          :key="line.key"
          :title="title(line)"
          :type="tone(line)"
          :timestamp="`${dates.short(line.at)} · ${who(line)}`"
          :datetime="dates.iso(line.at)"
        >
          <wx-text
            v-for="(part, index) in details(line)"
            :key="index"
            class="wx-orders-timeline__part"
            size="sm"
            :tone="index === 0 && line.public ? undefined : 'muted'"
          >
            {{ part }}
          </wx-text>
          <p
            v-if="reason(line)"
            class="wx-orders-timeline__reason"
            :class="
              line.kind === 'refund' && line.reversed
                ? 'wx-orders-timeline__reason--muted'
                : line.kind === 'refund' || line.kind === 'refund_reversed'
                  ? 'wx-orders-timeline__reason--warning'
                  : 'wx-orders-timeline__reason--danger'
            "
            data-test="timeline-reason"
          >
            <span class="wx-orders-timeline__label">{{ t('panel.summary-reason') }}</span>
            {{ reason(line) }}
          </p>
          <wx-button
            v-if="line.kind === 'refund' && !line.reversed && line.refund_id && reversible"
            class="wx-orders-timeline__action"
            size="sm"
            variant="text"
            :disabled="card?.busy.value"
            data-test="timeline-reverse"
            @click="card?.reverseRefund(line.refund_id)"
          >
            {{ t('panel.refund-reverse') }}
          </wx-button>
        </wx-timeline-item>
      </wx-timeline>
    </wx-card>

    <wx-card
      v-if="deliveries !== null"
      class="wx-orders-deliveries"
      :title="t('panel.notifications')"
    >
      <wx-empty v-if="deliveries.length === 0" :title="t('panel.notifications-empty')" size="sm" />

      <ul v-else class="wx-orders-deliveries__list">
        <li v-for="row in deliveries" :key="row.id" class="wx-orders-deliveries__row">
          <div class="wx-orders-deliveries__text">
            <wx-text size="sm">{{ row.channel }}</wx-text>
            <wx-text v-if="row.recipient !== ''" size="sm" tone="muted">
              {{ row.recipient }}
            </wx-text>
            <wx-text v-if="row.created_at" size="sm" tone="muted">
              {{ dates.short(row.created_at) }}
            </wx-text>
            <wx-text
              v-if="row.error ?? row.reason_label"
              size="sm"
              tone="muted"
              class="wx-orders-timeline__part"
            >
              {{ row.error ?? row.reason_label }}
            </wx-text>
          </div>
          <wx-badge :type="deliveryTone(row)">{{ row.status_label }}</wx-badge>
        </li>
      </ul>
    </wx-card>
  </div>
</template>

<style scoped>
/* Two cards, one tab: the history, then what the channels sent about it. */
.wx-orders-history {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-orders-deliveries__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-orders-deliveries__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--wx-space-12);
}

.wx-orders-deliveries__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-orders-timeline__part {
  display: block;
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.wx-orders-timeline__reason {
  margin: var(--wx-space-4) 0 0;
  padding: var(--wx-space-6) var(--wx-space-10);
  border-inline-start: 3px solid var(--wx-orders-timeline-edge);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-orders-timeline-tint);
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.wx-orders-timeline__reason--danger {
  --wx-orders-timeline-edge: var(--wx-color-danger);
  --wx-orders-timeline-tint: var(--wx-color-danger-soft);
}

.wx-orders-timeline__reason--warning {
  --wx-orders-timeline-edge: var(--wx-color-warning);
  --wx-orders-timeline-tint: var(--wx-color-warning-soft);
}

.wx-orders-timeline__action {
  margin-block-start: var(--wx-space-4);
}

/* The reason of a record reversed later: it no longer stands for money given back. */
.wx-orders-timeline__reason--muted {
  --wx-orders-timeline-edge: var(--wx-border-strong);
  --wx-orders-timeline-tint: var(--wx-bg-subtle);
}

.wx-orders-timeline__label {
  color: var(--wx-text-muted);
  font-weight: var(--wx-font-weight-medium);
}
</style>

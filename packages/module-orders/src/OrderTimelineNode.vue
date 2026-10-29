<script setup lang="ts">
import { computed } from 'vue'
import { WxBadge, WxCard, WxEmpty, WxText, WxTimeline, WxTimelineItem } from '@webx-ui/core'
import { useDates, useTranslate } from '@webx-ui/module-admin'
import type { TimelineItemType } from '@webx-ui/core'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { toneOf } from './tones'
import type { OrderDelivery, TimelineLine } from './types'

/**
 * `wx-orders-timeline` — «History» of the card (spec §8): one timeline out of the moves, the
 * notes and the rest of the history, the milestones and the refunds, oldest first, as the server
 * merged them. Who did it is the administrator's name where one is known; otherwise the source in
 * the panel's words — «Site API», «Agent» — or as the module that wrote it named itself.
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

function deliveryTone(row: OrderDelivery): 'danger' | 'success' | 'warning' | 'default' {
  if (row.status === 'failed') return 'danger'
  if (row.status === 'skipped') return 'warning'
  // Sent into a trap is not delivered: the label says so, and the colour must not contradict it.
  if (row.status === 'sent' && row.trap) return 'warning'
  if (row.status === 'sent') return 'success'

  return 'default'
}

/** The sources the panel has a word for; a gateway's or a carrier's own name stays as it is. */
const KNOWN = ['panel', 'api', 'mcp', 'checkout', 'quick', 'command']

function who(line: TimelineLine): string {
  if (line.source === 'mcp') {
    return line.admin ? t('panel.source-mcp-admin', { name: line.admin }) : t('panel.source-mcp')
  }

  if (line.admin) return line.admin
  if (line.source.startsWith('status#')) return t('panel.source-status')
  if (KNOWN.includes(line.source)) return t(`panel.source-${line.source}`)

  return line.source
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
    case 'block':
      return t('panel.timeline-block', { block: line.note ?? '' })
    default:
      return t(`panel.timeline-${line.kind}`)
  }
}

function tone(line: TimelineLine): TimelineItemType {
  if (line.kind === 'status') return toneOf(line.to_color)
  if (line.kind === 'payment_failed') return 'danger'
  if (line.kind === 'refund') return 'warning'
  if (line.kind === 'milestone') {
    return line.milestone?.key === 'cancelled'
      ? 'danger'
      : line.milestone?.key === 'refunded'
        ? 'warning'
        : 'success'
  }

  return 'default'
}

/** The line under the title: the note, the reference, the units — what the row has. */
function details(line: TimelineLine): string[] {
  const parts: string[] = []

  if (line.public) parts.push(t('panel.timeline-public', { status: line.public }))
  if (line.kind === 'refund' && line.units) {
    parts.push(t('panel.timeline-refund-units', { count: line.units }))
  }
  if (line.reference) parts.push(t('panel.timeline-reference', { reference: line.reference }))
  // A block's line names the block in its title already; the note is that name.
  if (line.note && line.kind !== 'block') parts.push(line.note)

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
</style>

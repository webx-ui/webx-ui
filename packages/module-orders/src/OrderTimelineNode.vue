<script setup lang="ts">
import { computed } from 'vue'
import { WxCard, WxEmpty, WxText, WxTimeline, WxTimelineItem } from '@webx-ui/core'
import { useDates, useTranslate } from '@webx-ui/module-admin'
import type { TimelineItemType } from '@webx-ui/core'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { toneOf } from './tones'
import type { TimelineLine } from './types'

/**
 * `wx-orders-timeline` — «History» of the card (spec §8): one timeline out of the moves, the
 * notes and the rest of the history, the milestones and the refunds, oldest first, as the server
 * merged them. Who did it is the administrator's name where one is known; otherwise the source in
 * the panel's words — «Site API», «Agent» — or as the module that wrote it named itself.
 */
defineOptions({ name: 'WxOrdersTimeline' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const dates = useDates()
const card = useOrderCard()
const lines = computed(() => card?.order.value?.timeline ?? [])

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
</template>

<style scoped>
.wx-orders-timeline__part {
  display: block;
  overflow-wrap: anywhere;
  white-space: pre-line;
}
</style>

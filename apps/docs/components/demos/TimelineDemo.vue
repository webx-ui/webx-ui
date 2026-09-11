<script setup lang="ts">
import { WxBadge, WxTimeline, WxTimelineItem, type TimelineItemType } from '@webx-ui/core'

const history: { title: string; time: string; type: TimelineItemType }[] = [
  { title: 'Page created', time: '2 days ago', type: 'default' },
  { title: 'Sent for review', time: 'yesterday', type: 'warning' },
  { title: 'Published', time: '14:20 today', type: 'success' },
]
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">An order, newest entry last</span>
      <wx-timeline>
        <wx-timeline-item timestamp="12.03.2026 09:12" datetime="2026-03-12T09:12" title="Created">
          Placed from the storefront by Maria Kovalenko.
        </wx-timeline-item>
        <wx-timeline-item
          timestamp="12.03.2026 09:40"
          datetime="2026-03-12T09:40"
          title="Paid"
          type="success"
          icon="check"
        >
          Card ending 4242 · <wx-badge type="success" size="sm">₴ 1 240</wx-badge>
        </wx-timeline-item>
        <wx-timeline-item
          timestamp="13.03.2026 11:05"
          datetime="2026-03-13T11:05"
          title="Sent"
          type="primary"
          icon="upload"
        >
          Nova Poshta, parcel 59000123456789.
        </wx-timeline-item>
        <wx-timeline-item title="Delivered" type="default" hollow>
          Waiting for the courier's confirmation.
        </wx-timeline-item>
      </wx-timeline>
    </div>

    <div>
      <span class="wx-demo__label">Compact, with the time under each entry</span>
      <wx-timeline size="sm">
        <wx-timeline-item
          v-for="entry in history"
          :key="entry.title"
          :title="entry.title"
          :timestamp="entry.time"
          :type="entry.type"
          timestamp-placement="bottom"
        />
      </wx-timeline>
    </div>
  </div>
</template>

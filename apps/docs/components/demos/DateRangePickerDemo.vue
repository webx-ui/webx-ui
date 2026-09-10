<script setup lang="ts">
import { ref } from 'vue'
import { WxDateRangePicker } from '@webx-ui/core'

const period = ref<string[] | null>(['2026-03-02', '2026-04-08'])
const quarter = ref<string[] | null>(null)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Two months at once</span>
      <wx-date-range-picker v-model="period" placeholder="Pick a period" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ JSON.stringify(period) }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Three months, bounded</span>
      <wx-date-range-picker
        v-model="quarter"
        :months="3"
        min-date="2026-01-01"
        max-date="2026-12-31"
        placeholder="Pick a quarter"
      />
    </div>

    <div>
      <span class="wx-demo__label">Disabled</span>
      <wx-date-range-picker :model-value="['2026-03-02', '2026-04-08']" disabled />
    </div>
  </div>
</template>

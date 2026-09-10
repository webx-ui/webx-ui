<script setup lang="ts">
import { ref } from 'vue'
import { WxRadioGroup } from '@webx-ui/core'

const state = ref('draft')
const layout = ref('list')

const states = [
  { label: 'Draft', value: 'draft' },
  { label: 'Published', value: 'published' },
  { label: 'Archived', value: 'archived', disabled: true },
]
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Stacked</span>
      <wx-radio-group v-model="state" :options="states" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Value: {{ state }}
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Inline, small</span>
      <wx-radio-group
        v-model="layout"
        inline
        size="sm"
        :options="[
          { label: 'List', value: 'list' },
          { label: 'Grid', value: 'grid' },
          { label: 'Table', value: 'table' },
        ]"
      />
    </div>
  </div>
</template>

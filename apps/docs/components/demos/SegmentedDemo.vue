<script setup lang="ts">
import { ref } from 'vue'
import { WxSegmented } from '@webx-ui/core'

const range = ref('week')
const view = ref('list')
const size = ref('md')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Choosing one of a few</span>
      <wx-segmented
        v-model="range"
        aria-label="Range"
        :options="[
          { label: 'Day', value: 'day' },
          { label: 'Week', value: 'week' },
          { label: 'Month', value: 'month' },
          { label: 'Year', value: 'year', disabled: true },
        ]"
      />
      <span class="wx-demo__note">Chosen: {{ range }}</span>
    </div>

    <div>
      <span class="wx-demo__label">Icons, with names for anyone who cannot see them</span>
      <wx-segmented
        v-model="view"
        size="sm"
        aria-label="View"
        :options="[
          { icon: 'list', value: 'list', ariaLabel: 'List' },
          { icon: 'grid', value: 'grid', ariaLabel: 'Grid' },
          { icon: 'calendar', value: 'calendar', ariaLabel: 'Calendar' },
        ]"
      />
    </div>

    <div>
      <span class="wx-demo__label">Filling the width it is given</span>
      <wx-segmented
        v-model="size"
        block
        aria-label="Size"
        :options="[
          { label: 'Small', value: 'sm' },
          { label: 'Medium', value: 'md' },
          { label: 'Large', value: 'lg' },
        ]"
      />
    </div>
  </div>
</template>

<style scoped>
.wx-demo__note {
  display: block;
  margin-top: var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>

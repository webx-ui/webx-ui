<script setup lang="ts">
import { computed } from 'vue'
import { WxText } from '@webx-ui/core'
import { useOrdersSettings } from './settings'

/**
 * `wx-orders-number-sample` — the trial number of `orders.settings` (spec §4, §11.4): what the next
 * order would be called in the format as it is typed. The page counts it; the server checks it
 * again when the format is saved, and refuses one an order already has.
 */
defineOptions({ name: 'WxOrdersNumberSample' })

const props = defineProps<{ label?: string; help?: string }>()

const settings = useOrdersSettings()
const sample = computed(() => settings?.sample.value ?? '')
</script>

<template>
  <div v-if="settings" class="wx-orders-number-sample" aria-live="polite">
    <wx-text size="sm" tone="muted">{{ props.label }}</wx-text>
    <span class="wx-orders-number-sample__value">{{ sample }}</span>
    <wx-text v-if="props.help" size="sm" tone="muted">{{ props.help }}</wx-text>
  </div>
</template>

<style scoped>
.wx-orders-number-sample {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  padding: var(--wx-space-12);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-muted);
}

.wx-orders-number-sample__value {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-lg);
  font-weight: var(--wx-font-weight-semibold);
  color: var(--wx-text-strong);
  overflow-wrap: anywhere;
}
</style>

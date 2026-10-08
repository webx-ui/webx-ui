<script setup lang="ts">
import { ref } from 'vue'
import { WxRichText } from '@webx-ui/core'

const heading = ref('Deeply heard<span>.</span> Gently guided')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-rich-text v-model="heading" inline placeholder="Heading" aria-label="Heading" />
    <code class="wx-demo__value">{{ heading }}</code>
  </div>
</template>

<style scoped>
.wx-demo__value {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}
</style>

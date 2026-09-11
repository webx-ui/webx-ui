<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxProgress } from '@webx-ui/core'

const value = ref(38)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A bar, with the figure beside it</span>
      <div class="wx-demo__stack">
        <wx-progress :value="value" show-value aria-label="Import" />
        <wx-progress :value="value" status="success" show-value />
        <wx-progress :value="value" status="danger" size="lg" show-value />
      </div>
      <div class="controls">
        <wx-button size="sm" variant="outline" @click="value = Math.max(0, value - 10)"
          >−10</wx-button
        >
        <wx-button size="sm" variant="outline" @click="value = Math.min(100, value + 10)"
          >+10</wx-button
        >
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Counting something that is not a percentage</span>
      <wx-progress :value="3" :max="8" show-value :formatter="(v, m) => `${v} of ${m} files`" />
    </div>

    <div>
      <span class="wx-demo__label">Rings</span>
      <div class="rings">
        <wx-progress type="circle" size="sm" :value="value" show-value />
        <wx-progress type="circle" :value="value" status="success" show-value />
        <wx-progress type="circle" size="sm" indeterminate aria-label="Working" />
      </div>
    </div>

    <div>
      <span class="wx-demo__label">No end in sight</span>
      <wx-progress indeterminate aria-label="Working" />
    </div>
  </div>
</template>

<style scoped>
.controls {
  display: flex;
  gap: var(--wx-space-8);
  margin-top: var(--wx-space-10);
}

.rings {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-24);
}
</style>

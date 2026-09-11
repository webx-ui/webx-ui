<script setup lang="ts">
import { WxCol, WxRow } from '@webx-ui/core'

const spans = [12, 8, 6, 4]
</script>

<template>
  <div class="wx-demo wx-demo--stack grid-demo">
    <div>
      <span class="wx-demo__label">24 columns, split four ways</span>
      <div class="wx-demo__stack">
        <wx-row v-for="span in spans" :key="span" :gutter="12">
          <wx-col v-for="index in 24 / span" :key="index" :span="span">
            <div class="grid-demo__cell">{{ span }}</div>
          </wx-col>
        </wx-row>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Offsets</span>
      <wx-row :gutter="12">
        <wx-col :span="8"><div class="grid-demo__cell">8</div></wx-col>
        <wx-col :span="8" :offset="8"><div class="grid-demo__cell">8, offset 8</div></wx-col>
      </wx-row>
    </div>

    <div>
      <span class="wx-demo__label">
        Responsive — one column on a phone, two from 768px, four from 1024px
      </span>
      <wx-row :gutter="12">
        <wx-col v-for="index in 4" :key="index" :span="24" :md="12" :lg="6">
          <div class="grid-demo__cell">Card {{ index }}</div>
        </wx-col>
      </wx-row>
    </div>
  </div>
</template>

<style scoped>
.grid-demo__cell {
  padding: var(--wx-space-8) var(--wx-space-12);
  background: var(--wx-color-primary-soft);
  border-radius: var(--wx-radius-sm);
  color: var(--wx-color-primary);
  font-size: var(--wx-font-size-xs);
  text-align: center;
  white-space: nowrap;
}

/* The rows must be able to use the full width of the demo frame. */
.grid-demo > div {
  width: 100%;
}
</style>

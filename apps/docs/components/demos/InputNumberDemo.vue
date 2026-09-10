<script setup lang="ts">
import { ref } from 'vue'
import { WxInputNumber } from '@webx-ui/core'

const quantity = ref(3)
const price = ref(19.9)
const weight = ref<number | null>(null)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Bounded, step 1 — wheel enabled here</span>
      <wx-input-number v-model="quantity" wheel :min="0" :max="10" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Value: {{ quantity }} — click the field, then scroll over it. The button disables itself at
        the bound.
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Decimal step and precision</span>
      <wx-input-number v-model="price" :step="0.1" :precision="2" :min="0" />
    </div>

    <div>
      <span class="wx-demo__label">Controls stacked on the right</span>
      <wx-input-number v-model="weight" controls-position="right" placeholder="Weight, kg" />
    </div>

    <div>
      <span class="wx-demo__label">No controls, sizes</span>
      <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px">
        <wx-input-number :model-value="1" size="sm" :controls="false" />
        <wx-input-number :model-value="1" size="md" :controls="false" />
        <wx-input-number :model-value="1" size="lg" :controls="false" />
        <wx-input-number :model-value="1" disabled />
      </div>
    </div>
  </div>
</template>

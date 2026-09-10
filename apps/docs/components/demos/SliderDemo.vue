<script setup lang="ts">
import { ref } from 'vue'
import { WxSlider } from '@webx-ui/core'

const volume = ref(40)
const price = ref([200, 700])
const quality = ref(2)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Single</span>
      <wx-slider v-model="volume" show-value />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ volume }}</code> — a number, not an array
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Range, the shape a shop filter needs</span>
      <wx-slider v-model="price" range :min="0" :max="1000" :step="50" show-value />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ JSON.stringify(price) }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Marks</span>
      <wx-slider
        v-model="quality"
        :min="0"
        :max="4"
        :marks="{ 0: 'Draft', 2: 'Normal', 4: 'Best' }"
      />
    </div>

    <div>
      <span class="wx-demo__label">Disabled</span>
      <wx-slider :model-value="30" disabled />
    </div>
  </div>
</template>

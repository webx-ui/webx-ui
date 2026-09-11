<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxCard, WxStep, WxSteps, WxSwitch } from '@webx-ui/core'

const current = ref(1)
const failed = ref(false)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-card bordered shadow="never">
      <wx-steps
        :current="current"
        :error="failed"
        clickable
        aria-label="Checkout"
        @change="current = $event"
      >
        <wx-step title="Details" description="Who it is for" />
        <wx-step title="Delivery" description="Where it goes" />
        <wx-step title="Payment" description="How it is paid" />
        <wx-step title="Done" />
      </wx-steps>
    </wx-card>

    <div class="controls">
      <wx-button size="sm" variant="outline" :disabled="current === 0" @click="current -= 1">
        Back
      </wx-button>
      <wx-button size="sm" type="primary" :disabled="current === 3" @click="current += 1">
        Next
      </wx-button>
      <wx-switch v-model="failed" label="This step went wrong" />
    </div>

    <div>
      <span class="wx-demo__label">Down the page</span>
      <wx-card bordered shadow="never">
        <wx-steps :current="1" direction="vertical" size="sm" aria-label="Import">
          <wx-step title="Upload" description="A CSV of up to 5 MB" />
          <wx-step title="Map columns" description="Match them to fields" />
          <wx-step title="Review" description="Check a sample of rows" />
        </wx-steps>
      </wx-card>
    </div>
  </div>
</template>

<style scoped>
.controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
}
</style>

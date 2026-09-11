<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxScrollbar } from '@webx-ui/core'

const log = ref<InstanceType<typeof WxScrollbar> | null>(null)

const lines = Array.from({ length: 40 }, (_, index) => ({
  id: index + 1,
  text: `queue.worker  job #${1000 + index} finished in ${20 + ((index * 7) % 90)}ms`,
}))

const columns = Array.from({ length: 12 }, (_, index) => `Column ${index + 1}`)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A panel that scrolls on its own</span>
      <wx-scrollbar ref="log" :height="180" class="scrollbar-demo__panel">
        <p v-for="line in lines" :key="line.id" class="scrollbar-demo__line">
          {{ line.text }}
        </p>
      </wx-scrollbar>
      <div class="wx-demo__row">
        <wx-button size="sm" variant="outline" @click="log?.scrollToTop('smooth')">
          To the top
        </wx-button>
        <wx-button size="sm" variant="outline" @click="log?.scrollToBottom('smooth')">
          To the bottom
        </wx-button>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Sideways, with the bar always visible</span>
      <wx-scrollbar axis="x" always class="scrollbar-demo__panel">
        <div class="scrollbar-demo__row">
          <span v-for="column in columns" :key="column" class="scrollbar-demo__cell">
            {{ column }}
          </span>
        </div>
      </wx-scrollbar>
    </div>
  </div>
</template>

<style scoped>
.scrollbar-demo__panel {
  width: 100%;
  padding: var(--wx-space-8);
  background: var(--wx-bg-body);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
}

.scrollbar-demo__line {
  margin: 0;
  padding: 2px 0;
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
}

.scrollbar-demo__row {
  display: flex;
  gap: var(--wx-space-8);
}

.scrollbar-demo__cell {
  padding: var(--wx-space-6) var(--wx-space-12);
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-sm);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
}
</style>

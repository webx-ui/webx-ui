<script setup lang="ts">
import { ref } from 'vue'
import { WxAffix, WxBacktop, WxButton, WxCard } from '@webx-ui/core'

const stuck = ref(false)
const rows = Array.from({ length: 14 }, (_, index) => index + 1)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <span class="wx-demo__label">Scroll the box: the toolbar sticks, and says so</span>

    <div id="affix-demo-scroller" class="scroller">
      <wx-affix :offset="0" @change="stuck = $event">
        <div class="toolbar" :class="{ 'is-stuck': stuck }">
          <strong>14 orders</strong>
          <wx-button size="sm" variant="outline">Export</wx-button>
        </div>
      </wx-affix>

      <wx-card v-for="row in rows" :key="row" bordered shadow="never" class="row">
        Order WX-41{{ String(row).padStart(2, '0') }}
      </wx-card>

      <wx-backtop target="#affix-demo-scroller" :visibility-height="120" :bottom="16" :right="16" />
    </div>

    <span class="wx-demo__note">Stuck: {{ stuck }}</span>
  </div>
</template>

<style scoped>
.scroller {
  position: relative;
  height: 280px;
  overflow-y: auto;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  background: var(--wx-bg-surface);
  border-bottom: 1px solid transparent;
  transition: box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.toolbar.is-stuck {
  border-bottom-color: var(--wx-border-default);
  box-shadow: var(--wx-shadow-card);
}

.row {
  margin: var(--wx-space-8) var(--wx-space-12);
}
</style>

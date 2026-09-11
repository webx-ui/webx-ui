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
        Responsive — drag the bottom-right corner and watch the row, not the window
      </span>
      <div class="grid-demo__scroller">
        <div class="grid-demo__resizable">
          <wx-row :gutter="12">
            <wx-col v-for="index in 4" :key="index" :span="24" :sm="12" :lg="6">
              <div class="grid-demo__cell">Card {{ index }}</div>
            </wx-col>
          </wx-row>
        </div>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">The same markup in a 300px column</span>
      <div class="grid-demo__narrow">
        <wx-row :gutter="12">
          <wx-col v-for="index in 4" :key="index" :span="24" :sm="12" :lg="6">
            <div class="grid-demo__cell">Card {{ index }}</div>
          </wx-col>
        </wx-row>
      </div>
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

/*
 * The reader drags the box; the columns answer to its width, not the window's. It is
 * allowed to grow past the docs column — the scroller takes the overflow, so the
 * 1024px breakpoint can be reached on a page that is narrower than that.
 */
.grid-demo__scroller {
  width: 100%;
  overflow-x: auto;
}

.grid-demo__resizable {
  width: 100%;
  min-width: 200px;
  max-width: none;
  padding: var(--wx-space-8);
  border: 1px dashed var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  overflow: hidden;
  resize: horizontal;
}

/* A stand-in for a drawer or a narrow panel: same markup, less room. */
.grid-demo__narrow {
  width: 300px;
  max-width: 100%;
  padding: var(--wx-space-8);
  border: 1px dashed var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
}
</style>

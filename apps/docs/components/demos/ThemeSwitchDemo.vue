<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { applyTheme, type ThemePreference } from '@webx-ui/tokens'
import { WxButton, WxInput, WxText, WxThemeSwitch } from '@webx-ui/core'

/**
 * The switch drives one card rather than the page: `data-theme` works on any element, so the
 * documentation can stay the colour it was while the preview beside it changes — and `system`
 * really does mean "take it from above", which here is this page.
 */
const preference = ref<ThemePreference>('system')
const preview = ref<HTMLElement | null>(null)

watch(preference, (value) => {
  if (preview.value !== null) {
    applyTheme(value, preview.value)
  }
})

onMounted(() => {
  if (preview.value !== null) {
    applyTheme(preference.value, preview.value)
  }
})
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Light, the machine's, dark</span>
      <wx-theme-switch v-model="preference" />
      <span class="wx-demo__note">Chosen: {{ preference }}</span>
    </div>

    <div ref="preview" class="preview">
      <wx-text size="sm" tone="muted">A corner of a panel</wx-text>
      <wx-input placeholder="Search" />
      <div class="preview__buttons">
        <wx-button type="primary">Save</wx-button>
        <wx-button variant="outline">Cancel</wx-button>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Filling the width it is given</span>
      <wx-theme-switch v-model="preference" size="sm" block />
    </div>
  </div>
</template>

<style scoped>
.preview {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  padding: var(--wx-space-16);
  background: var(--wx-bg-body);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-lg);
}

.preview__buttons {
  display: flex;
  gap: var(--wx-space-8);
}

.wx-demo__note {
  display: block;
  margin-top: var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>

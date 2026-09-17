<script setup lang="ts">
import { ref } from 'vue'
import { WxActionBar, WxBadge, WxButton, WxFormItem, WxInput, WxText } from '@webx-ui/core'

const fields = ['Title', 'Slug', 'Summary', 'Author', 'Keywords', 'Canonical URL', 'Note']

const saved = ref(true)
const saving = ref(false)

function save() {
  saving.value = true
  setTimeout(() => {
    saving.value = false
    saved.value = true
  }, 600)
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <!-- The scrolling box stands in for the page: the bar sticks to the bottom of whatever
         scrolls it, and settles into its place in the flow at the end. -->
    <div class="bar-demo">
      <div class="bar-demo__screen">
        <wx-form-item v-for="field in fields" :key="field" :label="field">
          <wx-input :placeholder="field" @update:model-value="saved = false" />
        </wx-form-item>

        <wx-action-bar>
          <template #state>
            <wx-badge :type="saved ? 'success' : 'warning'" dot>
              {{ saved ? 'Saved' : 'Not saved yet' }}
            </wx-badge>
          </template>

          <wx-button variant="outline">Discard</wx-button>
          <wx-button type="primary" :loading="saving" @click="save">Save</wx-button>
        </wx-action-bar>
      </div>
    </div>

    <span class="wx-demo__label">
      <wx-text size="sm" tone="muted">Scroll the box: the bar stays, the head does not.</wx-text>
    </span>
  </div>
</template>

<style scoped>
.bar-demo {
  width: 100%;
  height: 280px;
  overflow: auto;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-body);
}

.bar-demo__screen {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  /* The same air a shell keeps around its column, and what the bar stops short of. */
  --wx-action-bar-bottom: var(--wx-space-12);

  padding: var(--wx-space-12);
}
</style>

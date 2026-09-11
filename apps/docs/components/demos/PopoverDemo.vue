<script setup lang="ts">
import { ref, watch } from 'vue'
import { WxButton, WxForm, WxFormItem, WxIcon, WxInput, WxPopover, WxSelect } from '@webx-ui/core'

const label = ref('SEO')
const draft = ref(label.value)
const icon = ref<string | number | null>('search')
const open = ref(false)

const icons = [
  { label: 'Search', value: 'search' },
  { label: 'Edit', value: 'edit' },
  { label: 'Lock', value: 'lock' },
]

/* The trigger opens the panel by itself; the draft is taken when it does. */
watch(open, (value) => {
  if (value) draft.value = label.value
})

function save() {
  label.value = draft.value
  open.value = false
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A form in a panel · the tab is called «{{ label }}»</span>
      <wx-popover v-model:open="open" title="Tab" :width="260" closable side="bottom" align="start">
        <template #trigger>
          <wx-button size="sm">
            <template #icon><wx-icon name="edit" /></template>
            Rename
          </wx-button>
        </template>

        <wx-form gap="sm">
          <wx-form-item label="Name">
            <wx-input v-model="draft" size="sm" placeholder="Tab name" />
          </wx-form-item>
          <wx-form-item label="Icon">
            <wx-select v-model="icon" :options="icons" size="sm" clearable />
          </wx-form-item>
        </wx-form>

        <template #footer="{ close }">
          <wx-button size="sm" @click="close">Cancel</wx-button>
          <wx-button size="sm" type="primary" @click="save">Save</wx-button>
        </template>
      </wx-popover>
    </div>

    <div>
      <span class="wx-demo__label">Plain text, on either side</span>
      <div class="wx-demo__row">
        <wx-popover side="top" title="Why this matters">
          <template #trigger><wx-button size="sm">Above</wx-button></template>
          A description under 160 characters is what search engines show. Longer than that and they
          cut it off mid-sentence.
        </wx-popover>

        <wx-popover side="right" align="start">
          <template #trigger><wx-button size="sm">To the right</wx-button></template>
          No heading, no arrow to press — Escape or a click outside closes it.
        </wx-popover>

        <wx-popover side="bottom" :arrow="false" title="No pointer">
          <template #trigger><wx-button size="sm">Without the arrow</wx-button></template>
          A panel that does not point at anything, for when it is wide.
        </wx-popover>
      </div>
    </div>
  </div>
</template>

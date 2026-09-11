<script setup lang="ts">
import { ref } from 'vue'
import {
  WxBadge,
  WxButton,
  WxDialog,
  WxForm,
  WxFormItem,
  WxIcon,
  WxInput,
  WxTextarea,
} from '@webx-ui/core'

const title = ref('About the company')
const draft = ref(title.value)
const description = ref('')

const settingsOpen = ref(false)
const section = ref('general')

const sections = [
  { key: 'general', label: 'General', icon: 'settings' },
  { key: 'seo', label: 'Search', icon: 'search' },
  { key: 'access', label: 'Access', icon: 'lock' },
]

function save(close: () => void) {
  title.value = draft.value
  close()
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A form in a dialog · the page is called «{{ title }}»</span>
      <wx-dialog title="Edit page" :width="520" @open="draft = title">
        <template #trigger>
          <wx-button type="primary">
            <template #icon><wx-icon name="edit" /></template>
            Edit
          </wx-button>
        </template>

        <template #extra>
          <wx-badge type="warning">Draft</wx-badge>
        </template>

        <wx-form>
          <wx-form-item label="Title">
            <wx-input v-model="draft" placeholder="Page title" />
          </wx-form-item>
          <wx-form-item label="Description" help="Shown in search results.">
            <wx-textarea v-model="description" :rows="3" />
          </wx-form-item>
        </wx-form>

        <template #footer="{ close }">
          <wx-button @click="close">Cancel</wx-button>
          <wx-button type="primary" @click="save(close)">Save</wx-button>
        </template>
      </wx-dialog>
    </div>

    <div>
      <span class="wx-demo__label">Dragged by the heading, resized by the corner, remembered</span>
      <wx-dialog title="Media" :width="560" :height="360" draggable resizable persist="docs-media">
        <template #trigger>
          <wx-button>Open a movable dialog</wx-button>
        </template>

        Move it by the heading, pull the corner in the bottom right, then close and open it again —
        it comes back where it was left. That is `persist` writing the size and the position to
        `localStorage`.

        <template #footer="{ close }">
          <wx-button @click="close">Close</wx-button>
        </template>
      </wx-dialog>
    </div>

    <div>
      <span class="wx-demo__label">A sidebar beside the body · width in per cent</span>
      <wx-button @click="settingsOpen = true">Settings</wx-button>

      <wx-dialog v-model:open="settingsOpen" title="Settings" width="70%" height="60%">
        <template #sidebar>
          <nav class="demo-sections">
            <button
              v-for="item in sections"
              :key="item.key"
              class="demo-sections__item"
              :class="{ 'demo-sections__item--active': section === item.key }"
              @click="section = item.key"
            >
              <wx-icon :name="item.icon" />
              {{ item.label }}
            </button>
          </nav>
        </template>

        <p>
          The <code>sidebar</code> slot is what splits the body in two. Each column scrolls on its
          own, and on a narrow screen they stack.
        </p>
        <p>
          Section: <strong>{{ section }}</strong>
        </p>

        <template #footer="{ close }">
          <wx-button @click="close">Cancel</wx-button>
          <wx-button type="primary" @click="close">Save</wx-button>
        </template>
      </wx-dialog>
    </div>
  </div>
</template>

<style scoped>
.demo-sections {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}

.demo-sections__item {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8) var(--wx-space-10);
  background: none;
  border: none;
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.demo-sections__item:hover {
  background: var(--wx-bg-fill);
}

.demo-sections__item--active {
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
}
</style>

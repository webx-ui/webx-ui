<script setup lang="ts">
import { ref } from 'vue'
import {
  WxBadge,
  WxButton,
  WxDrawer,
  WxForm,
  WxFormItem,
  WxIcon,
  WxInput,
  WxSelect,
  WxTextarea,
} from '@webx-ui/core'
import type { DrawerSide } from '@webx-ui/core'

const sides: DrawerSide[] = ['right', 'left', 'top', 'bottom']
const side = ref<DrawerSide>('right')
const sideOpen = ref(false)

const name = ref('Acme Ltd')
const note = ref('')
const status = ref<string | number | null>('paid')

const statuses = [
  { label: 'Paid', value: 'paid' },
  { label: 'Pending', value: 'pending' },
  { label: 'Refunded', value: 'refunded' },
]

const tab = ref('details')
const tabs = [
  { key: 'details', label: 'Details', icon: 'file' },
  { key: 'history', label: 'History', icon: 'clock' },
  { key: 'files', label: 'Files', icon: 'folder' },
]
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A record beside the table it came from</span>
      <wx-drawer title="Order #1043" :size="420">
        <template #trigger>
          <wx-button type="primary">
            <template #icon><wx-icon name="file" /></template>
            Open the order
          </wx-button>
        </template>

        <template #extra>
          <wx-badge type="success">Paid</wx-badge>
        </template>

        <wx-form>
          <wx-form-item label="Customer">
            <wx-input v-model="name" />
          </wx-form-item>
          <wx-form-item label="Status">
            <wx-select v-model="status" :options="statuses" />
          </wx-form-item>
          <wx-form-item label="Note">
            <wx-textarea v-model="note" :rows="6" placeholder="Anything worth remembering" />
          </wx-form-item>
        </wx-form>

        <template #footer="{ close }">
          <wx-button @click="close">Cancel</wx-button>
          <wx-button type="primary" @click="close">Save</wx-button>
        </template>
      </wx-drawer>
    </div>

    <div>
      <span class="wx-demo__label">From any edge</span>
      <div class="wx-demo__row">
        <wx-button v-for="item in sides" :key="item" @click="((side = item), (sideOpen = true))">
          {{ item }}
        </wx-button>
      </div>

      <wx-drawer v-model:open="sideOpen" :side="side" :title="`From the ${side}`" size="40%">
        The panel slides in from the edge it was given and takes 40% of the screen along that axis.
        On a phone it covers the screen whichever edge it came from.

        <template #footer="{ close }">
          <wx-button @click="close">Close</wx-button>
        </template>
      </wx-drawer>
    </div>

    <div>
      <span class="wx-demo__label">Resizable and remembered, with a sidebar</span>
      <wx-drawer title="Customer" :size="560" resizable persist="docs-customer">
        <template #trigger>
          <wx-button>Open a resizable panel</wx-button>
        </template>

        <template #extra>
          <wx-badge>12 orders</wx-badge>
        </template>

        <template #sidebar>
          <nav class="demo-tabs">
            <button
              v-for="item in tabs"
              :key="item.key"
              class="demo-tabs__item"
              :class="{ 'demo-tabs__item--active': tab === item.key }"
              @click="tab = item.key"
            >
              <wx-icon :name="item.icon" />
              {{ item.label }}
            </button>
          </nav>
        </template>

        <p>
          Drag the left edge of the panel, or focus it with <kbd>Tab</kbd> and use the arrow keys.
          The width is written to <code>localStorage</code> under the <code>persist</code> key and
          is there again the next time it opens.
        </p>
        <p>
          Tab: <strong>{{ tab }}</strong>
        </p>

        <template #footer="{ close }">
          <wx-button @click="close">Close</wx-button>
        </template>
      </wx-drawer>
    </div>
  </div>
</template>

<style scoped>
.demo-tabs {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}

.demo-tabs__item {
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

.demo-tabs__item:hover {
  background: var(--wx-bg-fill);
}

.demo-tabs__item--active {
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
}
</style>

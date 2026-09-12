<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxFileCard, WxSelectionArea, vWxSelect } from '@webx-ui/core'

const names = [
  'engine.png',
  'hydraulics.png',
  'transmission.png',
  'undercarriage.png',
  'electrical.png',
  'cabin.png',
  'filters.pdf',
  'fasteners.xlsx',
  'attachments.zip',
  'tyres.png',
  'accessories.docx',
  'bucket.png',
]

const files = names.map((name, index) => ({
  id: index + 1,
  name,
  thumbnail: name.endsWith('.png')
    ? `https://picsum.photos/seed/wx-sel-${index}/220/165`
    : undefined,
}))

const picked = ref<number[]>([2, 3])

/* One at a time: the model is still an array, and never holds more than one value. */
const one = ref<number[]>([])

const rows = ref<string[]>([])

const orders = [
  { id: 'WX-4100', customer: 'Ada Lovelace', total: '€1,240' },
  { id: 'WX-4101', customer: 'Grace Hopper', total: '€380' },
  { id: 'WX-4102', customer: 'Alan Turing', total: '€2,905' },
  { id: 'WX-4103', customer: 'Katherine Johnson', total: '€145' },
  { id: 'WX-4104', customer: 'Margaret Hamilton', total: '€760' },
]
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Drag across the tiles</span>

      <div class="bar">
        <span class="count">{{ picked.length }} of {{ files.length }} selected</span>
        <wx-button size="sm" variant="text" @click="picked = files.map((file) => file.id)">
          Select all
        </wx-button>
        <wx-button size="sm" variant="text" :disabled="!picked.length" @click="picked = []">
          Clear
        </wx-button>
      </div>

      <wx-selection-area v-slot="{ isSelected }" v-model="picked" class="grid">
        <wx-file-card
          v-for="file in files"
          :key="file.id"
          v-wx-select="file.id"
          :name="file.name"
          :thumbnail="file.thumbnail"
          :selected="isSelected(file.id)"
          removable
          copyable
          url="https://files.example/x"
        />
      </wx-selection-area>

      <span class="wx-demo__note">
        Shift or ctrl to add to the selection, alt to take away. Click a card to pick it on its own,
        the background to clear. The tiles are
        <a href="/components/file-card">FileCards</a>, and their buttons are buttons — so a drag
        that starts on one is the button's, not the box's.
      </span>
    </div>

    <div>
      <span class="wx-demo__label">One at a time — a file picker rather than a gallery</span>

      <wx-selection-area v-slot="{ isSelected }" v-model="one" :multiple="false" class="grid">
        <wx-file-card
          v-for="file in files.slice(0, 4)"
          :key="file.id"
          v-wx-select="file.id"
          :name="file.name"
          :thumbnail="file.thumbnail"
          :selected="isSelected(file.id)"
        />
      </wx-selection-area>

      <span class="wx-demo__note">
        No box, no run, no toggle: a click or a tap picks the one under it, the background clears.
        Chosen: {{ files.find((file) => file.id === one[0])?.name ?? 'nothing' }}
      </span>
    </div>

    <div>
      <span class="wx-demo__label">And down a list, where the box only has to touch a row</span>

      <wx-selection-area v-slot="{ isSelected }" v-model="rows" class="rows">
        <div
          v-for="order in orders"
          :key="order.id"
          v-wx-select="order.id"
          class="row"
          :class="{ 'is-selected': isSelected(order.id) }"
        >
          <span class="id">{{ order.id }}</span>
          <span class="customer">{{ order.customer }}</span>
          <span class="total">{{ order.total }}</span>
        </div>
      </wx-selection-area>

      <span class="wx-demo__note">Selected: {{ rows.join(', ') || 'nothing' }}</span>
    </div>
  </div>
</template>

<style scoped>
.bar {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  margin-bottom: var(--wx-space-8);
}

.count {
  flex: 1 1 auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: var(--wx-space-12);
  padding: var(--wx-space-12);
  background: var(--wx-bg-body);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
}

.rows {
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
  overflow: hidden;
}

.row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  font-size: var(--wx-font-size-sm);
}

.row + .row {
  border-top: 1px solid var(--wx-border-muted);
}

.row.is-selected {
  background: var(--wx-color-primary-soft);
}

.id {
  font-family: var(--wx-font-family-mono);
  color: var(--wx-text-muted);
}

.customer {
  flex: 1 1 auto;
}

.total {
  font-variant-numeric: tabular-nums;
}
</style>

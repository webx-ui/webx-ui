<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxIcon, WxSelectionArea, vWxSelect } from '@webx-ui/core'

const files = [
  { id: 1, name: 'engine.png' },
  { id: 2, name: 'hydraulics.png' },
  { id: 3, name: 'transmission.png' },
  { id: 4, name: 'undercarriage.png' },
  { id: 5, name: 'electrical.png' },
  { id: 6, name: 'cabin.png' },
  { id: 7, name: 'filters.png' },
  { id: 8, name: 'fasteners.png' },
  { id: 9, name: 'attachments.png' },
  { id: 10, name: 'tyres.png' },
  { id: 11, name: 'accessories.png' },
  { id: 12, name: 'bucket.png' },
]

const picked = ref<number[]>([2, 3])

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
        <figure
          v-for="file in files"
          :key="file.id"
          v-wx-select="file.id"
          class="tile"
          :class="{ 'is-selected': isSelected(file.id) }"
        >
          <span class="thumb">
            <wx-icon name="image" />
            <button class="drop" type="button" aria-label="Delete">
              <wx-icon name="trash" />
            </button>
          </span>
          <figcaption class="name">{{ file.name }}</figcaption>
        </figure>
      </wx-selection-area>

      <span class="wx-demo__note">
        Shift or ctrl to add to the selection, alt to take away. Click a tile to pick it on its own,
        the background to clear. The bin is a button, so a drag that starts on it is the button's.
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

.tile {
  margin: 0;
  padding: var(--wx-space-8);
  border: 1px solid transparent;
  border-radius: var(--wx-radius-md);
}

.tile.is-selected {
  background: var(--wx-color-primary-soft);
  border-color: color-mix(in srgb, var(--wx-color-primary) 40%, transparent);
}

.thumb {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 4 / 3;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-sm);
  color: var(--wx-text-placeholder);
  font-size: 24px;
}

.drop {
  position: absolute;
  top: 4px;
  right: 4px;
  display: none;
  padding: 2px;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-xs);
  color: var(--wx-color-danger);
  font-size: 14px;
  cursor: pointer;
}

.tile:hover .drop {
  display: block;
}

.name {
  margin-top: var(--wx-space-6);
  overflow: hidden;
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
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

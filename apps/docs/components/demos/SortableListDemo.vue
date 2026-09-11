<script setup lang="ts">
import { ref } from 'vue'
import {
  WxAction,
  WxActions,
  WxButton,
  WxEntityCard,
  WxSortableList,
  type SortableMove,
} from '@webx-ui/core'

interface Product {
  id: number
  title: string
  sku: string
}

const products = ref<Product[]>([
  { id: 80633, title: 'Alternator Belt', sku: '7100104' },
  { id: 80636, title: 'Drive Pump Belt', sku: '6736775' },
  { id: 80637, title: 'Water Coolant Tank Cap', sku: '6733429' },
  { id: 80638, title: 'Hydraulic Oil Non-Vented Cap', sku: '6728149' },
])

const last = ref('')

function remove(id: number) {
  products.value = products.value.filter((product) => product.id !== id)
}

function onMove(move: SortableMove<Product>) {
  last.value = `${move.item.title}: ${move.from + 1} → ${move.to + 1} (${move.via})`
}

const blocks = ref(['Hero banner', 'Featured categories', 'New arrivals', 'Reviews'])
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A heading, a row per record, and what each row can do</span>

      <wx-sortable-list v-model="products" title="Pick the products" @move="onMove">
        <template #extra>
          <wx-button size="sm" variant="outline">Find</wx-button>
        </template>

        <template #default="{ item }">
          <wx-entity-card
            variant="plain"
            size="sm"
            :title="`${item.title}, ${item.sku}`"
            :meta="[
              { label: 'SKU', text: item.sku },
              { label: 'ID', text: String(item.id) },
            ]"
          />
        </template>

        <template #actions="{ item }">
          <wx-actions size="sm">
            <wx-action type="remove" @click="remove(item.id)" />
          </wx-actions>
        </template>

        <template #empty>Nothing picked yet — press Find.</template>
      </wx-sortable-list>

      <span class="wx-demo__note">
        {{ last || 'Drag a row by its grip, or tab to one and press space.' }}
      </span>
    </div>

    <div>
      <span class="wx-demo__label">Anywhere on the row, when there is nothing else on it</span>

      <wx-sortable-list v-model="blocks" handle="row" size="sm" aria-label="Blocks on the page" />

      <span class="wx-demo__note">Order: {{ blocks.join(' → ') }}</span>
    </div>
  </div>
</template>

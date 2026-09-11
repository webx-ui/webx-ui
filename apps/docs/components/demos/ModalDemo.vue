<script setup lang="ts">
import { ref } from 'vue'
import { WxAction, WxActions, WxButton, WxSortableList, confirm, createModal } from '@webx-ui/core'
import ProductBrowser, { type Product } from './ProductBrowser.vue'

/* The component, wrapped once in a function that opens it. */
const browseProducts = createModal<Product, { exclude?: number[] }>(ProductBrowser, {
  resolveOn: 'select',
})

const picked = ref<Product[]>([
  { id: 80633, title: 'Alternator Belt', sku: '7100104' },
  { id: 80636, title: 'Drive Pump Belt', sku: '6736775' },
])

const answer = ref('')

async function add() {
  const product = await browseProducts({ exclude: picked.value.map((item) => item.id) })
  if (product) picked.value = [...picked.value, product]
}

async function remove(product: Product) {
  const sure = await confirm({
    title: 'Remove this product?',
    message: `${product.title} will be taken off the list.`,
    confirmText: 'Remove',
    tone: 'danger',
  })

  if (sure) picked.value = picked.value.filter((item) => item.id !== product.id)
}

async function ask() {
  answer.value = (await confirm('Publish these changes?')) ? 'Confirmed' : 'Cancelled'
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A question, awaited</span>

      <div class="row">
        <wx-button @click="ask">Publish…</wx-button>
        <span class="wx-demo__note">{{ answer || 'No answer yet' }}</span>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">A browser of your own, opened the same way</span>

      <wx-sortable-list v-model="picked" title="Pick the products">
        <template #extra>
          <wx-button size="sm" variant="outline" @click="add">Find</wx-button>
        </template>

        <template #default="{ item }">
          <span class="name">{{ item.title }}</span>
          <span class="sku">SKU {{ item.sku }}</span>
        </template>

        <template #actions="{ item }">
          <wx-actions size="sm">
            <wx-action type="remove" @click="remove(item)" />
          </wx-actions>
        </template>

        <template #empty>Nothing picked yet — press Find.</template>
      </wx-sortable-list>
    </div>
  </div>
</template>

<style scoped>
.row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
}

.row .wx-demo__note {
  margin: 0;
}

.name {
  margin-right: var(--wx-space-8);
  font-weight: var(--wx-font-weight-medium);
}

.sku {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxDialog, WxEmpty, WxInput, useModal } from '@webx-ui/core'

export interface Product {
  id: number
  title: string
  sku: string
}

const props = withDefaults(defineProps<{ exclude?: number[] }>(), { exclude: () => [] })

const emit = defineEmits<{ select: [product: Product] }>()

/*
 * The component says nothing about how it was opened. It emits `select`, which is what it
 * would emit sitting in a page; the function that opens it turns that into the answer.
 */
const catalogue: Product[] = [
  { id: 80633, title: 'Alternator Belt', sku: '7100104' },
  { id: 80636, title: 'Drive Pump Belt', sku: '6736775' },
  { id: 80637, title: 'Water Coolant Tank Cap', sku: '6733429' },
  { id: 80638, title: 'Hydraulic Oil Non-Vented Cap', sku: '6728149' },
  { id: 80641, title: 'Undercarriage Roller', sku: '6689871' },
  { id: 80644, title: 'Cabin Air Filter', sku: '6666375' },
]

const { open } = useModal()

const query = ref('')

const found = computed(() =>
  catalogue.filter(
    (product) =>
      !props.exclude.includes(product.id) &&
      `${product.title} ${product.sku}`.toLowerCase().includes(query.value.trim().toLowerCase()),
  ),
)
</script>

<template>
  <wx-dialog v-model:open="open" title="Find a product" :width="520" closable>
    <wx-input v-model="query" placeholder="Title or SKU" clearable />

    <ul v-if="found.length" class="results">
      <li v-for="product in found" :key="product.id">
        <button type="button" class="result" @click="emit('select', product)">
          <span class="title">{{ product.title }}</span>
          <span class="sku">SKU {{ product.sku }}</span>
        </button>
      </li>
    </ul>

    <wx-empty v-else title="Nothing found" description="Try a shorter word." />
  </wx-dialog>
</template>

<style scoped>
.results {
  margin: var(--wx-space-12) 0 0;
  padding: 0;
  list-style: none;
}

.result {
  display: flex;
  align-items: baseline;
  gap: var(--wx-space-12);
  width: 100%;
  padding: var(--wx-space-8) var(--wx-space-10);
  background: none;
  border: none;
  border-radius: var(--wx-radius-sm);
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.result:hover {
  background: var(--wx-bg-fill);
}

.title {
  flex: 1 1 auto;
  font-weight: var(--wx-font-weight-medium);
}

.sku {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>

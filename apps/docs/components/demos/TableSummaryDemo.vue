<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxTable } from '@webx-ui/core'
import type { RowKey, TableColumn, TableSummaryRow } from '@webx-ui/core'

interface Item extends Record<string, unknown> {
  id: number
  sku: string
  name: string
  qty: number
  price: number
  discount: number
  note: string
}

const items: Item[] = [
  {
    id: 1,
    sku: 'KB-104',
    name: 'Mechanical keyboard',
    qty: 2,
    price: 7900,
    discount: 500,
    note: 'Brown switches, ISO layout. Ships from the Riga warehouse.',
  },
  {
    id: 2,
    sku: 'MS-220',
    name: 'Wireless mouse',
    qty: 1,
    price: 3200,
    discount: 0,
    note: 'Bundled with the keyboard order, same shipment.',
  },
  {
    id: 3,
    sku: 'CB-3M',
    name: 'USB-C cable, 3 m',
    qty: 4,
    price: 690,
    discount: 120,
    note: 'Backordered until the 12th.',
  },
]

const expanded = ref<RowKey[]>([1])

const money = (value: number) => `${(value / 100).toFixed(2)} €`

const columns: TableColumn<Item>[] = [
  { key: 'sku', label: 'SKU', width: 100 },
  { key: 'name', label: 'Item', minWidth: 180 },
  { key: 'qty', label: 'Qty', width: 70, align: 'right' },
  { key: 'price', label: 'Price', width: 110, align: 'right', formatter: (v) => money(Number(v)) },
  {
    key: 'line',
    label: 'Line total',
    width: 120,
    align: 'right',
    formatter: (_value, row) => money(row.qty * row.price - row.discount),
  },
]

const summary = computed<TableSummaryRow[]>(() => {
  const sum = items.reduce((total, item) => total + item.qty * item.price, 0)
  const discount = items.reduce((total, item) => total + item.discount, 0)

  return [
    { label: 'Sum', cells: { line: money(sum) } },
    { label: 'Discount', cells: { line: `−${money(discount)}` } },
    { label: 'Total', cells: { line: money(sum - discount) }, strong: true },
  ]
})
</script>

<template>
  <div class="wx-demo">
    <wx-table
      v-model:expanded="expanded"
      title="Order #1042"
      :data="items"
      :columns="columns"
      :summary="summary"
      expandable
      row-key="id"
      bordered
      aria-label="Order items"
    >
      <template #expanded="{ row }">
        <p class="demo-detail">{{ row.note }}</p>
      </template>
    </wx-table>
  </div>
</template>

<style scoped>
.demo-detail {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: 13px;
}
</style>

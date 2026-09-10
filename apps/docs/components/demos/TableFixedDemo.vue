<script setup lang="ts">
import { WxTable } from '@webx-ui/core'
import type { TableColumn, TableSummaryRow } from '@webx-ui/core'

interface Shipment extends Record<string, unknown> {
  id: number
  date: string
  name: string
  state: string
  city: string
  address: string
  zip: string
  courier: string
  weight: string
}

const shipments: Shipment[] = Array.from({ length: 14 }, (_, index) => ({
  id: index + 1,
  date: `2026-05-${String(index + 1).padStart(2, '0')}`,
  name: ['Tom', 'Ada', 'Grace', 'Alan'][index % 4],
  state: 'California',
  city: 'Los Angeles',
  address: 'No. 189, Grove St, Los Angeles',
  zip: 'CA 90036',
  courier: ['DHL', 'UPS', 'FedEx'][index % 3],
  weight: `${(1 + index * 0.4).toFixed(1)} kg`,
}))

const columns: TableColumn<Shipment>[] = [
  { key: 'date', label: 'Date', width: 130, fixed: 'left', sortable: true },
  { key: 'name', label: 'Name', width: 110 },
  { key: 'state', label: 'State', width: 130 },
  { key: 'city', label: 'City', width: 140 },
  { key: 'address', label: 'Address', width: 260 },
  { key: 'zip', label: 'Zip', width: 110 },
  { key: 'courier', label: 'Courier', width: 110 },
  { key: 'weight', label: 'Weight', width: 110, align: 'right' },
  { key: 'actions', label: '', width: 100, fixed: 'right' },
]

const summary: TableSummaryRow[] = [
  { label: 'Shipments', cells: { weight: `${shipments.length}` }, strong: true },
]
</script>

<template>
  <div class="wx-demo">
    <wx-table
      :data="shipments"
      :columns="columns"
      :summary="summary"
      :max-height="300"
      row-key="id"
      layout="fixed"
      aria-label="Shipments"
    >
      <template #cell-actions>
        <button class="demo-link" type="button">Remove</button>
      </template>
    </wx-table>
  </div>
</template>

<style scoped>
.demo-link {
  padding: 0;
  background: none;
  border: none;
  color: var(--wx-text-link);
  font: inherit;
  cursor: pointer;
}
</style>

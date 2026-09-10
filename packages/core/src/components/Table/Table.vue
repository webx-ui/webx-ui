<script setup lang="ts" generic="T extends TableRow = TableRow">
import { computed } from 'vue'
import WxCheckbox from '../Checkbox/Checkbox.vue'
import type { RowKey, TableColumn, TableEmits, TableProps, TableRow, TableSort } from './types'

defineOptions({ name: 'WxTable' })

const props = withDefaults(defineProps<TableProps<T>>(), {
  data: null,
  rowKey: 'id',
  loading: false,
  emptyText: 'Nothing to show',
  stripe: false,
  bordered: false,
  hover: true,
  size: 'md',
  selectable: false,
  selectableIf: undefined,
  maxHeight: undefined,
  rowClass: undefined,
  layout: 'auto',
  ariaLabel: undefined,
})

const emit = defineEmits<TableEmits<T>>()

/** Keys rather than rows: a selection outlives the page it was made on. */
const selected = defineModel<RowKey[]>('selected', { default: () => [] })
const sort = defineModel<TableSort | null>('sort', { default: null })

/** An array or a paginator; the table only ever needs the rows out of it. */
const rows = computed<T[]>(() => {
  const value = props.data
  if (!value) return []
  return Array.isArray(value) ? value : (value.data ?? [])
})

const visibleColumns = computed(() => props.columns.filter((column) => !column.hidden))

const columnCount = computed(() => visibleColumns.value.length + (props.selectable ? 1 : 0))

const classes = computed(() => [
  'wx-table',
  `wx-table--${props.size}`,
  {
    'wx-table--stripe': props.stripe,
    'wx-table--bordered': props.bordered,
    'wx-table--hover': props.hover,
    'wx-table--sticky': props.maxHeight !== undefined,
    'is-loading': props.loading,
  },
])

const scrollStyle = computed(() =>
  props.maxHeight === undefined ? undefined : { maxHeight: size(props.maxHeight) },
)

function size(value: string | number): string {
  return typeof value === 'number' ? `${value}px` : value
}

function colStyle(column: TableColumn<T>) {
  return {
    width: column.width === undefined ? undefined : size(column.width),
    minWidth: column.minWidth === undefined ? undefined : size(column.minWidth),
  }
}

/** A dotted key walks into the row, so an eager-loaded relation reads as `user.name`. */
function read(row: T, path: string): unknown {
  if (!path.includes('.')) return (row as TableRow)[path]
  return path.split('.').reduce<unknown>((value, part) => {
    if (value === null || typeof value !== 'object') return undefined
    return (value as TableRow)[part]
  }, row)
}

function cellText(column: TableColumn<T>, row: T, index: number): string {
  const value = read(row, column.key)
  if (column.formatter) return column.formatter(value, row, index)
  return value === null || value === undefined ? '' : String(value)
}

function keyOf(row: T, index: number): RowKey {
  if (typeof props.rowKey === 'function') return props.rowKey(row, index)
  const value = read(row, props.rowKey)
  return typeof value === 'string' || typeof value === 'number' ? value : index
}

function canSelect(row: T): boolean {
  return props.selectableIf ? props.selectableIf(row) : true
}

const selectableRows = computed(() => rows.value.filter(canSelect))

const selectableKeys = computed(() =>
  selectableRows.value.map((row) => keyOf(row, rows.value.indexOf(row))),
)

const selectedCount = computed(
  () => selectableKeys.value.filter((key) => selected.value.includes(key)).length,
)

const allSelected = computed(
  () => selectableKeys.value.length > 0 && selectedCount.value === selectableKeys.value.length,
)

const someSelected = computed(() => selectedCount.value > 0 && !allSelected.value)

function announce(keys: RowKey[]) {
  selected.value = keys
  emit(
    'selection-change',
    keys,
    rows.value.filter((row, index) => keys.includes(keyOf(row, index))),
  )
}

/**
 * The header checkbox works on this page only. Keys picked on other pages stay put —
 * clearing someone's selection because they paged forward would be a surprise.
 */
function toggleAll(checked: boolean) {
  const page = selectableKeys.value
  if (checked) announce([...selected.value, ...page.filter((key) => !selected.value.includes(key))])
  else announce(selected.value.filter((key) => !page.includes(key)))
}

function toggleRow(row: T, index: number, checked: boolean) {
  const key = keyOf(row, index)
  if (checked) {
    if (!selected.value.includes(key)) announce([...selected.value, key])
  } else {
    announce(selected.value.filter((item) => item !== key))
  }
}

function isSelected(row: T, index: number): boolean {
  return selected.value.includes(keyOf(row, index))
}

/**
 * Sorting is reported, never applied: the rows on screen are one page out of a query,
 * and reordering them here would only shuffle the page. Three states, because a column
 * has to be able to give the ordering back.
 */
function onSort(column: TableColumn<T>) {
  if (!column.sortable) return
  const current = sort.value
  let next: TableSort | null
  if (current?.key !== column.key) next = { key: column.key, order: 'asc' }
  else if (current.order === 'asc') next = { key: column.key, order: 'desc' }
  else next = null

  sort.value = next
  emit('sort-change', next)
}

function ariaSort(column: TableColumn<T>) {
  if (!column.sortable) return undefined
  if (sort.value?.key !== column.key) return 'none'
  return sort.value.order === 'asc' ? 'ascending' : 'descending'
}

function sortState(column: TableColumn<T>) {
  return sort.value?.key === column.key ? sort.value.order : null
}

function alignClass(column: TableColumn<T>) {
  return column.align && column.align !== 'left' ? `wx-table__cell--${column.align}` : undefined
}
</script>

<template>
  <div :class="classes">
    <div class="wx-table__scroll" :style="scrollStyle">
      <table
        class="wx-table__table"
        :class="{ 'wx-table__table--fixed': layout === 'fixed' }"
        :aria-label="ariaLabel"
        :aria-busy="loading || undefined"
      >
        <colgroup>
          <col v-if="selectable" class="wx-table__col--select" />
          <col v-for="column in visibleColumns" :key="column.key" :style="colStyle(column)" />
        </colgroup>

        <thead class="wx-table__head">
          <tr>
            <th v-if="selectable" scope="col" class="wx-table__cell wx-table__cell--select">
              <wx-checkbox
                :model-value="allSelected"
                :indeterminate="someSelected"
                :disabled="!selectableKeys.length"
                aria-label="Select every row on this page"
                @update:model-value="toggleAll"
              />
            </th>

            <th
              v-for="column in visibleColumns"
              :key="column.key"
              scope="col"
              class="wx-table__cell"
              :class="[alignClass(column), column.headerClass]"
              :aria-sort="ariaSort(column)"
            >
              <button
                v-if="column.sortable"
                class="wx-table__sort"
                type="button"
                @click="onSort(column)"
              >
                <slot :name="`header-${column.key}`" :column="column">
                  {{ column.label ?? column.key }}
                </slot>
                <svg
                  class="wx-table__sort-icon"
                  :class="sortState(column) ? `is-${sortState(column)}` : undefined"
                  viewBox="0 0 8 12"
                  aria-hidden="true"
                >
                  <path class="wx-table__sort-up" d="M4 1 7 5 1 5Z" />
                  <path class="wx-table__sort-down" d="M4 11 1 7 7 7Z" />
                </svg>
              </button>

              <slot v-else :name="`header-${column.key}`" :column="column">
                {{ column.label ?? column.key }}
              </slot>
            </th>
          </tr>
        </thead>

        <tbody class="wx-table__body">
          <tr
            v-for="(row, index) in rows"
            :key="keyOf(row, index)"
            class="wx-table__row"
            :class="[
              rowClass?.(row, index),
              { 'is-selected': selectable && isSelected(row, index) },
            ]"
            @click="emit('row-click', row, index, $event)"
          >
            <td v-if="selectable" class="wx-table__cell wx-table__cell--select" @click.stop>
              <wx-checkbox
                :model-value="isSelected(row, index)"
                :disabled="!canSelect(row)"
                aria-label="Select row"
                @update:model-value="(checked: boolean) => toggleRow(row, index, checked)"
              />
            </td>

            <td
              v-for="column in visibleColumns"
              :key="column.key"
              class="wx-table__cell"
              :class="[alignClass(column), column.cellClass]"
            >
              <slot
                :name="`cell-${column.key}`"
                :row="row"
                :value="read(row, column.key)"
                :index="index"
                :column="column"
              >
                {{ cellText(column, row, index) }}
              </slot>
            </td>
          </tr>

          <tr v-if="!rows.length && !loading" class="wx-table__row wx-table__row--empty">
            <td class="wx-table__cell wx-table__empty" :colspan="columnCount">
              <slot name="empty">{{ emptyText }}</slot>
            </td>
          </tr>
        </tbody>

        <tfoot v-if="$slots.footer" class="wx-table__foot">
          <tr>
            <td class="wx-table__cell" :colspan="columnCount">
              <slot name="footer" />
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div v-if="loading" class="wx-table__loading">
      <slot name="loading">
        <span class="wx-table__spinner" aria-hidden="true" />
        <span class="wx-table__loading-text">Loading</span>
      </slot>
    </div>
  </div>
</template>

<style scoped>
.wx-table {
  position: relative;
  box-sizing: border-box;
  background: var(--wx-bg-surface);
  border-radius: var(--wx-radius-md);
  color: var(--wx-text-default);
}

.wx-table__scroll {
  overflow: auto;
  border-radius: inherit;
}

/*
 * Explicit resets rather than an assumption that nothing else styles a table. VitePress
 * turns every table into a scrolling block; Bootstrap and Tailwind's preflight have
 * opinions of their own. Left alone, a host stylesheet takes the layout away from the
 * table, the stickiness away from the header and the stripes away from the rows —
 * quietly, and only once the library is embedded somewhere real.
 */
.wx-table__table {
  display: table;
  width: 100%;
  margin: 0;
  overflow: visible;
  border: 0;
  border-collapse: collapse;
  border-spacing: 0;
  font-size: var(--wx-font-size-md);
}

.wx-table__table thead {
  display: table-header-group;
}

.wx-table__table tbody {
  display: table-row-group;
}

.wx-table__table tfoot {
  display: table-footer-group;
}

.wx-table__table tr {
  display: table-row;
  background: none;
  border: 0;
}

.wx-table__table th,
.wx-table__table td {
  display: table-cell;
  border: 0;
}

.wx-table__table--fixed {
  table-layout: fixed;
}

.wx-table--sm .wx-table__table {
  font-size: var(--wx-font-size-sm);
}

.wx-table--lg .wx-table__table {
  font-size: var(--wx-font-size-lg);
}

.wx-table__cell {
  box-sizing: border-box;
  padding: var(--wx-table-padding-y, var(--wx-space-12)) var(--wx-space-16);
  text-align: left;
  vertical-align: middle;
}

.wx-table--sm {
  --wx-table-padding-y: var(--wx-space-8);
}

.wx-table--lg {
  --wx-table-padding-y: var(--wx-space-16);
}

.wx-table__cell--center {
  text-align: center;
}

.wx-table__cell--right {
  text-align: right;
}

.wx-table__cell--select {
  width: 44px;
  padding-right: 0;
}

.wx-table__head .wx-table__cell {
  background: var(--wx-bg-subtle);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-semibold);
  white-space: nowrap;
}

/* Sticky needs a painted background of its own, or the rows show through. */
.wx-table--sticky .wx-table__head .wx-table__cell {
  position: sticky;
  top: 0;
  z-index: var(--wx-z-index-sticky);
}

.wx-table__body .wx-table__cell {
  border-top: 1px solid var(--wx-border-muted);
}

.wx-table--bordered .wx-table__cell + .wx-table__cell {
  border-left: 1px solid var(--wx-border-muted);
}

.wx-table--stripe .wx-table__row:nth-child(even) {
  background: var(--wx-bg-subtle);
}

.wx-table--hover .wx-table__row:hover {
  background: var(--wx-bg-fill);
}

.wx-table__row.is-selected {
  background: var(--wx-color-primary-soft);
}

.wx-table__sort {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-6);
  padding: 0;
  background: none;
  border: none;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.wx-table__sort:hover {
  color: var(--wx-text-default);
}

.wx-table__sort:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
  border-radius: var(--wx-radius-xs);
}

.wx-table__sort-icon {
  flex: 0 0 auto;
  width: 8px;
  height: 12px;
}

.wx-table__sort-icon path {
  fill: var(--wx-border-strong);
}

.wx-table__sort-icon.is-asc .wx-table__sort-up,
.wx-table__sort-icon.is-desc .wx-table__sort-down {
  fill: var(--wx-color-primary);
}

.wx-table__empty {
  padding: var(--wx-space-32) var(--wx-space-16);
  color: var(--wx-text-muted);
  text-align: center;
}

.wx-table__foot .wx-table__cell {
  border-top: 1px solid var(--wx-border-muted);
}

/* The rows stay readable underneath: a page that is being refreshed still says what it said. */
.wx-table__loading {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--wx-space-8);
  background: color-mix(in srgb, var(--wx-bg-surface) 70%, transparent);
  border-radius: inherit;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-table__spinner {
  width: 16px;
  height: 16px;
  border: 2px solid var(--wx-border-default);
  border-top-color: var(--wx-color-primary);
  border-radius: var(--wx-radius-full);
  animation: wx-table-spin var(--wx-duration-slow) linear infinite;
}

@keyframes wx-table-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-table__spinner {
    animation-duration: 3s;
  }
}
</style>

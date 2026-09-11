<script setup lang="ts" generic="T extends TableRow = TableRow">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useSlots, watch } from 'vue'
import WxCheckbox from '../Checkbox/Checkbox.vue'
import WxInput from '../Input/Input.vue'
import WxPagination from '../Pagination/Pagination.vue'
import type {
  RowKey,
  TableColumn,
  TableEmits,
  TableProps,
  TableRow,
  TableSort,
  TableState,
  TableSummaryRow,
} from './types'

defineOptions({ name: 'WxTable' })

/** The disclosure and checkbox columns are square and never resize. */
const UTILITY_WIDTH = 44

const props = withDefaults(defineProps<TableProps<T>>(), {
  data: null,
  rowKey: 'id',
  title: undefined,
  searchable: false,
  searchPlaceholder: 'Search',
  searchDebounce: 300,
  loading: false,
  emptyText: 'Nothing to show',
  stripe: false,
  bordered: false,
  hover: true,
  size: 'md',
  selectable: false,
  selectableIf: undefined,
  expandable: false,
  expandableIf: undefined,
  summary: () => [],
  pagination: undefined,
  perPageOptions: () => [],
  persist: undefined,
  maxHeight: undefined,
  rowClass: undefined,
  layout: 'auto',
  ariaLabel: undefined,
})

const emit = defineEmits<TableEmits<T>>()

/** Keys rather than rows: a selection outlives the page it was made on. */
const selected = defineModel<RowKey[]>('selected', { default: () => [] })
const expanded = defineModel<RowKey[]>('expanded', { default: () => [] })
const sort = defineModel<TableSort | null>('sort', { default: null })
const search = defineModel<string>('search', { default: '' })
const page = defineModel<number>('page', { default: 1 })
const perPage = defineModel<number>('perPage', { default: 15 })

/** An array or a paginator; the table only ever needs the rows out of it. */
const rows = computed<T[]>(() => {
  const value = props.data
  if (!value) return []
  return Array.isArray(value) ? value : (value.data ?? [])
})

/** A paginated response is a promise that there are more pages to reach. */
const paginator = computed(() => (props.data && !Array.isArray(props.data) ? props.data : null))

const showPagination = computed(() => props.pagination ?? Boolean(paginator.value))

const visibleColumns = computed(() => props.columns.filter((column) => !column.hidden))

/** Columns that carry no data of their own: the chevron and the checkbox. */
const utilityCount = computed(() => (props.expandable ? 1 : 0) + (props.selectable ? 1 : 0))

const columnCount = computed(() => visibleColumns.value.length + utilityCount.value)

/*
 * The slots are declared rather than inferred. Left to infer, the type is read off a
 * template that builds slot names out of the column keys, and the circle that makes —
 * the slots depend on the template, the template on the slots — is answered with
 * `any`, which takes the table's props down with it.
 */
defineSlots<{
  title?: () => unknown
  actions?: () => unknown
  empty?: () => unknown
  footer?: () => unknown
  loading?: () => unknown
  expanded?: (props: { row: T; index: number }) => unknown
  [key: `header-${string}`]: ((props: { column: TableColumn<T> }) => unknown) | undefined
  [key: `cell-${string}`]:
    | ((props: { row: T; value: unknown; index: number; column: TableColumn<T> }) => unknown)
    | undefined
  [key: `summary-${string}`]:
    ((props: { row: TableSummaryRow; value: unknown }) => unknown) | undefined
}>()

const slots = useSlots()

const hasHeader = computed<boolean>(() =>
  Boolean(props.title || props.searchable || slots.title || slots.actions),
)

const classes = computed(() => [
  'wx-table',
  `wx-table--${props.size}`,
  {
    'wx-table--stripe': props.stripe,
    'wx-table--bordered': props.bordered,
    'wx-table--hover': props.hover,
    'wx-table--sticky': props.maxHeight !== undefined,
    'wx-table--has-header': hasHeader.value,
    'has-more-left': moreLeft.value,
    'has-more-right': moreRight.value,
    'is-loading': props.loading,
  },
])

const scrollStyle = computed(() =>
  props.maxHeight === undefined ? undefined : { maxHeight: length(props.maxHeight) },
)

function length(value: string | number): string {
  return typeof value === 'number' ? `${value}px` : value
}

/** Only a declared width can say where the column behind a pinned one begins. */
function widthOf(column: TableColumn<T>): number {
  if (typeof column.width === 'number') return column.width
  if (typeof column.width === 'string') {
    const parsed = Number.parseFloat(column.width)
    return Number.isFinite(parsed) ? parsed : 0
  }
  return 0
}

function colStyle(column: TableColumn<T>) {
  return {
    width: column.width === undefined ? undefined : length(column.width),
    minWidth: column.minWidth === undefined ? undefined : length(column.minWidth),
  }
}

/* ------------------------------------------------------------- measurement --- */

const scroller = ref<HTMLElement | null>(null)
const headRow = ref<HTMLElement | null>(null)

/**
 * The widths the browser actually handed the columns, in the order they sit in — the
 * utility columns first, then the declared ones.
 *
 * A declared width is what the caller *asked* for, and it is honoured only while
 * there is room. In `auto` layout a table that has to scroll squeezes every column
 * proportionally, so the declared numbers stop being true exactly when pinning
 * starts to matter — and a pinned column parked at a declared offset then leaves a
 * gap for the scrolling ones to show through.
 */
const laidOut = ref<number[]>([])

function measureColumns() {
  const cells = headRow.value?.children
  if (!cells) return

  const next = [...cells].map((cell) => (cell as HTMLElement).getBoundingClientRect().width)
  const same =
    next.length === laidOut.value.length &&
    next.every((width, index) => Math.abs(width - laidOut.value[index]) < 0.5)

  if (!same) laidOut.value = next
}

/** How far the columns have slid, which is what the edge shadows are about. */
const moreLeft = ref(false)
const moreRight = ref(false)

function readScroll() {
  const el = scroller.value
  if (!el) return
  moreLeft.value = el.scrollLeft > 1
  moreRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 1
}

function remeasure() {
  measureColumns()
  readScroll()
}

let observer: ResizeObserver | null = null

onMounted(() => {
  remeasure()
  if (typeof ResizeObserver === 'undefined' || !scroller.value) return
  observer = new ResizeObserver(remeasure)
  observer.observe(scroller.value)
})

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
})

/* Rows and columns both change what the browser gives each column. */
watch([() => rows.value.length, () => visibleColumns.value.length], async () => {
  await nextTick()
  remeasure()
})

/** What the browser gave the column at this position, or what was asked for. */
function laidOutWidth(index: number, asked: number) {
  const measured = laidOut.value[index]
  return measured === undefined || measured === 0 ? asked : measured
}

/**
 * Where every pinned column comes to rest, measured from its edge. The utility columns
 * are pinned too whenever anything else is: a checkbox that slides under a frozen name
 * column is worse than no freezing at all.
 */
const offsets = computed(() => {
  const map = new Map<string, { side: 'left' | 'right'; offset: number; edge: boolean }>()
  const utilities = utilityCount.value

  let left = 0
  for (let index = 0; index < utilities; index += 1) {
    left += laidOutWidth(index, UTILITY_WIDTH)
  }

  const lefts = visibleColumns.value
    .map((column, index) => ({ column, index }))
    .filter((entry) => entry.column.fixed === 'left')

  lefts.forEach((entry, order) => {
    map.set(entry.column.key, { side: 'left', offset: left, edge: order === lefts.length - 1 })
    left += laidOutWidth(utilities + entry.index, widthOf(entry.column))
  })

  const rights = visibleColumns.value
    .map((column, index) => ({ column, index }))
    .filter((entry) => entry.column.fixed === 'right')

  let right = 0
  for (let order = rights.length - 1; order >= 0; order -= 1) {
    const entry = rights[order]
    map.set(entry.column.key, { side: 'right', offset: right, edge: order === 0 })
    right += laidOutWidth(utilities + entry.index, widthOf(entry.column))
  }

  return map
})

const hasLeftFixed = computed(() => visibleColumns.value.some((column) => column.fixed === 'left'))

const hasFixed = computed(() => offsets.value.size > 0)

/**
 * The resting place goes out as a custom property rather than as `left` itself. An
 * inline `left` would outrank every stylesheet rule, and the pinning has to be able to
 * switch itself off in CSS when the table is too narrow to afford it.
 */
function fixedStyle(column: TableColumn<T>) {
  const pin = offsets.value.get(column.key)
  if (!pin) return undefined
  return { [`--wx-pin-${pin.side}`]: `${pin.offset}px` }
}

function fixedClass(column: TableColumn<T>) {
  const pin = offsets.value.get(column.key)
  if (!pin) return undefined
  return [`is-fixed-${pin.side}`, { 'is-fixed-edge': pin.edge }]
}

/** The utility columns ride along at the left edge once anything is pinned. */
function utilityStyle(slot: 'expand' | 'select') {
  if (!hasFixed.value) return undefined
  const before = slot === 'select' && props.expandable ? laidOutWidth(0, UTILITY_WIDTH) : 0
  return { '--wx-pin-left': `${before}px` }
}

const utilityClass = computed(() =>
  hasFixed.value ? ['is-fixed-left', { 'is-fixed-edge': !hasLeftFixed.value }] : undefined,
)

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

function canExpand(row: T): boolean {
  return props.expandableIf ? props.expandableIf(row) : true
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

function rowsFor(keys: RowKey[]): T[] {
  return rows.value.filter((row, index) => keys.includes(keyOf(row, index)))
}

function announce(keys: RowKey[]) {
  selected.value = keys
  emit('selection-change', keys, rowsFor(keys))
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

function isExpanded(row: T, index: number): boolean {
  return expanded.value.includes(keyOf(row, index))
}

function toggleExpand(row: T, index: number) {
  if (!canExpand(row)) return
  const key = keyOf(row, index)
  const keys = expanded.value.includes(key)
    ? expanded.value.filter((item) => item !== key)
    : [...expanded.value, key]

  expanded.value = keys
  emit('expand-change', keys, rowsFor(keys))
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
  page.value = 1
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

let timer: ReturnType<typeof setTimeout> | undefined

/**
 * The field answers at once; the backend hears about it when the typing settles. The
 * settled term is what the state is built from, so a burst of keystrokes is one change
 * rather than one per letter — and filtering changes what page one is, so the page goes
 * back to the first.
 */
const settledSearch = ref(search.value)

function onSearch(value: string | number | undefined) {
  search.value = value === undefined ? '' : String(value)
}

function onSearchChanged(term: string) {
  clearTimeout(timer)
  const settle = () => {
    settledSearch.value = term
    page.value = 1
    emit('search', term)
  }
  if (!props.searchDebounce) settle()
  else timer = setTimeout(settle, props.searchDebounce)
}

onBeforeUnmount(() => clearTimeout(timer))

/** Everything the backend needs, in one object, so there is one thing to watch. */
const state = computed<TableState>(() => ({
  page: page.value,
  perPage: perPage.value,
  sort: sort.value,
  search: settledSearch.value,
}))

const STORAGE_PREFIX = 'wx-table:'

/**
 * Reading happens after mount rather than during setup: this renders on a server too,
 * and markup built from one machine's localStorage would not match what the browser
 * then hydrates.
 */
function restore() {
  if (!props.persist) return
  let saved: Partial<TableState> | null = null
  try {
    const raw = window.localStorage.getItem(STORAGE_PREFIX + props.persist)
    saved = raw ? (JSON.parse(raw) as Partial<TableState>) : null
  } catch {
    // Unavailable, full, or holding something we did not write. Defaults will do.
    return
  }
  if (!saved) return

  if (typeof saved.page === 'number' && saved.page > 0) page.value = saved.page
  if (typeof saved.perPage === 'number' && saved.perPage > 0) perPage.value = saved.perPage
  if (typeof saved.search === 'string') search.value = saved.search
  if (saved.sort === null) sort.value = null
  else if (saved.sort && typeof saved.sort.key === 'string') {
    sort.value = { key: saved.sort.key, order: saved.sort.order === 'desc' ? 'desc' : 'asc' }
  }
}

function save(value: TableState) {
  if (!props.persist) return
  try {
    window.localStorage.setItem(STORAGE_PREFIX + props.persist, JSON.stringify(value))
  } catch {
    // A private window or a full quota. Remembering is a convenience, not a feature.
  }
}

onMounted(() => {
  restore()
  settledSearch.value = search.value
  emit('state-change', state.value)

  // Both watchers start here, so the restore above counts as the first state rather
  // than as a change — one fetch on load, not two.
  watch(search, onSearchChanged)
  watch(state, (value) => {
    save(value)
    emit('state-change', value)
  })
})

/** Columns ahead of the first figure belong to the caption. */
function summaryStart(row: TableSummaryRow): number {
  const cells = row.cells ?? {}
  const index = visibleColumns.value.findIndex((column) => column.key in cells)
  return index === -1 ? visibleColumns.value.length : index
}

function summarySpan(row: TableSummaryRow): number {
  return utilityCount.value + summaryStart(row)
}

function summaryColumns(row: TableSummaryRow): TableColumn<T>[] {
  return visibleColumns.value.slice(summaryStart(row))
}

function summaryValue(row: TableSummaryRow, column: TableColumn<T>): unknown {
  return row.cells?.[column.key]
}

function summaryText(row: TableSummaryRow, column: TableColumn<T>): string {
  const value = summaryValue(row, column)
  return value === null || value === undefined ? '' : String(value)
}
</script>

<template>
  <div :class="classes">
    <header v-if="hasHeader" class="wx-table__header">
      <div class="wx-table__title">
        <slot name="title">{{ title }}</slot>
      </div>

      <div class="wx-table__tools">
        <slot name="actions" />

        <div v-if="searchable" class="wx-table__search">
          <wx-input
            type="search"
            :model-value="search"
            :placeholder="searchPlaceholder"
            :size="size"
            clearable
            :aria-label="searchPlaceholder"
            @update:model-value="onSearch"
          />
        </div>
      </div>
    </header>

    <div ref="scroller" class="wx-table__scroll" :style="scrollStyle" @scroll="readScroll">
      <table
        class="wx-table__table"
        :class="{ 'wx-table__table--fixed': layout === 'fixed' }"
        :aria-label="ariaLabel"
        :aria-busy="loading || undefined"
      >
        <colgroup>
          <col v-if="expandable" :style="{ width: `${UTILITY_WIDTH}px` }" />
          <col v-if="selectable" :style="{ width: `${UTILITY_WIDTH}px` }" />
          <col v-for="column in visibleColumns" :key="column.key" :style="colStyle(column)" />
        </colgroup>

        <thead class="wx-table__head">
          <!-- The row the pin offsets are measured off: one cell per column, always. -->
          <tr ref="headRow">
            <th
              v-if="expandable"
              scope="col"
              class="wx-table__cell wx-table__cell--utility"
              :class="utilityClass"
              :style="utilityStyle('expand')"
            >
              <span class="wx-table__sr-only">Expand</span>
            </th>

            <th
              v-if="selectable"
              scope="col"
              class="wx-table__cell wx-table__cell--utility"
              :class="utilityClass"
              :style="utilityStyle('select')"
            >
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
              :class="[alignClass(column), fixedClass(column), column.headerClass]"
              :style="fixedStyle(column)"
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
          <template v-for="(row, index) in rows" :key="keyOf(row, index)">
            <tr
              class="wx-table__row"
              :class="[
                rowClass?.(row, index),
                {
                  'is-striped': index % 2 === 1,
                  'is-selected': selectable && isSelected(row, index),
                  'is-expanded': expandable && isExpanded(row, index),
                },
              ]"
              @click="emit('row-click', row, index, $event)"
            >
              <td
                v-if="expandable"
                class="wx-table__cell wx-table__cell--utility"
                :class="utilityClass"
                :style="utilityStyle('expand')"
                @click.stop
              >
                <button
                  v-if="canExpand(row)"
                  class="wx-table__expander"
                  type="button"
                  :aria-expanded="isExpanded(row, index)"
                  :aria-label="isExpanded(row, index) ? 'Collapse row' : 'Expand row'"
                  @click="toggleExpand(row, index)"
                >
                  <svg viewBox="0 0 12 12" aria-hidden="true">
                    <path d="M4.5 2 8.5 6l-4 4" />
                  </svg>
                </button>
              </td>

              <td
                v-if="selectable"
                class="wx-table__cell wx-table__cell--utility"
                :class="utilityClass"
                :style="utilityStyle('select')"
                @click.stop
              >
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
                :class="[alignClass(column), fixedClass(column), column.cellClass]"
                :style="fixedStyle(column)"
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

            <tr v-if="expandable && isExpanded(row, index)" class="wx-table__row--expansion">
              <td class="wx-table__cell wx-table__expansion" :colspan="columnCount">
                <slot name="expanded" :row="row" :index="index" />
              </td>
            </tr>
          </template>

          <tr v-if="!rows.length && !loading" class="wx-table__row wx-table__row--empty">
            <td class="wx-table__cell wx-table__empty" :colspan="columnCount">
              <slot name="empty">{{ emptyText }}</slot>
            </td>
          </tr>
        </tbody>

        <tfoot v-if="summary.length || $slots.footer || showPagination" class="wx-table__foot">
          <tr
            v-for="(line, lineIndex) in summary"
            :key="lineIndex"
            class="wx-table__summary"
            :class="[line.class, { 'is-strong': line.strong }]"
          >
            <td
              v-if="summarySpan(line) > 0"
              class="wx-table__cell wx-table__summary-label"
              :colspan="summarySpan(line)"
            >
              {{ line.label }}
            </td>

            <td
              v-for="column in summaryColumns(line)"
              :key="column.key"
              class="wx-table__cell"
              :class="alignClass(column)"
            >
              <slot :name="`summary-${column.key}`" :row="line" :value="summaryValue(line, column)">
                {{ summaryText(line, column) }}
              </slot>
            </td>
          </tr>

          <tr v-if="$slots.footer || showPagination" class="wx-table__footer-row">
            <td class="wx-table__cell" :colspan="columnCount">
              <slot name="footer">
                <wx-pagination
                  v-model:page="page"
                  v-model:per-page="perPage"
                  :paginator="paginator"
                  :per-page-options="perPageOptions"
                  :size="size"
                  :disabled="loading"
                />
              </slot>
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
  z-index: 0;
  box-sizing: border-box;
  /*
   * A flex or grid item will not shrink below its content unless told to, and the
   * content here is a table that can be twice the width of the page. Without this the
   * inner scroller never scrolls and the whole document does instead.
   */
  min-width: 0;
  /*
   * Pinning asks about the table's own width, so the table is the thing being measured.
   * Containment means the width can no longer come from the contents, so it is stated:
   * inside a flex row the element would otherwise measure zero.
   */
  width: 100%;
  max-width: 100%;
  container-type: inline-size;
  background: var(--wx-bg-surface);
  border-radius: var(--wx-radius-md);
  color: var(--wx-text-default);

  --wx-table-padding-y: var(--wx-space-10);
  --wx-table-padding-x: var(--wx-space-16);
  /* What a pinned cell paints itself with; every row state restates it. */
  --wx-table-row-bg: var(--wx-bg-surface);
}

.wx-table--sm {
  --wx-table-padding-y: var(--wx-space-6);
  --wx-table-padding-x: var(--wx-space-12);
}

.wx-table--lg {
  --wx-table-padding-y: var(--wx-space-14);
}

.wx-table__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-12) var(--wx-table-padding-x);
}

.wx-table__title {
  min-width: 0;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-lg);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-table__tools {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  margin-left: auto;
}

.wx-table__search {
  width: var(--wx-table-search-width, 240px);
}

.wx-table__scroll {
  overflow: auto;
  border-radius: inherit;
}

/*
 * A rounded corner belongs to the outside of the card, and once a title or a search
 * field sits above the rows, the top of the rows is no longer the outside. Left
 * rounded, the heading strip curves away from two square corners and leaves a white
 * wedge in each — small on a wide table, and the first thing you see on a narrow one.
 */
.wx-table--has-header .wx-table__scroll {
  border-start-start-radius: 0;
  border-start-end-radius: 0;
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

/*
 * A row's colour reaches the eye by two routes: the row paints it behind cells that are
 * transparent, and a pinned cell paints it itself. They therefore have to change at the
 * same speed, and that speed has to be stated — VitePress fades a `tr` over half a
 * second, which left the pinned columns snapping to the hover colour while the rest of
 * the row was still on its way there.
 *
 * The shadow goes with it: it is the same colour, covering the seam beside the cell.
 */
.wx-table__row,
.wx-table__cell.is-fixed-left,
.wx-table__cell.is-fixed-right {
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

@media (prefers-reduced-motion: reduce) {
  .wx-table__row,
  .wx-table__cell.is-fixed-left,
  .wx-table__cell.is-fixed-right {
    transition: none;
  }
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
  padding: var(--wx-table-padding-y) var(--wx-table-padding-x);
  text-align: left;
  vertical-align: middle;
}

.wx-table__cell--center {
  text-align: center;
}

.wx-table__cell--right {
  text-align: right;
}

/*
 * The control is the whole content of a utility cell, and an inline-level box would sit
 * on a text baseline — three pixels above the middle of the row, which is exactly the
 * kind of misalignment that reads as sloppiness rather than as a bug.
 */
.wx-table__cell--utility {
  width: 44px;
  padding-right: 0;
  line-height: 0;
}

.wx-table__cell--utility :deep(.wx-checkbox) {
  display: flex;
}

.wx-table__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.wx-table__head .wx-table__cell {
  --wx-table-row-bg: var(--wx-bg-subtle);

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
  z-index: 2;
}

.wx-table--sticky .wx-table__foot .wx-table__cell {
  position: sticky;
  bottom: 0;
  z-index: 2;
  background: var(--wx-bg-surface);
}

/* A pinned column paints over the scrolling ones, and the header over both. The colour
   it paints with is whatever the row it belongs to is wearing. */
.wx-table__cell.is-fixed-left,
.wx-table__cell.is-fixed-right {
  position: sticky;
  z-index: 1;
  background: var(--wx-table-row-bg);
}

.wx-table__cell.is-fixed-left {
  left: var(--wx-pin-left, 0px);
}

.wx-table__cell.is-fixed-right {
  right: var(--wx-pin-right, 0px);
}

.wx-table__head .wx-table__cell.is-fixed-left,
.wx-table__head .wx-table__cell.is-fixed-right,
.wx-table--sticky .wx-table__foot .wx-table__cell.is-fixed-left,
.wx-table--sticky .wx-table__foot .wx-table__cell.is-fixed-right {
  z-index: 3;
}

/*
 * A scrollport rarely begins on a whole pixel — at any display scale but 100% it begins
 * on a fraction of one — so the pinned cell is rasterised with its edge pixel only
 * partly covered, and a hairline of the scrolling column shows through it.
 *
 * The cover is a strip of its own rather than a box shadow: Firefox declines to paint a
 * shadow on a cell in a collapsed-border table, which is where the sliver was still
 * turning up. Two pixels wide and straddling the edge, so whichever pixel the boundary
 * falls in is covered outright instead of blended.
 */
.wx-table__cell.is-fixed-left::before,
.wx-table__cell.is-fixed-right::before {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2px;
  background: var(--wx-table-row-bg);
  pointer-events: none;
}

.wx-table__cell.is-fixed-left::before {
  left: -1px;
}

/*
 * Flush with the edge rather than a pixel past it. The rightmost pinned cell ends
 * where the table does, so a strip hanging over that edge is a pixel of scrollable
 * width — enough for a horizontal scrollbar to appear under a table that fits.
 */
.wx-table__cell.is-fixed-right::before {
  right: 0;
}

/*
 * The edge of the frozen block, so it reads as floating over what slides beneath —
 * and only while something is sliding beneath it. A shadow on a table with nothing
 * hidden either side is a line drawn across the middle of it for no reason.
 */
.wx-table.has-more-left .wx-table__cell.is-fixed-left.is-fixed-edge {
  box-shadow: 6px 0 6px -6px rgb(0 0 0 / 0.18);
}

.wx-table.has-more-right .wx-table__cell.is-fixed-right.is-fixed-edge {
  box-shadow: -6px 0 6px -6px rgb(0 0 0 / 0.18);
}

/*
 * Pinning is a luxury of width. On a phone the frozen columns take most of the screen
 * and the ones the reader came for have nowhere to scroll into view, so the table gives
 * up freezing and simply scrolls as a whole.
 *
 * The question is asked of the table rather than of the window: the same thing happens
 * to a table in a narrow panel on a wide desktop. The offsets are custom properties for
 * exactly this reason — an inline `left` could not be talked out of it.
 */
@container (max-width: 600px) {
  .wx-table__cell.is-fixed-left,
  .wx-table__cell.is-fixed-right,
  .wx-table__cell.is-fixed-left.is-fixed-edge,
  .wx-table__cell.is-fixed-right.is-fixed-edge {
    left: auto;
    right: auto;
    z-index: auto;
    box-shadow: none;
  }

  .wx-table__cell.is-fixed-left::before,
  .wx-table__cell.is-fixed-right::before {
    display: none;
  }
}

.wx-table__body .wx-table__cell {
  border-top: 1px solid var(--wx-border-muted);
}

.wx-table--bordered .wx-table__cell + .wx-table__cell {
  border-left: 1px solid var(--wx-border-muted);
}

/* Striped by row index, not by position: an expansion row is a sibling too, and
   counting it would flip the pattern from wherever a row was opened. Each state names
   its colour once; the pinned cells read it back out of the variable. */
.wx-table--stripe .wx-table__row.is-striped {
  --wx-table-row-bg: var(--wx-bg-subtle);

  background: var(--wx-bg-subtle);
}

.wx-table--hover .wx-table__row:hover {
  --wx-table-row-bg: var(--wx-bg-fill);

  background: var(--wx-bg-fill);
}

.wx-table__row.is-selected {
  --wx-table-row-bg: var(--wx-color-primary-soft);

  background: var(--wx-color-primary-soft);
}

.wx-table__expander {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  padding: 0;
  background: none;
  border: none;
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-table__expander:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-table__expander:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-table__expander svg {
  width: 12px;
  height: 12px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-table__row.is-expanded .wx-table__expander svg {
  transform: rotate(90deg);
}

.wx-table__expansion {
  background: var(--wx-bg-subtle);
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
  padding: var(--wx-space-24) var(--wx-table-padding-x);
  color: var(--wx-text-muted);
  text-align: center;
}

.wx-table__foot .wx-table__cell {
  border-top: 1px solid var(--wx-border-muted);
}

.wx-table__summary .wx-table__cell {
  color: var(--wx-text-muted);
}

.wx-table__summary-label {
  text-align: right;
}

.wx-table__summary.is-strong .wx-table__cell {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
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

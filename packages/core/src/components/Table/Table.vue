<script setup lang="ts" generic="T extends TableRow = TableRow">
import {
  computed,
  getCurrentInstance,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  useSlots,
  watch,
} from 'vue'
import WxCheckbox from '../Checkbox/Checkbox.vue'
import WxInput from '../Input/Input.vue'
import type { InputModelValue } from '../Input/types'
import WxIcon from '../Icon/Icon.vue'
import WxAction from '../Action/Action.vue'
import WxIndicator from '../Indicator/Indicator.vue'
import WxPagination from '../Pagination/Pagination.vue'
import WxPopover from '../Popover/Popover.vue'
import { useElementWidth } from '../../composables/useElementWidth'
import {
  useTreeNodes,
  type TreeAccessors,
  type TreeDropZone,
  type TreeKey,
  type TreeRow,
} from '../../composables/useTreeNodes'
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
  filtersCount: 0,
  filtersLabel: 'Filters',
  filtersWidth: 300,
  loading: false,
  emptyText: 'Nothing to show',
  stripe: false,
  bordered: false,
  hover: true,
  clickable: undefined,
  size: 'md',
  selectable: false,
  selectableIf: undefined,
  expandable: false,
  expandableIf: undefined,
  summary: () => [],
  /* A phone is 360–430 CSS pixels wide, and a table has to be wider than its widest row. */
  cardsBelow: 480,
  flush: false,
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
const source = computed<T[]>(() => {
  const value = props.data
  if (!value) return []
  return Array.isArray(value) ? value : (value.data ?? [])
})

/* ---------------------------------------------------------------------------
 * Tree mode
 *
 * The rows nest: the first column carries the indentation and the disclosure, every
 * other column is still a column. What is where, what is open and what a move does to
 * the arrays is `useTreeNodes` — the same machinery `WxTree` runs on, so the two cannot
 * drift apart in what a drop means or when a lazy branch arrives.
 * ------------------------------------------------------------------------- */

const isTree = computed(() => Boolean(props.tree))

const treeOptions = computed(() => ({
  childrenKey: 'children',
  hasChildrenKey: 'has_children',
  indent: 20,
  springDelay: 600,
  ...props.tree,
}))

/** The tree needs a key from the row alone; the table's own falls back to a position. */
function treeKeyOf(row: T, path: number[]): TreeKey {
  const index = path.at(-1) ?? 0
  if (typeof props.rowKey === 'function') return props.rowKey(row, index)
  if (props.rowKey) {
    const value = read(row, props.rowKey)
    if (typeof value === 'string' || typeof value === 'number') return value
  }
  return path.join('.')
}

const treeAccessors: TreeAccessors<T> = {
  key: treeKeyOf,
  children: (row) => row[treeOptions.value.childrenKey] as T[] | undefined,
  setChildren: (row, children) => {
    ;(row as Record<string, unknown>)[treeOptions.value.childrenKey] = children
  },
  /*
   * Absent is not the same as false: a backend that never said anything about children
   * gets a chevron, and finding nothing behind it costs one request. Saying `false` —
   * `withCount` returning zero — is taken at its word.
   */
  leaf: (row) => {
    const flag = row[treeOptions.value.hasChildrenKey]
    return flag === undefined ? false : !flag
  },
}

const tree = useTreeNodes<T>({
  nodes: () => source.value,
  accessors: treeAccessors,
  expanded,
  lazy: () => Boolean(treeOptions.value.lazy),
  load: (row) => treeOptions.value.load?.(row) ?? [],
  allowDrop: (drag, drop, zone) => treeOptions.value.allowDrop?.(drag, drop, zone) ?? true,
})

const treeRows = computed(() => (isTree.value ? tree.rows.value : []))

/** The rows on screen: the branch as it is opened, or the page as it arrived. */
const rows = computed<T[]>(() =>
  isTree.value ? treeRows.value.map((item) => item.node) : source.value,
)

/** The two arrays run in step, so a row's place in the tree is its index. */
const treeAt = (index: number) => (isTree.value ? treeRows.value[index] : undefined)

if (props.tree?.defaultExpandAll) tree.expandAll()

async function toggleBranch(item: TreeRow<T>) {
  await tree.toggle(item.key)
  emit('expand-change', [...expanded.value], rowsFor(expanded.value))
}

/* ------------------------------------------------------------- moving rows */

const dragKey = ref<TreeKey | null>(null)
const dropKey = ref<TreeKey | null>(null)
const dropZone = ref<TreeDropZone | null>(null)

/** The branch a row is hovering over, and the timer that will open it. */
let springKey: TreeKey | null = null
let springTimer: ReturnType<typeof setTimeout> | undefined

const canDragRow = (item: TreeRow<T>) =>
  Boolean(treeOptions.value.draggable) && (treeOptions.value.allowDrag?.(item.node) ?? true)

function onRowDragStart(item: TreeRow<T>, event: DragEvent) {
  if (!canDragRow(item)) {
    event.preventDefault()
    return
  }
  dragKey.value = item.key
  event.dataTransfer?.setData('text/plain', String(item.key))
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

/**
 * Which third of the row the pointer is over decides the landing: the edges put the row
 * beside the one under the pointer, the middle puts it inside.
 */
function zoneAt(event: DragEvent, element: HTMLElement): TreeDropZone {
  const box = element.getBoundingClientRect()
  const y = (event.clientY - box.top) / box.height
  if (y <= 0.3) return 'before'
  if (y >= 0.7) return 'after'
  return 'inside'
}

function cancelSpring() {
  if (springTimer) clearTimeout(springTimer)
  springTimer = undefined
  springKey = null
}

/**
 * Dropping into a branch nobody can see the inside of is a guess. Holding a row over a
 * closed one opens it — and fetches it, where the children are not in yet — so the
 * guess becomes a look, and a move across the tree is one drag rather than three.
 */
function spring(item: TreeRow<T>, zone: TreeDropZone) {
  const delay = treeOptions.value.springDelay ?? 0
  if (!delay || zone !== 'inside' || !item.expandable || item.expanded) {
    cancelSpring()
    return
  }
  if (springKey === item.key) return

  cancelSpring()
  springKey = item.key
  springTimer = setTimeout(() => {
    void tree.expand(item.key)
    cancelSpring()
  }, delay)
}

function onRowDragOver(item: TreeRow<T>, event: DragEvent) {
  if (dragKey.value === null) return

  const zone = zoneAt(event, event.currentTarget as HTMLElement)
  if (!tree.canDrop(dragKey.value, item.key, zone)) {
    dropKey.value = null
    dropZone.value = null
    cancelSpring()
    return
  }

  // Taking the event is what tells the browser this is a valid drop target.
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  dropKey.value = item.key
  dropZone.value = zone
  spring(item, zone)
}

async function onRowDrop(item: TreeRow<T>) {
  const key = dragKey.value
  const zone = dropZone.value
  if (key === null || zone === null || dropKey.value !== item.key) return

  cancelSpring()
  dragKey.value = null
  dropKey.value = null
  dropZone.value = null

  /*
   * Into a branch whose children have not arrived, the position is not ours to guess:
   * fetch first, then append to what is really there.
   */
  if (zone === 'inside' && treeOptions.value.lazy && !item.expanded) await tree.expand(item.key)

  const target = tree.entry(item.key)
  const landed = tree.move(key, item.key, zone)
  if (!landed || !target) return

  emit('node-drop', {
    row: landed.node,
    target: target.node,
    zone,
    parent: landed.parent,
    index: landed.index,
    via: 'pointer',
  })
}

function onRowDragEnd() {
  cancelSpring()
  dragKey.value = null
  dropKey.value = null
  dropZone.value = null
}

onBeforeUnmount(cancelSpring)

/** A paginated response is a promise that there are more pages to reach. */
const paginator = computed(() => (props.data && !Array.isArray(props.data) ? props.data : null))

/*
 * A page of a tree would cut branches in half, and the rows here are not a page anyway:
 * a lazy tree asks for a level at a time.
 */
/*
 * One page is not a thing to page through. The control still costs a line of the screen and
 * asks to be read, and on a phone that line is most of what is left.
 */
const showPagination = computed(() => {
  if (isTree.value) return false

  if (paginator.value) {
    return (props.pagination ?? true) && paginator.value.last_page > 1
  }

  return props.pagination ?? false
})

/*
 * The table's own width decides this, not the window's: the same table is a page, half of a
 * dialog and a phone, and only one of those is the window.
 */
const root = ref<HTMLElement | null>(null)
const width = useElementWidth(root)

const visibleColumns = computed(() =>
  props.columns.filter(
    (column) =>
      !column.hidden &&
      // Measured before the first layout, `width` is 0 and nothing is dropped — which is the
      // right way round: a column that flashes in is better than one that flashes out.
      !(column.hideBelow && width.value > 0 && width.value < column.hideBelow),
  ),
)

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
  /**
   * The fields of the filter, inside the panel a funnel in the header opens.
   *
   * Three dropdowns standing open in the header is three controls of chrome above the first
   * row, and on a phone that is half the screen before any data. Behind the funnel they cost
   * one button, and what they are set to is said by `applied` instead.
   */
  filters?: () => unknown
  /**
   * What the filters are set to, as chips the reader can take off — the strip between the
   * header and the rows.
   *
   * The table gives the place and the spacing; the chips are the caller's, because only the
   * caller knows what "rubric: News" is called and what taking it off means. Nothing is drawn
   * while the strip holds no elements.
   */
  applied?: () => unknown
  empty?: () => unknown
  footer?: () => unknown
  loading?: () => unknown
  expanded?: (props: { row: T; index: number }) => unknown
  /** Buttons along the top of a card, where a row has no room for a column of them. */
  'card-actions'?: (props: { row: T; index: number }) => unknown
  [key: `header-${string}`]: ((props: { column: TableColumn<T> }) => unknown) | undefined
  [key: `cell-${string}`]:
    | ((props: { row: T; value: unknown; index: number; column: TableColumn<T> }) => unknown)
    | undefined
  [key: `summary-${string}`]:
    ((props: { row: TableSummaryRow; value: unknown }) => unknown) | undefined
}>()

const slots = useSlots()

const hasHeader = computed<boolean>(() =>
  Boolean(
    props.title ||
    props.searchable ||
    slots.title ||
    slots.actions ||
    slots.filters ||
    slots.applied,
  ),
)

/*
 * Whether a row does anything when it is clicked, so the pointer can say so.
 *
 * Read off the vnode rather than taken as a prop: the caller already says it by listening, and
 * a second way to say the same thing is a second thing to get wrong.
 *
 * Except when the answer changes while the table is on screen. `instance.vnode` is not
 * reactive, so the inference runs once, at the first render, and a list that swaps its rows for
 * ones that lead nowhere — a bin, an archive — would keep the pointer and the highlight it no
 * longer earns. Such a list says so with the prop instead.
 */
const instance = getCurrentInstance()
const clickable = computed(() => props.clickable ?? Boolean(instance?.vnode.props?.onRowClick))

/**
 * A row leads somewhere, or it does not lead anywhere at all.
 *
 * Only an explicit `false` stops it, and then it stops everything: a row that looked inert and
 * still opened something would be the same lie as one that looked clickable and did nothing
 * (§13). The inference above is about how a row looks — a table nobody happens to be listening
 * to has withdrawn no promise, and its click is emitted into the air as it always was.
 */
function onRowClick(row: T, index: number, event: MouseEvent): void {
  if (props.clickable === false) return

  emit('row-click', row, index, event)
}

const asCards = computed(
  () => props.cardsBelow > 0 && width.value > 0 && width.value < props.cardsBelow,
)

/**
 * What a card shows.
 *
 * Not `visibleColumns`: `hideBelow` is about columns that will not fit beside each other, and a
 * card stacks them — there is room. Only `hideOnCards` applies here.
 */
const cardColumns = computed(() =>
  props.columns.filter((column) => !column.hidden && !column.hideOnCards),
)

const classes = computed(() => [
  'wx-table',
  `wx-table--${props.size}`,
  {
    'wx-table--cards': asCards.value,
    'wx-table--flush': props.flush,
    'wx-table--clickable': clickable.value,
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
/*
 * A tree has an order of its own, and it is the one on screen. Sorting the rows would
 * either scatter the branches or quietly sort inside each of them — the first is wrong
 * and the second is a control that does almost nothing. So in tree mode the heading is
 * a heading.
 */
const sortableIn = (column: TableColumn<T>) => Boolean(column.sortable) && !isTree.value

function onSort(column: TableColumn<T>) {
  if (!sortableIn(column)) return
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
  if (!sortableIn(column)) return undefined
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

/* `WxInput`'s model widened when it learned about languages; this field is never localized. */
function onSearch(value: InputModelValue) {
  search.value = typeof value === 'object' || value === undefined ? '' : String(value)
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
  <div ref="root" :class="classes">
    <header v-if="hasHeader" class="wx-table__header">
      <!--
        Only when there is one. An empty box is still a flex item: it took the first line of the
        head to itself and left the row gap above the search — 12px of nothing over every table
        that has no title, and on a phone, where the tools take a line of their own, it was the
        whole of the space above the field.
      -->
      <div v-if="title || $slots.title" class="wx-table__title">
        <slot name="title">{{ title }}</slot>
      </div>

      <!-- What the filters are set to, in the head rather than under it: the row already has
           the room, and a strip of its own costs a line above every filtered list. -->
      <div v-if="$slots.applied" class="wx-table__applied">
        <slot name="applied" />
      </div>

      <div class="wx-table__tools">
        <slot name="actions" />

        <!--
          The funnel. The panel hangs off the indicator rather than off the button, because the
          trigger has to be one element and `WxAction` carries its tooltip beside itself — its
          root is a fragment (CLAUDE.md §4).
        -->
        <wx-popover
          v-if="$slots.filters"
          :title="filtersLabel"
          :width="filtersWidth"
          side="bottom"
          align="end"
        >
          <template #trigger>
            <wx-indicator
              class="wx-table__filter"
              :value="filtersCount"
              :hidden="filtersCount === 0"
              type="primary"
              :label="filtersLabel"
            >
              <wx-action icon="filter" :size="size" :title="filtersLabel" />
            </wx-indicator>
          </template>

          <div class="wx-table__filter-fields">
            <slot name="filters" />
          </div>
        </wx-popover>

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

    <!--
      Below `cardsBelow` the columns become a card each. The cells are the same cells — the
      same `cell-<key>` slots, the same formatters — so a screen written for the table needs
      nothing added to survive a phone.
    -->
    <div v-if="asCards" class="wx-table__cards">
      <div
        v-for="(row, index) in rows"
        :key="keyOf(row, index)"
        class="wx-table__card"
        :class="[rowClass?.(row, index), { 'is-selected': selectable && isSelected(row, index) }]"
        @click="onRowClick(row, index, $event)"
      >
        <!-- The checkbox where a list puts one, the actions where a thumb reaches them. -->
        <div v-if="selectable || $slots['card-actions']" class="wx-table__card-top" @click.stop>
          <wx-checkbox
            v-if="selectable"
            :model-value="isSelected(row, index)"
            :disabled="!canSelect(row)"
            aria-label="Select row"
            @update:model-value="(checked: boolean) => toggleRow(row, index, checked)"
          />
          <div class="wx-table__card-tools">
            <slot name="card-actions" :row="row" :index="index" />
          </div>
        </div>

        <div v-for="column in cardColumns" :key="column.key" class="wx-table__field">
          <span v-if="column.label" class="wx-table__field-label">{{ column.label }}</span>
          <div class="wx-table__field-value" :class="column.cellClass">
            <slot
              :name="`cell-${column.key}`"
              :row="row"
              :value="read(row, column.key)"
              :index="index"
              :column="column"
            >
              {{ cellText(column, row, index) }}
            </slot>
          </div>
        </div>
      </div>

      <div v-if="rows.length === 0" class="wx-table__cards-empty">
        <slot name="empty">{{ emptyText }}</slot>
      </div>

      <div v-if="showPagination" class="wx-table__cards-footer">
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
      </div>
    </div>

    <div v-else ref="scroller" class="wx-table__scroll" :style="scrollStyle" @scroll="readScroll">
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
                v-if="sortableIn(column)"
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
                  'is-dragging': dragKey !== null && dragKey === treeAt(index)?.key,
                  [`is-drop-${dropZone}`]: dropZone && dropKey === treeAt(index)?.key,
                },
              ]"
              :draggable="treeAt(index) ? canDragRow(treeAt(index)!) : undefined"
              @click="onRowClick(row, index, $event)"
              @dragstart="treeAt(index) && onRowDragStart(treeAt(index)!, $event)"
              @dragover="treeAt(index) && onRowDragOver(treeAt(index)!, $event)"
              @drop.prevent="treeAt(index) && onRowDrop(treeAt(index)!)"
              @dragend="onRowDragEnd"
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
                <!--
                  The tree lives in the first column, ahead of whatever that column
                  draws: indentation for the level, and a disclosure for a branch.
                -->
                <span
                  v-if="treeAt(index) && column.key === visibleColumns[0]?.key"
                  class="wx-table__tree"
                  :style="{
                    paddingInlineStart: `${treeAt(index)!.depth * (treeOptions.indent ?? 20)}px`,
                  }"
                >
                  <button
                    v-if="treeAt(index)!.expandable"
                    class="wx-table__tree-toggle"
                    type="button"
                    :aria-expanded="treeAt(index)!.expanded"
                    :aria-label="treeAt(index)!.expanded ? 'Collapse branch' : 'Expand branch'"
                    @click.stop="toggleBranch(treeAt(index)!)"
                  >
                    <wx-icon
                      :name="treeAt(index)!.loading ? 'loader' : 'chevron-right'"
                      :spin="treeAt(index)!.loading"
                    />
                  </button>
                  <span v-else class="wx-table__tree-toggle is-leaf" />
                </span>

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
   * A column, so that a table given less height than it needs scrolls its own rows instead of
   * spilling out of whatever holds it. Left as a block it grew to its full height and the box
   * around it clipped the overflow — a list of six cards inside a pane 610px tall drew 1793px
   * of them and could not be scrolled by anything, because nothing in the chain had a scroller.
   * Unconstrained — a table on an ordinary page — the height stays `auto` and this changes
   * nothing: the column is as tall as its rows and the page scrolls, as before.
   */
  display: flex;
  flex-direction: column;
  min-height: 0;
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

/* Wraps: a filter and a search field are each wider than a phone can spare, and an input
   will not shrink below its own intrinsic width — it overflows sideways instead. */
.wx-table__tools {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: var(--wx-space-8);
  margin-left: auto;
}

.wx-table__search {
  width: var(--wx-table-search-width, 240px);
  max-width: 100%;
}

/* The funnel keeps its size whatever the row it is in does. */
.wx-table__filter {
  flex: none;
}

/*
 * As tall as the search field beside it, not as tall as an icon button: the two stand in one
 * row and are read as one control, and 36 next to 42 reads as a mistake.
 *
 * Declared on the button rather than on the wrapper. `.wx-action--md` declares this variable on
 * its own element, and a value inherited from an ancestor never beats one declared on the
 * element that reads it (CLAUDE.md §4) — and through `:deep()` because the button is another
 * component's, so a scoped rule would be looking for this component's attribute on it.
 */
.wx-table__filter :deep(.wx-action) {
  --wx-action-size: var(--wx-size-control-md);
}

.wx-table--sm .wx-table__filter :deep(.wx-action) {
  --wx-action-size: var(--wx-size-control-sm);
}

.wx-table--lg .wx-table__filter :deep(.wx-action) {
  --wx-action-size: var(--wx-size-control-lg);
}

/* The panel is a column of fields, spaced the way a form is. */
.wx-table__filter-fields {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

/*
 * The chips take the room the head has spare — between the title and the tools — and wrap into
 * it rather than pushing the search field off the row.
 */
.wx-table__applied {
  display: flex;
  flex: 1 1 auto;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

/*
 * Nothing in it, nothing drawn. `:empty` cannot say this: a `v-if` that rendered nothing
 * leaves a comment node behind, and a comment is a child — `:has(*)` asks about elements,
 * which is the question being asked.
 */
.wx-table__applied:not(:has(*)) {
  display: none;
}

/* Once the rows are cards there is no width to share: the filters become a column of their own. */
.wx-table--cards .wx-table__tools {
  width: 100%;
}

/* Whatever the caller put in `#actions` takes a line of its own: on a phone there is no width
   to share, and a button too narrow to hold its word is not a button. */
.wx-table--cards .wx-table__tools > :not(.wx-table__search):not(.wx-table__filter) {
  flex: 1 1 100%;
}

/*
 * The funnel and the search are the exception, and they keep one line between them: a button
 * with a field beside it is read as one control, and split over two lines it is two — on a
 * phone that is a second of the five lines the head is allowed.
 */
.wx-table--cards .wx-table__search {
  flex: 1 1 auto;
  width: auto;
  min-width: 0;
}

/*
 * Flush means the box around it does the spacing — for the head as much as for the rows.
 *
 * It used to keep the cells' own step as well, so that a search box with a border would not
 * touch the edge of whatever holds it. Inside a card it never does: the card's own padding is
 * already there, and the second inset only stood the head 16px further in than the row of
 * headings under it. Measured: the search ended at 1278 where the table ended at 1294.
 *
 * What is left is the space under it, and that is the panel's step rather than the table's —
 * the head and the rows are two things in a card, and everything else in one is spaced by it.
 */
.wx-table--flush:not(.wx-table--cards) .wx-table__header {
  padding: 0 0 var(--wx-gap, var(--wx-space-16));
}

/* What scrolls when the table is squeezed; the head and the pagination keep their height. */
.wx-table__scroll {
  overflow: auto;
  border-radius: inherit;
  min-height: 0;
}

.wx-table__header,
.wx-table__cards-footer {
  flex: none;
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

/*
 * A tone softer than `--wx-bg-fill`, and that is the whole reason it is not that token: the
 * `···` at the end of the row is filled with `--wx-bg-fill` at rest, so a row that took the
 * same colour swallowed the one control it carries — the button was there, and hovering the
 * row was what made it disappear.
 */
.wx-table--hover .wx-table__row:hover {
  --wx-table-row-bg: var(--wx-bg-subtle);

  background: var(--wx-bg-subtle);
}

/* A striped row is already that colour, so it takes the next one up — nothing to swallow
   there, the buttons of a striped table sit on grey either way. */
.wx-table--stripe.wx-table--hover .wx-table__row.is-striped:hover {
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

/* ---------------------------------------------------------------- tree mode */

/*
 * Inline rather than a flex row: a cell in a table is laid out by the table, and a
 * `display: flex` on it would take the column out of the very grid the pinned columns
 * are measured against.
 */
.wx-table__tree {
  display: inline-flex;
  align-items: center;
  vertical-align: middle;
}

.wx-table__tree-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  margin-inline-end: var(--wx-space-4);
  padding: 0;
  background: none;
  border: none;
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-table__tree-toggle:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-table__tree-toggle:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-table__tree-toggle.is-leaf {
  cursor: default;
  visibility: hidden;
}

.wx-table__tree-toggle :deep(svg) {
  transition: transform 0.15s ease;
}

.wx-table__tree-toggle[aria-expanded='true'] :deep(svg) {
  transform: rotate(90deg);
}

.wx-table__row.is-dragging {
  opacity: 0.4;
}

/*
 * The landing is drawn on the cells rather than on the row: a `<tr>` cannot be given a
 * border that survives a sticky column, and an absolutely positioned line would need a
 * positioned row, which is the corner table layout is least sure about.
 */
.wx-table__row.is-drop-before > .wx-table__cell {
  box-shadow: inset 0 2px 0 0 var(--wx-color-primary);
}

.wx-table__row.is-drop-after > .wx-table__cell {
  box-shadow: inset 0 -2px 0 0 var(--wx-color-primary);
}

.wx-table__row.is-drop-inside > .wx-table__cell {
  background: var(--wx-color-primary-soft);
}

@media (prefers-reduced-motion: reduce) {
  .wx-table__tree-toggle :deep(svg) {
    transition: none;
  }
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
/* --- cards --------------------------------------------------------------- */

.wx-table__cards {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8);
  /* The same as the row scroller: squeezed, the list scrolls rather than being cut off. */
  overflow-y: auto;
  min-height: 0;
}

/*
 * Flush in card mode: the column reaches every edge of the box around it, because that box is
 * what keeps the air. Its own step above and below would be that air twice — measured, the last
 * card stood 16 from the card's edge where the first stood 8 from the search.
 */
.wx-table--flush .wx-table__cards {
  padding: 0;
}

/*
 * And so is the head, for the same reason: its 12 on top of the card's 8 made the space above
 * the search 20 where the space beside it was 8. What is left is the step under it, which is
 * the same one everything else in a card is spaced by.
 */
.wx-table--flush.wx-table--cards .wx-table__header {
  padding: 0 0 var(--wx-gap, var(--wx-space-8));
}

/*
 * Under the search, the step between cards and not the head's own: in card mode the search is
 * one more box in the same column, and a column reads as a column only while the steps down it
 * are equal. The list of cards carries that step as its own top padding.
 */
.wx-table--cards .wx-table__header {
  padding-block-end: 0;
}

.wx-table__card {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  padding: var(--wx-space-10) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
}

.wx-table--hover .wx-table__card:hover {
  border-color: var(--wx-border-strong);
}

.wx-table__card.is-selected {
  border-color: var(--wx-color-primary);
}

.wx-table__card-top {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
}

/* Pushed to the end, so the checkbox keeps the left and the buttons keep the right. */
.wx-table__card-tools {
  margin-left: auto;
}

.wx-table__field {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.wx-table__field-label {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-table__field-value {
  min-width: 0;
  word-break: break-word;
}

.wx-table__cards-empty {
  padding: var(--wx-space-24) var(--wx-space-12);
  color: var(--wx-text-muted);
  text-align: center;
}

.wx-table__cards-footer {
  display: flex;
  justify-content: center;
}
/* A row that opens something says so before it is clicked. */
.wx-table--clickable .wx-table__row,
.wx-table--clickable .wx-table__card {
  cursor: pointer;
}
</style>

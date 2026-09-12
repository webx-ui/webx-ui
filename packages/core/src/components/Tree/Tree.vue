<script setup lang="ts" generic="T extends TreeNode">
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef } from 'vue'
import WxCheckbox from '../Checkbox/Checkbox.vue'
import WxIcon from '../Icon/Icon.vue'
import {
  useTreeNodes,
  type TreeAccessors,
  type TreeDropZone,
  type TreeKey,
  type TreeRow,
} from '../../composables/useTreeNodes'
import type { TreeEmits, TreeNode, TreeProps } from './types'

defineOptions({ name: 'WxTree' })

const props = withDefaults(defineProps<TreeProps<T>>(), {
  nodeKey: 'id',
  labelKey: 'label',
  childrenKey: 'children',
  disabledKey: 'disabled',
  leafKey: 'leaf',
  defaultExpandAll: false,
  accordion: false,
  expandOnClick: false,
  checkable: false,
  checkStrictly: false,
  draggable: false,
  allowDrag: undefined,
  allowDrop: undefined,
  springDelay: 600,
  lazy: false,
  load: undefined,
  filter: undefined,
  showLines: true,
  indent: 20,
  size: 'md',
  emptyText: 'Nothing here yet',
  dragLabel: 'Move',
  ariaLabel: undefined,
})

const emit = defineEmits<TreeEmits<T>>()

defineSlots<{
  /** One node's label. Without it the tree reads `labelKey` and highlights the filter. */
  default?: (props: { node: T; depth: number; expanded: boolean; selected: boolean }) => unknown
  /** The end of a row, where the buttons that act on it go. */
  actions?: (props: { node: T; depth: number }) => unknown
  /** Shown in place of an empty tree. */
  empty?: () => unknown
}>()

const model = defineModel<T[]>({ default: () => [] })
const expandedKeys = defineModel<TreeKey[]>('expanded', { default: () => [] })
const selectedKey = defineModel<TreeKey | null>('selected', { default: null })
const checkedKeys = defineModel<TreeKey[]>('checked', { default: () => [] })

const root = useTemplateRef<HTMLElement>('root')

/*
 * Every field is a prop, so the tree reads the records the backend already sends —
 * `{ id, title, children }` — instead of asking the caller to rename them first. The
 * path is the last resort for identity: enough to render and to open, too weak to
 * survive a move, which is why a real key is worth naming.
 */
const accessors: TreeAccessors<T> = {
  key: (node, path) => (node[props.nodeKey] ?? node.key ?? path.join('.')) as TreeKey,
  children: (node) => node[props.childrenKey] as T[] | undefined,
  setChildren: (node, children) => {
    ;(node as Record<string, unknown>)[props.childrenKey] = children
  },
  label: (node) => String(node[props.labelKey] ?? ''),
  disabled: (node) => Boolean(node[props.disabledKey]),
  leaf: (node) => Boolean(node[props.leafKey]),
}

const tree = useTreeNodes<T>({
  nodes: model,
  accessors,
  expanded: expandedKeys,
  filter: () => props.filter,
  accordion: () => props.accordion,
  lazy: () => props.lazy,
  load: (node) => props.load?.(node) ?? [],
  allowDrop: (drag, drop, zone) => props.allowDrop?.(drag, drop, zone) ?? true,
})

const rows = tree.rows

if (props.defaultExpandAll) tree.expandAll()

const classes = computed(() => [
  'wx-tree',
  `wx-tree--${props.size}`,
  { 'is-lined': props.showLines, 'is-draggable': props.draggable },
])

/* ---------------------------------------------------------------------------
 * Selecting and checking
 * ------------------------------------------------------------------------- */

const checkedSet = computed(() => new Set(checkedKeys.value))

/*
 * A branch is half-checked when something below it is checked and it is not. Working
 * down from the checked keys costs a walk up per checked node; asking every row about
 * its descendants instead would cost a walk down per row, on every render.
 */
const halfChecked = computed(() => {
  const out = new Set<TreeKey>()
  if (props.checkStrictly) return out
  for (const key of checkedSet.value) {
    for (const parent of tree.ancestors(key)) {
      if (!checkedSet.value.has(parent.key)) out.add(parent.key)
    }
  }
  return out
})

function select(row: TreeRow<T>) {
  if (row.disabled) return

  selectedKey.value = row.key
  emit('select', row.node, row.key)

  if (props.expandOnClick && row.expandable) void tree.toggle(row.key)
}

function onClick(row: TreeRow<T>, event: MouseEvent) {
  emit('node-click', row.node, event)
  select(row)
}

function onCheck(row: TreeRow<T>, checked: boolean) {
  const next = new Set(checkedKeys.value)
  const apply = (key: TreeKey, on: boolean) => (on ? next.add(key) : next.delete(key))

  apply(row.key, checked)

  if (!props.checkStrictly) {
    for (const child of tree.descendants(row.key)) {
      if (!accessors.disabled?.(child.node)) apply(child.key, checked)
    }
    // Upwards, nearest first: a parent is checked exactly when all its children are,
    // and each answer is the input to the question one level higher.
    for (const parent of [...tree.ancestors(row.key)].reverse()) {
      const children = parent.children.map((node) => tree.keyOf(node))
      apply(parent.key, children.length > 0 && children.every((key) => next.has(key)))
    }
  }

  checkedKeys.value = [...next]
  emit('check', checkedKeys.value, { node: row.node, checked })
}

async function toggle(row: TreeRow<T>) {
  const open = row.expanded
  await tree.toggle(row.key)
  if (open) emit('collapse', row.node)
  else emit('expand', row.node)
}

/* ---------------------------------------------------------------------------
 * Filter highlighting
 * ------------------------------------------------------------------------- */

/** The label cut into the part before the match, the match, and the rest. */
function parts(row: TreeRow<T>) {
  const label = accessors.label?.(row.node) ?? ''
  const term = props.filter?.trim()
  if (!term || !row.matched) return { before: label, hit: '', after: '' }

  const at = label.toLowerCase().indexOf(term.toLowerCase())
  if (at < 0) return { before: label, hit: '', after: '' }

  return {
    before: label.slice(0, at),
    hit: label.slice(at, at + term.length),
    after: label.slice(at + term.length),
  }
}

/* ---------------------------------------------------------------------------
 * Moving
 * ------------------------------------------------------------------------- */

const announcement = ref('')

const canDrag = (row: TreeRow<T>) =>
  props.draggable && !row.disabled && (props.allowDrag?.(row.node) ?? true)

function commit(
  dragKey: TreeKey,
  dropKey: TreeKey,
  zone: TreeDropZone,
  via: 'pointer' | 'keyboard',
) {
  const target = tree.entry(dropKey)
  const landed = tree.move(dragKey, dropKey, zone)
  if (!landed || !target) return false

  emit('drop', {
    node: landed.node,
    target: target.node,
    zone,
    parent: landed.parent,
    index: landed.index,
    via,
  })
  return true
}

/* ------------------------------------------------------------------ pointer */

const dragKey = ref<TreeKey | null>(null)
const dropKey = ref<TreeKey | null>(null)
const dropZone = ref<TreeDropZone | null>(null)

/** The branch a node is hovering over, and the timer that will open it. */
let springKey: TreeKey | null = null
let springTimer: ReturnType<typeof setTimeout> | undefined

function cancelSpring() {
  if (springTimer) clearTimeout(springTimer)
  springTimer = undefined
  springKey = null
}

/**
 * Dropping into a branch nobody can see the inside of is a guess. Holding a node over a
 * closed one opens it — and fetches it, where the children are not in yet — so the guess
 * becomes a look, and a move across the tree is one drag rather than three.
 */
function spring(row: TreeRow<T>, zone: TreeDropZone) {
  if (!props.springDelay || zone !== 'inside' || !row.expandable || row.expanded) {
    cancelSpring()
    return
  }
  /* Already counting down on this branch: restarting the clock would never finish. */
  if (springKey === row.key) return

  cancelSpring()
  springKey = row.key
  springTimer = setTimeout(() => {
    void tree.expand(row.key)
    emit('expand', row.node)
    cancelSpring()
  }, props.springDelay)
}

function onDragStart(row: TreeRow<T>, event: DragEvent) {
  if (!canDrag(row)) {
    event.preventDefault()
    return
  }
  dragKey.value = row.key
  event.dataTransfer?.setData('text/plain', String(row.key))
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

/**
 * Which third of the row the pointer is over decides the landing: the edges put the node
 * beside the one under the pointer, the middle puts it inside. A leaf accepts the middle
 * too — that is how a leaf becomes a branch.
 */
function zoneAt(event: DragEvent, element: HTMLElement): TreeDropZone {
  const box = element.getBoundingClientRect()
  const y = (event.clientY - box.top) / box.height
  if (y <= 0.3) return 'before'
  if (y >= 0.7) return 'after'
  return 'inside'
}

function onDragOver(row: TreeRow<T>, event: DragEvent) {
  if (dragKey.value === null) return

  const zone = zoneAt(event, event.currentTarget as HTMLElement)
  if (!tree.canDrop(dragKey.value, row.key, zone)) {
    dropKey.value = null
    dropZone.value = null
    cancelSpring()
    return
  }

  // Taking the event is what tells the browser this is a valid drop target.
  event.preventDefault()
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  dropKey.value = row.key
  dropZone.value = zone
  spring(row, zone)
}

function onDrop(row: TreeRow<T>) {
  if (dragKey.value === null || dropZone.value === null || dropKey.value !== row.key) return
  commit(dragKey.value, row.key, dropZone.value, 'pointer')
  onDragEnd()
}

onBeforeUnmount(cancelSpring)

function onDragEnd() {
  cancelSpring()
  dragKey.value = null
  dropKey.value = null
  dropZone.value = null
}

/* ----------------------------------------------------------------- keyboard */

/**
 * The same four moves without a mouse. `Alt` is what separates them from plain arrow
 * navigation, and the announcement is what a screen reader gets instead of the
 * animation a sighted user sees.
 */
function keyboardMove(row: TreeRow<T>, key: string) {
  if (!canDrag(row)) return false

  const siblings = row.siblings
  const at = row.index
  const name = accessors.label?.(row.node) ?? String(row.key)

  const say = (text: string) => {
    announcement.value = text
    void refocus(row.key)
  }

  if (key === 'ArrowUp' && at > 0) {
    if (!commit(row.key, tree.keyOf(siblings[at - 1]!, [at - 1]), 'before', 'keyboard'))
      return false
    say(`${name} is now ${at} of ${siblings.length}.`)
    return true
  }

  if (key === 'ArrowDown' && at < siblings.length - 1) {
    if (!commit(row.key, tree.keyOf(siblings[at + 1]!, [at + 1]), 'after', 'keyboard')) return false
    say(`${name} is now ${at + 2} of ${siblings.length}.`)
    return true
  }

  // Right nests under the node above, which is the only place a node can go down a
  // level without choosing between two parents.
  if (key === 'ArrowRight' && at > 0) {
    const parent = siblings[at - 1]!
    if (!commit(row.key, tree.keyOf(parent, [at - 1]), 'inside', 'keyboard')) return false
    say(`${name} is now inside ${accessors.label?.(parent) ?? ''}.`)
    return true
  }

  if (key === 'ArrowLeft' && row.parentKey !== null) {
    const parent = tree.entry(row.parentKey)
    if (!parent) return false
    if (!commit(row.key, parent.key, 'after', 'keyboard')) return false
    say(`${name} is now after ${accessors.label?.(parent.node) ?? ''}.`)
    return true
  }

  announcement.value = `${name} cannot move there.`
  return false
}

const activeKey = ref<TreeKey | null>(null)

const focusKey = computed(() => {
  const keys = rows.value.map((row) => row.key)
  if (activeKey.value !== null && keys.includes(activeKey.value)) return activeKey.value
  if (selectedKey.value !== null && keys.includes(selectedKey.value)) return selectedKey.value
  return keys[0] ?? null
})

/*
 * By position rather than by a selector built from the key: a key is whatever the
 * backend calls an id, and building a selector out of it is one slug with a dot in it
 * away from matching nothing.
 */
async function refocus(key: TreeKey) {
  activeKey.value = key
  await nextTick()
  const at = rows.value.findIndex((row) => row.key === key)
  if (at < 0) return
  root.value?.querySelectorAll<HTMLElement>('.wx-tree__row')[at]?.focus()
}

function step(from: number) {
  const row = rows.value[from]
  if (row) void refocus(row.key)
}

async function onKeydown(row: TreeRow<T>, event: KeyboardEvent) {
  const at = rows.value.indexOf(row)

  if (event.altKey && event.key.startsWith('Arrow')) {
    event.preventDefault()
    keyboardMove(row, event.key)
    return
  }

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      step(at + 1)
      break
    case 'ArrowUp':
      event.preventDefault()
      step(at - 1)
      break
    case 'ArrowRight':
      event.preventDefault()
      if (row.expandable && !row.expanded) await toggle(row)
      else step(at + 1)
      break
    case 'ArrowLeft':
      event.preventDefault()
      if (row.expanded) await toggle(row)
      else if (row.parentKey !== null) void refocus(row.parentKey)
      break
    case 'Home':
      event.preventDefault()
      step(0)
      break
    case 'End':
      event.preventDefault()
      step(rows.value.length - 1)
      break
    case 'Enter':
      event.preventDefault()
      select(row)
      break
    case ' ':
      event.preventDefault()
      if (props.checkable) onCheck(row, !checkedSet.value.has(row.key))
      else select(row)
      break
  }
}

defineExpose({
  /** Opens every branch on the way to a node. */
  reveal: (key: TreeKey) => tree.reveal(key),
  /**
   * Opens a named path one level at a time, waiting for each lazy level before asking
   * for the next — the way to reach a node the tree has not fetched yet.
   */
  openPath: (keys: TreeKey[]) => tree.openPath(keys),
  expandAll: () => tree.expandAll(),
  collapseAll: () => tree.collapseAll(),
  /** The node itself and everything above it — a breadcrumb of the tree. */
  path: (key: TreeKey) => tree.path(key).map((entry) => entry.node),
})
</script>

<template>
  <div
    ref="root"
    :class="classes"
    :style="{ '--wx-tree-indent': `${indent}px` }"
    role="tree"
    :aria-label="ariaLabel"
    :aria-multiselectable="checkable || undefined"
  >
    <div
      v-for="row in rows"
      :key="row.key"
      class="wx-tree__row"
      :class="{
        'is-selected': selectedKey === row.key,
        'is-disabled': row.disabled,
        'is-matched': row.matched,
        'is-dragging': dragKey === row.key,
        [`is-drop-${dropZone}`]: dropKey === row.key && dropZone,
      }"
      :style="{ '--wx-tree-depth': row.depth }"
      :data-key="row.key"
      role="treeitem"
      :tabindex="focusKey === row.key ? 0 : -1"
      :aria-level="row.depth + 1"
      :aria-posinset="row.index + 1"
      :aria-setsize="row.siblings.length"
      :aria-expanded="row.expandable ? row.expanded : undefined"
      :aria-selected="selectedKey === row.key"
      :aria-checked="checkable ? checkedSet.has(row.key) : undefined"
      :aria-disabled="row.disabled || undefined"
      :draggable="canDrag(row)"
      @click="onClick(row, $event)"
      @keydown="onKeydown(row, $event)"
      @focus="activeKey = row.key"
      @dragstart.stop="onDragStart(row, $event)"
      @dragover="onDragOver(row, $event)"
      @drop.prevent="onDrop(row)"
      @dragend="onDragEnd"
    >
      <!--
        Decorative: the row itself is what a pointer drags and what a keyboard moves, so
        the grip is there to say so rather than to be operated on its own.
      -->
      <span v-if="draggable" class="wx-tree__grip" :title="dragLabel" aria-hidden="true">
        <wx-icon name="drag" />
      </span>

      <!-- One per level, so the guides line up with the toggles above them. -->
      <span v-for="level in row.depth" :key="level" class="wx-tree__guide" />

      <button
        v-if="row.expandable"
        type="button"
        class="wx-tree__toggle"
        :aria-expanded="row.expanded"
        :aria-label="`${row.expanded ? 'Collapse' : 'Expand'} ${accessors.label?.(row.node)}`"
        tabindex="-1"
        @click.stop="toggle(row)"
      >
        <wx-icon :name="row.loading ? 'loader' : 'chevron-right'" :spin="row.loading" />
      </button>
      <span v-else class="wx-tree__toggle is-leaf" />

      <!--
        The wrapper takes the click: a click on a `<label>` reaches the row as well as
        the input inside it, and a tick is not also a selection.
      -->
      <span v-if="checkable" class="wx-tree__check" @click.stop>
        <wx-checkbox
          :model-value="checkedSet.has(row.key)"
          :indeterminate="halfChecked.has(row.key)"
          :disabled="row.disabled"
          :size="size"
          :aria-label="accessors.label?.(row.node)"
          tabindex="-1"
          @change="onCheck(row, $event)"
        />
      </span>

      <span class="wx-tree__label">
        <slot
          :node="row.node"
          :depth="row.depth"
          :expanded="row.expanded"
          :selected="selectedKey === row.key"
        >
          {{ parts(row).before
          }}<mark v-if="parts(row).hit" class="wx-tree__hit">{{ parts(row).hit }}</mark
          >{{ parts(row).after }}
        </slot>
      </span>

      <span v-if="$slots.actions" class="wx-tree__actions" @click.stop>
        <slot name="actions" :node="row.node" :depth="row.depth" />
      </span>
    </div>

    <div v-if="!rows.length" class="wx-tree__empty">
      <slot name="empty">{{ emptyText }}</slot>
    </div>

    <!-- What a keyboard move says out loud. -->
    <div class="wx-tree__live" role="status" aria-live="polite">{{ announcement }}</div>
  </div>
</template>

<style scoped>
.wx-tree {
  position: relative;
  box-sizing: border-box;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
}

.wx-tree__row {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-height: 32px;
  /*
   * Inside the row rather than around the tree, so the highlight still runs edge to
   * edge while what sits in the row keeps its distance from it. The block half is what
   * a row full of buttons needs: the caller's actions are taller than the text, and
   * without it they end up a pixel from the edge of the highlight. A row of plain
   * labels is shorter than `min-height` either way, so nothing is spent where there
   * is nothing to clear.
   */
  padding-block: var(--wx-space-4);
  padding-inline: var(--wx-space-8);
  border-radius: var(--wx-radius-control);
  cursor: pointer;
  user-select: none;
}

/*
 * The small size is density, not small print: a node's label is the content, and
 * shrinking the text of a list of names buys a couple of pixels at the cost of the
 * thing being read. What gives is the room around it.
 */
.wx-tree--sm .wx-tree__row {
  min-height: 26px;
  padding-block: var(--wx-space-2);
  line-height: var(--wx-font-line-height-tight);
}

.wx-tree__row:hover {
  background: var(--wx-bg-subtle);
}

.wx-tree__row:focus-visible {
  outline: 2px solid var(--wx-color-primary);
  outline-offset: -2px;
}

.wx-tree__row.is-selected {
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
  font-weight: var(--wx-font-weight-medium);
}

.wx-tree__row.is-disabled {
  color: var(--wx-text-disabled);
  cursor: default;
}

.wx-tree__row.is-dragging {
  opacity: 0.4;
}

/* The guide is a level of indentation that also draws the line down from the parent. */
.wx-tree__guide {
  flex: none;
  align-self: stretch;
  width: var(--wx-tree-indent, 20px);
}

.wx-tree.is-lined .wx-tree__guide {
  border-inline-start: 1px solid var(--wx-border-muted);
}

.wx-tree__toggle {
  flex: none;
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  border-radius: var(--wx-radius-xs);
  background: none;
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-tree__toggle:hover {
  background: var(--wx-bg-fill-hover);
  color: var(--wx-text-default);
}

.wx-tree__toggle.is-leaf {
  cursor: default;
  visibility: hidden;
}

.wx-tree__toggle :deep(svg) {
  transition: transform 0.15s ease;
}

.wx-tree__row[aria-expanded='true'] > .wx-tree__toggle :deep(svg) {
  transform: rotate(90deg);
}

.wx-tree__grip {
  flex: none;
  display: grid;
  place-items: center;
  width: 16px;
  color: var(--wx-text-placeholder);
  cursor: grab;
  opacity: 0;
}

.wx-tree__row:hover .wx-tree__grip,
.wx-tree__row:focus-visible .wx-tree__grip {
  opacity: 1;
}

/* Flex, not block: an inline-flex checkbox in a block sits in a line box, and the
   leading around it makes the wrapper taller than the control it holds. */
.wx-tree__check {
  flex: none;
  display: flex;
  align-items: center;
  margin-inline-end: var(--wx-space-2);
}

.wx-tree__label {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-tree__hit {
  background: var(--wx-color-warning-soft);
  color: inherit;
  border-radius: var(--wx-radius-none);
}

.wx-tree__actions {
  flex: none;
  display: flex;
  align-items: center;
  gap: var(--wx-space-2);
  margin-inline-start: var(--wx-space-8);
  opacity: 0;
}

.wx-tree__row:hover .wx-tree__actions,
.wx-tree__row:focus-within .wx-tree__actions {
  opacity: 1;
}

/*
 * Where the node will land. The line starts at the indentation of the target, so that
 * "before" and "after" read as a position in the list rather than a rule across it.
 */
.wx-tree__row.is-drop-before::after,
.wx-tree__row.is-drop-after::after {
  content: '';
  position: absolute;
  inset-inline: calc(var(--wx-space-8) + var(--wx-tree-depth, 0) * var(--wx-tree-indent, 20px))
    var(--wx-space-8);
  height: 2px;
  border-radius: var(--wx-radius-full);
  background: var(--wx-color-primary);
  pointer-events: none;
}

.wx-tree__row.is-drop-before::after {
  top: -1px;
}

.wx-tree__row.is-drop-after::after {
  bottom: -1px;
}

.wx-tree__row.is-drop-inside {
  background: var(--wx-color-primary-soft);
  box-shadow: inset 0 0 0 2px var(--wx-color-primary);
}

.wx-tree__empty {
  padding: var(--wx-space-16);
  color: var(--wx-text-muted);
  text-align: center;
}

.wx-tree__live {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

@media (prefers-reduced-motion: reduce) {
  .wx-tree__toggle :deep(svg) {
    transition: none;
  }
}
</style>

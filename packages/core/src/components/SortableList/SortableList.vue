<script setup lang="ts" generic="T">
import { computed, nextTick, ref, useSlots, useTemplateRef } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import WxIcon from '../Icon/Icon.vue'
import type { SortableListEmits, SortableListProps, SortableVia } from './types'

defineOptions({ name: 'WxSortableList' })

const props = withDefaults(defineProps<SortableListProps<T>>(), {
  title: undefined,
  handle: 'grip',
  itemKey: 'id',
  group: undefined,
  disabled: false,
  size: 'md',
  plain: false,
  emptyText: 'Nothing here yet',
  dragLabel: 'Reorder',
  ariaLabel: undefined,
})

const emit = defineEmits<SortableListEmits<T>>()

defineSlots<{
  /** Replaces the title. */
  header?: () => unknown
  /** The end of the heading — a search button, a menu, whatever the list can do. */
  extra?: () => unknown
  /** One row. */
  default?: (props: { item: T; index: number }) => unknown
  /** The end of a row, where the buttons that act on it go. */
  actions?: (props: { item: T; index: number }) => unknown
  /** Shown in place of an empty list. */
  empty?: () => unknown
}>()

const items = defineModel<T[]>({ default: () => [] })

const slots = useSlots()

const root = useTemplateRef<HTMLElement>('root')

const hasHeader = computed(() => Boolean(props.title || slots.header || slots.extra))

/*
 * The grip is ours by default, because a list whose rows can only be moved by dragging
 * them bodily is a list a keyboard cannot reorder at all — and because the thing that
 * starts a drag has to look like it does. `handle` gives that up to the caller: the whole
 * row, or a selector for a button of their own.
 */
const grip = computed(() => props.handle === 'grip')

const handleSelector = computed(() => {
  if (props.handle === 'grip') return '.wx-sortable-list__grip'
  if (props.handle === 'row') return undefined
  return props.handle
})

const classes = computed(() => [
  'wx-sortable-list',
  `wx-sortable-list--${props.size}`,
  { 'is-plain': props.plain, 'is-disabled': props.disabled, 'is-headed': hasHeader.value },
])

function keyOf(item: T, index: number) {
  if (typeof props.itemKey === 'function') return props.itemKey(item, index)
  if (item && typeof item === 'object') {
    const value = (item as Record<string, unknown>)[props.itemKey]
    if (typeof value === 'string' || typeof value === 'number') return value
  }
  return index
}

/**
 * What to call a row when there is nothing but the item itself to go on: the grip needs
 * an accessible name, and so does everything a keyboard move says out loud.
 */
function labelOf(item: T, index: number) {
  if (item && typeof item === 'object') {
    const record = item as Record<string, unknown>
    for (const field of ['title', 'name', 'label']) {
      const value = record[field]
      if (typeof value === 'string' && value) return value
    }
    return `Item ${index + 1}`
  }
  return String(item)
}

function nameAt(index: number) {
  const item = items.value[index]
  return item === undefined ? `Item ${index + 1}` : labelOf(item, index)
}

/* ---------------------------------------------------------------------------
 * Moving
 * ------------------------------------------------------------------------- */

function move(from: number, to: number, via: SortableVia) {
  if (to < 0 || to >= items.value.length || from === to) return

  const next = [...items.value]
  const [item] = next.splice(from, 1)
  if (item === undefined) return
  next.splice(to, 0, item)
  items.value = next

  emit('move', { item, from, to, via })
}

/*
 * Which row is under the pointer, remembered when the drag begins. By the time it ends
 * the library has rewritten the list and told the parent, but the new array has not come
 * back down as a prop yet — so reading the row out of the model at that point gets
 * whatever used to be at that position. It is the same reason a keyboard move reads the
 * name before it moves anything.
 */
let dragged: T | undefined

function onLift(event: { oldIndex?: number }) {
  dragged = items.value[event.oldIndex ?? -1]
}

/**
 * A pointer drag is over by the time this runs, so all that is left is to say what
 * happened. A row dragged into another list is left alone: two models changed, and
 * neither of them is a move within this one.
 */
function onDrop(event: {
  from: HTMLElement
  to: HTMLElement
  oldIndex?: number
  newIndex?: number
}) {
  const item = dragged
  dragged = undefined

  if (event.from !== event.to || item === undefined) return

  const from = event.oldIndex ?? -1
  const to = event.newIndex ?? -1
  if (from < 0 || to < 0 || from === to) return

  emit('move', { item, from, to, via: 'pointer' })
}

/* ---------------------------------------------------------------------------
 * Moving without a mouse
 *
 * SortableJS is a pointer library and has nothing to say to a keyboard, so the list
 * carries its own: space picks a row up, the arrows move it, space drops it, escape puts
 * it back. It is the same splice a drag performs; what a keyboard needs on top is
 * somewhere to read the result, and that is the live region.
 * ------------------------------------------------------------------------- */

const grabbed = ref<number | null>(null)

const origin = ref(-1)

const announcement = ref('')

function rowElements() {
  return root.value?.querySelectorAll<HTMLElement>(':scope > .wx-sortable-list__body > li') ?? []
}

/** The row is somewhere else in the DOM now, so the focus has to follow it there. */
async function refocus(index: number) {
  await nextTick()
  const row = rowElements()[index]
  if (!row) return
  const target = grip.value
    ? row.querySelector<HTMLElement>(':scope > .wx-sortable-list__grip')
    : row
  target?.focus()
}

function onKeydown(event: KeyboardEvent, index: number) {
  /* A button inside the row keeps its own keys. */
  if (props.disabled || event.target !== event.currentTarget) return

  const total = items.value.length
  const held = grabbed.value === index

  if (event.key === ' ' || event.key === 'Spacebar' || event.key === 'Enter') {
    event.preventDefault()
    if (held) {
      grabbed.value = null
      announcement.value = `Dropped ${nameAt(index)}, ${index + 1} of ${total}.`
    } else {
      grabbed.value = index
      origin.value = index
      announcement.value = `Picked up ${nameAt(index)}. Use the arrow keys to move it, space to drop it, escape to put it back.`
    }
    return
  }

  if (!held) return

  if (event.key === 'Escape') {
    event.preventDefault()
    grabbed.value = null
    move(index, origin.value, 'keyboard')
    announcement.value = 'Move cancelled.'
    void refocus(origin.value)
    return
  }

  const up = event.key === 'ArrowUp' || event.key === 'ArrowLeft'
  const down = event.key === 'ArrowDown' || event.key === 'ArrowRight'
  if (!up && !down) return

  event.preventDefault()
  const to = index + (up ? -1 : 1)
  if (to < 0 || to >= total) {
    announcement.value = `${nameAt(index)} is already ${index + 1} of ${total}.`
    return
  }

  /* Read before the move: a parent that writes the model back can take a tick over it. */
  const name = nameAt(index)
  move(index, to, 'keyboard')
  grabbed.value = to
  announcement.value = `${name} is now ${to + 1} of ${total}.`
  void refocus(to)
}
</script>

<template>
  <div ref="root" :class="classes">
    <header v-if="hasHeader" class="wx-sortable-list__head">
      <h3 class="wx-sortable-list__title">
        <slot name="header">{{ title }}</slot>
      </h3>
      <div v-if="$slots.extra" class="wx-sortable-list__extra">
        <slot name="extra" />
      </div>
    </header>

    <vue-draggable
      v-model="items"
      tag="ul"
      class="wx-sortable-list__body"
      :group="group"
      :disabled="disabled"
      :handle="handleSelector"
      draggable=".wx-sortable-list__row"
      :animation="160"
      :delay="150"
      :delay-on-touch-only="true"
      filter="button, a, input, .wx-sortable-list__actions"
      :prevent-on-filter="false"
      ghost-class="wx-sortable-list__row--ghost"
      chosen-class="wx-sortable-list__row--chosen"
      :aria-label="ariaLabel"
      @start="onLift"
      @end="onDrop"
    >
      <li
        v-for="(item, index) in items"
        :key="keyOf(item, index)"
        class="wx-sortable-list__row"
        :class="{ 'is-grabbed': grabbed === index }"
        :tabindex="grip || disabled ? undefined : 0"
        :role="grip || disabled ? undefined : 'button'"
        :aria-roledescription="grip || disabled ? undefined : 'Sortable row'"
        :aria-pressed="grip || disabled ? undefined : grabbed === index"
        @keydown="onKeydown($event, index)"
      >
        <!--
          A span with the role rather than a button, so that the `filter` below can keep a
          drag from starting on the buttons in a row without also disarming the grip.
        -->
        <span
          v-if="grip"
          class="wx-sortable-list__grip"
          :tabindex="disabled ? -1 : 0"
          :role="disabled ? undefined : 'button'"
          :aria-label="`${dragLabel}: ${labelOf(item, index)}`"
          aria-roledescription="Drag handle"
          :aria-pressed="disabled ? undefined : grabbed === index"
          @keydown="onKeydown($event, index)"
        >
          <wx-icon name="drag" />
        </span>

        <div class="wx-sortable-list__content">
          <slot :item="item" :index="index">{{ labelOf(item, index) }}</slot>
        </div>

        <div v-if="$slots.actions" class="wx-sortable-list__actions">
          <slot name="actions" :item="item" :index="index" />
        </div>
      </li>

      <!--
        Inside the list rather than after it, so that an empty list is still somewhere a
        row from another list can be dropped. `draggable` above keeps it out of the order.
      -->
      <li v-if="!items.length" class="wx-sortable-list__empty">
        <slot name="empty">{{ emptyText }}</slot>
      </li>
    </vue-draggable>

    <!-- What a keyboard move says out loud. -->
    <div class="wx-sortable-list__live" role="status" aria-live="polite">{{ announcement }}</div>
  </div>
</template>

<style scoped>
.wx-sortable-list {
  position: relative;
  box-sizing: border-box;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

/* Inside a card that already has a frame, the list brings only its rules. */
.wx-sortable-list.is-plain {
  background: none;
  border: none;
  border-radius: 0;
}

.wx-sortable-list__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-12) var(--wx-space-16);
  border-bottom: 1px solid var(--wx-border-muted);
}

.is-plain .wx-sortable-list__head {
  padding-inline: 0;
}

.wx-sortable-list__title {
  flex: 1 1 auto;
  min-width: 0;
  margin: 0;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-sortable-list__extra {
  display: flex;
  align-items: center;
  flex: 0 0 auto;
  gap: var(--wx-space-8);
}

.wx-sortable-list__body {
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-sortable-list__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  /*
   * The row owns its margins, and says so from a scoped rule so that it outweighs a host
   * stylesheet spacing list items — VitePress puts 8px between every `li + li`, and a CMS
   * theme will have its own.
   */
  margin: 0;
  padding: var(--wx-space-10) var(--wx-space-16);
  /*
   * A row paints no background of its own. It would be the same colour as the list it sits
   * in, and a square corner over a rounded one is how the frame loses its corners.
   */
  background: none;
  /*
   * Dragging is what a pointer is for here, and the first thing anybody tries is the row
   * itself. Without this that gesture smears a text selection across the list.
   */
  user-select: none;
}

/* The corners the frame is rounded by, so a tinted row does not square them off again. */
.wx-sortable-list:not(.is-plain) .wx-sortable-list__row:last-child {
  border-end-start-radius: calc(var(--wx-radius-md) - 1px);
  border-end-end-radius: calc(var(--wx-radius-md) - 1px);
}

.wx-sortable-list:not(.is-plain, .is-headed) .wx-sortable-list__row:first-child {
  border-start-start-radius: calc(var(--wx-radius-md) - 1px);
  border-start-end-radius: calc(var(--wx-radius-md) - 1px);
}

.is-plain .wx-sortable-list__row {
  padding-inline: 0;
}

.wx-sortable-list--sm .wx-sortable-list__row {
  gap: var(--wx-space-8);
  padding-block: var(--wx-space-6);
  font-size: var(--wx-font-size-sm);
}

.wx-sortable-list__row + .wx-sortable-list__row {
  border-top: 1px solid var(--wx-border-muted);
}

/*
 * The row runs the full width of the frame, so its ring is drawn inside it: a halo around
 * a row that wide lies over the frame's own border and past its corners.
 */
.wx-sortable-list__row:focus-visible,
.wx-sortable-list__row.is-grabbed {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: -2px;
}

/* Held by the keyboard: the ring, and the tint that says this one is in hand. */
.wx-sortable-list__row.is-grabbed {
  background: var(--wx-bg-muted);
}

.wx-sortable-list__grip:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
  border-radius: var(--wx-radius-xs);
}

.wx-sortable-list__grip {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 24px;
  height: 24px;
  color: var(--wx-text-placeholder);
  cursor: grab;
}

.wx-sortable-list__grip:hover {
  color: var(--wx-text-muted);
}

.is-disabled .wx-sortable-list__grip {
  cursor: default;
  opacity: 0.5;
}

.wx-sortable-list__content {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-sortable-list__actions {
  display: flex;
  align-items: center;
  flex: 0 0 auto;
  gap: var(--wx-space-4);
}

/* What is left behind in the list while the row is being dragged. */
.wx-sortable-list__row--ghost {
  opacity: 0.4;
}

/* The one under the pointer is opaque, whatever it is passing over. */
.wx-sortable-list__row--chosen {
  background: var(--wx-bg-surface);
  cursor: grabbing;
}

.wx-sortable-list__empty {
  margin: 0;
  padding: var(--wx-space-16);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-align: center;
}

.is-plain .wx-sortable-list__empty {
  padding-inline: 0;
}

/* Read out, never drawn. */
.wx-sortable-list__live {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  border: 0;
  clip-path: inset(50%);
  white-space: nowrap;
}
</style>

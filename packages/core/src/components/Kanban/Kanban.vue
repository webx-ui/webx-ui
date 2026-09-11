<script setup lang="ts" generic="T extends KanbanCard = KanbanCard">
import { computed, nextTick, ref, useId, useTemplateRef } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import WxBadge from '../Badge/Badge.vue'
import WxButton from '../Button/Button.vue'
import WxEntityCard from '../EntityCard/EntityCard.vue'
import WxIcon from '../Icon/Icon.vue'
import type { KanbanCard, KanbanColumn, KanbanEmits, KanbanId, KanbanProps } from './types'

defineOptions({ name: 'WxKanban', inheritAttrs: false })

const props = withDefaults(defineProps<KanbanProps<T>>(), {
  group: undefined,
  size: 'md',
  columnWidth: 288,
  disabled: false,
  handle: undefined,
  addable: false,
  addLabel: 'Add a card',
  emptyText: 'Nothing here yet',
  ariaLabel: undefined,
})

const emit = defineEmits<KanbanEmits<T>>()

defineSlots<{
  /** One card. Without it the board falls back to the card's own title. */
  card?: (props: { card: T; column: KanbanColumn<T>; index: number }) => unknown
  /** Replaces the title and the count above a column. */
  'column-header'?: (props: {
    column: KanbanColumn<T>
    count: number
    overLimit: boolean
  }) => unknown
  /** Under the cards, where the add button would be. */
  'column-footer'?: (props: { column: KanbanColumn<T> }) => unknown
  /** Shown in a column holding no cards. */
  empty?: (props: { column: KanbanColumn<T> }) => unknown
  /** After the last column — where a button that adds a column belongs. */
  default?: () => unknown
}>()

const root = useTemplateRef<HTMLElement>('root')

/** Boards exchange cards only when they are told to share a name. */
const ownGroup = `wx-kanban-${useId()}`
const groupName = computed(() => props.group ?? ownGroup)

const boardStyle = computed(() => ({
  '--wx-kanban-column-width':
    typeof props.columnWidth === 'number' ? `${props.columnWidth}px` : props.columnWidth,
}))

function isFull(column: KanbanColumn<T>) {
  return column.limit !== undefined && column.items.length >= column.limit
}

function isLocked(column: KanbanColumn<T>) {
  return props.disabled || Boolean(column.disabled)
}

function columnById(id: KanbanId | string) {
  return props.columns.find((column) => String(column.id) === String(id))
}

function cardTitle(card: T) {
  const named = card as { title?: unknown; name?: unknown; label?: unknown }
  return String(named.title ?? named.name ?? named.label ?? card.id)
}

/**
 * A work-in-progress limit is only worth drawing if it also refuses the card. The
 * column's own group decides what may be dropped into it; reordering inside a full
 * column stays allowed, since that does not make it any fuller.
 */
function groupFor(column: KanbanColumn<T>) {
  return {
    name: groupName.value,
    pull: !isLocked(column),
    put: (to: unknown, from: unknown) => {
      if (isLocked(column)) return false
      return to === from || !isFull(column)
    },
  }
}

/* ---------------------------------------------------------------------------
 * Moving a card
 * ------------------------------------------------------------------------- */

function move(
  from: KanbanColumn<T>,
  fromIndex: number,
  to: KanbanColumn<T>,
  toIndex: number,
  via: 'pointer' | 'keyboard',
) {
  const [card] = from.items.splice(fromIndex, 1)
  if (!card) return

  to.items.splice(toIndex, 0, card)
  emit('move', {
    card,
    from: { column: from.id, index: fromIndex },
    to: { column: to.id, index: toIndex },
    via,
  })
}

/**
 * A pointer drag is over by the time this runs — the library moved the card between
 * the arrays itself, and all that is left is to say what happened. Each list carries
 * its column id in the DOM so the event can be read back into our own terms.
 */
function onDrop(event: {
  from: HTMLElement
  to: HTMLElement
  oldIndex?: number
  newIndex?: number
}) {
  const fromId = event.from.dataset.columnId
  const toId = event.to.dataset.columnId
  const fromIndex = event.oldIndex ?? -1
  const toIndex = event.newIndex ?? -1
  if (fromId === undefined || toId === undefined || fromIndex < 0 || toIndex < 0) return
  if (fromId === toId && fromIndex === toIndex) return

  const to = columnById(toId)
  const card = to?.items[toIndex]
  if (!to || !card) return

  emit('move', {
    card,
    from: { column: columnById(fromId)?.id ?? fromId, index: fromIndex },
    to: { column: to.id, index: toIndex },
    via: 'pointer',
  })
}

/* ---------------------------------------------------------------------------
 * Moving a card without a mouse
 *
 * SortableJS is a pointer library and has nothing to say to a keyboard, so the board
 * carries its own: space picks a card up, the arrows move it, space drops it, escape
 * puts it back. It is the same two array operations a drag performs; what a keyboard
 * needs on top is somewhere to read the result, and that is the live region.
 * ------------------------------------------------------------------------- */

const grabbed = ref<{
  id: KanbanId
  column: KanbanId
  origin: { column: KanbanId; index: number }
} | null>(null)

const announcement = ref('')

function isGrabbed(card: T) {
  return grabbed.value?.id === card.id
}

function positionOf(card: T, column: KanbanColumn<T>) {
  return `${column.items.indexOf(card) + 1} of ${column.items.length}`
}

function nameOf(column: KanbanColumn<T>) {
  return String(column.title ?? column.id)
}

/**
 * The card has moved to another element, so the focus has to follow it there. The id
 * is compared rather than written into a selector: an id is the application's, and
 * quoting one into CSS needs an escape that not every environment has.
 */
async function refocus(card: T) {
  await nextTick()

  const wanted = String(card.id)
  const elements = root.value?.querySelectorAll<HTMLElement>('[data-card-id]') ?? []
  for (const element of elements) {
    if (element.dataset.cardId === wanted) {
      element.focus()
      return
    }
  }
}

function grab(card: T, column: KanbanColumn<T>, index: number) {
  grabbed.value = { id: card.id, column: column.id, origin: { column: column.id, index } }
  announcement.value = `Picked up ${cardTitle(card)}. Use the arrow keys to move it, space to drop it, escape to put it back.`
}

function drop(card: T, column: KanbanColumn<T>) {
  grabbed.value = null
  announcement.value = `Dropped ${cardTitle(card)} in ${nameOf(column)}, ${positionOf(card, column)}.`
}

function cancel(card: T) {
  const state = grabbed.value
  grabbed.value = null
  if (!state) return

  const current = columnById(state.column)
  const origin = columnById(state.origin.column)
  if (!current || !origin) return

  const index = current.items.indexOf(card)
  if (index >= 0) move(current, index, origin, state.origin.index, 'keyboard')

  announcement.value = 'Move cancelled.'
  void refocus(card)
}

function step(card: T, column: KanbanColumn<T>, offset: number) {
  const index = column.items.indexOf(card)
  const next = index + offset
  if (next < 0 || next >= column.items.length) return

  move(column, index, column, next, 'keyboard')
  announcement.value = `${cardTitle(card)} is now ${positionOf(card, column)} in ${nameOf(column)}.`
  void refocus(card)
}

function shift(card: T, column: KanbanColumn<T>, offset: number) {
  const columnIndex = props.columns.indexOf(column)

  /* Past a column that refuses the card there may still be one that takes it. */
  for (let i = columnIndex + offset; i >= 0 && i < props.columns.length; i += offset) {
    const target = props.columns[i]
    if (isLocked(target) || isFull(target)) continue

    const index = column.items.indexOf(card)
    move(column, index, target, Math.min(index, target.items.length), 'keyboard')

    if (grabbed.value) grabbed.value = { ...grabbed.value, column: target.id }
    announcement.value = `${cardTitle(card)} moved to ${nameOf(target)}, ${positionOf(card, target)}.`
    void refocus(card)
    return
  }

  announcement.value = `${nameOf(column)} is the last column that can take ${cardTitle(card)}.`
}

function onCardKeydown(event: KeyboardEvent, card: T, column: KanbanColumn<T>, index: number) {
  /* A button or a link inside a card keeps its own keys. */
  if (event.target !== event.currentTarget) return
  if (isLocked(column)) return

  const held = isGrabbed(card)

  if (event.key === ' ' || event.key === 'Enter') {
    event.preventDefault()
    if (held) drop(card, column)
    else grab(card, column, index)
    return
  }

  if (!held) return

  if (event.key === 'Escape') {
    event.preventDefault()
    cancel(card)
    return
  }

  const moves: Record<string, () => void> = {
    ArrowUp: () => step(card, column, -1),
    ArrowDown: () => step(card, column, 1),
    ArrowLeft: () => shift(card, column, -1),
    ArrowRight: () => shift(card, column, 1),
  }

  const run = moves[event.key]
  if (!run) return

  event.preventDefault()
  run()
}

const classes = computed(() => ['wx-kanban', `wx-kanban--${props.size}`])

defineExpose({ move })
</script>

<template>
  <div
    ref="root"
    v-bind="$attrs"
    :class="classes"
    :style="boardStyle"
    role="group"
    :aria-label="ariaLabel"
  >
    <div class="wx-kanban__scroller">
      <section
        v-for="column in columns"
        :key="column.id"
        class="wx-kanban__column"
        :class="[
          `wx-kanban__column--${column.tone ?? 'default'}`,
          { 'is-locked': isLocked(column) },
        ]"
      >
        <header class="wx-kanban__head">
          <slot
            name="column-header"
            :column="column"
            :count="column.items.length"
            :over-limit="isFull(column)"
          >
            <span class="wx-kanban__title">{{ column.title ?? column.id }}</span>
            <wx-badge
              class="wx-kanban__count"
              size="sm"
              round
              :type="isFull(column) ? 'danger' : 'default'"
            >
              {{ column.items.length }}<template v-if="column.limit">/{{ column.limit }}</template>
            </wx-badge>
          </slot>
        </header>

        <vue-draggable
          v-model="column.items"
          class="wx-kanban__list"
          :data-column-id="column.id"
          :group="groupFor(column)"
          :disabled="isLocked(column)"
          :handle="handle"
          :animation="160"
          :delay="150"
          :delay-on-touch-only="true"
          ghost-class="wx-kanban__card--ghost"
          chosen-class="wx-kanban__card--chosen"
          @end="onDrop"
        >
          <div
            v-for="(card, index) in column.items"
            :key="card.id"
            class="wx-kanban__card"
            :class="{ 'is-grabbed': isGrabbed(card) }"
            :data-card-id="card.id"
            :tabindex="isLocked(column) ? -1 : 0"
            role="button"
            aria-roledescription="Draggable card"
            :aria-pressed="isGrabbed(card)"
            @keydown="onCardKeydown($event, card, column, index)"
          >
            <slot name="card" :card="card" :column="column" :index="index">
              <wx-entity-card :title="cardTitle(card)" variant="card" bordered size="sm" />
            </slot>
          </div>
        </vue-draggable>

        <p v-if="column.items.length === 0" class="wx-kanban__empty">
          <slot name="empty" :column="column">{{ emptyText }}</slot>
        </p>

        <footer v-if="addable || $slots['column-footer']" class="wx-kanban__foot">
          <slot name="column-footer" :column="column">
            <wx-button
              class="wx-kanban__add"
              variant="text"
              size="sm"
              block
              :disabled="isLocked(column) || isFull(column)"
              @click="emit('add', column)"
            >
              <template #icon><wx-icon name="plus" /></template>
              {{ addLabel }}
            </wx-button>
          </slot>
        </footer>
      </section>

      <!-- A board is often followed by a button that adds a column; this is its room. -->
      <div v-if="$slots.default" class="wx-kanban__aside">
        <slot />
      </div>
    </div>

    <!-- What a keyboard move says out loud. -->
    <div class="wx-kanban__live" role="status" aria-live="polite">{{ announcement }}</div>
  </div>
</template>

<style scoped>
.wx-kanban {
  --wx-kanban-column-width: 288px;

  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  /* The board is as tall as it is given; the columns scroll inside it, not the page. */
  min-height: 0;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  container-type: inline-size;
}

.wx-kanban__scroller {
  display: flex;
  align-items: stretch;
  gap: var(--wx-space-12);
  min-height: 0;
  padding-bottom: var(--wx-space-6);
  overflow-x: auto;
  /* A swipe across the board must not turn into a page swipe or a back gesture. */
  overscroll-behavior-inline: contain;
  scroll-snap-type: inline proximity;
}

.wx-kanban__column {
  display: flex;
  flex: 0 0 auto;
  flex-direction: column;
  box-sizing: border-box;
  width: var(--wx-kanban-column-width);
  min-height: 0;
  background: var(--wx-bg-muted);
  border-top: 3px solid var(--wx-kanban-tone, var(--wx-border-default));
  border-radius: var(--wx-radius-md);
  scroll-snap-align: start;
}

.wx-kanban__column--primary {
  --wx-kanban-tone: var(--wx-color-primary);
}

.wx-kanban__column--success {
  --wx-kanban-tone: var(--wx-color-success);
}

.wx-kanban__column--warning {
  --wx-kanban-tone: var(--wx-color-warning);
}

.wx-kanban__column--danger {
  --wx-kanban-tone: var(--wx-color-danger);
}

.wx-kanban__column--info {
  --wx-kanban-tone: var(--wx-color-info);
}

.wx-kanban__column.is-locked {
  opacity: 0.7;
}

.wx-kanban__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-12) var(--wx-space-8);
}

.wx-kanban__title {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-kanban__count {
  flex: 0 0 auto;
}

.wx-kanban__list {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-8);
  /* Room to drop a card into a column that has none. */
  min-height: 40px;
  padding: 0 var(--wx-space-8);
  overflow-y: auto;
}

.wx-kanban__card {
  border-radius: var(--wx-radius-sm);
  cursor: grab;
}

.wx-kanban__card:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

/* Held by the keyboard: the same ring, plus the lift a dragged card has. */
.wx-kanban__card.is-grabbed {
  box-shadow: var(--wx-ring-focus), var(--wx-shadow-md);
}

/* What is left behind in the list while the card is being dragged. */
.wx-kanban__card--ghost {
  opacity: 0.4;
}

.wx-kanban__card--chosen {
  cursor: grabbing;
}

.wx-kanban__empty {
  margin: 0;
  padding: var(--wx-space-8) var(--wx-space-12) 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-kanban__foot {
  padding: var(--wx-space-8);
}

.wx-kanban__aside {
  display: flex;
  flex: 0 0 auto;
  align-items: flex-start;
  padding-top: var(--wx-space-12);
}

/* Read out, never drawn. */
.wx-kanban__live {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.wx-kanban--sm .wx-kanban__head {
  padding: var(--wx-space-8) var(--wx-space-10) var(--wx-space-6);
}

.wx-kanban--sm .wx-kanban__list {
  gap: var(--wx-space-6);
  padding: 0 var(--wx-space-6);
}

/*
 * On a phone a column of a fixed 288px leaves a slice of the next one showing, which
 * reads as clutter rather than as a hint. Below that width a column takes most of the
 * screen and the swipe snaps to it. Asked of the board, not of the window: the same
 * squeeze happens in a narrow panel on a wide monitor.
 */
@container (max-width: 560px) {
  .wx-kanban__scroller {
    scroll-snap-type: inline mandatory;
  }

  .wx-kanban__column {
    width: min(88cqw, 320px);
  }
}

/* A finger needs a bigger target, and a long press to tell a drag from a scroll. */
@media (pointer: coarse) {
  .wx-kanban__card {
    cursor: default;
  }
}
</style>

<script setup lang="ts" generic="T extends KanbanCard = KanbanCard">
import { computed, nextTick, ref, useId, useTemplateRef } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import WxBadge from '../Badge/Badge.vue'
import WxButton from '../Button/Button.vue'
import WxEntityCard from '../EntityCard/EntityCard.vue'
import WxIcon from '../Icon/Icon.vue'
import type {
  KanbanCard,
  KanbanColumn,
  KanbanEmits,
  KanbanId,
  KanbanProps,
  KanbanVia,
} from './types'

defineOptions({ name: 'WxKanban', inheritAttrs: false })

const props = withDefaults(defineProps<KanbanProps<T>>(), {
  group: undefined,
  size: 'md',
  columnWidth: 288,
  disabled: false,
  handle: undefined,
  addable: false,
  addLabel: 'Add a card',
  collapsible: false,
  reorderColumns: false,
  columnAddable: false,
  addColumnLabel: 'Add a column',
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
  /** The end of a column's heading — a plus, a menu, whatever the column can do. */
  'column-actions'?: (props: { column: KanbanColumn<T>; collapsed: boolean }) => unknown
  /** Under the cards, where the add button would be. */
  'column-footer'?: (props: { column: KanbanColumn<T> }) => unknown
  /** Shown in a column holding no cards. */
  empty?: (props: { column: KanbanColumn<T> }) => unknown
  /** After the last column, in place of the add-a-column button. */
  default?: () => unknown
}>()

/** Which columns are folded down. Bind it to remember them between visits. */
const collapsed = defineModel<KanbanId[]>('collapsed', { default: () => [] })

const root = useTemplateRef<HTMLElement>('root')

/** Boards exchange cards only when they are told to share a name. */
const ownId = useId()
const cardGroup = computed(() => props.group ?? `wx-kanban-cards-${ownId}`)

const boardStyle = computed(() => ({
  '--wx-kanban-column-width':
    typeof props.columnWidth === 'number' ? `${props.columnWidth}px` : props.columnWidth,
}))

function isFull(column: KanbanColumn<T>) {
  return column.limit !== undefined && column.items.length >= column.limit
}

function isCollapsed(column: KanbanColumn<T>) {
  return collapsed.value.includes(column.id)
}

/** A folded column has nowhere to put a card, so it is closed to them as well. */
function isLocked(column: KanbanColumn<T>) {
  return props.disabled || Boolean(column.disabled) || isCollapsed(column)
}

function columnById(id: KanbanId | string) {
  return props.columns.find((column) => String(column.id) === String(id))
}

function cardTitle(card: T) {
  const named = card as { title?: unknown; name?: unknown; label?: unknown }
  return String(named.title ?? named.name ?? named.label ?? card.id)
}

function nameOf(column: KanbanColumn<T>) {
  return String(column.title ?? column.id)
}

function toggle(column: KanbanColumn<T>) {
  collapsed.value = isCollapsed(column)
    ? collapsed.value.filter((id) => id !== column.id)
    : [...collapsed.value, column.id]
}

/**
 * A work-in-progress limit is only worth drawing if it also refuses the card. The
 * column's own group decides what may be dropped into it; reordering inside a full
 * column stays allowed, since that does not make it any fuller.
 */
function groupFor(column: KanbanColumn<T>) {
  return {
    name: cardGroup.value,
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
  via: KanbanVia,
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
function onCardDrop(event: {
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
 * Moving a column
 * ------------------------------------------------------------------------- */

/*
 * Writing into `columns` is the board's contract rather than an accident: a move has
 * to be visible the moment it happens, and the array the caller passed is the one
 * their own `ref` holds — the cards are moved between `column.items` the same way, a
 * level deeper than the rule can see. Assigning to the prop itself is still off the
 * table; only its contents are rearranged.
 */
/* eslint-disable vue/no-mutating-props */

/**
 * The new order of the columns, written back into the array it came from.
 */
const columnOrder = computed({
  get: () => props.columns,
  set: (next) => {
    props.columns.splice(0, props.columns.length, ...next)
  },
})

function moveColumn(from: number, to: number, via: KanbanVia) {
  if (to < 0 || to >= props.columns.length || from === to) return

  const [column] = props.columns.splice(from, 1)
  if (!column) return

  props.columns.splice(to, 0, column)
  emit('column-move', { column, from, to, via })
}

/* eslint-enable vue/no-mutating-props */

function onColumnDrop(event: { oldDraggableIndex?: number; newDraggableIndex?: number }) {
  const from = event.oldDraggableIndex ?? -1
  const to = event.newDraggableIndex ?? -1
  if (from < 0 || to < 0 || from === to) return

  const column = props.columns[to]
  if (!column) return

  emit('column-move', { column, from, to, via: 'pointer' })
}

/* ---------------------------------------------------------------------------
 * Moving without a mouse
 *
 * SortableJS is a pointer library and has nothing to say to a keyboard, so the board
 * carries its own: space picks a card — or a column — up, the arrows move it, space
 * drops it, escape puts it back. It is the same two array operations a drag performs;
 * what a keyboard needs on top is somewhere to read the result, and that is the live
 * region.
 * ------------------------------------------------------------------------- */

const grabbed = ref<{
  id: KanbanId
  column: KanbanId
  origin: { column: KanbanId; index: number }
} | null>(null)

const grabbedColumn = ref<{ id: KanbanId; origin: number } | null>(null)

const announcement = ref('')

function isGrabbed(card: T) {
  return grabbed.value?.id === card.id
}

function isColumnGrabbed(column: KanbanColumn<T>) {
  return grabbedColumn.value?.id === column.id
}

function positionOf(card: T, column: KanbanColumn<T>) {
  return `${column.items.indexOf(card) + 1} of ${column.items.length}`
}

/**
 * Whatever moved is in a different element now, so the focus has to follow it there.
 * The id is compared rather than written into a selector: an id belongs to the
 * application, and quoting one into CSS needs an escape not every environment has.
 */
async function refocus(attribute: 'data-card-id' | 'data-column-grip', id: KanbanId) {
  await nextTick()

  const wanted = String(id)
  const elements = root.value?.querySelectorAll<HTMLElement>(`[${attribute}]`) ?? []
  for (const element of elements) {
    if (element.getAttribute(attribute) === wanted) {
      element.focus()
      return
    }
  }
}

function onCardKeydown(event: KeyboardEvent, card: T, column: KanbanColumn<T>, index: number) {
  /* A button or a link inside a card keeps its own keys. */
  if (event.target !== event.currentTarget) return
  if (isLocked(column)) return

  const held = isGrabbed(card)

  if (event.key === ' ' || event.key === 'Spacebar' || event.key === 'Enter') {
    event.preventDefault()
    if (held) {
      grabbed.value = null
      announcement.value = `Dropped ${cardTitle(card)} in ${nameOf(column)}, ${positionOf(card, column)}.`
    } else {
      grabbed.value = { id: card.id, column: column.id, origin: { column: column.id, index } }
      announcement.value = `Picked up ${cardTitle(card)}. Use the arrow keys to move it, space to drop it, escape to put it back.`
    }
    return
  }

  if (!held) return

  if (event.key === 'Escape') {
    event.preventDefault()
    const state = grabbed.value
    grabbed.value = null
    if (!state) return

    const current = columnById(state.column)
    const origin = columnById(state.origin.column)
    if (current && origin) {
      const at = current.items.indexOf(card)
      if (at >= 0) move(current, at, origin, state.origin.index, 'keyboard')
    }

    announcement.value = 'Move cancelled.'
    void refocus('data-card-id', card.id)
    return
  }

  const steps: Record<string, () => void> = {
    ArrowUp: () => stepCard(card, column, -1),
    ArrowDown: () => stepCard(card, column, 1),
    ArrowLeft: () => shiftCard(card, column, -1),
    ArrowRight: () => shiftCard(card, column, 1),
  }

  const run = steps[event.key]
  if (!run) return

  event.preventDefault()
  run()
}

function stepCard(card: T, column: KanbanColumn<T>, offset: number) {
  const index = column.items.indexOf(card)
  const next = index + offset
  if (next < 0 || next >= column.items.length) return

  move(column, index, column, next, 'keyboard')
  announcement.value = `${cardTitle(card)} is now ${positionOf(card, column)} in ${nameOf(column)}.`
  void refocus('data-card-id', card.id)
}

function shiftCard(card: T, column: KanbanColumn<T>, offset: number) {
  const columnIndex = props.columns.indexOf(column)

  /* Past a column that refuses the card there may still be one that takes it. */
  for (let i = columnIndex + offset; i >= 0 && i < props.columns.length; i += offset) {
    const target = props.columns[i]
    if (isLocked(target) || isFull(target)) continue

    const index = column.items.indexOf(card)
    move(column, index, target, Math.min(index, target.items.length), 'keyboard')

    if (grabbed.value) grabbed.value = { ...grabbed.value, column: target.id }
    announcement.value = `${cardTitle(card)} moved to ${nameOf(target)}, ${positionOf(card, target)}.`
    void refocus('data-card-id', card.id)
    return
  }

  announcement.value = `${nameOf(column)} is the last column that can take ${cardTitle(card)}.`
}

function onColumnKeydown(event: KeyboardEvent, column: KanbanColumn<T>, index: number) {
  if (event.target !== event.currentTarget) return
  if (!props.reorderColumns || props.disabled) return

  const held = isColumnGrabbed(column)

  if (event.key === ' ' || event.key === 'Spacebar' || event.key === 'Enter') {
    event.preventDefault()
    if (held) {
      grabbedColumn.value = null
      announcement.value = `Dropped ${nameOf(column)}, ${index + 1} of ${props.columns.length}.`
    } else {
      grabbedColumn.value = { id: column.id, origin: index }
      announcement.value = `Picked up the ${nameOf(column)} column. Use the left and right arrows to move it.`
    }
    return
  }

  if (!held) return

  if (event.key === 'Escape') {
    event.preventDefault()
    const state = grabbedColumn.value
    grabbedColumn.value = null
    if (state) moveColumn(props.columns.indexOf(column), state.origin, 'keyboard')

    announcement.value = 'Move cancelled.'
    void refocus('data-column-grip', column.id)
    return
  }

  const offsets: Record<string, number> = { ArrowLeft: -1, ArrowRight: 1 }
  const offset = offsets[event.key]
  if (offset === undefined) return

  event.preventDefault()
  const at = props.columns.indexOf(column)
  moveColumn(at, at + offset, 'keyboard')
  announcement.value = `${nameOf(column)} is now ${props.columns.indexOf(column) + 1} of ${props.columns.length}.`
  void refocus('data-column-grip', column.id)
}

const classes = computed(() => ['wx-kanban', `wx-kanban--${props.size}`])

defineExpose({ move, moveColumn, toggle })
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
    <!--
      Only the columns are items of this list; the tail that adds a column rides along
      inside the same scroller so it stays at the end of the row wherever that is.
    -->
    <vue-draggable
      v-model="columnOrder"
      class="wx-kanban__scroller"
      :group="{ name: `wx-kanban-columns-${ownId}`, pull: false, put: false }"
      :disabled="!reorderColumns || disabled"
      draggable=".wx-kanban__column"
      handle=".wx-kanban__grip"
      filter="button, a, .wx-kanban__actions"
      :prevent-on-filter="false"
      :animation="160"
      @end="onColumnDrop"
    >
      <section
        v-for="(column, columnIndex) in columns"
        :key="column.id"
        class="wx-kanban__column"
        :class="[
          `wx-kanban__column--${column.tone ?? 'default'}`,
          {
            'is-collapsed': isCollapsed(column),
            'is-locked': disabled || column.disabled,
            'is-grabbed': isColumnGrabbed(column),
          },
        ]"
      >
        <!-- Folded down, a column is a strip with its name read the long way. -->
        <div
          v-if="isCollapsed(column)"
          class="wx-kanban__strip wx-kanban__grip"
          :data-column-grip="column.id"
          :tabindex="reorderColumns && !disabled ? 0 : -1"
          @keydown="onColumnKeydown($event, column, columnIndex)"
        >
          <button
            class="wx-kanban__expand"
            type="button"
            :aria-expanded="false"
            :aria-label="`Expand ${nameOf(column)}`"
            @click="toggle(column)"
          >
            <wx-icon name="chevron-right" />
          </button>
          <span class="wx-kanban__title wx-kanban__title--vertical">{{ nameOf(column) }}</span>
          <wx-badge size="sm" round :type="isFull(column) ? 'danger' : 'default'">
            {{ column.items.length }}
          </wx-badge>
        </div>

        <template v-else>
          <header
            class="wx-kanban__head wx-kanban__grip"
            :data-column-grip="column.id"
            :tabindex="reorderColumns && !disabled ? 0 : -1"
            :role="reorderColumns && !disabled ? 'button' : undefined"
            :aria-roledescription="reorderColumns && !disabled ? 'Draggable column' : undefined"
            :aria-pressed="reorderColumns && !disabled ? isColumnGrabbed(column) : undefined"
            @keydown="onColumnKeydown($event, column, columnIndex)"
          >
            <button
              v-if="collapsible"
              class="wx-kanban__collapse"
              type="button"
              :aria-expanded="true"
              :aria-label="`Collapse ${nameOf(column)}`"
              @click="toggle(column)"
            >
              <wx-icon name="chevron-left" />
            </button>

            <slot
              name="column-header"
              :column="column"
              :count="column.items.length"
              :over-limit="isFull(column)"
            >
              <span class="wx-kanban__title">{{ nameOf(column) }}</span>
              <wx-badge
                class="wx-kanban__count"
                size="sm"
                round
                :type="isFull(column) ? 'danger' : 'default'"
              >
                {{ column.items.length
                }}<template v-if="column.limit">/{{ column.limit }}</template>
              </wx-badge>
            </slot>

            <span v-if="$slots['column-actions']" class="wx-kanban__actions">
              <slot name="column-actions" :column="column" :collapsed="false" />
            </span>
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
            @end="onCardDrop"
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
        </template>
      </section>

      <div v-if="columnAddable || $slots.default" class="wx-kanban__tail">
        <slot>
          <button class="wx-kanban__add-column" type="button" @click="emit('add-column')">
            <wx-icon name="plus" />
            <span class="wx-kanban__title--vertical">{{ addColumnLabel }}</span>
          </button>
        </slot>
      </div>
    </vue-draggable>

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
  transition: width var(--wx-duration-normal) var(--wx-easing-standard);
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

.wx-kanban__column.is-collapsed {
  width: 48px;
}

.wx-kanban__column.is-grabbed {
  box-shadow: var(--wx-ring-focus), var(--wx-shadow-md);
}

.wx-kanban__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-12) var(--wx-space-8);
  border-radius: var(--wx-radius-md) var(--wx-radius-md) 0 0;
}

/* The heading is what a column is dragged by, so it says so under the pointer. */
.wx-kanban__grip {
  cursor: grab;
}

.wx-kanban__grip:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
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

/* Read down the strip of a folded column, the way a spine is. */
.wx-kanban__title--vertical {
  flex: 0 1 auto;
  max-height: 100%;
  writing-mode: vertical-rl;
}

.wx-kanban__count,
.wx-kanban__actions {
  flex: 0 0 auto;
}

.wx-kanban__actions {
  display: flex;
  align-items: center;
  gap: var(--wx-space-2);
  cursor: default;
}

.wx-kanban__collapse,
.wx-kanban__expand {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 22px;
  height: 22px;
  padding: 0;
  background: none;
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-kanban__collapse:hover,
.wx-kanban__expand:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-kanban__collapse:focus-visible,
.wx-kanban__expand:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-kanban__strip {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  align-items: center;
  gap: var(--wx-space-8);
  min-height: 0;
  padding: var(--wx-space-10) 0;
  overflow: hidden;
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

.wx-kanban__tail {
  display: flex;
  flex: 0 0 auto;
  align-items: stretch;
}

/* A column-shaped button, so the end of the row reads as a place for one more. */
.wx-kanban__add-column {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wx-space-8);
  box-sizing: border-box;
  width: 48px;
  padding: var(--wx-space-10) 0;
  background: var(--wx-bg-subtle);
  border: 1px dashed var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  color: var(--wx-text-muted);
  font-family: inherit;
  font-size: var(--wx-font-size-sm);
  cursor: pointer;
  transition: color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-kanban__add-column:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-kanban__add-column:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
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

  .wx-kanban__column:not(.is-collapsed) {
    width: min(88cqw, 320px);
  }
}

/* A finger needs a bigger target, and a long press to tell a drag from a scroll. */
@media (pointer: coarse) {
  .wx-kanban__card,
  .wx-kanban__grip {
    cursor: default;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-kanban__column {
    transition: none;
  }
}
</style>

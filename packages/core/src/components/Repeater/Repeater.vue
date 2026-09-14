<script setup lang="ts" generic="T extends object = Record<string, unknown>">
import { computed, ref, watch } from 'vue'
import WxAction from '../Action/Action.vue'
import WxActions from '../Actions/Actions.vue'
import WxButton from '../Button/Button.vue'
import WxIcon from '../Icon/Icon.vue'
import WxSortableList from '../SortableList/SortableList.vue'
import type { SortableMove } from '../SortableList/types'
import type { RepeaterEmits, RepeaterProps } from './types'

defineOptions({ name: 'WxRepeater' })

const props = withDefaults(defineProps<RepeaterProps<T>>(), {
  title: undefined,
  itemLabel: undefined,
  newItem: undefined,
  addLabel: 'Add',
  removeLabel: 'Remove',
  collapsible: false,
  collapsed: false,
  min: 0,
  max: undefined,
  sortable: true,
  disabled: false,
  emptyText: 'Nothing here yet',
  size: 'md',
  plain: false,
  ariaLabel: undefined,
})

const emit = defineEmits<RepeaterEmits<T>>()

defineSlots<{
  /** Replaces the title. */
  header?: () => unknown
  /** The end of the heading — whatever else the repeater can do. */
  extra?: () => unknown
  /** The fields of one row. */
  default?: (props: { item: T; index: number; update: (patch: Partial<T>) => void }) => unknown
  /** Row actions, before the one that removes it. */
  actions?: (props: { item: T; index: number }) => unknown
  /** Shown in place of an empty repeater. */
  empty?: () => unknown
}>()

const items = defineModel<T[]>({ default: () => [] })

/* ---------------------------------------------------------------------------
 * Keys
 *
 * A row is a form, and a form that remounts loses the caret. Positions cannot key one:
 * removing the first row would renumber every row under it, and Vue would rebuild them
 * all. Item identity cannot either — writing a field replaces the item with a copy, which
 * is exactly what must not remount anything. So the keys are the repeater's own, kept
 * alongside the model and moved with it.
 * ------------------------------------------------------------------------- */

let sequence = 0

const keys = ref<string[]>([])

const folded = ref(new Set<string>())

function nextKey(): string {
  sequence += 1
  return `row-${sequence}`
}

/**
 * Rows the repeater did not add itself — a model that arrived from the server, or one the
 * parent replaced. They start folded when the caller asked for that; a row somebody just
 * added never does, and `add` below gives it its key before the model changes.
 */
watch(
  () => items.value.length,
  (length) => {
    while (keys.value.length < length) {
      const key = nextKey()
      keys.value.push(key)
      if (props.collapsed) folded.value.add(key)
    }
    if (keys.value.length > length) keys.value.length = length
  },
  { immediate: true },
)

function keyAt(index: number): string {
  return keys.value[index] ?? `row-${index}`
}

const classes = computed(() => [
  'wx-repeater',
  `wx-repeater--${props.size}`,
  { 'is-plain': props.plain, 'is-disabled': props.disabled },
])

const foldable = computed(() => props.collapsible || props.collapsed)

const atMax = computed(() => props.max !== undefined && items.value.length >= props.max)

const atMin = computed(() => items.value.length <= props.min)

function labelOf(item: T, index: number): string {
  if (typeof props.itemLabel === 'function') return props.itemLabel(item, index)
  if (props.itemLabel) {
    const value = (item as Record<string, unknown>)?.[props.itemLabel]
    if (typeof value === 'string' && value.trim()) return value
    if (typeof value === 'number') return String(value)
  }
  return `${index + 1}`
}

function isOpen(index: number): boolean {
  return !foldable.value || !folded.value.has(keyAt(index))
}

function toggle(index: number) {
  const key = keyAt(index)
  const next = new Set(folded.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  folded.value = next
}

function add() {
  if (props.disabled || atMax.value) return

  const item = props.newItem ? props.newItem() : ({} as T)
  const index = items.value.length

  /* Before the model, so the watcher above sees a key already there and leaves it open. */
  keys.value.push(nextKey())
  items.value = [...items.value, item]

  emit('add', item, index)
}

function remove(index: number) {
  if (props.disabled || atMin.value) return

  const item = items.value[index]
  if (item === undefined) return

  const [key] = keys.value.splice(index, 1)
  if (key !== undefined && folded.value.has(key)) {
    const next = new Set(folded.value)
    next.delete(key)
    folded.value = next
  }

  const next = [...items.value]
  next.splice(index, 1)
  items.value = next

  emit('remove', item, index)
}

/** Writing a field: a copy of the row in a copy of the list, never a mutation of a prop. */
function update(index: number, patch: Partial<T>) {
  const item = items.value[index]
  if (item === undefined) return

  const next = [...items.value]
  next[index] = { ...item, ...patch }
  items.value = next
}

/** The list has already reordered the model; the keys follow it so the rows stay put. */
function onMove(move: SortableMove<T>) {
  const [key] = keys.value.splice(move.from, 1)
  if (key !== undefined) keys.value.splice(move.to, 0, key)

  emit('move', move)
}
</script>

<template>
  <div :class="classes">
    <wx-sortable-list
      v-model="items"
      class="wx-repeater__list"
      :title="title"
      :plain="plain"
      :size="size"
      :item-key="(_item: T, index: number) => keyAt(index)"
      :item-label="itemLabel"
      :disabled="disabled || !sortable"
      :handle="sortable ? 'grip' : 'row'"
      :empty-text="emptyText"
      :aria-label="ariaLabel ?? title"
      :drag-label="'Reorder'"
      @move="onMove"
    >
      <template v-if="$slots.header" #header><slot name="header" /></template>
      <template v-if="$slots.extra" #extra><slot name="extra" /></template>

      <template #default="{ index }">
        <div class="wx-repeater__row">
          <component
            :is="foldable ? 'button' : 'div'"
            v-if="foldable || itemLabel"
            class="wx-repeater__head"
            :type="foldable ? 'button' : undefined"
            :aria-expanded="foldable ? isOpen(index) : undefined"
            @click="foldable && toggle(index)"
          >
            <svg
              v-if="foldable"
              class="wx-repeater__chevron"
              :class="{ 'is-open': isOpen(index) }"
              viewBox="0 0 16 16"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="m6 4 4 4-4 4"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <span class="wx-repeater__title">{{ labelOf(items[index] as T, index) }}</span>
          </component>

          <div v-show="isOpen(index)" class="wx-repeater__body">
            <slot
              :item="items[index] as T"
              :index="index"
              :update="(patch: Partial<T>) => update(index, patch)"
            />
          </div>
        </div>
      </template>

      <template #actions="{ index }">
        <wx-actions :size="size === 'sm' ? 'sm' : 'md'">
          <slot name="actions" :item="items[index] as T" :index="index" />
          <wx-action
            type="remove"
            :label="removeLabel"
            :disabled="disabled || atMin"
            @click="remove(index)"
          />
        </wx-actions>
      </template>

      <template #empty
        ><slot name="empty">{{ emptyText }}</slot></template
      >
    </wx-sortable-list>

    <div class="wx-repeater__foot">
      <wx-button
        class="wx-repeater__add"
        variant="outline"
        :size="size === 'sm' ? 'sm' : 'md'"
        :disabled="disabled || atMax"
        @click="add"
      >
        <wx-icon name="plus" />
        {{ addLabel }}
      </wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-repeater {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  font-family: var(--wx-font-family-sans);
}

/*
 * A row of a sortable list is one line of text, centred and unselectable. A row here is a
 * form: it starts at the top, next to a grip that stays with the header, and its text is
 * there to be selected.
 */
.wx-repeater :deep(.wx-sortable-list__row) {
  align-items: flex-start;
  user-select: auto;
}

.wx-repeater :deep(.wx-sortable-list__grip),
.wx-repeater :deep(.wx-sortable-list__actions) {
  margin-block-start: var(--wx-space-2);
}

.wx-repeater__row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-repeater__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  margin: 0;
  padding: 0;
  border: none;
  background: none;
  color: var(--wx-text-muted);
  font: inherit;
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  text-align: start;
}

button.wx-repeater__head {
  cursor: pointer;
}

button.wx-repeater__head:hover {
  color: var(--wx-text-default);
}

button.wx-repeater__head:focus-visible {
  outline: none;
  border-radius: var(--wx-radius-xs);
  box-shadow: var(--wx-ring-focus);
}

.wx-repeater__chevron {
  flex: 0 0 auto;
  width: 14px;
  height: 14px;
  transition: transform var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-repeater__chevron.is-open {
  transform: rotate(90deg);
}

.wx-repeater__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-repeater__foot {
  display: flex;
}
</style>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import WxButton from '../Button/Button.vue'
import WxCheckbox from '../Checkbox/Checkbox.vue'
import WxIcon from '../Icon/Icon.vue'
import WxInput from '../Input/Input.vue'
import { cssLength } from '../../composables/useOverlayPanel'
import type {
  TransferEmits,
  TransferItem,
  TransferProps,
  TransferSide,
  TransferValue,
} from './types'

defineOptions({ name: 'WxTransfer' })

const props = withDefaults(defineProps<TransferProps>(), {
  items: () => [],
  titles: () => ['Available', 'Selected'],
  searchable: false,
  searchPlaceholder: 'Search',
  height: 260,
  emptyText: 'Nothing here',
  size: 'md',
  disabled: false,
  toRightLabel: 'Move to the right',
  toLeftLabel: 'Move to the left',
})

const emit = defineEmits<TransferEmits>()

defineSlots<{
  /** One row, on either side. */
  item?: (props: { item: TransferItem; side: TransferSide }) => unknown
  /** Shown in a panel with nothing in it. */
  empty?: (props: { side: TransferSide }) => unknown
}>()

/** The values on the right. The left is everything else, which is why it is not a prop. */
const model = defineModel<TransferValue[]>({ default: () => [] })

const sides = ['left', 'right'] as const

const query = reactive<Record<TransferSide, string>>({ left: '', right: '' })

const ticked = reactive<Record<TransferSide, TransferValue[]>>({ left: [], right: [] })

const announcement = ref('')

const byValue = computed(() => new Map(props.items.map((item) => [item.value, item])))

const chosen = computed(() => new Set(model.value))

/*
 * The right panel is in the model's order rather than the catalogue's: the model is an array, the
 * order in it is the order it will be saved in, and a panel that showed it in some other order
 * would be quietly lying about what is being saved.
 */
const panels = computed<Record<TransferSide, TransferItem[]>>(() => ({
  left: props.items.filter((item) => !chosen.value.has(item.value)),
  right: model.value
    .map((value) => byValue.value.get(value))
    .filter((item): item is TransferItem => Boolean(item)),
}))

function matches(item: TransferItem, text: string) {
  const needle = text.trim().toLowerCase()
  if (!needle) return true
  return `${item.label ?? ''} ${item.description ?? ''} ${item.value}`
    .toLowerCase()
    .includes(needle)
}

const visible = computed<Record<TransferSide, TransferItem[]>>(() => ({
  left: panels.value.left.filter((item) => matches(item, query.left)),
  right: panels.value.right.filter((item) => matches(item, query.right)),
}))

const tickedSet = computed<Record<TransferSide, Set<TransferValue>>>(() => ({
  left: new Set(ticked.left),
  right: new Set(ticked.right),
}))

function movable(side: TransferSide) {
  return panels.value[side].filter(
    (item) => !item.disabled && tickedSet.value[side].has(item.value),
  )
}

function pickable(side: TransferSide) {
  return visible.value[side].filter((item) => !item.disabled)
}

function allTicked(side: TransferSide) {
  const rows = pickable(side)
  return rows.length > 0 && rows.every((item) => tickedSet.value[side].has(item.value))
}

function someTicked(side: TransferSide) {
  return !allTicked(side) && pickable(side).some((item) => tickedSet.value[side].has(item.value))
}

function labelOf(item: TransferItem) {
  return item.label ?? String(item.value)
}

function tick(side: TransferSide, value: TransferValue, on: boolean) {
  ticked[side] = on ? [...ticked[side], value] : ticked[side].filter((held) => held !== value)
}

/** The heading's checkbox takes the rows a search has left showing, and only those. */
function tickAll(side: TransferSide, on: boolean) {
  const rows = pickable(side).map((item) => item.value)
  const shown = new Set(rows)
  ticked[side] = on
    ? [...ticked[side].filter((value) => !shown.has(value)), ...rows]
    : ticked[side].filter((value) => !shown.has(value))
}

function send(side: TransferSide, values: TransferValue[]) {
  if (!values.length) return

  const moving = new Set(values)
  model.value =
    side === 'right'
      ? [...model.value, ...values]
      : model.value.filter((value) => !moving.has(value))

  /* What has moved is no longer ticked: it is somewhere else now, and unticked there. */
  const from: TransferSide = side === 'right' ? 'left' : 'right'
  ticked[from] = ticked[from].filter((value) => !moving.has(value))

  emit('change', { values, to: side })
  announcement.value = `${values.length} moved to ${props.titles[side === 'right' ? 1 : 0]}.`
}

function move(to: TransferSide) {
  if (props.disabled) return
  send(
    to,
    movable(to === 'right' ? 'left' : 'right').map((item) => item.value),
  )
}

/** A double-click is the shortcut for one row, and the reason the buttons are not the only way. */
function moveOne(side: TransferSide, item: TransferItem) {
  if (props.disabled || item.disabled) return
  send(side === 'left' ? 'right' : 'left', [item.value])
}

const listStyle = computed(() => ({ height: cssLength(props.height) }))

const classes = computed(() => [
  'wx-transfer',
  `wx-transfer--${props.size}`,
  { 'is-disabled': props.disabled },
])
</script>

<template>
  <div :class="classes">
    <div class="wx-transfer__grid">
      <template v-for="(side, index) in sides" :key="side">
        <section class="wx-transfer__panel">
          <header class="wx-transfer__head">
            <wx-checkbox
              :model-value="allTicked(side)"
              :indeterminate="someTicked(side)"
              :disabled="disabled || !pickable(side).length"
              :size="size"
              :aria-label="`Tick everything in ${titles[index]}`"
              @update:model-value="tickAll(side, $event)"
            />
            <span class="wx-transfer__title">{{ titles[index] }}</span>
            <span class="wx-transfer__count">
              {{ ticked[side].length }}/{{ panels[side].length }}
            </span>
          </header>

          <div v-if="searchable" class="wx-transfer__search">
            <wx-input
              v-model="query[side]"
              type="search"
              size="sm"
              clearable
              :disabled="disabled"
              :placeholder="searchPlaceholder"
              :aria-label="`Search ${titles[index]}`"
            />
          </div>

          <ul class="wx-transfer__list" :style="listStyle">
            <li
              v-for="item in visible[side]"
              :key="item.value"
              class="wx-transfer__item"
              :class="{ 'is-disabled': item.disabled }"
              @dblclick="moveOne(side, item)"
            >
              <wx-checkbox
                :model-value="tickedSet[side].has(item.value)"
                :disabled="disabled || item.disabled"
                :size="size"
                @update:model-value="tick(side, item.value, $event)"
              >
                <slot name="item" :item="item" :side="side">
                  <span class="wx-transfer__label">{{ labelOf(item) }}</span>
                  <span v-if="item.description" class="wx-transfer__description">
                    {{ item.description }}
                  </span>
                </slot>
              </wx-checkbox>
            </li>

            <li v-if="!visible[side].length" class="wx-transfer__empty">
              <slot name="empty" :side="side">{{ emptyText }}</slot>
            </li>
          </ul>
        </section>

        <div v-if="index === 0" class="wx-transfer__controls">
          <wx-button
            variant="outline"
            :size="size"
            :disabled="disabled || !movable('left').length"
            :aria-label="toRightLabel"
            @click="move('right')"
          >
            <wx-icon name="chevron-right" />
          </wx-button>
          <wx-button
            variant="outline"
            :size="size"
            :disabled="disabled || !movable('right').length"
            :aria-label="toLeftLabel"
            @click="move('left')"
          >
            <wx-icon name="chevron-left" />
          </wx-button>
        </div>
      </template>
    </div>

    <!-- What a move says out loud. -->
    <div class="wx-transfer__live" role="status" aria-live="polite">{{ announcement }}</div>
  </div>
</template>

<style scoped>
.wx-transfer {
  box-sizing: border-box;
  position: relative;
  /*
   * The container is this element and the grid is inside it, because a container query cannot
   * style the container it is measuring — the columns have to belong to something the query can
   * reach.
   */
  container-type: inline-size;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-transfer__grid {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: stretch;
  gap: var(--wx-space-12);
}

.wx-transfer__panel {
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
  overflow: hidden;
}

.wx-transfer__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8) var(--wx-space-12);
  background: var(--wx-bg-subtle);
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-transfer__title {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-transfer__count {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-variant-numeric: tabular-nums;
}

.wx-transfer__search {
  padding: var(--wx-space-8) var(--wx-space-8) 0;
}

.wx-transfer__list {
  margin: 0;
  padding: var(--wx-space-8);
  overflow-y: auto;
  list-style: none;
}

.wx-transfer__item {
  margin: 0;
  padding: var(--wx-space-4) var(--wx-space-6);
  border-radius: var(--wx-radius-xs);
  /* A double-click moves the row, and a selection is not what a double-click should leave. */
  user-select: none;
}

.wx-transfer__item:hover:not(.is-disabled) {
  background: var(--wx-bg-fill);
}

.wx-transfer__item.is-disabled {
  opacity: 0.55;
}

.wx-transfer__label {
  display: block;
  font-size: var(--wx-font-size-sm);
}

.wx-transfer__description {
  display: block;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-normal);
}

.wx-transfer__empty {
  margin: 0;
  padding: var(--wx-space-16) var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-align: center;
}

.wx-transfer__controls {
  display: flex;
  flex-direction: column;
  align-self: center;
  gap: var(--wx-space-8);
}

/*
 * Side by side is the whole point of this component — two lists you can compare — so it is the
 * panel's own width that decides when there is no longer room for it, not the window's.
 */
@container (max-width: 520px) {
  .wx-transfer__grid {
    grid-template-columns: 1fr;
  }

  .wx-transfer__controls {
    flex-direction: row;
    justify-content: center;
  }

  /* The arrows point the way the panels now lie. */
  .wx-transfer__controls :deep(.wx-icon) {
    transform: rotate(90deg);
  }
}

/* Read out, never drawn. */
.wx-transfer__live {
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

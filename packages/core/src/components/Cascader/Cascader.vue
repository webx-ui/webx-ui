<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { PopoverAnchor, PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import WxIcon from '../Icon/Icon.vue'
import { useFormField } from '../../composables/useFormField'
import { useControlAttrs } from '../../composables/useControlAttrs'
import type {
  CascaderEmits,
  CascaderModelValue,
  CascaderOption,
  CascaderProps,
  CascaderValue,
} from './types'

defineOptions({ name: 'WxCascader', inheritAttrs: false })

/* `class` and `style` belong to the control; the rest belongs to its input. */
const { rootAttrs, controlAttrs } = useControlAttrs()

const props = withDefaults(defineProps<CascaderProps>(), {
  options: () => [],
  expandTrigger: 'click',
  checkStrictly: false,
  emitPath: true,
  showAllLevels: true,
  separator: ' / ',
  lazy: false,
  load: undefined,
  placeholder: undefined,
  emptyText: 'Nothing here',
  clearable: false,
  teleport: true,
  disabled: undefined,
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<CascaderEmits>()

const model = defineModel<CascaderModelValue>({ default: null })

const field = useFormField(props)

const open = ref(false)
/** The chain of opened nodes — one column per entry, plus the root column. */
const activePath = ref<CascaderOption[]>([])
const panel = ref<HTMLElement | null>(null)

/** Levels fetched by `load`, keyed by the path that asked for them. */
const loadedChildren = ref(new Map<string, CascaderOption[]>())
const loadingKeys = ref(new Set<string>())

/**
 * A path is keyed by the values along it, serialised — no separator character to
 * clash with a value that happens to contain one. The root is the empty path.
 */
function keyOf(path: CascaderOption[]): string {
  return JSON.stringify(path.map((option) => option.value))
}

const rootOptions = computed<CascaderOption[]>(() =>
  props.options.length > 0 ? props.options : (loadedChildren.value.get(keyOf([])) ?? []),
)

function childrenOf(option: CascaderOption, parents: CascaderOption[]): CascaderOption[] {
  if (option.children?.length) return option.children
  return loadedChildren.value.get(keyOf([...parents, option])) ?? []
}

function isLeaf(option: CascaderOption, parents: CascaderOption[]): boolean {
  if (option.leaf === true) return true
  if (option.children?.length) return false
  if (!props.lazy) return true

  /* Lazily: a node is a leaf once its level came back empty, not before. */
  const loaded = loadedChildren.value.get(keyOf([...parents, option]))
  return loaded !== undefined && loaded.length === 0
}

function isLoading(option: CascaderOption, parents: CascaderOption[]): boolean {
  return loadingKeys.value.has(keyOf([...parents, option]))
}

/** The root column, then one column per opened node that has something under it. */
const columns = computed<CascaderOption[][]>(() => {
  const result: CascaderOption[][] = [rootOptions.value]

  activePath.value.forEach((option, index) => {
    const children = childrenOf(option, activePath.value.slice(0, index))
    if (children.length > 0) result.push(children)
  })

  return result
})

/** Walks a stored path of values back into the options they name. */
function resolvePath(values: CascaderValue[]): CascaderOption[] {
  const path: CascaderOption[] = []
  let level = rootOptions.value

  for (const value of values) {
    const option = level.find((candidate) => candidate.value === value)
    if (!option) break
    path.push(option)
    level = childrenOf(option, path.slice(0, -1))
  }

  return path
}

/** Depth-first search, for a model that holds the last value alone. */
function findPath(
  options: CascaderOption[],
  value: CascaderValue,
  parents: CascaderOption[] = [],
): CascaderOption[] | undefined {
  for (const option of options) {
    const path = [...parents, option]
    if (option.value === value) return path

    const found = findPath(childrenOf(option, parents), value, path)
    if (found) return found
  }
  return undefined
}

const selectedPath = computed<CascaderOption[]>(() => {
  const value = model.value
  if (value === null || value === undefined || value === '') return []
  if (Array.isArray(value)) return resolvePath(value)
  return findPath(rootOptions.value, value) ?? []
})

const hasValue = computed(() => {
  const value = model.value
  if (value === null || value === undefined || value === '') return false
  /* An empty path is "nothing picked" — that is what `clear` leaves behind. */
  if (Array.isArray(value)) return value.length > 0
  return true
})

const displayLabel = computed(() => {
  const path = selectedPath.value

  /* Nothing resolved — usually a lazy tree that has not loaded the branch yet. */
  if (path.length === 0) {
    const value = model.value
    if (!hasValue.value) return ''
    return Array.isArray(value) ? value.join(props.separator) : String(value)
  }

  if (!props.showAllLevels) return path[path.length - 1].label
  return path.map((option) => option.label).join(props.separator)
})

const showClear = computed(() => props.clearable && !field.disabled.value && hasValue.value)

/** What a plain form post carries: the path as `content,news`, or the single value. */
const formValue = computed(() => {
  const value = model.value
  if (!hasValue.value) return ''
  return Array.isArray(value) ? value.join(',') : String(value)
})

const classes = computed(() => [
  'wx-cascader',
  `wx-cascader--${field.size.value}`,
  {
    [`wx-cascader--${field.status.value}`]: field.status.value !== 'default',
    'is-open': open.value,
    'is-disabled': field.disabled.value,
  },
])

async function loadLevel(path: CascaderOption[]): Promise<void> {
  if (!props.load) return

  const key = keyOf(path)
  if (loadedChildren.value.has(key) || loadingKeys.value.has(key)) return

  loadingKeys.value.add(key)
  try {
    const children = await props.load(path[path.length - 1] ?? null, path)
    loadedChildren.value.set(key, children)
  } finally {
    loadingKeys.value.delete(key)
  }
}

function expand(option: CascaderOption, level: number) {
  if (option.disabled) return

  const parents = activePath.value.slice(0, level)
  activePath.value = [...parents, option]
  emit('expand', activePath.value)

  if (props.lazy && !isLeaf(option, parents)) void loadLevel([...parents, option])
}

function select(path: CascaderOption[]) {
  const value: CascaderModelValue = props.emitPath
    ? path.map((option) => option.value)
    : (path[path.length - 1]?.value ?? null)

  model.value = value
  emit('change', value)
}

function onOptionClick(option: CascaderOption, level: number) {
  if (option.disabled) return

  const parents = activePath.value.slice(0, level)
  const path = [...parents, option]
  const leaf = isLeaf(option, parents)

  expand(option, level)

  if (leaf || props.checkStrictly) select(path)
  if (leaf) open.value = false
}

function onOptionHover(option: CascaderOption, level: number) {
  if (props.expandTrigger !== 'hover' || option.disabled) return
  if (isLeaf(option, activePath.value.slice(0, level))) return
  expand(option, level)
}

function clear() {
  model.value = props.emitPath ? [] : null
  activePath.value = []
  emit('change', model.value)
  emit('clear')
}

watch(open, (value) => {
  if (value) {
    /* Reopening lands where the current value sits, not where the last poke left it. */
    activePath.value = selectedPath.value.slice(0, -1)
    if (props.lazy && rootOptions.value.length === 0) void loadLevel([])
    emit('open')
    return
  }
  emit('close')
})

// --- keyboard ---------------------------------------------------------------

function optionsIn(level: number): HTMLButtonElement[] {
  if (!panel.value) return []
  return [
    ...panel.value.querySelectorAll<HTMLButtonElement>(
      `[data-column="${level}"] .wx-cascader__option:not(:disabled)`,
    ),
  ]
}

function focusOption(level: number, index: number) {
  const items = optionsIn(level)
  if (items.length === 0) return
  const bounded = ((index % items.length) + items.length) % items.length
  items[bounded]?.focus()
}

function onPanelKeydown(event: KeyboardEvent) {
  const target = event.target as HTMLElement | null
  const column = target?.closest<HTMLElement>('[data-column]')

  /* The panel itself has focus right after opening: the first arrow enters the list. */
  if (!column) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
      event.preventDefault()
      focusOption(0, 0)
    }
    return
  }

  const level = Number(column.dataset.column)
  const items = optionsIn(level)
  const current = items.indexOf(target as HTMLButtonElement)
  const option = columns.value[level]?.[Number((target as HTMLElement).dataset.index)]

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      focusOption(level, current + 1)
      break
    case 'ArrowUp':
      event.preventDefault()
      focusOption(level, current - 1)
      break
    case 'ArrowRight': {
      if (!option || isLeaf(option, activePath.value.slice(0, level))) return
      event.preventDefault()
      expand(option, level)
      void nextTick(() => focusOption(level + 1, 0))
      break
    }
    case 'ArrowLeft': {
      if (level === 0) return
      event.preventDefault()
      activePath.value = activePath.value.slice(0, level - 1)
      void nextTick(() => {
        const parent = columns.value[level - 1]?.findIndex(
          (candidate) => candidate.value === activePath.value[level - 1]?.value,
        )
        focusOption(level - 1, parent === undefined || parent < 0 ? 0 : parent)
      })
      break
    }
    default:
      break
  }
}
</script>

<template>
  <popover-root v-model:open="open">
    <div :class="classes" v-bind="rootAttrs">
      <popover-anchor as="div" class="wx-cascader__anchor">
        <popover-trigger
          :id="field.id.value"
          v-bind="controlAttrs"
          class="wx-cascader__trigger"
          type="button"
          :disabled="field.disabled.value"
          :aria-label="ariaLabel"
          :aria-describedby="field.describedBy.value"
          :aria-invalid="field.status.value === 'error' || undefined"
        >
          <span v-if="hasValue" class="wx-cascader__value">{{ displayLabel }}</span>
          <span v-else class="wx-cascader__placeholder">{{ placeholder }}</span>
          <wx-icon class="wx-cascader__arrow" name="chevron-down" />
        </popover-trigger>

        <button
          v-if="showClear"
          class="wx-cascader__clear"
          type="button"
          tabindex="-1"
          aria-label="Clear"
          @click.stop="clear"
        >
          <wx-icon name="close" />
        </button>
      </popover-anchor>

      <input v-if="name" type="hidden" :name="name" :value="formValue" />
    </div>

    <popover-portal :disabled="!teleport">
      <!--
        The panel itself takes focus when it opens — that is what makes the arrow keys
        work — so the key handler sits on it rather than on the columns inside.
      -->
      <popover-content
        class="wx-cascader__panel"
        align="start"
        :side-offset="4"
        @keydown="onPanelKeydown"
      >
        <div ref="panel" class="wx-cascader__columns">
          <div
            v-for="(column, level) in columns"
            :key="level"
            class="wx-cascader__column"
            :data-column="level"
            role="listbox"
            :aria-label="`Level ${level + 1}`"
          >
            <button
              v-for="(option, index) in column"
              :key="String(option.value)"
              class="wx-cascader__option"
              type="button"
              role="option"
              :data-index="index"
              :disabled="option.disabled"
              :aria-selected="selectedPath[level]?.value === option.value"
              :class="{
                'is-active': activePath[level]?.value === option.value,
                'is-selected': selectedPath[level]?.value === option.value,
              }"
              @click="onOptionClick(option, level)"
              @mouseenter="onOptionHover(option, level)"
            >
              <span class="wx-cascader__label">
                <slot name="option" :option="option" :level="level">{{ option.label }}</slot>
              </span>

              <wx-icon
                v-if="isLoading(option, activePath.slice(0, level))"
                class="wx-cascader__mark"
                name="loader"
                spin
              />
              <wx-icon
                v-else-if="!isLeaf(option, activePath.slice(0, level))"
                class="wx-cascader__mark"
                name="chevron-right"
              />
              <wx-icon
                v-else-if="selectedPath[level]?.value === option.value"
                class="wx-cascader__mark wx-cascader__mark--check"
                name="check"
              />
            </button>

            <p v-if="column.length === 0" class="wx-cascader__empty">{{ emptyText }}</p>
          </div>
        </div>
      </popover-content>
    </popover-portal>
  </popover-root>
</template>

<style scoped>
.wx-cascader {
  display: block;
  width: 100%;
}

.wx-cascader__anchor {
  display: flex;
  align-items: center;
  box-sizing: border-box;
  height: var(--wx-size-control-md);
  padding-right: var(--wx-space-8);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-md);
  transition: border-color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-cascader--sm .wx-cascader__anchor {
  height: var(--wx-size-control-sm);
  font-size: var(--wx-font-size-sm);
}

.wx-cascader--lg .wx-cascader__anchor {
  height: var(--wx-size-control-lg);
  font-size: var(--wx-font-size-lg);
}

.wx-cascader__anchor:hover {
  border-color: var(--wx-border-strong);
}

.wx-cascader.is-open .wx-cascader__anchor,
.wx-cascader__anchor:focus-within {
  border-color: var(--wx-border-focus);
}

.wx-cascader--error .wx-cascader__anchor,
.wx-cascader--error.is-open .wx-cascader__anchor {
  border-color: var(--wx-color-danger);
}

.wx-cascader--success .wx-cascader__anchor {
  border-color: var(--wx-color-success);
}

.wx-cascader--warning .wx-cascader__anchor {
  border-color: var(--wx-color-warning);
}

.wx-cascader.is-disabled .wx-cascader__anchor {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
}

.wx-cascader__trigger {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 1 1 auto;
  min-width: 0;
  height: 100%;
  padding: 0 0 0 var(--wx-space-12);
  background: transparent;
  border: none;
  color: inherit;
  font-family: inherit;
  font-size: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-cascader__trigger:focus-visible {
  outline: none;
}

.wx-cascader.is-disabled .wx-cascader__trigger {
  cursor: not-allowed;
}

.wx-cascader__value,
.wx-cascader__placeholder {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-cascader__placeholder {
  color: var(--wx-text-placeholder);
}

.wx-cascader__arrow {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  font-size: 16px;
  transition: transform var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-cascader.is-open .wx-cascader__arrow {
  transform: rotate(180deg);
}

.wx-cascader__clear {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 20px;
  height: 20px;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-cascader__clear:hover {
  color: var(--wx-text-default);
}
</style>

<style>
/* The panel is teleported, so its styles cannot be scoped to the component. */
.wx-cascader__panel {
  /* One layer for every floating panel, so the one opened last is the one on top. */
  z-index: var(--wx-z-index-popover);
  box-sizing: border-box;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-md);
  overflow: hidden;
}

.wx-cascader__columns {
  display: flex;
  align-items: stretch;
  max-width: min(90vw, 720px);
  /* Columns scroll sideways rather than push the panel off a narrow screen. */
  overflow-x: auto;
}

.wx-cascader__column {
  display: flex;
  flex-direction: column;
  flex: 0 0 auto;
  gap: 1px;
  min-width: 180px;
  max-height: 280px;
  padding: var(--wx-space-4);
  overflow-y: auto;
}

.wx-cascader__column + .wx-cascader__column {
  border-left: 1px solid var(--wx-border-muted);
}

.wx-cascader__option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  width: 100%;
  padding: var(--wx-space-6) var(--wx-space-10);
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-xs);
  color: inherit;
  font-family: inherit;
  font-size: var(--wx-font-size-sm);
  text-align: start;
  cursor: pointer;
}

.wx-cascader__option:hover:not(:disabled),
.wx-cascader__option:focus-visible {
  background: var(--wx-bg-fill);
  outline: none;
}

.wx-cascader__option.is-active {
  background: var(--wx-bg-fill);
}

.wx-cascader__option.is-selected {
  color: var(--wx-color-primary);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-cascader__option:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-cascader__label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-cascader__mark {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  font-size: 14px;
}

.wx-cascader__mark--check {
  color: var(--wx-color-primary);
}

.wx-cascader__empty {
  margin: 0;
  padding: var(--wx-space-10);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-align: center;
}
</style>

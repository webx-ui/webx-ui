<script setup lang="ts" generic="T extends TreeNode">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { PopoverAnchor, PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import WxIcon from '../Icon/Icon.vue'
import WxTree from '../Tree/Tree.vue'
import { useFormField } from '../../composables/useFormField'
import { useControlAttrs } from '../../composables/useControlAttrs'
import { useTreeNodes, type TreeAccessors, type TreeKey } from '../../composables/useTreeNodes'
import type { TreeNode } from '../Tree/types'
import type { TreeSelectEmits, TreeSelectProps, TreeSelectValue } from './types'

defineOptions({ name: 'WxTreeSelect', inheritAttrs: false })

/* `class` and `style` belong to the control; the rest belongs to its trigger. */
const { rootAttrs, controlAttrs } = useControlAttrs()

const props = withDefaults(defineProps<TreeSelectProps<T>>(), {
  nodes: () => [],
  multiple: false,
  checkStrictly: false,
  nodeKey: 'id',
  labelKey: 'label',
  childrenKey: 'children',
  disabledKey: 'disabled',
  leafKey: 'leaf',
  defaultExpandAll: false,
  lazy: false,
  load: undefined,
  selectedPath: undefined,
  filterable: false,
  filterPlaceholder: 'Search',
  showPath: false,
  separator: ' / ',
  panelHeight: 280,
  clearable: false,
  placeholder: 'Select',
  emptyText: 'Nothing here',
  teleport: true,
  disabled: undefined,
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<TreeSelectEmits<T>>()

defineSlots<{
  /** One node in the panel. */
  node?: (props: { node: T; depth: number; expanded: boolean; selected: boolean }) => unknown
  /** What the field shows instead of the labels. */
  value?: (props: { nodes: T[] }) => unknown
}>()

const model = defineModel<TreeSelectValue>({ default: null })

const field = useFormField(props)

const open = ref(false)
const term = ref('')
const expanded = ref<TreeKey[]>([])

/*
 * The same reading of a node the tree does, so `node-key` and its neighbours mean the
 * same thing on both sides of the panel.
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

/*
 * The field needs what the panel already knows: which node a key names, and the path
 * to it. Nothing here is rearranged, so this instance is only the index — the tree in
 * the panel keeps its own, and they share the one `expanded` ref.
 */
const lookup = useTreeNodes<T>({
  nodes: () => props.nodes,
  accessors,
  expanded,
})

/* ---------------------------------------------------------------------------
 * The value
 * ------------------------------------------------------------------------- */

const keys = computed<TreeKey[]>(() => {
  if (model.value === null || model.value === undefined) return []
  return Array.isArray(model.value) ? model.value : [model.value]
})

/** `selectedPath` as the array of paths it always is inside. */
const paths = computed<T[][]>(() => {
  const given = props.selectedPath
  if (!given?.length) return []
  return (Array.isArray(given[0]) ? given : [given]) as T[][]
})

/** The path the caller handed over for a key, when the tree has none of its own. */
function givenPath(key: TreeKey): T[] {
  return (
    (paths.value.find((path) => {
      const last = path.at(-1)
      return last !== undefined && accessors.key(last, []) === key
    }) as T[] | undefined) ?? []
  )
}

/** The nodes behind the keys, from the tree where it knows them and from the path where it does not. */
const selectedNodes = computed<T[]>(() =>
  keys.value
    .map((key) => lookup.entry(key)?.node ?? givenPath(key).at(-1))
    .filter((node): node is T => node !== undefined),
)

/*
 * A key with no node behind it is still a value. Saying so with the placeholder — the
 * way a field with nothing in it looks — would be the field lying about the model,
 * which is why a lazy tree wants `selected-path`: it is the only thing that can put a
 * name where the key would otherwise show through.
 */
const hasValue = computed(() => keys.value.length > 0)

function labelOf(key: TreeKey) {
  const known = lookup.path(key).map((step) => step.node)
  const steps = known.length ? known : givenPath(key)
  if (!steps.length) return String(key)

  const shown = props.showPath ? steps : steps.slice(-1)
  return shown.map((node) => accessors.label?.(node) ?? '').join(props.separator)
}

/** A `<form>` posts strings: one key, or several separated by commas. */
const formValue = computed(() => keys.value.join(','))

const showClear = computed(() => props.clearable && hasValue.value && !field.disabled.value)

const classes = computed(() => [
  'wx-tree-select',
  `wx-tree-select--${field.size.value}`,
  `wx-tree-select--${field.status.value}`,
  {
    'is-open': open.value,
    'is-disabled': field.disabled.value,
    'is-multiple': props.multiple,
  },
])

const panelStyle = computed(() => ({
  maxHeight: typeof props.panelHeight === 'number' ? `${props.panelHeight}px` : props.panelHeight,
}))

/* ---------------------------------------------------------------------------
 * Choosing
 * ------------------------------------------------------------------------- */

function announce(value: TreeSelectValue) {
  model.value = value
  emit('change', value, selectedNodes.value)
}

/** Single: a node is the answer, so the panel has nothing left to ask. */
function onSelect(_node: T | null, key: TreeKey | null) {
  if (props.multiple || key === null) return
  announce(key)
  open.value = false
}

function onChecked(next: TreeKey[]) {
  if (!props.multiple) return
  announce([...next])
}

function removeTag(key: TreeKey) {
  announce(keys.value.filter((held) => held !== key))
}

function clear() {
  announce(props.multiple ? [] : null)
  emit('clear')
}

/* ---------------------------------------------------------------------------
 * The panel
 * ------------------------------------------------------------------------- */

const panel = ref<HTMLElement | null>(null)
/*
 * Typed by the one thing the field asks of it. `InstanceType` cannot be taken of a
 * generic component, and the whole instance is more than is wanted here anyway.
 */
const tree = useTemplateRef<{ openPath: (keys: TreeKey[]) => Promise<boolean> }>('tree')

/*
 * The panel itself takes focus when it opens, which leaves the arrow keys with nothing
 * to act on — the tree listens on its rows. The first arrow hands focus to the row the
 * tree is already pointing at: what is selected, or the first one.
 */
function onPanelKeydown(event: KeyboardEvent) {
  if ((event.target as HTMLElement | null)?.closest('.wx-tree__row')) return
  if (event.key !== 'ArrowDown') return

  event.preventDefault()
  panel.value?.querySelector<HTMLElement>('.wx-tree__row[tabindex="0"]')?.focus()
}

/*
 * Opening the panel on a tree of five hundred nodes and showing the top of it is no
 * help when something is already chosen: the branches leading to it are opened, so the
 * answer to "where is this" is on screen before the first scroll.
 */
watch(open, async (isOpen) => {
  if (!isOpen) {
    term.value = ''
    emit('close')
    return
  }

  for (const key of keys.value) lookup.reveal(key)
  emit('open')

  await nextTick()

  /* A field with a search field is a field somebody opened in order to type. */
  if (props.filterable) {
    panel.value
      ?.closest('.wx-tree-select__panel')
      ?.querySelector<HTMLElement>('.wx-tree-select__search-input')
      ?.focus()
  }

  /*
   * Where the tree is fetched a branch at a time it does not hold the chosen node yet,
   * so `reveal` above had nothing to open. The path the caller gave is walked instead,
   * a level at a time, which is a fetch per level — and then the node is in the tree,
   * open and scrolled to.
   */
  for (const path of paths.value) {
    const ancestors = path.slice(0, -1).map((node) => accessors.key(node, []))
    if (ancestors.length) await tree.value?.openPath(ancestors)
  }

  await nextTick()
  panel.value
    ?.querySelector<HTMLElement>('.wx-tree__row.is-selected, .wx-tree__row[aria-checked="true"]')
    ?.scrollIntoView({ block: 'nearest' })
})
</script>

<template>
  <popover-root v-model:open="open">
    <div :class="classes" v-bind="rootAttrs">
      <popover-anchor as="div" class="wx-tree-select__anchor">
        <popover-trigger
          :id="field.id.value"
          v-bind="controlAttrs"
          class="wx-tree-select__trigger"
          type="button"
          :disabled="field.disabled.value"
          :aria-label="ariaLabel"
          :aria-describedby="field.describedBy.value"
          :aria-invalid="field.status.value === 'error' || undefined"
        >
          <span class="wx-tree-select__value">
            <slot v-if="hasValue" name="value" :nodes="selectedNodes">
              <template v-if="multiple">
                <span v-for="key in keys" :key="String(key)" class="wx-tree-select__tag">
                  {{ labelOf(key) }}
                  <span
                    v-if="!field.disabled.value"
                    class="wx-tree-select__tag-remove"
                    role="button"
                    tabindex="-1"
                    :aria-label="`Remove ${labelOf(key)}`"
                    @click.stop="removeTag(key)"
                  >
                    <wx-icon name="close" />
                  </span>
                </span>
              </template>
              <span v-else class="wx-tree-select__single">{{ labelOf(keys[0]!) }}</span>
            </slot>
            <span v-else class="wx-tree-select__placeholder">{{ placeholder }}</span>
          </span>

          <wx-icon class="wx-tree-select__arrow" name="chevron-down" />
        </popover-trigger>

        <button
          v-if="showClear"
          class="wx-tree-select__clear"
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
        The z-index goes on the content itself, which is where Reka puts the element it
        positions. The panel's own rule cannot carry it: that class lands one element
        further in, on a child that is not the positioned one — and a panel opened from
        inside a dialog would be painted behind it.
      -->
      <popover-content
        class="wx-tree-select__panel"
        align="start"
        :side-offset="4"
        :style="{ zIndex: 'var(--wx-z-index-popover)' }"
        @keydown="onPanelKeydown"
      >
        <label v-if="filterable" class="wx-tree-select__search">
          <wx-icon name="search" />
          <input
            v-model="term"
            class="wx-tree-select__search-input"
            type="search"
            :placeholder="filterPlaceholder"
            autocomplete="off"
          />
        </label>

        <div ref="panel" class="wx-tree-select__body" :style="panelStyle">
          <wx-tree
            ref="tree"
            :model-value="nodes"
            v-model:expanded="expanded"
            :selected="multiple ? null : (keys[0] ?? null)"
            :checked="multiple ? keys : []"
            :checkable="multiple"
            :check-strictly="checkStrictly"
            :node-key="nodeKey"
            :label-key="labelKey"
            :children-key="childrenKey"
            :disabled-key="disabledKey"
            :leaf-key="leafKey"
            :default-expand-all="defaultExpandAll"
            :lazy="lazy"
            :load="load"
            :filter="term"
            :size="field.size.value === 'lg' ? 'md' : 'sm'"
            :indent="16"
            :empty-text="emptyText"
            :aria-label="ariaLabel ?? placeholder"
            @select="onSelect"
            @update:checked="onChecked"
          >
            <template v-if="$slots.node" #default="slotProps">
              <slot name="node" v-bind="slotProps" />
            </template>
          </wx-tree>
        </div>
      </popover-content>
    </popover-portal>
  </popover-root>
</template>

<style scoped>
.wx-tree-select {
  display: block;
  width: 100%;
  font-family: var(--wx-font-family-sans);
}

.wx-tree-select__anchor {
  position: relative;
  display: flex;
  align-items: center;
}

.wx-tree-select__trigger {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  box-sizing: border-box;
  width: 100%;
  min-height: var(--wx-size-control-md);
  padding: var(--wx-space-4) var(--wx-space-6) var(--wx-space-4) var(--wx-space-12);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font-family: inherit;
  font-size: var(--wx-font-size-control-md);
  font-weight: var(--wx-font-weight-medium);
  text-align: start;
  cursor: pointer;
  transition: border-color var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-tree-select--sm .wx-tree-select__trigger {
  min-height: var(--wx-size-control-sm);
  font-size: var(--wx-font-size-control-sm);
}

.wx-tree-select--lg .wx-tree-select__trigger {
  min-height: var(--wx-size-control-lg);
  font-size: var(--wx-font-size-control-lg);
}

.wx-tree-select__trigger:hover {
  border-color: var(--wx-border-strong);
}

.wx-tree-select.is-open .wx-tree-select__trigger {
  border-color: var(--wx-border-focus);
}

.wx-tree-select__trigger:focus-visible {
  outline: none;
  border-color: var(--wx-color-primary);
  box-shadow: var(--wx-ring-focus);
}

.wx-tree-select--error .wx-tree-select__trigger,
.wx-tree-select--error.is-open .wx-tree-select__trigger {
  border-color: var(--wx-color-danger);
}

.wx-tree-select--success .wx-tree-select__trigger {
  border-color: var(--wx-color-success);
}

.wx-tree-select--warning .wx-tree-select__trigger {
  border-color: var(--wx-color-warning);
}

.wx-tree-select.is-disabled .wx-tree-select__trigger {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-tree-select__value {
  display: flex;
  flex: 1 1 auto;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-tree-select__single,
.wx-tree-select__placeholder {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-tree-select__placeholder {
  color: var(--wx-text-placeholder);
  font-weight: var(--wx-font-weight-regular);
}

/* One tag per chosen node, so a selection of eight is a selection of eight. */
.wx-tree-select__tag {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  max-width: 100%;
  padding: 2px var(--wx-space-6);
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-xs);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-regular);
  line-height: var(--wx-font-line-height-tight);
}

.wx-tree-select__tag-remove {
  display: inline-flex;
  align-items: center;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  cursor: pointer;
}

.wx-tree-select__tag-remove:hover {
  color: var(--wx-text-default);
}

.wx-tree-select__arrow {
  flex: none;
  color: var(--wx-text-muted);
  transition: transform var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-tree-select.is-open .wx-tree-select__arrow {
  transform: rotate(180deg);
}

/* Over the arrow, since a cleared field is about to show the arrow again anyway. */
.wx-tree-select__clear {
  position: absolute;
  inset-inline-end: var(--wx-space-6);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  background: var(--wx-bg-surface);
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  cursor: pointer;
}

.wx-tree-select__clear:hover {
  color: var(--wx-text-default);
}

@media (prefers-reduced-motion: reduce) {
  .wx-tree-select__trigger,
  .wx-tree-select__arrow {
    transition: none;
  }
}
</style>

<style>
/* The panel is teleported, so its styles cannot be scoped to the component. */
.wx-tree-select__panel {
  box-sizing: border-box;
  /* At least as wide as the field, and free to grow with a deep branch. */
  min-width: var(--reka-popover-trigger-width);
  max-width: min(560px, calc(100vw - var(--wx-space-32)));
  padding: var(--wx-space-4);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-sm);
  box-shadow: var(--wx-shadow-popover);
}

.wx-tree-select__search {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  padding: var(--wx-space-4) var(--wx-space-8);
  border-bottom: 1px solid var(--wx-border-muted);
  color: var(--wx-text-muted);
}

.wx-tree-select__search-input {
  flex: 1 1 auto;
  min-width: 0;
  padding: var(--wx-space-4) 0;
  background: none;
  border: none;
  outline: none;
  color: var(--wx-text-default);
  font-family: inherit;
  font-size: var(--wx-font-size-sm);
}

.wx-tree-select__search-input::placeholder {
  color: var(--wx-text-placeholder);
}

.wx-tree-select__body {
  overflow: auto;
  overscroll-behavior: contain;
}
</style>

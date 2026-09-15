<script setup lang="ts">
import {
  computed,
  getCurrentInstance,
  inject,
  onBeforeUnmount,
  onMounted,
  provide,
  ref,
  watch,
} from 'vue'
import { adminKey, useTranslate, type AdminContext } from '@webx-ui/module-admin'
import { createModal, toast, WxButton, WxText } from '@webx-ui/core'
import {
  coreTypes,
  WxScreenRenderer,
  type RenderContext,
  type ScreenNode,
  type TypeRegistry,
} from '@webx-ui/schema'
import { createBlocksApi, type BlocksApi } from './api'
import BlockPicker from './BlockPicker.vue'
import BlocksPreview from './BlocksPreview.vue'
import BlocksTree from './BlocksTree.vue'
import {
  cloneNode,
  insertNode,
  locate,
  makeNode,
  removeNode,
  replaceList,
  updateValues,
} from './content'
import { useBlocksMessages } from './i18n'
import { blocksPreviewKey, blocksRootKey } from './preview'
import { formSchema } from './schema'
import type { BlockNode, BlockType } from './types'

/**
 * The constructor — the `wx-blocks` field.
 *
 * Two states (§14). Nothing selected: the tree and the preview of the whole page, wide,
 * for looking at the layout, the order and the seams. A block selected: the tree, a wide
 * form of the block's fields, and the preview shrunk to a phone at one to one — a desktop
 * in a 420 px column is unreadable, and a phone is where things break first.
 *
 * Inside a block's own form the same node type means a nested constructor, and those are
 * edited in the tree on the left — so a nested instance draws a note and nothing else.
 */
defineOptions({ name: 'WxBlocks', inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    /** Handed over by the renderer: the node being drawn and the way down. Unused here. */
    node?: ScreenNode
    context?: RenderContext
    name?: string
    localized?: boolean
    /** Narrows what may be added at the top level, beyond what the types themselves allow. */
    allow?: string[] | null
    /** How many blocks the top level may hold. */
    max?: number | null
    /**
     * The published types with their schemas. Fetched from the panel when absent — handed
     * in for a screen outside one, a demo or a test.
     */
    catalog?: BlockType[] | null
    disabled?: boolean
    /** Where the section lives, for the picker's "make one" link. */
    blocksPath?: string
  }>(),
  {
    node: undefined,
    context: undefined,
    name: undefined,
    localized: false,
    allow: null,
    max: null,
    catalog: null,
    disabled: false,
    blocksPath: '/blocks',
  },
)

const model = defineModel<BlockNode[]>({ default: () => [] })

const nested = inject(blocksRootKey, false)
provide(blocksRootKey, true)

useBlocksMessages()
const t = useTranslate('webx-blocks')

/* The panel, when there is one. A demo page has none, and gets by on the catalog prop —
   which is why this is `inject` and not `useAdmin()`: that throws outside a panel, and a
   field must not. */
const admin: AdminContext | null = inject(adminKey, null)
let api: BlocksApi | null = null

const loaded = ref<BlockType[]>([])
const catalog = computed(() => props.catalog ?? loaded.value)

const tree = computed<BlockNode[]>(() => (Array.isArray(model.value) ? model.value : []))

const selectedKey = ref<string | null>(null)
const selected = computed(() => (selectedKey.value ? locate(tree.value, selectedKey.value) : null))
const selectedType = computed(() =>
  selected.value
    ? (catalog.value.find((type) => type.slug === selected.value?.node.type) ?? null)
    : null,
)

const preview = inject(blocksPreviewKey, null)
const previewEl = ref<InstanceType<typeof BlocksPreview> | null>(null)

const groups = computed<string[]>(
  () =>
    (admin?.state.manifest?.modules.find((m) => m.id === 'blocks')?.meta?.groups as
      string[] | undefined) ?? [],
)

/** Node types for the block's form: the panel's registry, or the core plus this field. */
const self = getCurrentInstance()?.type
const types = computed<TypeRegistry>(() => {
  if (props.context?.types) return props.context.types
  if (admin?.types) return { ...coreTypes, ...admin.types }

  return self
    ? { ...coreTypes, 'wx-blocks': { component: self, kind: 'field', nested: true } }
    : coreTypes
})

const translate = computed(
  () => props.context?.translate ?? admin?.i18n.t ?? ((key: string) => key),
)
const can = computed(() => props.context?.can ?? admin?.can ?? (() => true))

const pick = createModal<
  BlockType,
  {
    catalog: BlockType[]
    parent: BlockType | null
    allow: string[] | null
    tree: BlockNode[]
    groups: string[]
    blocksPath: string
  }
>(BlockPicker)

function set(next: BlockNode[]): void {
  model.value = next
}

async function add(
  parentKey: string | null,
  field: string | null,
  slot: ScreenNode | null,
): Promise<void> {
  const parent = parentKey ? locate(tree.value, parentKey) : null
  const parentType = parent
    ? (catalog.value.find((type) => type.slug === parent.node.type) ?? null)
    : null
  const allow =
    parentKey === null ? props.allow : ((slot?.props?.allow as string[] | undefined) ?? null)
  const max = parentKey === null ? props.max : ((slot?.props?.max as number | undefined) ?? null)
  const list =
    parentKey === null
      ? tree.value
      : parent && field
        ? ((parent.node.values[field] as BlockNode[] | undefined) ?? [])
        : []

  if (max !== null && max !== undefined && list.length >= max) return

  const type = await pick({
    catalog: catalog.value,
    parent: parentType,
    allow,
    tree: tree.value,
    groups: groups.value,
    blocksPath: props.blocksPath,
  })

  if (!type) return

  const node = makeNode(type.slug, structuredSample(type))
  set(insertNode(tree.value, parentKey, field, list.length, node))
  selectedKey.value = node.key
}

/** A new block starts from the sample rather than empty, so the page shows something. */
function structuredSample(type: BlockType): Record<string, unknown> {
  const sample = type.content?.sample ?? {}
  const values: Record<string, unknown> = {}

  for (const [key, value] of Object.entries(sample)) {
    // Nested blocks in a sample are the author's own — a page starts with an empty container.
    if (!Array.isArray(value) || value.length === 0 || typeof value[0] !== 'object')
      values[key] = value
  }

  return values
}

function remove(key: string): void {
  if (selectedKey.value === key) selectedKey.value = null
  set(removeNode(tree.value, key))
}

function duplicate(key: string): void {
  const found = locate(tree.value, key)
  if (!found) return

  const copy = cloneNode(found.node)
  set(insertNode(tree.value, found.parent?.key ?? null, found.field, found.index + 1, copy))
  selectedKey.value = copy.key
}

function reorder(parentKey: string | null, field: string | null, list: BlockNode[]): void {
  set(replaceList(tree.value, parentKey, field, list))
}

function select(key: string): void {
  selectedKey.value = selectedKey.value === key ? null : key
}

function done(): void {
  selectedKey.value = null
}

function onValues(values: Record<string, unknown>): void {
  if (!selectedKey.value) return

  set(updateValues(tree.value, selectedKey.value, values))
}

/* After a field changed, the block is redrawn on the server and swapped into the page a
   moment after the last keystroke. A swap that finds no markers — the block is new, the
   page has not been saved since — asks the host to reload instead. */
let timer: ReturnType<typeof setTimeout> | undefined

watch(
  () => selected.value?.node.values,
  (values, before) => {
    if (!values || !before || !selectedType.value || !api || !preview) return

    const key = selectedKey.value
    const typeId = selectedType.value.id

    clearTimeout(timer)
    timer = setTimeout(() => {
      if (!key || !api) return

      void api
        .render(typeId, { values, key })
        .then((drawn) => {
          if (!previewEl.value?.replace(key, drawn.html)) previewEl.value?.refresh()
        })
        .catch(() => {})
    }, 300)
  },
  { deep: true },
)

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape' && selectedKey.value) done()
}

onMounted(async () => {
  window.addEventListener('keydown', onKey)

  if (props.catalog === null && admin) {
    api = createBlocksApi(admin)

    try {
      loaded.value = await api.catalog()
    } catch (error) {
      toast.danger((error as { body?: { message?: string } }).body?.message ?? String(error))
    }
  } else if (admin) {
    api = createBlocksApi(admin)
  }
})

onBeforeUnmount(() => {
  clearTimeout(timer)
  window.removeEventListener('keydown', onKey)
})

const formRoot = computed(() =>
  selectedType.value?.content ? formSchema(selectedType.value.content.schema) : [],
)
</script>

<template>
  <div v-if="nested" class="wx-blocks-nested">
    <wx-text size="sm" tone="muted">{{ t('field.nested-note') }}</wx-text>
  </div>

  <div
    v-else
    class="wx-blocks"
    :class="{
      'is-editing': selected !== null,
      'has-preview': preview !== null && preview.url.value !== null,
    }"
  >
    <div class="wx-blocks__tree">
      <div class="wx-blocks__panel-head">
        <span class="wx-blocks__panel-title">{{ t('field.blocks') }}</span>
        <wx-text size="sm" tone="muted">{{ tree.length }}</wx-text>
      </div>
      <blocks-tree
        :nodes="tree"
        :catalog="catalog"
        :selected="selectedKey"
        :disabled="disabled"
        @select="select"
        @add="add"
        @remove="remove"
        @duplicate="duplicate"
        @reorder="reorder"
      />
      <wx-button
        v-if="!disabled && (max === null || tree.length < max)"
        variant="outline"
        block
        icon="plus"
        class="wx-blocks__add"
        @click="add(null, null, null)"
      >
        {{ t('field.add') }}
      </wx-button>
      <wx-button
        v-if="preview && preview.url.value"
        variant="text"
        block
        class="wx-blocks__preview-button"
        @click="previewEl?.open()"
      >
        {{ t('field.preview') }}
      </wx-button>
    </div>

    <div v-if="selected" class="wx-blocks__fields">
      <div class="wx-blocks__panel-head">
        <span class="wx-blocks__panel-title">{{ selectedType?.title ?? selected.node.type }}</span>
        <span class="wx-blocks__panel-extra">
          <code>{{ selected.node.type }}</code>
          <wx-button size="sm" variant="outline" @click="done"
            >{{ t('field.done') }} · Esc</wx-button
          >
        </span>
      </div>
      <div class="wx-blocks__form">
        <wx-screen-renderer
          v-if="selectedType?.content"
          :key="selected.node.key"
          :model-value="selected.node.values"
          :root="formRoot"
          :types="types"
          :translate="translate"
          :can="can"
          :disabled="disabled"
          @update:model-value="onValues"
        />
        <wx-text v-else size="sm" tone="danger">{{
          t('field.unknown-type', { type: selected.node.type })
        }}</wx-text>
      </div>
    </div>

    <div v-if="preview && preview.url.value" class="wx-blocks__preview">
      <blocks-preview
        ref="previewEl"
        :url="preview.url.value"
        :selected="selectedKey"
        :mode="selected ? 'phone' : 'wide'"
        :reload="preview.reload?.value ?? 0"
      />
    </div>
  </div>
</template>

<style scoped>
.wx-blocks {
  display: grid;
  grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
  gap: var(--wx-space-16);
  align-items: start;
  container-type: inline-size;
}

.wx-blocks.is-editing {
  grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
}

.wx-blocks.is-editing.has-preview {
  grid-template-columns: minmax(220px, 260px) minmax(360px, 1fr) 420px;
}

.wx-blocks__tree,
.wx-blocks__fields {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8);
  border: 1px solid var(--wx-color-border);
  border-radius: var(--wx-radius-md);
  background: var(--wx-color-surface);
  min-width: 0;
}

.wx-blocks__panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  padding: var(--wx-space-4) var(--wx-space-6);
}

.wx-blocks__panel-title {
  font-weight: var(--wx-font-weight-semibold);
}

.wx-blocks__panel-extra {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-blocks__form {
  padding: var(--wx-space-6);
}

.wx-blocks__preview {
  min-width: 0;
  position: sticky;
  top: var(--wx-space-12);
}

.wx-blocks__preview-button {
  display: none;
}

/* Below the width where three columns fit, the preview folds into a button that opens it
   full screen. */
@container (max-width: 1320px) {
  .wx-blocks.has-preview,
  .wx-blocks.is-editing.has-preview {
    grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
  }

  .wx-blocks__preview {
    display: none;
  }

  .wx-blocks.has-preview .wx-blocks__preview-button {
    display: inline-flex;
  }

  /* Full screen still needs the node in the tree: it is drawn there, only unstuck. */
  .wx-blocks__preview:has(.is-fullscreen) {
    display: block;
    position: static;
  }
}

@container (max-width: 720px) {
  .wx-blocks,
  .wx-blocks.is-editing,
  .wx-blocks.is-editing.has-preview {
    grid-template-columns: minmax(0, 1fr);
  }
}

code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  color: var(--wx-color-text-muted);
}
</style>

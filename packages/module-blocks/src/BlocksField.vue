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
import { adminKey, useErrorText, useTranslate, type AdminContext } from '@webx-ui/module-admin'
import { confirm, createModal, toast, WxButton, WxText } from '@webx-ui/core'
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
  countInside,
  insertNode,
  locate,
  makeNode,
  removeNode,
  replaceList,
  setHidden,
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
    /**
     * Be as tall as what the field is drawn in, and let each of the three panels scroll inside
     * itself.
     *
     * Off by default, because the ordinary case is a field on a form that scrolls: there the
     * constructor is as tall as it needs to be and the preview sticks. A screen that has given
     * the constructor the whole area below its head says so — otherwise the field grows, the
     * page scrolls, and the tree, the form and the preview all leave the screen together.
     */
    fill?: boolean
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
    fill: false,
  },
)

const model = defineModel<BlockNode[]>({ default: () => [] })

const nested = inject(blocksRootKey, false)
provide(blocksRootKey, true)

useBlocksMessages()
const t = useTranslate('webx-blocks')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

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

/**
 * Always a question, and the question says what is leaving.
 *
 * A single block used to go without one — one row in a tree that is right there, with a draft
 * that kept a version of it. That was wrong twice over: the row says the block's type and not
 * its words, so what vanished was never named, and a page is edited by pointing at things. A
 * container says how much goes with it, because that part is not on screen: collapse a section
 * and its twelve blocks are one row (§14.2).
 */
async function remove(key: string): Promise<void> {
  const found = locate(tree.value, key)

  if (!found) return

  const inside = countInside(found.node)

  const agreed = await confirm({
    title: t('field.remove-title', {
      title: catalog.value.find((type) => type.slug === found.node.type)?.title ?? found.node.type,
    }),
    message: inside > 0 ? t('field.remove-text', { count: inside }) : t('field.remove-alone'),
    confirmText: t('field.remove'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

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

/*
 * No question asked, unlike removing a container: this is one click, it is visible in the row
 * the moment it happens, and the same click puts it back.
 */
function visibility(key: string, hidden: boolean): void {
  set(setHidden(tree.value, key, hidden))
}

function reorder(parentKey: string | null, field: string | null, list: BlockNode[]): void {
  set(replaceList(tree.value, parentKey, field, list))
}

function select(key: string): void {
  selectedKey.value = selectedKey.value === key ? null : key
}

/**
 * A block clicked in the preview opens for editing.
 *
 * Not a toggle, unlike the row in the tree: pointing at a thing on the page and having it
 * close is not what anybody means by it. A click that lands on no block at all — the margin
 * of the page, the space between two sections — is left alone rather than treated as "close",
 * because missing is easy and losing the form over it is not what was meant either.
 */
function selectFromPreview(key: string | null): void {
  if (key === null || !locate(tree.value, key)) return

  selectedKey.value = key
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
      toast.danger(message(error))
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

  <!-- The host is the container the queries below measure: a query on an element's own
       class resolves against its nearest ancestor container, never against itself. -->
  <div v-else class="wx-blocks-host" :class="{ 'is-fill': fill }">
    <div
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
          @visibility="visibility"
          @reorder="reorder"
        />
        <!--
          One row, not two stacked full-width buttons: they are not two steps of the same
          thing, and a column of blocks that ends in a column of buttons reads as two more
          blocks. Adding is the one that grows, because it is the one that is always there.
        -->
        <div class="wx-blocks__tools">
          <wx-button
            v-if="!disabled && (max === null || tree.length < max)"
            variant="outline"
            icon="plus"
            class="wx-blocks__add"
            @click="add(null, null, null)"
          >
            {{ t('field.add') }}
          </wx-button>
          <wx-button
            v-if="preview && preview.url.value"
            variant="outline"
            icon="eye"
            class="wx-blocks__preview-button"
            @click="previewEl?.open()"
          >
            {{ t('field.preview') }}
          </wx-button>
        </div>
      </div>

      <div v-if="selected" class="wx-blocks__fields">
        <div class="wx-blocks__panel-head">
          <span class="wx-blocks__panel-title">{{
            selectedType?.title ?? selected.node.type
          }}</span>
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
          :fill="fill"
          @select="selectFromPreview"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.wx-blocks-host {
  container-type: inline-size;
}

/*
 * Filling the area it was given: the grid stretches to it, and each panel scrolls in itself.
 * The preview stops sticking — there is nothing to stick to when the page does not move — and
 * it is the panels that scroll instead.
 */
.wx-blocks-host.is-fill,
.wx-blocks-host.is-fill .wx-blocks {
  height: 100%;
  min-height: 0;
}

.wx-blocks-host.is-fill .wx-blocks {
  align-items: stretch;
}

.wx-blocks-host.is-fill .wx-blocks__tree,
.wx-blocks-host.is-fill .wx-blocks__fields {
  overflow: auto;
}

.wx-blocks-host.is-fill .wx-blocks__preview {
  position: static;
  min-height: 0;
}

.wx-blocks {
  display: grid;
  grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
  gap: var(--wx-gap, var(--wx-space-16));
  align-items: start;
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
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
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

/*
 * On a form that scrolls, all three columns stand still and scroll inside themselves. The tree
 * is the one that makes this worth doing: a page of twenty blocks is a column taller than the
 * window, and without this the way to the block being edited is off the top of it.
 *
 * The cap is the window less the air above and below; the containing block of a sticky grid
 * item is its grid area, which is the whole row, so a short column still stays with a tall one.
 */
.wx-blocks-host:not(.is-fill) .wx-blocks__tree,
.wx-blocks-host:not(.is-fill) .wx-blocks__fields {
  position: sticky;
  top: var(--wx-space-12);
  max-height: calc(100dvh - var(--wx-space-24));
  overflow: auto;
}

.wx-blocks__preview {
  min-width: 0;
  position: sticky;
  top: var(--wx-space-12);
}

/* The foot of the tree: one row, the adding taking whatever the other one leaves. */
/*
 * One row while they fit, a column the moment they do not.
 *
 * The tree is the narrowest column on the screen and the words on these two buttons are as
 * long as the language makes them: side by side in a 220px column they ran out over its edge
 * rather than wrapping, because a button is a flex item that does not break. Wrapping is the
 * whole fix — each one keeps its own line and the line is the column's width.
 */
.wx-blocks__tools {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  flex: none;
}

.wx-blocks__add,
.wx-blocks__preview-button {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-blocks__preview-button {
  display: none;
  flex: none;
}

/* Below the width where three columns fit, the phone beside the form folds into a button
   that opens it full screen. The two-column state — the tree and the whole page — keeps its
   preview: a laptop's panel is narrower than three columns and wide enough for two. The
   threshold is the three columns at their minimum — 260 + 360 + 420 and the two gaps. */
@container (max-width: 1080px) {
  .wx-blocks.is-editing.has-preview {
    grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
  }

  .wx-blocks.is-editing .wx-blocks__preview {
    display: none;
  }

  .wx-blocks.is-editing.has-preview .wx-blocks__preview-button {
    display: inline-flex;
  }

  /* Full screen still needs the node in the tree: it is drawn there, only unstuck. */
  .wx-blocks.is-editing .wx-blocks__preview:has(.is-fullscreen) {
    display: block;
    position: static;
  }
}

/* One column: the preview is a button in either state. */
@container (max-width: 720px) {
  .wx-blocks,
  .wx-blocks.is-editing,
  .wx-blocks.has-preview,
  .wx-blocks.is-editing.has-preview {
    grid-template-columns: minmax(0, 1fr);
  }

  .wx-blocks__preview {
    display: none;
  }

  .wx-blocks.has-preview .wx-blocks__preview-button {
    display: inline-flex;
  }

  .wx-blocks__preview:has(.is-fullscreen) {
    display: block;
    position: static;
  }
}

code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}
</style>

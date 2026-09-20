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
import {
  confirm,
  createModal,
  toast,
  useElementWidth,
  WxAction,
  WxButton,
  WxDrawer,
  WxIcon,
  WxText,
} from '@webx-ui/core'
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
  walk,
} from './content'
import { useBlocksMessages } from './i18n'
import { blocksPreviewKey, blocksRootKey } from './preview'
import { formSchema } from './schema'
import type { BlockNode, BlockType } from './types'

/**
 * The constructor — the `wx-blocks` field.
 *
 * Two states (§14), and two columns in both of them. The narrow one holds the tree, or the
 * open block's form in its place; the wide one holds the preview of the whole page, at the
 * width the preview's own bar is set to. One column of the two changes, the other does not:
 * the page under the form is the page the form is about, and it neither moves nor shrinks
 * when a block is opened.
 *
 * Inside a block's own form the same node type means a nested constructor, and those are
 * edited in the tree — so a nested instance draws a note and nothing else.
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

/** What a block is called in the catalogue, falling back to the raw type. */
function titleOf(node: BlockNode): string {
  return catalog.value.find((type) => type.slug === node.type)?.title ?? node.type
}

/**
 * Every block of the page in the order the page draws them, nested ones included — the tree
 * flattened, which is what "the next block" means with the tree off the screen.
 */
const order = computed(() => {
  const keys: string[] = []

  walk(tree.value, (node) => keys.push(node.key))

  return keys
})

/** The block before the open one and the block after it, null at either end. */
const steps = computed(() => {
  const at = selectedKey.value ? order.value.indexOf(selectedKey.value) : -1

  return {
    previous: at > 0 ? order.value[at - 1]! : null,
    next: at >= 0 && at < order.value.length - 1 ? order.value[at + 1]! : null,
  }
})

/** The blocks the open one is inside, outermost first. Empty at the top level. */
const trail = computed(() => {
  const steps: { key: string; title: string }[] = []

  let at = selected.value?.parent ?? null

  while (at) {
    steps.unshift({ key: at.key, title: titleOf(at) })
    at = locate(tree.value, at.key)?.parent ?? null
  }

  return steps
})

const preview = inject(blocksPreviewKey, null)
const previewEl = ref<InstanceType<typeof BlocksPreview> | null>(null)

const host = ref<HTMLElement | null>(null)
const hostWidth = useElementWidth(host)

/**
 * The panel has room for the page and one thing beside it, or for the page alone.
 *
 * Measured and not asked of CSS, because what changes is not the arrangement but where the
 * tree and the form are rendered — a column of the grid, or a sheet over the page. The
 * threshold is the narrow column at its widest plus a preview still worth looking at: below
 * it the preview would be drawn at a third of its size, which is a picture, not a page.
 *
 * Only with a preview, and that is not a detail: the way into the sheet is a button on the
 * preview's bar, so without one the sheet would be a room with no door.
 */
const hasPreview = computed(() => preview !== null && preview.url.value !== null)
const compact = computed(() => hasPreview.value && hostWidth.value > 0 && hostWidth.value <= 900)

/** Open while the blocks are being worked on over the page. Compact panels only. */
const sheet = ref(false)

/** What the sheet is showing: the open block with its containers, or the list. */
const sheetTitle = computed(() => {
  if (!selected.value) return undefined

  const name = selectedType.value?.title ?? selected.value.node.type

  return [...trail.value.map((step) => step.title), name].join(' / ')
})

const sideProps = computed(() =>
  compact.value
    ? {
        /* Up from the bottom, and not all the way: the strip of page left above it is what
           says the sheet is over the page rather than instead of it. No heading of its own —
           the panels inside carry theirs, and the × is the one thing the sheet has to add. */
        side: 'bottom' as const,
        size: '88%',
        /* Off, or the size above is ignored on exactly the screens this is for: a drawer
           takes a narrow screen whole unless it is told not to. */
        fullScreen: false,
        closable: true,
        closeLabel: t('field.close'),
        ariaLabel: t('field.blocks'),
        /* The open block's name goes in the sheet's own head, where there is a whole line for
           it: in the form's head it shared a row with four controls and wrapped onto two. With
           the blocks it is inside spelled out in front of it, because the trail that says so
           in the column is part of the heading this replaces. The tree keeps its own heading,
           which already says what it is and how many. */
        title: sheetTitle.value,
        open: sheet.value,
        'onUpdate:open': (value: boolean) => (sheet.value = value),
      }
    : {},
)

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
  /* On a phone the block clicked in the page opens for editing where the editing lives. */
  if (compact.value) sheet.value = true
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

  <div v-else ref="host" class="wx-blocks-host" :class="{ 'is-compact': compact }">
    <div
      class="wx-blocks"
      :class="{
        'is-editing': selected !== null,
        'has-preview': preview !== null && preview.url.value !== null,
      }"
    >
      <!--
        The tree and the form share one column and take turns in it.

        They are two views of the same thing — the blocks of this page — and a page has room
        for one of them beside a preview, not two. Side by side the form took whatever the
        preview was not given, and the preview was given the 420 px a desktop does not fit in:
        every block was edited against a picture of a phone. The way back out is the done
        button, or Escape; the way to the next block is in the form's own head.

        On a narrow panel that column is a sheet instead, and the same two panels take turns
        inside it — which is why they are wrapped rather than written twice. A phone has room
        for one thing at a time, and the thing worth the room is the page itself.
      -->
      <component :is="compact ? WxDrawer : 'div'" v-bind="sideProps" class="wx-blocks__side">
        <div v-if="!selected" class="wx-blocks__tree">
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
            <!-- Not in the sheet: the preview is what the sheet is covering. -->
            <wx-button
              v-if="!compact && preview && preview.url.value"
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
            <span class="wx-blocks__panel-title">
              <!-- The blocks this one is inside, because the tree that used to show them is
                 not on screen while it is open. Nothing at all at the top level. -->
              <span v-if="trail.length" class="wx-blocks__trail">
                <template v-for="step in trail" :key="step.key">
                  <button type="button" class="wx-blocks__trail-step" @click="select(step.key)">
                    {{ step.title }}
                  </button>
                  <wx-icon name="chevron-right" />
                </template>
              </span>
              <span class="wx-blocks__panel-name">{{
                selectedType?.title ?? selected.node.type
              }}</span>
            </span>
            <span class="wx-blocks__panel-extra">
              <!-- Not in the sheet: the identifier is for whoever writes the block type, and
                   the phone is where somebody fills one in. It costs a slot in a row of four
                   controls, and the name above already says which block this is. -->
              <code v-if="!compact">{{ selected.node.type }}</code>
              <!-- The tree's next row and previous row, kept where the tree is not. Every block
                 of the page in the order the page draws them, nested ones included. -->
              <wx-action
                size="sm"
                tone="neutral"
                icon="chevron-up"
                :title="t('field.previous')"
                :disabled="steps.previous === null"
                @click="steps.previous && select(steps.previous)"
              />
              <wx-action
                size="sm"
                tone="neutral"
                icon="chevron-down"
                :title="t('field.next')"
                :disabled="steps.next === null"
                @click="steps.next && select(steps.next)"
              />
              <!-- The same button the tree's foot carries, and hidden by the same rule: below
                 the width where the preview folds away, the tree's foot is not on screen
                 while a block is open, and that is exactly when it is needed. -->
              <wx-button
                v-if="!compact && preview && preview.url.value"
                size="sm"
                variant="outline"
                icon="eye"
                class="wx-blocks__preview-button"
                :aria-label="t('field.preview')"
                @click="previewEl?.open()"
              />
              <!-- The key is named only where there is one to press: a sheet on a phone is
                   left by the button or by the × above it, and "Esc" there is a word about
                   furniture nobody in the room has. -->
              <wx-button size="sm" variant="outline" @click="done">{{
                compact ? t('field.done') : `${t('field.done')} · Esc`
              }}</wx-button>
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
      </component>

      <div v-if="preview && preview.url.value" class="wx-blocks__preview">
        <blocks-preview
          ref="previewEl"
          :url="preview.url.value"
          :selected="selectedKey"
          :reload="preview.reload?.value ?? 0"
          :compact="compact"
          @select="selectFromPreview"
          @edit="sheet = true"
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
 * One width for the tree and for the form, because they stand in the same column and taking
 * turns in it is the whole idea: a column that changed size would move the preview and
 * rescale it on every block opened, which is a bigger thing to watch than the room it saves.
 *
 * Twice what the tree used to have. It was 260, which cut the name of every block that was
 * not called something short, and the form that now shares the column needs more than that
 * anyway. The upper bound is what a form of fields reads well at rather than what is left
 * over, and the percentage is what gives a narrow panel back to the preview.
 */
.wx-blocks {
  display: grid;
  grid-template-columns: clamp(360px, 34%, 520px) minmax(0, 1fr);
  gap: var(--wx-gap, var(--wx-space-16));
  align-items: start;
}

/* Without a preview there is no second column to leave room for. */
.wx-blocks:not(.has-preview) {
  grid-template-columns: minmax(0, 1fr);
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
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  font-weight: var(--wx-font-weight-semibold);
}

/* One line and an ellipsis: the head is a row of controls with a name in it, and a name that
   wraps pushes the row into two. The whole of it is one hover away, and in the sheet it is
   spelled out along the top anyway. */
.wx-blocks__panel-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* In the sheet the name is the sheet's heading, on a line of its own. */
.wx-drawer .wx-blocks__fields .wx-blocks__panel-title {
  display: none;
}

/* The way back up, and quieter than the block it leads to: the name of the open block is
   what the head is for. */
.wx-blocks__trail {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  color: var(--wx-text-muted);
  font-weight: var(--wx-font-weight-regular);
  font-size: var(--wx-font-size-sm);
}

.wx-blocks__trail-step {
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  cursor: pointer;
  white-space: nowrap;
}

.wx-blocks__trail-step:hover {
  color: var(--wx-text-default);
  text-decoration: underline;
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
 * The narrow column stands still and scrolls inside itself. The tree is the one that makes
 * this worth doing: a page of twenty blocks is a column taller than the window, and without
 * this the way to the block being edited is off the top of it.
 *
 * The cap is the window less the air above and below; the containing block of a sticky grid
 * item is its grid area, which is the whole row, so a short column still stays with a tall one.
 */
.wx-blocks-host:not(.is-compact) .wx-blocks__side {
  min-width: 0;
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

/*
 * A preview as tall as the page it shows is taller than the window, and a sticky box taller
 * than the window pins its head and puts its foot out of reach — the end of the page could
 * never be scrolled to. It stands still instead, and the tree beside it is the one that
 * follows.
 *
 * Full screen is in here for a different reason: `position: sticky` makes a stacking context
 * whatever its `z-index` says, so the sheet inside — fixed, and over everything by its own
 * number — could only ever be over what is in this column. It opened *under* the action bar,
 * which sits lower in the panel's stack and higher in the document. Nothing looked wrong in
 * the rule that draws it; the column it came out of was the one that had to move.
 */
.wx-blocks__preview:has(.is-grown),
.wx-blocks__preview:has(.is-fullscreen) {
  position: static;
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
/*
 * One column: the preview is a button in either state.
 *
 * The threshold above this one is gone with the layout that needed it — there used to be a
 * width at which three columns no longer fitted and the preview folded away while a block
 * was open. There are two columns now, in both states, so there is one width to speak of.
 *
 * And it is not a container query any more: the narrow panel does not rearrange its columns,
 * it renders the tree and the form somewhere else entirely, which only the component can
 * decide. What is left here is the column the page keeps.
 */
.wx-blocks-host.is-compact .wx-blocks,
.wx-blocks-host.is-compact .wx-blocks.has-preview {
  grid-template-columns: minmax(0, 1fr);
}

/*
 * Inside the sheet a panel is not a card — the sheet is the card, and one inside another is
 * two borders and two paddings for one thing. The sheet's own body brings the room.
 */
.wx-drawer .wx-blocks__tree,
.wx-drawer .wx-blocks__fields {
  border: 0;
  padding: 0;
  background: none;
}

code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}
</style>

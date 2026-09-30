<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  confirm,
  createModal,
  toast,
  WxAlert,
  WxButton,
  WxInput,
  WxSkeleton,
  WxText,
  WxTree,
  type TreeDropEvent,
  type TreeDropZone,
} from '@webx-ui/core'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import { createPropertiesApi } from './api'
import { usePropertyEditor } from './editor'
import { wordsIn } from './format'
import { NAMESPACE, useCatalogPropertiesMessages } from './i18n'
import MergeDialog from './MergeDialog.vue'
import type { PropertyValue, ValueNode } from './types'
import ValueDialog from './ValueDialog.vue'
import ValueRow from './ValueRow.vue'

/**
 * `wx-catalog-property-values`: the reference book of a property (§7.1, «Values»).
 *
 * A tree that reads one branch at a time (`WxTree` with `lazy`, which is `useTreeNodes` under
 * it): a book of materials three levels deep or of a thousand car models is opened where it is
 * needed, not all at once. A search answers flat, at any depth.
 *
 * A row shows the colour or the picture, the name, the slug and how many products hold the value;
 * the first three are written in the value's dialog — a colour picker on every row of a long book
 * is a column of controls nobody reads. Every change is a request of its own and is saved at once:
 * the values are records, not fields of the form (the editor's button saves «Main»). By hand, the
 * order and the nesting are dragged; in alphabetical order nothing is, because the alphabet decides.
 *
 * A value products hold is not deleted: the refusal says how many, and the way out is «Merge
 * with…», which moves those products onto another value first.
 */
defineOptions({ name: 'WxCatalogPropertyValues' })

const admin = useAdmin()
const api = createPropertiesApi(admin)
const editor = usePropertyEditor()
useCatalogPropertiesMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const property = computed(() => editor?.property.value ?? null)
const id = computed(() => property.value?.id ?? null)
const locale = computed(() => admin.i18n.state.locale)
const locked = computed(() => editor?.locked.value ?? true)

/* The colour and the picture follow the switches as they are flipped; the order and the nesting
   follow what is saved, because that is what the server orders and nests by. */
const hasColor = computed(() => Boolean(editor?.values.value.has_color))
const hasImage = computed(() => Boolean(editor?.values.value.has_image))
const isTree = computed(() => property.value?.is_tree ?? false)
const manual = computed(() => property.value?.value_order === 'manual')

const roots = ref<ValueNode[]>([])
const expanded = ref<(string | number)[]>([])
const loading = ref(true)
const failed = ref(false)

const query = ref('')
const found = ref<ValueNode[] | null>(null)
const searching = computed(() => query.value.trim() !== '')

const draft = ref('')
const adding = ref(false)

const draggable = computed(() => !locked.value && manual.value && !searching.value)

const editValue = createModal<
  PropertyValue,
  {
    property: number
    value?: PropertyValue | null
    parent?: number | null
    title: string
    color?: boolean
    image?: boolean
  }
>(ValueDialog)
const mergeValue = createModal<number, { property: number; value: PropertyValue }>(MergeDialog)

function nodeOf(value: PropertyValue): ValueNode {
  return {
    ...value,
    label: wordsIn(value.title, locale.value, `#${value.id}`),
    leaf: !value.has_children,
    children: undefined,
  }
}

async function load(): Promise<void> {
  if (id.value === null) return

  loading.value = true
  failed.value = false

  try {
    roots.value = (await api.values(id.value)).map(nodeOf)
    expanded.value = []
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

watch(id, () => void load(), { immediate: true })

/* A new order or tree setting changes what the server answers with — the branches are read again. */
watch(
  () => [property.value?.value_order, property.value?.is_tree],
  (now, before) => {
    if (before !== undefined && (now[0] !== before[0] || now[1] !== before[1])) void load()
  },
)

async function loadBranch(node: ValueNode): Promise<ValueNode[]> {
  if (id.value === null) return []

  return (await api.values(id.value, node.id)).map(nodeOf)
}

let timer: ReturnType<typeof setTimeout> | undefined
let asked = 0

watch(query, (term) => {
  clearTimeout(timer)

  if (term.trim() === '') {
    found.value = null

    return
  }

  timer = setTimeout(async () => {
    if (id.value === null) return

    const ticket = ++asked

    try {
      const answer = (await api.searchValues(id.value, term.trim(), 100)).map((value) => ({
        ...nodeOf(value),
        leaf: true,
      }))

      if (ticket === asked) found.value = answer
    } catch {
      if (ticket === asked) found.value = []
    }
  }, 250)
})

onBeforeUnmount(() => clearTimeout(timer))

/** Every loaded node with this id — a value can be on screen in the tree and in a search. */
function each(fn: (node: ValueNode) => void, nodes: ValueNode[] = roots.value): void {
  for (const node of nodes) {
    fn(node)
    if (node.children) each(fn, node.children)
  }

  if (nodes === roots.value && found.value) for (const node of found.value) fn(node)
}

function replace(value: PropertyValue): void {
  each((node) => {
    if (node.id === value.id) {
      Object.assign(node, {
        ...value,
        label: wordsIn(value.title, locale.value, `#${value.id}`),
        leaf: !value.has_children && !(node.children && node.children.length > 0),
        children: node.children,
      })
    }
  })
}

function refused(error: unknown): void {
  toast.danger(message(error))
}

/** A new value at the top level, by its name in the panel's language — the row above the tree. */
async function add(): Promise<void> {
  const title = draft.value.trim()

  if (title === '' || id.value === null || adding.value) return

  adding.value = true

  try {
    const made = nodeOf(await api.createValue(id.value, { title: { [locale.value]: title } }))

    draft.value = ''
    roots.value = manual.value
      ? [...roots.value, made]
      : [...roots.value, made].sort((a, b) => a.label.localeCompare(b.label, locale.value))
  } catch (error) {
    refused(error)
  } finally {
    adding.value = false
  }
}

async function addInside(parent: ValueNode): Promise<void> {
  if (id.value === null) return

  const made = await editValue({
    property: id.value,
    parent: parent.id,
    title: t('panel.value-new-inside', { name: parent.label }),
    color: hasColor.value,
    image: hasImage.value,
  })

  if (made === undefined) return

  // A branch that was never opened reads its children when it is; one that was gets the new one.
  if (parent.children) parent.children.push(nodeOf(made))
  parent.has_children = true
  parent.leaf = false
  if (!expanded.value.includes(parent.id)) expanded.value = [...expanded.value, parent.id]
}

async function edit(node: ValueNode): Promise<void> {
  if (id.value === null) return

  const saved = await editValue({
    property: id.value,
    value: node,
    title: node.label,
    color: hasColor.value,
    image: hasImage.value,
  })

  if (saved !== undefined) replace(saved)
}

async function merge(node: ValueNode): Promise<void> {
  if (id.value === null) return

  const moved = await mergeValue({ property: id.value, value: node })

  if (moved === undefined) return

  toast.success(t('panel.merged', { count: moved }))
  query.value = ''
  await load()
}

async function remove(node: ValueNode): Promise<void> {
  if (id.value === null) return

  if (node.products_count > 0) {
    toast.warning(t('panel.value-in-use', { count: node.products_count }))

    return
  }

  const agreed = await confirm({
    title: t('panel.value-delete-title', { name: node.label }),
    message: node.has_children ? t('panel.value-delete-branch') : t('panel.value-delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeValue(id.value, node.id)
    // Out of whatever array holds it, the tree's and the search's alike.
    const prune = (nodes: ValueNode[]): ValueNode[] =>
      nodes
        .filter((one) => one.id !== node.id)
        .map((one) => (one.children ? Object.assign(one, { children: prune(one.children) }) : one))

    roots.value = prune(roots.value)
    if (found.value) found.value = prune(found.value)
  } catch (error) {
    const products = (error as { body?: { meta?: { products?: number } } }).body?.meta?.products

    if (products) toast.warning(t('panel.value-in-use', { count: products }))
    else refused(error)
  }
}

function menuOf(node: ValueNode): RowAction[] {
  if (locked.value) return []

  const items: RowAction[] = [
    { key: 'edit', label: t('panel.edit'), icon: 'edit', run: () => void edit(node) },
  ]

  if (isTree.value && !searching.value) {
    items.push({
      key: 'inside',
      label: t('panel.value-add-inside'),
      icon: 'plus',
      run: () => void addInside(node),
    })
  }

  items.push(
    { key: 'merge', label: t('panel.merge-with'), icon: 'copy', run: () => void merge(node) },
    {
      key: 'delete',
      label: t('panel.delete'),
      icon: 'trash',
      danger: true,
      run: () => void remove(node),
    },
  )

  return items
}

/* Only a tree takes a value inside another; a flat book is a list, and only its order moves. */
function allowDrop(_drag: ValueNode, _drop: ValueNode, zone: TreeDropZone): boolean {
  return isTree.value || zone !== 'inside'
}

/**
 * The tree has already moved the node on screen; this tells the server where it landed — under
 * which parent, before which sibling — and reads the tree again if it did not agree.
 */
async function moved(event: TreeDropEvent<ValueNode>): Promise<void> {
  if (id.value === null) return

  const siblings = event.parent ? (event.parent.children ?? []) : roots.value
  const before = siblings[event.index + 1] ?? null

  if (event.parent) {
    event.parent.has_children = true
    event.parent.leaf = false
  }

  try {
    await api.moveValue(id.value, event.node.id, event.parent?.id ?? null, before?.id ?? null)
  } catch (error) {
    refused(error)
    await load()
  }
}
</script>

<template>
  <div class="wx-catalog-values">
    <wx-alert
      v-if="!property"
      type="info"
      variant="soft"
      :description="t('panel.values-unsaved')"
    />

    <template v-else>
      <div class="wx-catalog-values__bar">
        <form v-if="!locked" class="wx-catalog-values__add" @submit.prevent="add">
          <wx-input
            v-model="draft"
            :placeholder="t('panel.value-new')"
            :aria-label="t('panel.value-new')"
            size="sm"
          />
          <wx-button
            native-type="submit"
            size="sm"
            :loading="adding"
            :disabled="draft.trim() === ''"
          >
            {{ t('panel.add') }}
          </wx-button>
        </form>

        <wx-input
          v-model="query"
          class="wx-catalog-values__search"
          :placeholder="t('panel.search')"
          :aria-label="t('panel.search')"
          clearable
          size="sm"
        />
      </div>

      <wx-skeleton v-if="loading" :rows="4" />

      <wx-alert
        v-else-if="failed"
        type="warning"
        variant="soft"
        :description="t('panel.values-failed')"
      />

      <wx-tree
        v-else-if="searching"
        :model-value="found ?? []"
        class="wx-catalog-values__tree"
        :empty-text="found === null ? t('panel.searching') : t('panel.nothing-found')"
        :aria-label="t('property.tab-values')"
      >
        <template #default="{ node }">
          <value-row :node="node as ValueNode" :locale="locale" />
        </template>
        <template #actions="{ node }">
          <wx-row-menu
            v-if="!locked"
            :actions="menuOf(node as ValueNode)"
            :label="(node as ValueNode).label"
          />
        </template>
      </wx-tree>

      <wx-tree
        v-else
        v-model="roots"
        v-model:expanded="expanded"
        class="wx-catalog-values__tree"
        lazy
        :load="loadBranch"
        leaf-key="leaf"
        :draggable="draggable"
        :allow-drop="allowDrop"
        :empty-text="t('panel.values-empty')"
        :aria-label="t('property.tab-values')"
        @drop="moved"
      >
        <template #default="{ node }">
          <value-row :node="node as ValueNode" :locale="locale" />
        </template>
        <template #actions="{ node }">
          <wx-row-menu
            v-if="!locked"
            :actions="menuOf(node as ValueNode)"
            :label="(node as ValueNode).label"
          />
        </template>
      </wx-tree>

      <wx-text v-if="!loading && !failed && !searching" size="sm" tone="muted">
        {{ manual ? t('panel.values-order-manual') : t('panel.values-order-alpha') }}
      </wx-text>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-values {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  container-type: inline-size;
}

.wx-catalog-values__bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

.wx-catalog-values__add {
  display: flex;
  flex: 1 1 280px;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-values__add > :first-child {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-catalog-values__search {
  flex: 0 1 240px;
  min-width: 0;
}

/* On a phone the two lines stand one over the other, each the whole width. */
@container (max-width: 520px) {
  .wx-catalog-values__search {
    flex-basis: 100%;
  }
}
</style>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  useTranslate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  WxBadge,
  WxTable,
  WxText,
  type TableColumn,
  type TableNodeDropEvent,
  type TableState,
  type TableTreeOptions,
} from '@webx-ui/core'
import CategoryCreateDialog from './CategoryCreateDialog.vue'
import { createCatalogApi } from './api'
import { useCatalogMessages } from './i18n'
import { lastSegment } from './paths'
import { flatten, useCategoryTree } from './store'
import type { CategoryDetail, CategoryNode } from './types'

/**
 * The shelves of the catalogue: the whole tree at once, with how many products each holds
 * (§6.1), dragged into place.
 *
 * Whole, not a level at a time as the pages are: a catalogue has dozens of categories, not
 * thousands, and the counts are one query for all of them on the server. A drag moves a category
 * and everything under it; the addresses do not move with it — they are flat (decision 23) — so
 * no drop is ever questioned, unlike a page's.
 *
 * Publication is here as well as on the form, because taking a branch off the site is a thing
 * done to the tree: an unpublished category hides everything below it (§6.3), and the rows under
 * it say so.
 *
 * A search puts the tree away and lists the matches flat — a branch drawn for one match deep
 * inside it tells the reader nothing they asked about.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const context = useAdmin()
const api = createCatalogApi(context)
const tree = useCategoryTree(context)
const router = useRouter()
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const rows = ref<CategoryNode[]>([])
const loading = ref(true)
/*
 * The tree opens every branch on its first render, and only then — so the table is mounted once
 * there is a tree to open, not on the empty one it would otherwise start from.
 */
const loaded = ref(false)
const search = ref('')

const create = createModal<CategoryDetail, { parent: number | null }>(CategoryCreateDialog)

const canManage = computed(() => context.can('catalog.manage'))
const canDelete = computed(() => context.can('catalog.delete'))
const asTree = computed(() => search.value.trim() === '')

const matches = computed(() => {
  const term = search.value.trim().toLowerCase()

  return flatten(rows.value)
    .filter(
      (node) =>
        node.name.toLowerCase().includes(term) || (node.slug ?? '').toLowerCase().includes(term),
    )
    .map((node) => ({ ...node, children: [] }))
})

const data = computed(() => (asTree.value ? rows.value : matches.value))

const columns = computed<TableColumn<CategoryNode>[]>(() => [
  { key: 'name', label: t('category.name'), minWidth: 200 },
  { key: 'slug', label: t('category.slug'), width: 200, hideBelow: 620 },
  { key: 'products_count', label: t('panel.column-products'), width: 110, align: 'right' },
  { key: 'state', label: t('panel.column-state'), width: 150, hideBelow: 760 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

const treeOptions = computed<TableTreeOptions<CategoryNode> | undefined>(() =>
  asTree.value
    ? {
        childrenKey: 'children',
        defaultExpandAll: true,
        draggable: canManage.value,
        allowDrop: (drag, drop) => drag.id !== drop.id,
      }
    : undefined,
)

/** A copy: the table rearranges what it is given, and the cache is every picker's. */
function show(nodes: CategoryNode[]): void {
  rows.value = nodes.map(plain)
}

function plain(node: CategoryNode): CategoryNode {
  return { ...node, children: (node.children ?? []).map(plain) }
}

async function load(force = true): Promise<void> {
  loading.value = true

  try {
    show(await tree.load(force))
    loaded.value = true
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

function onState(state: TableState): void {
  search.value = state.search
}

function open(node: CategoryNode): void {
  void router.push(`${props.base}/categories/${node.id}`)
}

async function add(parent: CategoryNode | null): Promise<void> {
  const detail = await create({ parent: parent?.id ?? null })

  if (!detail) return

  tree.drop()
  void router.push(`${props.base}/categories/${detail.category.id}`)
}

/**
 * The table has already put the row where it was dropped; the server is told the same thing in
 * its own terms — under which parent, before which sibling — and answers with the whole tree,
 * which is drawn as it says rather than as the drop left it.
 */
async function dropped(event: TableNodeDropEvent<CategoryNode>): Promise<void> {
  const parent = event.parent?.id ?? null
  const siblings = event.parent ? (event.parent.children ?? []) : rows.value
  const before = siblings[event.index + 1]?.id ?? null

  try {
    const answer = await api.moveCategory(event.row.id, parent, before)

    tree.take(answer)
    show(answer)
    toast.success(t('panel.category-moved'))
  } catch (error) {
    toast.danger(message(error))
    await load()
  }
}

async function publish(node: CategoryNode, on: boolean): Promise<void> {
  try {
    await api.saveCategory(node.id, { is_published: on })
    toast.success(on ? t('panel.category-published') : t('panel.category-unpublished'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(node: CategoryNode): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-category-title', { name: node.name }),
    message: t('panel.delete-category-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeCategory(node.id)
    toast.success(t('panel.category-deleted'))
    await load()
  } catch (error) {
    // Not empty: the server's sentence says how many products or subcategories are in the way.
    toast.danger(message(error))
  }
}

function productsOf(node: CategoryNode) {
  return { path: `${props.base}/products`, query: { 'f.category': String(node.id) } }
}

function actionsFor(node: CategoryNode): RowAction[] {
  const actions: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(node) },
    {
      key: 'products',
      icon: 'list',
      label: t('panel.show-products'),
      run: () => void router.push(productsOf(node)),
    },
  ]

  if (node.url && node.visible) {
    actions.push({ key: 'site', icon: 'link', label: t('panel.open-on-site'), href: node.url })
  }

  if (canManage.value) {
    actions.push(
      { key: 'add', icon: 'plus', label: t('panel.add-child'), run: () => void add(node) },
      node.is_published
        ? {
            key: 'unpublish',
            icon: 'eye-off',
            label: t('panel.unpublish'),
            run: () => void publish(node, false),
          }
        : {
            key: 'publish',
            icon: 'eye',
            label: t('panel.publish'),
            run: () => void publish(node, true),
          },
    )
  }

  if (canDelete.value) {
    actions.push({
      key: 'delete',
      icon: 'trash',
      label: t('panel.delete'),
      danger: true,
      run: () => void remove(node),
    })
  }

  return actions
}

onMounted(() => void load(false))

const actions = computed<ScreenAction[]>(() => {
  const leads: ScreenAction[] = []

  if (canManage.value) {
    leads.push({
      key: 'new',
      label: t('panel.new-category'),
      icon: 'plus',
      primary: true,
      run: () => void add(null),
    })
  }

  if (canDelete.value) {
    leads.push({
      key: 'deleted',
      label: t('module.deleted'),
      icon: 'trash',
      menu: true,
      run: () => void router.push({ path: `${props.base}/deleted`, query: { type: 'categories' } }),
    })
  }

  return leads
})
</script>

<template>
  <div class="wx-catalog-categories">
    <wx-list-screen
      :title="t('module.categories')"
      :subtitle="canManage && rows.length > 0 ? t('panel.category-order') : undefined"
      :actions="actions"
    >
      <wx-table
        :key="loaded ? 'tree' : 'waiting'"
        :data="data"
        :columns="columns"
        row-key="id"
        :tree="treeOptions"
        searchable
        clickable
        hover
        flush
        layout="fixed"
        :loading="loading"
        :cards-below="0"
        :search-placeholder="t('panel.search-categories')"
        :empty-text="search ? t('panel.empty-search') : t('panel.empty-categories')"
        :aria-label="t('module.categories')"
        @row-click="open"
        @state-change="onState"
        @node-drop="dropped"
      >
        <template #cell-name="{ row }">
          <span class="wx-catalog-categories__name" :class="{ 'is-hidden': !row.visible }">
            {{ row.name }}
          </span>
        </template>

        <template #cell-slug="{ row }">
          <wx-text v-if="row.slug" mono size="sm" tone="muted" truncate>
            /{{ lastSegment(row.url) ?? row.slug }}
          </wx-text>
        </template>

        <template #cell-products_count="{ row }">
          <router-link
            v-if="row.products_count > 0"
            class="wx-catalog-categories__count"
            :to="productsOf(row)"
            @click.stop
          >
            {{ row.products_count }}
          </router-link>
          <wx-text v-else size="sm" tone="muted">0</wx-text>
        </template>

        <template #cell-state="{ row }">
          <wx-badge v-if="!row.is_published" dot size="sm">{{ t('states.unpublished') }}</wx-badge>
          <wx-badge
            v-else-if="!row.visible"
            type="warning"
            dot
            size="sm"
            :title="t('panel.category-hidden')"
          >
            {{ t('states.published') }}
          </wx-badge>
          <wx-badge v-else type="success" dot size="sm">{{ t('states.published') }}</wx-badge>
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.name" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-catalog-categories {
  min-width: 0;
}

.wx-catalog-categories__name {
  font-weight: var(--wx-font-weight-medium);
}

/* Hidden by a parent: still here to be arranged, but plainly not what a visitor sees. */
.wx-catalog-categories__name.is-hidden {
  color: var(--wx-text-muted);
}

.wx-catalog-categories__count {
  color: var(--wx-color-primary);
  font-variant-numeric: tabular-nums;
  text-decoration: none;
}

.wx-catalog-categories__count:hover {
  text-decoration: underline;
}
</style>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import {
  toast,
  WxBadge,
  WxTable,
  WxText,
  type Paginated,
  type TabItem,
  type TableColumn,
  type TableState,
  type TabValue,
} from '@webx-ui/core'
import { createCatalogApi } from './api'
import { useCatalogMessages } from './i18n'
import { useCategoryTree } from './store'
import type { CategoryRef, DeletedCategory, DeletedKind, DeletedProduct } from './types'

/**
 * «Deleted» (§5, §6.3): what was deleted, and the one thing that can be done to it — bring it
 * back. There is no "delete for ever": orders will point at products, and their links have to
 * keep answering. No filters either; a search finds what somebody is looking for, and the list
 * is newest first, which is where a regret usually is.
 *
 * Products and categories are two tabs of one screen rather than two screens, because the
 * question a reader comes with is "where did it go", not which kind of thing it was.
 *
 * A row says what a restore will do to it before it is done: a product whose category is
 * deleted too comes back without one and unpublished, and that is worth knowing first.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const context = useAdmin()
const api = createCatalogApi(context)
const tree = useCategoryTree(context)
const route = useRoute()
const router = useRouter()
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

type Row = DeletedProduct | DeletedCategory

const page = ref<Paginated<Row> | null>(null)
const loading = ref(true)
const search = ref(typeof route.query.q === 'string' ? route.query.q : '')

const canRestore = computed(() => context.can('catalog.delete'))

const kind = computed<TabValue>({
  get: () => (route.query.type === 'categories' ? 'categories' : 'products'),
  set: (value) => {
    // The table is keyed by the tab and mounts afresh, which asks for its first page itself.
    search.value = ''
    page.value = null
    void router.replace({
      query: { type: value === 'categories' ? 'categories' : undefined },
    })
  },
})

const views = computed<TabItem[]>(() => [
  { value: 'products', label: t('module.products') },
  { value: 'categories', label: t('module.categories') },
])

const columns = computed<TableColumn<Row>[]>(() =>
  kind.value === 'categories'
    ? [
        { key: 'name', label: t('category.name'), minWidth: 200 },
        { key: 'parent', label: t('panel.column-parent'), width: 200, hideBelow: 640 },
        { key: 'deleted_at', label: t('panel.column-deleted'), width: 150 },
        { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
      ]
    : [
        { key: 'name', label: t('panel.column-product'), minWidth: 200 },
        { key: 'category', label: t('product.category'), width: 220, hideBelow: 640 },
        { key: 'deleted_at', label: t('panel.column-deleted'), width: 150 },
        { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
      ],
)

let asked = 0

async function load(state?: TableState): Promise<void> {
  const mine = ++asked

  loading.value = true

  try {
    const answer = await api.deleted(kind.value as DeletedKind, {
      q: state?.search ?? search.value,
      page: state?.page,
      per_page: state?.perPage,
    })

    if (mine === asked) page.value = answer
  } catch (error) {
    toast.danger(message(error))
  } finally {
    if (mine === asked) loading.value = false
  }
}

function onState(state: TableState): void {
  void router.replace({
    query: { ...route.query, q: state.search === '' ? undefined : state.search },
  })
  void load(state)
}

async function restore(row: Row): Promise<void> {
  try {
    if (kind.value === 'categories') {
      await api.restoreCategory(row.id)
      tree.drop()
      toast.success(t('panel.category-restored'))
    } else {
      const product = await api.restoreProduct(row.id)
      const bare = product.category === null && (row as DeletedProduct).category !== null

      toast.success(bare ? t('panel.product-restored-bare') : t('panel.product-restored'))
    }

    await load()
  } catch (error) {
    // A category whose address was taken while it was away is refused, and the server says by whom.
    toast.danger(message(error))
  }
}

/** A product's category or a category's parent, as the row names it. */
function refOf(row: Record<string, unknown>, key: 'category' | 'parent'): CategoryRef | null {
  const found = row[key]

  return found && typeof found === 'object' ? (found as CategoryRef) : null
}

function actionsFor(row: Row): RowAction[] {
  const actions: RowAction[] = []

  if (canRestore.value) {
    actions.push({
      key: 'restore',
      icon: 'refresh',
      label: t('panel.restore'),
      run: () => void restore(row),
    })
  }

  return actions
}
</script>

<template>
  <div class="wx-catalog-deleted">
    <wx-list-screen
      v-model:view="kind"
      :title="t('module.deleted')"
      :subtitle="t('panel.deleted-help')"
      :back="`${props.base}/products`"
      :back-label="t('module.products')"
      :views="views"
    >
      <wx-table
        :key="String(kind)"
        v-model:search="search"
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        :clickable="false"
        :hover="false"
        flush
        layout="fixed"
        :loading="loading"
        :search-placeholder="t('panel.deleted-search')"
        :empty-text="search ? t('panel.empty-search') : t('panel.deleted-empty')"
        :aria-label="t('module.deleted')"
        @state-change="onState"
      >
        <template #cell-name="{ row }">
          <div class="wx-catalog-deleted__name">
            <span class="wx-catalog-deleted__title">{{ row.name }}</span>
            <span v-if="'sku' in row && row.sku" class="wx-catalog-deleted__code">{{
              row.sku
            }}</span>
            <span v-else-if="'slug' in row && row.slug" class="wx-catalog-deleted__code">
              /{{ row.slug }}/
            </span>
          </div>
        </template>

        <template #cell-category="{ row }">
          <wx-text v-if="!refOf(row, 'category')" size="sm" tone="muted">
            {{ t('product.no-category') }}
          </wx-text>
          <span v-else class="wx-catalog-deleted__ref">
            <wx-text
              size="sm"
              truncate
              :tone="refOf(row, 'category')?.deleted ? 'muted' : undefined"
            >
              {{ refOf(row, 'category')?.name }}
            </wx-text>
            <wx-badge v-if="refOf(row, 'category')?.deleted" type="warning" size="sm" round>
              {{ t('panel.restore-bare') }}
            </wx-badge>
          </span>
        </template>

        <template #cell-parent="{ row }">
          <wx-text v-if="!refOf(row, 'parent')" size="sm" tone="muted">
            {{ t('panel.parent-root') }}
          </wx-text>
          <wx-text
            v-else
            size="sm"
            truncate
            :tone="refOf(row, 'parent')?.deleted ? 'muted' : undefined"
          >
            {{ refOf(row, 'parent')?.name }}
            <template v-if="refOf(row, 'parent')?.deleted">
              ({{ t('panel.category-gone') }})</template
            >
          </wx-text>
        </template>

        <template #cell-deleted_at="{ row }">
          <wx-date :value="row.deleted_at" compact />
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu v-if="canRestore" :actions="actionsFor(row)" :label="row.name" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-catalog-deleted {
  min-width: 0;
}

.wx-catalog-deleted__name {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-catalog-deleted__title {
  font-weight: var(--wx-font-weight-medium);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-deleted__code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-catalog-deleted__ref {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
}
</style>

<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxFilterChips,
  WxListScreen,
  WxRowMenu,
  type AppliedFilter,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  useElementWidth,
  WxAlert,
  WxBadge,
  WxButton,
  WxEntityCard,
  WxFormItem,
  WxIcon,
  WxInputNumber,
  WxSelect,
  WxSwitch,
  WxTable,
  WxText,
  WxTooltip,
  WxTreeSelect,
  type RowKey,
  type SelectOption,
  type TabItem,
  type TableColumn,
  type TableState,
  type TabValue,
  type TreeSelectValue,
} from '@webx-ui/core'
import BulkBar from './BulkBar.vue'
import ColumnValue from './ColumnValue.vue'
import ProductCreateDialog from './ProductCreateDialog.vue'
import { createCatalogApi } from './api'
import { FACET_PREFIX, readFacets, writeFacet } from './filters'
import { useCatalogMessages } from './i18n'
import { flatten, useCategoryTree, useFacetRegistry } from './store'
import type {
  BulkSelection,
  FacetChoice,
  FacetInfo,
  ProductDetail,
  ProductQuery,
  ProductRow,
  ProductSort,
  SortInfo,
  ProductsPage,
} from './types'

/**
 * The products of the catalogue (§11.1): a page of rows, the views of decision 3 over it, the
 * facets as filters behind the funnel, and a checkbox per row.
 *
 * The checkboxes are for the bulk actions (§11.4): a selection outlives the page it was made on,
 * which is what "select these twelve across three pages" needs, and "everything found" picks the
 * filter itself rather than its ids. The actions and their progress are `BulkBar`'s.
 *
 * What the list shows beyond the core's columns is the satellites' (`ProductColumns`, §7.4): the
 * server says which, and each row carries its values under their keys.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

/** Where the row stops being a row. Below it the table draws cards and this screen draws one. */
const CARDS = 640

const context = useAdmin()
const api = createCatalogApi(context)
const tree = useCategoryTree(context)
const registry = useFacetRegistry(context)
const route = useRoute()
const router = useRouter()
useCatalogMessages()

const t = useTranslate('webx-catalog')
/* The words a list needs as soon as it has filters belong to the panel, not to the catalogue. */
const admin = useTranslate('webx-admin')
const message = useErrorText()

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)

const page = ref<ProductsPage | null>(null)
const loading = ref(true)
const selected = ref<RowKey[]>([])
/** The whole of what the filter finds is picked, not only the ticked rows. */
const everything = ref(false)
/* The table's own search and page, started from the address: coming back lands where it left. */
const search = ref(typeof route.query.q === 'string' ? route.query.q : '')
const pageNumber = ref(Number(route.query.page ?? 1) || 1)
/* The search asked for as typed after the engine corrected it; another search is corrected again. */
const typedFor = ref<string | null>(null)
const corrected = computed(() => page.value?.corrected ?? null)

/* The search index did not answer and the database did (decision 13 of the Manticore spec). */
const fellBack = computed(() => page.value?.fell_back === true)
/* The database engine past its limit: `webx:doctor` says it too, but nobody reads that daily. */
const outgrown = computed(() => page.value?.outgrown ?? null)
/* The page size the table last asked for: a reload after a filter has to page the same way. */
let perPage: number | undefined

const create = createModal<ProductDetail, Record<string, never>>(ProductCreateDialog)

const canManage = computed(() => context.can('catalog.manage'))
const canDelete = computed(() => context.can('catalog.delete'))

const title = computed(() => t('module.products'))

/** Everything the list is looking at lives in the address — coming back lands in the same place. */
const view = computed<TabValue>({
  get: () => (typeof route.query.view === 'string' ? route.query.view : ''),
  set: (value) => {
    void router.replace({
      query: { ...route.query, view: value === '' ? undefined : String(value), page: undefined },
    })
  },
})

/**
 * The orders are the registry's (§7.2): a satellite adds its own, and a site without prices has
 * none by price — a sort the server does not know is a 422, so nothing is offered or asked for
 * that it did not list. Until it answers, the default order is the one there is.
 */
const sorts = computed<SortInfo[]>(() =>
  registry.sorts.value.length > 0
    ? registry.sorts.value
    : [{ key: 'default', label: t('panel.sort-default') }],
)

const sort = computed<ProductSort>(() => {
  const asked = route.query.sort

  return typeof asked === 'string' && registry.sorts.value.some((one) => one.key === asked)
    ? asked
    : 'default'
})

const facets = computed<FacetInfo[]>(() => registry.facets.value ?? [])
const chosen = computed(() => readFacets(route.query, facets.value))

/** The counter of decision 3 rides on its own tab, where somebody looking for those products looks. */
const views = computed<TabItem[]>(() => {
  const missing = page.value?.counts.no_category ?? 0

  return [
    { value: '', label: t('panel.view-all') },
    { value: 'published', label: t('panel.view-published') },
    { value: 'unpublished', label: t('panel.view-unpublished') },
    {
      value: 'no-category',
      label: t('panel.view-no-category'),
      badge: missing > 0 ? missing : undefined,
    },
  ]
})

/** Absent from every row when the site switched prices off — then the column is not drawn. */
const priced = computed(() => (page.value?.data ?? []).some((row) => 'price' in row))
const extra = computed(() => page.value?.columns ?? [])

const asCards = computed(() => width.value > 0 && width.value < CARDS)

const categoryNames = computed(
  () => new Map(flatten(tree.nodes.value ?? []).map((node) => [String(node.id), node.name])),
)

/**
 * Every column but the name carries a width and the table is laid out `fixed`, so the ones that
 * drop below a breakpoint give their room to the name. They go in the order they can be spared:
 * when it changed, then the satellites', then the category — what is left is the picture, the
 * name and the state, which is what identifies a product and what somebody is looking for.
 */
const NAME = 180
const EXTRA = 120
const CATEGORY = 140
const PRICE = 120
const STATE = 130

/*
 * Each width is what its content takes, measured, plus 32 of the cell's padding: the badges
 * «Out of stock» and «Unpublished» about 90px with their dot, a price with its old one 80. A name
 * and a category end in an ellipsis, so they are the ones that give. Wider, the columns cost
 * the satellites — at 140, 180, 150 and 150 a 1440px window held one satellite of three; now it
 * holds all three.
 *
 * The category is drawn once the name keeps its width beside the checkbox, the picture, the price,
 * the state and the menu.
 */
const CATEGORY_FROM = 44 + 64 + NAME + CATEGORY + PRICE + STATE + rowMenuWidth

/*
 * The width each satellite's column is drawn from on: one after another after the category's, so
 * a third satellite drops first rather than taking its room out of the name — the table is laid out
 * `fixed`, and there the name's `minWidth` gives way to the widths around it (three satellites at
 * 1080px left it 59px). «Updated» goes before any of them.
 */
const extraFrom = (index: number) => CATEGORY_FROM + EXTRA * (index + 1)

const columns = computed<TableColumn<ProductRow>[]>(() => {
  if (asCards.value) {
    // The card is the row: a tap opens the product, and a line of its own for the `···` would make
    // every card half as tall again for a menu whose first item is that same tap.
    return [{ key: 'card', label: '' }]
  }

  return [
    { key: 'image', label: '', width: 64 },
    { key: 'name', label: t('panel.column-product'), minWidth: NAME },
    { key: 'category', label: t('product.category'), width: CATEGORY, hideBelow: CATEGORY_FROM },
    ...(priced.value
      ? [{ key: 'price', label: t('product.price'), width: PRICE, align: 'right' as const }]
      : []),
    ...extra.value.map((column, index) => ({
      key: `x.${column.key}`,
      label: column.label,
      width: EXTRA,
      hideBelow: extraFrom(index),
    })),
    { key: 'state', label: t('panel.column-state'), width: STATE, hideBelow: 720 },
    {
      key: 'updated_at',
      label: t('panel.column-updated'),
      width: 130,
      hideBelow: Math.max(1180, extraFrom(extra.value.length - 1) + 130),
    },
    { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
  ]
})

function query(state?: TableState): ProductQuery {
  const q = state?.search ?? search.value

  return {
    q,
    typed: typedFor.value !== null && typedFor.value === q,
    state: view.value as ProductQuery['state'],
    sort: sort.value,
    page: state?.page ?? pageNumber.value,
    per_page: state?.perPage ?? perPage,
    facets: chosen.value,
  }
}

let asked = 0

async function load(state?: TableState): Promise<void> {
  const mine = ++asked

  loading.value = true

  try {
    const answer = await api.products(query(state))

    // A slower answer to an older question must not land over a newer one.
    if (mine === asked) page.value = answer
  } catch (error) {
    toast.danger(message(error))
  } finally {
    if (mine === asked) loading.value = false
  }
}

function onState(state: TableState): void {
  perPage = state.perPage

  const wanted: LocationQueryRaw = {
    ...route.query,
    q: state.search === '' ? undefined : state.search,
    page: state.page === 1 ? undefined : String(state.page),
  }

  void router.replace({ query: wanted })
  void load(state)
}

function narrow(key: string, choice: FacetChoice | null): void {
  void router.replace({
    query: { ...route.query, [`${FACET_PREFIX}${key}`]: writeFacet(choice), page: undefined },
  })
}

function order(value: unknown): void {
  void router.replace({
    query: {
      ...route.query,
      sort: typeof value === 'string' && value !== 'default' ? value : undefined,
      page: undefined,
    },
  })
}

function clearFilters(): void {
  const rest = Object.fromEntries(
    Object.entries(route.query).filter(([name]) => !name.startsWith(FACET_PREFIX)),
  )

  void router.replace({ query: { ...rest, page: undefined } })
}

/**
 * What a terms facet can be narrowed to: the values the list counted for it, with how many
 * products each would leave. The engine counts a facet without its own choice, so picking one
 * value does not take the others away; a chosen value that counts nothing any more stays offered,
 * or it could not be taken off.
 */
function optionsOf(key: string): SelectOption[] {
  const counted = page.value?.facets?.[key]?.values ?? []
  const options: SelectOption[] = counted.map((one) => ({
    value: one.value,
    label: `${one.label} (${one.count})`,
  }))

  for (const value of valuesOf(key)) {
    if (!counted.some((one) => one.value === value)) options.push({ value, label: value })
  }

  return options
}

/** Values of a terms facet, or of the tree, for a chip: "Category: Laptops, Tablets". */
function valueLabel(facet: FacetInfo, value: string | number): string {
  if (facet.kind === 'tree') return categoryNames.value.get(String(value)) ?? `#${value}`

  return (
    page.value?.facets?.[facet.key]?.values?.find((one) => one.value === String(value))?.label ??
    String(value)
  )
}

/** The bounds a range facet has in the list as it stands, said in its fields before anything is typed. */
function boundOf(key: string, end: 'min' | 'max'): string {
  const bound = page.value?.facets?.[key]?.[end]

  return typeof bound === 'number' ? money.value.format(bound) : ''
}

const applied = computed<AppliedFilter[]>(() =>
  facets.value.flatMap((facet) => {
    const choice = chosen.value[facet.key]

    if (choice === undefined) return []

    let said: string

    if (choice === true) said = facet.label
    else if (Array.isArray(choice)) {
      said = `${facet.label}: ${choice.map((value) => valueLabel(facet, value)).join(', ')}`
    } else {
      said = `${facet.label}: ${choice.min ?? '…'} – ${choice.max ?? '…'}`
    }

    return [{ key: facet.key, label: said, clear: () => narrow(facet.key, null) }]
  }),
)

const emptyText = computed(() => {
  if (search.value !== '') return t('panel.empty-search')
  if (applied.value.length > 0 || view.value !== '') return t('panel.empty-search')

  return t('panel.empty-products')
})

function rangeOf(key: string): { min?: number | null; max?: number | null } {
  const choice = chosen.value[key]

  return choice && !Array.isArray(choice) && choice !== true ? choice : {}
}

function valuesOf(key: string): string[] {
  const choice = chosen.value[key]

  return Array.isArray(choice) ? choice.map(String) : []
}

function treeChoice(key: string): TreeSelectValue {
  return valuesOf(key).map(Number)
}

/** How many the list finds as it is filtered — what "select everything found" would pick. */
const found = computed(() => page.value?.total ?? 0)

/**
 * "Everything found" is the list's query, not its ids (§11.4): the server turns it into ids when
 * the run starts, so forty thousand products never travel through the browser.
 */
const bulkSelection = computed<BulkSelection>(() => {
  if (!everything.value) return { ids: selected.value.map(Number) }

  const facetsQuery: Record<
    string,
    Array<string | number> | { min?: number | null; max?: number | null }
  > = {}

  for (const [key, choice] of Object.entries(chosen.value)) {
    // A toggle is sent the way the list sends it: a list with `1` in it.
    facetsQuery[key] = choice === true ? ['1'] : choice
  }

  return { query: { q: search.value, state: String(view.value), facets: facetsQuery } }
})

function clearSelection(): void {
  selected.value = []
  everything.value = false
}

/**
 * What refused stays picked, and nothing else: the strip keeps its list of refusals beside the
 * very products it names, ready for another go once they are put right.
 */
function searchAsTyped(): void {
  typedFor.value = search.value
  void load()
}

function onBulkFinished(refused: number[]): void {
  everything.value = false
  selected.value = refused
  void load()
}

/* One source per value, not one getter returning an array: a page turn must not fire a second
   request racing the one `onState` already sent. */
watch([view, sort, () => JSON.stringify(chosen.value), search], () => {
  // "Everything found" was everything this filter found; another filter finds something else.
  everything.value = false
})

watch([view, sort, () => JSON.stringify(chosen.value)], () => {
  // A new filter is a new list: the page it was on belongs to the list it was on. If that moves
  // the table's page, the table asks for itself; otherwise nobody would.
  if (pageNumber.value !== 1) pageNumber.value = 1
  else void load()
})

onMounted(() => {
  void registry.load().catch(() => undefined)
  void tree.load().catch(() => undefined)
})

function open(product: ProductRow): void {
  void router.push({ path: `${props.base}/products/${product.id}` })
}

async function add(): Promise<void> {
  const detail = await create({})

  if (!detail) return

  open(detail.product)
}

async function publish(product: ProductRow, on: boolean): Promise<void> {
  try {
    await api.saveProduct(product.id, { is_published: on })
    toast.success(on ? t('panel.product-published') : t('panel.product-unpublished'))
    await load()
  } catch (error) {
    // Publishing without a main category is refused (decision 3), and the server says so.
    toast.danger(message(error))
  }
}

async function remove(product: ProductRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-product-title', { name: product.name }),
    message: t('panel.delete-product-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeProduct(product.id)
    selected.value = selected.value.filter((key) => key !== product.id)
    toast.success(t('panel.product-deleted'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(product: ProductRow): RowAction[] {
  const actions: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(product) },
  ]

  if (product.url) {
    actions.push({ key: 'site', icon: 'link', label: t('panel.open-on-site'), href: product.url })
  }

  if (canManage.value) {
    actions.push(
      product.is_published
        ? {
            key: 'unpublish',
            icon: 'eye-off',
            label: t('panel.unpublish'),
            run: () => void publish(product, false),
          }
        : {
            key: 'publish',
            icon: 'eye',
            label: t('panel.publish'),
            run: () => void publish(product, true),
          },
    )
  }

  if (canDelete.value) {
    actions.push({
      key: 'delete',
      icon: 'trash',
      label: t('panel.delete'),
      danger: true,
      run: () => void remove(product),
    })
  }

  return actions
}

function stateType(product: ProductRow): 'success' | 'warning' | 'default' {
  if (!product.is_published) return 'default'

  return product.visible ? 'success' : 'warning'
}

const money = computed(
  () =>
    new Intl.NumberFormat(context.i18n.state.locale, {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }),
)

function price(value: number | null | undefined): string {
  return value === null || value === undefined ? '—' : money.value.format(value)
}

function unit(product: ProductRow): string {
  return product.unit ? ` / ${t(`units.${product.unit}`)}` : ''
}

function hasValue(value: unknown): boolean {
  return Array.isArray(value)
    ? value.length > 0
    : value !== null && value !== undefined && value !== ''
}

const actions = computed<ScreenAction[]>(() => {
  const leads: ScreenAction[] = []

  if (canManage.value) {
    leads.push({
      key: 'new',
      label: t('panel.new-product'),
      icon: 'plus',
      primary: true,
      run: () => void add(),
    })
  }

  if (canDelete.value) {
    leads.push({
      key: 'deleted',
      label: t('module.deleted'),
      icon: 'trash',
      menu: true,
      run: () => void router.push(`${props.base}/deleted`),
    })
  }

  // After «Deleted» (§8.1 of the exchange spec): reading and exporting is anybody's who sees
  // the catalogue; the import inside asks for the right to write it.
  leads.push({
    key: 'exchange',
    label: t('panel.exchange-title'),
    icon: 'file-csv',
    menu: true,
    run: () => void router.push(`${props.base}/exchange`),
  })

  return leads
})
</script>

<template>
  <div ref="root" class="wx-catalog-products">
    <wx-list-screen v-model:view="view" :title="title" :views="views" :actions="actions">
      <!-- What is picked, said above the rows it was picked from, with what can be done to it. -->
      <div v-if="selected.length > 0 || everything" class="wx-catalog-products__selection">
        <wx-text size="sm" weight="medium">
          {{
            everything
              ? t('panel.selected-all', { count: found })
              : t('panel.selected', { count: selected.length })
          }}
        </wx-text>
        <wx-button
          v-if="!everything && found > selected.length"
          size="sm"
          variant="text"
          @click="everything = true"
        >
          {{ t('panel.select-all-found', { count: found }) }}
        </wx-button>
        <wx-button size="sm" variant="text" @click="clearSelection">
          {{ t('panel.select-clear') }}
        </wx-button>
        <bulk-bar
          v-if="canManage || canDelete"
          :count="everything ? found : selected.length"
          :selection="bulkSelection"
          :base="props.base"
          @finished="onBulkFinished"
        />
      </div>

      <!-- Work goes on without the index, but the search is cruder: say so over the list. -->
      <wx-alert v-if="fellBack" class="wx-catalog-products__notice" type="warning">
        {{ t('panel.fell-back') }}
      </wx-alert>

      <wx-alert v-if="outgrown" class="wx-catalog-products__notice" type="warning">
        {{ t('panel.outgrown', { live: outgrown.live, limit: outgrown.limit }) }}
      </wx-alert>

      <!-- Found for other words than the ones typed: said, with the way back to them. -->
      <div v-if="corrected" class="wx-catalog-products__corrected">
        <wx-text size="sm">{{ t('panel.search-corrected', { corrected }) }}</wx-text>
        <wx-button size="sm" variant="text" @click="searchAsTyped">
          {{ t('panel.search-instead', { q: search }) }}
        </wx-button>
      </div>

      <wx-table
        v-model:selected="selected"
        v-model:search="search"
        v-model:page="pageNumber"
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        selectable
        clickable
        hover
        flush
        :loading="loading"
        layout="fixed"
        :cards-below="CARDS"
        :filters-count="applied.length"
        :filters-label="admin('filters.title')"
        :select-row-label="admin('filters.select-row')"
        :select-all-label="admin('filters.select-all')"
        :search-placeholder="t('panel.search-products')"
        :empty-text="emptyText"
        :aria-label="title"
        @row-click="open"
        @state-change="onState"
      >
        <template #filters>
          <wx-form-item :label="t('panel.sort')">
            <wx-select
              :model-value="sort"
              :options="sorts.map((one) => ({ value: one.key, label: one.label }))"
              size="sm"
              @update:model-value="order"
            />
          </wx-form-item>

          <wx-form-item v-for="facet in facets" :key="facet.key" :label="facet.label">
            <wx-tree-select
              v-if="facet.kind === 'tree'"
              :model-value="treeChoice(facet.key)"
              :nodes="tree.nodes.value ?? []"
              node-key="id"
              label-key="name"
              children-key="children"
              multiple
              check-strictly
              filterable
              clearable
              size="sm"
              :placeholder="t('panel.any')"
              @update:model-value="
                (value: TreeSelectValue) =>
                  narrow(facet.key, Array.isArray(value) ? value.map(String) : [])
              "
            />

            <div v-else-if="facet.kind === 'range'" class="wx-catalog-products__range">
              <wx-input-number
                :model-value="rangeOf(facet.key).min ?? null"
                :min="0"
                :controls="false"
                size="sm"
                :placeholder="boundOf(facet.key, 'min') || t('panel.range-from')"
                :aria-label="`${facet.label}: ${t('panel.range-from')}`"
                @update:model-value="
                  (value: number | null | undefined) =>
                    narrow(facet.key, { ...rangeOf(facet.key), min: value ?? null })
                "
              />
              <wx-input-number
                :model-value="rangeOf(facet.key).max ?? null"
                :min="0"
                :controls="false"
                size="sm"
                :placeholder="boundOf(facet.key, 'max') || t('panel.range-to')"
                :aria-label="`${facet.label}: ${t('panel.range-to')}`"
                @update:model-value="
                  (value: number | null | undefined) =>
                    narrow(facet.key, { ...rangeOf(facet.key), max: value ?? null })
                "
              />
            </div>

            <wx-switch
              v-else-if="facet.kind === 'toggle'"
              :model-value="chosen[facet.key] === true"
              size="sm"
              @update:model-value="(on) => narrow(facet.key, on ? true : null)"
            />

            <wx-select
              v-else
              :model-value="valuesOf(facet.key)"
              :options="optionsOf(facet.key)"
              multiple
              filterable
              clearable
              size="sm"
              :placeholder="t('panel.any')"
              @update:model-value="
                (value: unknown) => narrow(facet.key, Array.isArray(value) ? value.map(String) : [])
              "
            />
          </wx-form-item>

          <wx-button v-if="applied.length > 0" variant="text" size="sm" block @click="clearFilters">
            <template #icon><wx-icon name="close" /></template>
            {{ admin('filters.reset') }}
          </wx-button>
        </template>

        <template #applied>
          <wx-filter-chips :filters="applied" />
        </template>

        <template #cell-image="{ row }">
          <span class="wx-catalog-products__image" :class="{ 'is-empty': !row.image }">
            <img v-if="row.image" :src="row.image.thumb ?? row.image.url" alt="" loading="lazy" />
            <wx-icon v-else name="image" size="sm" />
          </span>
        </template>

        <template #cell-name="{ row }">
          <div class="wx-catalog-products__name">
            <span class="wx-catalog-products__title">{{ row.name }}</span>
            <span class="wx-catalog-products__sku">{{ row.sku ?? '' }}</span>
          </div>
        </template>

        <template #cell-category="{ row }">
          <wx-badge v-if="!row.category" type="warning" size="sm" round>
            {{ t('product.no-category') }}
          </wx-badge>
          <wx-text v-else size="sm" truncate :tone="row.category.deleted ? 'muted' : undefined">
            {{ row.category.name }}
          </wx-text>
        </template>

        <template #cell-price="{ row }">
          <span class="wx-catalog-products__price">
            <span
              >{{ price(row.price) }}<small v-if="row.price != null">{{ unit(row) }}</small></span
            >
            <s v-if="row.old_price != null">{{ price(row.old_price) }}</s>
          </span>
        </template>

        <template v-for="column in extra" :key="column.key" #[`cell-x.${column.key}`]="{ row }">
          <column-value :value="row.columns?.[column.key]" />
        </template>

        <template #cell-state="{ row }">
          <wx-tooltip v-if="row.is_published && !row.visible" :content="t('panel.invisible')">
            <wx-badge :type="stateType(row)" dot size="sm">{{ t(`states.${row.state}`) }}</wx-badge>
          </wx-tooltip>
          <wx-badge v-else :type="stateType(row)" dot size="sm">
            {{ t(`states.${row.state}`) }}
          </wx-badge>
        </template>

        <template #cell-updated_at="{ row }">
          <wx-date :value="row.updated_at" compact />
        </template>

        <template #cell-card="{ row }">
          <wx-entity-card
            variant="plain"
            :title="row.name"
            :image="row.image?.thumb ?? row.image?.url ?? undefined"
            image-size="56px"
            :title-lines="2"
            :subtitle="row.sku ?? undefined"
          >
            <template #meta>
              <wx-badge :type="stateType(row)" dot size="sm">{{
                t(`states.${row.state}`)
              }}</wx-badge>
              <wx-badge v-if="!row.category" type="warning" size="sm" round>
                {{ t('product.no-category') }}
              </wx-badge>
              <wx-text v-if="priced && row.price != null" size="sm">
                {{ price(row.price) }}{{ unit(row) }}
              </wx-text>
              <!-- The satellites' values that are there; an empty one is not worth a dash on a card. -->
              <template v-for="column in extra" :key="column.key">
                <column-value
                  v-if="hasValue(row.columns?.[column.key])"
                  :value="row.columns?.[column.key]"
                />
              </template>
            </template>
          </wx-entity-card>
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.name" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-catalog-products {
  min-width: 0;
}

.wx-catalog-products__selection,
.wx-catalog-products__corrected {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-8) var(--wx-space-16);
  border-bottom: 1px solid var(--wx-border-default);
  background: var(--wx-bg-subtle);
}

.wx-catalog-products__notice {
  /* The card already pads the table: the notice keeps to the same edges. */
  margin-bottom: var(--wx-space-12);
}

.wx-catalog-products__image {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: var(--wx-radius-sm);
  overflow: clip;
  background: var(--wx-bg-subtle);
  color: var(--wx-text-muted);
  vertical-align: middle;
}

.wx-catalog-products__image img {
  width: 100%;
  height: 100%;
  min-width: 0;
  object-fit: cover;
}

.wx-catalog-products__name {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-catalog-products__title {
  font-weight: var(--wx-font-weight-medium);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-products__sku {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
  min-height: 1em;
}

.wx-catalog-products__price {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-end;
  font-variant-numeric: tabular-nums;
}

/* A price and its unit are one reading: '99 990 / pcs' broken over two lines reads as two facts. */
.wx-catalog-products__price > * {
  white-space: nowrap;
}

.wx-catalog-products__price small,
.wx-catalog-products__price s {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-catalog-products__range {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--wx-space-8);
}
</style>

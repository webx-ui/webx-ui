<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
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
  WxAlert,
  WxBadge,
  WxEmpty,
  WxIcon,
  WxInput,
  WxSelect,
  WxSkeleton,
  WxSortableList,
  WxTooltip,
  type TabItem,
  type TabValue,
} from '@webx-ui/core'
import { createRecipesApi } from './api'
import { useRecipesMessages } from './i18n'
import { formatMinutes } from './minutes'
import RecipeCreateDialog from './RecipeCreateDialog.vue'
import type { RecipeRow, RecipeStatus, RecipesList } from './types'

/**
 * Every recipe at once, in the order the site shows them (§5.9).
 *
 * No paginator: this list is where recipes are put in order, and a drag cannot cross a page
 * boundary. Recipes have one order and only one (decision 4) — no order inside a category, none
 * inside a service — so the grips are there only while the list is the whole of it. Any filter
 * makes it a selection with invisible gaps in it, the grips go away and the line under the list
 * says why: that is the difference from services, where a category has an order of its own.
 *
 * A row is a card rather than a line of cells, which is what makes the same list work on a
 * phone: the picture, the name and the address on one side, the categories and the state beside
 * them on a wide screen and under them on a narrow one.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/recipes' })

const context = useAdmin()
const api = createRecipesApi(context)
const route = useRoute()
const router = useRouter()
useRecipesMessages()

const t = useTranslate('webx-recipes')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<RecipeRow[]>([])
const filters = ref<RecipesList['filters']>({ categories: [], nutrients: [], services: null })
const loading = ref(true)

const create = createModal<RecipeRow, Record<string, never>>(RecipeCreateDialog)

const canManage = computed(() => context.can('recipes.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'recipes')?.title ??
    t('module.recipes'),
)

/*
 * Everything the list is looking at lives in the address, so coming back from a recipe lands on
 * the same view, the same filters and the same search.
 */
const view = computed<TabValue>({
  get: () => (typeof route.query.view === 'string' ? route.query.view : ''),
  set: (value) => {
    void router.replace({
      query: { ...route.query, view: value === '' ? undefined : String(value) },
    })
  },
})

const inBin = computed(() => view.value === 'trashed')

function idIn(name: string): number | null {
  const raw = route.query[name]

  return typeof raw === 'string' && raw !== '' ? Number(raw) || null : null
}

const category = computed(() => idIn('category'))
const nutrient = computed(() => idIn('nutrient'))
const service = computed(() => idIn('service'))

const q = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))

/* What is typed, ahead of the address: the address follows it after a pause. */
const typed = ref(q.value)

const views = computed<TabItem[]>(() => [
  { value: '', label: t('panel.view-all') },
  { value: 'published', label: t('panel.view-published') },
  { value: 'draft', label: t('panel.view-draft') },
  { value: 'unpublished', label: t('panel.view-unpublished') },
  { value: 'trashed', label: t('panel.bin'), icon: 'trash' },
])

/** The whole list, with nothing narrowing it — the only list whose order can be dragged. */
const whole = computed(
  () =>
    view.value === '' &&
    q.value.trim() === '' &&
    category.value === null &&
    nutrient.value === null &&
    service.value === null,
)

/* Dragged only by somebody who may write, and only in a list that is the whole order. */
const sortable = computed(() => canManage.value && whole.value)

const hint = computed(() => t(whole.value ? 'panel.order-all' : 'panel.order-locked'))

const emptyText = computed(() => {
  if (q.value !== '') return t('panel.empty-search')

  return inBin.value ? t('panel.empty-bin') : t('panel.empty')
})

async function load(): Promise<void> {
  loading.value = true

  try {
    const answer = await api.list({
      q: q.value,
      category: category.value,
      nutrient: nutrient.value,
      service: service.value,
      status: inBin.value ? '' : (view.value as RecipeStatus | ''),
      trashed: inBin.value,
    })

    rows.value = answer.data
    filters.value = answer.filters
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch(
  () => [view.value, category.value, nutrient.value, service.value, q.value] as const,
  () => void load(),
)
void load()

let pause: ReturnType<typeof setTimeout> | undefined

watch(typed, (value) => {
  clearTimeout(pause)
  pause = setTimeout(() => {
    void router.replace({ query: { ...route.query, q: value.trim() === '' ? undefined : value } })
  }, 300)
})

onBeforeUnmount(() => clearTimeout(pause))

function narrow(name: 'category' | 'nutrient' | 'service', value: unknown): void {
  void router.replace({
    query: { ...route.query, [name]: typeof value === 'number' ? String(value) : undefined },
  })
}

const options = (list: { id: number; title: string }[] | null) =>
  (list ?? []).map((item) => ({ value: item.id, label: item.title }))

function open(recipe: RecipeRow): void {
  void router.push({ path: `${props.base}/${recipe.id}`, query: { ...route.query } })
}

/** A new recipe: the dialog asks for a title, the editor opens. */
async function add(): Promise<void> {
  const made = await create({})

  if (made) open(made)
}

/**
 * The order on screen, written as a whole. The list is already in its new order
 * (`WxSortableList` reorders the model before it says so), so a failure has to put it back
 * rather than leave the screen disagreeing with the database.
 */
async function reorder(): Promise<void> {
  try {
    await api.reorder(rows.value.map((row) => row.id))
  } catch (error) {
    toast.danger(message(error, t('panel.reorder-failed')))
    await load()
  }
}

function actionsFor(recipe: RecipeRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('panel.restore'),
        run: () => void restore(recipe),
      },
    ]
  }

  const actions: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(recipe) },
  ]

  if (recipe.url && recipe.status !== 'draft') {
    actions.push({
      key: 'site',
      icon: 'external-link',
      label: t('panel.open-on-site'),
      href: recipe.url,
    })
  }

  if (recipe.url) {
    actions.push({
      key: 'copy',
      icon: 'copy',
      label: t('panel.copy-address'),
      run: () => void copyAddress(recipe),
    })
  }

  actions.push(
    recipe.status === 'published' || recipe.status === 'modified'
      ? {
          key: 'unpublish',
          icon: 'eye-off',
          label: t('panel.unpublish'),
          run: () => void run(() => api.unpublish(recipe.id), t('panel.unpublished-done')),
        }
      : {
          key: 'publish',
          icon: 'upload',
          label: t('panel.publish'),
          run: () => void run(() => api.publish(recipe.id), t('panel.published')),
        },
  )

  actions.push({
    key: 'delete',
    icon: 'trash',
    label: t('panel.delete'),
    danger: true,
    run: () => void remove(recipe),
  })

  return actions
}

async function remove(recipe: RecipeRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { title: recipe.title }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await run(() => api.remove(recipe.id), t('panel.deleted'))
}

async function restore(recipe: RecipeRow): Promise<void> {
  await run(() => api.restore(recipe.id), t('panel.restored'))
}

/** One request, one sentence about it, and the list as the server now sees it. */
async function run(request: () => Promise<unknown>, said: string): Promise<void> {
  try {
    await request()
    toast.success(said)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

async function copyAddress(recipe: RecipeRow): Promise<void> {
  if (!recipe.url) return

  try {
    await navigator.clipboard.writeText(recipe.url)
    toast.success(t('panel.address-copied'))
  } catch {
    // A browser that refuses the clipboard without a gesture it trusts; the address is on screen.
  }
}

/* Live and live-with-edits are the same green; what is waiting has a chip of its own. */
function badge(status: RecipeStatus): 'default' | 'success' {
  return status === 'published' || status === 'modified' ? 'success' : 'default'
}

function address(recipe: RecipeRow): string {
  if (inBin.value) return '—'

  return recipe.path === null ? t('panel.no-address') : `/${recipe.path}`
}

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new', label: t('panel.new'), icon: 'plus', primary: true, run: () => void add() }]
    : [],
)
</script>

<template>
  <div class="wx-recipes">
    <wx-list-screen
      v-model:view="view"
      :title="title"
      :views="views"
      :actions="actions"
      padding="sm"
    >
      <div class="wx-recipes__bar">
        <wx-input
          v-model="typed"
          class="wx-recipes__search"
          size="sm"
          clearable
          :placeholder="t('panel.search')"
          :aria-label="t('panel.search')"
        >
          <template #prefix><wx-icon name="search" size="sm" /></template>
        </wx-input>

        <!-- In sight rather than behind a funnel: each of them decides whether the list can be
             dragged, and the line under the list says so. -->
        <wx-select
          :model-value="category"
          :options="options(filters.categories)"
          :placeholder="t('panel.any-category')"
          :aria-label="t('panel.filter-category')"
          clearable
          size="sm"
          @update:model-value="narrow('category', $event)"
        />
        <wx-select
          :model-value="nutrient"
          :options="options(filters.nutrients)"
          :placeholder="t('panel.any-nutrient')"
          :aria-label="t('panel.filter-nutrient')"
          clearable
          size="sm"
          @update:model-value="narrow('nutrient', $event)"
        />
        <!-- `null` from the server: the services module is not installed, and there is nothing
             a recipe could be related to. -->
        <wx-select
          v-if="filters.services !== null"
          :model-value="service"
          :options="options(filters.services)"
          :placeholder="t('panel.any-service')"
          :aria-label="t('panel.filter-service')"
          clearable
          size="sm"
          @update:model-value="narrow('service', $event)"
        />
      </div>

      <wx-skeleton v-if="loading && rows.length === 0" class="wx-recipes__loading" :rows="5" />

      <wx-empty
        v-else-if="rows.length === 0"
        :title="emptyText"
        :description="whole ? t('panel.empty-help') : undefined"
      />

      <template v-else>
        <!--
          A grip and not the whole row: the row opens a recipe, and a row that both opens and
          drags is one where one of the two happens by accident. No grips at all where the list
          is a selection — a handle that moves rows past invisible ones would be lying.
        -->
        <wx-sortable-list
          v-model="rows"
          class="wx-recipes__list"
          :class="{ 'is-loading': loading }"
          plain
          item-key="id"
          :handle="sortable ? 'grip' : 'row'"
          :item-label="(item: RecipeRow) => item.title"
          :disabled="!sortable"
          @move="reorder"
        >
          <template #default="{ item }">
            <component
              :is="inBin ? 'div' : 'router-link'"
              class="wx-recipe-row"
              v-bind="inBin ? {} : { to: { path: `${props.base}/${item.id}`, query: route.query } }"
            >
              <!-- The picture's box, never the picture: `<img>` has `min-width: auto`, and a
                   photograph would take the row (CLAUDE.md §4). -->
              <span class="wx-recipe-row__cover" :class="{ 'is-empty': !item.cover?.thumb }">
                <img v-if="item.cover?.thumb" :src="item.cover.thumb" alt="" loading="lazy" />
                <wx-icon v-else name="image" size="sm" />
              </span>

              <span class="wx-recipe-row__name">
                <span class="wx-recipe-row__title">{{ item.title }}</span>
                <span class="wx-recipe-row__address">{{ address(item) }}</span>
              </span>

              <span class="wx-recipe-row__facts">
                <span class="wx-recipe-row__chips">
                  <template v-for="(chip, index) in item.categories" :key="chip.id">
                    <wx-tooltip v-if="index === 0" :content="t('panel.main-category')">
                      <wx-badge size="sm" round type="primary">{{ chip.title }}</wx-badge>
                    </wx-tooltip>
                    <wx-badge v-else size="sm" round>{{ chip.title }}</wx-badge>
                  </template>
                </span>

                <span class="wx-recipe-row__state">
                  <span v-if="item.minutes" class="wx-recipe-row__time">
                    <wx-icon name="clock" size="sm" />{{ formatMinutes(item.minutes, t) }}
                  </span>
                  <wx-badge :type="badge(item.status)" dot size="sm">
                    {{ t(`panel.status-${item.status}`) }}
                  </wx-badge>
                  <wx-badge v-if="item.status === 'modified'" type="primary" size="sm" round>
                    {{ t('panel.edits') }}
                  </wx-badge>
                </span>
              </span>
            </component>
          </template>

          <template #actions="{ item }">
            <wx-row-menu :actions="actionsFor(item)" :label="item.title" />
          </template>
        </wx-sortable-list>

        <!-- What a drag does here, or why there is nothing to drag — under the rows it is about. -->
        <wx-alert
          v-if="canManage && !inBin"
          class="wx-recipes__note"
          type="info"
          :description="hint"
        />
      </template>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-recipes {
  min-width: 0;
  container-type: inline-size;
}

.wx-recipes__bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  padding: var(--wx-space-4) var(--wx-space-8) var(--wx-space-8);
}

.wx-recipes__search {
  flex: 1 1 240px;
  min-width: 0;
}

/* The select is a fragment (it carries its dropdown beside it), so our scope attribute never
   reaches it: styled from our own wrapper (CLAUDE.md §4). */
.wx-recipes__bar > :deep(.wx-select) {
  flex: 1 1 160px;
  max-width: 220px;
}

.wx-recipes__loading {
  padding: var(--wx-space-8);
}

.wx-recipes__list.is-loading {
  opacity: 0.6;
}

.wx-recipes__list :deep(.wx-sortable-list__row) {
  padding-inline: var(--wx-space-8);
}

.wx-recipes__list :deep(.wx-sortable-list__row:hover) {
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-sm);
}

.wx-recipes__note {
  margin-block-start: var(--wx-space-8);
}

.wx-recipe-row {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  grid-template-areas: 'cover name facts';
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-12);
  flex: 1 1 auto;
  min-width: 0;
  padding-block: var(--wx-space-4);
  color: inherit;
  text-decoration: none;
}

.wx-recipe-row__cover {
  grid-area: cover;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 32px;
  overflow: hidden;
  border-radius: var(--wx-radius-xs);
  background: var(--wx-bg-subtle);
  color: var(--wx-text-muted);
}

.wx-recipe-row__cover.is-empty {
  border: 1px dashed var(--wx-border-default);
  background: var(--wx-bg-surface);
}

.wx-recipe-row__cover img {
  width: 100%;
  height: 100%;
  min-width: 0;
  object-fit: cover;
}

.wx-recipe-row__name {
  grid-area: name;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-recipe-row__title {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-recipe-row__address {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-recipe-row__facts {
  grid-area: facts;
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-recipe-row__chips,
.wx-recipe-row__state {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-recipe-row__chips {
  justify-content: flex-end;
  max-width: 320px;
}

.wx-recipe-row__time {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
}

/*
 * Narrow: the facts go under the name rather than beside it, so the name keeps the width. By the
 * width of the list, not of the window — the panel's sidebar decides how much the list has.
 */
@container (max-width: 640px) {
  .wx-recipe-row {
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas:
      'cover name'
      'cover facts';
    align-items: start;
    column-gap: var(--wx-space-8);
  }

  .wx-recipe-row__facts {
    flex-wrap: wrap;
    gap: var(--wx-space-4);
  }

  /* One wrapping line of badges rather than two boxes side by side (see the services list). */
  .wx-recipe-row__chips,
  .wx-recipe-row__state {
    display: contents;
  }

  .wx-recipes__list :deep(.wx-sortable-list__row) {
    gap: var(--wx-space-8);
    padding-inline: var(--wx-space-4);
  }

  .wx-recipes__bar {
    padding-inline: var(--wx-space-4);
  }

  .wx-recipes__bar > :deep(.wx-select) {
    max-width: none;
  }
}
</style>

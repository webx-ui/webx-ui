<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useItemOrder,
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
import { createServicesApi, SERVICES_API } from './api'
import { useServicesMessages } from './i18n'
import ServiceCreateDialog from './ServiceCreateDialog.vue'
import type { ServiceCategoryRef, ServiceRow, ServiceStatus } from './types'

/**
 * The catalogue: every service at once, in the order the site shows them (§4.6).
 *
 * No paginator and no table. There are dozens of services, not thousands, and this list is where
 * they are put in order — a drag cannot cross a page boundary. The order is decision 5 of the
 * spec: without a filter the rows are the whole list and a drag moves the common order; narrowed
 * to one category they come in that category's own order and a drag moves only it; any other
 * narrowing (a search, a status, the bin) shows a selection with invisible gaps in it, so the
 * grips go away and the line under the list says why.
 *
 * A row is a card rather than a line of cells, which is what makes the same list work on a
 * phone: the picture, the name and the address on one side, the categories and the state beside
 * them on a wide screen and under them on a narrow one.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/services' })

const context = useAdmin()
const api = createServicesApi(context)
const route = useRoute()
const router = useRouter()
useServicesMessages()

const t = useTranslate('webx-services')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<ServiceRow[]>([])
const categories = ref<ServiceCategoryRef[]>([])
const loading = ref(true)

const create = createModal<ServiceRow, Record<string, never>>(ServiceCreateDialog)

const canManage = computed(() => context.can('services.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'services')?.title ??
    t('module.services'),
)

/*
 * Everything the list is looking at lives in the address, so coming back from a service lands on
 * the same view, the same category and the same search.
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

const category = computed<number | null>(() => {
  const raw = route.query.category

  return typeof raw === 'string' && raw !== '' ? Number(raw) || null : null
})

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

const order = useItemOrder(SERVICES_API, () => ({
  q: q.value,
  category: category.value,
  filtered: view.value !== '',
}))

/* Dragged only by somebody who may write, and only in a list that is a whole order. */
const sortable = computed(() => canManage.value && order.sortable.value)

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
      status: inBin.value ? '' : (view.value as ServiceStatus | ''),
      trashed: inBin.value,
    })

    rows.value = answer.data
    categories.value = answer.filters.categories
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch(
  () => [view.value, category.value, q.value] as const,
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

function narrow(value: unknown): void {
  void router.replace({
    query: { ...route.query, category: typeof value === 'number' ? String(value) : undefined },
  })
}

function open(service: ServiceRow): void {
  void router.push({ path: `${props.base}/${service.id}`, query: { ...route.query } })
}

/** A new service: the dialog asks for a title, the editor opens. */
async function add(): Promise<void> {
  const made = await create({})

  if (made) open(made)
}

/**
 * The order on screen, written as a whole — the list or the category's, whichever this is. The
 * list is already in its new order (`WxSortableList` reorders the model before it says so), so a
 * failure has to put it back rather than leave the screen disagreeing with the database.
 */
async function reorder(): Promise<void> {
  try {
    await order.move(rows.value.map((row) => row.id))
  } catch (error) {
    toast.danger(message(error, t('panel.reorder-failed')))
    await load()
  }
}

function actionsFor(service: ServiceRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('panel.restore'),
        run: () => void restore(service),
      },
    ]
  }

  const actions: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(service) },
  ]

  if (service.url && service.status !== 'draft') {
    actions.push({
      key: 'site',
      icon: 'external-link',
      label: t('panel.open-on-site'),
      href: service.url,
    })
  }

  if (service.url) {
    actions.push({
      key: 'copy',
      icon: 'copy',
      label: t('panel.copy-address'),
      run: () => void copyAddress(service),
    })
  }

  actions.push(
    service.status === 'published' || service.status === 'modified'
      ? {
          key: 'unpublish',
          icon: 'eye-off',
          label: t('panel.unpublish'),
          run: () => void run(() => api.unpublish(service.id), t('panel.unpublished-done')),
        }
      : {
          key: 'publish',
          icon: 'upload',
          label: t('panel.publish'),
          run: () => void run(() => api.publish(service.id), t('panel.published')),
        },
  )

  actions.push({
    key: 'delete',
    icon: 'trash',
    label: t('panel.delete'),
    danger: true,
    run: () => void remove(service),
  })

  return actions
}

async function remove(service: ServiceRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { title: service.title }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await run(() => api.remove(service.id), t('panel.deleted'))
}

async function restore(service: ServiceRow): Promise<void> {
  await run(() => api.restore(service.id), t('panel.restored'))
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

async function copyAddress(service: ServiceRow): Promise<void> {
  if (!service.url) return

  try {
    await navigator.clipboard.writeText(service.url)
    toast.success(t('panel.address-copied'))
  } catch {
    // A browser that refuses the clipboard without a gesture it trusts; the address is on screen.
  }
}

/* Live and live-with-edits are the same green; what is waiting has a chip of its own. */
function badge(status: ServiceStatus): 'default' | 'success' {
  return status === 'published' || status === 'modified' ? 'success' : 'default'
}

function address(service: ServiceRow): string {
  if (inBin.value) return '—'

  return service.path === null ? t('panel.no-address') : `/${service.path}`
}

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new', label: t('panel.new'), icon: 'plus', primary: true, run: () => void add() }]
    : [],
)
</script>

<template>
  <div class="wx-services">
    <wx-list-screen
      v-model:view="view"
      :title="title"
      :views="views"
      :actions="actions"
      padding="sm"
    >
      <div class="wx-services__bar">
        <wx-input
          v-model="typed"
          class="wx-services__search"
          size="sm"
          clearable
          :placeholder="t('panel.search')"
          :aria-label="t('panel.search')"
        >
          <template #prefix><wx-icon name="search" size="sm" /></template>
        </wx-input>

        <!-- Always in sight rather than behind a funnel: it is the one filter that decides which
             order a drag writes, and the list says so right under it. -->
        <wx-select
          class="wx-services__category"
          :model-value="category"
          :options="categories.map((item) => ({ value: item.id, label: item.title }))"
          :placeholder="t('panel.any-category')"
          :aria-label="t('panel.filter-category')"
          clearable
          size="sm"
          @update:model-value="narrow"
        />
      </div>

      <wx-skeleton v-if="loading && rows.length === 0" class="wx-services__loading" :rows="5" />

      <wx-empty
        v-else-if="rows.length === 0"
        :title="emptyText"
        :description="
          view === '' && q === '' && category === null ? t('panel.empty-help') : undefined
        "
      />

      <template v-else>
        <!--
          A grip and not the whole row: the row opens a service, and a row that both opens and
          drags is one where one of the two happens by accident. No grips at all where the list
          is a selection — a handle that moves rows past invisible ones would be lying.
        -->
        <wx-sortable-list
          v-model="rows"
          class="wx-services__list"
          :class="{ 'is-loading': loading }"
          plain
          item-key="id"
          :handle="sortable ? 'grip' : 'row'"
          :item-label="(item: ServiceRow) => item.title"
          :disabled="!sortable"
          @move="reorder"
        >
          <template #default="{ item }">
            <component
              :is="inBin ? 'div' : 'router-link'"
              class="wx-service-row"
              v-bind="inBin ? {} : { to: { path: `${props.base}/${item.id}`, query: route.query } }"
            >
              <!-- The picture's box, never the picture: `<img>` has `min-width: auto`, and a
                   photograph would take the row (CLAUDE.md §4). -->
              <span class="wx-service-row__cover" :class="{ 'is-empty': !item.cover }">
                <img v-if="item.cover?.thumb" :src="item.cover.thumb" alt="" loading="lazy" />
                <wx-icon v-else name="image" size="sm" />
              </span>

              <span class="wx-service-row__name">
                <span class="wx-service-row__title">{{ item.title }}</span>
                <span class="wx-service-row__address">{{ address(item) }}</span>
              </span>

              <span class="wx-service-row__facts">
                <span class="wx-service-row__chips">
                  <template v-for="(chip, index) in item.categories" :key="chip.id">
                    <wx-tooltip v-if="index === 0" :content="t('panel.main-category')">
                      <wx-badge size="sm" round type="primary">{{ chip.title }}</wx-badge>
                    </wx-tooltip>
                    <wx-badge v-else size="sm" round>{{ chip.title }}</wx-badge>
                  </template>
                </span>

                <span class="wx-service-row__state">
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
          class="wx-services__note"
          type="info"
          :description="order.hint.value"
        />
      </template>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-services {
  min-width: 0;
  container-type: inline-size;
}

.wx-services__bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  padding: var(--wx-space-4) var(--wx-space-8) var(--wx-space-8);
}

.wx-services__search {
  flex: 1 1 240px;
  min-width: 0;
}

/* The select is a fragment (it carries its dropdown beside it), so our scope attribute never
   reaches it: styled from our own wrapper (CLAUDE.md §4). */
.wx-services__bar > :deep(.wx-select) {
  flex: 1 1 160px;
  max-width: 280px;
}

.wx-services__loading {
  padding: var(--wx-space-8);
}

.wx-services__list.is-loading {
  opacity: 0.6;
}

.wx-services__list :deep(.wx-sortable-list__row) {
  padding-inline: var(--wx-space-8);
}

.wx-services__list :deep(.wx-sortable-list__row:hover) {
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-sm);
}

.wx-services__note {
  margin-block-start: var(--wx-space-8);
}

.wx-service-row {
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

.wx-service-row__cover {
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

.wx-service-row__cover.is-empty {
  border: 1px dashed var(--wx-border-default);
  background: var(--wx-bg-surface);
}

.wx-service-row__cover img {
  width: 100%;
  height: 100%;
  min-width: 0;
  object-fit: cover;
}

.wx-service-row__name {
  grid-area: name;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-service-row__title {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-service-row__address {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-service-row__facts {
  grid-area: facts;
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-service-row__chips,
.wx-service-row__state {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-service-row__chips {
  justify-content: flex-end;
  max-width: 320px;
}

/*
 * Narrow: the facts go under the name rather than beside it, so the name keeps the width. By the
 * width of the list, not of the window — the panel's sidebar decides how much the list has.
 */
@container (max-width: 640px) {
  .wx-service-row {
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas:
      'cover name'
      'cover facts';
    align-items: start;
  }

  .wx-service-row__facts {
    flex-wrap: wrap;
    gap: var(--wx-space-4);
  }

  /* One wrapping line of badges rather than two boxes side by side: squeezed next to each
     other, each box shrank to its widest badge and stacked the categories one per line. */
  .wx-service-row__chips,
  .wx-service-row__state {
    display: contents;
  }

  /* Every pixel of a 375px row goes to the name: the grip and the menu keep their size, the
     gaps between them do not. */
  .wx-service-row {
    column-gap: var(--wx-space-8);
  }

  .wx-services__list :deep(.wx-sortable-list__row) {
    gap: var(--wx-space-8);
    padding-inline: var(--wx-space-4);
  }

  .wx-services__bar {
    padding-inline: var(--wx-space-4);
  }

  .wx-services__bar > :deep(.wx-select) {
    max-width: none;
  }
}
</style>

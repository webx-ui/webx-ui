<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  rowMenuWidth,
  useAdmin,
  useDates,
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
  WxBadge,
  WxButton,
  WxEntityCard,
  WxFormItem,
  WxIcon,
  WxSelect,
  WxTable,
  WxText,
  WxTooltip,
  type TabItem,
  type TableColumn,
  type TableState,
  type TabValue,
} from '@webx-ui/core'
import { createEventsApi } from './api'
import EventCreateDialog from './EventCreateDialog.vue'
import { useEventsMessages } from './i18n'
import type { EventRow, EventStatus, EventsPage, EventWhen } from './types'

/**
 * The section: a page of events with the filters of §4.9 over it.
 *
 * A paginator and not a sortable list, which is the whole difference from recipes: events have
 * no order of their own — the date is the order (decision 4) — and they pile up for years, so the
 * screen is twenty rows, the filters and a page number.
 *
 * The tabs are *when*, not the state: the upcoming ones are what an editor comes here for and
 * what the site's lists show, so they are the list with nothing chosen. The past ones are a tab
 * away, and "all" draws the past ones muted, so the line between the two can be seen at a glance.
 * The state, the category and the service live behind the funnel.
 *
 * Below {@link CARDS} the row becomes a card, the way the articles do it: the cover on the left,
 * everything else beside it.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/events' })

/** Where the row stops being a row. Below it the table draws cards and this screen draws one. */
const CARDS = 640

const context = useAdmin()
const api = createEventsApi(context)
const route = useRoute()
const router = useRouter()
const dates = useDates()
useEventsMessages()

const t = useTranslate('webx-events')
/* The two words a list needs as soon as it has filters belong to the panel, not to the events. */
const admin = useTranslate('webx-admin')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)

const page = ref<EventsPage | null>(null)
const loading = ref(true)

const create = createModal<EventRow, Record<string, never>>(EventCreateDialog)

const canManage = computed(() => context.can('events.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'events')?.title ??
    t('module.events'),
)

/**
 * Everything the list is looking at lives in the address, so coming back from an event lands on
 * the same tab, the same filters and the same page.
 */
const view = computed<TabValue>({
  get: () => (typeof route.query.view === 'string' ? route.query.view : ''),
  set: (value) => {
    // A new view is a new list: the page it was on belongs to the view it was on.
    void router.replace({
      query: { ...route.query, view: value === '' ? undefined : String(value), page: undefined },
    })
  },
})

const inBin = computed(() => view.value === 'trashed')

/** The bin holds whatever was deleted, whenever it was to happen. */
const when = computed<EventWhen>(() => {
  if (inBin.value || view.value === 'all') return 'all'

  return view.value === 'past' ? 'past' : 'upcoming'
})

const query = computed(() => ({
  q: typeof route.query.q === 'string' ? route.query.q : '',
  category: number(route.query.category),
  service: number(route.query.service),
  status: (typeof route.query.status === 'string' ? route.query.status : '') as EventStatus | '',
  page: Number(route.query.page ?? 1) || 1,
}))

function number(value: unknown): number | null {
  return typeof value === 'string' && value !== '' ? Number(value) || null : null
}

const views = computed<TabItem[]>(() => [
  { value: '', label: t('panel.view-upcoming') },
  { value: 'past', label: t('panel.view-past') },
  { value: 'all', label: t('panel.view-all') },
  { value: 'trashed', label: t('panel.bin'), icon: 'trash' },
])

const filters = computed(() => page.value?.filters ?? { categories: [], services: null })

const statuses = computed(() =>
  (['published', 'draft', 'unpublished'] as const).map((status) => ({
    value: status,
    label: t(`panel.status-${status}`),
  })),
)

const asCards = computed(() => width.value > 0 && width.value < CARDS)

/**
 * Six columns, or one. Every column but the title carries a width and the table is laid out
 * `fixed`, so the ones that drop below a breakpoint give what they had to the title (CLAUDE.md §4
 * on `WxActions collapse` in a table). They go in the order they can be spared — the categories
 * first — and what is left is the cover, the title, when and the state: what an editor scanning
 * a list of events is looking for.
 */
const columns = computed<TableColumn<EventRow>[]>(() => {
  if (asCards.value) {
    return [{ key: 'card', label: '' }]
  }

  return [
    { key: 'cover', label: '', width: 68 },
    /* The one that takes what the others leave, and the only one without a width. */
    { key: 'title', label: t('panel.column-title'), minWidth: 220 },
    {
      key: 'when',
      label: inBin.value ? t('panel.column-deleted') : t('panel.column-when'),
      width: 210,
      hideBelow: 760,
    },
    { key: 'categories', label: t('panel.column-categories'), width: 190, hideBelow: 1080 },
    { key: 'status', label: t('panel.column-status'), width: 150 },
    { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
  ]
})

const emptyText = computed(() => {
  if (query.value.q !== '') return t('panel.empty-search')
  if (inBin.value) return t('panel.empty-bin')
  if (view.value === 'past') return t('panel.empty-past')

  return view.value === 'all' ? t('panel.empty') : t('panel.empty-upcoming')
})

/**
 * What the dropdowns behind the funnel are set to, said in the reader's words. A shut panel says
 * nothing about itself, and a list narrowed by something nobody can see is a list that looks
 * wrong.
 */
const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []

  if (query.value.status !== '') {
    chips.push({
      key: 'status',
      label: `${t('panel.filter-status')}: ${t(`panel.status-${query.value.status}`)}`,
      clear: () => narrow('status', undefined),
    })
  }

  const category = filters.value.categories.find((item) => item.id === query.value.category)
  if (category) {
    chips.push({
      key: 'category',
      label: `${t('panel.filter-category')}: ${category.title}`,
      clear: () => narrow('category', undefined),
    })
  }

  const service = filters.value.services?.find((item) => item.id === query.value.service)
  if (service) {
    chips.push({
      key: 'service',
      label: `${t('panel.filter-service')}: ${service.title}`,
      clear: () => narrow('service', undefined),
    })
  }

  return chips
})

function clearFilters(): void {
  void router.replace({
    query: {
      ...route.query,
      status: undefined,
      category: undefined,
      service: undefined,
      page: undefined,
    },
  })
}

async function load(state?: TableState): Promise<void> {
  loading.value = true

  try {
    page.value = await api.list({
      when: when.value,
      q: state?.search ?? query.value.q,
      status: inBin.value ? '' : query.value.status,
      trashed: inBin.value,
      category: query.value.category,
      service: query.value.service,
      page: state?.page ?? query.value.page,
      per_page: state?.perPage,
    })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

/**
 * The table reports everything it knows in one event, and that is the one place the address is
 * written from — so a page and a search both survive opening an event and coming back.
 */
function onState(state: TableState): void {
  const wanted: LocationQueryRaw = {
    ...route.query,
    q: state.search === '' ? undefined : state.search,
    page: state.page === 1 ? undefined : String(state.page),
  }

  void router.replace({ query: wanted })
  void load(state)
}

/** One of the dropdowns moved: a new list, from its first page. */
function narrow(name: 'status' | 'category' | 'service', value: unknown): void {
  void router.replace({
    query: {
      ...route.query,
      [name]:
        typeof value === 'number' || (typeof value === 'string' && value !== '')
          ? String(value)
          : undefined,
      page: undefined,
    },
  })
}

/*
 * Sources one by one, not a getter returning an array: a fresh array is a new value on every change
 * of the address, and a page turned by the table would be read a second time without its size.
 */
watch(
  [view, () => query.value.status, () => query.value.category, () => query.value.service],
  () => void load(),
)

void load()

/** The bin never reaches here: the table is told its rows lead nowhere (CLAUDE.md §4). */
function open(event: EventRow): void {
  void router.push({ path: `${props.base}/${event.id}`, query: { ...route.query } })
}

/** A new event: the dialog asks for a title, the editor opens. */
async function add(): Promise<void> {
  const made = await create({})

  if (made) open(made)
}

function actionsFor(event: EventRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('panel.restore'),
        run: () => void restore(event),
      },
    ]
  }

  const actions: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(event) },
    {
      key: 'duplicate',
      icon: 'copy',
      label: t('panel.duplicate'),
      run: () => void duplicate(event),
    },
  ]

  if (event.url && event.status !== 'draft') {
    actions.push({
      key: 'site',
      icon: 'external-link',
      label: t('panel.open-on-site'),
      href: event.url,
    })
  }

  actions.push(
    event.status === 'published' || event.status === 'modified'
      ? {
          key: 'unpublish',
          icon: 'eye-off',
          label: t('panel.unpublish'),
          run: () => void run(() => api.unpublish(event.id), t('panel.unpublished-done')),
        }
      : {
          key: 'publish',
          icon: 'upload',
          label: t('panel.publish'),
          run: () => void run(() => api.publish(event.id), t('panel.published')),
        },
  )

  actions.push({
    key: 'delete',
    icon: 'trash',
    label: t('panel.delete'),
    danger: true,
    run: () => void remove(event),
  })

  return actions
}

/**
 * The next date of the same event (decision 9): a copy as a draft, opened straight away — the
 * date is the first thing that differs, and it is on the editor, not here.
 */
async function duplicate(event: EventRow): Promise<void> {
  try {
    const copy = await api.duplicate(event.id)

    toast.success(t('event.duplicated'))
    open(copy.event)
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(event: EventRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { title: event.title }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await run(() => api.remove(event.id), t('panel.deleted'))
}

async function restore(event: EventRow): Promise<void> {
  await run(() => api.restore(event.id), t('panel.restored'))
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

/* Live and live-with-edits are the same green; what is waiting has a chip of its own. */
function badge(status: EventStatus): 'default' | 'success' {
  return status === 'published' || status === 'modified' ? 'success' : 'default'
}

function address(event: EventRow): string {
  if (inBin.value) return '—'

  return event.path === null ? t('panel.no-address') : `/${event.path}`
}

/**
 * The exact moments behind the printed line, in the reader's own zone — the line is the site's
 * (§4.6), in the site's zone and with `date_note` over it, and the tip is where "when exactly" is
 * answered.
 */
function exact(event: EventRow): string {
  if (event.starts_at === null) return ''

  const start = dates.exact(event.starts_at)

  return event.ends_at === null ? start : `${start} – ${dates.exact(event.ends_at)}`
}

/** The line in the column: what the site prints, or a word for an event with no date at all. */
function whenOf(event: EventRow): string {
  return event.when !== '' ? event.when : t('panel.no-date')
}

/* In "all" the past ones are muted, so the line between the two halves shows. */
function rowClass(event: EventRow): string | undefined {
  return event.past && view.value === 'all' ? 'is-past' : undefined
}

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new', label: t('panel.new'), icon: 'plus', primary: true, run: () => void add() }]
    : [],
)
</script>

<template>
  <div ref="root" class="wx-events">
    <wx-list-screen v-model:view="view" :title="title" :views="views" :actions="actions">
      <wx-table
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        :clickable="!inBin"
        :hover="!inBin"
        flush
        :loading="loading"
        layout="fixed"
        :cards-below="CARDS"
        :row-class="rowClass"
        :filters-count="applied.length"
        :filters-label="admin('filters.title')"
        :search-placeholder="t('panel.search')"
        :empty-text="emptyText"
        :aria-label="title"
        @row-click="open"
        @state-change="onState"
      >
        <template #filters>
          <wx-form-item v-if="!inBin" :label="t('panel.filter-status')">
            <wx-select
              :model-value="query.status === '' ? null : query.status"
              :options="statuses"
              :placeholder="t('panel.any-status')"
              clearable
              size="sm"
              @update:model-value="(value: unknown) => narrow('status', value)"
            />
          </wx-form-item>

          <wx-form-item :label="t('panel.filter-category')">
            <wx-select
              :model-value="query.category"
              :options="filters.categories.map((item) => ({ value: item.id, label: item.title }))"
              :placeholder="t('panel.any-category')"
              clearable
              size="sm"
              @update:model-value="(value: unknown) => narrow('category', value)"
            />
          </wx-form-item>

          <!-- `null` from the server: the services module is not installed, and there is nothing
               an event could be related to. -->
          <wx-form-item v-if="filters.services !== null" :label="t('panel.filter-service')">
            <wx-select
              :model-value="query.service"
              :options="filters.services.map((item) => ({ value: item.id, label: item.title }))"
              :placeholder="t('panel.any-service')"
              clearable
              filterable
              size="sm"
              @update:model-value="(value: unknown) => narrow('service', value)"
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

        <template #cell-cover="{ row }">
          <span class="wx-events__cover" :class="{ 'is-empty': !row.cover?.thumb }">
            <img v-if="row.cover?.thumb" :src="row.cover.thumb" alt="" loading="lazy" />
            <wx-icon v-else name="calendar" size="sm" />
          </span>
        </template>

        <template #cell-title="{ row }">
          <div class="wx-events__name">
            <span class="wx-events__title">{{ row.title }}</span>
            <span class="wx-events__address">{{ address(row) }}</span>
          </div>
        </template>

        <template #cell-when="{ row }">
          <wx-date v-if="inBin" :value="row.deleted_at" compact />
          <wx-tooltip v-else-if="exact(row)" :content="exact(row)">
            <span class="wx-events__when">{{ whenOf(row) }}</span>
          </wx-tooltip>
          <span v-else class="wx-events__when" :class="{ 'is-none': row.when === '' }">
            {{ whenOf(row) }}
          </span>
        </template>

        <template #cell-categories="{ row }">
          <span class="wx-events__chips">
            <template v-for="(chip, index) in row.categories" :key="chip.id">
              <wx-tooltip v-if="index === 0" :content="t('panel.main-category')">
                <wx-badge size="sm" round type="primary">{{ chip.title }}</wx-badge>
              </wx-tooltip>
              <wx-badge v-else size="sm" round>{{ chip.title }}</wx-badge>
            </template>
          </span>
        </template>

        <template #cell-status="{ row }">
          <span class="wx-events__state">
            <wx-badge :type="badge(row.status)" dot size="sm">
              {{ t(`panel.status-${row.status}`) }}
            </wx-badge>
            <wx-badge v-if="row.status === 'modified'" type="primary" size="sm" round>
              {{ t('panel.edits') }}
            </wx-badge>
          </span>
        </template>

        <!-- The card: the cover on the left, everything else beside it. One field and no label,
             because the table's own stack of labelled lines is five lines for one event. -->
        <template #cell-card="{ row }">
          <wx-entity-card
            class="wx-events__entity"
            variant="plain"
            :title="row.title"
            :image="row.cover?.thumb ?? undefined"
            image-size="56px"
            :title-lines="2"
            :subtitle="address(row)"
          >
            <template #meta>
              <wx-text size="sm" :tone="row.past ? 'muted' : 'default'">
                {{ inBin ? dates.short(row.deleted_at) : whenOf(row) }}
              </wx-text>
              <wx-badge :type="badge(row.status)" dot size="sm">
                {{ t(`panel.status-${row.status}`) }}
              </wx-badge>
              <wx-badge v-if="row.categories[0]" size="sm" round>
                {{ row.categories[0].title }}
              </wx-badge>
            </template>
          </wx-entity-card>
        </template>

        <template #card-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.title" />
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.title" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-events {
  min-width: 0;
}

/* The picture's box, never the picture: `<img>` has `min-width: auto` (CLAUDE.md §4). */
.wx-events__cover {
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

.wx-events__cover.is-empty {
  border: 1px dashed var(--wx-border-default);
  background: var(--wx-bg-surface);
}

.wx-events__cover img {
  width: 100%;
  height: 100%;
  min-width: 0;
  object-fit: cover;
}

.wx-events__name {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-events__title {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-events__address {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-events__when {
  font-size: var(--wx-font-size-sm);
}

.wx-events__when.is-none {
  color: var(--wx-text-muted);
}

.wx-events__chips,
.wx-events__state {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  overflow: hidden;
}

/*
 * A past event in "all": the whole row a step quieter rather than a badge of its own — "is this
 * still ahead" is answered by the eye running down the list, not by reading. `:deep()` because
 * the row is the table's element and the class is ours (CLAUDE.md §4).
 */
.wx-events :deep(.is-past) .wx-events__title,
.wx-events :deep(.is-past) .wx-events__when {
  color: var(--wx-text-muted);
}

.wx-events :deep(.is-past) .wx-events__cover {
  opacity: 0.6;
}
</style>

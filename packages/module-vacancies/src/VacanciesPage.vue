<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
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
  WxAction,
  WxAlert,
  WxBadge,
  WxButton,
  WxEmpty,
  WxFormItem,
  WxIcon,
  WxIndicator,
  WxInput,
  WxPopover,
  WxSelect,
  WxSkeleton,
  WxSortableList,
  WxTooltip,
  type TabItem,
  type TabValue,
} from '@webx-ui/core'
import { createVacanciesApi } from './api'
import { formatDay, longDay } from './days'
import { useVacanciesMessages } from './i18n'
import { employmentKey } from './messages'
import VacancyCreateDialog from './VacancyCreateDialog.vue'
import type { VacanciesList, VacancyRow, VacancyState, VacancyStatus } from './types'

/**
 * Every vacancy at once, in the order the site lists them (§4.10).
 *
 * The shape of the recipes, with the tabs of the events: no paginator, because this is where the
 * order is dragged and a drag cannot cross a page — a site has tens of vacancies, not thousands.
 * The tabs are *open or closed*, not the state: the open ones are what the site lists and what an
 * editor comes here for, so they are the list with nothing chosen. "All" draws the closed ones
 * muted, so the line between the two can be seen at a glance; the category, the state and the
 * search narrow any of them.
 *
 * One order, and only one (§4.11). The grips are there on "Open" and "All" while nothing narrows
 * the list — a selection has invisible gaps in it, and a closed vacancy is on no list of the site,
 * so ordering the "Closed" tab would be ordering nothing. The line under the rows says which of
 * the three it is.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/vacancies' })

const context = useAdmin()
const api = createVacanciesApi(context)
const route = useRoute()
const router = useRouter()
useVacanciesMessages()

const t = useTranslate('webx-vacancies')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<VacancyRow[]>([])
const filters = ref<VacanciesList['filters']>({ categories: [] })
const loading = ref(true)

const create = createModal<VacancyRow, Record<string, never>>(VacancyCreateDialog)

const canManage = computed(() => context.can('vacancies.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'vacancies')?.title ??
    t('module.vacancies'),
)

const locale = computed(() => context.i18n.state.locale)

/*
 * Everything the list is looking at lives in the address, so coming back from a vacancy lands on
 * the same tab, the same filters and the same search.
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

const state = computed<VacancyState>(() => {
  if (view.value === 'closed') return 'closed'

  return view.value === 'all' ? 'all' : 'open'
})

const category = computed(() => {
  const raw = route.query.category

  return typeof raw === 'string' && raw !== '' ? Number(raw) || null : null
})

const status = computed(
  () => (typeof route.query.status === 'string' ? route.query.status : '') as VacancyStatus | '',
)

const q = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))

/* What is typed, ahead of the address: the address follows it after a pause. */
const typed = ref(q.value)

const views = computed<TabItem[]>(() => [
  { value: '', label: t('panel.view-open') },
  { value: 'closed', label: t('panel.view-closed') },
  { value: 'all', label: t('panel.view-all') },
  { value: 'trashed', label: t('panel.bin'), icon: 'trash' },
])

const statuses = computed(() =>
  (['published', 'draft', 'unpublished'] as const).map((one) => ({
    value: one,
    label: t(`panel.status-${one}`),
  })),
)

/** Nothing narrows the list: no search, no category, no state. */
const unfiltered = computed(
  () => q.value.trim() === '' && category.value === null && status.value === '',
)

/** The one order, as far as this tab can show it (§4.10). */
const ordered = computed(() => state.value !== 'closed' && !inBin.value)

/* Dragged only by somebody who may write, and only in a list that is the whole order. */
const sortable = computed(() => canManage.value && ordered.value && unfiltered.value)

const hint = computed(() => {
  if (!ordered.value) return t('panel.order-closed')

  return t(unfiltered.value ? 'panel.order-all' : 'panel.order-locked')
})

const emptyText = computed(() => {
  if (q.value !== '') return t('panel.empty-search')
  if (inBin.value) return t('panel.empty-bin')
  if (state.value === 'closed') return t('panel.empty-closed')

  return state.value === 'all' ? t('panel.empty') : t('panel.empty-open')
})

/**
 * What the dropdowns behind the funnel are set to, said in the reader's words. A shut panel says
 * nothing about itself, and a list narrowed by something nobody can see is a list that looks
 * wrong — here doubly, because it is also why the grips went away.
 */
const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []

  if (status.value !== '') {
    chips.push({
      key: 'status',
      label: `${t('panel.filter-status')}: ${t(`panel.status-${status.value}`)}`,
      clear: () => narrow('status', undefined),
    })
  }

  const chosen = filters.value.categories.find((item) => item.id === category.value)

  if (chosen) {
    chips.push({
      key: 'category',
      label: `${t('panel.filter-category')}: ${chosen.title}`,
      clear: () => narrow('category', undefined),
    })
  }

  return chips
})

async function load(): Promise<void> {
  loading.value = true

  try {
    const answer = await api.list({
      state: state.value,
      q: q.value,
      category: category.value,
      status: inBin.value ? '' : status.value,
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

/*
 * Sources one by one, not a getter returning an array: a fresh array is a new value on every
 * change of the address, and the list would be read twice for one click.
 */
watch([view, category, status, q], () => void load())
void load()

let pause: ReturnType<typeof setTimeout> | undefined

watch(typed, (value) => {
  clearTimeout(pause)
  pause = setTimeout(() => {
    void router.replace({ query: { ...route.query, q: value.trim() === '' ? undefined : value } })
  }, 300)
})

onBeforeUnmount(() => clearTimeout(pause))

function narrow(name: 'category' | 'status', value: unknown): void {
  void router.replace({
    query: {
      ...route.query,
      [name]:
        typeof value === 'number' || (typeof value === 'string' && value !== '')
          ? String(value)
          : undefined,
    },
  })
}

function clearFilters(): void {
  const query: LocationQueryRaw = { ...route.query }

  delete query.category
  delete query.status
  void router.replace({ query })
}

function open(vacancy: VacancyRow): void {
  void router.push({ path: `${props.base}/${vacancy.id}`, query: { ...route.query } })
}

/** A new vacancy: the dialog asks for the position, the editor opens. */
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

const live = (vacancy: VacancyRow) =>
  vacancy.status === 'published' || vacancy.status === 'modified'

function actionsFor(vacancy: VacancyRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('panel.restore'),
        run: () => void restore(vacancy),
      },
    ]
  }

  const actions: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(vacancy) },
    {
      key: 'duplicate',
      icon: 'copy',
      label: t('panel.duplicate'),
      run: () => void duplicate(vacancy),
    },
  ]

  if (vacancy.url && vacancy.status !== 'draft') {
    actions.push({
      key: 'site',
      icon: 'external-link',
      label: t('panel.open-on-site'),
      href: vacancy.url,
    })
  }

  /*
   * Closing is a publication of `is_closed` (§4.11), so it is offered only for what is on the
   * site: closing a draft would publish it. A vacancy closed only by its date is not reopened
   * here — its date is what closes it, and that is changed in the editor.
   */
  if (live(vacancy) && vacancy.closed_reason === 'manual') {
    actions.push({
      key: 'reopen',
      icon: 'refresh',
      label: t('panel.reopen'),
      run: () => void reopen(vacancy),
    })
  } else if (live(vacancy) && vacancy.closed_reason === null) {
    actions.push({
      key: 'close',
      icon: 'lock',
      label: t('panel.close'),
      run: () => void close(vacancy),
    })
  }

  actions.push(
    live(vacancy)
      ? {
          key: 'unpublish',
          icon: 'eye-off',
          label: t('panel.unpublish'),
          run: () => void run(() => api.unpublish(vacancy.id), t('panel.unpublished-done')),
        }
      : {
          key: 'publish',
          icon: 'upload',
          label: t('panel.publish'),
          run: () => void run(() => api.publish(vacancy.id), t('panel.published')),
        },
  )

  actions.push({
    key: 'delete',
    icon: 'trash',
    label: t('panel.delete'),
    danger: true,
    run: () => void remove(vacancy),
  })

  return actions
}

/**
 * The same position somewhere else (decision 20): a copy as a draft, opened straight away — the
 * city is the first thing that differs, and it is on the editor, not here.
 */
async function duplicate(vacancy: VacancyRow): Promise<void> {
  try {
    const copy = await api.duplicate(vacancy.id)

    toast.success(t('panel.duplicated'))
    open(copy.vacancy)
  } catch (error) {
    toast.danger(message(error))
  }
}

/**
 * Closing publishes, so edits waiting in the draft would go out with it; the server refuses that
 * with a 409, and the panel says so before asking — the answer is the same, and a question whose
 * only answer is "no" is not one to put.
 */
async function close(vacancy: VacancyRow): Promise<void> {
  if (vacancy.status === 'modified') {
    toast.warning(t('panel.close-edits'))

    return
  }

  const agreed = await confirm({
    title: t('panel.close-title', { title: vacancy.title }),
    message: t('panel.close-text'),
    confirmText: t('panel.close'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed) return

  await run(() => api.close(vacancy.id), t('panel.closed'))
}

async function reopen(vacancy: VacancyRow): Promise<void> {
  if (vacancy.status === 'modified') {
    toast.warning(t('panel.close-edits'))

    return
  }

  const agreed = await confirm({
    title: t('panel.reopen-title', { title: vacancy.title }),
    message: t('panel.reopen-text'),
    confirmText: t('panel.reopen'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed) return

  try {
    const row = await api.reopen(vacancy.id)

    // Taken off by hand and past its day as well: it is open to the switch, closed to the date.
    if (row.closed) toast.warning(t('panel.reopen-expired'))
    else toast.success(t('panel.reopened'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

async function remove(vacancy: VacancyRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { title: vacancy.title }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await run(() => api.remove(vacancy.id), t('panel.deleted'))
}

async function restore(vacancy: VacancyRow): Promise<void> {
  await run(() => api.restore(vacancy.id), t('panel.restored'))
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
function badge(value: VacancyStatus): 'default' | 'success' {
  return value === 'published' || value === 'modified' ? 'success' : 'default'
}

function address(vacancy: VacancyRow): string {
  if (inBin.value) return '—'

  return vacancy.path === null ? t('panel.no-address') : `/${vacancy.path}`
}

/** Where: the city, "Remote", or the city and "Hybrid" — what a candidate reads first. */
function where(vacancy: VacancyRow): string {
  if (vacancy.workplace === 'remote') return t('vacancy.workplace.remote')

  const city = vacancy.city !== '' ? vacancy.city : t('panel.no-city')

  return vacancy.workplace === 'hybrid' ? `${city} · ${t('vacancy.workplace.hybrid')}` : city
}

function employment(vacancy: VacancyRow): string {
  return vacancy.employment_types.map((code) => t(employmentKey(code))).join(', ')
}

/* In "all" the closed ones are muted, so the line between the two halves shows. */
function muted(vacancy: VacancyRow): boolean {
  return vacancy.closed && state.value === 'all' && !inBin.value
}

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new', label: t('panel.new'), icon: 'plus', primary: true, run: () => void add() }]
    : [],
)
</script>

<template>
  <div class="wx-vacancies">
    <wx-list-screen
      v-model:view="view"
      :title="title"
      :views="views"
      :actions="actions"
      padding="sm"
    >
      <div class="wx-vacancies__bar">
        <wx-input
          v-model="typed"
          class="wx-vacancies__search"
          size="sm"
          clearable
          :placeholder="t('panel.search')"
          :aria-label="t('panel.search')"
        >
          <template #prefix><wx-icon name="search" size="sm" /></template>
        </wx-input>

        <!--
          The funnel, the way `WxTable` draws its own: the panel hangs off the indicator rather
          than off the button, because `WxAction` carries its tooltip beside itself and a trigger
          has to be one element (CLAUDE.md §4).
        -->
        <wx-popover :title="t('panel.filters')" :width="280" side="bottom" align="end">
          <template #trigger>
            <wx-indicator
              class="wx-vacancies__funnel"
              :value="applied.length"
              :hidden="applied.length === 0"
              type="primary"
              :label="t('panel.filters')"
            >
              <wx-action icon="filter" size="sm" :title="t('panel.filters')" />
            </wx-indicator>
          </template>

          <div class="wx-vacancies__filters">
            <wx-form-item v-if="!inBin" :label="t('panel.filter-status')">
              <wx-select
                :model-value="status === '' ? null : status"
                :options="statuses"
                :placeholder="t('panel.any-status')"
                clearable
                size="sm"
                @update:model-value="(value: unknown) => narrow('status', value)"
              />
            </wx-form-item>

            <wx-form-item :label="t('panel.filter-category')">
              <wx-select
                :model-value="category"
                :options="filters.categories.map((item) => ({ value: item.id, label: item.title }))"
                :placeholder="t('panel.any-category')"
                clearable
                size="sm"
                @update:model-value="(value: unknown) => narrow('category', value)"
              />
            </wx-form-item>

            <wx-button
              v-if="applied.length > 0"
              variant="text"
              size="sm"
              block
              @click="clearFilters"
            >
              <template #icon><wx-icon name="close" /></template>
              {{ t('panel.filters-reset') }}
            </wx-button>
          </div>
        </wx-popover>
      </div>

      <div v-if="applied.length > 0" class="wx-vacancies__applied">
        <wx-filter-chips :filters="applied" />
      </div>

      <wx-skeleton v-if="loading && rows.length === 0" class="wx-vacancies__loading" :rows="5" />

      <wx-empty
        v-else-if="rows.length === 0"
        :title="emptyText"
        :description="state === 'all' && !inBin && unfiltered ? t('panel.empty-help') : undefined"
      />

      <template v-else>
        <!--
          A grip and not the whole row: the row opens a vacancy, and a row that both opens and
          drags is one where one of the two happens by accident. No grips at all where the list
          is a selection — a handle that moves rows past invisible ones would be lying.
        -->
        <wx-sortable-list
          v-model="rows"
          class="wx-vacancies__list"
          :class="{ 'is-loading': loading }"
          plain
          item-key="id"
          :handle="sortable ? 'grip' : 'row'"
          :item-label="(item: VacancyRow) => item.title"
          :disabled="!sortable"
          @move="reorder"
        >
          <template #default="{ item }">
            <component
              :is="inBin ? 'div' : 'router-link'"
              class="wx-vacancy-row"
              :class="{ 'is-closed': muted(item) }"
              v-bind="inBin ? {} : { to: { path: `${props.base}/${item.id}`, query: route.query } }"
            >
              <span class="wx-vacancy-row__name">
                <span class="wx-vacancy-row__title">{{ item.title }}</span>
                <span class="wx-vacancy-row__address">{{ address(item) }}</span>
              </span>

              <span class="wx-vacancy-row__facts">
                <span class="wx-vacancy-row__where">
                  <wx-icon :name="item.workplace === 'remote' ? 'monitor' : 'map-pin'" size="sm" />
                  {{ where(item) }}
                </span>
                <span v-if="item.employment_types.length > 0" class="wx-vacancy-row__employment">
                  {{ employment(item) }}
                </span>
                <wx-tooltip
                  v-if="item.valid_through"
                  :content="t('panel.until-help', { date: longDay(item.valid_through, locale) })"
                >
                  <span class="wx-vacancy-row__until">
                    <wx-icon name="calendar" size="sm" />
                    {{ t('panel.until', { date: formatDay(item.valid_through, locale) }) }}
                  </span>
                </wx-tooltip>
              </span>

              <span class="wx-vacancy-row__state">
                <wx-badge v-for="chip in item.categories" :key="chip.id" size="sm" round>
                  {{ chip.title }}
                </wx-badge>
                <wx-tooltip
                  v-if="item.closed_reason"
                  :content="t(`panel.closed-${item.closed_reason}-help`)"
                >
                  <wx-badge type="warning" size="sm" round>
                    {{ t(`panel.closed-${item.closed_reason}`) }}
                  </wx-badge>
                </wx-tooltip>
                <wx-badge :type="badge(item.status)" dot size="sm">
                  {{ t(`panel.status-${item.status}`) }}
                </wx-badge>
                <wx-badge v-if="item.status === 'modified'" type="primary" size="sm" round>
                  {{ t('panel.edits') }}
                </wx-badge>
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
          class="wx-vacancies__note"
          type="info"
          :description="hint"
        />
      </template>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-vacancies {
  min-width: 0;
  container-type: inline-size;
}

.wx-vacancies__bar {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-4) var(--wx-space-8) var(--wx-space-8);
}

.wx-vacancies__search {
  flex: 1 1 240px;
  min-width: 0;
  max-width: 420px;
}

.wx-vacancies__funnel {
  flex: none;
}

.wx-vacancies__filters {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-vacancies__applied {
  padding: 0 var(--wx-space-8) var(--wx-space-8);
}

.wx-vacancies__loading {
  padding: var(--wx-space-8);
}

.wx-vacancies__list.is-loading {
  opacity: 0.6;
}

.wx-vacancies__list :deep(.wx-sortable-list__row) {
  padding-inline: var(--wx-space-8);
}

.wx-vacancies__list :deep(.wx-sortable-list__row:hover) {
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-sm);
}

.wx-vacancies__note {
  margin-block-start: var(--wx-space-8);
}

/*
 * Three columns on a wide list: who, the facts a candidate reads, the state. The facts are
 * the ones that fold first — under the name on a narrow list.
 */
.wx-vacancy-row {
  display: grid;
  /* Each row is a grid of its own, so the state column has a width rather than `auto`: sized by
     its badges, it would start the facts at a different place on every row. */
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 260px;
  grid-template-areas: 'name facts state';
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-16);
  flex: 1 1 auto;
  min-width: 0;
  padding-block: var(--wx-space-6);
  color: inherit;
  text-decoration: none;
}

.wx-vacancy-row__name {
  grid-area: name;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-vacancy-row__title {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-vacancy-row__address {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-vacancy-row__facts {
  grid-area: facts;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-2) var(--wx-space-12);
  min-width: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-vacancy-row__where,
.wx-vacancy-row__until {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  white-space: nowrap;
}

.wx-vacancy-row__employment {
  min-width: 0;
}

.wx-vacancy-row__state {
  grid-area: state;
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

/*
 * A closed vacancy in "all": the whole row a step quieter rather than a badge alone — "is this
 * still open" is answered by the eye running down the list, not by reading.
 */
.wx-vacancy-row.is-closed .wx-vacancy-row__title,
.wx-vacancy-row.is-closed .wx-vacancy-row__facts {
  color: var(--wx-text-muted);
}

/*
 * Narrow: the facts and the state go under the name rather than beside it, so the name keeps the
 * width. By the width of the list, not of the window — the panel's sidebar decides how much the
 * list has.
 */
@container (max-width: 720px) {
  .wx-vacancy-row {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
      'name'
      'facts'
      'state';
    align-items: start;
  }

  .wx-vacancy-row__state {
    justify-content: flex-start;
  }

  .wx-vacancies__list :deep(.wx-sortable-list__row) {
    gap: var(--wx-space-8);
    padding-inline: var(--wx-space-4);
  }

  .wx-vacancies__bar,
  .wx-vacancies__applied {
    padding-inline: var(--wx-space-4);
  }

  .wx-vacancies__search {
    max-width: none;
  }
}
</style>

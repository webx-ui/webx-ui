<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  confirm,
  toast,
  WxAlert,
  WxBadge,
  WxCard,
  WxEmpty,
  WxIcon,
  WxInput,
  WxListDetail,
  WxSelect,
  WxSkeleton,
  WxSortableList,
  type TabItem,
  type TabValue,
} from '@webx-ui/core'
import {
  useAdmin,
  useErrorText,
  useItemOrder,
  useTranslate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import { createTariffsApi, TARIFFS_API } from './api'
import { useTariffsMessages } from './i18n'
import { priceLine } from './price'
import TariffPane from './TariffPane.vue'
import type { TariffGroupRef, TariffRow, TariffSummary } from './types'

/**
 * The tariffs: all of them on the left, the one being written on the right (§5.2).
 *
 * The shape of the reviews, for the same reason: a price card is short, and the one next to edit
 * is usually the one beside it — "Growth" is priced against "Starter". On a phone the form is the
 * whole screen, in the drawer `WxListDetail` raises, and draws its own way back.
 *
 * Two orders, as with the reviews: without a filter a drag moves the common order; narrowed to
 * one group, the group's own; a search or the bin shows a selection, so the grips go away and the
 * line under the list says why. The query keeps the shared code's name, `category` (decision 14).
 *
 * Everything the screen is looking at lives in the address — the view, the group, the search and
 * the open tariff — so a link to a tariff is a link to it in its list.
 */
withDefaults(defineProps<{ base?: string }>(), { base: '/tariffs' })

const context = useAdmin()
const api = createTariffsApi(context)
const route = useRoute()
const router = useRouter()
useTariffsMessages()

const t = useTranslate('webx-tariffs')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<TariffRow[]>([])
const categories = ref<TariffGroupRef[]>([])
const loading = ref(true)

const canManage = computed(() => context.can('tariffs.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'tariffs')?.title ??
    t('module.tariffs'),
)

const view = computed<TabValue>({
  get: () => (route.query.view === 'trashed' ? 'trashed' : ''),
  set: (value) => {
    const query: LocationQueryRaw = {
      ...route.query,
      view: value === '' ? undefined : String(value),
    }

    // A tariff from the list is not a tariff from the bin: the form closes with the view.
    delete query.tariff
    void router.replace({ query })
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

/** The open tariff: a number, `'new'` for one not yet saved, or nothing. */
const selected = computed<number | 'new' | null>(() => {
  const raw = route.query.tariff

  if (raw === 'new') return canManage.value ? 'new' : null

  return typeof raw === 'string' && raw !== '' ? Number(raw) || null : null
})

/*
 * Open is "a tariff is chosen", and closing is un-choosing it — so the address, the list's
 * highlight and the drawer on a phone can never disagree about it.
 */
const open = computed({
  get: () => selected.value !== null,
  set: (value) => {
    if (!value) close()
  },
})

const views = computed<TabItem[]>(() => [
  { value: '', label: t('tariff.all') },
  { value: 'trashed', label: t('tariff.trashed'), icon: 'trash' },
])

const order = useItemOrder(TARIFFS_API, () => ({
  q: q.value,
  category: category.value,
  filtered: inBin.value,
}))

/* Dragged only by somebody who may write, and only in a list that is a whole order. */
const sortable = computed(() => canManage.value && order.sortable.value)

/* The panel's shared line says "category"; here the editor reads "group" (decision 14). */
const hint = computed(() =>
  order.mode.value === 'category' ? t('tariff.order-group') : order.hint.value,
)

const emptyText = computed(() => {
  if (q.value !== '') return t('tariff.empty-search')

  return inBin.value ? t('tariff.empty-bin') : t('tariff.empty')
})

async function load(): Promise<void> {
  loading.value = true

  try {
    const answer = await api.list({
      search: q.value,
      category: inBin.value ? null : category.value,
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

/* `replace` and not `push`: going through twenty tariffs is not twenty steps of history. */
function choose(id: number | 'new'): void {
  void router.replace({ query: { ...route.query, tariff: String(id) } })
}

function close(): void {
  const query = { ...route.query }

  delete query.tariff
  void router.replace({ query })
}

/** A new tariff was saved: it is a record now, and the list has it. */
function created(tariff: TariffSummary): void {
  choose(tariff.id)
  void load()
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
    toast.danger(message(error, t('tariff.reorder-failed')))
    await load()
  }
}

function actionsFor(row: TariffRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('tariff.restore'),
        run: () => void restore(row),
      },
    ]
  }

  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('tariff.delete'),
      danger: true,
      run: () => void remove(row),
    },
  ]
}

async function remove(row: TariffRow): Promise<void> {
  const agreed = await confirm({
    title: t('tariff.delete-title', { name: row.name }),
    message: t('tariff.delete-text'),
    confirmText: t('tariff.delete'),
    cancelText: t('tariff.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('tariff.deleted'))
    removed(row.id)
  } catch (error) {
    toast.danger(message(error))
  }
}

/** Gone from the list; and if it was the open one, the form goes with it. */
function removed(id: number): void {
  if (selected.value === id) close()

  void load()
}

async function restore(row: TariffRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('tariff.restored'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

/* The price as the site would print it — the language of the panel only decides the separators. */
function price(row: TariffRow): string {
  return priceLine(row, context.i18n.state.locale)
}
</script>

<template>
  <wx-list-screen v-model:view="view" :title="title" :views="views" :card="false">
    <wx-card class="wx-tariffs" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-tariffs__panes"
        :list-width="360"
        :detail-min="460"
        :detail-label="t('module.tariffs')"
      >
        <template #list>
          <div class="wx-tariffs__bar">
            <wx-input
              v-model="typed"
              class="wx-tariffs__search"
              size="sm"
              clearable
              :placeholder="t('tariff.search')"
              :aria-label="t('tariff.search')"
            >
              <template #prefix><wx-icon name="search" size="sm" /></template>
            </wx-input>

            <!-- Always in sight rather than behind a funnel: it is the one filter that decides
                 which order a drag writes, and the list says so right under it. -->
            <wx-select
              v-if="!inBin"
              :model-value="category"
              :options="categories.map((item) => ({ value: item.id, label: item.title }))"
              :placeholder="t('tariff.any-category')"
              :aria-label="t('tariff.filter-category')"
              clearable
              size="sm"
              @update:model-value="narrow"
            />
          </div>

          <!--
            A line of the list rather than a button in the head: the new tariff is written where
            it will stand, beside the ones it joins — and the form opens the same way any other
            tariff's does.
          -->
          <button
            v-if="canManage && !inBin"
            type="button"
            class="wx-tariffs__new"
            :class="{ 'is-current': selected === 'new' }"
            @click="choose('new')"
          >
            <wx-icon name="plus" size="sm" />
            {{ t('tariff.new') }}
          </button>

          <wx-skeleton v-if="loading && rows.length === 0" class="wx-tariffs__loading" :rows="5" />

          <wx-empty
            v-else-if="rows.length === 0"
            :title="emptyText"
            :description="!inBin && q === '' ? t('tariff.empty-help') : undefined"
          />

          <template v-else>
            <!-- A grip and not the whole row: the row opens a tariff, and a row that both opens
                 and drags is one where one of the two happens by accident. -->
            <wx-sortable-list
              v-model="rows"
              class="wx-tariffs__list"
              :class="{ 'is-loading': loading }"
              plain
              item-key="id"
              :handle="sortable ? 'grip' : 'row'"
              :item-label="(item: TariffRow) => item.name"
              :disabled="!sortable"
              @move="reorder"
            >
              <template #default="{ item }">
                <component
                  :is="inBin ? 'div' : 'button'"
                  :type="inBin ? undefined : 'button'"
                  class="wx-tariff-row"
                  :class="{ 'is-current': selected === item.id }"
                  @click="inBin ? undefined : choose(item.id)"
                >
                  <span class="wx-tariff-row__body">
                    <span class="wx-tariff-row__who">
                      <span class="wx-tariff-row__name">{{ item.name }}</span>
                      <!-- A mark and not a control: the switch is in the form. -->
                      <span
                        v-if="item.featured"
                        class="wx-tariff-row__star"
                        role="img"
                        :aria-label="t('tariff.featured')"
                      >
                        <wx-icon name="star" size="sm" />
                      </span>
                    </span>

                    <span
                      class="wx-tariff-row__price"
                      :class="{ 'is-missing': price(item) === '' }"
                    >
                      {{ price(item) || t('tariff.no-price') }}
                    </span>

                    <span class="wx-tariff-row__facts">
                      <wx-badge v-if="!item.published" dot size="sm">
                        {{ t('tariff.not-published') }}
                      </wx-badge>

                      <span v-if="item.badge" class="wx-tariff-row__badge">{{ item.badge }}</span>

                      <wx-badge v-for="chip in item.categories" :key="chip.id" size="sm" round>
                        {{ chip.title }}
                      </wx-badge>
                    </span>
                  </span>
                </component>
              </template>

              <template #actions="{ item }">
                <wx-row-menu :actions="actionsFor(item)" :label="item.name" />
              </template>
            </wx-sortable-list>

            <!-- What a drag does here, or why there is nothing to drag. -->
            <wx-alert
              v-if="canManage && !inBin"
              class="wx-tariffs__note"
              type="info"
              :description="hint"
            />
          </template>
        </template>

        <template #detail="{ inline, back }">
          <tariff-pane
            v-if="selected !== null"
            :id="selected === 'new' ? null : selected"
            :key="String(selected)"
            :category="category"
            :inline="inline"
            @back="back"
            @saved="load"
            @created="created"
            @removed="removed"
          />
        </template>

        <template #empty>
          <wx-empty
            :title="t('tariff.choose')"
            :description="canManage ? t('tariff.choose-help') : undefined"
          />
        </template>
      </wx-list-detail>
    </wx-card>
  </wx-list-screen>
</template>

<style>
/*
 * What scrolls here is the page, the way it does on every other list of the panel. The card's
 * corners are the screen's, and the panes paint their own background to the edge: `clip`, not
 * `hidden`, which would make this a scroll container and pin everything sticky inside to a box
 * that never moves (CLAUDE.md §4).
 */
.wx-tariffs > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-tariffs__bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-12) var(--wx-space-8);
}

.wx-tariffs__search {
  flex: 1 1 180px;
  min-width: 0;
}

/* The select is a fragment (it carries its dropdown beside it), so our scope attribute never
   reaches it: styled from our own wrapper (CLAUDE.md §4). */
.wx-tariffs__bar > :deep(.wx-select) {
  flex: 1 1 140px;
  min-width: 0;
}

.wx-tariffs__new {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  width: calc(100% - 2 * var(--wx-space-8));
  margin: 0 var(--wx-space-8) var(--wx-space-4);
  padding: var(--wx-space-8);
  border: 1px dashed var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  background: none;
  font: inherit;
  color: var(--wx-color-primary);
  text-align: start;
  cursor: pointer;
}

.wx-tariffs__new:hover,
.wx-tariffs__new.is-current {
  background: var(--wx-bg-subtle);
}

.wx-tariffs__loading {
  padding: var(--wx-space-12);
}

.wx-tariffs__list {
  padding-inline: var(--wx-space-8);
}

.wx-tariffs__list.is-loading {
  opacity: 0.6;
}

.wx-tariffs__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
  border-radius: var(--wx-radius-sm);
}

.wx-tariffs__list :deep(.wx-sortable-list__row:hover),
.wx-tariffs__list :deep(.wx-sortable-list__row:has(.is-current)) {
  background: var(--wx-bg-subtle);
}

.wx-tariffs__note {
  margin: var(--wx-space-8);
}

.wx-tariff-row {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-10);
  /* The row is the target: a short name should not leave half the line dead — and a button,
     unlike a link, is only as wide as what is in it until it is told otherwise. */
  flex: 1 1 auto;
  width: 100%;
  min-width: 0;
  padding-block: var(--wx-space-8);
  padding-inline: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  text-align: start;
}

button.wx-tariff-row {
  cursor: pointer;
}

.wx-tariff-row__body {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-tariff-row__who {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  column-gap: var(--wx-space-8);
  min-width: 0;
}

.wx-tariff-row__name {
  min-width: 0;
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-tariff-row.is-current .wx-tariff-row__name {
  color: var(--wx-color-primary);
}

.wx-tariff-row__star {
  display: inline-flex;
  flex: none;
  align-self: center;
  color: var(--wx-color-warning);
}

/* The price is what tells two cards apart in the list, so it is the second line, not a chip. */
.wx-tariff-row__price {
  overflow: hidden;
  font-size: var(--wx-font-size-sm);
  font-variant-numeric: tabular-nums;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-tariff-row__price.is-missing {
  color: var(--wx-text-muted);
  font-style: italic;
}

.wx-tariff-row__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  margin-top: var(--wx-space-2);
}

.wx-tariff-row__facts:empty {
  display: none;
}

.wx-tariff-row__badge {
  min-width: 0;
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}
</style>

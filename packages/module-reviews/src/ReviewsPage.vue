<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  confirm,
  toast,
  WxAlert,
  WxAvatar,
  WxBadge,
  WxCard,
  WxEmpty,
  WxIcon,
  WxInput,
  WxListDetail,
  WxSelect,
  WxSkeleton,
  WxSortableList,
  WxTooltip,
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
import { createReviewsApi, REVIEWS_API } from './api'
import { useReviewsMessages } from './i18n'
import ReviewPane from './ReviewPane.vue'
import type { ReviewCategoryRef, ReviewRow, ReviewSummary } from './types'

/**
 * The reviews: all of them on the left, the one being written on the right (§4.6, decision 11).
 *
 * The same shape as the FAQ, for the same reason: a review is typed in from a message in a
 * minute, and the next one is usually the one under it — a round trip through a page of its own
 * for every review is most of the time spent. On a phone the form is the whole screen, in the
 * drawer `WxListDetail` raises, and draws its own way back.
 *
 * The order is decision 6: without a filter a drag moves the common order; narrowed to one
 * category, the category's own; a search or the bin shows a selection, so the grips go away and
 * the line under the list says why.
 *
 * Everything the screen is looking at lives in the address — the view, the category, the search
 * and the open review — so a link to a review is a link to it in its list.
 */
withDefaults(defineProps<{ base?: string }>(), { base: '/reviews' })

const context = useAdmin()
const api = createReviewsApi(context)
const route = useRoute()
const router = useRouter()
useReviewsMessages()

const t = useTranslate('webx-reviews')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<ReviewRow[]>([])
const categories = ref<ReviewCategoryRef[]>([])
const loading = ref(true)

const canManage = computed(() => context.can('reviews.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'reviews')?.title ??
    t('module.reviews'),
)

const view = computed<TabValue>({
  get: () => (route.query.view === 'trashed' ? 'trashed' : ''),
  set: (value) => {
    const query: LocationQueryRaw = {
      ...route.query,
      view: value === '' ? undefined : String(value),
    }

    // A review from the list is not a review from the bin: the form closes with the view.
    delete query.review
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

/** The open review: a number, `'new'` for one not yet saved, or nothing. */
const selected = computed<number | 'new' | null>(() => {
  const raw = route.query.review

  if (raw === 'new') return canManage.value ? 'new' : null

  return typeof raw === 'string' && raw !== '' ? Number(raw) || null : null
})

/*
 * Open is "a review is chosen", and closing is un-choosing it — so the address, the list's
 * highlight and the drawer on a phone can never disagree about it.
 */
const open = computed({
  get: () => selected.value !== null,
  set: (value) => {
    if (!value) close()
  },
})

const views = computed<TabItem[]>(() => [
  { value: '', label: t('review.all') },
  { value: 'trashed', label: t('review.trashed'), icon: 'trash' },
])

const order = useItemOrder(REVIEWS_API, () => ({
  q: q.value,
  category: category.value,
  filtered: inBin.value,
}))

/* Dragged only by somebody who may write, and only in a list that is a whole order. */
const sortable = computed(() => canManage.value && order.sortable.value)

const emptyText = computed(() => {
  if (q.value !== '') return t('review.empty-search')

  return inBin.value ? t('review.empty-bin') : t('review.empty')
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

/* `replace` and not `push`: going through twenty reviews is not twenty steps of history. */
function choose(id: number | 'new'): void {
  void router.replace({ query: { ...route.query, review: String(id) } })
}

function close(): void {
  const query = { ...route.query }

  delete query.review
  void router.replace({ query })
}

/** A new review was saved: it is a record now, and the list has it. */
function created(review: ReviewSummary): void {
  choose(review.id)
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
    toast.danger(message(error, t('review.reorder-failed')))
    await load()
  }
}

function actionsFor(row: ReviewRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('review.restore'),
        run: () => void restore(row),
      },
    ]
  }

  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('review.delete'),
      danger: true,
      run: () => void remove(row),
    },
  ]
}

async function remove(row: ReviewRow): Promise<void> {
  const agreed = await confirm({
    title: t('review.delete-title', { name: row.name }),
    message: t('review.delete-text'),
    confirmText: t('review.delete'),
    cancelText: t('review.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('review.deleted'))
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

async function restore(row: ReviewRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('review.restored'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

/* Published and yet shown in no language: the one state the list has to point out. */
function unseen(row: ReviewRow): boolean {
  return row.published && row.locales.length === 0
}

/* Five places, the rating's filled: text rather than `WxRate`, which is a control, and a control
   inside the row's button is a button in a button. */
function stars(rating: number): string {
  const whole = Math.max(0, Math.min(5, Math.round(rating)))

  return '★'.repeat(whole) + '☆'.repeat(5 - whole)
}
</script>

<template>
  <wx-list-screen v-model:view="view" :title="title" :views="views" :card="false">
    <wx-card class="wx-reviews" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-reviews__panes"
        :list-width="360"
        :detail-min="460"
        :detail-label="t('module.reviews')"
      >
        <template #list>
          <div class="wx-reviews__bar">
            <wx-input
              v-model="typed"
              class="wx-reviews__search"
              size="sm"
              clearable
              :placeholder="t('review.search')"
              :aria-label="t('review.search')"
            >
              <template #prefix><wx-icon name="search" size="sm" /></template>
            </wx-input>

            <!-- Always in sight rather than behind a funnel: it is the one filter that decides
                 which order a drag writes, and the list says so right under it. -->
            <wx-select
              v-if="!inBin"
              :model-value="category"
              :options="categories.map((item) => ({ value: item.id, label: item.title }))"
              :placeholder="t('review.any-category')"
              :aria-label="t('review.filter-category')"
              clearable
              size="sm"
              @update:model-value="narrow"
            />
          </div>

          <!--
            A line of the list rather than a button in the head: the new review is written where
            it will stand, beside the ones it joins — and the form opens the same way any other
            review's does.
          -->
          <button
            v-if="canManage && !inBin"
            type="button"
            class="wx-reviews__new"
            :class="{ 'is-current': selected === 'new' }"
            @click="choose('new')"
          >
            <wx-icon name="plus" size="sm" />
            {{ t('review.new') }}
          </button>

          <wx-skeleton v-if="loading && rows.length === 0" class="wx-reviews__loading" :rows="5" />

          <wx-empty
            v-else-if="rows.length === 0"
            :title="emptyText"
            :description="!inBin && q === '' ? t('review.empty-help') : undefined"
          />

          <template v-else>
            <!-- A grip and not the whole row: the row opens a review, and a row that both opens
                 and drags is one where one of the two happens by accident. -->
            <wx-sortable-list
              v-model="rows"
              class="wx-reviews__list"
              :class="{ 'is-loading': loading }"
              plain
              item-key="id"
              :handle="sortable ? 'grip' : 'row'"
              :item-label="(item: ReviewRow) => item.name"
              :disabled="!sortable"
              @move="reorder"
            >
              <template #default="{ item }">
                <component
                  :is="inBin ? 'div' : 'button'"
                  :type="inBin ? undefined : 'button'"
                  class="wx-review-row"
                  :class="{ 'is-current': selected === item.id }"
                  @click="inBin ? undefined : choose(item.id)"
                >
                  <wx-avatar
                    class="wx-review-row__photo"
                    :src="item.photo?.thumb ?? undefined"
                    :name="item.name"
                    size="md"
                  />

                  <span class="wx-review-row__body">
                    <span class="wx-review-row__who">
                      <span class="wx-review-row__name">{{ item.name }}</span>
                      <span
                        v-if="item.rating !== null"
                        class="wx-review-row__stars"
                        role="img"
                        :aria-label="`${item.rating} / 5`"
                      >
                        {{ stars(item.rating) }}
                      </span>
                    </span>

                    <span v-if="item.job_title" class="wx-review-row__job">
                      {{ item.job_title }}
                    </span>

                    <span class="wx-review-row__facts">
                      <wx-badge v-if="!item.published" dot size="sm">
                        {{ t('review.not-published') }}
                      </wx-badge>
                      <wx-tooltip v-else-if="unseen(item)" :content="t('review.visible-nowhere')">
                        <wx-badge type="warning" dot size="sm">
                          {{ t('review.not-published') }}
                        </wx-badge>
                      </wx-tooltip>

                      <wx-badge v-for="chip in item.categories" :key="chip.id" size="sm" round>
                        {{ chip.title }}
                      </wx-badge>

                      <span
                        v-if="item.locales.length > 0"
                        class="wx-review-row__locales"
                        :aria-label="t('review.seen-in', { locales: item.locales.join(', ') })"
                      >
                        {{ item.locales.join(' · ') }}
                      </span>
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
              class="wx-reviews__note"
              type="info"
              :description="order.hint.value"
            />
          </template>
        </template>

        <template #detail="{ inline, back }">
          <review-pane
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
            :title="t('review.choose')"
            :description="canManage ? t('review.choose-help') : undefined"
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
.wx-reviews > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-reviews__bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-12) var(--wx-space-8);
}

.wx-reviews__search {
  flex: 1 1 180px;
  min-width: 0;
}

/* The select is a fragment (it carries its dropdown beside it), so our scope attribute never
   reaches it: styled from our own wrapper (CLAUDE.md §4). */
.wx-reviews__bar > :deep(.wx-select) {
  flex: 1 1 140px;
  min-width: 0;
}

.wx-reviews__new {
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

.wx-reviews__new:hover,
.wx-reviews__new.is-current {
  background: var(--wx-bg-subtle);
}

.wx-reviews__loading {
  padding: var(--wx-space-12);
}

.wx-reviews__list {
  padding-inline: var(--wx-space-8);
}

.wx-reviews__list.is-loading {
  opacity: 0.6;
}

.wx-reviews__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
  border-radius: var(--wx-radius-sm);
}

.wx-reviews__list :deep(.wx-sortable-list__row:hover),
.wx-reviews__list :deep(.wx-sortable-list__row:has(.is-current)) {
  background: var(--wx-bg-subtle);
}

.wx-reviews__note {
  margin: var(--wx-space-8);
}

.wx-review-row {
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

button.wx-review-row {
  cursor: pointer;
}

.wx-review-row__photo {
  flex: none;
}

.wx-review-row__body {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-review-row__who {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  column-gap: var(--wx-space-8);
  min-width: 0;
}

.wx-review-row__name {
  min-width: 0;
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-review-row.is-current .wx-review-row__name {
  color: var(--wx-color-primary);
}

.wx-review-row__stars {
  flex: none;
  color: var(--wx-color-warning);
  font-size: var(--wx-font-size-xs);
  letter-spacing: 0.08em;
}

.wx-review-row__job {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-review-row__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  margin-top: var(--wx-space-2);
}

.wx-review-row__facts:empty {
  display: none;
}

.wx-review-row__locales {
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  text-transform: uppercase;
}
</style>

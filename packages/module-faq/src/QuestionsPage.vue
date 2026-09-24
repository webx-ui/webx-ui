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
import { createFaqApi, FAQ_API } from './api'
import { useFaqMessages } from './i18n'
import QuestionPane from './QuestionPane.vue'
import type { QuestionCategoryRef, QuestionRow } from './types'

/**
 * The questions: the whole FAQ on the left, the one being written on the right (§4.5).
 *
 * The list and the form side by side rather than a page per question, because a question is
 * written in a minute and the next one is usually the one under it: a round trip through a
 * page of its own for every question is most of the time spent. On a phone the form is the
 * whole screen, in the drawer `WxListDetail` raises, and draws its own way back.
 *
 * The order is decision 4, as with services: without a filter a drag moves the common order;
 * narrowed to one category, the category's own; a search or the bin shows a selection, so the
 * grips go away and the line under the list says why.
 *
 * Everything the screen is looking at lives in the address — the view, the category, the
 * search and the open question — so a link to a question is a link to it in its list.
 */
withDefaults(defineProps<{ base?: string }>(), { base: '/faq' })

const context = useAdmin()
const api = createFaqApi(context)
const route = useRoute()
const router = useRouter()
useFaqMessages()

const t = useTranslate('webx-faq')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<QuestionRow[]>([])
const categories = ref<QuestionCategoryRef[]>([])
const loading = ref(true)

const canManage = computed(() => context.can('faq.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'faq')?.title ??
    t('module.questions'),
)

const view = computed<TabValue>({
  get: () => (route.query.view === 'trashed' ? 'trashed' : ''),
  set: (value) => {
    const query: LocationQueryRaw = {
      ...route.query,
      view: value === '' ? undefined : String(value),
    }

    // A question from the list is not a question from the bin: the form closes with the view.
    delete query.question
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

/** The open question: a number, `'new'` for one not yet saved, or nothing. */
const selected = computed<number | 'new' | null>(() => {
  const raw = route.query.question

  if (raw === 'new') return canManage.value ? 'new' : null

  return typeof raw === 'string' && raw !== '' ? Number(raw) || null : null
})

/*
 * Open is "a question is chosen", and closing is un-choosing it — so the address, the list's
 * highlight and the drawer on a phone can never disagree about it.
 */
const open = computed({
  get: () => selected.value !== null,
  set: (value) => {
    if (!value) close()
  },
})

const views = computed<TabItem[]>(() => [
  { value: '', label: t('question.all') },
  { value: 'trashed', label: t('question.bin'), icon: 'trash' },
])

const order = useItemOrder(FAQ_API, () => ({
  q: q.value,
  category: category.value,
  filtered: inBin.value,
}))

/* Dragged only by somebody who may write, and only in a list that is a whole order. */
const sortable = computed(() => canManage.value && order.sortable.value)

const emptyText = computed(() => {
  if (q.value !== '') return t('question.empty-search')

  return inBin.value ? t('question.empty-bin') : t('question.empty')
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

/* `replace` and not `push`: going through twenty questions is not twenty steps of history. */
function choose(id: number | 'new'): void {
  void router.replace({ query: { ...route.query, question: String(id) } })
}

function close(): void {
  const query = { ...route.query }

  delete query.question
  void router.replace({ query })
}

/** A new question was saved: it is a record now, and the list has it. */
function created(row: QuestionRow): void {
  choose(row.id)
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
    toast.danger(message(error, t('question.reorder-failed')))
    await load()
  }
}

function actionsFor(row: QuestionRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('question.restore'),
        run: () => void restore(row),
      },
    ]
  }

  return [
    {
      key: 'copy',
      icon: 'copy',
      label: t('question.copy-link'),
      run: () => void copyLink(row),
    },
    {
      key: 'delete',
      icon: 'trash',
      label: t('question.delete'),
      danger: true,
      run: () => void remove(row),
    },
  ]
}

async function remove(row: QuestionRow): Promise<void> {
  const agreed = await confirm({
    title: t('question.delete-title', { title: row.question }),
    message: t('question.delete-text'),
    confirmText: t('question.delete'),
    cancelText: t('question.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('question.deleted'))
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

async function restore(row: QuestionRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('question.restored'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

async function copyLink(row: QuestionRow): Promise<void> {
  try {
    await navigator.clipboard.writeText(`#${row.anchor}`)
    toast.success(t('question.link-copied'))
  } catch {
    // A browser that refuses the clipboard without a gesture it trusts.
  }
}

/* Published and yet shown in no language: the one state the list has to point out (decision 9). */
function unseen(row: QuestionRow): boolean {
  return row.published && row.locales.length === 0
}
</script>

<template>
  <wx-list-screen v-model:view="view" :title="title" :views="views" :card="false">
    <wx-card class="wx-faq" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-faq__panes"
        :list-width="360"
        :detail-min="460"
        :detail-label="t('module.questions')"
      >
        <template #list>
          <div class="wx-faq__bar">
            <wx-input
              v-model="typed"
              class="wx-faq__search"
              size="sm"
              clearable
              :placeholder="t('question.search')"
              :aria-label="t('question.search')"
            >
              <template #prefix><wx-icon name="search" size="sm" /></template>
            </wx-input>

            <!-- Always in sight rather than behind a funnel: it is the one filter that decides
                 which order a drag writes, and the list says so right under it. -->
            <wx-select
              v-if="!inBin"
              :model-value="category"
              :options="categories.map((item) => ({ value: item.id, label: item.title }))"
              :placeholder="t('question.any-category')"
              :aria-label="t('question.filter-category')"
              clearable
              size="sm"
              @update:model-value="narrow"
            />
          </div>

          <!--
            A line of the list rather than a button in the head: the new question is written
            where it will stand, beside the ones it joins — and the form opens the same way any
            other question's does.
          -->
          <button
            v-if="canManage && !inBin"
            type="button"
            class="wx-faq__new"
            :class="{ 'is-current': selected === 'new' }"
            @click="choose('new')"
          >
            <wx-icon name="plus" size="sm" />
            {{ t('question.new') }}
          </button>

          <wx-skeleton v-if="loading && rows.length === 0" class="wx-faq__loading" :rows="5" />

          <wx-empty
            v-else-if="rows.length === 0"
            :title="emptyText"
            :description="!inBin && q === '' ? t('question.empty-help') : undefined"
          />

          <template v-else>
            <!-- A grip and not the whole row: the row opens a question, and a row that both
                 opens and drags is one where one of the two happens by accident. -->
            <wx-sortable-list
              v-model="rows"
              class="wx-faq__list"
              :class="{ 'is-loading': loading }"
              plain
              item-key="id"
              :handle="sortable ? 'grip' : 'row'"
              :item-label="(item: QuestionRow) => item.question"
              :disabled="!sortable"
              @move="reorder"
            >
              <template #default="{ item }">
                <component
                  :is="inBin ? 'div' : 'button'"
                  :type="inBin ? undefined : 'button'"
                  class="wx-faq-row"
                  :class="{ 'is-current': selected === item.id }"
                  @click="inBin ? undefined : choose(item.id)"
                >
                  <span class="wx-faq-row__question">{{ item.question }}</span>

                  <span class="wx-faq-row__facts">
                    <wx-badge v-if="!item.published" dot size="sm">
                      {{ t('question.not-published') }}
                    </wx-badge>
                    <wx-tooltip v-else-if="unseen(item)" :content="t('question.seen-nowhere')">
                      <wx-badge type="warning" dot size="sm">
                        {{ t('question.not-published') }}
                      </wx-badge>
                    </wx-tooltip>

                    <wx-badge v-for="chip in item.categories" :key="chip.id" size="sm" round>
                      {{ chip.title }}
                    </wx-badge>

                    <span v-if="item.locales.length > 0" class="wx-faq-row__locales">
                      {{ item.locales.join(' · ') }}
                    </span>
                  </span>
                </component>
              </template>

              <template #actions="{ item }">
                <wx-row-menu :actions="actionsFor(item)" :label="item.question" />
              </template>
            </wx-sortable-list>

            <!-- What a drag does here, or why there is nothing to drag. -->
            <wx-alert
              v-if="canManage && !inBin"
              class="wx-faq__note"
              type="info"
              :description="order.hint.value"
            />
          </template>
        </template>

        <template #detail="{ inline, back }">
          <question-pane
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
            :title="t('question.choose')"
            :description="canManage ? t('question.choose-help') : undefined"
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
.wx-faq > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-faq__bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-12) var(--wx-space-8);
}

.wx-faq__search {
  flex: 1 1 180px;
  min-width: 0;
}

/* The select is a fragment (it carries its dropdown beside it), so our scope attribute never
   reaches it: styled from our own wrapper (CLAUDE.md §4). */
.wx-faq__bar > :deep(.wx-select) {
  flex: 1 1 140px;
  min-width: 0;
}

.wx-faq__new {
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

.wx-faq__new:hover,
.wx-faq__new.is-current {
  background: var(--wx-bg-subtle);
}

.wx-faq__loading {
  padding: var(--wx-space-12);
}

.wx-faq__list {
  padding-inline: var(--wx-space-8);
}

.wx-faq__list.is-loading {
  opacity: 0.6;
}

.wx-faq__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
  border-radius: var(--wx-radius-sm);
}

.wx-faq__list :deep(.wx-sortable-list__row:hover),
.wx-faq__list :deep(.wx-sortable-list__row:has(.is-current)) {
  background: var(--wx-bg-subtle);
}

.wx-faq__note {
  margin: var(--wx-space-8);
}

.wx-faq-row {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: var(--wx-space-4);
  /* The row is the target: a short question should not leave half the line dead — and a
     button, unlike a link, is only as wide as what is in it until it is told otherwise. */
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

button.wx-faq-row {
  cursor: pointer;
}

.wx-faq-row.is-current .wx-faq-row__question {
  color: var(--wx-color-primary);
}

/* Two lines, then an ellipsis: a question is a sentence, and one line of it is rarely enough to
   tell two apart. */
.wx-faq-row__question {
  display: -webkit-box;
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow-wrap: anywhere;
}

.wx-faq-row__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-faq-row__facts:empty {
  display: none;
}

.wx-faq-row__locales {
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  text-transform: uppercase;
}
</style>

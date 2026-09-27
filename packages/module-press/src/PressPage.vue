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
import { createPressApi, PRESS_API } from './api'
import { usePressMessages } from './i18n'
import OutletPane from './OutletPane.vue'
import type { OutletRow, OutletSummary } from './types'

/**
 * The outlets: all of them on the left, the one being written on the right (§4.9, decision 15).
 *
 * The shape of the reviews, for the same reason: an outlet is typed in from a clipping in a
 * minute, and the next one is usually under it. On a phone the form is the whole screen, in the
 * drawer `WxListDetail` raises, and draws its own way back.
 *
 * One order, the general one (decision 6): a drag moves it; a search or the bin shows a
 * selection, so the grips go away and the line under the list says why. The order of the
 * articles is not here — it is the order of the rows in the outlet's form.
 *
 * Everything the screen is looking at lives in the address — the view, the search and the open
 * outlet — so a link to an outlet is a link to it in its list.
 */
withDefaults(defineProps<{ base?: string }>(), { base: '/press' })

const context = useAdmin()
const api = createPressApi(context)
const route = useRoute()
const router = useRouter()
usePressMessages()

const t = useTranslate('webx-press')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<OutletRow[]>([])
const loading = ref(true)

const canManage = computed(() => context.can('press.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'press')?.title ??
    t('module.title'),
)

const view = computed<TabValue>({
  get: () => (route.query.view === 'trashed' ? 'trashed' : ''),
  set: (value) => {
    const query: LocationQueryRaw = {
      ...route.query,
      view: value === '' ? undefined : String(value),
    }

    // An outlet from the list is not an outlet from the bin: the form closes with the view.
    delete query.outlet
    void router.replace({ query })
  },
})

const inBin = computed(() => view.value === 'trashed')

const q = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))

/* What is typed, ahead of the address: the address follows it after a pause. */
const typed = ref(q.value)

/** The open outlet: a number, `'new'` for one not yet saved, or nothing. */
const selected = computed<number | 'new' | null>(() => {
  const raw = route.query.outlet

  if (raw === 'new') return canManage.value ? 'new' : null

  return typeof raw === 'string' && raw !== '' ? Number(raw) || null : null
})

/*
 * Open is "an outlet is chosen", and closing is un-choosing it — so the address, the list's
 * highlight and the drawer on a phone can never disagree about it.
 */
const open = computed({
  get: () => selected.value !== null,
  set: (value) => {
    if (!value) close()
  },
})

const views = computed<TabItem[]>(() => [
  { value: '', label: t('outlet.all') },
  { value: 'trashed', label: t('outlet.trashed'), icon: 'trash' },
])

const order = useItemOrder(PRESS_API, () => ({ q: q.value, filtered: inBin.value }))

/* Dragged only by somebody who may write, and only in a list that is the whole order. */
const sortable = computed(() => canManage.value && order.sortable.value)

const emptyText = computed(() => {
  if (q.value !== '') return t('outlet.empty-search')

  return inBin.value ? t('outlet.empty-bin') : t('outlet.empty')
})

async function load(): Promise<void> {
  loading.value = true

  try {
    const answer = await api.list({ search: q.value, trashed: inBin.value })

    rows.value = answer.data
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch(
  () => [view.value, q.value] as const,
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

/* `replace` and not `push`: going through twenty outlets is not twenty steps of history. */
function choose(id: number | 'new'): void {
  void router.replace({ query: { ...route.query, outlet: String(id) } })
}

function close(): void {
  const query = { ...route.query }

  delete query.outlet
  void router.replace({ query })
}

/** A new outlet was saved: it is a record now, and the list has it. */
function created(outlet: OutletSummary): void {
  choose(outlet.id)
  void load()
}

/**
 * The order on screen, written as a whole. The list is already in its new order
 * (`WxSortableList` reorders the model before it says so), so a failure has to put it back
 * rather than leave the screen disagreeing with the database.
 */
async function reorder(): Promise<void> {
  try {
    await order.move(rows.value.map((row) => row.id))
  } catch (error) {
    toast.danger(message(error, t('outlet.reorder-failed')))
    await load()
  }
}

function actionsFor(row: OutletRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('outlet.restore'),
        run: () => void restore(row),
      },
    ]
  }

  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('outlet.delete'),
      danger: true,
      run: () => void remove(row),
    },
  ]
}

async function remove(row: OutletRow): Promise<void> {
  const agreed = await confirm({
    title: t('outlet.delete-title', { name: row.title }),
    message: t('outlet.delete-text'),
    confirmText: t('outlet.delete'),
    cancelText: t('outlet.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('outlet.deleted'))
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

async function restore(row: OutletRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('outlet.restored'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

/* Published and yet shown in no language: the one state the list has to point out. */
function unseen(row: OutletRow): boolean {
  return row.published && row.locales.length === 0
}
</script>

<template>
  <wx-list-screen v-model:view="view" :title="title" :views="views" :card="false">
    <wx-card class="wx-press" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-press__panes"
        :list-width="360"
        :detail-min="520"
        :detail-label="t('module.title')"
      >
        <template #list>
          <div class="wx-press__bar">
            <wx-input
              v-model="typed"
              class="wx-press__search"
              size="sm"
              clearable
              :placeholder="t('outlet.search')"
              :aria-label="t('outlet.search')"
            >
              <template #prefix><wx-icon name="search" size="sm" /></template>
            </wx-input>
          </div>

          <!--
            A line of the list rather than a button in the head: the new outlet is written where
            it will stand, beside the ones it joins — and the form opens the way any other does.
          -->
          <button
            v-if="canManage && !inBin"
            type="button"
            class="wx-press__new"
            :class="{ 'is-current': selected === 'new' }"
            @click="choose('new')"
          >
            <wx-icon name="plus" size="sm" />
            {{ t('outlet.new') }}
          </button>

          <wx-skeleton v-if="loading && rows.length === 0" class="wx-press__loading" :rows="5" />

          <wx-empty
            v-else-if="rows.length === 0"
            :title="emptyText"
            :description="!inBin && q === '' ? t('outlet.empty-help') : undefined"
          />

          <template v-else>
            <!-- A grip and not the whole row: the row opens an outlet, and a row that both opens
                 and drags is one where one of the two happens by accident. -->
            <wx-sortable-list
              v-model="rows"
              class="wx-press__list"
              :class="{ 'is-loading': loading }"
              plain
              item-key="id"
              :handle="sortable ? 'grip' : 'row'"
              :item-label="(item: OutletRow) => item.title"
              :disabled="!sortable"
              @move="reorder"
            >
              <template #default="{ item }">
                <component
                  :is="inBin ? 'div' : 'button'"
                  :type="inBin ? undefined : 'button'"
                  class="wx-outlet-row"
                  :class="{ 'is-current': selected === item.id }"
                  @click="inBin ? undefined : choose(item.id)"
                >
                  <!-- A logo is wide as often as not: in a square it shrinks to a sliver, so it gets a
                       box of its own shape, and only an outlet without one gets initials. -->
                  <span v-if="item.logo?.thumb" class="wx-outlet-row__logo">
                    <img :src="item.logo.thumb" alt="" />
                  </span>
                  <wx-avatar
                    v-else
                    class="wx-outlet-row__initials"
                    shape="square"
                    :name="item.title"
                    size="md"
                  />

                  <span class="wx-outlet-row__body">
                    <span class="wx-outlet-row__head">
                      <span class="wx-outlet-row__title">{{ item.title }}</span>
                      <wx-tooltip v-if="item.featured" :content="t('outlet.featured')">
                        <wx-icon
                          class="wx-outlet-row__featured"
                          name="star"
                          size="sm"
                          :aria-label="t('outlet.featured')"
                        />
                      </wx-tooltip>
                    </span>

                    <span class="wx-outlet-row__facts">
                      <wx-badge v-if="!item.published" dot size="sm">
                        {{ t('outlet.not-published') }}
                      </wx-badge>
                      <wx-tooltip v-else-if="unseen(item)" :content="t('outlet.visible-nowhere')">
                        <wx-badge type="warning" dot size="sm">
                          {{ t('outlet.not-published') }}
                        </wx-badge>
                      </wx-tooltip>

                      <span class="wx-outlet-row__count">
                        {{
                          item.articles_count > 0
                            ? t('outlet.articles-count', { count: item.articles_count })
                            : t('outlet.no-articles')
                        }}
                      </span>

                      <span
                        v-if="item.locales.length > 0"
                        class="wx-outlet-row__locales"
                        :aria-label="t('outlet.seen-in', { locales: item.locales.join(', ') })"
                      >
                        {{ item.locales.join(' · ') }}
                      </span>
                    </span>
                  </span>
                </component>
              </template>

              <template #actions="{ item }">
                <wx-row-menu :actions="actionsFor(item)" :label="item.title" />
              </template>
            </wx-sortable-list>

            <!-- What a drag does here, or why there is nothing to drag. -->
            <wx-alert
              v-if="canManage && !inBin"
              class="wx-press__note"
              type="info"
              :description="order.hint.value"
            />
          </template>
        </template>

        <template #detail="{ inline, back }">
          <outlet-pane
            v-if="selected !== null"
            :id="selected === 'new' ? null : selected"
            :key="String(selected)"
            :inline="inline"
            @back="back"
            @saved="load"
            @created="created"
            @removed="removed"
          />
        </template>

        <template #empty>
          <wx-empty
            :title="t('outlet.choose')"
            :description="canManage ? t('outlet.choose-help') : undefined"
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
.wx-press > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-press__bar {
  display: flex;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-12) var(--wx-space-8);
}

.wx-press__search {
  flex: 1 1 180px;
  min-width: 0;
}

.wx-press__new {
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

.wx-press__new:hover,
.wx-press__new.is-current {
  background: var(--wx-bg-subtle);
}

.wx-press__loading {
  padding: var(--wx-space-12);
}

.wx-press__list {
  padding-inline: var(--wx-space-8);
}

.wx-press__list.is-loading {
  opacity: 0.6;
}

.wx-press__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
  border-radius: var(--wx-radius-sm);
}

.wx-press__list :deep(.wx-sortable-list__row:hover),
.wx-press__list :deep(.wx-sortable-list__row:has(.is-current)) {
  background: var(--wx-bg-subtle);
}

.wx-press__note {
  margin: var(--wx-space-8);
}

.wx-outlet-row {
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

button.wx-outlet-row {
  cursor: pointer;
}

.wx-outlet-row__initials {
  flex: none;
}

/* Twice as wide as the initials beside it, and the logo drawn whole inside: cropped, it loses
   its letters. */
.wx-outlet-row__logo {
  display: flex;
  flex: none;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 32px;
  overflow: hidden;
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
}

.wx-outlet-row__logo img {
  display: block;
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.wx-outlet-row__body {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-outlet-row__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-outlet-row__title {
  min-width: 0;
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-outlet-row.is-current .wx-outlet-row__title {
  color: var(--wx-color-primary);
}

.wx-outlet-row__featured {
  flex: none;
  color: var(--wx-color-warning);
}

.wx-outlet-row__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-outlet-row__locales {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  text-transform: uppercase;
}
</style>

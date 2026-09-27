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
import { createTeamApi, TEAM_API } from './api'
import { useTeamMessages } from './i18n'
import MemberPane from './MemberPane.vue'
import type { MemberRow, MemberSummary } from './types'

/**
 * The team: everybody on the left, the person being written on the right (§5.6, decision 11).
 *
 * The shape of the reviews without their categories: a person is typed in from a list sent by
 * the client in a minute, and the next one is usually the one under it. On a phone the form is
 * the whole screen, in the drawer `WxListDetail` raises, and draws its own way back.
 *
 * One order, the general one (decision 5): a drag moves it; a search or the bin shows a
 * selection, so the grips go away and the line under the list says why.
 *
 * Everything the screen is looking at lives in the address — the view, the search and the open
 * person — so a link to a person is a link to them in their list.
 */
withDefaults(defineProps<{ base?: string }>(), { base: '/team' })

const context = useAdmin()
const api = createTeamApi(context)
const route = useRoute()
const router = useRouter()
useTeamMessages()

const t = useTranslate('webx-team')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<MemberRow[]>([])
const loading = ref(true)

const canManage = computed(() => context.can('team.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'team')?.title ??
    t('module.team'),
)

const view = computed<TabValue>({
  get: () => (route.query.view === 'trashed' ? 'trashed' : ''),
  set: (value) => {
    const query: LocationQueryRaw = {
      ...route.query,
      view: value === '' ? undefined : String(value),
    }

    // A person from the list is not a person from the bin: the form closes with the view.
    delete query.member
    void router.replace({ query })
  },
})

const inBin = computed(() => view.value === 'trashed')

const q = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))

/* What is typed, ahead of the address: the address follows it after a pause. */
const typed = ref(q.value)

/** The open person: a number, `'new'` for one not yet saved, or nothing. */
const selected = computed<number | 'new' | null>(() => {
  const raw = route.query.member

  if (raw === 'new') return canManage.value ? 'new' : null

  return typeof raw === 'string' && raw !== '' ? Number(raw) || null : null
})

/*
 * Open is "a person is chosen", and closing is un-choosing them — so the address, the list's
 * highlight and the drawer on a phone can never disagree about it.
 */
const open = computed({
  get: () => selected.value !== null,
  set: (value) => {
    if (!value) close()
  },
})

const views = computed<TabItem[]>(() => [
  { value: '', label: t('member.all') },
  { value: 'trashed', label: t('member.trashed'), icon: 'trash' },
])

const order = useItemOrder(TEAM_API, () => ({ q: q.value, filtered: inBin.value }))

/* Dragged only by somebody who may write, and only in a list that is the whole order. */
const sortable = computed(() => canManage.value && order.sortable.value)

const emptyText = computed(() => {
  if (q.value !== '') return t('member.empty-search')

  return inBin.value ? t('member.empty-bin') : t('member.empty')
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

/* `replace` and not `push`: going through twenty people is not twenty steps of history. */
function choose(id: number | 'new'): void {
  void router.replace({ query: { ...route.query, member: String(id) } })
}

function close(): void {
  const query = { ...route.query }

  delete query.member
  void router.replace({ query })
}

/** A new person was saved: they are a record now, and the list has them. */
function created(member: MemberSummary): void {
  choose(member.id)
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
    toast.danger(message(error, t('member.reorder-failed')))
    await load()
  }
}

function actionsFor(row: MemberRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('member.restore'),
        run: () => void restore(row),
      },
    ]
  }

  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('member.delete'),
      danger: true,
      run: () => void remove(row),
    },
  ]
}

async function remove(row: MemberRow): Promise<void> {
  const agreed = await confirm({
    title: t('member.delete-title', { name: row.name }),
    message: t('member.delete-text'),
    confirmText: t('member.delete'),
    cancelText: t('member.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('member.deleted'))
    removed(row.id)
  } catch (error) {
    toast.danger(message(error))
  }
}

/** Gone from the list; and if they were the open one, the form goes with them. */
function removed(id: number): void {
  if (selected.value === id) close()

  void load()
}

async function restore(row: MemberRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('member.restored'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}
</script>

<template>
  <wx-list-screen v-model:view="view" :title="title" :views="views" :card="false">
    <wx-card class="wx-team" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-team__panes"
        :list-width="360"
        :detail-min="460"
        :detail-label="t('module.team')"
      >
        <template #list>
          <div class="wx-team__bar">
            <wx-input
              v-model="typed"
              class="wx-team__search"
              size="sm"
              clearable
              :placeholder="t('member.search')"
              :aria-label="t('member.search')"
            >
              <template #prefix><wx-icon name="search" size="sm" /></template>
            </wx-input>
          </div>

          <!--
            A line of the list rather than a button in the head: the new person is written where
            they will stand, beside the ones they join — and the form opens the way any other does.
          -->
          <button
            v-if="canManage && !inBin"
            type="button"
            class="wx-team__new"
            :class="{ 'is-current': selected === 'new' }"
            @click="choose('new')"
          >
            <wx-icon name="plus" size="sm" />
            {{ t('member.new') }}
          </button>

          <wx-skeleton v-if="loading && rows.length === 0" class="wx-team__loading" :rows="5" />

          <wx-empty
            v-else-if="rows.length === 0"
            :title="emptyText"
            :description="!inBin && q === '' ? t('member.empty-help') : undefined"
          />

          <template v-else>
            <!-- A grip and not the whole row: the row opens a person, and a row that both opens
                 and drags is one where one of the two happens by accident. -->
            <wx-sortable-list
              v-model="rows"
              class="wx-team__list"
              :class="{ 'is-loading': loading }"
              plain
              item-key="id"
              :handle="sortable ? 'grip' : 'row'"
              :item-label="(item: MemberRow) => item.name"
              :disabled="!sortable"
              @move="reorder"
            >
              <template #default="{ item }">
                <component
                  :is="inBin ? 'div' : 'button'"
                  :type="inBin ? undefined : 'button'"
                  class="wx-member-row"
                  :class="{ 'is-current': selected === item.id }"
                  @click="inBin ? undefined : choose(item.id)"
                >
                  <wx-avatar
                    class="wx-member-row__photo"
                    :src="item.photo?.thumb ?? undefined"
                    :name="item.name"
                    size="md"
                  />

                  <span class="wx-member-row__body">
                    <span class="wx-member-row__name">{{ item.name }}</span>

                    <span v-if="item.job_title" class="wx-member-row__job">
                      {{ item.job_title }}
                    </span>

                    <span v-if="!item.published" class="wx-member-row__facts">
                      <wx-badge dot size="sm">{{ t('member.not-published') }}</wx-badge>
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
              class="wx-team__note"
              type="info"
              :description="order.hint.value"
            />
          </template>
        </template>

        <template #detail="{ inline, back }">
          <member-pane
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
            :title="t('member.choose')"
            :description="canManage ? t('member.choose-help') : undefined"
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
.wx-team > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-team__bar {
  display: flex;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-12) var(--wx-space-8);
}

.wx-team__search {
  flex: 1 1 180px;
  min-width: 0;
}

.wx-team__new {
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

.wx-team__new:hover,
.wx-team__new.is-current {
  background: var(--wx-bg-subtle);
}

.wx-team__loading {
  padding: var(--wx-space-12);
}

.wx-team__list {
  padding-inline: var(--wx-space-8);
}

.wx-team__list.is-loading {
  opacity: 0.6;
}

.wx-team__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
  border-radius: var(--wx-radius-sm);
}

.wx-team__list :deep(.wx-sortable-list__row:hover),
.wx-team__list :deep(.wx-sortable-list__row:has(.is-current)) {
  background: var(--wx-bg-subtle);
}

.wx-team__note {
  margin: var(--wx-space-8);
}

.wx-member-row {
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

button.wx-member-row {
  cursor: pointer;
}

.wx-member-row__photo {
  flex: none;
}

.wx-member-row__body {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-member-row__name {
  min-width: 0;
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-member-row.is-current .wx-member-row__name {
  color: var(--wx-color-primary);
}

.wx-member-row__job {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-member-row__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  margin-top: var(--wx-space-2);
}
</style>
